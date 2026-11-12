<?php

namespace Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicLandingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['database.connections.simak_main' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ], 'cache.default' => 'array']);
        DB::purge('simak_main');
        Cache::store('array')->flush();
        $this->createSchema();
        $this->seedData();
    }

    public function test_homepage_shell_renders_before_aggregate_dashboard_data(): void
    {
        $response = $this->get('/');
        $response->assertOk()
            ->assertSee('data-landing-fallback', false)
            ->assertSee('SIAKAD (Sistem Informasi Akademik)')
            ->assertSee('--brand-primary: #2167a5', false)
            ->assertSee('name="theme-color" content="#2167a5"', false)
            ->assertSee('Motto Perguruan Tinggi')
            ->assertDontSee('Menghubungkan ilmu, manusia, dan masa depan.')
            ->assertDontSee('Jelajahi ringkasan data akademik Universitas Khairun dalam satu ruang informasi yang terbuka dan mudah dipahami.')
            ->assertSee('public-landing-data')
            ->assertSee('public-fallback-menu', false)
            ->assertSee('name="description"', false)
            ->assertDontSee('smartkampus.id')
            ->assertSee('name="keywords" content="siakad, Sistem Informasi Akademik', false)
            ->assertSee('name="robots" content="index, follow"', false)
            ->assertSee('property="og:site_name" content="siakad AUI"', false)
            ->assertSee('<title>Dashboard Universitas | siakad AUI</title>', false)
            ->assertSee('rel="canonical"', false)
            ->assertSee('ASEAN University International. All Rights Reserved.')
            ->assertDontSee('Kurikulum OBE')
            ->assertSee('dashboard-loading-flash', false)
            ->assertSee('data-page-loader')
            ->assertSee('loading')
            ->assertDontSee('Data agregat lintas layanan akademik, diperbarui dari basis data SIMAK.')
            ->assertSee('Universitas Khairun')
            ->assertDontSee('name="username"');
        $visitorCookie = $response->getCookie('simak_public_visitor');
        $this->assertNotNull($visitorCookie);
        $this->assertTrue($visitorCookie->isHttpOnly());

        $data = $response->viewData('landingData');
        $this->assertNull(collect($data['metrics'])->firstWhere('key', 'programs')['value']);
        $this->withCookie('simak_public_visitor', (string) $visitorCookie->getValue())->withCredentials();
        $this->assertSame(2, $this->getJson($data['dataUrl'])->assertOk()->json('metrics.0.value'));
        $this->assertSame(3, $this->getJson($data['dataUrl'])->assertOk()->json('metrics.1.value'));
        $dashboardJson = $this->getJson($data['dataUrl'])->assertOk()->json();
        $this->assertNull(collect($dashboardJson['metrics'])->firstWhere('key', 'curricula'));
        $this->assertArrayNotHasKey('curriculumStatuses', $dashboardJson);
        $this->assertCount(6, $dashboardJson['studentIntake']['years']);
        $this->assertSame(2, $dashboardJson['studentIntake']['series'][0]['values'][5]);
        $this->assertSame('Seluruh mahasiswa', $dashboardJson['studentIntake']['series'][0]['label']);
        $this->assertSame(3, $dashboardJson['studentDistribution'][0]['total']);
        $homeFacultyBreakdown = collect($dashboardJson['studentDistribution'][0]['units'])
            ->firstWhere('label', 'Fakultas Sains');
        $this->assertSame(2, $homeFacultyBreakdown['total']);
        $visitors = $dashboardJson['visitors'];
        $this->assertSame(1, $visitors['total']);
        $this->assertSame(1, $visitors['today']);
        $this->assertCount(7, $visitors['series']['daily']);
        $this->assertCount(12, $visitors['series']['monthly']);
        $this->assertCount(5, $visitors['series']['yearly']);
    }

    public function test_public_landing_uses_the_master_institution_name(): void
    {
        DB::connection('simak_main')->table('s1m4k_masterpt')->update([
            'nama_perguruan_tinggi' => 'Universitas Dinamis Uji',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Universitas Dinamis Uji')
            ->assertDontSee('Universitas Khairun');
    }

    public function test_visitor_chart_retains_daily_monthly_and_yearly_counters_across_period_boundaries(): void
    {
        Carbon::setTestNow('2025-12-31 12:00:00');

        try {
            $page = $this->get('/');
            $visitorCookie = $page->getCookie('simak_public_visitor');
            $this->assertNotNull($visitorCookie);
            $dataUrl = $page->viewData('landingData')['dataUrl'];
            $this->withCookie('simak_public_visitor', (string) $visitorCookie->getValue())->withCredentials();
            $this->getJson($dataUrl)->assertOk();

            Carbon::setTestNow('2026-01-01 12:00:00');
            $otherVisitor = $this->withCookie('simak_public_visitor', '8a640da5-7218-48b8-8bc5-a3d602493543')
                ->withCredentials()
                ->getJson($dataUrl)->assertOk()->json('visitors');

            $this->assertSame(1, $otherVisitor['series']['daily'][5]['value']);
            $this->assertSame(1, $otherVisitor['series']['monthly'][10]['value']);
            $this->assertSame(1, $otherVisitor['series']['yearly'][3]['value']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_visitor_chart_preserves_existing_current_period_card_counters(): void
    {
        Carbon::setTestNow('2025-12-31 12:00:00');
        Cache::store('array')->put('public-visitors:v3:daily:2025-12-31', 258);
        Cache::store('array')->put('public-visitors:v3:monthly:2025-12', 1948);
        Cache::store('array')->put('public-visitors:v3:yearly:2025', 1948);

        try {
            $page = $this->get('/');
            $visitorCookie = $page->getCookie('simak_public_visitor');
            $this->assertNotNull($visitorCookie);
            $visitors = $this->withCookie('simak_public_visitor', (string) $visitorCookie->getValue())
                ->withCredentials()
                ->getJson($page->viewData('landingData')['dataUrl'])
                ->assertOk()
                ->json('visitors');

            $this->assertSame(259, $visitors['today']);
            $this->assertSame(1949, $visitors['month']);
            $this->assertSame(1949, $visitors['year']);
            $this->assertSame(259, $visitors['series']['daily'][6]['value']);
            $this->assertSame(1949, $visitors['series']['monthly'][11]['value']);
            $this->assertSame(1949, $visitors['series']['yearly'][4]['value']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_dashboard_student_totals_share_a_n_c_m_scope_and_explain_map_coverage(): void
    {
        $connection = DB::connection('simak_main');
        $connection->table('s1m4k_mastermhs')->insert([
            [
                'id_mahasiswa' => 'student-n',
                'id_prodi' => 'program-1',
                'id_status_mahasiswa' => 'N',
                'nim' => 'NPM-N',
                'nama_mahasiswa' => 'Mahasiswa Nonaktif',
                'id_periode_masuk' => now()->year.'3',
                'id_wilayah' => 'region-1',
            ],
            [
                'id_mahasiswa' => 'student-m-no-region',
                'id_prodi' => 'program-1',
                'id_status_mahasiswa' => 'M',
                'nim' => 'NPM-M-NO-REGION',
                'nama_mahasiswa' => 'Mahasiswa Kampus Merdeka',
                'id_periode_masuk' => now()->year.'4',
                'id_wilayah' => null,
            ],
            [
                'id_mahasiswa' => 'student-g-extra',
                'id_prodi' => 'program-1',
                'id_status_mahasiswa' => 'G',
                'nim' => 'NPM-G-EXTRA',
                'nama_mahasiswa' => 'Status G',
                'id_periode_masuk' => now()->year.'5',
                'id_wilayah' => 'region-1',
            ],
            [
                'id_mahasiswa' => 'student-no-nim',
                'id_prodi' => 'program-1',
                'id_status_mahasiswa' => 'A',
                'nim' => null,
                'nama_mahasiswa' => 'Tanpa NIM',
                'id_periode_masuk' => now()->year.'1',
                'id_wilayah' => 'region-1',
            ],
            [
                'id_mahasiswa' => 'student-blank-nim',
                'id_prodi' => 'program-1',
                'id_status_mahasiswa' => 'C',
                'nim' => '  ',
                'nama_mahasiswa' => 'NIM Kosong',
                'id_periode_masuk' => now()->year.'1',
                'id_wilayah' => 'region-1',
            ],
            [
                'id_mahasiswa' => 'student-no-name',
                'id_prodi' => 'program-1',
                'id_status_mahasiswa' => 'M',
                'nim' => 'NPM-NO-NAME',
                'nama_mahasiswa' => null,
                'id_periode_masuk' => now()->year.'1',
                'id_wilayah' => 'region-1',
            ],
            [
                'id_mahasiswa' => 'student-blank-name',
                'id_prodi' => 'program-1',
                'id_status_mahasiswa' => 'A',
                'nim' => 'NPM-BLANK-NAME',
                'nama_mahasiswa' => '  ',
                'id_periode_masuk' => now()->year.'1',
                'id_wilayah' => 'region-1',
            ],
        ]);

        $landingData = $this->get(route('public.home'))->assertOk()->viewData('landingData');
        $dashboard = $this->getJson($landingData['dataUrl'])->assertOk();

        $this->assertSame(5, $dashboard->json('studentTotal'));
        $this->assertSame(5, $dashboard->json('metrics.1.value'));
        $this->assertSame(5, collect($dashboard->json('programRows'))->sum('students'));
        $this->assertSame(4, $dashboard->json('studentDistributionTotal'));
        $this->assertSame(4, collect($dashboard->json('studentDistribution'))->sum('total'));
        $this->assertSame(9, $dashboard->json('studentIntake.series.0.values.5'));
    }

    public function test_public_dashboard_shows_unique_dtps_per_program_across_all_semesters(): void
    {
        $connection = DB::connection('simak_main');
        $connection->table('s1m4k_kalender')->insert([
            ['id_kalender' => 'term-1', 'id_prodi' => 'program-1', 'tahun' => '20251', 'namatahun' => '2025 Ganjil'],
            ['id_kalender' => 'term-2', 'id_prodi' => 'program-1', 'tahun' => '20262', 'namatahun' => '2026 Genap'],
        ]);
        $connection->table('s1m4k_mastermk')->insert([
            ['id_matkul' => 'dtps-course-old', 'nama_mata_kuliah' => 'Mata Kuliah Lama', 'id_prodi' => 'program-1'],
            ['id_matkul' => 'dtps-course-latest', 'nama_mata_kuliah' => 'Mata Kuliah Terbaru', 'id_prodi' => 'program-1'],
        ]);
        $connection->table('s1m4k_masterdosen')->insert([
            ['id_dosen' => 'dtps-home-1', 'nidn' => 'NIDN-HOME1', 'nama_dosen' => 'Dosen Home 1', 'id_prodi' => 'program-1'],
            ['id_dosen' => 'dtps-home-2', 'nidn' => 'NIDN-HOME2', 'nama_dosen' => 'Dosen Home 2', 'id_prodi' => 'program-2'],
            ['id_dosen' => 'dtps-visitor', 'nidn' => 'NIDN-VISITOR', 'nama_dosen' => 'Dosen Tamu', 'id_prodi' => 'program-1'],
            ['id_dosen' => 'dtps-visitor-copy', 'nidn' => 'NIDN-VISITOR', 'nama_dosen' => 'Dosen Tamu Duplikat', 'id_prodi' => 'program-2'],
            ['id_dosen' => 'dtps-slot-14', 'nidn' => 'NIDN-SLOT14', 'nama_dosen' => null, 'id_prodi' => 'program-2'],
        ]);
        $connection->table('s1m4k_masterasdos')->insert([
            ['id_asdos' => 'dtps-assistant', 'id_prodi' => 'program-2', 'nama' => 'Asisten Terdaftar'],
        ]);
        $schedules = $connection->table('s1m4k_jadwal');
        $schedules->insert(
            [
                'id_jadwal' => 'dtps-public-1', 'id_prodi' => 'program-1', 'id_kalender' => 'term-1', 'id_matkul' => 'dtps-course-old',
                'dosen1' => 'dtps-home-1', 'dosen2' => 'dtps-visitor', 'dosen3' => 'dtps-visitor',
                'dosen4' => 'dtps-visitor-copy', 'dosen12' => 'missing-master-person',
                'dosen13' => 'dtps-assistant', 'dosen14' => 'dtps-slot-14',
            ],
        );
        $schedules->insert(
            [
                'id_jadwal' => 'dtps-public-2', 'id_prodi' => 'program-1', 'id_kalender' => 'term-2', 'id_matkul' => 'dtps-course-latest',
                'dosen1' => 'dtps-home-1', 'dosen2' => 'dtps-visitor-copy',
            ],
        );
        $schedules->insert(
            [
                'id_jadwal' => 'dtps-public-3', 'id_prodi' => 'program-2', 'id_kalender' => 'term-1',
                'dosen1' => 'dtps-home-2', 'dosen2' => 'dtps-home-1',
            ],
        );

        $rows = $this->getJson(route('public.dashboard-data', ['pageType' => 'home']))
            ->assertOk()
            ->json('programRows');
        $programs = collect($rows)->keyBy('id');

        $this->assertSame(3, $programs['program-1']['dtps']);
        $this->assertSame(1, $programs['program-1']['dtpsHomebase']);
        $this->assertSame(2, $programs['program-1']['dtpsNonHomebase']);
        $this->assertSame(2, $programs['program-2']['dtps']);
        $this->assertSame(1, $programs['program-2']['dtpsHomebase']);
        $this->assertSame(1, $programs['program-2']['dtpsNonHomebase']);

        $details = $this->getJson(route('public.dtps-details', [
            'program' => 'program-1',
            'category' => 'all',
        ]))->assertOk();
        $this->assertSame(3, $details->json('total'));
        $visitor = collect($details->json('lecturers'))->firstWhere('id', 'dtps-visitor-copy');
        $this->assertSame('Dosen Tamu Duplikat', $visitor['name']);
        $this->assertNull(collect($details->json('lecturers'))->firstWhere('id', 'dtps-assistant'));
        $this->assertSame('20262', $visitor['latest_academic_year']);
        $unnamedLecturer = collect($details->json('lecturers'))->firstWhere('id', 'dtps-slot-14');
        $this->assertNotNull($unnamedLecturer);
        $this->assertNull($unnamedLecturer['name']);
        $this->assertNull(collect($details->json('lecturers'))->firstWhere('id', 'missing-master-person'));
        $this->assertSame('2026 Genap', collect($visitor['courses'])->firstWhere('name', 'Mata Kuliah Terbaru')['academic_year']);
        $this->getJson(route('public.dtps-details', [
            'program' => 'inactive-program',
            'category' => 'all',
        ]))->assertNotFound();
    }

    public function test_public_dashboard_excludes_inactive_programs_from_program_scoped_data(): void
    {
        DB::connection('simak_main')->table('s1m4k_masterprodi')
            ->where('id_prodi', 'program-2')
            ->update(['status' => 'N']);

        $page = $this->get(route('public.home'))->assertOk();
        $dashboard = $this->getJson($page->viewData('landingData')['dataUrl'])->assertOk();

        $this->assertSame(1, $dashboard->json('metrics.0.value'));
        $this->assertSame(2, $dashboard->json('metrics.1.value'));
        $this->assertSame(['program-1'], collect($dashboard->json('programRows'))->pluck('id')->all());
        $this->get(route('public.programs.show', 'program-2'))->assertNotFound();
        $this->get(route('public.faculties.show', 'faculty-2'))->assertNotFound();
    }

    public function test_laravel_maintenance_mode_shows_custom_503_to_visitors_and_login_users(): void
    {
        Schema::connection('simak_main')->create('simak_site_settings', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('logo_path')->nullable();
            $table->string('preloader_logo_path')->nullable();
            $table->string('motto')->nullable();
            $table->string('ministry_name')->nullable();
        });
        Storage::fake('public');
        Storage::disk('public')->put('branding/maintenance-logo.png', 'logo');
        DB::connection('simak_main')->table('simak_site_settings')->insert([
            'id' => 1,
            'logo_path' => 'branding/maintenance-logo.png',
        ]);

        Artisan::call('down', ['--render' => 'errors.503']);

        try {
            $this->get('/')->assertStatus(503)
                ->assertSee('Pemeliharaan sistem')
                ->assertSee('Logo Universitas Khairun')
                ->assertSee(Storage::disk('public')->url('branding/maintenance-logo.png'))
                ->assertSee('maintenance-card')
                ->assertSee('Kami sedang melakukan pemeliharaan');
            $this->get('/login')->assertStatus(503)
                ->assertSee('Logo Universitas Khairun')
                ->assertSee(Storage::disk('public')->url('branding/maintenance-logo.png'))
                ->assertSee('Pemeliharaan sistem');
        } finally {
            Artisan::call('up');
        }
    }

    public function test_public_faculty_and_program_dashboards_are_scoped_and_count_unique_browsers(): void
    {
        $facultyResponse = $this->get(route('public.faculties.show', 'faculty-1'));
        $facultyResponse->assertOk()->assertSee('Fakultas Sains')->assertDontSee('Program Dua');
        $visitorCookie = $facultyResponse->getCookie('simak_public_visitor');
        $this->assertNotNull($visitorCookie);
        $this->withCookie('simak_public_visitor', (string) $visitorCookie->getValue())->withCredentials();
        $facultyData = $facultyResponse->viewData('landingData');
        $facultyJson = $this->getJson($facultyData['dataUrl'])->assertOk();
        $this->assertSame(2, $facultyJson->json('metrics.1.value'));
        $this->assertSame('Program Satu S1', $facultyJson->json('programRows.0.name'));
        $this->assertCount(1, $facultyJson->json('programRows'));
        $this->assertSame(2, $facultyJson->json('studentDistribution.0.total'));
        $this->assertSame(1, $facultyJson->json('studentIntake.series.0.values.5'));

        $facultyListData = $this->get(route('public.faculties.index'))->assertOk()->viewData('landingData');
        $facultyListJson = $this->getJson($facultyListData['dataUrl'])->assertOk();
        $this->assertCount(2, $facultyListJson->json('studentIntake.series'));
        $this->assertSame('Fakultas Sains', $facultyListJson->json('studentIntake.series.0.label'));
        $this->assertSame(1, $facultyListJson->json('studentIntake.series.0.values.5'));
        $this->assertSame(1, $facultyListJson->json('studentIntake.series.1.values.5'));
        $facultyMapBreakdown = collect($facultyListJson->json('studentDistribution.0.units'))
            ->firstWhere('label', 'Fakultas Sains');
        $this->assertSame(2, $facultyMapBreakdown['total']);

        $programResponse = $this->get(route('public.programs.show', 'program-2'));
        $programResponse->assertOk()->assertSee('Program Dua')->assertDontSee('Program Satu');
        $programData = $programResponse->viewData('landingData');
        $programJson = $this->getJson($programData['dataUrl'])->assertOk();
        $this->assertSame(1, $programJson->json('metrics.1.value'));
        $this->assertSame(1, $programJson->json('studentDistribution.0.total'));
        $this->assertSame(1, $programJson->json('studentIntake.series.0.values.5'));

        $programListData = $this->get(route('public.programs.index'))->assertOk()->viewData('landingData');
        $programListJson = $this->getJson($programListData['dataUrl'])->assertOk();
        $this->assertCount(2, $programListJson->json('studentIntake.series'));
        $this->assertSame('Program Dua S1', $programListJson->json('studentIntake.series.0.label'));
        $this->assertSame('Program Satu S1', $programListJson->json('studentIntake.series.1.label'));
        $this->assertSame(1, $programListJson->json('studentIntake.series.0.values.5'));
        $this->assertSame(1, $programListJson->json('studentIntake.series.1.values.5'));
        $programMapBreakdown = collect($programListJson->json('studentDistribution.0.units'));
        $this->assertSame(2, $programMapBreakdown->firstWhere('label', 'Program Satu S1')['total']);
        $this->assertSame(1, $programMapBreakdown->firstWhere('label', 'Program Dua S1')['total']);
        $this->assertSame(1, $programJson->json('visitors.total'));
        $this->assertSame(1, $programJson->json('visitors.pageToday'));

        $this->get(route('public.programs.show', 'program-2'));
        $sameVisitor = $this->getJson($programData['dataUrl'])->assertOk()->json('visitors');
        $this->assertSame(1, $sameVisitor['total']);
        $this->assertSame(1, $sameVisitor['today']);
        $this->assertSame(1, $sameVisitor['month']);
        $this->assertSame(1, $sameVisitor['year']);
        $this->assertSame(1, $sameVisitor['pageToday']);

        $otherVisitor = $this->withCookie('simak_public_visitor', '8a640da5-7218-48b8-8bc5-a3d602493543')
            ->withCredentials()
            ->getJson($programData['dataUrl'])->assertOk()->json('visitors');
        $this->assertSame(2, $otherVisitor['total']);
        $this->assertSame(2, $otherVisitor['today']);
        $this->assertSame(2, $otherVisitor['month']);
        $this->assertSame(2, $otherVisitor['year']);
        $this->assertSame(2, $otherVisitor['pageToday']);

        $this->get(route('public.faculties.show', 'missing-faculty'))->assertNotFound();
        $this->get(route('public.programs.show', 'missing-program'))->assertNotFound();
    }

    public function test_public_landing_marks_an_authenticated_session_for_dashboard_navigation(): void
    {
        $user = new GenericUser(['id' => 'user-1', 'role' => 'admin', 'name' => 'Admin']);
        $this->actingAs($user);
        $landing = $this->get(route('public.home'))->assertOk()->viewData('landingData');
        $this->assertTrue($landing['authenticated']);
        $this->assertSame(route('dashboard'), $landing['dashboardUrl']);
    }

    public function test_public_dashboard_data_is_rate_limited(): void
    {
        $landing = $this->get(route('public.home'))->assertOk()->viewData('landingData');

        foreach (range(1, 30) as $attempt) {
            $this->getJson($landing['dataUrl'])->assertOk();
        }

        $this->getJson($landing['dataUrl'])->assertTooManyRequests();
    }

    public function test_public_visitor_identity_cache_expires_after_one_year(): void
    {
        $landing = $this->get(route('public.home'))->assertOk();
        $visitorCookie = $landing->getCookie('simak_public_visitor');
        $this->assertNotNull($visitorCookie);
        $this->withCookie('simak_public_visitor', (string) $visitorCookie->getValue())->withCredentials();
        $dataUrl = $landing->viewData('landingData')['dataUrl'];

        $this->getJson($dataUrl)->assertOk()->assertJsonPath('visitors.total', 1);
        $this->travel(366)->days();

        $this->getJson($dataUrl)->assertOk()->assertJsonPath('visitors.total', 2);
    }

    public function test_professional_programs_are_excluded_from_cohort_trends(): void
    {
        $connection = DB::connection('simak_main');
        $connection->table('s1m4k_masterprodi')->insert([
            'id_prodi' => 'program-profesi',
            'id_fakultas' => 'faculty-1',
            'nama_program_studi' => 'Pendidikan Profesi Dokter',
            'nama_jenjang_pendidikan' => 'Profesi',
            'status' => 'A',
        ]);
        $connection->table('s1m4k_mastermhs')->insert([
            'id_mahasiswa' => 'student-profesi',
            'id_prodi' => 'program-profesi',
            'id_status_mahasiswa' => 'A',
            'nim' => 'NPM-PROFESI',
            'id_periode_masuk' => now()->year.'1',
            'id_wilayah' => 'region-1',
        ]);

        $homeData = $this->get(route('public.home'))->assertOk()->viewData('landingData');
        $homeJson = $this->getJson($homeData['dataUrl'])->assertOk();
        $this->assertSame(2, $homeJson->json('studentIntake.series.0.values.5'));

        $programData = $this->get(route('public.programs.show', 'program-profesi'))
            ->assertOk()->viewData('landingData');
        $this->assertFalse($this->getJson($programData['dataUrl'])->assertOk()->json('studentIntake.available'));
    }

    public function test_expired_session_returns_to_public_home_with_login_modal(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $landing = $this->get(route('login'))->assertOk()->viewData('landingData');
        $this->assertTrue($landing['openLogin']);
    }

    public function test_password_reset_opens_from_the_public_homepage_modal(): void
    {
        $this->get(route('password.request'))->assertRedirect(route('public.home'));

        $response = $this->followingRedirects()->post(route('password.email'), ['login' => '']);
        $response->assertOk();
        $data = $response->viewData('landingData');
        $this->assertSame(route('password.email'), $data['resetPasswordUrl']);
        $this->assertTrue($data['openResetPassword']);
        $this->assertNotEmpty($data['resetErrors']['login']);
        $this->assertSame([], $data['loginErrors']);
    }

    public function test_public_informational_pages_are_accessible_and_render_branding(): void
    {
        foreach (['about', 'structure', 'admissions', 'academic', 'accreditation', 'payment-information', 'contact', 'privacy', 'terms', 'accessibility'] as $slug) {
            $response = $this->get('/' . $slug)->assertOk();
            $response->assertSee('ASEAN University International');
            $response->assertSee(route('login'));
        }

        $this->get('/payment')->assertRedirect('/payment-information');
    }

    private function createSchema(): void
    {
        Schema::connection('simak_main')->create('s1m4k_masterpt', function (Blueprint $table): void {
            $table->string('id_perguruan_tinggi')->primary();
            $table->string('nama_perguruan_tinggi')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterfakultas', function (Blueprint $table): void {
            $table->string('id_fakultas')->primary();
            $table->string('nama_fakultas')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterprodi', function (Blueprint $table): void {
            $table->string('id_prodi')->primary();
            $table->string('id_fakultas')->nullable();
            $table->string('nama_program_studi')->nullable();
            $table->string('nama_jenjang_pendidikan')->nullable();
            $table->string('status')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_mastermhs', function (Blueprint $table): void {
            $table->string('id_mahasiswa')->primary();
            $table->string('id_prodi')->nullable();
            $table->string('id_status_mahasiswa');
            $table->string('nim')->nullable();
            $table->string('nama_mahasiswa')->nullable();
            $table->string('id_periode_masuk')->nullable();
            $table->string('id_wilayah')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_kalender', function (Blueprint $table): void {
            $table->string('id_kalender')->primary();
            $table->string('id_prodi')->nullable();
            $table->string('tahun')->nullable();
            $table->string('namatahun')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_kodereferensi', function (Blueprint $table): void {
            $table->string('kode_referensi')->primary();
            $table->string('keterangan')->nullable();
            $table->string('nama_provinsi')->nullable();
            $table->decimal('lat_provinsi', 10, 6)->nullable();
            $table->decimal('lng_provinsi', 10, 6)->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterdosen', function (Blueprint $table): void {
            $table->string('id_dosen')->primary();
            $table->string('id_prodi')->nullable();
            $table->string('nidn')->nullable();
            $table->string('nama_dosen')->nullable();
            $table->string('gelar_depan')->nullable();
            $table->string('gelar_belakang')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterasdos', function (Blueprint $table): void {
            $table->string('id_asdos')->primary();
            $table->string('id_prodi')->nullable();
            $table->string('nama')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_mastermk', function (Blueprint $table): void {
            $table->string('id_matkul')->primary();
            $table->string('nama_mata_kuliah')->nullable();
            $table->string('id_prodi')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_jadwal', function (Blueprint $table): void {
            $table->string('id_jadwal')->primary();
            $table->string('id_prodi')->nullable();
            $table->string('id_kalender')->nullable();
            $table->string('id_matkul')->nullable();
            for ($slot = 1; $slot <= 14; $slot++) {
                $table->string('dosen'.$slot)->nullable();
            }
        });
        Schema::connection('simak_main')->create('s1m4k_krs', function (Blueprint $table): void {
            $table->string('id_krs')->primary();
            $table->string('id_prodi')->nullable();
        });
        Schema::connection('simak_main')->create('eskripsi_judul', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('id_prodi');
            $table->integer('status');
        });
        Schema::connection('simak_main')->create('eskripsi_skripsi', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('id_prodi');
            $table->integer('status');
        });
        Schema::connection('simak_main')->create('eskripsi_ujian', function (Blueprint $table): void {
            $table->string('id_ujian')->primary();
            $table->string('id_skripsi');
        });
        Schema::connection('simak_main')->create('ekur_kurikulum', function (Blueprint $table): void {
            $table->string('id_kur')->primary();
            $table->string('id_prodi');
            $table->string('status_kur');
        });
    }

    private function seedData(): void
    {
        $connection = DB::connection('simak_main');
        $connection->table('s1m4k_masterpt')->insert([
            'id_perguruan_tinggi' => 'university',
            'nama_perguruan_tinggi' => 'Universitas Khairun',
        ]);
        $connection->table('s1m4k_masterfakultas')->insert([
            ['id_fakultas' => 'faculty-1', 'nama_fakultas' => 'Fakultas Sains'],
            ['id_fakultas' => 'faculty-2', 'nama_fakultas' => 'Fakultas Teknik'],
        ]);
        $connection->table('s1m4k_masterprodi')->insert([
            ['id_prodi' => 'program-1', 'id_fakultas' => 'faculty-1', 'nama_program_studi' => 'Program Satu', 'nama_jenjang_pendidikan' => 'S1', 'status' => 'A'],
            ['id_prodi' => 'program-2', 'id_fakultas' => 'faculty-2', 'nama_program_studi' => 'Program Dua', 'nama_jenjang_pendidikan' => 'S1', 'status' => 'A'],
        ]);
        $connection->table('s1m4k_mastermhs')->insert([
            ['id_mahasiswa' => 'student-1', 'id_prodi' => 'program-1', 'id_status_mahasiswa' => 'A', 'nim' => 'NPM-1', 'nama_mahasiswa' => 'Mahasiswa 1', 'id_periode_masuk' => now()->year.'1', 'id_wilayah' => 'region-1'],
            ['id_mahasiswa' => 'student-2', 'id_prodi' => 'program-1', 'id_status_mahasiswa' => 'C', 'nim' => 'NPM-2', 'nama_mahasiswa' => 'Mahasiswa 2', 'id_periode_masuk' => (now()->year - 1).'1', 'id_wilayah' => 'region-1'],
            ['id_mahasiswa' => 'student-3', 'id_prodi' => 'program-2', 'id_status_mahasiswa' => 'M', 'nim' => 'NPM-3', 'nama_mahasiswa' => 'Mahasiswa 3', 'id_periode_masuk' => now()->year.'2', 'id_wilayah' => 'region-1'],
            ['id_mahasiswa' => 'student-4', 'id_prodi' => 'program-1', 'id_status_mahasiswa' => 'G', 'nim' => 'NPM-4', 'nama_mahasiswa' => 'Mahasiswa 4', 'id_periode_masuk' => (now()->year - 6).'1', 'id_wilayah' => 'region-1'],
        ]);
        $connection->table('s1m4k_kodereferensi')->insert([
            'kode_referensi' => 'region-1',
            'keterangan' => 'GetWilayah',
            'nama_provinsi' => 'Maluku Utara',
            'lat_provinsi' => 1.570992,
            'lng_provinsi' => 127.808769,
        ]);
        $connection->table('s1m4k_masterdosen')->insert([
            ['id_dosen' => 'lecturer-1', 'id_prodi' => 'program-1'],
            ['id_dosen' => 'lecturer-2', 'id_prodi' => 'program-2'],
        ]);
        $connection->table('s1m4k_mastermk')->insert([
            ['id_matkul' => 'course-1', 'id_prodi' => 'program-1'],
        ]);
        $connection->table('s1m4k_jadwal')->insert([
            ['id_jadwal' => 'class-1', 'id_prodi' => 'program-1'],
        ]);
        $connection->table('s1m4k_krs')->insert([
            ['id_krs' => 'krs-1', 'id_prodi' => 'program-1'],
        ]);
        $connection->table('eskripsi_judul')->insert([
            ['id' => 'proposal-1', 'id_prodi' => 'program-1', 'status' => 0],
        ]);
        $connection->table('eskripsi_skripsi')->insert([
            ['id' => 'thesis-1', 'id_prodi' => 'program-1', 'status' => 1],
        ]);
        $connection->table('eskripsi_ujian')->insert([
            ['id_ujian' => 'exam-1', 'id_skripsi' => 'thesis-1'],
        ]);
        $connection->table('ekur_kurikulum')->insert([
            ['id_kur' => 'curriculum-1', 'id_prodi' => 'program-1', 'status_kur' => 'Draf'],
        ]);
    }
}
