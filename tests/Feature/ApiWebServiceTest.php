<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ApiWebServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.simak_main' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
        ]]);
        DB::purge('simak_main');
        Schema::connection('simak_main')->create('s1m4k_admin', function (Blueprint $table): void {
            $table->bigIncrements('id_admin');
            $table->string('username')->unique();
            $table->string('password');
            $table->string('level');
            $table->string('blokir', 1)->default('N');
            $table->string('nama_lengkap')->nullable();
            $table->string('email')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterfakultas', function (Blueprint $table): void {
            $table->string('id_fakultas')->primary();
            $table->string('id_perguruan_tinggi')->nullable();
            $table->string('kode_fakultas')->nullable();
            $table->string('nama_fakultas')->nullable();
            $table->string('status')->nullable();
            $table->string('dekan')->nullable();
            $table->string('pd1')->nullable();
            $table->string('pd2')->nullable();
            $table->string('pd3')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterdosen', function (Blueprint $table): void {
            $table->string('id_dosen')->primary();
            $table->string('nidn')->nullable();
            $table->string('nip')->nullable();
            $table->string('nama_dosen')->nullable();
            $table->string('gelar_depan')->nullable();
            $table->string('gelar_belakang')->nullable();
            $table->string('email')->nullable();
            $table->string('jenis_kelamin')->nullable();
            $table->string('tanggal_lahir')->nullable();
            $table->string('id_registrasi_dosen')->nullable();
            $table->string('id_prodi')->nullable();
            $table->string('id_fakultas')->nullable();
            $table->string('password')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterasdos', function (Blueprint $table): void {
            $table->string('id_asdos')->primary();
            $table->string('kdasisten')->nullable();
            $table->string('nama')->nullable();
            $table->string('gelard')->nullable();
            $table->string('gelarb')->nullable();
            $table->string('jkel')->nullable();
            $table->string('email')->nullable();
            $table->string('notlp')->nullable();
            $table->string('status')->nullable();
            $table->string('id_prodi')->nullable();
            $table->string('id_fakultas')->nullable();
            $table->string('password')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_mastermhs', function (Blueprint $table): void {
            $table->string('id_mahasiswa')->primary();
            $table->string('nim')->nullable();
            $table->string('nama_mahasiswa')->nullable();
            $table->string('id_registrasi_mahasiswa')->nullable();
            $table->string('id_prodi')->nullable();
            $table->string('id_fakultas')->nullable();
            $table->string('id_status_mahasiswa')->nullable();
            $table->string('nama_status_mahasiswa')->nullable();
            $table->string('jenis_kelamin')->nullable();
            $table->string('tempat_lahir')->nullable();
            $table->string('tanggal_lahir')->nullable();
            $table->string('nisn')->nullable();
            $table->string('handphone')->nullable();
            $table->string('id_periode_masuk')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterprodi', function (Blueprint $table): void {
            $table->string('id_prodi')->primary();
            $table->string('nama_program_studi')->nullable();
            $table->string('nama_jenjang_pendidikan')->nullable();
        });
        DB::connection('simak_main')->table('s1m4k_masterprodi')->insert([
            'id_prodi' => 'program-1',
            'nama_program_studi' => 'Ilmu Komputer',
            'nama_jenjang_pendidikan' => 'S1',
        ]);
        DB::connection('simak_main')->table('s1m4k_masterdosen')->insert([
            'id_dosen' => 'lecturer-1',
            'nidn' => '10001',
            'nip' => '19800101',
            'nama_dosen' => 'Dosen Uji',
            'gelar_depan' => 'Dr.',
            'gelar_belakang' => 'M.Kom.',
            'email' => 'dosen@example.test',
            'jenis_kelamin' => 'L',
            'tanggal_lahir' => '1980-01-01',
            'id_registrasi_dosen' => 'reg-dosen-1',
            'id_prodi' => 'program-1',
            'id_fakultas' => 'faculty-1',
            'password' => Hash::make('dosen-secret'),
        ]);
        DB::connection('simak_main')->table('s1m4k_masterasdos')->insert([
            'id_asdos' => 'assistant-1',
            'kdasisten' => 'AS001',
            'nama' => 'Asisten Uji',
            'gelard' => 'S.Kom.',
            'jkel' => 'P',
            'email' => 'asdos@example.test',
            'notlp' => '081234567890',
            'status' => 'Aktif',
            'id_prodi' => 'program-1',
            'id_fakultas' => 'faculty-1',
            'password' => Hash::make('asdos-secret'),
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            'id_mahasiswa' => 'student-1',
            'nim' => '20260001',
            'nama_mahasiswa' => 'Mahasiswa Uji',
            'id_registrasi_mahasiswa' => 'reg-mhs-1',
            'id_prodi' => 'program-1',
            'id_fakultas' => 'faculty-1',
            'id_status_mahasiswa' => 'A',
            'nama_status_mahasiswa' => 'Aktif',
            'jenis_kelamin' => 'P',
            'tempat_lahir' => 'Ternate',
            'tanggal_lahir' => '2006-04-15',
            'nisn' => '0012345678',
            'handphone' => '081234567891',
            'id_periode_masuk' => '20261',
            'email' => 'mhs@example.test',
            'password' => Hash::make('mhs-secret'),
        ]);
        Schema::connection('simak_main')->create('s1m4k_masterpt', function (Blueprint $table): void {
            $table->string('id_perguruan_tinggi')->primary();
            $table->string('nama_perguruan_tinggi')->nullable();
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
        DB::connection('simak_main')->table('s1m4k_masterpt')->insert([
            'id_perguruan_tinggi' => 'pt-1', 'nama_perguruan_tinggi' => 'Universitas Uji',
        ]);
        DB::connection('simak_main')->table('s1m4k_masterfakultas')->insert([
            'id_fakultas' => 'faculty-1', 'kode_fakultas' => '01', 'nama_fakultas' => 'Fakultas Uji', 'status' => 'Aktif',
        ]);
        DB::connection('simak_main')->table('s1m4k_admin')->insert([
            ['username' => 'ws-active', 'password' => Hash::make('ws-secret-123'), 'level' => 'admin_ws', 'blokir' => 'N'],
            ['username' => 'normal-admin', 'password' => Hash::make('ws-secret-123'), 'level' => 'admin', 'blokir' => 'N'],
            ['username' => 'ws-blocked', 'password' => Hash::make('ws-secret-123'), 'level' => 'admin_ws', 'blokir' => 'Y'],
        ]);
        DB::connection('simak_main')->table('s1m4k_admin')
            ->where('username', 'normal-admin')
            ->update(['nama_lengkap' => 'Admin SIMAK', 'email' => 'admin@example.test']);
    }

    public function test_sso_login_returns_authenticated_user_identity_biodata_and_level(): void
    {
        $response = $this->postJson('/api/v1/auth/sso', [
            'login' => 'normal-admin',
            'password' => 'ws-secret-123',
        ])->assertOk()
            ->assertJsonStructure([
                'success',
                'user' => [
                    'id',
                    'level',
                    'biodata' => ['jenis_akun', 'identitas_login', 'nama', 'email', 'id_prodi', 'id_fakultas'],
                ],
            ])
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.level', 'admin')
            ->assertJsonPath('user.biodata.identitas_login', 'normal-admin')
            ->assertJsonPath('user.biodata.nama', 'Admin SIMAK')
            ->assertJsonPath('user.biodata.email', 'admin@example.test')
            ->assertJsonMissingPath('user.password')
            ->assertJsonMissingPath('akses_token');

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertSame(
            (string) DB::connection('simak_main')->table('s1m4k_admin')
                ->where('username', 'normal-admin')->value('id_admin'),
            $response->json('user.id'),
        );
        $this->postJson('/api/v1/auth/sso', [
            'login' => 'ws-active',
            'password' => 'ws-secret-123',
        ])->assertUnauthorized();
        $this->postJson('/api/v1/auth/sso', [
            'login' => 'normal-admin',
            'password' => 'wrong-password',
        ])->assertUnauthorized();

    }

    public function test_sso_returns_role_specific_profile_fields_for_non_admin_accounts(): void
    {
        $this->postJson('/api/v1/auth/sso', ['login' => '10001', 'password' => 'dosen-secret'])
            ->assertOk()
            ->assertJsonPath('user.level', 'dosen')
            ->assertJsonPath('user.biodata.nip', '19800101')
            ->assertJsonPath('user.biodata.id_registrasi_dosen', 'reg-dosen-1')
            ->assertJsonPath('user.biodata.nama_program_studi', 'Ilmu Komputer')
            ->assertJsonPath('user.biodata.nama_fakultas', 'Fakultas Uji')
            ->assertJsonMissingPath('user.biodata.password');
        $this->postJson('/api/v1/auth/sso', ['login' => 'AS001', 'password' => 'asdos-secret'])
            ->assertOk()
            ->assertJsonPath('user.level', 'asdos')
            ->assertJsonPath('user.biodata.gelard', 'S.Kom.')
            ->assertJsonPath('user.biodata.notlp', '081234567890');
        $this->postJson('/api/v1/auth/sso', ['login' => '20260001', 'password' => 'mhs-secret'])
            ->assertOk()
            ->assertJsonPath('user.level', 'mahasiswa')
            ->assertJsonPath('user.biodata.id_registrasi_mahasiswa', 'reg-mhs-1')
            ->assertJsonPath('user.biodata.tempat_lahir', 'Ternate')
            ->assertJsonPath('user.biodata.nama_status_mahasiswa', 'Aktif')
            ->assertJsonPath('user.biodata.nama_program_studi', 'Ilmu Komputer')
            ->assertJsonMissingPath('user.biodata.password');
    }

    public function test_only_active_admin_ws_accounts_can_issue_api_tokens(): void
    {
        $this->post('/login', ['login' => 'ws-active', 'password' => 'ws-secret-123'])
            ->assertSessionHasErrors('login');
        $this->assertGuest();

        $tokenResponse = $this->postJson('/api/v1/auth/token', [
            'username' => 'ws-active',
            'password' => 'ws-secret-123',
        ])->assertOk()->assertJsonStructure(['akses_token', 'tipe_token', 'kedaluwarsa_dalam']);
        $this->assertSame('Bearer', $tokenResponse->json('tipe_token'));
        $this->assertSame(3600, $tokenResponse->json('kedaluwarsa_dalam'));

        $this->postJson('/api/v1/auth/token', [
            'username' => 'normal-admin',
            'password' => 'ws-secret-123',
        ])->assertUnauthorized();
        $this->postJson('/api/v1/auth/token', [
            'username' => 'ws-blocked',
            'password' => 'ws-secret-123',
        ])->assertUnauthorized();
        $this->postJson('/api/v1/auth/token', [
            'username' => 'ws-active',
            'password' => 'wrong-password',
        ])->assertUnauthorized();
    }

    public function test_docs_operation_catalog_and_openapi_are_available_without_a_token(): void
    {
        $this->get('/api/v1/')->assertOk()
            ->assertSee('Layanan Web SIAKAD')
            ->assertSee('Panduan API (PDF)')
            ->assertSee('/api/v1/guide.pdf')
            ->assertSee('SSO Login SIAKAD')
            ->assertSee('data-sso-login-submit', false)
            ->assertSee('data-sso-menu', false)
            ->assertSee('Sandbox')
            ->assertSee('Production')
            ->assertSee('Request Body')
            ->assertSee('data-request-fields')
            ->assertDontSee('data-add-request-field')
            ->assertSee('Operation Reference')
            ->assertSee('data-reference-tab="parameters"', false)
            ->assertSee('data-reference-tab="request"', false)
            ->assertSee('data-reference-tab="response"', false)
            ->assertSee('GET · POST · PUT/PATCH · DELETE');
        $this->getJson('/api/v1/operations')->assertOk()
            ->assertJsonPath('versi', '1.0.0')
            ->assertJsonFragment(['name' => 'master-data.fakultas.data', 'summary' => 'Ambil data fakultas'])
            ->assertJsonFragment(['name' => 'master-data.fakultas.store', 'summary' => 'Create fakultas'])
            ->assertJsonFragment(['name' => 'master-data.fakultas.data', 'response_kind' => 'json'])
            ->assertJsonFragment(['name' => 'academic-calendars.store', 'response_kind' => 'mixed'])
            ->assertJsonFragment(['path' => '/data-master/fakultas/data'])
            ->assertJsonFragment(['path' => '/keuangan/periode-ukt'])
            ->assertJsonFragment(['path' => '/keuangan/pembayaran-ukt/generate'])
            ->assertJsonFragment(['name' => 'lms.materials.store', 'summary' => 'Create materi kuliah'])
            ->assertJsonFragment(['name' => 'lms.meetings.index', 'response_kind' => 'json'])
            ->assertJsonFragment(['name' => 'lms.questions.store', 'method' => 'POST'])
            ->assertJsonFragment(['name' => 'lms.tasks.grade', 'method' => 'PUT'])
            ->assertJsonFragment(['name' => 'lms.exams.update', 'method' => 'PUT'])
            ->assertJsonFragment(['name' => 'lms.discussions.destroy', 'method' => 'DELETE'])
            ->assertJsonFragment(['path' => '/administrasi/pengguna/data'])
            ->assertJsonMissing(['path' => '/master-data/fakultas/data'])
            ->assertJsonMissing(['path' => '/finance/billing/data'])
            ->assertJsonMissing(['path' => '/theses/titles'])
            ->assertJsonMissing(['name' => 'lms.materials.download'])
            ->assertJsonMissing(['name' => 'lms.questions.import'])
            ->assertJsonMissing(['name' => 'lms.attendance.self'])
            ->assertJsonMissing(['name' => 'lms.exams.submit'])
            ->assertJsonMissing(['path' => '/graduation-validations/export/pdf']);
        $openApi = $this->get('/api/v1/openapi.yaml')->assertOk()
            ->assertHeader('Content-Type', 'application/yaml; charset=UTF-8');
        $document = json_decode($openApi->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('3.1.0', $document['openapi']);
        $this->assertSame('API Layanan Web SIAKAD', $document['info']['title']);
        $this->assertArrayHasKey('/data-master/fakultas/data', $document['paths']);
        $this->assertSame('Ambil data fakultas', $document['paths']['/data-master/fakultas/data']['get']['summary']);
        $this->assertArrayHasKey('/auth/token', $document['paths']);
        $this->assertArrayHasKey('/auth/sso', $document['paths']);
        $this->assertArrayHasKey('/lms/bank-soal', $document['paths']);

        $guide = $this->get('/api/v1/guide.pdf')->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="panduan-api-ws-simak-v1.pdf"');
        $this->assertStringStartsWith('%PDF-', $guide->getContent());
    }

    public function test_expired_tokens_and_tokens_for_blocked_accounts_are_rejected(): void
    {
        $expiredToken = Crypt::encryptString(json_encode([
            'aud' => 'simak-api-v1', 'sub' => '1', 'jti' => 'expired-jti',
            'iat' => time() - 3700, 'exp' => time() - 100,
        ], JSON_THROW_ON_ERROR));
        $this->withToken($expiredToken)->getJson('/api/v1/master-data/fakultas/data')->assertUnauthorized();

        $validToken = $this->postJson('/api/v1/auth/token', [
            'username' => 'ws-active', 'password' => 'ws-secret-123',
        ])->assertOk()->json('akses_token');
        DB::connection('simak_main')->table('s1m4k_admin')
            ->where('username', 'ws-active')->update(['blokir' => 'Y']);

        $this->withToken($validToken)->getJson('/api/v1/master-data/fakultas/data')->assertUnauthorized();
    }

    public function test_gateway_preserves_existing_route_throttles(): void
    {
        $token = $this->postJson('/api/v1/auth/token', [
            'username' => 'ws-active', 'password' => 'ws-secret-123',
        ])->assertOk()->json('akses_token');

        foreach (range(1, 20) as $attempt) {
            $this->withToken($token)->putJson('/api/v1/validasi-wisuda/period-1/participant-1', [])
                ->assertUnprocessable();
        }

        $this->withToken($token)->putJson('/api/v1/validasi-wisuda/period-1/participant-1', [])
            ->assertTooManyRequests();
    }

    public function test_sandbox_isolated_and_production_gateway_reuses_allowlisted_business_controller(): void
    {
        $this->getJson('/api/v1/master-data/fakultas/data')->assertUnauthorized();

        $this->postJson('/api/v1/sandbox/execute', [
            'operation' => 'master-data.fakultas.store',
            'method' => 'POST',
            'path' => '/master-data/fakultas',
            'body' => ['nama_fakultas' => 'Fakultas Simulasi', 'password' => 'do-not-return-this'],
        ])->assertOk()->assertJsonPath('mode', 'sandbox')
            ->assertJsonPath('respons.data.nama_fakultas', 'Fakultas Simulasi')
            ->assertJsonPath('respons.pesan', 'Simulasi insert berhasil. Tidak ada data yang disimpan.')
            ->assertDontSee('do-not-return-this');
        $this->assertDatabaseCount('s1m4k_masterfakultas', 1, 'simak_main');

        $token = $this->postJson('/api/v1/auth/token', [
            'username' => 'ws-active', 'password' => 'ws-secret-123',
        ])->assertOk()->json('akses_token');
        $this->withToken($token)->getJson('/api/v1/operations')
            ->assertOk()->assertJsonFragment(['name' => 'master-data.fakultas.data']);
        $this->withToken($token)->getJson('/api/v1/master-data/fakultas/data')
            ->assertOk()->assertJsonPath('data.0.nilai.nama_fakultas', 'Fakultas Uji');
        $this->withToken($token)->putJson('/api/v1/administration/users/useradmin/1', [
            'password' => 'must-not-be-reset-through-api',
        ])->assertForbidden();
        $created = $this->withToken($token)->postJson('/api/v1/master-data/fakultas', [
            'kode_fakultas' => '02',
            'nama_fakultas' => 'Fakultas API',
            'status' => 'Aktif',
        ])->assertCreated()->assertJsonPath('pesan', 'Fakultas berhasil ditambahkan.');
        $this->assertDatabaseHas('s1m4k_masterfakultas', [
            'id_fakultas' => $created->json('rekaman.id'),
            'kode_fakultas' => '02',
        ], 'simak_main');
        $this->assertDatabaseHas('log_aktivitas', [
            'id_user' => '1', 'level_user' => 'admin_ws', 'modul' => 'Data Master Fakultas',
        ], 'simak_main');
        $this->withToken($token)->getJson('/api/v1/settings/feeder')->assertForbidden();
    }

    public function test_lms_api_crud_uses_dosen_delegation_and_existing_course_validation(): void
    {
        Schema::connection('simak_main')->create('s1m4k_kalender', function (Blueprint $table): void {
            $table->string('id_kalender')->primary();
            $table->string('namatahun')->nullable();
            $table->string('tahun');
            $table->string('status')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_mastermk', function (Blueprint $table): void {
            $table->string('id_matkul')->primary();
            $table->string('id_prodi')->nullable();
            $table->string('kode_mata_kuliah')->nullable();
            $table->string('nama_mata_kuliah')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_jadwal', function (Blueprint $table): void {
            $table->string('id_jadwal')->primary();
            $table->string('id_prodi');
            $table->string('id_kalender');
            $table->string('id_matkul');
            $table->string('nama_kelas');
            foreach (range(1, 14) as $slot) {
                $table->string('dosen'.$slot)->nullable();
            }
        });
        Schema::connection('simak_main')->create('lms_bank_soal', function (Blueprint $table): void {
            $table->string('id_soal')->primary();
            $table->string('id_dosen');
            $table->string('id_matkul');
            $table->string('jenis_soal');
            $table->text('pertanyaan');
            $table->text('opsi_a')->nullable();
            $table->text('opsi_b')->nullable();
            $table->text('opsi_c')->nullable();
            $table->text('opsi_d')->nullable();
            $table->text('opsi_e')->nullable();
            $table->string('kunci_jawaban')->nullable();
            $table->boolean('aktif')->default(true);
            $table->string('dibuat_pada')->nullable();
        });
        DB::connection('simak_main')->table('s1m4k_kalender')->insert([
            'id_kalender' => 'calendar-lms', 'namatahun' => '2026 Ganjil', 'tahun' => '20261', 'status' => 'Y',
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermk')->insert([
            'id_matkul' => 'course-lms', 'id_prodi' => 'program-1',
            'kode_mata_kuliah' => 'IF101', 'nama_mata_kuliah' => 'Pemrograman',
        ]);
        DB::connection('simak_main')->table('s1m4k_jadwal')->insert([
            'id_jadwal' => 'schedule-lms', 'id_prodi' => 'program-1', 'id_kalender' => 'calendar-lms',
            'id_matkul' => 'course-lms', 'nama_kelas' => 'A', 'dosen2' => 'lecturer-1',
        ]);

        $token = $this->postJson('/api/v1/auth/token', [
            'username' => 'ws-active', 'password' => 'ws-secret-123',
        ])->assertOk()->json('akses_token');
        $payload = [
            'id_matkul' => 'course-lms', 'tahun' => '20261', 'jenis_soal' => 'essay',
            'pertanyaan' => 'Terangkan pengujian API.',
        ];
        $this->withToken($token)->postJson('/api/v1/lms/bank-soal', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('kesalahan.id_dosen.0', 'ID dosen wajib diisi untuk operasi LMS melalui Web Service.');

        $this->withToken($token)->postJson('/api/v1/lms/bank-soal', $payload + ['id_dosen' => 'lecturer-1'])
            ->assertOk()
            ->assertJsonPath('berhasil', true);
        $this->assertDatabaseHas('lms_bank_soal', [
            'id_dosen' => 'lecturer-1', 'id_matkul' => 'course-lms', 'pertanyaan' => 'Terangkan pengujian API.',
        ], 'simak_main');

        $this->post('/api/v1/lms/materi', [
            'berkas' => UploadedFile::fake()->create('lesson.pdf', 10),
        ], ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'])
            ->assertStatus(406);
    }
}
