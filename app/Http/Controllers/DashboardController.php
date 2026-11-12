<?php

namespace App\Http\Controllers;

use App\Support\DashboardStudentStatus;
use App\Support\DashboardTeachingStaffCounts;
use App\Support\LecturerName;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    private const MASTER_ROLES = ['admin', 'pimuniv', 'pimfak', 'userprodi', 'usertu'];

    private const DEFAULT_LATITUDE = 1.5709922;

    private const DEFAULT_LONGITUDE = 127.8087685;

    public function dtpsDetails(Request $request, string $program): JsonResponse
    {
        $user = $request->user();
        abort_unless(in_array($user->role, self::MASTER_ROLES, true), 403);

        $programQuery = DB::connection('simak_main')->table('s1m4k_masterprodi')
            ->where('id_prodi', $program)
            ->where('status', 'A');
        if ($user->role === 'pimfak') {
            $facultyId = trim((string) data_get($user, 'id_fakultas'));
            $facultyId === ''
                ? $programQuery->whereRaw('1 = 0')
                : $programQuery->where('id_fakultas', $facultyId);
        } elseif (in_array($user->role, ['userprodi', 'usertu'], true)) {
            $programId = trim((string) data_get($user, 'id_prodi'));
            $programId === ''
                ? $programQuery->whereRaw('1 = 0')
                : $programQuery->where('id_prodi', $programId);
        }
        abort_unless($programQuery->exists(), 404);

        $filters = $request->validate([
            'category' => ['required', 'in:all,homebase,non_homebase'],
        ]);

        return response()->json(DashboardTeachingStaffCounts::detailsForProgram(
            $program,
            category: $filters['category'] === 'all' ? null : $filters['category'],
        ));
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $role = $user->role;
        $hasMasterDashboard = in_array($role, self::MASTER_ROLES, true);
        if (! $hasMasterDashboard) {
            $personalActivity = match ($role) {
                'mahasiswa' => $this->studentAcademicActivity((string) data_get($user, 'legacy_id')),
                'dosen' => $this->lecturerAcademicActivity((string) data_get($user, 'legacy_id')),
                default => null,
            };

            return view('dashboard', [
                'hasMasterDashboard' => false,
                'roleLabel' => config('simak.role_labels.'.$role, ucfirst($role)),
                'personalActivity' => $personalActivity,
            ]);
        }

        $programQuery = DB::connection('simak_main')->table('s1m4k_masterprodi')
            ->leftJoin('s1m4k_masterfakultas', 's1m4k_masterprodi.id_fakultas', '=', 's1m4k_masterfakultas.id_fakultas')
            ->select([
                's1m4k_masterprodi.id_prodi',
                's1m4k_masterprodi.id_fakultas',
                's1m4k_masterprodi.nama_program_studi',
                's1m4k_masterprodi.nama_jenjang_pendidikan',
                's1m4k_masterfakultas.nama_fakultas',
            ])
            ->whereNotNull('s1m4k_masterprodi.nama_program_studi')
            ->where('s1m4k_masterprodi.status', 'A')
            ->whereRaw("TRIM(s1m4k_masterprodi.nama_program_studi) <> ''");

        if ($role === 'pimfak') {
            $facultyId = trim((string) data_get($user, 'id_fakultas'));
            if ($facultyId === '') {
                $programQuery->whereRaw('1 = 0');
            } else {
                $programQuery->where('s1m4k_masterprodi.id_fakultas', $facultyId);
            }
        } elseif (in_array($role, ['userprodi', 'usertu'], true)) {
            $programId = trim((string) data_get($user, 'id_prodi'));
            if ($programId === '') {
                $programQuery->whereRaw('1 = 0');
            } else {
                $programQuery->where('s1m4k_masterprodi.id_prodi', $programId);
            }
        } elseif (! $hasMasterDashboard) {
            $programQuery->whereRaw('1 = 0');
        }

        $programs = $programQuery->orderBy('s1m4k_masterprodi.nama_program_studi')->get();
        $programIds = $programs->pluck('id_prodi')->filter()->values();
        $isInstitutionWide = in_array($role, ['admin', 'pimuniv'], true);
        $scopeLabel = $this->scopeLabel($user, $programs, $isInstitutionWide);
        $years = range(now()->year - 5, now()->year);

        $countsByProgram = [];
        foreach ([
            'mahasiswa' => 's1m4k_mastermhs',
            'dosen' => 's1m4k_masterdosen',
            'asisten' => 's1m4k_masterasdos',
            'mataKuliah' => 's1m4k_mastermk',
            'ruang' => 's1m4k_masterruang',
        ] as $key => $table) {
            $countsByProgram[$key] = $this->countsByProgram($table, $programIds);
        }
        $dtpsByProgram = DashboardTeachingStaffCounts::forPrograms($programIds);

        $cards = [
            ['label' => 'Program studi', 'value' => $programs->count(), 'detail' => 'Dalam cakupan akses'],
            ['label' => 'Mahasiswa', 'value' => array_sum($countsByProgram['mahasiswa']), 'detail' => 'Status A, N, C, dan M'],
            ['label' => 'Dosen', 'value' => array_sum($countsByProgram['dosen']), 'detail' => 'Homebase dalam cakupan'],
            ['label' => 'Asisten dosen', 'value' => array_sum($countsByProgram['asisten']), 'detail' => 'Dalam cakupan akses'],
            ['label' => 'Mata kuliah', 'value' => array_sum($countsByProgram['mataKuliah']), 'detail' => 'Dalam cakupan akses'],
            ['label' => 'Ruang', 'value' => array_sum($countsByProgram['ruang']), 'detail' => 'Dalam cakupan akses'],
        ];

        $programSummary = $programs->map(fn (object $program): array => [
            'id' => (string) $program->id_prodi,
            'name' => trim($program->nama_program_studi.' '.($program->nama_jenjang_pendidikan ?? '')),
            'faculty' => is_string($program->nama_fakultas) && trim($program->nama_fakultas) !== ''
                ? $program->nama_fakultas
                : 'Belum ditetapkan',
            'students' => $countsByProgram['mahasiswa'][$program->id_prodi] ?? 0,
            'lecturers' => $countsByProgram['dosen'][$program->id_prodi] ?? 0,
            'assistants' => $countsByProgram['asisten'][$program->id_prodi] ?? 0,
            'dtps' => $dtpsByProgram[$program->id_prodi]['total'] ?? 0,
            'dtpsHomebase' => $dtpsByProgram[$program->id_prodi]['homebase'] ?? 0,
            'dtpsNonHomebase' => $dtpsByProgram[$program->id_prodi]['non_homebase'] ?? 0,
            'courses' => $countsByProgram['mataKuliah'][$program->id_prodi] ?? 0,
            'rooms' => $countsByProgram['ruang'][$program->id_prodi] ?? 0,
        ]);

        $statusQuery = DB::connection('simak_main')->table('s1m4k_mastermhs')
            ->selectRaw("COALESCE(NULLIF(TRIM(nama_status_mahasiswa), ''), 'Belum ditentukan') as label, COUNT(*) as total")
            ->whereIn('id_status_mahasiswa', DashboardStudentStatus::INCLUDED)
            ->groupBy('label')
            ->orderByDesc('total');
        DashboardStudentStatus::applyStudentDataRequirements($statusQuery);
        $this->applyProgramScope($statusQuery, $programIds);
        $studentStatuses = $statusQuery->get();
        $studentIntake = $this->studentIntake($programs, $programIds, $years);
        $studentDistribution = $this->studentDistribution($programs, $programIds);
        $faculties = $this->facultyNames($programs);
        if (collect($studentDistribution)->contains(
            static fn (array $province): bool => array_key_exists('Fakultas belum ditetapkan', $province['faculties'])
        )) {
            $faculties[] = 'Fakultas belum ditetapkan';
        }
        $distributionFacultyTotals = array_fill_keys($faculties, 0);
        foreach ($studentDistribution as $province) {
            foreach ($province['faculties'] as $faculty => $total) {
                $distributionFacultyTotals[$faculty] = ($distributionFacultyTotals[$faculty] ?? 0) + $total;
            }
        }

        return view('dashboard', [
            'hasMasterDashboard' => $hasMasterDashboard,
            'scopeLabel' => $scopeLabel,
            'cards' => $cards,
            'programSummary' => $programSummary,
            'studentStatuses' => $studentStatuses,
            'studentTotal' => array_sum($countsByProgram['mahasiswa']),
            'intakeYears' => $years,
            'intakeSeries' => $studentIntake,
            'faculties' => $faculties,
            'studentDistribution' => $studentDistribution,
            'distributionTotal' => array_sum(array_column($studentDistribution, 'total')),
            'distributionFacultyTotals' => $distributionFacultyTotals,
            'universityName' => DB::connection('simak_main')->table('s1m4k_masterpt')
                ->whereNotNull('nama_perguruan_tinggi')
                ->value('nama_perguruan_tinggi'),
        ]);
    }

    private function studentAcademicActivity(string $studentId): array
    {
        $connection = DB::connection('simak_main');
        $student = $connection->table('s1m4k_mastermhs as mhs')
            ->join('s1m4k_masterprodi as prodi', 'mhs.id_prodi', '=', 'prodi.id_prodi')
            ->where('prodi.status', 'A')
            ->where('mhs.id_mahasiswa', trim($studentId))
            ->first(['mhs.id_mahasiswa', 'mhs.nim', 'mhs.nama_mahasiswa', 'mhs.id_prodi']);

        if ($student === null) {
            return ['type' => 'student', 'found' => false];
        }

        $calendar = $connection->table('s1m4k_kalender')
            ->where('id_prodi', $student->id_prodi)
            ->where('status', 'Y')
            ->orderByDesc('tahun')
            ->first(['id_kalender', 'tahun', 'namatahun']);
        $history = $connection->table('s1m4k_akm as akm')
            ->leftJoin('s1m4k_kalender as calendar', 'akm.id_kalender', '=', 'calendar.id_kalender')
            ->where('akm.id_mahasiswa', $student->id_mahasiswa)
            ->orderByDesc('calendar.tahun')
            ->orderByDesc('akm.tahun')
            ->get([
                'akm.id_ipmhs', 'akm.ips', 'akm.ipk', 'akm.sks_semester',
                'akm.sks_lulus', 'akm.tahun',
                'calendar.namatahun', 'calendar.tahun as calendar_year',
            ]);
        $latest = $history->first();

        $hasScheduleId = Schema::connection('simak_main')->hasColumn('s1m4k_krs', 'id_jadwal');
        $hasLecturer = Schema::connection('simak_main')->hasTable('s1m4k_masterdosen')
            && Schema::connection('simak_main')->hasColumn('s1m4k_masterdosen', 'nama_dosen');

        $coursesQuery = $connection->table('s1m4k_krs as krs')
            ->join('s1m4k_mastermk as course', 'krs.id_matkul', '=', 'course.id_matkul');

        $selects = [
            'course.kode_mata_kuliah', 'course.nama_mata_kuliah',
            'course.sks_mata_kuliah', 'krs.nilai_huruf',
        ];

        if ($hasScheduleId && $hasLecturer) {
            $coursesQuery->leftJoin('s1m4k_jadwal as schedule', 'krs.id_jadwal', '=', 'schedule.id_jadwal')
                ->leftJoin('s1m4k_masterdosen as lecturer', 'schedule.dosen1', '=', 'lecturer.id_dosen');
            $selects[] = 'lecturer.nama_dosen';
            $selects[] = 'lecturer.gelar_depan';
            $selects[] = 'lecturer.gelar_belakang';
        }

        $courses = $calendar === null ? collect() : $coursesQuery
            ->where('krs.id_mahasiswa', $student->id_mahasiswa)
            ->where('krs.id_kalender', $calendar->id_kalender)
            ->orderBy('course.nama_mata_kuliah')
            ->get($selects);

        $courses->transform(function (object $course) use ($hasScheduleId, $hasLecturer): object {
            $course->nama_dosen = ($hasScheduleId && $hasLecturer)
                ? LecturerName::format($course->nama_dosen ?? null, $course->gelar_depan ?? null, $course->gelar_belakang ?? null)
                : null;

            return $course;
        });

        return [
            'type' => 'student',
            'found' => true,
            'name' => (string) $student->nama_mahasiswa,
            'identifier' => (string) $student->nim,
            'calendar' => $calendar?->namatahun ?: $calendar?->tahun,
            'cards' => [
                ['label' => 'Mata kuliah semester aktif', 'value' => $courses->count(), 'detail' => $calendar?->namatahun ?: 'Belum ada kalender aktif'],
                ['label' => 'IPS terakhir', 'value' => $latest?->ips ?? '-', 'detail' => $latest?->namatahun ?: $latest?->calendar_year ?: 'Belum ada data AKM'],
                ['label' => 'IPK terakhir', 'value' => $latest?->ipk ?? '-', 'detail' => 'Berdasarkan data AKM terakhir'],
                ['label' => 'SKS lulus', 'value' => $latest?->sks_lulus ?? '-', 'detail' => 'Dari data AKM terakhir'],
            ],
            'courses' => $courses->take(8),
            'history' => $history->take(6),
        ];
    }

    private function lecturerAcademicActivity(string $lecturerId): array
    {
        $lecturerId = trim($lecturerId);
        if ($lecturerId === '') {
            return ['type' => 'lecturer', 'found' => false];
        }
        $lecturerExists = DB::connection('simak_main')->table('s1m4k_masterdosen')
            ->where('id_dosen', $lecturerId)->exists();
        if (! $lecturerExists) {
            return ['type' => 'lecturer', 'found' => false];
        }

        $schedules = DB::connection('simak_main')->table('s1m4k_jadwal as schedule')
            ->join('s1m4k_kalender as calendar', 'schedule.id_kalender', '=', 'calendar.id_kalender')
            ->join('s1m4k_masterprodi as program', 'schedule.id_prodi', '=', 'program.id_prodi')
            ->leftJoin('s1m4k_mastermk as course', 'schedule.id_matkul', '=', 'course.id_matkul')
            ->leftJoin('s1m4k_akd as activity', function ($join) use ($lecturerId): void {
                $join->on('activity.id_jadwal', '=', 'schedule.id_jadwal')
                    ->where('activity.id_dosen', '=', $lecturerId);
            })
            ->where('calendar.status', 'Y')
            ->where('program.status', 'A')
            ->where(function (Builder $builder) use ($lecturerId): void {
                for ($slot = 1; $slot <= 14; $slot++) {
                    $method = $slot === 1 ? 'where' : 'orWhere';
                    $builder->{$method}('schedule.dosen'.$slot, $lecturerId);
                }
            })
            ->orderBy('program.nama_program_studi')
            ->orderBy('course.nama_mata_kuliah')
            ->select([
                'schedule.id_jadwal', 'schedule.nama_kelas',
                'course.kode_mata_kuliah', 'course.nama_mata_kuliah',
                'program.nama_program_studi', 'calendar.namatahun', 'calendar.tahun',
                'activity.rencana', 'activity.realisasi',
            ])->get();

        $planned = (int) $schedules->sum(fn (object $schedule): int => (int) $schedule->rencana);
        $realized = (int) $schedules->sum(fn (object $schedule): int => (int) $schedule->realisasi);

        return [
            'type' => 'lecturer',
            'found' => true,
            'cards' => [
                ['label' => 'Mata kuliah diampu', 'value' => $schedules->count(), 'detail' => 'Pada kalender aktif'],
                ['label' => 'Tatap muka direncanakan', 'value' => $planned, 'detail' => 'Total seluruh mata kuliah aktif'],
                ['label' => 'Tatap muka terealisasi', 'value' => $realized, 'detail' => $planned > 0 ? round($realized / $planned * 100).'% dari rencana' : 'Belum ada rencana'],
            ],
            'schedules' => $schedules->take(10),
        ];
    }

    private function studentIntake(Collection $programs, Collection $programIds, array $years): array
    {
        $query = DB::connection('simak_main')->table('s1m4k_mastermhs')
            ->selectRaw('id_prodi, SUBSTR(id_periode_masuk, 1, 4) as angkatan, COUNT(*) as total')
            ->whereNotNull('id_periode_masuk')
            ->whereRaw("TRIM(id_periode_masuk) <> ''")
            ->whereRaw('SUBSTR(id_periode_masuk, 1, 4) BETWEEN ? AND ?', [
                (string) min($years),
                (string) max($years),
            ])
            ->groupBy('id_prodi', 'angkatan');
        $this->applyProgramScope($query, $programIds);

        $counts = [];
        foreach ($query->get() as $row) {
            $counts[$row->id_prodi][(int) $row->angkatan] = (int) $row->total;
        }

        return $programs->map(fn (object $program): array => [
            'id' => (string) $program->id_prodi,
            'label' => trim($program->nama_program_studi.' '.($program->nama_jenjang_pendidikan ?? '')),
            'values' => array_map(
                fn (int $year): int => $counts[$program->id_prodi][$year] ?? 0,
                $years
            ),
        ])->values()->all();
    }

    private function facultyNames(Collection $programs): array
    {
        return $programs->pluck('nama_fakultas')
            ->filter(static fn (mixed $name): bool => is_string($name) && trim($name) !== '')
            ->map(static fn (string $name): string => trim($name))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function studentDistribution(Collection $programs, Collection $programIds): array
    {
        $query = DB::connection('simak_main')->table('s1m4k_mastermhs as mhs')
            ->leftJoin('s1m4k_masterprodi as prodi', 'mhs.id_prodi', '=', 'prodi.id_prodi')
            ->leftJoin('s1m4k_masterfakultas as fakultas', 'prodi.id_fakultas', '=', 'fakultas.id_fakultas')
            ->leftJoin('s1m4k_kodereferensi as wilayah', function ($join): void {
                $join->on('mhs.id_wilayah', '=', 'wilayah.kode_referensi')
                    ->where('wilayah.keterangan', '=', 'GetWilayah');
            })
            ->selectRaw("
                COALESCE(NULLIF(TRIM(wilayah.nama_provinsi), ''), 'Maluku Utara') as provinsi,
                wilayah.lat_provinsi,
                wilayah.lng_provinsi,
                COALESCE(NULLIF(TRIM(fakultas.nama_fakultas), ''), 'Fakultas belum ditetapkan') as nama_fakultas,
                COUNT(DISTINCT mhs.id_mahasiswa) as total
            ")
            ->whereIn('mhs.id_status_mahasiswa', DashboardStudentStatus::INCLUDED)
            ->whereNotNull('mhs.id_wilayah')
            ->whereRaw("TRIM(mhs.id_wilayah) <> ''")
            ->groupBy('provinsi', 'wilayah.lat_provinsi', 'wilayah.lng_provinsi', 'nama_fakultas')
            ->orderBy('provinsi');
        DashboardStudentStatus::applyStudentDataRequirements($query, 'mhs');
        $this->applyProgramScope($query, $programIds, 'mhs.id_prodi');

        $provinces = [];
        foreach ($query->get() as $row) {
            $province = (string) $row->provinsi;
            $latitude = (float) $row->lat_provinsi;
            $longitude = (float) $row->lng_provinsi;
            if ($province === 'Maluku Utara' || $latitude === 0.0 || $longitude === 0.0) {
                $latitude = self::DEFAULT_LATITUDE;
                $longitude = self::DEFAULT_LONGITUDE;
            }

            if (! isset($provinces[$province])) {
                $provinces[$province] = [
                    'province' => $province,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'total' => 0,
                    'faculties' => [],
                ];
            }

            $faculty = (string) $row->nama_fakultas;
            $count = (int) $row->total;
            $provinces[$province]['total'] += $count;
            $provinces[$province]['faculties'][$faculty] = ($provinces[$province]['faculties'][$faculty] ?? 0) + $count;
        }

        foreach ($provinces as &$province) {
            foreach ($this->facultyNames($programs) as $faculty) {
                $province['faculties'][$faculty] ??= 0;
            }
            ksort($province['faculties']);
        }
        unset($province);

        return array_values($provinces);
    }

    private function countsByProgram(string $table, Collection $programIds): array
    {
        $query = DB::connection('simak_main')->table($table)
            ->selectRaw('id_prodi, COUNT(*) as total')
            ->groupBy('id_prodi');
        if ($table === 's1m4k_mastermhs') {
            $query->whereIn('id_status_mahasiswa', DashboardStudentStatus::INCLUDED);
            DashboardStudentStatus::applyStudentDataRequirements($query);
        }
        $this->applyProgramScope($query, $programIds);

        $counts = array_fill_keys($programIds->all(), 0);
        foreach ($query->get() as $row) {
            if (array_key_exists($row->id_prodi, $counts)) {
                $counts[$row->id_prodi] = (int) $row->total;
            }
        }

        return $counts;
    }

    private function applyProgramScope(Builder $query, Collection $programIds, string $column = 'id_prodi'): void
    {
        if ($programIds->isEmpty()) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn($column, $programIds->all());
    }

    private function scopeLabel(object $user, Collection $programs, bool $isInstitutionWide): string
    {
        if ($isInstitutionWide) {
            return 'Seluruh universitas';
        }
        if ($user->role === 'pimfak') {
            $faculty = DB::connection('simak_main')->table('s1m4k_masterfakultas')
                ->where('id_fakultas', data_get($user, 'id_fakultas'))
                ->value('nama_fakultas');

            return is_string($faculty) && trim($faculty) !== '' ? 'Fakultas '.$faculty : 'Cakupan fakultas belum ditetapkan';
        }

        $program = $programs->first();
        if ($program !== null) {
            return 'Program studi '.trim($program->nama_program_studi.' '.($program->nama_jenjang_pendidikan ?? ''));
        }

        return 'Cakupan program studi belum ditetapkan';
    }
}
