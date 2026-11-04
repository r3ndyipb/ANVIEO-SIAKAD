<?php

namespace App\Http\Controllers;

use App\Support\AcademicCalendarScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MonitoringKrsController extends Controller
{
    private const ROLES = ['admin', 'userprodi', 'usertu', 'pimuniv', 'pimfak'];

    private const ACTIVE_STUDENT_STATUSES = ['A', 'C', 'M'];

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless(in_array($user->role, self::ROLES, true), 403);

        $filters = $request->validate([
            'program' => ['nullable', 'string', 'max:100'],
            'calendar' => ['nullable', 'string', 'max:100'],
        ]);
        $programs = $this->programsFor($user);
        $selectedProgram = $this->resolveProgram($user, $filters['program'] ?? null);
        $calendars = collect();
        $selectedCalendar = null;
        if (filled($selectedProgram)) {
            $calendars = AcademicCalendarScope::excludeShortSemesters(
                DB::connection('simak_main')->table('s1m4k_kalender')
            )
                ->where('id_prodi', $selectedProgram)
                ->orderByDesc('tahun')
                ->get(['id_kalender', 'tahun', 'namatahun', 'status']);
            $selectedCalendarId = $filters['calendar'] ?? $calendars
                ->first(fn (object $calendar): bool => $calendar->status === 'Y')?->id_kalender
                ?? $calendars->first()?->id_kalender;
            if (filled($filters['calendar'] ?? null)) {
                abort_unless($calendars->contains(
                    fn (object $calendar): bool => (string) $calendar->id_kalender === (string) $filters['calendar']
                ), 404);
            }
            $selectedCalendar = $calendars->first(
                fn (object $calendar): bool => (string) $calendar->id_kalender === (string) $selectedCalendarId
            );
        }

        return view('monitoring-krs.index', [
            'user' => $user,
            'programs' => $programs,
            'selectedProgram' => $selectedProgram,
            'calendars' => $calendars,
            'selectedCalendar' => $selectedCalendar,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(in_array($user->role, self::ROLES, true), 403);
        $request->validate([
            'program' => ['nullable', 'string', 'max:100'],
            'calendar' => ['nullable', 'string', 'max:100'],
            'draw' => ['nullable', 'integer', 'min:0'],
            'start' => ['nullable', 'integer', 'min:0'],
            'length' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search.value' => ['nullable', 'string', 'max:120'],
            'order.0.column' => ['nullable', 'integer', 'min:0', 'max:6'],
            'order.0.dir' => ['nullable', 'in:asc,desc'],
        ]);

        $programId = $this->resolveProgram($user, $request->input('program'));
        $draw = max(0, (int) $request->input('draw', 0));
        if (blank($programId)) {
            return response()->json(['draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []]);
        }

        $calendarQuery = AcademicCalendarScope::excludeShortSemesters(
            DB::connection('simak_main')->table('s1m4k_kalender')
        )
            ->where('id_prodi', $programId);
        if (filled($request->input('calendar'))) {
            $calendarQuery->where('id_kalender', $request->input('calendar'));
        } else {
            $calendarQuery->orderByRaw("CASE WHEN status = 'Y' THEN 0 ELSE 1 END")
                ->orderByDesc('tahun');
        }
        $calendar = $calendarQuery->first(['id_kalender']);
        if ($calendar === null) {
            return response()->json(['draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []]);
        }

        $query = $this->studentQuery((string) $programId);
        $recordsTotal = (clone $query)->count();
        $search = trim((string) $request->input('search.value', ''));
        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('student.nim', 'like', '%'.$search.'%')
                    ->orWhere('student.nama_mahasiswa', 'like', '%'.$search.'%');
            });
        }
        $recordsFiltered = (clone $query)->count();

        $orderFields = [
            1 => 'student.nim',
            2 => 'student.nama_mahasiswa',
            3 => 'program.nama_program_studi',
            4 => 'student.nama_status_mahasiswa',
        ];
        $orderColumn = (int) $request->input('order.0.column', 1);
        $orderField = $orderFields[$orderColumn] ?? 'student.nim';
        $orderDirection = $request->input('order.0.dir') === 'desc' ? 'desc' : 'asc';
        $start = max(0, (int) $request->input('start', 0));
        $length = min(100, max(10, (int) $request->input('length', 25)));

        $rows = $query->orderBy($orderField, $orderDirection)
            ->offset($start)
            ->limit($length)
            ->select([
                'student.id_mahasiswa', 'student.nim', 'student.nama_mahasiswa',
                'student.id_status_mahasiswa', 'student.nama_status_mahasiswa',
                'program.nama_program_studi',
            ])
            ->get();
        $page = new LengthAwarePaginator($rows, $recordsFiltered, $length, (int) floor($start / $length) + 1);
        $this->decorateKrsStatus($page, $calendar);
        $sksByStudent = DB::connection('simak_main')->table('s1m4k_krs as krs')
            ->leftJoin('s1m4k_mastermk as course', 'krs.id_matkul', '=', 'course.id_matkul')
            ->where('krs.id_prodi', $programId)
            ->where('krs.id_kalender', $calendar->id_kalender)
            ->whereIn('krs.id_mahasiswa', $page->getCollection()->pluck('id_mahasiswa'))
            ->groupBy('krs.id_mahasiswa')
            ->selectRaw('krs.id_mahasiswa, SUM(COALESCE(course.sks_mata_kuliah, 0)) as sks_diprogram')
            ->get()
            ->keyBy('id_mahasiswa');

        $data = $page->getCollection()->map(function (object $student) use ($calendar, $sksByStudent): array {
            $canPrint = $student->has_krs
                && $student->is_approved_by_program;

            return [
                'nim' => $student->nim,
                'nama_mahasiswa' => $student->nama_mahasiswa,
                'nama_program_studi' => $student->nama_program_studi,
                'nama_status_mahasiswa' => $student->nama_status_mahasiswa ?: $student->id_status_mahasiswa,
                'sks_diprogram' => (float) ($sksByStudent->get($student->id_mahasiswa)?->sks_diprogram ?? 0),
                'krs_status' => $student->krs_status,
                'status_class' => ! $student->has_krs ? 'is-empty' : ($student->is_approved_by_program ? 'is-approved' : 'is-pending'),
                'proses' => $canPrint ? 'Cetak KRS' : 'View Only',
                'print_url' => $canPrint
                    ? route('krs.print', ['student' => $student->id_mahasiswa, 'calendar' => $calendar->id_kalender])
                    : null,
            ];
        });

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    private function programsFor(object $user)
    {
        return DB::connection('simak_main')->table('s1m4k_masterprodi')
            ->when($user->role === 'pimfak', function ($query) use ($user): void {
                $facultyId = data_get($user, 'id_fakultas');
                if (blank($facultyId)) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->where('id_fakultas', $facultyId);
                }
            })
            ->when(in_array($user->role, ['userprodi', 'usertu'], true), fn ($query) => $query->where('id_prodi', data_get($user, 'id_prodi')))
            ->orderBy('nama_program_studi')
            ->get(['id_prodi', 'nama_program_studi', 'nama_jenjang_pendidikan', 'id_fakultas']);
    }

    private function resolveProgram(object $user, ?string $requestedProgram): ?string
    {
        if (in_array($user->role, ['userprodi', 'usertu'], true)) {
            $programId = (string) data_get($user, 'id_prodi', '');
            abort_if($programId === '', 403);

            return $programId;
        }
        if (blank($requestedProgram)) {
            return null;
        }

        $program = DB::connection('simak_main')->table('s1m4k_masterprodi')
            ->where('id_prodi', $requestedProgram)
            ->when($user->role === 'pimfak', function ($query) use ($user): void {
                $facultyId = data_get($user, 'id_fakultas');
                if (blank($facultyId)) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->where('id_fakultas', $facultyId);
                }
            })
            ->exists();
        abort_unless($program, 404);

        return $requestedProgram;
    }

    private function studentQuery(string $programId)
    {
        return DB::connection('simak_main')->table('s1m4k_mastermhs as student')
            ->leftJoin('s1m4k_masterprodi as program', 'student.id_prodi', '=', 'program.id_prodi')
            ->where('student.id_prodi', $programId)
            ->whereIn('student.id_status_mahasiswa', self::ACTIVE_STUDENT_STATUSES)
            ->whereRaw("TRIM(COALESCE(student.nim, '')) <> ''")
            ->whereRaw("TRIM(COALESCE(student.id_mahasiswa, '')) <> ''");
    }

    private function decorateKrsStatus(LengthAwarePaginator $students, ?object $activeCalendar): void
    {
        $rows = $students->getCollection();
        if ($rows->isEmpty()) {
            return;
        }

        $studentIds = $rows->pluck('id_mahasiswa')->all();
        $krsStudentIds = DB::connection('simak_main')->table('s1m4k_krs')
            ->where('id_kalender', $activeCalendar->id_kalender)
            ->whereIn('id_mahasiswa', $studentIds)
            ->distinct()
            ->pluck('id_mahasiswa')
            ->map(fn ($id): string => (string) $id);
        $approvedByProgramIds = DB::connection('simak_main')->table('s1m4k_pengesahankrs')
            ->where('id_kalender', $activeCalendar->id_kalender)
            ->whereIn('id_mahasiswa', $studentIds)
            ->whereNotNull('nama_kpro')
            ->whereRaw("TRIM(nama_kpro) <> ''")
            ->distinct()
            ->pluck('id_mahasiswa')
            ->map(fn ($id): string => (string) $id);
        $rows->each(function (object $student) use ($krsStudentIds, $approvedByProgramIds): void {
            $student->has_krs = $krsStudentIds->contains((string) $student->id_mahasiswa);
            $student->is_approved_by_program = $approvedByProgramIds->contains((string) $student->id_mahasiswa);
            $student->krs_status = ! $student->has_krs
                ? 'Belum Isi KRS'
                : ($student->is_approved_by_program
                    ? 'KRS Telah Disahkan Prodi'
                    : 'KRS Belum Disahkan Prodi');
        });

        $students->setCollection($rows);
    }
}
