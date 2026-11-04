<?php

namespace Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class AcademicDashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.simak_main' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
        ], 'cache.default' => 'array']);
        DB::purge('simak_main');
        Cache::store('array')->flush();

        Schema::connection('simak_main')->create('s1m4k_masterprodi', function (Blueprint $table): void {
            $table->string('id_prodi')->primary();
            $table->string('id_fakultas')->nullable();
            $table->string('nama_program_studi')->nullable();
            $table->string('nama_jenjang_pendidikan')->nullable();
            $table->string('status')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterfakultas', function (Blueprint $table): void {
            $table->string('id_fakultas')->primary();
            $table->string('nama_fakultas')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_kalender', function (Blueprint $table): void {
            $table->string('id_kalender')->primary();
            $table->string('id_prodi');
            $table->string('tahun');
            $table->string('namatahun')->nullable();
            $table->string('status')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_mastermhs', function (Blueprint $table): void {
            $table->string('id_mahasiswa')->primary();
            $table->string('id_prodi');
            $table->string('id_status_mahasiswa');
            $table->string('nim')->nullable();
            $table->string('nama_mahasiswa')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_registrasiulang', function (Blueprint $table): void {
            $table->string('id_registrasi')->primary();
            $table->string('id_mahasiswa');
            $table->string('id_kalender');
        });
        Schema::connection('simak_main')->create('s1m4k_krs', function (Blueprint $table): void {
            $table->string('id_krs')->primary();
            $table->string('id_mahasiswa');
            $table->string('id_kalender');
            $table->string('input_nilai')->nullable();
            $table->string('id_matkul')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_pengesahankrs', function (Blueprint $table): void {
            $table->string('id_pengesahan')->primary();
            $table->string('id_mahasiswa');
            $table->string('id_kalender');
            $table->string('nama_kpro')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_akm', function (Blueprint $table): void {
            $table->string('id_ipmhs')->primary();
            $table->string('id_prodi');
            $table->string('id_kalender');
            $table->string('ips')->nullable();
            $table->string('ipk')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_edom', function (Blueprint $table): void {
            $table->string('id_edom')->primary();
            $table->string('id_kalender');
            $table->string('id_jadwal')->nullable();
            $table->string('id_krs')->nullable();
            $table->integer('nilai');
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
            $table->string('kode_mata_kuliah')->nullable();
            $table->string('nama_mata_kuliah')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_jadwal', function (Blueprint $table): void {
            $table->string('id_jadwal')->primary();
            $table->string('id_prodi');
            $table->string('id_kalender');
            $table->string('id_matkul')->nullable();
            $table->string('nama_kelas')->nullable();
            for ($slot = 1; $slot <= 14; $slot++) {
                $table->string('dosen'.$slot)->nullable();
            }
        });

        DB::connection('simak_main')->table('s1m4k_masterfakultas')->insert([
            'id_fakultas' => 'faculty-1', 'nama_fakultas' => 'Fakultas Satu',
        ]);
        DB::connection('simak_main')->table('s1m4k_masterprodi')->insert([
            'id_prodi' => 'program-1', 'id_fakultas' => 'faculty-1',
            'nama_program_studi' => 'Program Satu', 'nama_jenjang_pendidikan' => 'S1', 'status' => 'A',
        ]);
        DB::connection('simak_main')->table('s1m4k_kalender')->insert([
            'id_kalender' => 'calendar-1', 'id_prodi' => 'program-1',
            'tahun' => '20261', 'namatahun' => '2026 Ganjil', 'status' => 'Y',
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            ['id_mahasiswa' => 'student-1', 'id_prodi' => 'program-1', 'id_status_mahasiswa' => 'A', 'nim' => 'NIM-1', 'nama_mahasiswa' => 'Mahasiswa 1'],
            ['id_mahasiswa' => 'student-2', 'id_prodi' => 'program-1', 'id_status_mahasiswa' => 'C', 'nim' => 'NIM-2', 'nama_mahasiswa' => 'Mahasiswa 2'],
            ['id_mahasiswa' => 'student-3', 'id_prodi' => 'program-1', 'id_status_mahasiswa' => 'N', 'nim' => 'NIM-3', 'nama_mahasiswa' => 'Mahasiswa 3'],
            ['id_mahasiswa' => 'student-4', 'id_prodi' => 'program-1', 'id_status_mahasiswa' => 'M', 'nim' => 'NIM-4', 'nama_mahasiswa' => 'Mahasiswa 4'],
            ['id_mahasiswa' => 'student-5', 'id_prodi' => 'program-1', 'id_status_mahasiswa' => 'G', 'nim' => 'NIM-5', 'nama_mahasiswa' => 'Mahasiswa 5'],
            ['id_mahasiswa' => 'student-no-nim', 'id_prodi' => 'program-1', 'id_status_mahasiswa' => 'A', 'nim' => null, 'nama_mahasiswa' => 'Tanpa NIM'],
            ['id_mahasiswa' => 'student-blank-nim', 'id_prodi' => 'program-1', 'id_status_mahasiswa' => 'N', 'nim' => '  ', 'nama_mahasiswa' => 'NIM Kosong'],
            ['id_mahasiswa' => 'student-no-name', 'id_prodi' => 'program-1', 'id_status_mahasiswa' => 'C', 'nim' => 'NIM-6', 'nama_mahasiswa' => null],
            ['id_mahasiswa' => 'student-blank-name', 'id_prodi' => 'program-1', 'id_status_mahasiswa' => 'M', 'nim' => 'NIM-7', 'nama_mahasiswa' => '  '],
        ]);
        DB::connection('simak_main')->table('s1m4k_registrasiulang')->insert([
            'id_registrasi' => 'registration-1', 'id_mahasiswa' => 'student-1', 'id_kalender' => 'calendar-1',
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermk')->insert([
            ['id_matkul' => 'course-1', 'kode_mata_kuliah' => 'MK001', 'nama_mata_kuliah' => 'Mata Kuliah Satu'],
            ['id_matkul' => 'course-2', 'kode_mata_kuliah' => 'MK002', 'nama_mata_kuliah' => 'Mata Kuliah Dua'],
        ]);
        DB::connection('simak_main')->table('s1m4k_krs')->insert([
            'id_krs' => 'krs-1', 'id_mahasiswa' => 'student-1', 'id_kalender' => 'calendar-1', 'input_nilai' => 'Y', 'id_matkul' => 'course-1',
        ]);
    }

    public function test_dashboard_payload_is_cached_for_a_scoped_calendar(): void
    {
        $this->actingAs(new GenericUser([
            'id' => 'operator-1', 'role' => 'userprodi', 'id_prodi' => 'program-1',
        ]));
        $url = route('academic-dashboard.data', ['calendar' => '20261']);

        $first = $this->getJson($url)->assertOk();
        $this->assertSame('Mahasiswa', $first->json('cards.0.label'));
        $this->assertSame('Status A, N, C, dan M', $first->json('cards.0.detail'));
        $this->assertSame(4, $first->json('cards.0.value'));
        $this->assertSame(4, $first->json('programSummary.0.active_students'));
        $this->assertSame(1, $first->json('cards.1.value'));

        DB::connection('simak_main')->table('s1m4k_krs')->delete();

        $cached = $this->getJson($url)->assertOk();
        $this->assertSame($first->json('programSummary'), $cached->json('programSummary'));
        $this->assertSame(1, $cached->json('cards.2.value'));
    }

    public function test_academic_process_counts_download_complete_excel_details_with_role_scope(): void
    {
        $connection = DB::connection('simak_main');
        $connection->table('s1m4k_pengesahankrs')->insert([
            ['id_pengesahan' => 'approval-1', 'id_mahasiswa' => 'student-1', 'id_kalender' => 'calendar-1', 'nama_kpro' => 'Ketua Prodi'],
            ['id_pengesahan' => 'approval-2', 'id_mahasiswa' => 'student-1', 'id_kalender' => 'calendar-1', 'nama_kpro' => 'Ketua Prodi'],
        ]);
        $connection->table('s1m4k_krs')->insert([
            ['id_krs' => 'krs-2', 'id_mahasiswa' => 'student-1', 'id_kalender' => 'calendar-1', 'input_nilai' => 'Y', 'id_matkul' => 'course-1'],
            ['id_krs' => 'krs-3', 'id_mahasiswa' => 'student-1', 'id_kalender' => 'calendar-1', 'input_nilai' => 'Y', 'id_matkul' => 'course-2'],
        ]);
        $connection->table('s1m4k_masterprodi')->insert([
            'id_prodi' => 'program-2', 'id_fakultas' => 'faculty-2',
            'nama_program_studi' => 'Program Dua', 'nama_jenjang_pendidikan' => 'S1', 'status' => 'A',
        ]);
        $connection->table('s1m4k_kalender')->insert([
            'id_kalender' => 'calendar-2', 'id_prodi' => 'program-2',
            'tahun' => '20261', 'namatahun' => '2026 Ganjil', 'status' => 'Y',
        ]);
        $this->actingAs(new GenericUser([
            'id' => 'operator-1', 'role' => 'userprodi', 'id_prodi' => 'program-1',
        ]));

        foreach ([
            'active' => 4,
            'registered' => 1,
            'krs' => 1,
            'approved' => 1,
            'grades' => 2,
        ] as $metric => $expectedRows) {
            $response = $this->get(route('academic-dashboard.export.excel', [
                'program' => 'program-1',
                'calendar' => '20261',
                'metric' => $metric,
            ]))->assertOk()
                ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                ->assertHeader('Content-Disposition', 'attachment; filename="dashboard-akademik-'.$metric.'-program-1-20261.xlsx"');

            $spreadsheet = $this->spreadsheetFromResponse($response->getContent());
            $sheet = $spreadsheet->getActiveSheet();
            $this->assertSame('NPM', $sheet->getCell('A1')->getValue());
            $this->assertSame($expectedRows + 1, $sheet->getHighestRow());
            $this->assertSame('NIM-1', $sheet->getCell('A2')->getValue());
            $this->assertSame('Mahasiswa 1', $sheet->getCell('B2')->getValue());
            $this->assertSame('Fakultas Satu', $sheet->getCell('F2')->getValue());
            if ($metric === 'grades') {
                $this->assertSame('Kode Mata Kuliah', $sheet->getCell('H1')->getValue());
                $this->assertSame('Nama Mata Kuliah', $sheet->getCell('I1')->getValue());
                $this->assertSame('MK001', $sheet->getCell('H2')->getValue());
                $this->assertSame('Mata Kuliah Satu', $sheet->getCell('I2')->getValue());
                $this->assertSame('Mata Kuliah Dua', $sheet->getCell('I3')->getValue());
            }
            $spreadsheet->disconnectWorksheets();
        }

        $this->get(route('academic-dashboard.export.excel', [
            'program' => 'program-2',
            'calendar' => '20261',
            'metric' => 'active',
        ]))->assertNotFound();
        $this->get(route('academic-dashboard.export.excel', [
            'program' => 'program-1',
            'calendar' => '20261',
            'metric' => 'unknown',
        ]))->assertSessionHasErrors('metric');
    }

    public function test_dashboard_file_cache_preserves_program_summary_as_an_array(): void
    {
        $cachePath = sys_get_temp_dir().'/simak-academic-dashboard-'.bin2hex(random_bytes(8));
        config([
            'cache.default' => 'academic-dashboard-test',
            'cache.stores.academic-dashboard-test' => [
                'driver' => 'file',
                'path' => $cachePath,
                'lock_path' => $cachePath,
            ],
        ]);

        try {
            $this->actingAs(new GenericUser([
                'id' => 'operator-1', 'role' => 'userprodi', 'id_prodi' => 'program-1',
            ]));
            $url = route('academic-dashboard.data', ['calendar' => '20261']);

            $this->getJson($url)
                ->assertOk()
                ->assertJsonStructure(['programSummary' => [['id', 'active_students']]]);
            $cached = $this->getJson($url)->assertOk();
            $this->assertSame(4, $cached->json('programSummary.0.active_students'));

            $insightsUrl = route('academic-dashboard.insights', ['calendar' => '20261']);
            $this->getJson($insightsUrl)
                ->assertOk()
                ->assertJsonStructure(['trendRows', 'topEdomLecturers']);
            $cachedInsights = $this->getJson($insightsUrl)->assertOk();
            $this->assertIsArray($cachedInsights->json('trendRows'));
            $this->assertIsArray($cachedInsights->json('topEdomLecturers'));
        } finally {
            File::deleteDirectory($cachePath);
        }
    }

    public function test_inactive_program_and_its_calendar_are_excluded_from_academic_dashboard(): void
    {
        $connection = DB::connection('simak_main');
        $connection->table('s1m4k_masterprodi')->insert([
            'id_prodi' => 'program-inactive', 'id_fakultas' => 'faculty-1',
            'nama_program_studi' => 'Program Nonaktif', 'nama_jenjang_pendidikan' => 'S1', 'status' => 'N',
        ]);
        $connection->table('s1m4k_kalender')->insert([
            'id_kalender' => 'calendar-inactive', 'id_prodi' => 'program-inactive',
            'tahun' => '20271', 'namatahun' => '2027 Ganjil', 'status' => 'Y',
        ]);
        $connection->table('s1m4k_mastermhs')->insert([
            'id_mahasiswa' => 'student-inactive-prodi', 'id_prodi' => 'program-inactive',
            'id_status_mahasiswa' => 'A', 'nim' => 'NIM-INACTIVE', 'nama_mahasiswa' => 'Mahasiswa Prodi Nonaktif',
        ]);

        $this->actingAs(new GenericUser(['id' => 'operator-admin', 'role' => 'admin']));
        $this->get(route('academic-dashboard.index'))
            ->assertOk()
            ->assertDontSee('Program Nonaktif')
            ->assertDontSee('2027 Ganjil');

        $response = $this->getJson(route('academic-dashboard.data', ['calendar' => '20261']))->assertOk();
        $this->assertCount(1, $response->json('programSummary'));
        $this->assertSame(4, $response->json('cards.0.value'));
    }

    public function test_academic_dashboard_scopes_dtps_to_all_calendars_in_selected_year(): void
    {
        $connection = DB::connection('simak_main');
        $connection->table('s1m4k_kalender')->insert([
            ['id_kalender' => 'calendar-20262', 'id_prodi' => 'program-1', 'tahun' => '20262', 'namatahun' => '2026 Genap', 'status' => 'N'],
        ]);
        $connection->table('s1m4k_mastermk')->insert([
            ['id_matkul' => 'course-20261-a', 'nama_mata_kuliah' => 'Mata Kuliah A'],
            ['id_matkul' => 'course-20261-b', 'nama_mata_kuliah' => 'Mata Kuliah B'],
        ]);
        $connection->table('s1m4k_masterdosen')->insert([
            ['id_dosen' => 'dtps-home', 'id_prodi' => 'program-1', 'nidn' => 'NIDN-HOME', 'nama_dosen' => 'Dosen Homebase', 'gelar_depan' => 'Dr.', 'gelar_belakang' => 'M.Kom.'],
            ['id_dosen' => 'dtps-visitor', 'id_prodi' => 'program-1', 'nidn' => 'NIDN-VISITOR', 'nama_dosen' => 'Dosen Tamu', 'gelar_depan' => null, 'gelar_belakang' => null],
            ['id_dosen' => 'dtps-visitor-copy', 'id_prodi' => 'program-other', 'nidn' => 'NIDN-VISITOR', 'nama_dosen' => 'Dosen Tamu Duplikat', 'gelar_depan' => null, 'gelar_belakang' => null],
            ['id_dosen' => 'dtps-slot-14', 'id_prodi' => 'program-other', 'nidn' => 'NIDN-SLOT14', 'nama_dosen' => null, 'gelar_depan' => null, 'gelar_belakang' => null],
        ]);
        $connection->table('s1m4k_masterasdos')->insert([
            ['id_asdos' => 'dtps-assistant', 'id_prodi' => 'program-other', 'nama' => 'Asisten Terdaftar'],
        ]);
        $schedules = $connection->table('s1m4k_jadwal');
        $schedules->insert(
            [
                'id_jadwal' => 'class-20261-1', 'id_prodi' => 'program-1', 'id_kalender' => 'calendar-1', 'id_matkul' => 'course-20261-a',
                'dosen1' => 'dtps-home', 'dosen2' => 'dtps-visitor', 'dosen3' => 'dtps-visitor',
                'dosen12' => 'missing-master-person', 'dosen13' => 'dtps-assistant',
                'dosen4' => 'dtps-visitor-copy', 'dosen14' => 'dtps-slot-14',
            ],
        );
        $schedules->insert(
            [
                'id_jadwal' => 'class-20261-2', 'id_prodi' => 'program-1', 'id_kalender' => 'calendar-1', 'id_matkul' => 'course-20261-b',
                'dosen3' => 'dtps-visitor-copy',
            ],
        );
        $schedules->insert(
            [
                'id_jadwal' => 'class-20262', 'id_prodi' => 'program-1', 'id_kalender' => 'calendar-20262',
                'dosen1' => 'dtps-home',
            ],
        );

        $this->actingAs(new GenericUser([
            'id' => 'operator-1', 'role' => 'userprodi', 'id_prodi' => 'program-1',
        ]));

        $oddTerm = $this->getJson(route('academic-dashboard.data', ['calendar' => '20261']))->assertOk();
        $this->assertSame(3, $oddTerm->json('programSummary.0.dtps'));
        $this->assertSame(1, $oddTerm->json('programSummary.0.dtps_homebase'));
        $this->assertSame(2, $oddTerm->json('programSummary.0.dtps_non_homebase'));

        $evenTerm = $this->getJson(route('academic-dashboard.data', ['calendar' => '20262']))->assertOk();
        $this->assertSame(1, $evenTerm->json('programSummary.0.dtps'));
        $this->assertSame(1, $evenTerm->json('programSummary.0.dtps_homebase'));
        $this->assertSame(0, $evenTerm->json('programSummary.0.dtps_non_homebase'));

        $details = $this->getJson(route('academic-dashboard.dtps-details', [
            'program' => 'program-1',
            'calendar' => '20261',
            'category' => 'all',
        ]))->assertOk();
        $this->assertSame(3, $details->json('total'));
        $visitor = collect($details->json('lecturers'))->firstWhere('id', 'dtps-visitor-copy');
        $this->assertSame('Dr. Dosen Homebase, M.Kom.', collect($details->json('lecturers'))->firstWhere('id', 'dtps-home')['name']);
        $this->assertSame('Dosen Tamu Duplikat', $visitor['name']);
        $this->assertNull(collect($details->json('lecturers'))->firstWhere('id', 'dtps-assistant'));
        $unnamedLecturer = collect($details->json('lecturers'))->firstWhere('id', 'dtps-slot-14');
        $this->assertNotNull($unnamedLecturer);
        $this->assertNull($unnamedLecturer['name']);
        $this->assertNull(collect($details->json('lecturers'))->firstWhere('id', 'missing-master-person'));
        $this->assertCount(2, $visitor['courses']);
        $this->getJson(route('academic-dashboard.dtps-details', [
            'program' => 'program-1',
            'calendar' => '20262',
            'category' => 'all',
        ]))->assertJsonPath('total', 1);
        $this->getJson(route('academic-dashboard.dtps-details', [
            'program' => 'program-other',
            'calendar' => '20261',
            'category' => 'all',
        ]))->assertNotFound();
    }

    private function spreadsheetFromResponse(string $contents): Spreadsheet
    {
        $path = tempnam(sys_get_temp_dir(), 'simak-dashboard-export-');
        file_put_contents($path, $contents);

        try {
            return IOFactory::load($path);
        } finally {
            unlink($path);
        }
    }
}
