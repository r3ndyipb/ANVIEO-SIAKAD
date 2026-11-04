<?php

namespace App\Http\Controllers;

use App\Support\AcademicCalendarScope;
use App\Support\DashboardStudentStatus;
use App\Support\DashboardTeachingStaffCounts;
use App\Support\LecturerName;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AcademicDashboardController extends Controller
{
    private const ROLES = ['admin', 'pimuniv', 'pimfak', 'userprodi', 'usertu'];

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless(in_array($user->role, self::ROLES, true), 403);
        $filters = $request->validate([
            'calendar' => ['nullable', 'string', 'max:5'],
        ]);
        $programs = $this->programsFor($user);
        $programIds = $programs->pluck('id_prodi')->map(fn (mixed $id): string => (string) $id)->values();
        $calendarRows = $this->calendarRows($programIds);
        $calendarOptions = $this->calendarOptions($calendarRows);
        $selectedYear = (string) ($filters['calendar'] ?? '');
        if ($selectedYear !== '') {
            abort_unless($calendarOptions->contains(fn (array $term): bool => $term['year'] === $selectedYear), 404);
        } else {
            $selectedYear = (string) ($calendarOptions->firstWhere('active', true)['year'] ?? $calendarOptions->first()['year'] ?? '');
        }
        $scopeLabel = $this->scopeLabel($user, $programs);

        return view('academic-dashboard.index', compact(
            'calendarOptions', 'selectedYear', 'scopeLabel'
        ));
    }

    public function data(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(in_array($user->role, self::ROLES, true), 403);
        $filters = $request->validate(['calendar' => ['required', 'string', 'max:5']]);
        $programs = $this->programsFor($user);
        $programIds = $programs->pluck('id_prodi')->map(fn (mixed $id): string => (string) $id)->values();
        $calendarRows = $this->calendarRows($programIds);
        $calendarOptions = $this->calendarOptions($calendarRows);
        $selectedTerm = $calendarOptions->firstWhere('year', $filters['calendar']);
        abort_unless($selectedTerm !== null, 404);

        $termCalendars = $calendarRows->where('tahun', $selectedTerm['year']);
        $calendarIds = $termCalendars->pluck('id_kalender')->values();
        $cacheKey = 'academic-dashboard:v10:'.hash('sha256', $programIds->sort()->implode('|').':'.$selectedTerm['year']);

        $payload = Cache::remember($cacheKey, now()->addMinute(), function () use (
            $programs, $programIds, $selectedTerm, $calendarIds
        ): array {
            $activeCounts = $this->activeStudentsByProgram($programIds);
            $registrationCounts = $this->registrationsByProgram($calendarIds);
            $krsCounts = $this->krsByProgram($calendarIds);
            $approvalCounts = $this->approvalsByProgram($calendarIds);
            $akmAverages = $this->akmByProgram($calendarIds);
            $edomScores = $this->edomScoresByProgram($calendarIds);
            $dtpsCounts = DashboardTeachingStaffCounts::forPrograms($programIds, $calendarIds);
            $programSummary = $programs->map(function (object $program) use (
                $activeCounts, $registrationCounts, $krsCounts, $approvalCounts, $akmAverages, $edomScores, $dtpsCounts
            ): array {
                $id = (string) $program->id_prodi;
                $active = $activeCounts[$id] ?? 0;
                $registered = $registrationCounts[$id] ?? 0;
                $krsStudents = $krsCounts[$id]['students'] ?? 0;
                $approved = $approvalCounts[$id] ?? 0;
                $gradesEntered = $krsCounts[$id]['grades'] ?? 0;

                return [
                    'id' => $id,
                    'name' => trim($program->nama_program_studi.' '.($program->nama_jenjang_pendidikan ?? '')),
                    'faculty' => $program->nama_fakultas ?: 'Fakultas belum ditetapkan',
                    'active_students' => $active,
                    'registered_students' => $registered,
                    'registration_rate' => $active > 0 ? min(100, round($registered / $active * 100)) : 0,
                    'krs_students' => $krsStudents,
                    'krs_rate' => $active > 0 ? min(100, round($krsStudents / $active * 100)) : 0,
                    'approved_students' => $approved,
                    'approval_rate' => $krsStudents > 0 ? min(100, round($approved / $krsStudents * 100)) : 0,
                    'grades_entered' => $gradesEntered,
                    'grade_rate' => $krsStudents > 0 ? min(100, round($gradesEntered / $krsStudents * 100)) : 0,
                    'average_ips' => $akmAverages[$id]['ips'] ?? null,
                    'average_ipk' => $akmAverages[$id]['ipk'] ?? null,
                    'average_edom_score' => $edomScores[$id]['average'] ?? null,
                    'edom_responses' => $edomScores[$id]['responses'] ?? 0,
                    'dtps' => $dtpsCounts[$id]['total'] ?? 0,
                    'dtps_homebase' => $dtpsCounts[$id]['homebase'] ?? 0,
                    'dtps_non_homebase' => $dtpsCounts[$id]['non_homebase'] ?? 0,
                ];
            })->values();

            $totals = [
                'active' => $programSummary->sum('active_students'),
                'registered' => $programSummary->sum('registered_students'),
                'krs' => $programSummary->sum('krs_students'),
                'approved' => $programSummary->sum('approved_students'),
                'grades' => $programSummary->sum('grades_entered'),
                'edom_score_sum' => $programSummary->sum(fn (array $program): float => ($program['average_edom_score'] ?? 0) * $program['edom_responses']),
                'edom_responses' => $programSummary->sum('edom_responses'),
            ];
            $averageEdomScore = $totals['edom_responses'] > 0
                ? round($totals['edom_score_sum'] / $totals['edom_responses'], 1)
                : null;
            $cards = [
                ['label' => 'Mahasiswa', 'value' => $totals['active'], 'detail' => 'Status A, N, C, dan M'],
                ['label' => 'Registrasi ulang', 'value' => $totals['registered'], 'detail' => $this->percentage($totals['registered'], $totals['active']).' dari mahasiswa aktif'],
                ['label' => 'Sudah mengisi KRS', 'value' => $totals['krs'], 'detail' => $this->percentage($totals['krs'], $totals['active']).' dari mahasiswa aktif'],
                ['label' => 'KRS disahkan prodi', 'value' => $totals['approved'], 'detail' => $this->percentage($totals['approved'], $totals['krs']).' dari pengisi KRS'],
                ['label' => 'Nilai sudah diinput', 'value' => $totals['grades'], 'detail' => $this->percentage($totals['grades'], $totals['krs']).' dari pengisi KRS'],
                ['label' => 'Rata-rata capaian EDOM', 'value' => $averageEdomScore !== null ? $averageEdomScore.'%' : '-', 'detail' => number_format($totals['edom_responses']).' jawaban dalam cakupan'],
            ];
            $selectedYear = (int) substr($selectedTerm['year'], 0, 4);
            $trendYears = range($selectedYear - 3, $selectedYear);

            return [
                'calendar' => $selectedTerm,
                'cards' => $cards,
                'programSummary' => $programSummary->values()->all(),
                'trendYears' => $trendYears,
                'trendRows' => [],
                'topEdomLecturers' => [],
            ];
        });

        return response()->json($payload);
    }

    public function exportExcel(Request $request): Response
    {
        $user = $request->user();
        abort_unless(in_array($user->role, self::ROLES, true), 403);
        $filters = $request->validate([
            'calendar' => ['required', 'string', 'max:5'],
            'program' => ['required', 'string', 'max:100'],
            'metric' => ['required', 'in:active,registered,krs,approved,grades'],
        ]);
        $programs = $this->programsFor($user);
        $program = $programs->firstWhere('id_prodi', $filters['program']);
        abort_if($program === null, 404);
        $programIds = $programs->pluck('id_prodi')->map(fn (mixed $id): string => (string) $id)->values();
        $calendarRows = $this->calendarRows($programIds);
        $calendarOptions = $this->calendarOptions($calendarRows);
        $selectedTerm = $calendarOptions->firstWhere('year', $filters['calendar']);
        abort_if($selectedTerm === null, 404);
        $termCalendars = $calendarRows
            ->where('id_prodi', $filters['program'])
            ->where('tahun', $selectedTerm['year']);
        abort_if($termCalendars->isEmpty(), 404);

        $rows = $this->academicProcessExportQuery(
            $filters['metric'],
            $filters['program'],
            $termCalendars->pluck('id_kalender')->values(),
        )->get();
        $metricLabels = [
            'active' => 'Mahasiswa aktif',
            'registered' => 'Registrasi ulang',
            'krs' => 'Sudah isi KRS',
            'approved' => 'Disahkan prodi',
            'grades' => 'Nilai diinput',
        ];
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data '.$metricLabels[$filters['metric']]);
        $headers = ['NPM', 'Nama Mahasiswa', 'ID Mahasiswa', 'Status Mahasiswa', 'Program Studi', 'Fakultas', 'Tahun Akademik'];
        if ($filters['metric'] === 'grades') {
            $headers[] = 'Kode Mata Kuliah';
            $headers[] = 'Nama Mata Kuliah';
        }
        foreach ($headers as $index => $header) {
            $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($index + 1).'1', $header, DataType::TYPE_STRING);
        }
        $sheet->getStyle('A1:'.Coordinate::stringFromColumnIndex(count($headers)).'1')->getFont()->setBold(true);
        foreach ($rows as $index => $row) {
            $values = [
                $row->nim,
                $row->nama_mahasiswa,
                $row->id_mahasiswa,
                $row->id_status_mahasiswa,
                trim((string) $program->nama_program_studi.' '.($program->nama_jenjang_pendidikan ?? '')),
                $program->nama_fakultas ?: 'Fakultas belum ditetapkan',
                $selectedTerm['label'],
            ];
            if ($filters['metric'] === 'grades') {
                $values[] = $row->kode_mata_kuliah;
                $values[] = $row->nama_mata_kuliah;
            }
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
            'Content-Disposition' => 'attachment; filename="dashboard-akademik-'.$filters['metric'].'-'.$filters['program'].'-'.$selectedTerm['year'].'.xlsx"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function insights(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(in_array($user->role, self::ROLES, true), 403);
        $filters = $request->validate(['calendar' => ['required', 'string', 'max:5']]);
        $programs = $this->programsFor($user);
        $programIds = $programs->pluck('id_prodi')->map(fn (mixed $id): string => (string) $id)->values();
        $calendarRows = $this->calendarRows($programIds);
        $calendarOptions = $this->calendarOptions($calendarRows);
        $selectedTerm = $calendarOptions->firstWhere('year', $filters['calendar']);
        abort_unless($selectedTerm !== null, 404);

        $calendarIds = $calendarRows->where('tahun', $selectedTerm['year'])->pluck('id_kalender')->values();
        $year = (int) substr($selectedTerm['year'], 0, 4);

        $cacheKey = 'academic-dashboard-insights:v4:'.hash('sha256', $programIds->sort()->implode('|').':'.$selectedTerm['year']);

        $payload = Cache::remember($cacheKey, now()->addMinute(), function () use ($programs, $calendarRows, $calendarIds, $year): array {
            return [
                'trendYears' => range($year - 3, $year),
                'trendRows' => $this->trendsByProgram($programs, $calendarRows, range($year - 3, $year))->values()->all(),
                'topEdomLecturers' => $this->topEdomLecturers($calendarIds)->values()->all(),
            ];
        });

        return response()->json($payload);
    }

    public function dtpsDetails(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(in_array($user->role, self::ROLES, true), 403);
        $filters = $request->validate([
            'program' => ['required', 'string', 'max:100'],
            'calendar' => ['required', 'string', 'max:5'],
            'category' => ['required', 'in:all,homebase,non_homebase'],
        ]);
        $programs = $this->programsFor($user);
        $programIds = $programs->pluck('id_prodi')->map(fn (mixed $id): string => (string) $id)->values();
        abort_unless($programIds->contains($filters['program']), 404);

        $calendarRows = $this->calendarRows($programIds);
        $calendarOptions = $this->calendarOptions($calendarRows);
        abort_unless($calendarOptions->contains(
            fn (array $term): bool => $term['year'] === $filters['calendar']
        ), 404);
        $calendarIds = $calendarRows->where('tahun', $filters['calendar'])
            ->pluck('id_kalender')
            ->values();

        return response()->json(DashboardTeachingStaffCounts::detailsForProgram(
            $filters['program'],
            $calendarIds,
            $filters['category'] === 'all' ? null : $filters['category'],
        ));
    }

    private function calendarRows(Collection $programIds): Collection
    {
        if ($programIds->isEmpty()) {
            return collect();
        }

        return AcademicCalendarScope::excludeShortSemesters(
            DB::connection('simak_main')->table('s1m4k_kalender as calendar'),
            'calendar.tahun'
        )
            ->whereIn('calendar.id_prodi', $programIds)
            ->whereNotNull('calendar.tahun')
            ->whereRaw("TRIM(calendar.tahun) <> ''")
            ->orderByDesc('calendar.tahun')
            ->get([
                'calendar.id_kalender', 'calendar.id_prodi', 'calendar.tahun',
                'calendar.namatahun', 'calendar.status',
            ]);
    }

    private function calendarOptions(Collection $calendarRows): Collection
    {
        return $calendarRows->groupBy('tahun')->map(function (Collection $rows, string|int $year): array {
            $label = $rows->first(fn (object $row): bool => filled($row->namatahun))?->namatahun;

            return [
                'year' => (string) $year,
                'label' => filled($label) ? (string) $label : (string) $year,
                'active' => $rows->contains(fn (object $row): bool => $row->status === 'Y'),
            ];
        })->sortByDesc('year')->values();
    }

    private function programsFor(object $user): Collection
    {
        return DB::connection('simak_main')->table('s1m4k_masterprodi as program')
            ->leftJoin('s1m4k_masterfakultas as faculty', 'program.id_fakultas', '=', 'faculty.id_fakultas')
            ->where('program.status', 'A')
            ->when($user->role === 'pimfak', function ($query) use ($user): void {
                $facultyId = trim((string) data_get($user, 'id_fakultas'));
                $facultyId === ''
                    ? $query->whereRaw('1 = 0')
                    : $query->where('program.id_fakultas', $facultyId);
            })
            ->when(in_array($user->role, ['userprodi', 'usertu'], true), function ($query) use ($user): void {
                $programId = trim((string) data_get($user, 'id_prodi'));
                $programId === ''
                    ? $query->whereRaw('1 = 0')
                    : $query->where('program.id_prodi', $programId);
            })
            ->orderBy('program.nama_program_studi')
            ->get([
                'program.id_prodi', 'program.id_fakultas', 'program.nama_program_studi',
                'program.nama_jenjang_pendidikan', 'faculty.nama_fakultas',
            ]);
    }

    private function activeStudentsByProgram(Collection $programIds): array
    {
        $query = DB::connection('simak_main')->table('s1m4k_mastermhs')
            ->whereIn('id_prodi', $programIds)
            ->whereIn('id_status_mahasiswa', DashboardStudentStatus::INCLUDED)
            ->selectRaw('id_prodi, COUNT(DISTINCT id_mahasiswa) as total')
            ->groupBy('id_prodi');
        DashboardStudentStatus::applyStudentDataRequirements($query);

        return $query->get()
            ->mapWithKeys(fn (object $row): array => [(string) $row->id_prodi => (int) $row->total])->all();
    }

    private function registrationsByProgram(Collection $calendarIds): array
    {
        if ($calendarIds->isEmpty()) {
            return [];
        }

        return DB::connection('simak_main')->table('s1m4k_registrasiulang as registration')
            ->join('s1m4k_kalender as calendar', 'registration.id_kalender', '=', 'calendar.id_kalender')
            ->whereIn('registration.id_kalender', $calendarIds)
            ->selectRaw('calendar.id_prodi, COUNT(DISTINCT registration.id_mahasiswa) as total')
            ->groupBy('calendar.id_prodi')->get()
            ->mapWithKeys(fn (object $row): array => [(string) $row->id_prodi => (int) $row->total])->all();
    }

    private function academicProcessExportQuery(string $metric, string $programId, Collection $calendarIds)
    {
        if ($metric === 'active') {
            $students = DB::connection('simak_main')->table('s1m4k_mastermhs')
                ->where('id_prodi', $programId)
                ->whereIn('id_status_mahasiswa', DashboardStudentStatus::INCLUDED);
            DashboardStudentStatus::applyStudentDataRequirements($students);

            return $students->select([
                'id_mahasiswa', 'nim', 'nama_mahasiswa', 'id_status_mahasiswa',
            ])->orderBy('nim');
        }

        if ($metric === 'grades') {
            return DB::connection('simak_main')->table('s1m4k_krs as krs')
                ->join('s1m4k_mastermhs as student', 'krs.id_mahasiswa', '=', 'student.id_mahasiswa')
                ->leftJoin('s1m4k_mastermk as course', 'krs.id_matkul', '=', 'course.id_matkul')
                ->where('student.id_prodi', $programId)
                ->whereIn('krs.id_kalender', $calendarIds)
                ->where('krs.input_nilai', 'Y')
                ->select([
                    'student.id_mahasiswa',
                    'student.nim',
                    'student.nama_mahasiswa',
                    'student.id_status_mahasiswa',
                    'course.kode_mata_kuliah',
                    'course.nama_mata_kuliah',
                ])
                ->distinct()
                ->orderBy('student.nim')
                ->orderBy('course.kode_mata_kuliah')
                ->orderBy('course.nama_mata_kuliah');
        }

        $processStudents = match ($metric) {
            'registered' => DB::connection('simak_main')->table('s1m4k_registrasiulang')
                ->whereIn('id_kalender', $calendarIds)
                ->select('id_mahasiswa')
                ->distinct(),
            'krs' => DB::connection('simak_main')->table('s1m4k_krs')
                ->whereIn('id_kalender', $calendarIds)
                ->select('id_mahasiswa')
                ->distinct(),
            'approved' => DB::connection('simak_main')->table('s1m4k_pengesahankrs')
                ->whereIn('id_kalender', $calendarIds)
                ->whereNotNull('nama_kpro')
                ->whereRaw("TRIM(nama_kpro) <> ''")
                ->select('id_mahasiswa')
                ->distinct(),
        };

        return DB::connection('simak_main')->query()
            ->fromSub($processStudents, 'process')
            ->leftJoin('s1m4k_mastermhs as student', 'process.id_mahasiswa', '=', 'student.id_mahasiswa')
            ->select([
                'process.id_mahasiswa',
                'student.nim',
                'student.nama_mahasiswa',
                'student.id_status_mahasiswa',
            ])
            ->orderBy('student.nim');
    }

    private function krsByProgram(Collection $calendarIds): array
    {
        if ($calendarIds->isEmpty()) {
            return [];
        }

        return DB::connection('simak_main')->table('s1m4k_krs as krs')
            ->join('s1m4k_kalender as calendar', 'krs.id_kalender', '=', 'calendar.id_kalender')
            ->whereIn('krs.id_kalender', $calendarIds)
            ->selectRaw("calendar.id_prodi, COUNT(DISTINCT krs.id_mahasiswa) as students, COUNT(DISTINCT CASE WHEN krs.input_nilai = 'Y' THEN krs.id_mahasiswa END) as grades")
            ->groupBy('calendar.id_prodi')->get()
            ->mapWithKeys(fn (object $row): array => [(string) $row->id_prodi => [
                'students' => (int) $row->students,
                'grades' => (int) $row->grades,
            ]])->all();
    }

    private function approvalsByProgram(Collection $calendarIds): array
    {
        if ($calendarIds->isEmpty()) {
            return [];
        }

        return DB::connection('simak_main')->table('s1m4k_pengesahankrs as approval')
            ->join('s1m4k_kalender as calendar', 'approval.id_kalender', '=', 'calendar.id_kalender')
            ->whereIn('approval.id_kalender', $calendarIds)
            ->whereNotNull('approval.nama_kpro')
            ->whereRaw("TRIM(approval.nama_kpro) <> ''")
            ->selectRaw('calendar.id_prodi, COUNT(DISTINCT approval.id_mahasiswa) as total')
            ->groupBy('calendar.id_prodi')->get()
            ->mapWithKeys(fn (object $row): array => [(string) $row->id_prodi => (int) $row->total])->all();
    }

    private function akmByProgram(Collection $calendarIds): array
    {
        if ($calendarIds->isEmpty()) {
            return [];
        }

        return DB::connection('simak_main')->table('s1m4k_akm')
            ->whereIn('id_kalender', $calendarIds)
            ->selectRaw("id_prodi, AVG(NULLIF(ips, '')) as average_ips, AVG(NULLIF(ipk, '')) as average_ipk")
            ->groupBy('id_prodi')->get()
            ->mapWithKeys(fn (object $row): array => [(string) $row->id_prodi => [
                'ips' => $row->average_ips !== null ? (float) $row->average_ips : null,
                'ipk' => $row->average_ipk !== null ? (float) $row->average_ipk : null,
            ]])->all();
    }

    private function edomScoresByProgram(Collection $calendarIds): array
    {
        if ($calendarIds->isEmpty()) {
            return [];
        }

        return DB::connection('simak_main')->table('s1m4k_edom as response')
            ->join('s1m4k_kalender as calendar', 'response.id_kalender', '=', 'calendar.id_kalender')
            ->whereIn('response.id_kalender', $calendarIds)
            ->selectRaw('calendar.id_prodi, SUM(response.nilai) as score_sum, COUNT(response.id_edom) as response_count')
            ->groupBy('calendar.id_prodi')->get()
            ->mapWithKeys(function (object $row): array {
                $responses = (int) $row->response_count;

                return [(string) $row->id_prodi => [
                    'average' => $responses > 0 ? round(((float) $row->score_sum / $responses) / 5 * 100, 1) : null,
                    'responses' => $responses,
                ]];
            })->all();
    }

    private function topEdomLecturers(Collection $calendarIds): Collection
    {
        if ($calendarIds->isEmpty()) {
            return collect();
        }

        $teachingAssignments = null;
        foreach (range(1, 9) as $slot) {
            $query = DB::connection('simak_main')->table('s1m4k_jadwal as schedule')
                ->whereIn('schedule.id_kalender', $calendarIds)
                ->whereNotNull('schedule.dosen'.$slot)
                ->whereRaw("TRIM(schedule.dosen{$slot}) <> ''")
                ->select([
                    'schedule.id_jadwal', 'schedule.id_kalender', 'schedule.id_matkul',
                    'schedule.nama_kelas',
                ])
                ->selectRaw('schedule.dosen'.$slot.' as id_dosen');
            $teachingAssignments = $teachingAssignments === null
                ? $query
                : $teachingAssignments->union($query);
        }

        return DB::connection('simak_main')->query()->fromSub($teachingAssignments, 'teaching')
            ->join('s1m4k_edom as response', function ($join): void {
                $join->on('response.id_jadwal', '=', 'teaching.id_jadwal')
                    ->on('response.id_kalender', '=', 'teaching.id_kalender');
            })
            ->join('s1m4k_krs as registration', 'response.id_krs', '=', 'registration.id_krs')
            ->join('s1m4k_masterdosen as lecturer', 'teaching.id_dosen', '=', 'lecturer.id_dosen')
            ->whereIn('response.id_kalender', $calendarIds)
            ->selectRaw('lecturer.id_dosen, lecturer.nama_dosen, lecturer.gelar_depan, lecturer.gelar_belakang')
            ->selectRaw('SUM(response.nilai) as score_sum, COUNT(response.id_edom) as response_count')
            ->selectRaw('COUNT(DISTINCT teaching.id_matkul) as course_count, COUNT(DISTINCT teaching.nama_kelas) as class_count, COUNT(DISTINCT registration.id_krs) as respondent_count')
            ->groupBy('lecturer.id_dosen', 'lecturer.nama_dosen', 'lecturer.gelar_depan', 'lecturer.gelar_belakang')
            ->orderByRaw('SUM(response.nilai) / NULLIF(COUNT(response.id_edom), 0) DESC')
            ->orderByDesc('response_count')->limit(10)->get()
            ->map(fn (object $row): array => [
                'id' => (string) $row->id_dosen,
                'name' => LecturerName::format(
                    $row->nama_dosen ? ucwords(mb_strtolower((string) $row->nama_dosen)) : null,
                    $row->gelar_depan,
                    $row->gelar_belakang,
                ),
                'average_score' => round(((float) $row->score_sum / max(1, (int) $row->response_count)) / 5 * 100, 1),
                'response_count' => (int) $row->response_count,
                'course_count' => (int) $row->course_count,
                'class_count' => (int) $row->class_count,
                'respondent_count' => (int) $row->respondent_count,
            ])->values();
    }

    private function trendsByProgram(Collection $programs, Collection $calendarRows, array $years): Collection
    {
        $averages = [];
        $programIds = $programs->pluck('id_prodi')->values();
        if ($programIds->isNotEmpty()) {
            $averages = DB::connection('simak_main')->table('s1m4k_akm as akm')
                ->join('s1m4k_kalender as calendar', 'akm.id_kalender', '=', 'calendar.id_kalender')
                ->whereIn('calendar.id_prodi', $programIds)
                ->whereRaw('SUBSTR(calendar.tahun, 1, 4) BETWEEN ? AND ?', [
                    (string) min($years),
                    (string) max($years),
                ])
                ->selectRaw("calendar.id_prodi, SUBSTR(calendar.tahun, 1, 4) as academic_year, AVG(NULLIF(akm.ips, '')) as average_ips, AVG(NULLIF(akm.ipk, '')) as average_ipk")
                ->groupBy('calendar.id_prodi', 'academic_year')->get()
                ->keyBy(fn (object $row): string => $row->id_prodi.'|'.$row->academic_year)
                ->all();
        }

        return $programs->map(function (object $program) use ($years, $averages): array {
            $values = [];
            foreach ($years as $year) {
                $row = $averages[$program->id_prodi.'|'.$year] ?? null;
                $values[$year] = [
                    'ips' => $row?->average_ips !== null ? (float) $row->average_ips : null,
                    'ipk' => $row?->average_ipk !== null ? (float) $row->average_ipk : null,
                ];
            }

            return [
                'id' => (string) $program->id_prodi,
                'name' => trim($program->nama_program_studi.' '.($program->nama_jenjang_pendidikan ?? '')),
                'values' => $values,
            ];
        })->values();
    }

    private function percentage(int|float $value, int|float $total): string
    {
        return ($total > 0 ? round($value / $total * 100) : 0).'%';
    }

    private function scopeLabel(object $user, Collection $programs): string
    {
        if (in_array($user->role, ['admin', 'pimuniv'], true)) {
            return 'Seluruh universitas';
        }
        if ($user->role === 'pimfak') {
            return $programs->pluck('nama_fakultas')->filter()->unique()->implode(', ') ?: 'Cakupan fakultas belum ditetapkan';
        }

        $program = $programs->first();

        return $program !== null
            ? 'Program studi '.trim($program->nama_program_studi.' '.($program->nama_jenjang_pendidikan ?? ''))
            : 'Cakupan program studi belum ditetapkan';
    }
}
