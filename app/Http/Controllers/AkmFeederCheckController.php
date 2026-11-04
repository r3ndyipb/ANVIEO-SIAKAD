<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Services\LegacyFeederService;
use App\Support\AcademicCalendarScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use RuntimeException;

class AkmFeederCheckController extends Controller
{
    private const ROLES = ['admin', 'userprodi', 'usertu'];

    private const STUDENT_STATUSES = ['A', 'N', 'C', 'G'];

    public function index(Request $request): View
    {
        $user = $request->user();
        $this->authorizeRole($user);
        $filters = $request->validate([
            'program' => ['nullable', 'string', 'max:100'],
            'calendar' => ['nullable', 'string', 'max:100'],
        ]);
        $programs = $this->programs($user);
        $programId = $this->resolveProgram($user, $filters['program'] ?? null, false);
        $calendars = $programId === '' ? collect() : $this->calendars($programId);
        $calendar = null;
        if (filled($filters['calendar'] ?? null)) {
            $calendar = $calendars->firstWhere('id_kalender', $filters['calendar']);
            abort_if($calendar === null, 404, 'Kalender bukan milik program studi terpilih.');
        }

        return view('akm-feeder.index', [
            'user' => $user,
            'programs' => $programs,
            'programId' => $programId,
            'calendars' => $calendars,
            'calendar' => $calendar,
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function data(Request $request, LegacyFeederService $feeder): JsonResponse
    {
        $user = $request->user();
        $this->authorizeRole($user);
        $validated = $request->validate([
            'program' => ['required', 'string', 'max:100'],
            'calendar' => ['required', 'string', 'max:100'],
            'draw' => ['nullable', 'integer', 'min:0'],
            'start' => ['nullable', 'integer', 'min:0'],
            'length' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search.value' => ['nullable', 'string', 'max:120'],
            'order.0.column' => ['nullable', 'integer', 'min:0', 'max:4'],
            'order.0.dir' => ['nullable', 'in:asc,desc'],
        ]);
        $programId = $this->resolveProgram($user, $validated['program'], true);
        $calendar = $this->calendar($programId, $validated['calendar']);
        $query = $this->studentAkmQuery($programId, $calendar->id_kalender);
        $recordsTotal = (clone $query)->count();
        $search = trim((string) $request->input('search.value', ''));
        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $like = '%'.$search.'%';
                $builder->where('student.nim', 'like', $like)
                    ->orWhere('student.nama_mahasiswa', 'like', $like);
            });
        }
        $recordsFiltered = (clone $query)->count();
        $sortFields = ['student.nim', 'student.nim', 'student.nama_mahasiswa', 'calendar.namatahun', 'akm.status_mahasiswa'];
        $sortIndex = (int) $request->input('order.0.column', 2);
        $sortDirection = $request->input('order.0.dir') === 'desc' ? 'desc' : 'asc';
        $start = max(0, (int) $request->input('start', 0));
        $length = min(100, max(10, (int) $request->input('length', 25)));
        $students = $query->orderBy($sortFields[$sortIndex], $sortDirection)
            ->offset($start)->limit($length)
            ->get([
                'student.id_mahasiswa', 'student.nim', 'student.nama_mahasiswa',
                'akm.id_ipmhs', 'akm.status_mahasiswa', 'calendar.tahun', 'calendar.namatahun',
            ]);

        $feederError = null;
        try {
            $feederStatuses = $feeder->studentAcademicStatuses((string) $calendar->tahun, $students->map(fn (object $student): array => [
                'nim' => (string) $student->nim,
            ])->all());
        } catch (RuntimeException $exception) {
            Log::warning('Pengecekan status AKM Feeder gagal.', [
                'program_id' => $programId,
                'calendar_id' => $calendar->id_kalender,
                'exception' => $exception->getMessage(),
            ]);
            $feederStatuses = [];
            $feederError = 'Status Feeder gagal dimuat: '.$exception->getMessage();
        }
        $statusOptions = $this->statusOptions()->map(static fn (object $status): array => [
            'code' => (string) $status->kode_referensi,
            'label' => (string) $status->ket_referensi,
        ])->values();

        return response()->json([
            'draw' => max(0, (int) $request->input('draw', 0)),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'statusOptions' => $statusOptions,
            'feederError' => $feederError,
            'data' => $students->map(fn (object $student): array => [
                'id_mahasiswa' => $student->id_mahasiswa,
                'nim' => $student->nim,
                'nama_mahasiswa' => $student->nama_mahasiswa,
                'id_ipmhs' => $student->id_ipmhs,
                'status_simak' => $student->status_mahasiswa,
                'status_feeder' => $feederStatuses[(string) $student->nim] ?? null,
                'namatahun' => $student->namatahun,
            ]),
        ]);
    }

    public function updateStatus(Request $request, ActivityLogger $activityLogger): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $this->authorizeRole($user);
        $validated = $request->validate([
            'program' => ['required', 'string', 'max:100'],
            'calendar' => ['required', 'string', 'max:100'],
            'id_ipmhs' => ['required', 'string', 'max:100'],
            'status' => ['required', 'string', 'max:10'],
        ]);
        $programId = $this->resolveProgram($user, $validated['program'], true);
        $calendar = $this->calendar($programId, $validated['calendar']);
        $status = $this->statusOptions()->firstWhere('kode_referensi', $validated['status']);
        abort_if($status === null, 422, 'Status tidak ditemukan pada referensi SIMAK.');

        $connection = DB::connection('simak_main');
        $record = $connection->table('s1m4k_akm as akm')
            ->join('s1m4k_mastermhs as student', 'akm.id_mahasiswa', '=', 'student.id_mahasiswa')
            ->where('akm.id_ipmhs', $validated['id_ipmhs'])
            ->where('akm.id_prodi', $programId)
            ->where('akm.id_kalender', $calendar->id_kalender)
            ->where('student.id_prodi', $programId)
            ->first([
                'akm.id_ipmhs', 'akm.id_mahasiswa', 'akm.status_mahasiswa',
                'student.nim', 'student.nama_mahasiswa',
            ]);
        abort_if($record === null, 404);

        $connection->table('s1m4k_akm')
            ->where('id_ipmhs', $record->id_ipmhs)
            ->where('id_prodi', $programId)
            ->where('id_kalender', $calendar->id_kalender)
            ->update(['status_mahasiswa' => $validated['status']]);
        $activityLogger->record($user, 'Cek AKM SIMAK - Feeder', 'Mengubah status AKM SIMAK', [
            'status_mahasiswa' => [
                'sebelum' => $record->status_mahasiswa,
                'sesudah' => $validated['status'],
                'label' => $status->ket_referensi,
            ],
        ], (string) $record->id_ipmhs, [
            'nama' => $record->nama_mahasiswa,
            'nomor_identitas' => $record->nim,
            'program_studi' => $programId,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Status AKM SIMAK berhasil diperbarui.']);
        }

        return redirect()->route('akm-feeder.index', [
            'program' => $programId,
            'calendar' => $calendar->id_kalender,
        ])->with('status', 'Status AKM SIMAK berhasil diperbarui.');
    }

    private function studentAkmQuery(string $programId, string $calendarId)
    {
        return DB::connection('simak_main')->table('s1m4k_mastermhs as student')
            ->join('s1m4k_akm as akm', 'student.id_mahasiswa', '=', 'akm.id_mahasiswa')
            ->join('s1m4k_kalender as calendar', 'akm.id_kalender', '=', 'calendar.id_kalender')
            ->where('student.id_prodi', $programId)
            ->where('akm.id_prodi', $programId)
            ->where('akm.id_kalender', $calendarId)
            ->whereIn('student.id_status_mahasiswa', self::STUDENT_STATUSES);
    }

    private function programs(object $user): Collection
    {
        $query = DB::connection('simak_main')->table('s1m4k_masterprodi')
            ->orderBy('nama_program_studi')
            ->get(['id_prodi', 'nama_program_studi', 'nama_jenjang_pendidikan']);
        if ($user->role === 'admin') {
            return $query;
        }
        $programId = $this->requiredProgramId($user);

        return $query->where('id_prodi', $programId)->values();
    }

    private function resolveProgram(object $user, ?string $requestedProgram, bool $required): string
    {
        if (in_array($user->role, ['userprodi', 'usertu'], true)) {
            $programId = $this->requiredProgramId($user);
            abort_if(filled($requestedProgram) && $requestedProgram !== $programId, 404);
        } else {
            $programId = trim((string) $requestedProgram);
        }
        if ($programId === '') {
            abort_if($required, 422, 'Pilih program studi terlebih dahulu.');

            return '';
        }
        abort_unless($this->programs($user)->contains('id_prodi', $programId), 404);

        return $programId;
    }

    private function calendars(string $programId): Collection
    {
        return AcademicCalendarScope::excludeShortSemesters(
            DB::connection('simak_main')->table('s1m4k_kalender')
        )
            ->where('id_prodi', $programId)
            ->orderByDesc('tahun')
            ->get(['id_kalender', 'id_prodi', 'tahun', 'namatahun']);
    }

    private function calendar(string $programId, string $calendarId): object
    {
        $calendar = $this->calendars($programId)->firstWhere('id_kalender', $calendarId);
        abort_if($calendar === null, 404, 'Kalender bukan milik program studi terpilih.');

        return $calendar;
    }

    private function statusOptions(): Collection
    {
        return DB::connection('simak_main')->table('s1m4k_kodereferensi')
            ->whereIn('keterangan', ['GetStatusMahasiswa', 'GetJenisKeluar'])
            ->orderBy('kode_referensi')
            ->get(['kode_referensi', 'ket_referensi']);
    }

    private function requiredProgramId(object $user): string
    {
        $programId = trim((string) data_get($user, 'id_prodi'));
        abort_unless($programId !== '', 403);

        return $programId;
    }

    private function authorizeRole(object $user): void
    {
        abort_unless(in_array($user->role, self::ROLES, true), 403);
    }
}
