<?php

namespace Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FeederExportTest extends TestCase
{
    private bool $failFirstParticipantExport = false;

    private array $lecturerAssignmentData = [
        ['id_dosen' => 'lecturer-1', 'id_registrasi_dosen' => 'lecturer-registration-1'],
    ];

    private array $lecturerInsertResponse = ['error_code' => '0', 'data' => []];

    private array $feederResponseOverrides = [];

    private array $feederResponseSequences = [];

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
            $table->string('alamat_feeder')->nullable();
            $table->string('user_feeder')->nullable();
            $table->string('password_feeder')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterprodi', function (Blueprint $table): void {
            $table->string('id_prodi')->primary();
            $table->string('nama_program_studi')->nullable();
            $table->string('nama_jenjang_pendidikan')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_kalender', function (Blueprint $table): void {
            $table->string('id_kalender')->primary();
            $table->string('id_prodi');
            $table->string('tahun');
            $table->string('periode')->nullable();
            $table->string('namatahun')->nullable();
            $table->string('status')->nullable();
            $table->date('tgl_mulai_krs')->nullable();
            $table->date('tgl_akhir_isinilai')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_kurikulum', function (Blueprint $table): void {
            $table->string('id_kurikulum')->primary();
            $table->string('nama_kurikulum')->nullable();
            $table->string('id_prodi')->nullable();
            $table->string('id_semester')->nullable();
            $table->string('semester_mulai_berlaku')->nullable();
            $table->string('jumlah_sks_lulus')->nullable();
            $table->string('jumlah_sks_wajib')->nullable();
            $table->string('jumlah_sks_pilihan')->nullable();
            $table->string('jumlah_sks_mata_kuliah_wajib')->nullable();
            $table->string('jumlah_sks_mata_kuliah_pilihan')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_export', function (Blueprint $table): void {
            $table->string('id_export')->primary();
            $table->string('id_prodi')->nullable();
            $table->string('id_kurikulum')->nullable();
            $table->string('id_kalender')->nullable();
            $table->string('id_semester')->nullable();
            $table->string('id_data')->nullable();
            $table->string('data')->nullable();
            $table->string('status');
            $table->string('waktu')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_export_detail', function (Blueprint $table): void {
            $table->string('id_export_detail')->primary();
            $table->string('id_export');
            $table->string('source_id');
            $table->string('status');
            $table->string('payload_hash')->nullable();
            $table->text('payload_json')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_desc')->nullable();
            $table->integer('attempts')->default(0);
            $table->dateTime('last_attempt_at')->nullable();
            $table->dateTime('succeeded_at')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->unique(['id_export', 'source_id']);
        });
        Schema::connection('simak_main')->create('s1m4k_jadwal', function (Blueprint $table): void {
            $table->string('id_jadwal')->primary();
            $table->string('id_kalender')->nullable();
            $table->string('id_prodi')->nullable();
            $table->string('id_kelas_kuliah')->nullable();
            $table->string('id_matkul')->nullable();
            $table->string('id_ruang')->nullable();
            $table->string('dosen1')->nullable();
            $table->string('nama_kelas')->nullable();
            $table->string('kampus_merdeka')->nullable();
            $table->string('lingkup_km')->nullable();
            $table->string('mode_km')->nullable();
            $table->string('tgl_tutup_daftar_km')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterruang', function (Blueprint $table): void {
            $table->string('id_ruang')->primary();
            $table->integer('kapasitas')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_mastermk', function (Blueprint $table): void {
            $table->string('id_matkul')->primary();
            $table->string('nama_mata_kuliah')->nullable();
            $table->string('sks_mata_kuliah')->nullable();
            $table->string('sks_tatap_muka')->nullable();
            $table->string('sks_praktek')->nullable();
            $table->string('sks_praktek_lapangan')->nullable();
            $table->string('sks_simulasi')->nullable();
            $table->string('id_jenis_mata_kuliah')->nullable();
            $table->integer('semester')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterdosen', function (Blueprint $table): void {
            $table->string('id_dosen')->primary();
            $table->string('nidn')->nullable();
            $table->string('nama_dosen')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_akd', function (Blueprint $table): void {
            $table->string('id_akd')->primary();
            $table->string('id_jadwal')->nullable();
            $table->string('id_dosen')->nullable();
            $table->string('id_kalender')->nullable();
            $table->string('id_prodi')->nullable();
            $table->string('ket')->nullable();
            $table->integer('rencana')->nullable();
            $table->integer('realisasi')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_krs', function (Blueprint $table): void {
            $table->string('id_krs')->primary();
            $table->string('id_jadwal')->nullable();
            $table->string('id_mahasiswa')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_mastermhs', function (Blueprint $table): void {
            $table->string('id_mahasiswa')->primary();
            $table->string('id_prodi')->nullable();
            $table->string('id_registrasi_mahasiswa')->nullable();
            $table->string('nim')->nullable();
            $table->string('nama_mahasiswa')->nullable();
            $table->string('id_status_mahasiswa')->nullable();
            $table->integer('flag_feeder')->default(0);
        });
        Schema::connection('simak_main')->create('s1m4k_akm', function (Blueprint $table): void {
            $table->string('id_ipmhs')->primary();
            $table->string('id_mahasiswa')->nullable();
            $table->string('id_kalender')->nullable();
            $table->string('id_prodi')->nullable();
            $table->string('status_mahasiswa')->nullable();
            $table->string('ips')->nullable();
            $table->string('ipk')->nullable();
            $table->integer('sks_semester')->nullable();
            $table->integer('sks_program')->nullable();
            $table->integer('flag_feeder')->default(0);
            $table->string('tgl_keluar')->nullable();
            $table->string('ket')->nullable();
            $table->string('no_sk')->nullable();
            $table->string('tgl_sk')->nullable();
            $table->string('no_ijazah')->nullable();
        });
        Schema::connection('simak_main')->create('log_aktivitas', function (Blueprint $table): void {
            $table->string('id_log')->primary();
            $table->string('id_user')->nullable();
            $table->string('level_user')->nullable();
            $table->string('nama_user');
            $table->string('modul');
            $table->text('aktifitas');
            $table->timestamp('created_at')->useCurrent();
        });

        DB::connection('simak_main')->table('s1m4k_masterpt')->insert([
            'id_perguruan_tinggi' => 'pt-1',
            'alamat_feeder' => 'https://feeder.example.test',
            'user_feeder' => 'feeder-user',
            'password_feeder' => $this->encrypt('feeder-secret'),
        ]);
        DB::connection('simak_main')->table('s1m4k_masterprodi')->insert([
            'id_prodi' => 'prodi-1',
            'nama_program_studi' => 'Program Uji',
            'nama_jenjang_pendidikan' => 'S1',
        ]);
        DB::connection('simak_main')->table('s1m4k_kalender')->insert([
            'id_kalender' => 'calendar-1',
            'id_prodi' => 'prodi-1',
            'tahun' => '20261',
            'periode' => 'Ganjil',
            'namatahun' => '2026/2027 Ganjil',
        ]);
        DB::connection('simak_main')->table('s1m4k_kurikulum')->insert([
            'id_kurikulum' => 'curriculum-1',
            'nama_kurikulum' => 'Program Uji Ganjil',
            'id_prodi' => 'prodi-1',
            'id_semester' => '20261',
        ]);
        DB::connection('simak_main')->table('s1m4k_export')->insert([
            'id_export' => 'export-1',
            'id_prodi' => 'prodi-1',
            'id_kurikulum' => 'curriculum-1',
            'id_kalender' => 'calendar-1',
            'id_semester' => '20261',
            'data' => 'PesertaKelasKuliah',
            'status' => 'N',
        ]);
        DB::connection('simak_main')->table('s1m4k_jadwal')->insert([
            'id_jadwal' => 'schedule-1',
            'id_kalender' => 'calendar-1',
            'id_prodi' => 'prodi-1',
            'id_kelas_kuliah' => 'feeder-class-1',
        ]);
        DB::connection('simak_main')->table('s1m4k_krs')->insert([
            'id_krs' => 'krs-1',
            'id_jadwal' => 'schedule-1',
            'id_mahasiswa' => 'student-1',
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            'id_mahasiswa' => 'student-1',
            'id_prodi' => 'prodi-1',
            'id_registrasi_mahasiswa' => 'registration-1',
            'nim' => 'NIM-1',
            'nama_mahasiswa' => 'Mahasiswa Uji',
        ]);

        Http::fake(function (ClientRequest $request, array $options) {
            $this->assertSame(5, $options['timeout'] ?? null);
            $this->assertSame(4, $options['connect_timeout'] ?? null);
            if (($request['act'] ?? '') === 'GetToken') {
                return Http::response(['error_code' => '0', 'data' => ['token' => 'token-uji']]);
            }
            if (($this->feederResponseSequences[(string) ($request['act'] ?? '')] ?? []) !== []) {
                return Http::response(array_shift($this->feederResponseSequences[(string) $request['act']]));
            }
            if (array_key_exists((string) ($request['act'] ?? ''), $this->feederResponseOverrides)) {
                return Http::response($this->feederResponseOverrides[(string) $request['act']]);
            }
            if (($request['act'] ?? '') === 'GetListPenugasanDosen') {
                return Http::response(['error_code' => '0', 'data' => $this->lecturerAssignmentData]);
            }
            if (($request['act'] ?? '') === 'InsertDosenPengajarKelasKuliah') {
                return Http::response($this->lecturerInsertResponse);
            }
            if (($request['act'] ?? '') === 'InsertKurikulum') {
                return Http::response(['error_code' => '0', 'data' => [
                    'id_kurikulum' => 'feeder-curriculum-1',
                ]]);
            }
            if (($request['act'] ?? '') === 'InsertPesertaKelasKuliah' && $this->failFirstParticipantExport) {
                $this->failFirstParticipantExport = false;

                return Http::response([
                    'error_code' => '42',
                    'error_desc' => 'ID registrasi mahasiswa tidak ditemukan.',
                    'data' => [],
                ]);
            }

            return Http::response(['error_code' => '0', 'data' => []]);
        });
    }

    public function test_export_processes_in_small_steps_and_marks_the_source_record_complete(): void
    {
        $this->actingAs($this->operator());
        $start = $this->postJson(route('feeder-export.jobs.start'), ['export_id' => 'export-1'])
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('progress', 0);
        $jobId = $start->json('job_id');

        $this->postJson(route('feeder-export.jobs.step', ['job' => $jobId]))
            ->assertOk()
            ->assertJsonPath('progress', 100)
            ->assertJsonPath('success_count', 1)
            ->assertJsonPath('failure_count', 0)
            ->assertJsonPath('just_completed', true);

        $this->assertSame('Y', DB::connection('simak_main')->table('s1m4k_export')->value('status'));
        $detail = DB::connection('simak_main')->table('s1m4k_export_detail')->first();
        $this->assertSame('Y', $detail->status);
        $this->assertSame(1, $detail->attempts);
        $this->assertSame(64, strlen($detail->payload_hash));
        $this->assertSame(1, DB::connection('simak_main')->table('log_aktivitas')
            ->where('modul', 'Export ke Feeder')->count());
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'InsertPesertaKelasKuliah'
            && ($request['token'] ?? '') === 'token-uji'
            && ($request['record']['id_registrasi_mahasiswa'] ?? '') === 'registration-1');
    }

    public function test_unchanged_successful_data_is_skipped_and_changed_participant_is_replaced(): void
    {
        $this->actingAs($this->operator());
        $firstJob = $this->postJson(route('feeder-export.jobs.start'), ['export_id' => 'export-1'])
            ->assertOk()->json('job_id');
        $this->postJson(route('feeder-export.jobs.step', ['job' => $firstJob]))
            ->assertOk()->assertJsonPath('success_count', 1);
        $requestCountAfterFirstExport = Http::recorded()->count();

        $secondJob = $this->postJson(route('feeder-export.jobs.start'), ['export_id' => 'export-1'])
            ->assertOk()->json('job_id');
        $this->postJson(route('feeder-export.jobs.step', ['job' => $secondJob]))
            ->assertOk()
            ->assertJsonPath('success_count', 0)
            ->assertJsonPath('skipped_count', 1)
            ->assertJsonPath('complete', true);
        $this->assertSame($requestCountAfterFirstExport, Http::recorded()->count());

        DB::connection('simak_main')->table('s1m4k_mastermhs')
            ->where('id_mahasiswa', 'student-1')
            ->update(['id_registrasi_mahasiswa' => 'registration-updated']);
        $thirdJob = $this->postJson(route('feeder-export.jobs.start'), ['export_id' => 'export-1'])
            ->assertOk()->json('job_id');
        $this->postJson(route('feeder-export.jobs.step', ['job' => $thirdJob]))
            ->assertOk()
            ->assertJsonPath('success_count', 1)
            ->assertJsonPath('skipped_count', 0)
            ->assertJsonPath('complete', true);

        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'DeletePesertaKelasKuliah'
            && ($request['key']['id_registrasi_mahasiswa'] ?? '') === 'registration-1');
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'InsertPesertaKelasKuliah'
            && ($request['record']['id_registrasi_mahasiswa'] ?? '') === 'registration-updated');
        $detail = DB::connection('simak_main')->table('s1m4k_export_detail')->first();
        $this->assertSame('Y', $detail->status);
        $this->assertSame(2, $detail->attempts);
    }

    public function test_export_refreshes_cached_token_once_when_feeder_rejects_it(): void
    {
        $this->feederResponseSequences['InsertPesertaKelasKuliah'] = [
            [
                'error_code' => '1',
                'error_desc' => 'Response token tidak valid',
                'data' => [],
            ],
            ['error_code' => '0', 'data' => []],
        ];
        $this->actingAs($this->operator());
        $jobId = $this->postJson(route('feeder-export.jobs.start'), ['export_id' => 'export-1'])
            ->assertOk()->json('job_id');

        $this->postJson(route('feeder-export.jobs.step', ['job' => $jobId]))
            ->assertOk()
            ->assertJsonPath('success_count', 1)
            ->assertJsonPath('failure_count', 0);

        Http::assertSentCount(4);
        $this->assertSame(
            2,
            Http::recorded()->filter(
                fn (array $record): bool => ($record[0]['act'] ?? '') === 'GetToken',
            )->count(),
        );
    }

    public function test_feeder_error_is_shown_and_failed_record_can_be_retried_without_reexporting_successful_rows(): void
    {
        $this->failFirstParticipantExport = true;
        $this->actingAs($this->operator());
        $jobId = $this->postJson(route('feeder-export.jobs.start'), ['export_id' => 'export-1'])
            ->assertOk()->json('job_id');

        $this->postJson(route('feeder-export.jobs.step', ['job' => $jobId]))
            ->assertOk()
            ->assertJsonPath('progress', 100)
            ->assertJsonPath('failure_count', 1)
            ->assertJsonPath('failures.0.identity', 'NIM-1 - Mahasiswa Uji')
            ->assertJsonPath('failures.0.error_code', '42')
            ->assertJsonPath('failures.0.error_desc', 'ID registrasi mahasiswa tidak ditemukan.');
        $this->assertSame('N', DB::connection('simak_main')->table('s1m4k_export')->value('status'));
        $failedDetail = DB::connection('simak_main')->table('s1m4k_export_detail')->first();
        $this->assertSame('N', $failedDetail->status);
        $this->assertNull($failedDetail->payload_hash);

        $retryJob = $this->postJson(route('feeder-export.jobs.retry', ['job' => $jobId]))
            ->assertOk()->json('job_id');
        $this->postJson(route('feeder-export.jobs.step', ['job' => $retryJob]))
            ->assertOk()
            ->assertJsonPath('success_count', 1)
            ->assertJsonPath('failure_count', 0);
        $this->assertSame('Y', DB::connection('simak_main')->table('s1m4k_export')->value('status'));
        $this->assertSame('Y', DB::connection('simak_main')->table('s1m4k_export_detail')->value('status'));
        Http::assertSentCount(3);
    }

    public function test_export_marks_partial_success_and_retry_completes_the_status(): void
    {
        DB::connection('simak_main')->table('s1m4k_krs')->insert([
            'id_krs' => 'krs-2',
            'id_jadwal' => 'schedule-1',
            'id_mahasiswa' => 'student-2',
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            'id_mahasiswa' => 'student-2',
            'id_prodi' => 'prodi-1',
            'id_registrasi_mahasiswa' => 'registration-2',
            'nim' => 'NIM-2',
            'nama_mahasiswa' => 'Mahasiswa Kedua',
        ]);
        $this->failFirstParticipantExport = true;
        $this->actingAs($this->operator());
        $jobId = $this->postJson(route('feeder-export.jobs.start'), ['export_id' => 'export-1'])
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->json('job_id');

        $this->postJson(route('feeder-export.jobs.step', ['job' => $jobId]))
            ->assertOk()
            ->assertJsonPath('processed', 1)
            ->assertJsonPath('success_count', 0)
            ->assertJsonPath('failure_count', 1);
        $this->postJson(route('feeder-export.jobs.step', ['job' => $jobId]))
            ->assertOk()
            ->assertJsonPath('complete', true)
            ->assertJsonPath('success_count', 1)
            ->assertJsonPath('failure_count', 1)
            ->assertJsonPath('export_status', 'P');
        $this->assertSame('P', DB::connection('simak_main')->table('s1m4k_export')->value('status'));

        $this->failFirstParticipantExport = true;
        $retryJob = $this->postJson(route('feeder-export.jobs.retry', ['job' => $jobId]))
            ->assertOk()
            ->json('job_id');
        $this->postJson(route('feeder-export.jobs.step', ['job' => $retryJob]))
            ->assertOk()
            ->assertJsonPath('complete', true)
            ->assertJsonPath('failure_count', 1)
            ->assertJsonPath('export_status', 'P');
        $this->assertSame('P', DB::connection('simak_main')->table('s1m4k_export')->value('status'));

        $finalRetryJob = $this->postJson(route('feeder-export.jobs.retry', ['job' => $retryJob]))
            ->assertOk()
            ->json('job_id');
        $this->postJson(route('feeder-export.jobs.step', ['job' => $finalRetryJob]))
            ->assertOk()
            ->assertJsonPath('complete', true)
            ->assertJsonPath('failure_count', 0)
            ->assertJsonPath('export_status', 'Y');
        $this->assertSame('Y', DB::connection('simak_main')->table('s1m4k_export')->value('status'));
    }

    public function test_empty_data_is_rejected_before_contacting_feeder(): void
    {
        DB::connection('simak_main')->table('s1m4k_krs')->delete();
        $this->actingAs($this->operator());

        $this->postJson(route('feeder-export.jobs.start'), ['export_id' => 'export-1'])
            ->assertStatus(422)
            ->assertJsonPath('empty', true)
            ->assertJsonPath('message', 'Data yang akan diekspor kosong. Periksa data internal SIMAK terlebih dahulu.');

        Http::assertNothingSent();
    }

    public function test_existing_curriculum_course_is_treated_as_already_exported(): void
    {
        DB::connection('simak_main')->table('s1m4k_export')->update(['data' => 'MatkulKurikulum']);
        DB::connection('simak_main')->table('s1m4k_jadwal')->insert([
            'id_jadwal' => 'schedule-course-1',
            'id_kalender' => 'calendar-1',
            'id_prodi' => 'prodi-1',
            'id_matkul' => 'course-1',
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermk')->insert([
            'id_matkul' => 'course-1',
            'sks_mata_kuliah' => '3',
            'id_jenis_mata_kuliah' => 'A',
            'semester' => 1,
        ]);
        $this->feederResponseSequences['InsertMatkulKurikulum'] = [
            [
                'error_code' => '630',
                'error_desc' => 'Data mata kuliah kurikulum ini sudah ada',
                'data' => [],
            ],
            ['error_code' => '0', 'data' => []],
        ];
        $this->actingAs($this->operator());
        $jobId = $this->postJson(route('feeder-export.jobs.start'), ['export_id' => 'export-1'])
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->json('job_id');

        $this->postJson(route('feeder-export.jobs.step', ['job' => $jobId]))
            ->assertOk()
            ->assertJsonPath('success_count', 1)
            ->assertJsonPath('failure_count', 0)
            ->assertJsonPath('complete', true);
        $this->assertSame('Y', DB::connection('simak_main')->table('s1m4k_export')->value('status'));
    }

    public function test_existing_class_is_matched_and_linked_locally_before_counting_success(): void
    {
        DB::connection('simak_main')->table('s1m4k_export')->update(['data' => 'KelasKuliah']);
        DB::connection('simak_main')->table('s1m4k_jadwal')
            ->where('id_jadwal', 'schedule-1')
            ->update(['id_matkul' => 'course-1', 'id_kelas_kuliah' => null, 'nama_kelas' => 'A1']);
        $this->feederResponseOverrides['InsertKelasKuliah'] = [
            'error_code' => '630',
            'error_desc' => 'Data kelas kuliah sudah ada',
            'data' => [],
        ];
        $this->feederResponseOverrides['GetListKelasKuliah'] = [
            'error_code' => '0',
            'data' => [[
                'id_kelas_kuliah' => 'existing-class-1',
                'id_prodi' => 'prodi-1',
                'id_semester' => '20261',
                'id_matkul' => 'course-1',
                'nama_kelas_kuliah' => 'A1',
            ]],
        ];
        $this->actingAs($this->operator());
        $jobId = $this->postJson(route('feeder-export.jobs.start'), ['export_id' => 'export-1'])
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->json('job_id');

        $this->postJson(route('feeder-export.jobs.step', ['job' => $jobId]))
            ->assertOk()
            ->assertJsonPath('success_count', 1)
            ->assertJsonPath('failure_count', 0)
            ->assertJsonPath('complete', true);
        $this->assertSame(
            'existing-class-1',
            DB::connection('simak_main')->table('s1m4k_jadwal')->where('id_jadwal', 'schedule-1')->value('id_kelas_kuliah'),
        );
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'UpdateKelasKuliah'
            && ($request['key']['id_kelas_kuliah'] ?? '') === 'existing-class-1'
            && array_key_exists('sks_mata_kuliah', $request['record'])
            && ! array_key_exists('apa_untuk_pditt', $request['record'])
            && ! array_key_exists('tanggal_tutup_daftar', $request['record']));
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'InsertKelasKuliah'
            && array_key_exists('apa_untuk_pditt', $request['record'])
            && ! array_key_exists('sks_mata_kuliah', $request['record'])
            && ! array_key_exists('tanggal_tutup_daftar', $request['record']));
    }

    public function test_new_class_uses_insert_fields_then_updates_update_only_credit_fields(): void
    {
        DB::connection('simak_main')->table('s1m4k_export')->update(['data' => 'KelasKuliah']);
        DB::connection('simak_main')->table('s1m4k_jadwal')
            ->where('id_jadwal', 'schedule-1')
            ->update(['id_matkul' => 'course-1', 'nama_kelas' => 'A1', 'id_kelas_kuliah' => null]);
        DB::connection('simak_main')->table('s1m4k_mastermk')->insert([
            'id_matkul' => 'course-1',
            'nama_mata_kuliah' => 'Mata Kuliah Uji',
            'sks_mata_kuliah' => '3',
            'sks_tatap_muka' => '2',
            'sks_praktek' => '1',
        ]);
        $this->feederResponseOverrides['InsertKelasKuliah'] = [
            'error_code' => '0',
            'data' => ['id_kelas_kuliah' => 'created-class-1'],
        ];
        $this->actingAs($this->operator());
        $jobId = $this->postJson(route('feeder-export.jobs.start'), ['export_id' => 'export-1'])
            ->assertOk()->json('job_id');

        $this->postJson(route('feeder-export.jobs.step', ['job' => $jobId]))
            ->assertOk()
            ->assertJsonPath('success_count', 1)
            ->assertJsonPath('failure_count', 0);

        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'InsertKelasKuliah'
            && array_key_exists('apa_untuk_pditt', $request['record'])
            && ! array_key_exists('sks_mata_kuliah', $request['record'])
            && ! array_key_exists('tanggal_tutup_daftar', $request['record']));
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'UpdateKelasKuliah'
            && ($request['key']['id_kelas_kuliah'] ?? '') === 'created-class-1'
            && ($request['record']['sks_mata_kuliah'] ?? '') === '3.00'
            && ! array_key_exists('apa_untuk_pditt', $request['record']));
        $this->assertSame(
            'created-class-1',
            DB::connection('simak_main')->table('s1m4k_jadwal')
                ->where('id_jadwal', 'schedule-1')->value('id_kelas_kuliah'),
        );
    }

    public function test_existing_participant_is_not_reported_as_failed(): void
    {
        $this->feederResponseOverrides['InsertPesertaKelasKuliah'] = [
            'error_code' => '630',
            'error_desc' => 'Data peserta kelas kuliah sudah terdaftar',
            'data' => [],
        ];
        $this->actingAs($this->operator());
        $jobId = $this->postJson(route('feeder-export.jobs.start'), ['export_id' => 'export-1'])
            ->assertOk()
            ->json('job_id');

        $this->postJson(route('feeder-export.jobs.step', ['job' => $jobId]))
            ->assertOk()
            ->assertJsonPath('success_count', 1)
            ->assertJsonPath('failure_count', 0)
            ->assertJsonPath('complete', true);
    }

    public function test_existing_student_semester_record_is_marked_synced(): void
    {
        DB::connection('simak_main')->table('s1m4k_export')->update(['data' => 'PerkuliahanMahasiswa']);
        DB::connection('simak_main')->table('s1m4k_akm')->insert([
            'id_ipmhs' => 'activity-1',
            'id_mahasiswa' => 'student-1',
            'id_kalender' => 'calendar-1',
            'id_prodi' => 'prodi-1',
            'status_mahasiswa' => 'A',
        ]);
        $this->feederResponseOverrides['InsertPerkuliahanMahasiswa'] = [
            'error_code' => '630',
            'error_desc' => 'Data perkuliahan mahasiswa sudah ada',
            'data' => [],
        ];
        $this->actingAs($this->operator());
        $jobId = $this->postJson(route('feeder-export.jobs.start'), ['export_id' => 'export-1'])
            ->assertOk()
            ->json('job_id');

        $this->postJson(route('feeder-export.jobs.step', ['job' => $jobId]))
            ->assertOk()
            ->assertJsonPath('success_count', 1)
            ->assertJsonPath('failure_count', 0);
        $this->assertSame(
            1,
            DB::connection('simak_main')->table('s1m4k_akm')->where('id_ipmhs', 'activity-1')->value('flag_feeder'),
        );
    }

    public function test_existing_student_exit_record_is_marked_synced(): void
    {
        DB::connection('simak_main')->table('s1m4k_export')->update(['data' => 'MahasiswaLulusDO']);
        DB::connection('simak_main')->table('s1m4k_mastermhs')
            ->where('id_mahasiswa', 'student-1')
            ->update(['id_status_mahasiswa' => '1']);
        DB::connection('simak_main')->table('s1m4k_akm')->insert([
            'id_ipmhs' => 'exit-activity-1',
            'id_mahasiswa' => 'student-1',
            'id_kalender' => 'calendar-1',
            'id_prodi' => 'prodi-1',
        ]);
        $this->feederResponseOverrides['InsertMahasiswaLulusDO'] = [
            'error_code' => '630',
            'error_desc' => 'Data mahasiswa lulus/DO sudah tercatat',
            'data' => [],
        ];
        $this->actingAs($this->operator());
        $jobId = $this->postJson(route('feeder-export.jobs.start'), ['export_id' => 'export-1'])
            ->assertOk()
            ->json('job_id');

        $this->postJson(route('feeder-export.jobs.step', ['job' => $jobId]))
            ->assertOk()
            ->assertJsonPath('success_count', 1)
            ->assertJsonPath('failure_count', 0);
        $this->assertSame(
            1,
            DB::connection('simak_main')->table('s1m4k_mastermhs')->where('id_mahasiswa', 'student-1')->value('flag_feeder'),
        );
    }

    public function test_successful_rows_are_not_skipped_when_export_updates_the_candidate_query(): void
    {
        DB::connection('simak_main')->table('s1m4k_export')->update(['data' => 'PerkuliahanMahasiswa']);
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            'id_mahasiswa' => 'student-2',
            'id_prodi' => 'prodi-1',
            'id_registrasi_mahasiswa' => 'registration-2',
            'nim' => 'NIM-2',
            'nama_mahasiswa' => 'Mahasiswa Dua',
        ]);
        DB::connection('simak_main')->table('s1m4k_akm')->insert([
            [
                'id_ipmhs' => 'activity-1',
                'id_mahasiswa' => 'student-1',
                'id_kalender' => 'calendar-1',
                'id_prodi' => 'prodi-1',
                'status_mahasiswa' => 'A',
                'flag_feeder' => 0,
            ],
            [
                'id_ipmhs' => 'activity-2',
                'id_mahasiswa' => 'student-2',
                'id_kalender' => 'calendar-1',
                'id_prodi' => 'prodi-1',
                'status_mahasiswa' => 'A',
                'flag_feeder' => 0,
            ],
        ]);
        $this->actingAs($this->operator());
        $jobId = $this->postJson(route('feeder-export.jobs.start'), ['export_id' => 'export-1'])
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->json('job_id');

        $this->postJson(route('feeder-export.jobs.step', ['job' => $jobId]))
            ->assertOk()
            ->assertJsonPath('progress', 50)
            ->assertJsonPath('success_count', 1);
        $this->postJson(route('feeder-export.jobs.step', ['job' => $jobId]))
            ->assertOk()
            ->assertJsonPath('progress', 100)
            ->assertJsonPath('success_count', 2)
            ->assertJsonPath('just_completed', true);

        $this->assertSame(
            2,
            DB::connection('simak_main')->table('s1m4k_akm')->where('flag_feeder', 1)->count(),
        );
        Http::assertSentCount(3);
    }

    public function test_single_export_job_processes_every_candidate_without_a_total_limit(): void
    {
        $students = [];
        $courseRegistrations = [];
        for ($index = 2; $index <= 30; $index++) {
            $students[] = [
                'id_mahasiswa' => 'student-'.$index,
                'id_prodi' => 'prodi-1',
                'id_registrasi_mahasiswa' => 'registration-'.$index,
                'nim' => 'NIM-'.$index,
                'nama_mahasiswa' => 'Mahasiswa '.$index,
            ];
            $courseRegistrations[] = [
                'id_krs' => 'krs-'.$index,
                'id_jadwal' => 'schedule-1',
                'id_mahasiswa' => 'student-'.$index,
            ];
        }
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert($students);
        DB::connection('simak_main')->table('s1m4k_krs')->insert($courseRegistrations);
        $this->actingAs($this->operator());

        $jobId = $this->postJson(route('feeder-export.jobs.start'), ['export_id' => 'export-1'])
            ->assertOk()
            ->assertJsonPath('total', 30)
            ->json('job_id');

        do {
            $result = $this->postJson(route('feeder-export.jobs.step', ['job' => $jobId]))
                ->assertOk()
                ->assertJsonPath('total', 30);
        } while (! $result->json('complete'));

        $result->assertJsonPath('processed', 30)
            ->assertJsonPath('success_count', 30)
            ->assertJsonPath('failure_count', 0)
            ->assertJsonPath('progress', 100);
        $this->assertSame('Y', DB::connection('simak_main')->table('s1m4k_export')->value('status'));
        Http::assertSentCount(31);
    }

    public function test_lecturer_export_keeps_all_feeder_calls_within_a_bounded_request(): void
    {
        DB::connection('simak_main')->table('s1m4k_export')->update(['data' => 'DosenPengajarKelasKuliah']);
        DB::connection('simak_main')->table('s1m4k_jadwal')
            ->where('id_jadwal', 'schedule-1')
            ->update(['id_matkul' => 'course-1']);
        DB::connection('simak_main')->table('s1m4k_mastermk')->insert([
            'id_matkul' => 'course-1',
            'sks_mata_kuliah' => '3',
        ]);
        DB::connection('simak_main')->table('s1m4k_masterdosen')->insert([
            'id_dosen' => 'lecturer-1',
            'nidn' => null,
            'nama_dosen' => 'Dosen Uji',
        ]);
        DB::connection('simak_main')->table('s1m4k_akd')->insert([
            'id_akd' => 'teaching-1',
            'id_jadwal' => 'schedule-1',
            'id_dosen' => 'lecturer-1',
            'id_kalender' => 'calendar-1',
            'id_prodi' => 'prodi-1',
            'ket' => 'dosen',
            'rencana' => 16,
            'realisasi' => 14,
        ]);
        $this->lecturerAssignmentData = [
            [
                'id_dosen' => 'other-lecturer',
                'id_registrasi_dosen' => 'wrong-registration',
                'nidn' => 'NIDN-1',
            ],
            [
                'id_dosen' => 'lecturer-1',
                'id_registrasi_dosen' => 'lecturer-registration-1',
                'nidn' => 'NIDN-OTHER',
            ],
        ];
        $this->actingAs($this->operator());
        $jobId = $this->postJson(route('feeder-export.jobs.start'), ['export_id' => 'export-1'])
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->json('job_id');

        $this->postJson(route('feeder-export.jobs.step', ['job' => $jobId]))
            ->assertOk()
            ->assertJsonPath('success_count', 1)
            ->assertJsonPath('failure_count', 0)
            ->assertJsonPath('just_completed', true);

        Http::assertSentCount(3);
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'GetListPenugasanDosen'
            && ($request['filter'] ?? '') === "id_dosen='lecturer-1'"
            && ($request['order'] ?? '') === 'id_tahun_ajaran DESC'
            && ($request['limit'] ?? null) === 100);
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'InsertDosenPengajarKelasKuliah'
            && ($request['record']['id_registrasi_dosen'] ?? '') === 'lecturer-registration-1');
    }

    public function test_existing_lecturer_assignment_is_counted_as_already_exported_not_a_failure(): void
    {
        DB::connection('simak_main')->table('s1m4k_export')->update(['data' => 'DosenPengajarKelasKuliah']);
        DB::connection('simak_main')->table('s1m4k_jadwal')
            ->where('id_jadwal', 'schedule-1')
            ->update(['id_matkul' => 'course-1']);
        DB::connection('simak_main')->table('s1m4k_mastermk')->insert([
            'id_matkul' => 'course-1',
            'sks_mata_kuliah' => '3',
        ]);
        DB::connection('simak_main')->table('s1m4k_masterdosen')->insert([
            'id_dosen' => 'lecturer-1',
            'nidn' => 'NIDN-1',
            'nama_dosen' => 'Dosen Uji',
        ]);
        DB::connection('simak_main')->table('s1m4k_akd')->insert([
            'id_akd' => 'teaching-1',
            'id_jadwal' => 'schedule-1',
            'id_dosen' => 'lecturer-1',
            'id_kalender' => 'calendar-1',
            'id_prodi' => 'prodi-1',
            'ket' => 'dosen',
            'rencana' => 16,
            'realisasi' => 14,
        ]);
        $this->lecturerInsertResponse = [
            'error_code' => '1192',
            'error_desc' => 'Data Dosen Pengajar sudah ada',
            'data' => [],
        ];
        $this->actingAs($this->operator());
        $jobId = $this->postJson(route('feeder-export.jobs.start'), ['export_id' => 'export-1'])
            ->assertOk()
            ->json('job_id');

        $this->postJson(route('feeder-export.jobs.step', ['job' => $jobId]))
            ->assertOk()
            ->assertJsonPath('success_count', 1)
            ->assertJsonPath('failure_count', 0)
            ->assertJsonPath('complete', true);
    }

    public function test_export_screen_previews_available_data_and_shows_the_progress_controls(): void
    {
        $this->actingAs($this->operator());

        $this->get(route('feeder-export.index', ['prodi' => 'prodi-1']))
            ->assertOk()
            ->assertSee('Export ke Feeder')
            ->assertSee('Informasi proses ekspor')
            ->assertSee('Pilih kurikulum')
            ->assertSee('Tambah data pelaporan kurikulum')
            ->assertSee('Edit data pelaporan kurikulum')
            ->assertSee('data-open-dialog="feeder-add-curriculum-dialog"', false)
            ->assertSee('data-edit-curriculum="curriculum-1"', false)
            ->assertSee('data-action-progress', false)
            ->assertSee('data-action-progress-bar', false)
            ->assertSee('feeder-export-spinner', false)
            ->assertSee('Pelaporan')
            ->assertSee('data-searchable-select', false)
            ->assertDontSee('data-select2', false)
            ->assertDontSee('feeder-add-curriculum-dialog" open', false)
            ->assertDontSee('Data tersedia di SIMAK');

        DB::connection('simak_main')->table('s1m4k_export')->update(['status' => 'P']);
        DB::connection('simak_main')->table('s1m4k_export_detail')->insert([
            [
                'id_export_detail' => 'detail-success',
                'id_export' => 'export-1',
                'source_id' => 'source-success',
                'status' => 'Y',
            ],
            [
                'id_export_detail' => 'detail-failure',
                'id_export' => 'export-1',
                'source_id' => 'source-failure',
                'status' => 'N',
            ],
        ]);
        $this->get(route('feeder-export.reports', ['curriculum' => 'curriculum-1']))
            ->assertOk()
            ->assertSee('Pelaporan Export ke Feeder')
            ->assertSee('Data tersedia di SIMAK')
            ->assertSee('data-export-success>1</td>', false)
            ->assertSee('data-export-failure>1</td>', false)
            ->assertSee('tanpa batas jumlah ekspor')
            ->assertSee('Satu kali klik akan memproses semua data')
            ->assertDontSee('id="sync-curriculum-form"', false)
            ->assertSee('progressbar')
            ->assertSee('data-progress-box', false)
            ->assertSee('data-progress-bar', false)
            ->assertSee('Sebagian Tereksport')
            ->assertSee('feeder-export-status partial', false)
            ->assertSee('Ekspor ulang')
            ->assertSee('while (!state.complete)', false)
            ->assertSee('Mulai ekspor')
            ->assertSee('Kembali ke daftar kurikulum');

        Http::assertNothingSent();
    }

    public function test_curriculum_list_uses_server_side_datatables_pagination(): void
    {
        $rows = [];
        for ($index = 2; $index <= 27; $index++) {
            $rows[] = [
                'id_kurikulum' => 'curriculum-'.$index,
                'nama_kurikulum' => 'Kurikulum '.$index,
                'id_prodi' => 'prodi-1',
                'id_semester' => (string) (20260 + $index),
            ];
        }
        DB::connection('simak_main')->table('s1m4k_kurikulum')->insert($rows);
        $this->actingAs($this->operator());

        $this->get(route('feeder-export.index', ['prodi' => 'prodi-1']))
            ->assertOk()
            ->assertSee('dt-paging')
            ->assertSee('Selanjutnya')
            ->assertSee('Kurikulum 27')
            ->assertDontSee('Kurikulum 2</td>');
        $this->get(route('feeder-export.index', ['prodi' => 'prodi-1', 'page' => 2]))
            ->assertOk()
            ->assertSeeText('Kurikulum 2');
    }

    public function test_edit_curriculum_updates_feeder_and_local_reporting_calendar(): void
    {
        DB::connection('simak_main')->table('s1m4k_mastermk')->insert([
            'id_matkul' => 'course-1',
            'sks_mata_kuliah' => '3',
            'id_jenis_mata_kuliah' => 'A',
        ]);
        DB::connection('simak_main')->table('s1m4k_jadwal')->update(['id_matkul' => 'course-1']);
        $this->actingAs($this->operator());

        $this->postJson(route('feeder-export.curricula.sync', ['curriculum' => 'curriculum-1']), [
            'program_id' => 'prodi-1',
            'calendar_id' => 'calendar-1',
        ])->assertOk()->assertJsonPath('message', 'Data kurikulum berhasil diperbarui di Feeder.');

        $this->assertSame(
            'calendar-1',
            DB::connection('simak_main')->table('s1m4k_export')->value('id_kalender'),
        );
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'UpdateKurikulum'
            && ($request['key']['id_kurikulum'] ?? '') === 'curriculum-1'
            && ($request['record']['id_prodi'] ?? '') === 'prodi-1');
    }

    public function test_create_curriculum_uses_bounded_feeder_requests_before_preparing_exports(): void
    {
        DB::connection('simak_main')->table('s1m4k_mastermk')->insert([
            'id_matkul' => 'course-1',
            'sks_mata_kuliah' => '3',
            'id_jenis_mata_kuliah' => 'A',
        ]);
        DB::connection('simak_main')->table('s1m4k_jadwal')
            ->where('id_jadwal', 'schedule-1')
            ->update(['id_matkul' => 'course-1']);
        $this->actingAs($this->operator());

        $this->postJson(route('feeder-export.curricula.store'), [
            'program_id' => 'prodi-1',
            'calendar_id' => 'calendar-1',
        ])->assertOk()
            ->assertJsonPath('message', 'Kurikulum berhasil disimpan ke Feeder dan daftar ekspor telah disiapkan.');

        $this->assertDatabaseHas('s1m4k_kurikulum', [
            'id_kurikulum' => 'feeder-curriculum-1',
            'id_prodi' => 'prodi-1',
            'id_semester' => '20261',
        ], 'simak_main');
        $this->assertSame(8, DB::connection('simak_main')->table('s1m4k_export')
            ->where('id_kurikulum', 'feeder-curriculum-1')->count());
        Http::assertSentCount(2);
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'InsertKurikulum'
            && ($request['record']['id_prodi'] ?? '') === 'prodi-1'
            && ($request['record']['id_semester'] ?? '') === '20261');
    }

    public function test_existing_curriculum_is_reused_when_feeder_rejects_duplicate_insert(): void
    {
        DB::connection('simak_main')->table('s1m4k_mastermk')->insert([
            'id_matkul' => 'course-1',
            'sks_mata_kuliah' => '3',
            'id_jenis_mata_kuliah' => 'A',
        ]);
        DB::connection('simak_main')->table('s1m4k_jadwal')
            ->where('id_jadwal', 'schedule-1')
            ->update(['id_matkul' => 'course-1']);
        $this->feederResponseOverrides['InsertKurikulum'] = [
            'error_code' => '630',
            'error_desc' => 'Data kurikulum sudah ada',
            'data' => [],
        ];
        $this->feederResponseOverrides['GetListKurikulum'] = [
            'error_code' => '0',
            'data' => [[
                'id_kurikulum' => 'existing-curriculum-1',
                'id_prodi' => 'prodi-1',
                'id_semester' => '20261',
                'nama_kurikulum' => 'Program Uji Ganjil',
            ]],
        ];
        $this->actingAs($this->operator());

        $this->postJson(route('feeder-export.curricula.store'), [
            'program_id' => 'prodi-1',
            'calendar_id' => 'calendar-1',
        ])->assertOk()
            ->assertJsonPath('message', 'Kurikulum berhasil disimpan ke Feeder dan daftar ekspor telah disiapkan.');

        $this->assertSame(8, DB::connection('simak_main')->table('s1m4k_export')
            ->where('id_kurikulum', 'existing-curriculum-1')->count());
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'GetListKurikulum'
            && ($request['filter'] ?? '') === "id_prodi='prodi-1' AND id_semester='20261'");
    }

    public function test_only_admin_and_usertu_can_open_the_export_module_and_usertu_cannot_export_another_program(): void
    {
        $this->actingAs(new GenericUser([
            'id' => 'admin-1',
            'legacy_id' => 'admin-1',
            'role' => 'admin',
            'name' => 'Admin Uji',
            'login' => 'admin-uji',
        ]));
        $this->get(route('feeder-export.index', ['prodi' => 'prodi-1']))
            ->assertOk()
            ->assertSee('data-searchable-select="filter"', false)
            ->assertSee('data-open-dialog="feeder-sync-curriculum-dialog"', false)
            ->assertSee('id="sync-curriculum-form"', false)
            ->assertSee('data-progress-title="Menyinkronkan kurikulum"', false)
            ->assertSee('Program Uji');

        $this->actingAs(new GenericUser([
            'id' => 'userprodi-1',
            'legacy_id' => 'userprodi-1',
            'role' => 'userprodi',
            'id_prodi' => 'prodi-1',
            'name' => 'Pengelola Prodi',
        ]));
        $this->get(route('feeder-export.index'))->assertForbidden();

        $this->actingAs(new GenericUser([
            'id' => 'usertu-1',
            'legacy_id' => 'usertu-1',
            'role' => 'usertu',
            'id_prodi' => 'prodi-1',
            'login' => 'operator-prodi',
            'name' => 'Operator Prodi',
        ]));
        $this->get(route('feeder-export.index'))
            ->assertOk()
            ->assertDontSee('data-open-dialog="feeder-sync-curriculum-dialog"', false);
        $this->postJson(route('feeder-export.curricula.sync', ['curriculum' => 'curriculum-1']), [
            'program_id' => 'prodi-1',
            'calendar_id' => 'calendar-1',
            'admin_sync' => true,
        ])->assertForbidden();

        $this->actingAs(new GenericUser([
            'id' => 'usertu-other',
            'legacy_id' => 'usertu-other',
            'role' => 'usertu',
            'id_prodi' => 'prodi-lain',
            'name' => 'Operator Prodi Lain',
        ]));
        $this->postJson(route('feeder-export.jobs.start'), ['export_id' => 'export-1'])->assertForbidden();
        $this->get(route('feeder-export.reports', ['curriculum' => 'curriculum-1']))->assertForbidden();
        Http::assertNothingSent();
    }

    private function operator(): GenericUser
    {
        return new GenericUser([
            'id' => 'usertu-1',
            'legacy_id' => 'usertu-1',
            'role' => 'usertu',
            'id_prodi' => 'prodi-1',
            'login' => 'operator-uji',
            'email' => 'operator@example.test',
            'name' => 'Operator Uji',
        ]);
    }

    private function encrypt(string $value): string
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
