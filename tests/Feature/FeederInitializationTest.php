<?php

namespace Tests\Feature;

use App\Services\FeederInitializationService;
use App\Services\LegacyFeederService;
use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class FeederInitializationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'cache.default' => 'array',
            'database.connections.simak_main' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
        ]);
        Cache::flush();
        DB::purge('simak_main');

        Schema::connection('simak_main')->create('s1m4k_masterpt', function (Blueprint $table): void {
            $table->string('id_perguruan_tinggi')->primary();
            foreach ([
                'kode_perguruan_tinggi', 'nama_perguruan_tinggi', 'telepon', 'faximile',
                'email', 'website', 'jalan', 'dusun', 'rt_rw', 'kelurahan', 'kode_pos',
                'id_wilayah', 'nama_wilayah', 'lintang_bujur', 'bank', 'unit_cabang',
                'nomor_rekening', 'mbs', 'luas_tanah_milik', 'luas_tanah_bukan_milik',
                'sk_pendirian', 'tanggal_sk_pendirian', 'id_status_milik', 'nama_status_milik',
                'status_perguruan_tinggi', 'sk_izin_operasional', 'tanggal_izin_operasional',
                'alamat_feeder', 'user_feeder', 'password_feeder',
            ] as $column) {
                $table->string($column)->nullable();
            }
        });
        Schema::connection('simak_main')->create('s1m4k_kodereferensi', function (Blueprint $table): void {
            $table->string('id_referensi')->primary();
            $table->string('kode_referensi')->nullable();
            $table->text('ket_referensi')->nullable();
            $table->string('ket_referensi2')->nullable();
            $table->string('keterangan')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('nama_provinsi')->nullable();
            $table->decimal('lat_provinsi', 10, 7)->nullable();
            $table->decimal('lng_provinsi', 10, 7)->nullable();
            $table->string('untuk_kampus_merdeka')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterfakultas', function (Blueprint $table): void {
            $table->string('id_fakultas')->primary();
            $table->string('nama_fakultas')->nullable();
        });
        Schema::connection('simak_main')->create('log_aktivitas', function (Blueprint $table): void {
            $table->string('id_log', 36)->primary();
            $table->string('id_user', 100)->nullable();
            $table->string('level_user', 50)->nullable();
            $table->string('nama_user', 255);
            $table->string('modul', 100);
            $table->longText('aktifitas');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    private function createStudentGradeSchema(): void
    {
        Schema::connection('simak_main')->create('s1m4k_mastermhs', function (Blueprint $table): void {
            $table->string('id_mahasiswa')->primary();
            $table->string('nim')->nullable();
            $table->string('id_status_mahasiswa')->nullable();
            $table->string('id_perguruan_tinggi')->nullable();
            $table->string('id_fakultas')->nullable();
            $table->string('id_prodi')->nullable();
            $table->string('id_registrasi_mahasiswa')->nullable();
            $table->string('id_jenis_daftar')->nullable();
            $table->string('id_jalur_daftar')->nullable();
            $table->string('tanggal_daftar')->nullable();
            $table->string('id_pembiayaan')->nullable();
            $table->string('biaya_masuk_kuliah')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_kalender', function (Blueprint $table): void {
            $table->string('id_kalender')->primary();
            $table->string('tahun')->nullable();
            $table->string('id_prodi')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_jadwal', function (Blueprint $table): void {
            $table->string('id_jadwal')->primary();
            $table->string('id_perguruan_tinggi')->nullable();
            $table->string('id_fakultas')->nullable();
            $table->string('id_kelas_kuliah')->nullable();
            $table->string('id_kalender')->nullable();
            $table->string('id_prodi')->nullable();
            $table->string('id_matkul')->nullable();
            $table->string('nama_kelas')->nullable();
            $table->integer('jml_mahasiswa')->default(0);
        });
        Schema::connection('simak_main')->create('s1m4k_krs', function (Blueprint $table): void {
            $table->string('id_krs')->primary();
            foreach ([
                'id_perguruan_tinggi', 'id_fakultas', 'id_prodi', 'id_kalender', 'tahun',
                'id_mahasiswa', 'id_jadwal', 'id_matkul', 'status_krs', 'krs_mbkm',
                'isi_edom', 'nilai_angka', 'nilai_huruf', 'nilai_bobot', 'input_nilai',
            ] as $column) {
                $table->string($column)->nullable();
            }
        });
        Schema::connection('simak_main')->create('s1m4k_pengesahankrs', function (Blueprint $table): void {
            $table->string('id_pengesahan')->primary();
            foreach ([
                'id_mahasiswa', 'id_kalender', 'yang_mengesahkan',
                'tgl_pengesahan_kpro', 'nama_kpro', 'tgl_pengesahan',
            ] as $column) {
                $table->string($column)->nullable();
            }
        });
    }

    public function test_backfill_grade_schedules_previews_then_creates_only_unambiguous_missing_rows(): void
    {
        $this->createStudentGradeSchema();
        $connection = DB::connection('simak_main');
        $connection->table('s1m4k_krs')->insert([
            [
                'id_krs' => 'krs-1', 'id_mahasiswa' => 'student-1', 'id_jadwal' => 'class-1',
                'id_perguruan_tinggi' => 'pt-1', 'id_fakultas' => 'faculty-1',
                'id_prodi' => 'program-1', 'id_kalender' => 'calendar-1', 'id_matkul' => 'course-1',
            ],
            [
                'id_krs' => 'krs-2', 'id_mahasiswa' => 'student-2', 'id_jadwal' => 'class-1',
                'id_perguruan_tinggi' => 'pt-1', 'id_fakultas' => 'faculty-1',
                'id_prodi' => 'program-1', 'id_kalender' => 'calendar-1', 'id_matkul' => 'course-1',
            ],
            [
                'id_krs' => 'krs-3', 'id_mahasiswa' => 'student-3', 'id_jadwal' => 'class-ambiguous',
                'id_perguruan_tinggi' => null, 'id_fakultas' => null,
                'id_prodi' => 'program-1', 'id_kalender' => 'calendar-1', 'id_matkul' => 'course-1',
            ],
            [
                'id_krs' => 'krs-4', 'id_mahasiswa' => 'student-4', 'id_jadwal' => 'class-ambiguous',
                'id_perguruan_tinggi' => null, 'id_fakultas' => null,
                'id_prodi' => 'program-1', 'id_kalender' => 'calendar-1', 'id_matkul' => 'course-2',
            ],
        ]);
        $connection->table('s1m4k_jadwal')->insert([
            'id_jadwal' => 'class-existing',
            'id_prodi' => 'program-1',
        ]);
        $connection->table('s1m4k_krs')->insert([
            'id_krs' => 'krs-existing', 'id_mahasiswa' => 'student-5', 'id_jadwal' => 'class-existing',
            'id_prodi' => 'program-1', 'id_kalender' => 'calendar-1', 'id_matkul' => 'course-1',
        ]);

        $this->artisan('siakad:backfill-grade-schedules')
            ->expectsOutputToContain('Dapat dibuat: 1 jadwal placeholder; 1 sudah ada, 1 dilewati karena data KRS ambigu, 0 ID jadwal tidak valid.')
            ->assertExitCode(0);
        $this->assertSame(1, $connection->table('s1m4k_jadwal')->count());

        $this->artisan('siakad:backfill-grade-schedules', ['--apply' => true, '--yes' => true])
            ->expectsOutputToContain('Dibuat: 1 jadwal placeholder; 1 sudah ada, 1 dilewati karena data KRS ambigu, 0 ID jadwal tidak valid.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('s1m4k_jadwal', [
            'id_jadwal' => 'class-1',
            'id_kelas_kuliah' => 'class-1',
            'id_perguruan_tinggi' => 'pt-1',
            'id_fakultas' => 'faculty-1',
            'id_prodi' => 'program-1',
            'id_kalender' => 'calendar-1',
            'id_matkul' => 'course-1',
            'nama_kelas' => 'Feeder',
            'jml_mahasiswa' => 2,
        ], 'simak_main');
        $this->assertSame(2, $connection->table('s1m4k_jadwal')->count());

        $this->artisan('siakad:backfill-grade-schedules', ['--apply' => true, '--yes' => true])
            ->expectsOutputToContain('Dibuat: 0 jadwal placeholder; 2 sudah ada, 1 dilewati karena data KRS ambigu, 0 ID jadwal tidak valid.')
            ->assertExitCode(0);
        $this->assertSame(2, $connection->table('s1m4k_jadwal')->count());
    }

    public function test_only_admin_can_open_or_start_initialization(): void
    {
        $this->actingAs(new GenericUser([
            'id' => 'staff-1', 'role' => 'userprodi', 'name' => 'Staff',
        ]));

        $this->get(route('feeder-initialization.index'))->assertForbidden();
        $this->postJson(route('feeder-initialization.start'), [
            'url' => 'https://feeder.example.test',
            'username' => 'feeder-user',
            'password' => 'secret',
        ])->assertForbidden();
        $this->postJson(route('feeder-initialization.next'))->assertForbidden();
    }

    public function test_start_keeps_credentials_encrypted_and_does_not_save_them_before_profile_import(): void
    {
        $this->actingAs($this->admin());
        Http::fake(fn () => Http::response(['error_code' => '0', 'data' => ['token' => 'verified-token']]));
        $response = $this->postJson(route('feeder-initialization.start'), [
            'url' => 'http://localhost:8082/ws',
            'username' => 'feeder-user',
            'password' => 'feeder-secret',
        ])->assertCreated()
            ->assertJsonPath('stage', 'connection')
            ->assertJsonPath('progress', 0)
            ->assertJsonPath('connection_verified', true)
            ->assertJsonPath('ready_for_next', true)
            ->assertDontSee('feeder-secret');

        $job = Cache::get('feeder-initialization:'.$response->json('job_id'));
        $this->assertIsArray($job);
        $this->assertNotSame('feeder-user', $job['username_encrypted']);
        $this->assertNotSame('feeder-secret', $job['password_encrypted']);
        $this->assertSame('feeder-user', Crypt::decryptString($job['username_encrypted']));
        $this->assertSame('feeder-secret', Crypt::decryptString($job['password_encrypted']));
        $this->assertSame('verified-token', Crypt::decryptString($job['token_encrypted']));
        $this->assertSame(0, DB::connection('simak_main')->table('s1m4k_masterpt')->count());
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'GetToken'
            && ($request['username'] ?? '') === 'feeder-user'
            && ($request['password'] ?? '') === 'feeder-secret');
        $this->postJson(route('feeder-initialization.next'))
            ->assertOk()
            ->assertJsonPath('stage', 'references')
            ->assertJsonPath('ready_for_next', false);
        Http::assertSentCount(1);
    }

    public function test_failed_connection_test_does_not_create_a_wizard_or_save_credentials(): void
    {
        $this->actingAs($this->admin());
        Http::fake(fn () => Http::response(['error_code' => '1', 'error_desc' => 'Kredensial tidak valid']));

        $this->postJson(route('feeder-initialization.start'), [
            'url' => 'http://localhost:8082/ws',
            'username' => 'feeder-user',
            'password' => 'invalid-secret',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Feeder gagal (1): Kredensial tidak valid');

        $this->assertSame(0, DB::connection('simak_main')->table('s1m4k_masterpt')->count());
        $this->assertNull(session('feeder_initialization_job'));
    }

    public function test_wizard_page_shows_ordered_tabs_and_prerequisite_links(): void
    {
        $this->actingAs($this->admin());
        $jobId = (string) Str::uuid();
        $this->withSession(['feeder_initialization_job' => $jobId]);
        $state = $this->jobState($jobId, 'connection');
        $state['connection_verified'] = true;
        $state['stage_complete'] = true;
        Cache::put('feeder-initialization:'.$jobId, $state);

        $this->get(route('feeder-initialization.index'))
            ->assertOk()
            ->assertSee('Koneksi')
            ->assertSee('Master PT')
            ->assertSee('Program Studi')
            ->assertSee('Kalender Akademik')
            ->assertSee('Nilai Mahasiswa')
            ->assertSee('Ulangi tahapan ini')
            ->assertSee(route('master-data.fakultas.index'))
            ->assertSee(route('master-data.program-studi.index'));
    }

    public function test_profile_batch_saves_connection_settings_only_after_profile_is_imported(): void
    {
        $this->actingAs($this->admin());
        $jobId = (string) Str::uuid();
        $this->withSession(['feeder_initialization_job' => $jobId]);
        Cache::put('feeder-initialization:'.$jobId, $this->jobState($jobId, 'university'));
        Http::fake(function (ClientRequest $request) {
            if (($request['act'] ?? '') === 'GetToken') {
                return Http::response(['error_code' => '0', 'data' => ['token' => 'test-token']]);
            }

            return Http::response([
                'error_code' => '0',
                'data' => [[
                    'id_perguruan_tinggi' => 'pt-1',
                    'kode_perguruan_tinggi' => '001',
                    'nama_perguruan_tinggi' => 'Perguruan Tinggi Uji',
                ]],
            ]);
        });

        $this->postJson(route('feeder-initialization.step'))
            ->assertOk()
            ->assertJsonPath('stage', 'university')
            ->assertJsonPath('stage_complete', true);

        $profile = DB::connection('simak_main')->table('s1m4k_masterpt')->first();
        $this->assertSame('pt-1', $profile->id_perguruan_tinggi);
        $this->assertSame('Perguruan Tinggi Uji', $profile->nama_perguruan_tinggi);
        $this->assertSame('http://localhost:8082/ws', $profile->alamat_feeder);
        $this->assertSame('feeder-user', $profile->user_feeder);
        $this->assertSame($this->legacyEncrypt('feeder-secret'), $profile->password_feeder);
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'GetProfilPT'
            && ($request['limit'] ?? null) === 1
            && ($request['offset'] ?? null) === 0);
    }

    public function test_reference_import_uses_paged_ws_requests(): void
    {
        $rows = [];
        for ($index = 1; $index <= FeederInitializationService::BATCH_SIZE; $index++) {
            $rows[] = [
                'id_pembiayaan' => 'funding-'.$index,
                'nama_pembiayaan' => 'Sumber dana '.$index,
            ];
        }
        Http::fake(function (ClientRequest $request) use ($rows) {
            if (($request['act'] ?? '') === 'GetToken') {
                return Http::response(['error_code' => '0', 'data' => ['token' => 'test-token']]);
            }

            return Http::response(['error_code' => '0', 'data' => $rows]);
        });

        $state = app(FeederInitializationService::class)
            ->processBatch($this->jobState((string) Str::uuid(), 'references'), app(LegacyFeederService::class));

        $this->assertSame('references', $state['stage']);
        $this->assertSame(0, $state['reference_index']);
        $this->assertSame(FeederInitializationService::BATCH_SIZE, $state['offset']);
        $this->assertSame(FeederInitializationService::BATCH_SIZE, $state['records']);
        $this->assertSame(FeederInitializationService::BATCH_SIZE, DB::connection('simak_main')
            ->table('s1m4k_kodereferensi')->count());
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'GetPembiayaan'
            && ($request['limit'] ?? null) === FeederInitializationService::BATCH_SIZE
            && ($request['offset'] ?? null) === 0);
    }

    public function test_terminal_mode_paginates_responses_into_batches(): void
    {
        $rows = [];
        for ($index = 1; $index <= 101; $index++) {
            $rows[] = [
                'id_pembiayaan' => 'funding-'.$index,
                'nama_pembiayaan' => 'Sumber dana '.$index,
            ];
        }
        Http::fake(function (ClientRequest $request) use ($rows) {
            if (($request['act'] ?? '') === 'GetToken') {
                return Http::response(['error_code' => '0', 'data' => ['token' => 'test-token']]);
            }

            return Http::response(['error_code' => '0', 'data' => array_slice(
                $rows,
                (int) ($request['offset'] ?? 0),
                (int) ($request['limit'] ?? 0),
            )]);
        });

        $initialization = app(FeederInitializationService::class);
        $state = $initialization->newTerminalState(
            'http://localhost:8082/ws',
            'feeder-user',
            'feeder-secret',
            'test-token',
        );
        $state = $initialization->nextStage($state);
        $state = $initialization->processBatch($state, app(LegacyFeederService::class));

        $this->assertSame(100, $state['records']);
        $this->assertSame(0, $state['reference_index']);
        $this->assertSame(100, $state['offset']);
        $state = $initialization->processBatch($state, app(LegacyFeederService::class));
        $this->assertSame(101, $state['records']);
        $this->assertSame(1, $state['reference_index']);
        $this->assertSame(0, $state['offset']);
        Http::assertSentCount(2);
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'GetPembiayaan'
            && ($request['limit'] ?? null) === FeederInitializationService::BATCH_SIZE
            && ($request['offset'] ?? null) === 0);
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'GetPembiayaan'
            && ($request['limit'] ?? null) === FeederInitializationService::BATCH_SIZE
            && ($request['offset'] ?? null) === 100);
    }

    public function test_completed_reference_import_enriches_wilayah_from_bundled_coordinates(): void
    {
        $connection = DB::connection('simak_main');
        $connection->table('s1m4k_kodereferensi')->insert([
            'id_referensi' => 'region-1',
            'kode_referensi' => '020537',
            'ket_referensi' => 'ID',
            'ket_referensi2' => 'Kec. Tajurhalang',
            'keterangan' => 'GetWilayah',
        ]);
        $actions = array_keys((new \ReflectionClass(FeederInitializationService::class))
            ->getConstant('REFERENCE_ACTIONS'));
        Http::fake(function (ClientRequest $request) {
            if (($request['act'] ?? '') === 'GetToken') {
                return Http::response(['error_code' => '0', 'data' => ['token' => 'test-token']]);
            }

            return Http::response(['error_code' => '0', 'data' => []]);
        });
        $state = $this->jobState((string) Str::uuid(), 'references');
        $state['reference_index'] = count($actions) - 1;
        $service = app(FeederInitializationService::class);

        $state = $service->processBatch($state, app(LegacyFeederService::class));
        $this->assertSame(count($actions), $state['reference_index']);
        $this->assertFalse($state['stage_complete']);

        $state = $service->processBatch($state, app(LegacyFeederService::class));
        $region = $connection->table('s1m4k_kodereferensi')
            ->where('id_referensi', 'region-1')->first();

        $this->assertSame('GetWilayah', $region->keterangan);
        $this->assertSame('Kec. Tajurhalang', $region->ket_referensi2);
        $this->assertSame('Jawa Barat', $region->nama_provinsi);
        $this->assertEqualsWithDelta(-6.4752826, (float) $region->lat, 0.0000001);
        $this->assertEqualsWithDelta(106.7576126, (float) $region->lng, 0.0000001);
        $this->assertEqualsWithDelta(-6.8891904, (float) $region->lat_provinsi, 0.0000001);
        $this->assertEqualsWithDelta(107.6404716, (float) $region->lng_provinsi, 0.0000001);
        $this->assertSame(FeederInitializationService::BATCH_SIZE, $state['wilayah_enrichment_offset']);
        $this->assertFalse($state['stage_complete']);

        for ($batch = 0; $batch < 100 && ! $state['stage_complete']; $batch++) {
            $state = $service->processBatch($state, app(LegacyFeederService::class));
        }
        $this->assertTrue($state['stage_complete']);
        $this->assertSame(8420, $state['wilayah_enrichment_offset']);
    }

    public function test_program_import_keeps_the_manual_faculty_mapping(): void
    {
        $connection = DB::connection('simak_main');
        DB::connection('simak_main')->table('s1m4k_masterpt')->insert([
            'id_perguruan_tinggi' => 'pt-1',
        ]);
        $connection->table('s1m4k_masterfakultas')->insert([
            'id_fakultas' => 'faculty-1',
            'nama_fakultas' => 'Fakultas Uji',
        ]);
        Schema::connection('simak_main')->create('s1m4k_masterprodi', function (Blueprint $table): void {
            $table->string('id_prodi')->primary();
            $table->string('id_perguruan_tinggi')->nullable();
            $table->string('id_fakultas')->nullable();
            $table->string('kode_program_studi')->nullable();
            $table->string('nama_program_studi')->nullable();
            $table->string('status')->nullable();
            $table->string('id_jenjang_pendidikan')->nullable();
            $table->string('nama_jenjang_pendidikan')->nullable();
            for ($index = 1; $index <= 8; $index++) {
                $table->integer('k'.$index)->default(0);
            }
        });
        DB::connection('simak_main')->table('s1m4k_masterprodi')->insert([
            'id_prodi' => 'program-1',
            'id_perguruan_tinggi' => 'pt-1',
            'id_fakultas' => 'faculty-1',
            'k1' => 1, 'k2' => 2, 'k3' => 3, 'k4' => 4,
            'k5' => 5, 'k6' => 6, 'k7' => 7, 'k8' => 8,
        ]);
        Http::fake(function (ClientRequest $request) {
            if (($request['act'] ?? '') === 'GetToken') {
                return Http::response(['error_code' => '0', 'data' => ['token' => 'test-token']]);
            }

            return Http::response(['error_code' => '0', 'data' => [[
                'id_prodi' => 'program-1',
                'kode_program_studi' => '101',
                'nama_program_studi' => 'Program Studi Uji',
                'status' => 'A',
                'id_jenjang_pendidikan' => '3',
                'nama_jenjang_pendidikan' => 'S1',
            ]]]);
        });

        $state = app(FeederInitializationService::class)
            ->processBatch($this->jobState((string) Str::uuid(), 'programs'), app(LegacyFeederService::class));
        $program = DB::connection('simak_main')->table('s1m4k_masterprodi')->first();

        $this->assertSame('faculty-1', $program->id_fakultas);
        $this->assertSame('Program Studi Uji', $program->nama_program_studi);
        $this->assertSame(8, $program->k8);
        $this->assertSame('programs', $state['stage']);
        $this->assertTrue($state['stage_complete']);
    }

    public function test_student_history_lookups_run_in_parallel_and_save_profiles_immediately(): void
    {
        Schema::connection('simak_main')->create('s1m4k_masterprodi', function (Blueprint $table): void {
            $table->string('id_prodi')->primary();
            $table->string('id_perguruan_tinggi')->nullable();
            $table->string('id_fakultas')->nullable();
        });
        DB::connection('simak_main')->table('s1m4k_masterprodi')->insert([
            'id_prodi' => 'program-1',
            'id_perguruan_tinggi' => 'pt-1',
            'id_fakultas' => 'faculty-1',
        ]);
        DB::connection('simak_main')->table('s1m4k_kodereferensi')->insert([
            'id_referensi' => 'student-status-active',
            'kode_referensi' => 'A',
            'ket_referensi' => 'Aktif',
            'keterangan' => 'GetStatusMahasiswa',
        ]);
        Schema::connection('simak_main')->create('s1m4k_mastermhs', function (Blueprint $table): void {
            $table->string('id_mahasiswa')->primary();
            foreach ([
                'id_perguruan_tinggi', 'id_fakultas', 'id_prodi', 'id_registrasi_mahasiswa',
                'id_status_mahasiswa', 'nama_status_mahasiswa', 'nim', 'nama_mahasiswa',
                'password', 'jenis_kelamin', 'jalan', 'rt', 'rw', 'dusun', 'kelurahan',
                'kode_pos', 'nisn', 'nik', 'tempat_lahir', 'tanggal_lahir', 'nama_ayah',
                'tanggal_lahir_ayah', 'nik_ayah', 'id_pendidikan_ayah', 'id_pekerjaan_ayah',
                'id_penghasilan_ayah', 'id_kebutuhan_khusus_ayah', 'nama_ibu',
                'tanggal_lahir_ibu', 'nik_ibu', 'id_pendidikan_ibu', 'id_pekerjaan_ibu',
                'id_penghasilan_ibu', 'id_kebutuhan_khusus_ibu', 'nama_wali',
                'tanggal_lahir_wali', 'id_pendidikan_wali', 'id_pekerjaan_wali',
                'id_penghasilan_wali', 'id_kebutuhan_khusus_mahasiswa', 'telepon',
                'handphone', 'email', 'penerima_kps', 'nomor_kps', 'npwp', 'id_wilayah',
                'id_jenis_tinggal', 'id_agama', 'id_alat_transportasi', 'id_negara',
                'id_jenis_daftar', 'id_jalur_daftar', 'id_periode_masuk', 'tanggal_daftar',
                'sks_diakui', 'id_perguruan_tinggi_asal', 'id_prodi_asal', 'id_pembiayaan',
                'biaya_masuk_kuliah', 'flag_feeder', 'update_ukt',
            ] as $column) {
                $table->string($column)->nullable();
            }
        });
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            'id_mahasiswa' => 'student-1',
            'nim' => '20260001',
            'password' => 'existing-password-hash',
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            'id_mahasiswa' => 'student-2',
            'id_prodi' => 'program-1',
            'nim' => '19990002',
            'id_registrasi_mahasiswa' => 'existing-registration',
            'nama_mahasiswa' => 'Data lokal tetap',
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            'id_mahasiswa' => 'student-existing-status',
            'id_status_mahasiswa' => null,
            'nama_status_mahasiswa' => 'AKTIF',
        ]);
        Http::fake(function (ClientRequest $request) {
            if (($request['act'] ?? '') === 'GetToken') {
                return Http::response(['error_code' => '0', 'data' => ['token' => 'test-token']]);
            }
            if (($request['act'] ?? '') === 'GetDataLengkapMahasiswaProdi') {
                return Http::response(['error_code' => '0', 'data' => array_map(
                    static fn (int $number): array => [
                        'id_mahasiswa' => 'student-'.$number,
                        'id_prodi' => 'program-1',
                        'nim' => '2026000'.$number,
                        'nama_mahasiswa' => 'Mahasiswa Uji '.$number,
                        'id_registrasi_mahasiswa' => 'registration-student-'.$number,
                        'nama_status_mahasiswa' => $number === 5 ? 'Lulus' : 'AKTIF',
                        'tanggal_lahir' => '2000-01-0'.$number,
                        'nik' => $number === 1 ? '001234567890123456' : null,
                        'nik_ayah' => $number === 1 ? '123456789012345678' : null,
                        'nik_ibu' => $number === 1 ? '009876543210987654' : null,
                    ],
                    range(1, 5),
                )]);
            }
            if (($request['act'] ?? '') === 'GetListRiwayatPendidikanMahasiswa') {
                $filter = (string) ($request['filter'] ?? '');
                if ($filter === "id_registrasi_mahasiswa='registration-student-3'") {
                    return Http::response([
                        'error_code' => '121',
                        'error_desc' => 'Kolom filter registrasi tidak didukung',
                    ]);
                }
                preg_match('/registration-student-(\\d+)/', $filter, $matches);
                if (! isset($matches[1])) {
                    preg_match("/id_mahasiswa='student-(\\d+)'/", $filter, $matches);
                }
                $number = (int) ($matches[1] ?? 0);

                return Http::response(['error_code' => '0', 'data' => [[
                    'id_mahasiswa' => 'student-'.$number,
                    'id_prodi' => 'program-1',
                    'id_perguruan_tinggi' => 'pt-1',
                    'id_registrasi_mahasiswa' => 'registration-student-'.$number,
                    'id_jenis_daftar' => '1',
                    'id_jalur_daftar' => '1',
                    'tanggal_daftar' => '2026-01-15',
                    'id_pembiayaan' => 'funding-1',
                    'biaya_masuk' => 1000000,
                ]]]);
            }

            return Http::response(['error_code' => '0', 'data' => []]);
        });
        $service = app(FeederInitializationService::class);
        $state = $this->jobState((string) Str::uuid(), 'students');
        $state = $service->processBatch($state, app(LegacyFeederService::class));
        $this->assertTrue($state['student_profiles_complete']);
        $this->assertSame([], $state['student_history_pending']);
        $this->assertDatabaseHas('s1m4k_mastermhs', [
            'id_mahasiswa' => 'student-3',
            'nama_mahasiswa' => 'Mahasiswa Uji 3',
            'tanggal_daftar' => '2026-01-15',
        ], 'simak_main');
        $this->assertNull(DB::connection('simak_main')->table('s1m4k_mastermhs')
            ->where('id_mahasiswa', 'student-existing-status')->value('id_status_mahasiswa'));
        $this->assertSame(0, $state['student_status_ids_backfilled'] ?? 0);
        $this->assertSame(1, $state['student_nim_conflict_count']);
        $this->assertDatabaseMissing('s1m4k_mastermhs', [
            'id_mahasiswa' => 'student-5',
        ], 'simak_main');
        $this->assertStringContainsString('1 konflik ID/NIM dilewati', $state['last_batch']);
        $this->assertStringContainsString(
            'ID mahasiswa student-2 (NIM lokal 19990002, NIM Feeder 20260002)',
            $state['last_batch'],
        );
        $this->assertSame('students', $state['stage']);

        $this->assertSame('Mahasiswa Uji 1', DB::connection('simak_main')->table('s1m4k_mastermhs')
            ->where('id_mahasiswa', 'student-1')->value('nama_mahasiswa'));
        $this->assertSame('1000000', DB::connection('simak_main')->table('s1m4k_mastermhs')
            ->where('id_mahasiswa', 'student-1')->value('biaya_masuk_kuliah'));
        $this->assertSame(3, DB::connection('simak_main')->table('s1m4k_mastermhs')
            ->whereNotNull('tanggal_daftar')->count());
        $this->assertSame('1000000', DB::connection('simak_main')->table('s1m4k_mastermhs')
            ->where('id_mahasiswa', 'student-1')->value('biaya_masuk_kuliah'));
        $state = $service->processBatch($state, app(LegacyFeederService::class));
        $this->assertSame('A', DB::connection('simak_main')->table('s1m4k_mastermhs')
            ->where('id_mahasiswa', 'student-existing-status')->value('id_status_mahasiswa'));
        $this->assertStringContainsString('1 ID status mahasiswa yang kosong diperbaiki', $state['last_batch']);
        $this->assertDatabaseHas('s1m4k_mastermhs', [
            'id_mahasiswa' => 'student-existing-status',
            'id_status_mahasiswa' => 'A',
        ], 'simak_main');
        $this->assertTrue($state['stage_complete']);
        foreach (range(1, 4) as $number) {
            $student = DB::connection('simak_main')->table('s1m4k_mastermhs')
                ->where('id_mahasiswa', 'student-'.$number)->first();
            if ($number === 2) {
                $this->assertSame('19990002', $student->nim);
                $this->assertSame('existing-registration', $student->id_registrasi_mahasiswa);
                $this->assertSame('Data lokal tetap', $student->nama_mahasiswa);

                continue;
            }
            $this->assertSame('registration-student-'.$number, $student->id_registrasi_mahasiswa);
            $this->assertSame('A', $student->id_status_mahasiswa);
            $this->assertSame('Aktif', $student->nama_status_mahasiswa);
            if ($number === 1) {
                $this->assertSame('existing-password-hash', $student->password);
                $this->assertSame('0012345678901234', $student->nik);
                $this->assertSame('1234567890123456', $student->nik_ayah);
                $this->assertSame('0098765432109876', $student->nik_ibu);
            } else {
                $this->assertTrue(Hash::check('2026000'.$number, $student->password));
            }
        }
        $this->assertSame(6, $state['records']);
        $this->assertSame(1, $state['student_nim_conflict_count']);
        Http::assertSentCount(6); // Token, one profile batch, three exact lookups, and one fallback.
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'GetListRiwayatPendidikanMahasiswa'
            && ($request['filter'] ?? '') === "id_registrasi_mahasiswa='registration-student-1'"
            && ($request['limit'] ?? null) === 1);
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'GetListRiwayatPendidikanMahasiswa'
            && ($request['filter'] ?? '') === "id_mahasiswa='student-3' AND id_prodi='program-1'"
            && ($request['order'] ?? '') === 'id_periode_masuk DESC'
            && ($request['limit'] ?? null) === 1);
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'GetDataLengkapMahasiswaProdi'
            && ($request['filter'] ?? '') === "id_prodi='program-1' AND (nama_status_mahasiswa LIKE '%Aktif%' OR nama_status_mahasiswa LIKE '%Cuti%' OR nama_status_mahasiswa LIKE '%Kampus Merdeka%' OR nama_status_mahasiswa LIKE '%Non-Aktif%' OR nama_status_mahasiswa LIKE '%Non Aktif%')"
            && ($request['limit'] ?? null) === FeederInitializationService::STUDENT_BATCH_SIZE);
    }

    public function test_student_history_parallel_group_is_limited_to_fifty_requests(): void
    {
        $this->createStudentGradeSchema();
        Schema::connection('simak_main')->create('s1m4k_masterprodi', function (Blueprint $table): void {
            $table->string('id_prodi')->primary();
            $table->string('id_perguruan_tinggi')->nullable();
            $table->string('id_fakultas')->nullable();
        });
        DB::connection('simak_main')->table('s1m4k_masterprodi')->insert([
            'id_prodi' => 'program-1',
            'id_perguruan_tinggi' => 'pt-1',
            'id_fakultas' => 'faculty-1',
        ]);
        $pending = [];
        for ($number = 1; $number <= 51; $number++) {
            $id = 'student-'.$number;
            $registrationId = 'registration-'.$number;
            $pending[] = [
                'id_mahasiswa' => $id,
                'id_registrasi_mahasiswa' => $registrationId,
                'profile' => [
                    'id_mahasiswa' => $id,
                    'nim' => (string) $number,
                    'id_prodi' => 'program-1',
                    'id_registrasi_mahasiswa' => $registrationId,
                    'tanggal_daftar' => null,
                    'id_pembiayaan' => null,
                    'biaya_masuk_kuliah' => null,
                ],
            ];
        }
        Http::fake(function (ClientRequest $request) {
            if (($request['act'] ?? '') === 'GetToken') {
                return Http::response(['error_code' => '0', 'data' => ['token' => 'test-token']]);
            }
            preg_match('/registration-(\\d+)/', (string) ($request['filter'] ?? ''), $matches);
            $number = (int) ($matches[1] ?? 0);

            return Http::response(['error_code' => '0', 'data' => [[
                'id_mahasiswa' => 'student-'.$number,
                'id_prodi' => 'program-1',
                'id_registrasi_mahasiswa' => 'registration-'.$number,
                'tanggal_daftar' => '2026-01-15',
                'id_pembiayaan' => 'funding-1',
                'biaya_masuk' => 1000000,
            ]]]);
        });

        $state = $this->jobState((string) Str::uuid(), 'students');
        $state['program_ids'] = ['program-1'];
        $state['student_history_strategy'] = 'per-student-history-before-write-v3';
        $state['student_history_pending'] = $pending;
        $state['student_profiles_complete'] = true;
        $state = app(FeederInitializationService::class)
            ->processBatch($state, app(LegacyFeederService::class));

        $this->assertSame(1, count($state['student_history_pending']));
        $this->assertSame(50, DB::connection('simak_main')->table('s1m4k_mastermhs')->count());
        $this->assertSame(50, DB::connection('simak_main')->table('s1m4k_mastermhs')
            ->whereNotNull('tanggal_daftar')->count());
        Http::assertSentCount(51);
        $this->assertSame(
            50,
            Http::recorded()->filter(
                fn (array $record): bool => ($record[0]['act'] ?? '') === 'GetListRiwayatPendidikanMahasiswa',
            )->count(),
        );
    }

    public function test_legacy_student_history_checkpoint_uses_per_student_fallback(): void
    {
        $this->createStudentGradeSchema();
        Schema::connection('simak_main')->create('s1m4k_masterprodi', function (Blueprint $table): void {
            $table->string('id_prodi')->primary();
            $table->string('id_perguruan_tinggi')->nullable();
            $table->string('id_fakultas')->nullable();
        });

        $connection = DB::connection('simak_main');
        $connection->table('s1m4k_masterprodi')->insert([
            'id_prodi' => 'program-1',
            'id_perguruan_tinggi' => 'pt-1',
            'id_fakultas' => 'faculty-1',
        ]);
        $students = [];
        $histories = [];
        for ($number = 1; $number <= 101; $number++) {
            $id = 'student-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT);
            $students[] = [
                'id_mahasiswa' => $id,
                'nim' => '2026'.str_pad((string) $number, 4, '0', STR_PAD_LEFT),
                'id_status_mahasiswa' => 'A',
                'id_perguruan_tinggi' => 'pt-1',
                'id_fakultas' => 'faculty-1',
                'id_prodi' => 'program-1',
            ];
            $histories[] = [
                'id_mahasiswa' => $id,
                'id_prodi' => 'program-1',
                'id_perguruan_tinggi' => 'pt-1',
                'id_registrasi_mahasiswa' => 'registration-'.$number,
                'id_jenis_daftar' => '1',
                'tanggal_daftar' => '2026-01-15',
                'id_pembiayaan' => 'funding-1',
                'biaya_masuk' => 1000000,
                'id_jalur_daftar' => '1',
            ];
        }
        $connection->table('s1m4k_mastermhs')->insert($students);

        Http::fake(function (ClientRequest $request) use ($histories) {
            if (($request['act'] ?? '') === 'GetToken') {
                return Http::response(['error_code' => '0', 'data' => ['token' => 'test-token']]);
            }

            preg_match("/id_mahasiswa='([^']+)'/", (string) ($request['filter'] ?? ''), $matches);

            return Http::response([
                'error_code' => '0',
                'data' => array_values(array_filter(
                    $histories,
                    static fn (array $history): bool => $history['id_mahasiswa'] === ($matches[1] ?? null),
                )),
            ]);
        });

        $state = $this->jobState((string) Str::uuid(), 'students');
        $state['program_ids'] = ['program-1'];
        $state['student_history_bulk_mode'] = true;
        $state['student_profiles_complete'] = true;
        $service = app(FeederInitializationService::class);
        $attempts = 0;
        while (! ($state['stage_complete'] ?? false) && $attempts < 110) {
            $state = $service->processBatch($state, app(LegacyFeederService::class));
            $attempts++;
        }
        $this->assertTrue($state['stage_complete']);
        $this->assertSame(101, $connection->table('s1m4k_mastermhs')
            ->whereNotNull('tanggal_daftar')->count());
        Http::assertSentCount(102);
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'GetListRiwayatPendidikanMahasiswa'
            && preg_match("/^id_mahasiswa='student-\\d+' AND id_prodi='program-1'$/", (string) ($request['filter'] ?? '')) === 1
            && ($request['order'] ?? '') === 'id_periode_masuk DESC'
            && ($request['offset'] ?? null) === 0
            && ($request['limit'] ?? null) === 1);
    }

    public function test_legacy_history_checkpoint_falls_back_to_latest_student_program_record(): void
    {
        $this->createStudentGradeSchema();
        Schema::connection('simak_main')->create('s1m4k_masterprodi', function (Blueprint $table): void {
            $table->string('id_prodi')->primary();
            $table->string('id_perguruan_tinggi')->nullable();
            $table->string('id_fakultas')->nullable();
        });
        $connection = DB::connection('simak_main');
        $connection->table('s1m4k_masterprodi')->insert([
            'id_prodi' => 'program-1',
            'id_perguruan_tinggi' => 'pt-1',
            'id_fakultas' => 'faculty-1',
        ]);
        $connection->table('s1m4k_mastermhs')->insert([
            'id_mahasiswa' => 'student-1',
            'id_prodi' => 'program-1',
            'nim' => '20260001',
            'id_status_mahasiswa' => 'A',
            'id_registrasi_mahasiswa' => 'profile-registration',
        ]);
        Http::fake(function (ClientRequest $request) {
            if (($request['act'] ?? '') === 'GetToken') {
                return Http::response(['error_code' => '0', 'data' => ['token' => 'test-token']]);
            }
            if (($request['filter'] ?? '') === "id_registrasi_mahasiswa='profile-registration'") {
                return Http::response([
                    'error_code' => '121',
                    'error_desc' => 'Kolom filter registrasi tidak didukung',
                ]);
            }

            return Http::response(['error_code' => '0', 'data' => [[
                'id_mahasiswa' => 'student-1',
                'id_prodi' => 'program-1',
                'id_registrasi_mahasiswa' => 'registration-1',
                'tanggal_daftar' => '2026-01-15',
                'id_pembiayaan' => 'funding-1',
                'biaya_masuk' => 1000000,
            ]]]);
        });
        $state = $this->jobState((string) Str::uuid(), 'students');
        $state['program_ids'] = ['program-1'];
        $state['student_history_bulk_mode'] = true;
        $state['student_profiles_complete'] = true;
        $state['student_history_keyset_mode'] = true;
        $service = app(FeederInitializationService::class);

        $state = $service->processBatch($state, app(LegacyFeederService::class));
        $this->assertSame('2026-01-15', $connection->table('s1m4k_mastermhs')
            ->where('id_mahasiswa', 'student-1')->value('tanggal_daftar'));
        $this->assertSame('funding-1', $connection->table('s1m4k_mastermhs')
            ->where('id_mahasiswa', 'student-1')->value('id_pembiayaan'));
        $this->assertSame('1000000', $connection->table('s1m4k_mastermhs')
            ->where('id_mahasiswa', 'student-1')->value('biaya_masuk_kuliah'));
        $state = $service->processBatch($state, app(LegacyFeederService::class));

        $this->assertTrue($state['stage_complete']);
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'GetListRiwayatPendidikanMahasiswa'
            && ($request['filter'] ?? '') === "id_registrasi_mahasiswa='profile-registration'"
            && ($request['limit'] ?? null) === 1);
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'GetListRiwayatPendidikanMahasiswa'
            && ($request['filter'] ?? '') === "id_mahasiswa='student-1' AND id_prodi='program-1'"
            && ($request['order'] ?? '') === 'id_periode_masuk DESC'
            && ($request['limit'] ?? null) === 1);
    }

    public function test_terminal_student_history_uses_per_student_lookups(): void
    {
        $this->createStudentGradeSchema();
        Schema::connection('simak_main')->create('s1m4k_masterprodi', function (Blueprint $table): void {
            $table->string('id_prodi')->primary();
            $table->string('id_perguruan_tinggi')->nullable();
            $table->string('id_fakultas')->nullable();
        });

        $connection = DB::connection('simak_main');
        $connection->table('s1m4k_masterprodi')->insert([
            'id_prodi' => 'program-1',
            'id_perguruan_tinggi' => 'pt-1',
            'id_fakultas' => 'faculty-1',
        ]);
        $students = [];
        $histories = [];
        for ($number = 1; $number <= 101; $number++) {
            $id = 'student-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT);
            $students[] = [
                'id_mahasiswa' => $id,
                'nim' => '2026'.str_pad((string) $number, 4, '0', STR_PAD_LEFT),
                'id_prodi' => 'program-1',
                'id_status_mahasiswa' => 'A',
            ];
            $histories[] = [
                'id_mahasiswa' => $id,
                'id_prodi' => 'program-1',
                'id_registrasi_mahasiswa' => 'registration-'.$number,
                'id_jenis_daftar' => '1',
                'tanggal_daftar' => '2026-01-15',
                'id_pembiayaan' => 'funding-1',
                'biaya_masuk' => 1000000,
            ];
        }
        $connection->table('s1m4k_mastermhs')->insert($students);
        Http::fake(function (ClientRequest $request) use ($histories) {
            if (($request['act'] ?? '') === 'GetToken') {
                return Http::response(['error_code' => '0', 'data' => ['token' => 'test-token']]);
            }

            preg_match("/id_mahasiswa='([^']+)'/", (string) ($request['filter'] ?? ''), $matches);

            return Http::response(['error_code' => '0', 'data' => array_values(array_filter(
                $histories,
                static fn (array $history): bool => $history['id_mahasiswa'] === ($matches[1] ?? null),
            ))]);
        });

        $state = app(FeederInitializationService::class)->newTerminalState(
            'http://localhost:8082/ws',
            'feeder-user',
            'feeder-secret',
            'test-token',
        );
        $state['stage'] = 'students';
        $state['stage_complete'] = false;
        $state['program_ids'] = ['program-1'];
        $state['student_history_bulk_mode'] = true;
        $state['student_profiles_complete'] = true;
        $service = app(FeederInitializationService::class);
        $attempts = 0;
        while (! ($state['stage_complete'] ?? false) && $attempts < 110) {
            $state = $service->processBatch($state, app(LegacyFeederService::class));
            $attempts++;
        }
        $this->assertTrue($state['stage_complete']);
        $this->assertSame(101, $connection->table('s1m4k_mastermhs')
            ->whereNotNull('tanggal_daftar')->count());
        Http::assertSentCount(101);
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'GetListRiwayatPendidikanMahasiswa'
            && preg_match("/^id_mahasiswa='student-\\d+' AND id_prodi='program-1'$/", (string) ($request['filter'] ?? '')) === 1
            && ($request['order'] ?? '') === 'id_periode_masuk DESC'
            && ($request['limit'] ?? null) === 1);
    }

    public function test_lecturer_import_updates_profile_password_titles_and_can_be_repeated(): void
    {
        $connection = DB::connection('simak_main');
        DB::connection('simak_main')->table('s1m4k_masterpt')->insert([
            'id_perguruan_tinggi' => 'pt-1',
        ]);
        Schema::connection('simak_main')->create('s1m4k_masterprodi', function (Blueprint $table): void {
            $table->string('id_prodi')->primary();
            $table->string('id_perguruan_tinggi')->nullable();
            $table->string('id_fakultas')->nullable();
            $table->string('nama_program_studi')->nullable();
        });
        DB::connection('simak_main')->table('s1m4k_masterprodi')->insert([
            'id_prodi' => 'program-1',
            'id_perguruan_tinggi' => 'pt-1',
            'id_fakultas' => 'faculty-1',
            'nama_program_studi' => 'Program Uji',
        ]);
        DB::connection('simak_main')->table('s1m4k_masterfakultas')->insert([
            'id_fakultas' => 'faculty-1',
            'nama_fakultas' => 'Fakultas Uji',
        ]);
        Schema::connection('simak_main')->create('s1m4k_masterdosen', function (Blueprint $table): void {
            $table->string('id_dosen')->primary();
            foreach ([
                'id_registrasi_dosen', 'id_perguruan_tinggi', 'id_fakultas', 'id_prodi',
                'id_tahun_ajaran', 'nama_dosen', 'password', 'nidn', 'nuptk', 'nip',
                'gelar_depan', 'gelar_belakang', 'jenis_kelamin', 'id_agama', 'tanggal_lahir', 'id_status_aktif',
                'nama_status_aktif', 'nomor_surat_tugas', 'tanggal_surat_tugas',
                'mulai_surat_tugas', 'flag_feeder',
            ] as $column) {
                $table->string($column)->nullable();
            }
        });
        $existingPassword = Hash::make('password-lama');
        DB::connection('simak_main')->table('s1m4k_masterdosen')->insert([
            'id_dosen' => 'lecturer-1',
            'nidn' => '12345',
            'password' => $existingPassword,
            'id_tahun_ajaran' => '20261',
        ]);
        Http::fake(function (ClientRequest $request) {
            if (($request['act'] ?? '') === 'GetToken') {
                return Http::response(['error_code' => '0', 'data' => ['token' => 'test-token']]);
            }
            if (($request['act'] ?? '') === 'GetListDosen') {
                return Http::response(['error_code' => '0', 'data' => [[
                    'id_dosen' => 'lecturer-1',
                    'nama_dosen' => 'Dosen Uji',
                    'nidn' => '12345',
                    'nuptk' => 'NUPTK-123',
                    'nip' => '98765',
                    'gelar_depan' => 'Dr.',
                    'gelar_belakang' => 'M.Kom.',
                    'jenis_kelamin' => 'L',
                    'tanggal_lahir' => '1980-05-06',
                ]]]);
            }

            return Http::response(['error_code' => '0', 'data' => [[
                'id_dosen' => 'lecturer-1',
                'id_registrasi_dosen' => 'lecturer-registration-1',
                'id_perguruan_tinggi' => 'pt-1',
                'id_prodi' => 'program-1',
                'id_tahun_ajaran' => '20261',
                'nomor_surat_tugas' => 'SK-1',
                'tanggal_surat_tugas' => '2026-01-01',
                'mulai_surat_tugas' => '2026-01-02',
            ]]]);
        });
        $service = app(FeederInitializationService::class);
        $state = $this->jobState((string) Str::uuid(), 'lecturers');
        $state = $service->processBatch($state, app(LegacyFeederService::class));
        $this->assertSame(1, $state['substage']);
        $this->assertTrue($state['lecturer_assignments_fetched']);
        $this->assertCount(1, $state['lecturer_assignments_pending']);
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'GetListDosen');
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'GetListPenugasanDosen'
            && ($request['limit'] ?? null) === FeederInitializationService::BATCH_SIZE
            && ($request['offset'] ?? null) === 0
            && ($request['order'] ?? '') === 'id_tahun_ajaran DESC');

        $state = $service->processBatch($state, app(LegacyFeederService::class));
        $lecturer = DB::connection('simak_main')->table('s1m4k_masterdosen')
            ->where('id_dosen', 'lecturer-1')->first();
        $this->assertSame('faculty-1', $lecturer->id_fakultas);
        $this->assertSame('Program Uji', DB::connection('simak_main')
            ->table('s1m4k_masterprodi')->value('nama_program_studi'));
        $this->assertSame('20261', $lecturer->id_tahun_ajaran);
        $this->assertSame('lecturer-registration-1', $lecturer->id_registrasi_dosen);
        $this->assertNotSame($existingPassword, $lecturer->password);
        $this->assertTrue(Hash::check('12345', $lecturer->password));
        $this->assertSame('12345', $lecturer->nidn);
        $this->assertSame('NUPTK-123', $lecturer->nuptk);
        $this->assertSame('Dr.', $lecturer->gelar_depan);
        $this->assertSame('M.Kom.', $lecturer->gelar_belakang);
        $this->assertSame('pt-1', $lecturer->id_perguruan_tinggi);
        $this->assertSame('program-1', $lecturer->id_prodi);
        $this->assertSame('faculty-1', $lecturer->id_fakultas);
        $this->assertSame('lecturers', $state['stage']);
        $this->assertTrue($state['stage_complete']);
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'GetListDosen'
            && ($request['limit'] ?? null) === FeederInitializationService::BATCH_SIZE);
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'GetListPenugasanDosen'
            && ($request['limit'] ?? null) === FeederInitializationService::BATCH_SIZE
            && ($request['offset'] ?? null) === 0
            && ($request['order'] ?? '') === 'id_tahun_ajaran DESC');

        $connection->table('s1m4k_masterdosen')->where('id_dosen', 'lecturer-1')->update([
            'password' => Hash::make('password-diubah'),
        ]);
        $state = $service->restartCurrentStage($state);
        $state = $service->processBatch($state, app(LegacyFeederService::class));
        $this->assertSame(1, $state['substage']);
        $updatedLecturer = $connection->table('s1m4k_masterdosen')
            ->where('id_dosen', 'lecturer-1')->first();
        $this->assertTrue(Hash::check('12345', $updatedLecturer->password));
    }

    public function test_lecturer_assignments_are_paginated_and_saved_for_every_lecturer(): void
    {
        $connection = DB::connection('simak_main');
        $connection->table('s1m4k_masterpt')->insert(['id_perguruan_tinggi' => 'pt-1']);
        $connection->table('s1m4k_masterfakultas')->insert([
            'id_fakultas' => 'faculty-1',
            'nama_fakultas' => 'Fakultas Uji',
        ]);
        Schema::connection('simak_main')->create('s1m4k_masterprodi', function (Blueprint $table): void {
            $table->string('id_prodi')->primary();
            $table->string('id_perguruan_tinggi')->nullable();
            $table->string('id_fakultas')->nullable();
            $table->string('nama_program_studi')->nullable();
        });
        $connection->table('s1m4k_masterprodi')->insert([
            'id_prodi' => 'program-1',
            'id_perguruan_tinggi' => 'pt-1',
            'id_fakultas' => 'faculty-1',
            'nama_program_studi' => 'Program Uji',
        ]);
        Schema::connection('simak_main')->create('s1m4k_masterdosen', function (Blueprint $table): void {
            $table->string('id_dosen')->primary();
            foreach ([
                'id_registrasi_dosen', 'id_perguruan_tinggi', 'id_fakultas', 'id_prodi',
                'id_tahun_ajaran', 'nama_dosen', 'password', 'nidn', 'nuptk', 'nip',
                'gelar_depan', 'gelar_belakang', 'jenis_kelamin', 'id_agama', 'tanggal_lahir',
                'id_status_aktif', 'nama_status_aktif', 'nomor_surat_tugas',
                'tanggal_surat_tugas', 'mulai_surat_tugas', 'flag_feeder',
            ] as $column) {
                $table->string($column)->nullable();
            }
        });

        $lecturers = [];
        $assignments = [[
            'id_dosen' => 'lecturer-1',
            'id_registrasi_dosen' => 'registration-unmapped',
            'id_perguruan_tinggi' => 'pt-1',
            'id_prodi' => 'program-not-imported',
            'id_tahun_ajaran' => '20271',
        ]];
        for ($number = 1; $number <= 101; $number++) {
            $id = 'lecturer-'.$number;
            $lecturers[] = [
                'id_dosen' => $id,
                'nidn' => 'nidn-'.$number,
                'nama_dosen' => 'Dosen '.$number,
            ];
            $assignments[] = [
                'id_dosen' => $id,
                'id_registrasi_dosen' => 'registration-'.$number,
                'id_perguruan_tinggi' => 'pt-1',
                'id_prodi' => 'program-1',
                'id_tahun_ajaran' => '20261',
            ];
        }
        $assignments[] = [
            'id_dosen' => 'lecturer-1',
            'id_registrasi_dosen' => 'registration-old',
            'id_perguruan_tinggi' => 'pt-1',
            'id_prodi' => 'program-1',
            'id_tahun_ajaran' => '20251',
        ];

        Http::fake(function (ClientRequest $request) use ($lecturers, $assignments) {
            $offset = (int) ($request['offset'] ?? 0);
            $limit = (int) ($request['limit'] ?? 0);

            return Http::response([
                'error_code' => '0',
                'data' => match ($request['act'] ?? '') {
                    'GetToken' => ['token' => 'test-token'],
                    'GetListDosen' => array_slice($lecturers, $offset, $limit),
                    'GetListPenugasanDosen' => array_slice($assignments, $offset, $limit),
                    default => [],
                },
            ]);
        });

        $service = app(FeederInitializationService::class);
        $state = $this->jobState((string) Str::uuid(), 'lecturers');
        $attempts = 0;
        while (! ($state['stage_complete'] ?? false) && $attempts < 10) {
            $state = $service->processBatch($state, app(LegacyFeederService::class));
            $attempts++;
        }

        $this->assertTrue($state['stage_complete']);
        $this->assertSame(101, $connection->table('s1m4k_masterdosen')->count());
        foreach (['id_registrasi_dosen', 'id_fakultas', 'id_prodi', 'id_tahun_ajaran'] as $column) {
            $this->assertSame(0, $connection->table('s1m4k_masterdosen')->whereNull($column)->count(), $column);
        }
        foreach ([1, 100, 101] as $number) {
            $lecturer = $connection->table('s1m4k_masterdosen')
                ->where('id_dosen', 'lecturer-'.$number)->first();
            $this->assertSame('registration-'.$number, $lecturer->id_registrasi_dosen);
            $this->assertSame('faculty-1', $lecturer->id_fakultas);
            $this->assertSame('program-1', $lecturer->id_prodi);
            $this->assertSame('20261', $lecturer->id_tahun_ajaran);
        }
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'GetListPenugasanDosen'
            && ($request['limit'] ?? null) === FeederInitializationService::BATCH_SIZE
            && ($request['offset'] ?? null) === 0
            && ($request['order'] ?? '') === 'id_tahun_ajaran DESC');
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'GetListPenugasanDosen'
            && ($request['limit'] ?? null) === FeederInitializationService::BATCH_SIZE
            && ($request['offset'] ?? null) === 100
            && ($request['order'] ?? '') === 'id_tahun_ajaran DESC');
    }

    public function test_terminal_lecturer_import_processes_dosen_in_batches_of_one_hundred(): void
    {
        DB::connection('simak_main')->table('s1m4k_masterpt')->insert([
            'id_perguruan_tinggi' => 'pt-1',
        ]);
        Schema::connection('simak_main')->create('s1m4k_masterdosen', function (Blueprint $table): void {
            $table->string('id_dosen')->primary();
            foreach ([
                'id_registrasi_dosen', 'id_perguruan_tinggi', 'id_fakultas', 'id_prodi',
                'id_tahun_ajaran', 'nama_dosen', 'password', 'nidn', 'nuptk', 'nip',
                'gelar_depan', 'gelar_belakang', 'jenis_kelamin', 'id_agama', 'tanggal_lahir',
                'id_status_aktif', 'nama_status_aktif', 'nomor_surat_tugas',
                'tanggal_surat_tugas', 'mulai_surat_tugas', 'flag_feeder',
            ] as $column) {
                $table->string($column)->nullable();
            }
        });
        $lecturers = array_map(
            static fn (int $number): array => [
                'id_dosen' => 'lecturer-'.$number,
                'nidn' => 'nidn-'.$number,
                'nama_dosen' => 'Dosen '.$number,
            ],
            range(1, 205),
        );
        Http::fake(fn (ClientRequest $request) => Http::response([
            'error_code' => '0',
            'data' => match ($request['act'] ?? '') {
                'GetToken' => ['token' => 'test-token'],
                'GetListDosen' => array_slice(
                    $lecturers,
                    (int) ($request['offset'] ?? 0),
                    (int) ($request['limit'] ?? 0),
                ),
                default => [],
            },
        ]));

        $state = $this->jobState((string) Str::uuid(), 'lecturers');
        $state['terminal_mode'] = true;
        $state = app(FeederInitializationService::class)
            ->processBatch($state, app(LegacyFeederService::class));

        $this->assertSame(100, $state['records']);
        $this->assertSame(100, DB::connection('simak_main')->table('s1m4k_masterdosen')->count());
        $this->assertSame(0, $state['substage'] ?? 0);
        $state = app(FeederInitializationService::class)
            ->processBatch($state, app(LegacyFeederService::class));
        $this->assertSame(200, $state['records']);
        $this->assertSame(200, DB::connection('simak_main')->table('s1m4k_masterdosen')->count());
        $state = app(FeederInitializationService::class)
            ->processBatch($state, app(LegacyFeederService::class));
        $this->assertSame(205, $state['records']);
        $this->assertSame(205, DB::connection('simak_main')->table('s1m4k_masterdosen')->count());
        $this->assertSame(1, $state['substage']);
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'GetListDosen'
            && ($request['limit'] ?? null) === FeederInitializationService::BATCH_SIZE
            && ($request['offset'] ?? null) === 0);
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'GetListDosen'
            && ($request['limit'] ?? null) === FeederInitializationService::BATCH_SIZE
            && ($request['offset'] ?? null) === 100);
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'GetListDosen'
            && ($request['limit'] ?? null) === FeederInitializationService::BATCH_SIZE
            && ($request['offset'] ?? null) === 200);
    }

    public function test_lecturer_password_and_nidn_fall_back_to_nip_then_nuptk(): void
    {
        DB::connection('simak_main')->table('s1m4k_masterpt')->insert([
            'id_perguruan_tinggi' => 'pt-1',
        ]);
        Schema::connection('simak_main')->create('s1m4k_masterdosen', function (Blueprint $table): void {
            $table->string('id_dosen')->primary();
            foreach ([
                'id_perguruan_tinggi', 'nama_dosen', 'password', 'nidn', 'nuptk', 'nip',
                'gelar_depan', 'gelar_belakang', 'jenis_kelamin', 'id_agama', 'tanggal_lahir',
                'id_status_aktif', 'nama_status_aktif', 'flag_feeder', 'id_registrasi_dosen',
                'id_fakultas', 'id_prodi', 'id_tahun_ajaran', 'nomor_surat_tugas',
                'tanggal_surat_tugas', 'mulai_surat_tugas',
            ] as $column) {
                $table->string($column)->nullable();
            }
        });
        $lecturers = [
            ['id_dosen' => 'lecturer-nidn', 'nidn' => 'NIDN-1', 'nip' => 'NIP-1', 'nuptk' => 'NUPTK-1'],
            ['id_dosen' => 'lecturer-nip', 'nidn' => '', 'nip' => 'NIP-2', 'nuptk' => 'NUPTK-2'],
            ['id_dosen' => 'lecturer-nuptk', 'nidn' => '', 'nip' => '', 'nuptk' => 'NUPTK-3'],
        ];
        Http::fake(function (ClientRequest $request) use ($lecturers) {
            if (($request['act'] ?? '') === 'GetToken') {
                return Http::response(['error_code' => '0', 'data' => ['token' => 'test-token']]);
            }

            return Http::response(['error_code' => '0', 'data' => ($request['act'] ?? '') === 'GetListDosen' ? $lecturers : []]);
        });
        $service = app(FeederInitializationService::class);
        $state = $this->jobState((string) Str::uuid(), 'lecturers');
        $state = $service->processBatch($state, app(LegacyFeederService::class));
        $state = $service->processBatch($state, app(LegacyFeederService::class));

        foreach ([
            'lecturer-nidn' => 'NIDN-1',
            'lecturer-nip' => 'NIP-2',
            'lecturer-nuptk' => 'NUPTK-3',
        ] as $id => $identifier) {
            $lecturer = DB::connection('simak_main')->table('s1m4k_masterdosen')
                ->where('id_dosen', $id)->first();
            $this->assertSame($identifier, $lecturer->nidn);
            $this->assertTrue(Hash::check($identifier, $lecturer->password));
        }
        $this->assertTrue($state['stage_complete']);
    }

    public function test_lecturer_stage_is_blocked_until_all_programs_have_faculty(): void
    {
        Schema::connection('simak_main')->create('s1m4k_masterprodi', function (Blueprint $table): void {
            $table->string('id_prodi')->primary();
            $table->string('id_fakultas')->nullable();
            $table->string('status')->nullable();
        });
        DB::connection('simak_main')->table('s1m4k_masterprodi')->insert([
            'id_prodi' => 'program-unmapped',
            'id_fakultas' => null,
            'status' => 'N',
        ]);
        $service = app(FeederInitializationService::class);

        try {
            $service->processBatch(
                $this->jobState((string) Str::uuid(), 'lecturers'),
                app(LegacyFeederService::class),
            );
            $this->fail('Tahap Dosen seharusnya diblokir sebelum request Feeder.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Program Studi yang belum memiliki Fakultas valid', $exception->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_academic_calendar_import_inserts_and_updates_by_semester_and_program(): void
    {
        $connection = DB::connection('simak_main');
        Schema::connection('simak_main')->create('s1m4k_masterprodi', function (Blueprint $table): void {
            $table->string('id_prodi')->primary();
            $table->string('id_perguruan_tinggi')->nullable();
            $table->string('id_fakultas')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_kalender', function (Blueprint $table): void {
            $table->string('id_kalender', 50)->primary();
            $table->string('periode', 20)->nullable();
            $table->string('tahun', 5)->nullable();
            $table->string('namatahun', 100)->nullable();
            $table->string('id_perguruan_tinggi', 50)->nullable();
            $table->string('id_fakultas', 50)->nullable();
            $table->string('id_prodi', 50)->nullable();
        });
        $connection->table('s1m4k_masterpt')->insert(['id_perguruan_tinggi' => 'pt-1']);
        $connection->table('s1m4k_masterfakultas')->insert([
            'id_fakultas' => 'faculty-1',
            'nama_fakultas' => 'Fakultas Uji',
        ]);
        $connection->table('s1m4k_masterprodi')->insert([
            'id_prodi' => 'program-1',
            'id_perguruan_tinggi' => 'pt-1',
            'id_fakultas' => 'faculty-1',
        ]);
        $connection->table('s1m4k_kalender')->insert([
            'id_kalender' => 'calendar-existing',
            'id_perguruan_tinggi' => 'pt-old',
            'id_fakultas' => 'faculty-old',
            'id_prodi' => 'program-1',
            'tahun' => '20261',
            'periode' => 'Semester Lama',
            'namatahun' => 'Tahun Akademik Semester Lama',
        ]);
        Http::fake(function (ClientRequest $request) {
            if (($request['act'] ?? '') === 'GetToken') {
                return Http::response(['error_code' => '0', 'data' => ['token' => 'test-token']]);
            }

            return Http::response(['error_code' => '0', 'data' => [[
                'id_prodi' => 'program-1',
                'id_semester' => '20261',
                'nama_semester' => '2026/2027 Ganjil',
            ], [
                'id_prodi' => 'program-1',
                'id_semester' => '20262',
                'nama_semester' => '2026/2027 Genap',
            ], [
                'id_prodi' => 'program-missing',
                'id_semester' => '20262',
                'nama_semester' => '2026/2027 Genap',
            ]]]);
        });

        $state = app(FeederInitializationService::class)
            ->processBatch($this->jobState((string) Str::uuid(), 'calendar'), app(LegacyFeederService::class));

        $this->assertTrue($state['stage_complete']);
        $this->assertSame(2, $connection->table('s1m4k_kalender')->count());
        $existing = $connection->table('s1m4k_kalender')->where('tahun', '20261')->first();
        $this->assertSame('calendar-existing', $existing->id_kalender);
        $this->assertSame('pt-1', $existing->id_perguruan_tinggi);
        $this->assertSame('faculty-1', $existing->id_fakultas);
        $this->assertSame('2026/2027 Ganjil', $existing->periode);
        $this->assertSame('Tahun Akademik 2026/2027 Ganjil', $existing->namatahun);
        $this->assertSame(1, $connection->table('s1m4k_kalender')->where('tahun', '20262')->count());
        $this->assertStringContainsString('1 dilewati', $state['last_batch']);
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'GetDetailPeriodePerkuliahan'
            && ($request['limit'] ?? null) === FeederInitializationService::BATCH_SIZE
            && ($request['offset'] ?? null) === 0);

        $state = app(FeederInitializationService::class)->nextStage($state);
        $this->assertSame('student_grades', $state['stage']);
        $this->assertSame('Nilai Mahasiswa', app(FeederInitializationService::class)->stageLabel($state));
    }

    public function test_student_grades_imports_all_rows_upserts_by_student_year_course_and_preserves_approvals(): void
    {
        $this->createStudentGradeSchema();
        $connection = DB::connection('simak_main');
        $connection->table('s1m4k_mastermhs')->insert([
            [
                'id_mahasiswa' => 'student-1', 'nim' => '20260001', 'id_status_mahasiswa' => 'A',
                'id_perguruan_tinggi' => 'pt-1', 'id_fakultas' => 'faculty-1', 'id_prodi' => 'program-1',
            ],
            [
                'id_mahasiswa' => 'student-2', 'nim' => '20260002', 'id_status_mahasiswa' => 'C',
                'id_perguruan_tinggi' => 'pt-1', 'id_fakultas' => 'faculty-1', 'id_prodi' => 'program-1',
            ],
            [
                'id_mahasiswa' => 'student-inactive', 'nim' => '20260003', 'id_status_mahasiswa' => 'G',
                'id_perguruan_tinggi' => 'pt-1', 'id_fakultas' => 'faculty-1', 'id_prodi' => 'program-1',
            ],
        ]);
        $connection->table('s1m4k_kalender')->insert([
            'id_kalender' => 'calendar-1', 'tahun' => '20261', 'id_prodi' => 'program-1',
        ]);
        $connection->table('s1m4k_jadwal')->insert([
            [
                'id_jadwal' => 'schedule-1', 'id_kelas_kuliah' => 'class-1',
                'id_kalender' => 'calendar-1', 'id_prodi' => 'program-1', 'id_matkul' => 'course-1',
            ],
            [
                'id_jadwal' => 'schedule-2', 'id_kelas_kuliah' => 'class-2',
                'id_kalender' => 'calendar-1', 'id_prodi' => 'program-1', 'id_matkul' => 'course-2',
            ],
        ]);
        $connection->table('s1m4k_krs')->insert([
            'id_krs' => 'krs-existing', 'id_perguruan_tinggi' => 'pt-1', 'id_fakultas' => 'faculty-1',
            'id_prodi' => 'program-1', 'id_kalender' => 'calendar-1', 'tahun' => '20261',
            'id_mahasiswa' => 'student-1', 'id_jadwal' => 'schedule-old', 'id_matkul' => 'course-1',
            'status_krs' => 'B', 'krs_mbkm' => 'N', 'isi_edom' => 'Y',
            'nilai_angka' => '10', 'nilai_huruf' => 'E', 'nilai_bobot' => '0', 'input_nilai' => 'N',
        ]);
        $connection->table('s1m4k_pengesahankrs')->insert([
            'id_pengesahan' => 'approval-existing', 'id_mahasiswa' => 'student-1',
            'id_kalender' => 'calendar-1', 'yang_mengesahkan' => 'Ketua Lama',
            'nama_kpro' => 'Ketua Lama', 'tgl_pengesahan' => '2026-01-01',
            'tgl_pengesahan_kpro' => '2026-01-01',
        ]);

        Http::fake(function (ClientRequest $request) {
            if (($request['act'] ?? '') === 'GetToken') {
                return Http::response(['error_code' => '0', 'data' => ['token' => 'test-token']]);
            }
            if (($request['act'] ?? '') !== 'GetDetailNilaiPerkuliahanKelas') {
                return Http::response(['error_code' => '0', 'data' => []]);
            }

            $nim = str_contains((string) ($request['filter'] ?? ''), '20260001')
                ? '20260001'
                : '20260002';
            $grades = $nim === '20260001'
                ? [
                    [
                        'id_semester' => '20261',
                        'id_kelas_kuliah' => 'class-1',
                        'id_mata_kuliah' => 'course-1',
                        'nama_kelas_kuliah' => 'Kelas Feeder A',
                        'nilai_angka' => 88,
                        'nilai_huruf' => 'A', 'nilai_indeks' => 4,
                    ],
                    [
                        'id_semester' => '20261',
                        'id_kelas_kuliah' => 'class-2',
                        'id_mata_kuliah' => 'course-2',
                        'nilai_angka' => 77,
                        'nilai_huruf' => 'B', 'nilai_indeks' => 3,
                    ],
                    [
                        'id_semester' => '20261', 'id_kelas_kuliah' => 'class-missing',
                        'id_mata_kuliah' => 'course-3', 'nilai_angka' => 55,
                        'nilai_huruf' => 'C', 'nilai_indeks' => 2,
                    ],
                ]
                : [[
                    'id_semester' => '20261',
                    'id_kelas_kuliah' => 'class-1',
                    'id_mata_kuliah' => 'course-1',
                    'nilai_angka' => 65,
                    'nilai_huruf' => 'C', 'nilai_indeks' => 2,
                ]];

            return Http::response(['error_code' => '0', 'data' => $grades]);
        });

        $service = app(FeederInitializationService::class);
        $state = $this->jobState((string) Str::uuid(), 'student_grades');
        $state = $service->processBatch($state, app(LegacyFeederService::class));
        $this->assertTrue($state['stage_complete']);
        $this->assertSame(1, $connection->table('s1m4k_krs')->where('id_mahasiswa', 'student-1')
            ->where('id_matkul', 'course-1')->count());
        $this->assertSame('88', $connection->table('s1m4k_krs')
            ->where('id_krs', 'krs-existing')->value('nilai_angka'));
        $this->assertSame('class-1', $connection->table('s1m4k_krs')
            ->where('id_krs', 'krs-existing')->value('id_jadwal'));
        $this->assertSame('Y', $connection->table('s1m4k_krs')
            ->where('id_krs', 'krs-existing')->value('input_nilai'));
        $this->assertSame(2, $connection->table('s1m4k_pengesahankrs')->count());
        $this->assertSame('Ketua Lama', $connection->table('s1m4k_pengesahankrs')
            ->where('id_pengesahan', 'approval-existing')->value('nama_kpro'));

        $this->assertSame(4, $connection->table('s1m4k_krs')->count());
        $this->assertSame(3, $connection->table('s1m4k_jadwal')->whereIn('id_jadwal', [
            'class-1', 'class-2', 'class-missing',
        ])->count());
        $this->assertSame('program-1', $connection->table('s1m4k_jadwal')
            ->where('id_jadwal', 'class-1')->value('id_prodi'));
        $this->assertSame('course-1', $connection->table('s1m4k_jadwal')
            ->where('id_jadwal', 'class-1')->value('id_matkul'));
        $this->assertSame('Kelas Feeder A', $connection->table('s1m4k_jadwal')
            ->where('id_jadwal', 'class-1')->value('nama_kelas'));
        $this->assertStringContainsString('3 jadwal feeder dibuat', $state['last_batch']);
        $this->assertSame('class-missing', $connection->table('s1m4k_krs')
            ->where('id_mahasiswa', 'student-1')->where('id_matkul', 'course-3')->value('id_jadwal'));
        $this->assertSame('B', $connection->table('s1m4k_krs')
            ->where('id_mahasiswa', 'student-1')->where('id_matkul', 'course-2')->value('status_krs'));
        $this->assertSame('N', $connection->table('s1m4k_krs')
            ->where('id_mahasiswa', 'student-1')->where('id_matkul', 'course-2')->value('krs_mbkm'));
        $this->assertSame('Y', $connection->table('s1m4k_krs')
            ->where('id_mahasiswa', 'student-1')->where('id_matkul', 'course-2')->value('isi_edom'));
        $this->assertSame(2, $connection->table('s1m4k_pengesahankrs')->count());
        $this->assertSame('Admin', $connection->table('s1m4k_pengesahankrs')
            ->where('id_mahasiswa', 'student-2')->value('yang_mengesahkan'));
        $this->assertStringContainsString('0 baris nilai dilewati', $state['last_batch']);
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'GetDetailNilaiPerkuliahanKelas'
            && ($request['filter'] ?? '') === "nim='20260001'"
            && ($request['limit'] ?? null) === FeederInitializationService::BATCH_SIZE);
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'GetDetailNilaiPerkuliahanKelas'
            && ($request['filter'] ?? '') === "nim='20260002'"
            && ($request['limit'] ?? null) === FeederInitializationService::BATCH_SIZE);

        $state = $service->restartCurrentStage($state);
        $state = $service->processBatch($state, app(LegacyFeederService::class));
        $this->assertTrue($state['stage_complete']);
        $this->assertSame(4, $connection->table('s1m4k_krs')->count());
        $this->assertSame(3, $connection->table('s1m4k_jadwal')->whereIn('id_jadwal', [
            'class-1', 'class-2', 'class-missing',
        ])->count());
        $this->assertSame(2, $connection->table('s1m4k_pengesahankrs')->count());
        $this->assertSame('student-2', $state['grade_student_cursor']);
    }

    public function test_student_grades_imports_using_feeder_class_id_without_a_local_schedule(): void
    {
        $this->createStudentGradeSchema();
        $connection = DB::connection('simak_main');
        $connection->table('s1m4k_mastermhs')->insert([
            'id_mahasiswa' => 'student-1', 'nim' => '20260001', 'id_status_mahasiswa' => 'A',
        ]);
        Http::fake(function (ClientRequest $request) {
            if (($request['act'] ?? '') === 'GetToken') {
                return Http::response(['error_code' => '0', 'data' => ['token' => 'test-token']]);
            }
            if (($request['act'] ?? '') === 'GetDetailNilaiPerkuliahanKelas') {
                return Http::response([
                    'error_code' => '0',
                    'data' => [[
                        'id_kelas_kuliah' => 'feeder-class-1',
                    ]],
                ]);
            }

            return Http::response(['error_code' => '0', 'data' => []]);
        });

        $state = app(FeederInitializationService::class)->processBatch(
            $this->jobState((string) Str::uuid(), 'student_grades'),
            app(LegacyFeederService::class),
        );

        $this->assertTrue($state['stage_complete']);
        $this->assertSame(1, $connection->table('s1m4k_jadwal')->count());
        $this->assertSame('feeder-class-1', $connection->table('s1m4k_jadwal')
            ->where('id_jadwal', 'feeder-class-1')->value('id_kelas_kuliah'));
        $this->assertSame('Feeder', $connection->table('s1m4k_jadwal')
            ->where('id_jadwal', 'feeder-class-1')->value('nama_kelas'));
        $this->assertSame('feeder-class-1', $connection->table('s1m4k_krs')
            ->where('id_mahasiswa', 'student-1')->value('id_jadwal'));
        $this->assertNull($connection->table('s1m4k_krs')
            ->where('id_mahasiswa', 'student-1')->value('id_perguruan_tinggi'));
        $this->assertNull($connection->table('s1m4k_krs')
            ->where('id_mahasiswa', 'student-1')->value('id_kalender'));
        $this->assertNull($connection->table('s1m4k_krs')
            ->where('id_mahasiswa', 'student-1')->value('id_matkul'));
        $this->assertNull($connection->table('s1m4k_krs')
            ->where('id_mahasiswa', 'student-1')->value('nilai_angka'));
        $this->assertSame(0, $connection->table('s1m4k_pengesahankrs')->count());
        $this->assertStringContainsString('1 KRS ditambahkan', $state['last_batch']);
    }

    public function test_student_grades_fetches_subsequent_pages_for_students_with_more_than_one_hundred_grades(): void
    {
        $this->createStudentGradeSchema();
        $connection = DB::connection('simak_main');
        $connection->table('s1m4k_mastermhs')->insert([
            'id_mahasiswa' => 'student-1', 'nim' => '20260001', 'id_status_mahasiswa' => 'A',
            'id_perguruan_tinggi' => 'pt-1', 'id_fakultas' => 'faculty-1', 'id_prodi' => 'program-1',
        ]);
        $connection->table('s1m4k_kalender')->insert([
            'id_kalender' => 'calendar-1', 'tahun' => '20261', 'id_prodi' => 'program-1',
        ]);

        $grades = [];
        $schedules = [];
        for ($index = 0; $index < 101; $index++) {
            $grades[] = [
                'id_semester' => '20261',
                'id_kelas_kuliah' => 'class-'.$index,
                'id_mata_kuliah' => 'course-'.$index,
                'nilai_angka' => 80,
                'nilai_huruf' => 'A',
                'nilai_indeks' => 4,
            ];
            $schedules[] = [
                'id_jadwal' => 'schedule-'.$index,
                'id_kelas_kuliah' => 'class-'.$index,
                'id_kalender' => 'calendar-1',
                'id_prodi' => 'program-1',
                'id_matkul' => 'course-'.$index,
            ];
        }
        $connection->table('s1m4k_jadwal')->insert($schedules);

        Http::fake(function (ClientRequest $request) use ($grades) {
            if (($request['act'] ?? '') === 'GetToken') {
                return Http::response(['error_code' => '0', 'data' => ['token' => 'test-token']]);
            }
            if (($request['act'] ?? '') !== 'GetDetailNilaiPerkuliahanKelas') {
                return Http::response(['error_code' => '0', 'data' => []]);
            }

            $offset = (int) ($request['offset'] ?? 0);

            return Http::response([
                'error_code' => '0',
                'data' => array_slice($grades, $offset, FeederInitializationService::BATCH_SIZE),
            ]);
        });

        $service = app(FeederInitializationService::class);
        $state = $this->jobState((string) Str::uuid(), 'student_grades');
        $state = $service->processBatch($state, app(LegacyFeederService::class));
        $this->assertTrue($state['stage_complete']);
        $this->assertSame(101, $connection->table('s1m4k_krs')->count());
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'GetDetailNilaiPerkuliahanKelas'
            && ($request['offset'] ?? null) === 0
            && ($request['limit'] ?? null) === FeederInitializationService::BATCH_SIZE);
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'GetDetailNilaiPerkuliahanKelas'
            && ($request['offset'] ?? null) === 100
            && ($request['limit'] ?? null) === FeederInitializationService::BATCH_SIZE);
        $this->assertSame('student-1', $state['grade_student_cursor']);
    }

    public function test_terminal_student_grades_fetches_rows_in_paged_batches_per_student(): void
    {
        $this->createStudentGradeSchema();
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            'id_mahasiswa' => 'student-1',
            'nim' => '20260001',
            'id_status_mahasiswa' => 'A',
        ]);
        $grades = array_fill(0, 101, ['not_a_grade' => true]);
        Http::fake(function (ClientRequest $request) use ($grades) {
            if (($request['act'] ?? '') === 'GetToken') {
                return Http::response(['error_code' => '0', 'data' => ['token' => 'test-token']]);
            }

            return Http::response(['error_code' => '0', 'data' => array_slice(
                $grades,
                (int) ($request['offset'] ?? 0),
                (int) ($request['limit'] ?? 0),
            )]);
        });

        $state = app(FeederInitializationService::class)->newTerminalState(
            'http://localhost:8082/ws',
            'feeder-user',
            'feeder-secret',
            'test-token',
        );
        $state['stage'] = 'student_grades';
        $state['stage_complete'] = false;
        $state = app(FeederInitializationService::class)
            ->processBatch($state, app(LegacyFeederService::class));

        $this->assertTrue($state['stage_complete']);
        $this->assertSame(0, $state['student_grades_skipped']);
        $this->assertSame(101, DB::connection('simak_main')->table('s1m4k_krs')->count());
        Http::assertSentCount(2);
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'GetDetailNilaiPerkuliahanKelas'
            && ($request['limit'] ?? null) === FeederInitializationService::BATCH_SIZE
            && ($request['offset'] ?? null) === 0);
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'GetDetailNilaiPerkuliahanKelas'
            && ($request['limit'] ?? null) === FeederInitializationService::BATCH_SIZE
            && ($request['offset'] ?? null) === 100);
    }

    public function test_student_grade_batches_continue_using_a_primary_key_cursor(): void
    {
        $this->createStudentGradeSchema();
        $connection = DB::connection('simak_main');
        $connection->table('s1m4k_mastermhs')->insert([
            ['id_mahasiswa' => 'student-1', 'nim' => '20260001', 'id_status_mahasiswa' => 'A'],
            ['id_mahasiswa' => 'student-2', 'nim' => '20260002', 'id_status_mahasiswa' => 'A'],
            ['id_mahasiswa' => 'student-3', 'nim' => '20260003', 'id_status_mahasiswa' => 'A'],
            ['id_mahasiswa' => 'student-4', 'nim' => '20260004', 'id_status_mahasiswa' => 'A'],
            ['id_mahasiswa' => 'student-5', 'nim' => '20260005', 'id_status_mahasiswa' => 'A'],
        ]);
        Http::fake(function (ClientRequest $request) {
            if (($request['act'] ?? '') === 'GetToken') {
                return Http::response(['error_code' => '0', 'data' => ['token' => 'test-token']]);
            }

            return Http::response(['error_code' => '0', 'data' => []]);
        });

        $service = app(FeederInitializationService::class);
        $state = $this->jobState((string) Str::uuid(), 'student_grades');
        $state = $service->processBatch($state, app(LegacyFeederService::class));

        $this->assertFalse($state['stage_complete']);
        $this->assertSame(4, $state['grade_student_offset']);
        $this->assertSame('student-4', $state['grade_student_cursor']);
        $this->assertArrayHasKey('batch_duration_ms', $state);

        $state = $service->processBatch($state, app(LegacyFeederService::class));
        $this->assertTrue($state['stage_complete']);
        $this->assertSame(5, $state['grade_student_offset']);
        $this->assertSame('student-5', $state['grade_student_cursor']);
    }

    public function test_failed_profile_import_does_not_persist_feeder_configuration(): void
    {
        $this->actingAs($this->admin());
        $jobId = (string) Str::uuid();
        $this->withSession(['feeder_initialization_job' => $jobId]);
        Cache::put('feeder-initialization:'.$jobId, $this->jobState($jobId, 'university'));
        Http::fake(function (ClientRequest $request) {
            if (($request['act'] ?? '') === 'GetToken') {
                return Http::response(['error_code' => '0', 'data' => ['token' => 'test-token']]);
            }

            return Http::response(['error_code' => '9', 'error_desc' => 'Profil tidak tersedia', 'data' => []]);
        });

        $this->postJson(route('feeder-initialization.step'))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Feeder gagal (9): Profil tidak tersedia');
        $this->assertSame(0, DB::connection('simak_main')->table('s1m4k_masterpt')->count());
    }

    public function test_initialization_request_refreshes_an_expired_temporary_token_once(): void
    {
        Http::fakeSequence()
            ->push(['error_code' => '1', 'error_desc' => 'Token tidak valid', 'data' => []])
            ->push(['error_code' => '0', 'data' => ['token' => 'fresh-token']])
            ->push(['error_code' => '0', 'data' => [['id' => 'row-1']]]);

        $result = app(LegacyFeederService::class)->initializationRequest(
            'http://localhost:8082/ws',
            'feeder-user',
            'feeder-secret',
            'expired-token',
            'GetPembiayaan',
            ['filter' => '', 'order' => '', 'limit' => 100, 'offset' => 0],
        );

        $this->assertSame('fresh-token', $result['token']);
        $this->assertSame([['id' => 'row-1']], $result['rows']);
        Http::assertSentCount(3);
    }

    public function test_next_after_profile_import_requires_faculty_data(): void
    {
        $this->actingAs($this->admin());
        $jobId = (string) Str::uuid();
        $this->withSession(['feeder_initialization_job' => $jobId]);
        $state = $this->jobState($jobId, 'university');
        $state['stage_complete'] = true;
        Cache::put('feeder-initialization:'.$jobId, $state);

        $this->postJson(route('feeder-initialization.next'))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Data Fakultas belum tersedia. Lengkapi melalui modul Master Fakultas sebelum melanjutkan.');

        DB::connection('simak_main')->table('s1m4k_masterfakultas')->insert([
            'id_fakultas' => 'faculty-1',
            'nama_fakultas' => 'Fakultas Uji',
        ]);
        $this->postJson(route('feeder-initialization.next'))
            ->assertOk()
            ->assertJsonPath('stage', 'programs')
            ->assertJsonPath('ready_for_next', false);
    }

    public function test_next_after_program_import_requires_faculty_for_every_program(): void
    {
        $this->actingAs($this->admin());
        $jobId = (string) Str::uuid();
        $this->withSession(['feeder_initialization_job' => $jobId]);
        Schema::connection('simak_main')->create('s1m4k_masterprodi', function (Blueprint $table): void {
            $table->string('id_prodi')->primary();
            $table->string('id_fakultas')->nullable();
            $table->string('status')->nullable();
        });
        DB::connection('simak_main')->table('s1m4k_masterprodi')->insert([
            ['id_prodi' => 'active-1', 'id_fakultas' => null, 'status' => 'A'],
            ['id_prodi' => 'inactive-1', 'id_fakultas' => null, 'status' => 'N'],
        ]);
        DB::connection('simak_main')->table('s1m4k_masterfakultas')->insert([
            'id_fakultas' => 'faculty-1',
            'nama_fakultas' => 'Fakultas Uji',
        ]);
        $state = $this->jobState($jobId, 'programs');
        $state['stage_complete'] = true;
        Cache::put('feeder-initialization:'.$jobId, $state);

        $this->postJson(route('feeder-initialization.next'))
            ->assertUnprocessable()
            ->assertJsonPath('unassigned_programs', 2)
            ->assertJsonPath('ready_for_next', false);

        DB::connection('simak_main')->table('s1m4k_masterprodi')
            ->where('id_prodi', 'active-1')->update(['id_fakultas' => 'unknown-faculty']);
        $this->postJson(route('feeder-initialization.next'))
            ->assertUnprocessable()
            ->assertJsonPath('unassigned_programs', 2);
        DB::connection('simak_main')->table('s1m4k_masterprodi')
            ->where('id_prodi', 'active-1')->update(['id_fakultas' => 'faculty-1']);
        $this->postJson(route('feeder-initialization.next'))
            ->assertUnprocessable()
            ->assertJsonPath('unassigned_programs', 1);
        DB::connection('simak_main')->table('s1m4k_masterprodi')
            ->where('id_prodi', 'inactive-1')->update(['id_fakultas' => 'faculty-1']);
        $this->postJson(route('feeder-initialization.next'))
            ->assertOk()
            ->assertJsonPath('stage', 'lecturers');
    }

    public function test_terminal_command_does_not_import_programs_without_faculty_data(): void
    {
        $state = $this->jobState((string) Str::uuid(), 'programs');
        Cache::put('feeder-initialization:terminal-job', $state);

        $this->artisan('siakad:initialize-feeder', ['--resume' => true, '--yes' => true])
            ->expectsOutputToContain('Data Fakultas belum tersedia')
            ->assertExitCode(1);

        Http::assertNothingSent();
        $this->assertSame('programs', Cache::get('feeder-initialization:terminal-job')['stage']);
    }

    public function test_terminal_command_can_restart_the_current_checkpoint_stage(): void
    {
        $state = $this->jobState((string) Str::uuid(), 'programs');
        $state['offset'] = 250;
        $state['stage_complete'] = true;
        Cache::put('feeder-initialization:terminal-job', $state);

        $this->artisan('siakad:initialize-feeder', [
            '--resume' => true,
            '--restart-current-stage' => true,
            '--yes' => true,
        ])
            ->expectsOutputToContain('Mengulang tahap Program Studi dari awal.')
            ->expectsOutputToContain('Data Fakultas belum tersedia')
            ->assertExitCode(1);

        $savedState = Cache::get('feeder-initialization:terminal-job');
        $this->assertSame(0, $savedState['offset']);
        $this->assertFalse($savedState['stage_complete'] ?? false);
        Http::assertNothingSent();
    }

    public function test_terminal_command_prompts_for_all_feeder_credentials_until_they_are_stored(): void
    {
        DB::connection('simak_main')->table('s1m4k_masterpt')->insert([
            'id_perguruan_tinggi' => 'university-1',
            'alamat_feeder' => 'https://partial.example.test/ws',
            'user_feeder' => 'partial-user',
        ]);

        $previousPassword = getenv('FEEDER_PASSWORD');
        putenv('FEEDER_PASSWORD=');
        unset($_ENV['FEEDER_PASSWORD'], $_SERVER['FEEDER_PASSWORD']);
        Http::fake(fn () => Http::response(['error_code' => '0', 'data' => []]));

        try {
            $this->artisan('siakad:initialize-feeder')
                ->expectsQuestion('URL WebService Feeder', 'https://feeder.example.test/ws')
                ->expectsQuestion('Username Feeder', 'feeder-user')
                ->expectsQuestion('Password Feeder', 'feeder-secret')
                ->expectsConfirmation(
                    'Proses ini akan mengimpor dan memperbarui data SIMAK dari Feeder sampai semua tahapan selesai. Pastikan database terisolasi/backup tersedia dan Fakultas sudah diisi.',
                    'yes',
                )
                ->expectsOutputToContain('Menguji koneksi Feeder...')
                ->expectsOutputToContain('Feeder tidak memberikan token akses.')
                ->assertExitCode(1);
        } finally {
            if ($previousPassword === false) {
                putenv('FEEDER_PASSWORD');
            } else {
                putenv('FEEDER_PASSWORD='.$previousPassword);
            }
        }

        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'GetToken'
            && ($request['username'] ?? '') === 'feeder-user'
            && ($request['password'] ?? '') === 'feeder-secret'
            && $request->url() === 'https://feeder.example.test/ws');
        $this->assertFalse(Cache::has('feeder-initialization:terminal-job'));
    }

    public function test_stored_terminal_credentials_are_used_only_when_all_three_values_exist(): void
    {
        $feeder = app(LegacyFeederService::class);
        $connection = DB::connection('simak_main');
        $connection->table('s1m4k_masterpt')->insert([
            'id_perguruan_tinggi' => 'university-1',
            'alamat_feeder' => 'https://feeder.example.test/ws',
            'user_feeder' => 'feeder-user',
        ]);

        $this->assertNull($feeder->storedInitializationCredentials());

        $connection->table('s1m4k_masterpt')
            ->where('id_perguruan_tinggi', 'university-1')
            ->update(['password_feeder' => $this->legacyEncrypt('feeder-secret')]);

        $this->assertSame([
            'url' => 'https://feeder.example.test/ws',
            'username' => 'feeder-user',
            'password' => 'feeder-secret',
        ], $feeder->storedInitializationCredentials());
    }

    public function test_terminal_command_does_not_start_lecturers_until_every_program_has_faculty(): void
    {
        Schema::connection('simak_main')->create('s1m4k_masterprodi', function (Blueprint $table): void {
            $table->string('id_prodi')->primary();
            $table->string('id_fakultas')->nullable();
            $table->string('status')->nullable();
        });
        DB::connection('simak_main')->table('s1m4k_masterprodi')->insert([
            'id_prodi' => 'inactive-1',
            'id_fakultas' => null,
            'status' => 'N',
        ]);
        $state = $this->jobState((string) Str::uuid(), 'lecturers');
        $state['terminal_full_fetch'] = true;
        Cache::put('feeder-initialization:terminal-job', $state);

        $this->artisan('siakad:initialize-feeder', ['--resume' => true, '--yes' => true])
            ->expectsOutputToContain('Program Studi yang belum memiliki Fakultas valid')
            ->assertExitCode(1);

        Http::assertNothingSent();
        $savedState = Cache::get('feeder-initialization:terminal-job');
        $this->assertSame('lecturers', $savedState['stage']);
        $this->assertTrue($savedState['terminal_mode']);
        $this->assertArrayNotHasKey('terminal_full_fetch', $savedState);
    }

    public function test_terminal_command_advances_completed_stages_without_ui_interaction(): void
    {
        $state = $this->jobState((string) Str::uuid(), 'student_grades');
        $state['stage_complete'] = true;
        Cache::put('feeder-initialization:terminal-job', $state);
        $staleCacheLock = Cache::lock('feeder-initialization:terminal-lock', 3600);
        $this->assertTrue($staleCacheLock->get());

        try {
            $this->artisan('siakad:initialize-feeder', ['--resume' => true, '--yes' => true])
                ->expectsOutputToContain('Inisialisasi seluruh data Feeder selesai.')
                ->assertExitCode(0);
        } finally {
            $staleCacheLock->release();
        }

        Http::assertNothingSent();
        $this->assertNull(Cache::get('feeder-initialization:terminal-job'));
    }

    public function test_completed_batch_can_be_reimported_without_advancing(): void
    {
        $state = $this->jobState((string) Str::uuid(), 'university');
        $state['token_encrypted'] = Crypt::encryptString('test-token');
        $service = app(FeederInitializationService::class);
        Http::fake(fn () => Http::response(['error_code' => '0', 'data' => [[
            'id_perguruan_tinggi' => 'pt-1',
            'nama_perguruan_tinggi' => 'Perguruan Tinggi Uji',
        ]]]));

        $state = $service->processBatch($state, app(LegacyFeederService::class));
        $this->assertSame('university', $state['stage']);
        $this->assertTrue($state['stage_complete']);

        $state = $service->restartCurrentStage($state);
        $state = $service->processBatch($state, app(LegacyFeederService::class));
        $this->assertSame('university', $state['stage']);
        $this->assertTrue($state['stage_complete']);
        $this->assertSame(2, $state['records']);
    }

    public function test_completed_stage_can_be_reopened_and_restarted_without_losing_completed_tabs(): void
    {
        $service = app(FeederInitializationService::class);
        $state = $this->jobState((string) Str::uuid(), 'lecturers');
        $state['completed_stages'] = ['connection', 'references', 'university', 'programs'];
        $state['offset'] = 250;
        $state['stage_records'] = 250;
        $state['stage_complete'] = true;

        $state = $service->activateStage($state, 'programs');
        $this->assertSame('programs', $state['stage']);
        $this->assertTrue($state['stage_complete']);
        $this->assertSame(250, $state['offset']);
        $this->assertContains('lecturers', $service->completedStages($state));

        $state = $service->restartCurrentStage($state);
        $this->assertSame('programs', $state['stage']);
        $this->assertFalse($state['stage_complete'] ?? false);
        $this->assertSame(0, $state['offset']);

        $this->expectException(\RuntimeException::class);
        $service->activateStage($state, 'students');
    }

    public function test_admin_can_navigate_back_to_completed_wizard_stage(): void
    {
        $this->actingAs($this->admin());
        $jobId = (string) Str::uuid();
        $this->withSession(['feeder_initialization_job' => $jobId]);
        $state = $this->jobState($jobId, 'lecturers');
        $state['completed_stages'] = ['connection', 'references', 'university', 'programs'];
        Cache::put('feeder-initialization:'.$jobId, $state);

        $this->postJson(route('feeder-initialization.stage'), ['stage' => 'university'])
            ->assertOk()
            ->assertJsonPath('stage', 'university')
            ->assertJsonPath('stage_complete', true)
            ->assertJsonPath('completed_stages.3', 'programs');
    }

    public function test_completed_legacy_wizard_is_migrated_to_the_new_student_grades_stage(): void
    {
        $this->actingAs($this->admin());
        $jobId = (string) Str::uuid();
        $this->withSession(['feeder_initialization_job' => $jobId]);
        $state = $this->jobState($jobId, 'complete');
        $state['wizard_version'] = 2;
        $state['stage_complete'] = true;
        $state['completed_stages'] = [
            'connection', 'references', 'university', 'programs', 'lecturers',
            'students', 'courses', 'grades', 'universities', 'all_programs', 'calendar', 'complete',
        ];
        Cache::put('feeder-initialization:'.$jobId, $state);

        $this->get(route('feeder-initialization.index'))
            ->assertOk()
            ->assertSee('Nilai Mahasiswa');
        $this->getJson(route('feeder-initialization.status'))
            ->assertOk()
            ->assertJsonPath('stage', 'student_grades')
            ->assertJsonPath('stage_complete', false)
            ->assertJsonPath('completed_stages.10', 'calendar');

        $upgraded = Cache::get('feeder-initialization:'.$jobId);
        $this->assertSame(3, $upgraded['wizard_version']);
        $this->assertNotContains('student_grades', $upgraded['completed_stages']);
    }

    public function test_all_programs_stage_completes_against_composite_primary_key(): void
    {
        Schema::connection('simak_main')->create('s1m4k_masterallpt', function (Blueprint $table): void {
            $table->string('id_perguruan_tinggi')->primary();
            $table->string('kode_perguruan_tinggi')->nullable();
            $table->string('nama_perguruan_tinggi')->nullable();
            $table->string('nama_singkat')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterallprodi', function (Blueprint $table): void {
            $table->string('id_perguruan_tinggi');
            $table->string('id_prodi');
            $table->string('kode_perguruan_tinggi')->nullable();
            $table->string('kode_program_studi')->nullable();
            $table->string('nama_program_studi')->nullable();
            $table->string('status')->nullable();
            $table->string('id_jenjang_pendidikan')->nullable();
            $table->primary(['id_perguruan_tinggi', 'id_prodi']);
        });
        DB::connection('simak_main')->table('s1m4k_masterallpt')->insert([
            'id_perguruan_tinggi' => 'pt-1',
            'kode_perguruan_tinggi' => '001',
        ]);
        Http::fake(fn () => Http::response(['error_code' => '0', 'data' => []]));
        $state = $this->jobState((string) Str::uuid(), 'all_programs');
        $state['token_encrypted'] = Crypt::encryptString('test-token');

        $service = app(FeederInitializationService::class);
        $state = $service->processBatch($state, app(LegacyFeederService::class));
        $this->assertSame(1, $state['institution_index']);
        $state = $service->processBatch($state, app(LegacyFeederService::class));
        $this->assertSame('all_programs', $state['stage']);
        $this->assertTrue($state['stage_complete']);
        $state = $service->nextStage($state);
        $this->assertSame('calendar', $state['stage']);
    }

    public function test_all_programs_import_can_store_same_program_id_for_multiple_universities(): void
    {
        Schema::connection('simak_main')->create('s1m4k_masterallpt', function (Blueprint $table): void {
            $table->string('id_perguruan_tinggi')->primary();
            $table->string('kode_perguruan_tinggi')->nullable();
            $table->string('nama_perguruan_tinggi')->nullable();
            $table->string('nama_singkat')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterallprodi', function (Blueprint $table): void {
            $table->string('id_perguruan_tinggi');
            $table->string('id_prodi');
            $table->string('kode_perguruan_tinggi')->nullable();
            $table->string('kode_program_studi')->nullable();
            $table->string('nama_program_studi')->nullable();
            $table->string('status')->nullable();
            $table->string('id_jenjang_pendidikan')->nullable();
            $table->primary(['id_perguruan_tinggi', 'id_prodi']);
        });
        DB::connection('simak_main')->table('s1m4k_masterallpt')->insert([
            ['id_perguruan_tinggi' => 'pt-1', 'kode_perguruan_tinggi' => '001'],
            ['id_perguruan_tinggi' => 'pt-2', 'kode_perguruan_tinggi' => '002'],
        ]);
        Http::fake(function (ClientRequest $request) {
            $code = str_contains((string) ($request['filter'] ?? ''), "'002'") ? '002' : '001';

            return Http::response(['error_code' => '0', 'data' => [[
                'id_perguruan_tinggi' => 'pt-'.$code,
                'kode_perguruan_tinggi' => $code,
                'id_prodi' => 'program-shared',
                'kode_program_studi' => '101',
                'nama_program_studi' => 'Program '.$code,
                'status' => 'A',
                'id_jenjang_pendidikan' => '3',
            ]]]);
        });
        $state = $this->jobState((string) Str::uuid(), 'all_programs');
        $state['token_encrypted'] = Crypt::encryptString('test-token');
        $service = app(FeederInitializationService::class);

        $state = $service->processBatch($state, app(LegacyFeederService::class));
        $state = $service->processBatch($state, app(LegacyFeederService::class));
        $state = $service->processBatch($state, app(LegacyFeederService::class));

        $this->assertSame(2, DB::connection('simak_main')->table('s1m4k_masterallprodi')->count());
        $this->assertSame(['pt-001', 'pt-002'], DB::connection('simak_main')->table('s1m4k_masterallprodi')
            ->orderBy('id_perguruan_tinggi')->pluck('id_perguruan_tinggi')->all());
        $this->assertSame(2, $state['institution_index']);
        $this->assertTrue($state['stage_complete']);
        $state = $service->nextStage($state);
        $this->assertSame('calendar', $state['stage']);
    }

    private function jobState(string $jobId, string $stage): array
    {
        return [
            'job_id' => $jobId,
            'owner_id' => 'admin-1',
            'wizard_version' => 3,
            'stage' => $stage,
            'reference_index' => 0,
            'offset' => 0,
            'stage_records' => 0,
            'records' => 0,
            'progress' => 0,
            'stage_complete' => false,
            'url_encrypted' => Crypt::encryptString('http://localhost:8082/ws'),
            'username_encrypted' => Crypt::encryptString('feeder-user'),
            'password_encrypted' => Crypt::encryptString('feeder-secret'),
            'token_encrypted' => null,
            'last_batch' => 'Batch siap.',
        ];
    }

    private function admin(): GenericUser
    {
        return new GenericUser([
            'id' => 'admin-1', 'role' => 'admin', 'name' => 'Admin SIMAK',
        ]);
    }

    private function legacyEncrypt(string $value): string
    {
        $key = '979a218e0632df2935317f98d47956c7';
        $encrypted = '';
        for ($index = 0, $length = strlen($value); $index < $length; $index++) {
            $keyCharacter = substr($key, ($index % strlen($key)) - 1, 1);
            $encrypted .= chr((ord($value[$index]) + ord($keyCharacter)) % 256);
        }

        return urlencode(base64_encode($encrypted));
    }
}
