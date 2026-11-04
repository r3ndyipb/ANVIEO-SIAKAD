<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Support\AcademicCalendarScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class StudentAcademicActivityController extends Controller
{
    private const ROLES = ['admin', 'userprodi', 'usertu'];

    public function index(Request $request): View
    {
        $user = $request->user();
        $this->authorizeRole($user);
        $filters = $request->validate([
            'program' => ['nullable', 'string', 'max:100'],
            'calendar' => ['nullable', 'string', 'max:100'],
        ]);
        $programs = $this->programs($user);
        $programId = $user->role === 'admin'
            ? (string) ($filters['program'] ?? '')
            : trim((string) data_get($user, 'id_prodi'));
        if ($user->role !== 'admin') {
            abort_unless($programId !== '', 403);
        }
        if ($programId !== '') {
            abort_unless($programs->contains('id_prodi', $programId), 404);
        }
        $calendars = $programId !== '' ? $this->calendars($programId) : collect();
        $calendar = null;
        if (filled($filters['calendar'] ?? null)) {
            $calendar = $calendars->firstWhere('id_kalender', $filters['calendar']);
            abort_if($calendar === null, 404, 'Kalender bukan milik program studi terpilih.');
        }

        $rows = collect();
        $locked = false;
        $statusOptions = collect();
        if ($calendar !== null) {
            $connection = DB::connection('simak_main');
            $rowsQuery = $this->rowsQuery($programId, (string) $calendar->id_kalender, $user->role === 'admin');
            $locked = $connection->table('s1m4k_akm')
                ->where('id_prodi', $programId)
                ->where('id_kalender', $calendar->id_kalender)
                ->where('simpan', 1)
                ->exists();
            $rows = $rowsQuery->paginate(25)->withQueryString();
            $statusOptions = $connection->table('s1m4k_kodereferensi')
                ->whereIn('keterangan', ['GetStatusMahasiswa', 'GetJenisKeluar'])
                ->orderBy('kode_referensi')
                ->get(['kode_referensi', 'ket_referensi']);
        }

        return view('student-akm.index', compact(
            'user', 'programs', 'programId', 'calendars', 'calendar', 'rows', 'locked', 'statusOptions'
        ));
    }

    public function exportExcel(Request $request): Response
    {
        $user = $request->user();
        $this->authorizeRole($user);
        $validated = $request->validate([
            'program' => [$user->role === 'admin' ? 'required' : 'nullable', 'string', 'max:100'],
            'calendar' => ['required', 'string', 'max:100'],
        ]);
        $programId = $this->programIdForUser($user, (string) ($validated['program'] ?? ''));
        $calendar = $this->calendar($programId, $validated['calendar']);
        $rows = $this->rowsQuery($programId, (string) $calendar->id_kalender, $user->role === 'admin')->get();
        $statusLabels = DB::connection('simak_main')->table('s1m4k_kodereferensi')
            ->whereIn('keterangan', ['GetStatusMahasiswa', 'GetJenisKeluar'])
            ->pluck('ket_referensi', 'kode_referensi');

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Aktivitas Kuliah');
        $headers = ['NPM', 'Nama Mahasiswa', 'Tahun Akademik', 'IPS', 'IPK', 'SKS Semester', 'SKS Program', 'SKS Lulus', 'Status'];
        foreach ($headers as $index => $header) {
            $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($index + 1).'1', $header, DataType::TYPE_STRING);
        }
        $sheet->getStyle('A1:I1')->getFont()->setBold(true);
        foreach ($rows as $index => $row) {
            $status = (string) $row->status_mahasiswa;
            $values = [
                $row->nim,
                $row->nama_mahasiswa,
                $calendar->namatahun ?: $calendar->tahun,
                number_format((float) $row->ips, 2),
                number_format((float) $row->ipk, 2),
                number_format((float) $row->sks_semester, 2),
                number_format((float) $row->sks_program, 2),
                number_format((float) $row->sks_lulus, 2),
                $status.' · '.($statusLabels->get($status) ?? $status),
            ];
            foreach ($values as $column => $value) {
                $sheet->setCellValueExplicit(
                    Coordinate::stringFromColumnIndex($column + 1).($index + 2),
                    (string) ($value ?? ''),
                    DataType::TYPE_STRING
                );
            }
        }

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $contents = (string) ob_get_clean();
        $spreadsheet->disconnectWorksheets();

        return response($contents, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="aktivitas-kuliah-'.$calendar->tahun.'.xlsx"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function generate(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeRole($user);
        $validated = $request->validate([
            'program' => ['required', 'string', 'max:100'],
            'calendar' => ['required', 'string', 'max:100'],
        ]);
        $programId = $this->programIdForUser($user, $validated['program']);
        $calendar = $this->calendar($programId, $validated['calendar']);
        $connection = DB::connection('simak_main');
        abort_if($connection->table('s1m4k_akm')->where('id_prodi', $programId)
            ->where('id_kalender', $calendar->id_kalender)->where('simpan', 1)->exists(), 422,
            'Data AKM sudah disimpan permanen dan tidak dapat digenerate ulang.');

        $students = $connection->table('s1m4k_mastermhs')
            ->where('id_prodi', $programId)
            ->where('id_periode_masuk', '>=', (string) ((int) $calendar->tahun - 100))
            ->orderBy('nama_mahasiswa')
            ->get(['id_mahasiswa', 'nim', 'nama_mahasiswa']);
        $oldRows = $connection->table('s1m4k_akm')
            ->where('id_prodi', $programId)
            ->where('id_kalender', $calendar->id_kalender)
            ->get();

        $generated = [];
        foreach ($students as $student) {
            $generated[] = $this->generatedRow($student, $programId, $calendar);
        }

        $connection->transaction(function () use ($connection, $programId, $calendar, $generated, $students, $user, $activityLogger, $oldRows): void {
            $connection->table('s1m4k_akm')
                ->where('id_prodi', $programId)
                ->where('id_kalender', $calendar->id_kalender)
                ->delete();
            if ($generated !== []) {
                $connection->table('s1m4k_akm')->insert($generated);
            }
            $activityLogger->record($user, 'Aktivitas Kuliah Mahasiswa', 'Generate AKM', [
                'jumlah_mahasiswa' => $students->count(),
                'data_sebelumnya' => $oldRows->map(fn (object $row): array => (array) $row)->all(),
                'data_baru' => $generated,
            ], (string) $calendar->id_kalender, [
                'program_studi' => $programId,
                'tahun_akademik' => $calendar->namatahun,
            ]);
        });

        return redirect()->route('student-akm.index', [
            'program' => $programId,
            'calendar' => $calendar->id_kalender,
        ])->with('status', 'Data aktivitas kuliah mahasiswa berhasil digenerate.');
    }

    public function updateStatus(Request $request, string $recordId, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeRole($user);
        $validated = $request->validate([
            'status' => ['required', 'string', 'max:50'],
            'program' => ['required', 'string', 'max:100'],
            'calendar' => ['required', 'string', 'max:100'],
        ]);
        $programId = $this->programIdForUser($user, $validated['program']);
        $calendar = $this->calendar($programId, $validated['calendar']);
        $connection = DB::connection('simak_main');
        $statusLabel = $connection->table('s1m4k_kodereferensi')
            ->whereIn('keterangan', ['GetStatusMahasiswa', 'GetJenisKeluar'])
            ->where('kode_referensi', $validated['status'])
            ->value('ket_referensi');
        abort_if($statusLabel === null, 422, 'Status mahasiswa tidak tersedia pada referensi SIMAK.');
        $record = $connection->table('s1m4k_akm')
            ->where('id_ipmhs', $recordId)
            ->where('id_prodi', $programId)
            ->where('id_kalender', $calendar->id_kalender)
            ->first();
        abort_if($record === null, 404);
        abort_if((int) $record->simpan === 1, 422, 'Data AKM sudah disimpan permanen dan tidak dapat diubah.');

        $connection->transaction(function () use ($connection, $record, $validated, $statusLabel, $user, $activityLogger, $programId): void {
            $connection->table('s1m4k_akm')->where('id_ipmhs', $record->id_ipmhs)
                ->update(['status_mahasiswa' => $validated['status']]);
            $connection->table('s1m4k_mastermhs')->where('id_mahasiswa', $record->id_mahasiswa)
                ->where('id_prodi', $programId)
                ->update([
                    'id_status_mahasiswa' => $validated['status'],
                    'nama_status_mahasiswa' => $statusLabel,
                ]);
            $activityLogger->record($user, 'Aktivitas Kuliah Mahasiswa', 'Mengubah status mahasiswa', [
                'status_mahasiswa' => [
                    'sebelum' => $record->status_mahasiswa,
                    'sesudah' => $validated['status'],
                    'label' => $statusLabel,
                ],
            ], (string) $record->id_ipmhs, [
                'nama' => $connection->table('s1m4k_mastermhs')
                    ->where('id_mahasiswa', $record->id_mahasiswa)
                    ->value('nama_mahasiswa'),
                'program_studi' => $programId,
            ]);
        });

        return redirect()->route('student-akm.index', [
            'program' => $programId,
            'calendar' => $calendar->id_kalender,
        ])->with('status', 'Status aktivitas kuliah mahasiswa berhasil diperbarui.');
    }

    public function lock(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeRole($user);
        $validated = $request->validate([
            'program' => ['required', 'string', 'max:100'],
            'calendar' => ['required', 'string', 'max:100'],
        ]);
        $programId = $this->programIdForUser($user, $validated['program']);
        $calendar = $this->calendar($programId, $validated['calendar']);
        $connection = DB::connection('simak_main');
        $count = $connection->table('s1m4k_akm')->where('id_prodi', $programId)
            ->where('id_kalender', $calendar->id_kalender)->count();
        abort_if($count === 0, 422, 'Generate data AKM sebelum menyimpan permanen.');

        $connection->transaction(function () use ($connection, $programId, $calendar, $user, $activityLogger): void {
            $connection->table('s1m4k_akm')
                ->where('id_prodi', $programId)
                ->where('id_kalender', $calendar->id_kalender)
                ->update(['simpan' => 1]);
            $activityLogger->record($user, 'Aktivitas Kuliah Mahasiswa', 'Simpan permanen AKM', [
                'id_kalender' => $calendar->id_kalender,
                'tahun' => $calendar->tahun,
            ], (string) $calendar->id_kalender, [
                'program_studi' => $programId,
                'tahun_akademik' => $calendar->namatahun,
            ]);
        });

        return redirect()->route('student-akm.index', [
            'program' => $programId,
            'calendar' => $calendar->id_kalender,
        ])->with('status', 'Data aktivitas kuliah berhasil disimpan permanen.');
    }

    private function generatedRow(object $student, string $programId, object $calendar): array
    {
        $connection = DB::connection('simak_main');
        $currentCourses = $connection->table('s1m4k_krs as krs')
            ->join('s1m4k_mastermk as course', 'krs.id_matkul', '=', 'course.id_matkul')
            ->where('krs.id_mahasiswa', $student->id_mahasiswa)
            ->where('krs.tahun', $calendar->tahun)
            ->where('krs.krs_reguler', 'Y')
            ->get(['krs.nilai_bobot', 'course.sks_mata_kuliah']);
        $semesterCredits = (float) $currentCourses->sum(fn (object $row): float => (float) $row->sks_mata_kuliah);
        $semesterQuality = (float) $currentCourses->sum(
            fn (object $row): float => (float) $row->nilai_bobot * (float) $row->sks_mata_kuliah
        );
        $previousCourses = $connection->table('s1m4k_krs as krs')
            ->join('s1m4k_mastermk as course', 'krs.id_matkul', '=', 'course.id_matkul')
            ->where('krs.id_mahasiswa', $student->id_mahasiswa)
            ->where('krs.tahun', '<', $calendar->tahun)
            ->select('krs.id_matkul', DB::raw('MAX(krs.nilai_bobot) as nilai_bobot'), 'course.sks_mata_kuliah')
            ->groupBy('krs.id_matkul', 'course.sks_mata_kuliah')
            ->get();
        $programCredits = (float) $previousCourses->sum(fn (object $row): float => (float) $row->sks_mata_kuliah);
        $programQuality = (float) $previousCourses->sum(
            fn (object $row): float => (float) $row->nilai_bobot * (float) $row->sks_mata_kuliah
        );
        $passedCredits = (float) $previousCourses
            ->filter(fn (object $row): bool => (float) $row->nilai_bobot > 0)
            ->sum(fn (object $row): float => (float) $row->sks_mata_kuliah);
        $registered = $connection->table('s1m4k_registrasiulang')
            ->where('id_mahasiswa', $student->id_mahasiswa)
            ->where('id_kalender', $calendar->id_kalender)
            ->exists();

        return [
            'id_ipmhs' => (string) Str::uuid(),
            'id_mahasiswa' => $student->id_mahasiswa,
            'id_prodi' => $programId,
            'id_kalender' => $calendar->id_kalender,
            'tahun' => $calendar->tahun,
            'ips' => number_format($semesterCredits > 0 ? $semesterQuality / $semesterCredits : 0, 2, '.', ''),
            'ipk' => number_format($programCredits > 0 ? $programQuality / $programCredits : 0, 2, '.', ''),
            'sks_semester' => number_format($semesterCredits, 2, '.', ''),
            'sks_program' => number_format($programCredits, 2, '.', ''),
            'sks_lulus' => number_format($passedCredits, 2, '.', ''),
            'status_mahasiswa' => $registered ? 'A' : 'N',
            'update_akm' => 0,
            'simpan' => 0,
            'flag_feeder' => 0,
        ];
    }

    private function programs(object $user): Collection
    {
        $query = DB::connection('simak_main')->table('s1m4k_masterprodi')
            ->orderBy('nama_program_studi')
            ->select(['id_prodi', 'id_fakultas', 'nama_program_studi', 'nama_jenjang_pendidikan']);
        if ($user->role !== 'admin') {
            $programId = trim((string) data_get($user, 'id_prodi'));
            abort_unless($programId !== '', 403);
            $query->where('id_prodi', $programId);
        }

        return $query->get();
    }

    private function programIdForUser(object $user, string $requestedProgram): string
    {
        $programId = $user->role === 'admin'
            ? $requestedProgram
            : trim((string) data_get($user, 'id_prodi'));
        abort_unless($programId !== '', 403);
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
            ->get(['id_kalender', 'id_prodi', 'tahun', 'namatahun', 'status']);
    }

    private function rowsQuery(string $programId, string $calendarId, bool $isAdmin)
    {
        return DB::connection('simak_main')->table('s1m4k_akm as akm')
            ->leftJoin('s1m4k_mastermhs as student', 'akm.id_mahasiswa', '=', 'student.id_mahasiswa')
            ->where('student.id_prodi', $programId)
            ->where('akm.id_prodi', $programId)
            ->where('akm.id_kalender', $calendarId)
            ->whereIn(DB::raw("LOWER(TRIM(COALESCE(student.nama_status_mahasiswa, '')))"), $isAdmin
                ? ['aktif', 'cuti', 'dikeluarkan', 'non-aktif', 'kampus merdeka']
                : ['aktif', 'cuti', 'non-aktif', 'kampus merdeka'])
            ->orderBy('student.nama_mahasiswa')
            ->select([
                'akm.id_ipmhs', 'akm.id_mahasiswa', 'akm.ips', 'akm.ipk',
                'akm.sks_semester', 'akm.sks_program', 'akm.sks_lulus',
                'akm.status_mahasiswa', 'akm.simpan', 'student.nim', 'student.nama_mahasiswa',
            ]);
    }

    private function calendar(string $programId, string $calendarId): object
    {
        $calendar = $this->calendars($programId)->firstWhere('id_kalender', $calendarId);
        abort_if($calendar === null, 404, 'Kalender bukan milik program studi terpilih.');

        return $calendar;
    }

    private function authorizeRole(object $user): void
    {
        abort_unless(in_array($user->role, self::ROLES, true), 403);
    }
}
