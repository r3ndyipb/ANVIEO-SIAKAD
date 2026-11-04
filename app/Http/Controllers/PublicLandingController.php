<?php

namespace App\Http\Controllers;

use App\Services\InstitutionSettings;
use App\Support\DashboardStudentStatus;
use App\Support\DashboardTeachingStaffCounts;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PublicLandingController extends Controller
{
    private const VISITOR_CACHE_PREFIX = 'public-visitors:v3:';

    private const VISITOR_SERIES_CACHE_PREFIX = 'public-visitor-series:v1:';

    private const VISITOR_COOKIE = 'simak_public_visitor';

    public function home(Request $request): Response
    {
        return $this->page($request, 'home', [
            'pageTitle' => 'Dashboard Universitas',
            'scopeName' => $this->universityName(),
        ]);
    }

    public function dtpsDetails(Request $request, string $program): JsonResponse
    {
        abort_unless($this->programRows()->contains(
            static fn (object $row): bool => (string) $row->id_prodi === $program
        ), 404);
        $filters = $request->validate([
            'category' => ['required', 'in:all,homebase,non_homebase'],
        ]);

        return response()->json(DashboardTeachingStaffCounts::detailsForProgram(
            $program,
            category: $filters['category'] === 'all' ? null : $filters['category'],
        ));
    }

    public function faculties(Request $request): Response
    {
        return $this->page($request, 'faculties', [
            'pageTitle' => 'Dashboard Fakultas',
            'scopeName' => 'Seluruh fakultas',
        ]);
    }

    public function faculty(Request $request, string $faculty): Response
    {
        $facultyRecord = DB::connection('simak_main')->table('s1m4k_masterfakultas')
            ->where('id_fakultas', $faculty)
            ->whereExists(function ($programs): void {
                $programs->selectRaw('1')->from('s1m4k_masterprodi as program')
                    ->whereColumn('program.id_fakultas', 's1m4k_masterfakultas.id_fakultas')
                    ->where('program.status', 'A');
            })
            ->first(['id_fakultas', 'nama_fakultas']);
        abort_if($facultyRecord === null, 404);

        return $this->page($request, 'faculty', [
            'pageTitle' => 'Dashboard Fakultas',
            'scopeName' => $facultyRecord->nama_fakultas ?: 'Fakultas',
            'parentName' => $this->universityName(),
            'parentUrl' => route('public.faculties.index'),
        ]);
    }

    public function programs(Request $request): Response
    {
        return $this->page($request, 'programs', [
            'pageTitle' => 'Dashboard Program Studi',
            'scopeName' => 'Seluruh program studi',
        ]);
    }

    public function program(Request $request, string $program): Response
    {
        $programRecord = DB::connection('simak_main')->table('s1m4k_masterprodi as prodi')
            ->leftJoin('s1m4k_masterfakultas as fakultas', 'prodi.id_fakultas', '=', 'fakultas.id_fakultas')
            ->where('prodi.id_prodi', $program)
            ->where('prodi.status', 'A')
            ->whereNotNull('prodi.nama_program_studi')
            ->first([
                'prodi.id_prodi', 'prodi.id_fakultas', 'prodi.nama_program_studi',
                'prodi.nama_jenjang_pendidikan', 'fakultas.nama_fakultas',
            ]);
        abort_if($programRecord === null, 404);

        return $this->page($request, 'program', [
            'pageTitle' => 'Dashboard Program Studi',
            'scopeName' => $this->programName($programRecord),
            'parentName' => $programRecord->nama_fakultas ?: 'Seluruh fakultas',
            'parentUrl' => filled($programRecord->id_fakultas)
                ? route('public.faculties.show', $programRecord->id_fakultas)
                : route('public.faculties.index'),
        ]);
    }

    public function data(Request $request, string $pageType, ?string $identifier = null): JsonResponse
    {
        abort_unless(in_array($pageType, ['home', 'faculties', 'faculty', 'programs', 'program'], true), 404);
        abort_unless(in_array($pageType, ['faculty', 'program'], true) === ($identifier !== null), 404);

        $cacheKey = 'public-dashboard:v12:'.$pageType.':'.($identifier ?? 'all');
        $dashboard = Cache::remember($cacheKey, now()->addMinute(), function () use ($pageType, $identifier): array {
            $allPrograms = $this->programRows();
            $programs = match ($pageType) {
                'faculty' => $allPrograms->where('id_fakultas', $identifier)->values(),
                'program' => $allPrograms->where('id_prodi', $identifier)->values(),
                default => $allPrograms,
            };
            abort_if(in_array($pageType, ['faculty', 'program'], true) && $programs->isEmpty(), 404);

            $data = $this->dashboardData($programs, $pageType);
            $data['unitRows'] = match ($pageType) {
                'home', 'faculties' => $this->facultySummaries($this->facultyRows(), $data['programRows']),
                default => $data['programRows'],
            };

            return $data;
        });

        return response()->json([
            ...$dashboard,
            'visitors' => $this->recordVisit($pageType, $request),
        ]);
    }

    private function page(Request $request, string $pageType, array $data): Response
    {
        $visitorId = $request->cookie(self::VISITOR_COOKIE);
        if (! is_string($visitorId) || ! Str::isUuid($visitorId)) {
            $visitorId = (string) Str::uuid();
        }
        $siteConfiguration = app(InstitutionSettings::class)->forDisplay();

        return response()->view('public.landing', [
            'pageType' => $pageType,
            'landingData' => [
                ...$data,
                'metrics' => array_map(static fn (string $key): array => ['key' => $key, 'value' => null], [
                    'programs', 'students', 'lecturers', 'courses', 'classes', 'krs',
                    'proposals', 'theses', 'exams',
                ]),
                'unitRows' => [],
                'proposalStatuses' => [],
                'thesisStatuses' => [],
                'visitors' => ['today' => 0, 'month' => 0, 'year' => 0, 'total' => 0, 'series' => []],
                'loginUrl' => route('login'),
                'loginPostUrl' => route('login.store'),
                'dashboardUrl' => route('dashboard'),
                'authenticated' => Auth::check(),
                'authChanged' => (bool) $request->session()->get('auth_changed', false),
                'dataUrl' => route('public.dashboard-data', [
                    'pageType' => $pageType,
                    'identifier' => $pageType === 'faculty' ? request()->route('faculty') : ($pageType === 'program' ? request()->route('program') : null),
                ]),
                'csrfToken' => csrf_token(),
                'forgotPasswordUrl' => route('password.request'),
                'resetPasswordUrl' => route('password.email'),
                'loginErrors' => $request->session()->get('errors')?->getBag('default')->getMessages() ?? [],
                'loginValue' => (string) $request->session()->get('_old_input.login', ''),
                'resetErrors' => $request->session()->get('errors')?->getBag('passwordReset')->getMessages() ?? [],
                'resetLoginValue' => (string) $request->session()->get('_old_input.login', ''),
                'resetPasswordStatus' => $request->session()->get('reset_password_status'),
                'openResetPassword' => (bool) $request->session()->get('open_reset_password', false),
                'openLogin' => (bool) $request->session()->get('open_login', false),
                'homeUrl' => route('public.home'),
                'facultiesUrl' => route('public.faculties.index'),
                'programsUrl' => route('public.programs.index'),
                'institutionName' => $siteConfiguration['institution_name'],
                'appName' => $siteConfiguration['app_name'],
                'appFullName' => $siteConfiguration['app_full_name'],
                'motto' => $siteConfiguration['motto'],
                'logoUrl' => $siteConfiguration['logo_url'],
                'heroImages' => $siteConfiguration['hero_images'],
            ],
        ])->withCookie(cookie(
            self::VISITOR_COOKIE,
            $visitorId,
            525600,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            'lax',
        ));
    }

    private function dashboardData(Collection $programs, string $pageType): array
    {
        $programIds = $programs->pluck('id_prodi')->map(static fn ($id): string => (string) $id)->values();
        $programRows = $this->programSummaries($programs);
        $studentDistribution = $this->studentDistribution($programs, $programIds, $pageType);
        $metrics = [
            ['key' => 'programs', 'value' => $programIds->count()],
            ['key' => 'students', 'value' => $this->countByProgram('s1m4k_mastermhs', $programIds, 'mhs.id_prodi', 'mhs')],
            ['key' => 'lecturers', 'value' => $this->countByProgram('s1m4k_masterdosen', $programIds, 'dosen.id_prodi', 'dosen')],
            ['key' => 'courses', 'value' => $this->countByProgram('s1m4k_mastermk', $programIds, 'matkul.id_prodi', 'matkul')],
            ['key' => 'classes', 'value' => $this->countByProgram('s1m4k_jadwal', $programIds, 'jadwal.id_prodi', 'jadwal')],
            ['key' => 'krs', 'value' => $this->countByProgram('s1m4k_krs', $programIds, 'krs.id_prodi', 'krs')],
            ['key' => 'proposals', 'value' => $this->countByProgram('eskripsi_judul', $programIds, 'proposal.id_prodi', 'proposal')],
            ['key' => 'theses', 'value' => $this->countByProgram('eskripsi_skripsi', $programIds, 'skripsi.id_prodi', 'skripsi')],
            ['key' => 'exams', 'value' => $this->examCount($programIds)],
        ];

        return [
            'metrics' => $metrics,
            'programRows' => $programRows,
            'studentTotal' => $metrics[1]['value'],
            'studentDistributionTotal' => array_sum(array_column($studentDistribution, 'total')),
            'studentIntake' => $this->studentIntake($programs, $pageType),
            'studentDistribution' => $studentDistribution,
            'proposalStatuses' => $this->statusBreakdown('eskripsi_judul', 'status', 'proposal', $programIds),
            'thesisStatuses' => $this->statusBreakdown('eskripsi_skripsi', 'status', 'skripsi', $programIds),
        ];
    }

    private function studentIntake(Collection $programs, string $pageType): array
    {
        $eligiblePrograms = $programs->reject(fn (object $program): bool => $this->isProfessionalProgram($program));
        $years = range(now()->year - 5, now()->year);
        $programIds = $eligiblePrograms->pluck('id_prodi')->map(static fn ($id): string => (string) $id)->values();
        if ($programIds->isEmpty()) {
            return ['available' => false, 'years' => [], 'series' => []];
        }

        $studentQuery = DB::connection('simak_main')->table('s1m4k_mastermhs as mhs')
            ->join('s1m4k_masterprodi as prodi', 'mhs.id_prodi', '=', 'prodi.id_prodi')
            ->leftJoin('s1m4k_masterfakultas as fakultas', 'prodi.id_fakultas', '=', 'fakultas.id_fakultas')
            ->selectRaw('mhs.id_prodi, prodi.nama_program_studi, prodi.nama_jenjang_pendidikan, prodi.id_fakultas, fakultas.nama_fakultas, SUBSTR(mhs.id_periode_masuk, 1, 4) as angkatan, COUNT(DISTINCT mhs.id_mahasiswa) as total')
            ->whereIn('mhs.id_prodi', $programIds->all())
            ->where('prodi.status', 'A')
            ->whereNotNull('id_periode_masuk')
            ->whereRaw("TRIM(id_periode_masuk) <> ''")
            ->whereRaw('SUBSTR(id_periode_masuk, 1, 4) BETWEEN ? AND ?', [
                (string) min($years),
                (string) max($years),
            ])
            ->groupBy('mhs.id_prodi', 'prodi.nama_program_studi', 'prodi.nama_jenjang_pendidikan', 'prodi.id_fakultas', 'fakultas.nama_fakultas', 'angkatan');
        $rows = $studentQuery->get();

        $groupBy = match ($pageType) {
            'faculties' => 'faculty',
            'programs' => 'program',
            default => 'scope',
        };
        $series = [];
        foreach ($eligiblePrograms as $program) {
            $key = match ($groupBy) {
                'faculty' => (string) ($program->id_fakultas ?? 'unassigned'),
                'program' => (string) $program->id_prodi,
                default => 'scope',
            };
            $label = match ($groupBy) {
                'faculty' => $program->nama_fakultas ?: 'Fakultas belum ditetapkan',
                'program' => $this->programName($program),
                default => 'Seluruh mahasiswa',
            };
            $series[$key] ??= [
                'id' => $key,
                'label' => $label,
                'values' => array_fill_keys($years, 0),
            ];
        }

        foreach ($rows as $row) {
            $key = match ($groupBy) {
                'faculty' => (string) ($row->id_fakultas ?? 'unassigned'),
                'program' => (string) $row->id_prodi,
                default => 'scope',
            };
            $label = match ($groupBy) {
                'faculty' => $row->nama_fakultas ?: 'Fakultas belum ditetapkan',
                'program' => $this->programName($row),
                default => 'Seluruh mahasiswa',
            };
            $series[$key] ??= [
                'id' => $key,
                'label' => $label,
                'values' => array_fill_keys($years, 0),
            ];
            $series[$key]['values'][(int) $row->angkatan] += (int) $row->total;
        }

        $series = collect($series)->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)->values()
            ->map(static fn (array $item): array => [
                ...$item,
                'values' => array_map(static fn (int $year): int => $item['values'][$year], $years),
            ])->all();

        return ['available' => true, 'years' => $years, 'series' => $series];
    }

    private function isProfessionalProgram(object $program): bool
    {
        $name = mb_strtolower(trim((string) ($program->nama_program_studi ?? '')));
        $level = mb_strtolower(trim((string) ($program->nama_jenjang_pendidikan ?? '')));

        return str_contains($name, 'profesi') || str_contains($level, 'profesi');
    }

    private function studentDistribution(Collection $programs, Collection $programIds, string $pageType): array
    {
        if ($programIds->isEmpty()) {
            return [];
        }

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
                prodi.id_prodi,
                prodi.nama_program_studi,
                prodi.nama_jenjang_pendidikan,
                prodi.id_fakultas,
                COALESCE(NULLIF(TRIM(fakultas.nama_fakultas), ''), 'Fakultas belum ditetapkan') as nama_fakultas,
                COUNT(DISTINCT mhs.id_mahasiswa) as total
            ")
            ->whereIn('mhs.id_status_mahasiswa', DashboardStudentStatus::INCLUDED)
            ->whereNotNull('mhs.id_wilayah')
            ->whereRaw("TRIM(mhs.id_wilayah) <> ''")
            ->whereIn('mhs.id_prodi', $programIds->all())
            ->where('prodi.status', 'A')
            ->groupBy('provinsi', 'wilayah.lat_provinsi', 'wilayah.lng_provinsi', 'prodi.id_prodi', 'prodi.nama_program_studi', 'prodi.nama_jenjang_pendidikan', 'prodi.id_fakultas', 'nama_fakultas')
            ->orderBy('provinsi');
        DashboardStudentStatus::applyStudentDataRequirements($query, 'mhs');

        $provinces = [];
        foreach ($query->get() as $row) {
            $province = (string) $row->provinsi;
            $latitude = (float) $row->lat_provinsi;
            $longitude = (float) $row->lng_provinsi;
            if ($province === 'Maluku Utara' || $latitude === 0.0 || $longitude === 0.0) {
                $latitude = 1.5709922;
                $longitude = 127.8087685;
            }

            if (! isset($provinces[$province])) {
                $provinces[$province] = [
                    'province' => $province,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'total' => 0,
                    'faculties' => [],
                    'programs' => [],
                    'units' => [],
                ];
            }

            $faculty = (string) $row->nama_fakultas;
            $program = $this->programName($row);
            $programId = (string) $row->id_prodi;
            $provinces[$province]['total'] += (int) $row->total;
            $provinces[$province]['faculties'][$faculty] = ($provinces[$province]['faculties'][$faculty] ?? 0) + (int) $row->total;
            $provinces[$province]['programs'][$programId] ??= ['label' => $program, 'total' => 0];
            $provinces[$province]['programs'][$programId]['total'] += (int) $row->total;
        }

        foreach ($provinces as &$province) {
            foreach ($programs->pluck('nama_fakultas')->filter()->unique() as $faculty) {
                $province['faculties'][$faculty] ??= 0;
            }
            ksort($province['faculties']);
            uasort($province['programs'], static fn (array $left, array $right): int => strnatcasecmp($left['label'], $right['label']));
            $province['programs'] = array_values($province['programs']);
            $province['units'] = match ($pageType) {
                'faculties' => collect($province['faculties'])->map(static fn (int $total, string $label): array => ['label' => $label, 'total' => $total])->values()->all(),
                'programs' => $province['programs'],
                'faculty' => $province['programs'],
                'program' => $province['programs'],
                default => collect($province['faculties'])->map(static fn (int $total, string $label): array => ['label' => $label, 'total' => $total])->values()->all(),
            };
        }
        unset($province);

        return array_values($provinces);
    }

    private function programSummaries(Collection $programs): array
    {
        $ids = $programs->pluck('id_prodi')->map(static fn ($id): string => (string) $id)->values();
        $counts = [];
        foreach ([
            'students' => ['s1m4k_mastermhs', 'id_prodi'],
            'lecturers' => ['s1m4k_masterdosen', 'id_prodi'],
            'courses' => ['s1m4k_mastermk', 'id_prodi'],
            'classes' => ['s1m4k_jadwal', 'id_prodi'],
            'krs' => ['s1m4k_krs', 'id_prodi'],
            'proposals' => ['eskripsi_judul', 'id_prodi'],
            'theses' => ['eskripsi_skripsi', 'id_prodi'],
        ] as $key => [$table, $column]) {
            $counts[$key] = $this->groupCounts($table, $column, $ids);
        }
        $counts['dtps'] = DashboardTeachingStaffCounts::forPrograms($ids);
        $counts['exams'] = $this->groupExamCounts($ids);

        return $programs->map(function (object $program) use ($counts): array {
            $id = (string) $program->id_prodi;

            return [
                'id' => $id,
                'name' => $this->programName($program),
                'faculty' => $program->nama_fakultas ?: '',
                'facultyId' => (string) ($program->id_fakultas ?? ''),
                'url' => route('public.programs.show', $id),
                'dtpsDetailsUrl' => route('public.dtps-details', $id),
                'students' => $counts['students'][$id] ?? 0,
                'lecturers' => $counts['lecturers'][$id] ?? 0,
                'dtps' => $counts['dtps'][$id]['total'] ?? 0,
                'dtpsHomebase' => $counts['dtps'][$id]['homebase'] ?? 0,
                'dtpsNonHomebase' => $counts['dtps'][$id]['non_homebase'] ?? 0,
                'courses' => $counts['courses'][$id] ?? 0,
                'classes' => $counts['classes'][$id] ?? 0,
                'krs' => $counts['krs'][$id] ?? 0,
                'proposals' => $counts['proposals'][$id] ?? 0,
                'theses' => $counts['theses'][$id] ?? 0,
                'exams' => $counts['exams'][$id] ?? 0,
            ];
        })->values()->all();
    }

    private function facultySummaries(Collection $faculties, array $programRows): array
    {
        $programsByFaculty = collect($programRows)->groupBy('facultyId');

        return $faculties->map(static function (object $faculty) use ($programsByFaculty): array {
            $rows = $programsByFaculty->get((string) $faculty->id_fakultas, collect());
            $sum = static fn (string $field): int => (int) $rows->sum($field);

            return [
                'id' => (string) $faculty->id_fakultas,
                'name' => $faculty->nama_fakultas ?: 'Fakultas',
                'url' => route('public.faculties.show', $faculty->id_fakultas),
                'programCount' => $rows->count(),
                'students' => $sum('students'),
                'lecturers' => $sum('lecturers'),
                'dtps' => $sum('dtps'),
                'dtpsHomebase' => $sum('dtpsHomebase'),
                'dtpsNonHomebase' => $sum('dtpsNonHomebase'),
                'courses' => $sum('courses'),
                'classes' => $sum('classes'),
                'krs' => $sum('krs'),
                'proposals' => $sum('proposals'),
                'theses' => $sum('theses'),
                'exams' => $sum('exams'),
            ];
        })->values()->all();
    }

    private function countByProgram(string $table, Collection $programIds, string $column, string $alias): int
    {
        return array_sum($this->groupCounts($table, $column, $programIds, $alias));
    }

    private function groupCounts(string $table, string $column, Collection $programIds, string $alias = ''): array
    {
        if ($programIds->isEmpty()) {
            return [];
        }
        $query = DB::connection('simak_main')->table($table);
        if ($alias !== '') {
            $query->from($table.' as '.$alias);
        }
        if ($table === 's1m4k_mastermhs') {
            $query->whereIn(
                ($alias !== '' ? $alias.'.' : '').'id_status_mahasiswa',
                DashboardStudentStatus::INCLUDED,
            );
            DashboardStudentStatus::applyStudentDataRequirements($query, $alias);
        }

        return $query->whereIn($column, $programIds->all())
            ->selectRaw($column.' as program_id, COUNT(*) as total')
            ->groupBy($column)
            ->pluck('total', 'program_id')
            ->map(static fn ($value): int => (int) $value)
            ->all();
    }

    private function groupExamCounts(Collection $programIds): array
    {
        if ($programIds->isEmpty()) {
            return [];
        }

        return DB::connection('simak_main')->table('eskripsi_ujian as ujian')
            ->join('eskripsi_skripsi as skripsi', 'ujian.id_skripsi', '=', 'skripsi.id')
            ->whereIn('skripsi.id_prodi', $programIds->all())
            ->selectRaw('skripsi.id_prodi as program_id, COUNT(*) as total')
            ->groupBy('skripsi.id_prodi')
            ->pluck('total', 'program_id')
            ->map(static fn ($value): int => (int) $value)
            ->all();
    }

    private function examCount(Collection $programIds): int
    {
        return array_sum($this->groupExamCounts($programIds));
    }

    private function statusBreakdown(string $table, string $statusColumn, string $alias, Collection $programIds): array
    {
        if ($programIds->isEmpty()) {
            return [];
        }

        $query = DB::connection('simak_main')->table($table.' as '.$alias);
        $this->applyScope($query, $programIds, $alias.'.id_prodi');

        return $query->selectRaw('CAST('.$alias.'.'.$statusColumn.' AS CHAR) as label, COUNT(*) as total')
            ->groupBy('label')
            ->orderBy('label')
            ->get()
            ->map(static fn (object $row): array => ['label' => (string) $row->label, 'value' => (int) $row->total])
            ->all();
    }

    private function applyScope(Builder $query, Collection $programIds, string $column): void
    {
        if ($programIds->isEmpty()) {
            $query->whereRaw('1 = 0');

            return;
        }
        $query->whereIn($column, $programIds->all());
    }

    private function facultyRows(): Collection
    {
        return DB::connection('simak_main')->table('s1m4k_masterfakultas')
            ->whereNotNull('nama_fakultas')
            ->whereExists(function ($programs): void {
                $programs->selectRaw('1')->from('s1m4k_masterprodi as program')
                    ->whereColumn('program.id_fakultas', 's1m4k_masterfakultas.id_fakultas')
                    ->where('program.status', 'A');
            })
            ->orderBy('nama_fakultas')
            ->get(['id_fakultas', 'nama_fakultas']);
    }

    private function programRows(): Collection
    {
        return DB::connection('simak_main')->table('s1m4k_masterprodi as prodi')
            ->leftJoin('s1m4k_masterfakultas as fakultas', 'prodi.id_fakultas', '=', 'fakultas.id_fakultas')
            ->where('prodi.status', 'A')
            ->whereNotNull('prodi.nama_program_studi')
            ->orderBy('fakultas.nama_fakultas')
            ->orderBy('prodi.nama_program_studi')
            ->get([
                'prodi.id_prodi', 'prodi.id_fakultas', 'prodi.nama_program_studi',
                'prodi.nama_jenjang_pendidikan', 'fakultas.nama_fakultas',
            ]);
    }

    private function universityName(): string
    {
        return (string) (DB::connection('simak_main')->table('s1m4k_masterpt')
            ->whereNotNull('nama_perguruan_tinggi')
            ->value('nama_perguruan_tinggi') ?: 'Perguruan Tinggi');
    }

    private function programName(object $program): string
    {
        $name = trim((string) $program->nama_program_studi);
        $degree = trim((string) ($program->nama_jenjang_pendidikan ?? ''));
        if ($degree === '' || strcasecmp(substr($name, -strlen($degree)), $degree) === 0) {
            return $name;
        }

        return trim($name.' '.$degree);
    }

    private function recordVisit(string $pageType, Request $request): array
    {
        $cache = Cache::store();
        $now = CarbonImmutable::instance(now());
        $visitorId = $request->cookie(self::VISITOR_COOKIE);
        if (! is_string($visitorId) || ! Str::isUuid($visitorId)) {
            $visitorId = $request->session()->getId();
        }
        $visitorId = hash_hmac('sha256', $visitorId, (string) config('app.key'));

        $daily = [];
        for ($daysAgo = 6; $daysAgo >= 0; $daysAgo--) {
            $date = $now->subDays($daysAgo);
            $this->primeVisitorSeriesCounter($cache, 'daily', $date->format('Y-m-d'));
            $daily[] = $this->visitorPoint($cache, $date->format('Y-m-d'), $date->format('Y-m-d'), 'daily');
        }
        $monthly = [];
        for ($monthsAgo = 11; $monthsAgo >= 0; $monthsAgo--) {
            $date = $now->startOfMonth()->subMonths($monthsAgo);
            $this->primeVisitorSeriesCounter($cache, 'monthly', $date->format('Y-m'));
            $monthly[] = $this->visitorPoint($cache, $date->format('Y-m'), $date->format('Y-m'), 'monthly');
        }
        $yearly = [];
        for ($yearsAgo = 4; $yearsAgo >= 0; $yearsAgo--) {
            $date = $now->startOfYear()->subYears($yearsAgo);
            $this->primeVisitorSeriesCounter($cache, 'yearly', $date->format('Y'));
            $yearly[] = $this->visitorPoint($cache, $date->format('Y'), $date->format('Y'), 'yearly');
        }

        $this->incrementForUniqueVisitor($cache, 'all-time', $visitorId, null, 'total');
        $this->incrementForUniqueVisitor($cache, 'day', $visitorId, $now->format('Y-m-d'), 'daily');
        $this->incrementForUniqueVisitor($cache, 'month', $visitorId, $now->format('Y-m'), 'monthly');
        $this->incrementForUniqueVisitor($cache, 'year', $visitorId, $now->format('Y'), 'yearly');
        $this->incrementForUniqueVisitor(
            $cache,
            'page-'.$pageType,
            $visitorId,
            $now->format('Y-m-d'),
            'page:'.$pageType,
        );

        foreach ($daily as &$point) {
            $point['value'] = (int) $cache->get($this->visitorSeriesCounterKey('daily', $point['label']), 0);
        }
        unset($point);
        foreach ($monthly as &$point) {
            $point['value'] = (int) $cache->get($this->visitorSeriesCounterKey('monthly', $point['label']), 0);
        }
        unset($point);
        foreach ($yearly as &$point) {
            $point['value'] = (int) $cache->get($this->visitorSeriesCounterKey('yearly', $point['label']), 0);
        }
        unset($point);

        return [
            'today' => (int) $cache->get($this->visitorCounterKey('daily', $now->format('Y-m-d')), 0),
            'month' => (int) $cache->get($this->visitorCounterKey('monthly', $now->format('Y-m')), 0),
            'year' => (int) $cache->get($this->visitorCounterKey('yearly', $now->format('Y')), 0),
            'total' => (int) $cache->get(self::VISITOR_CACHE_PREFIX.'total', 0),
            'pageToday' => (int) $cache->get(
                $this->visitorCounterKey('page:'.$pageType, $now->format('Y-m-d')),
                0,
            ),
            'series' => [
                'daily' => $daily,
                'monthly' => $monthly,
                'yearly' => $yearly,
            ],
        ];
    }

    private function incrementForUniqueVisitor(
        mixed $cache,
        string $periodType,
        string $visitorId,
        ?string $period,
        string $counterType,
    ): void {
        $identity = $period === null
            ? self::VISITOR_CACHE_PREFIX.'unique:'.$visitorId
            : self::VISITOR_CACHE_PREFIX.'unique:'.$periodType.':'.$period.':'.$visitorId;
        $identityExpiration = $period === null
            ? CarbonImmutable::instance(now())->addYear()
            : $this->periodExpiration($counterType, $period);
        if (! $cache->add($identity, true, $identityExpiration)) {
            return;
        }

        $counterKey = $this->visitorCounterKey($counterType, $period);
        $expiration = $period === null ? null : $this->visitorCounterExpiration($counterType, $period);
        if ($expiration === null) {
            $cache->add($counterKey, 0);
        } else {
            $cache->add($counterKey, 0, $expiration);
        }
        $cache->increment($counterKey);
        if (in_array($counterType, ['daily', 'monthly', 'yearly'], true) && $period !== null) {
            $cache->increment($this->visitorSeriesCounterKey($counterType, $period));
        }
    }

    private function visitorPoint(mixed $cache, string $label, string $period, string $counterType): array
    {
        return [
            'label' => $label,
            'value' => (int) $cache->get($this->visitorSeriesCounterKey($counterType, $period), 0),
        ];
    }

    private function primeVisitorSeriesCounter(mixed $cache, string $counterType, string $period): void
    {
        $cache->add(
            $this->visitorSeriesCounterKey($counterType, $period),
            (int) $cache->get($this->visitorCounterKey($counterType, $period), 0),
            $this->visitorCounterExpiration($counterType, $period),
        );
    }

    private function visitorSeriesCounterKey(string $counterType, string $period): string
    {
        return self::VISITOR_SERIES_CACHE_PREFIX.$counterType.':'.$period;
    }

    private function visitorCounterKey(string $counterType, ?string $period): string
    {
        return $counterType === 'total'
            ? self::VISITOR_CACHE_PREFIX.'total'
            : self::VISITOR_CACHE_PREFIX.$counterType.':'.$period;
    }

    private function periodExpiration(string $counterType, string $period): ?CarbonImmutable
    {
        if (str_starts_with($counterType, 'page:')) {
            return CarbonImmutable::parse($period, config('app.timezone'))->endOfDay();
        }

        return match ($counterType) {
            'daily' => CarbonImmutable::parse($period, config('app.timezone'))->endOfDay(),
            'monthly' => CarbonImmutable::createFromFormat('!Y-m', $period, config('app.timezone'))->endOfMonth(),
            'yearly' => CarbonImmutable::createFromFormat('!Y', $period, config('app.timezone'))->endOfYear(),
            default => null,
        };
    }

    private function visitorCounterExpiration(string $counterType, string $period): ?CarbonImmutable
    {
        $periodEnd = $this->periodExpiration($counterType, $period);

        return match ($counterType) {
            'daily' => $periodEnd?->addDays(7),
            'monthly' => $periodEnd?->addMonths(12),
            'yearly' => $periodEnd?->addYears(5),
            default => $periodEnd,
        };
    }
}
