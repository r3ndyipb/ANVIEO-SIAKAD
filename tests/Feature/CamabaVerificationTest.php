<?php

namespace Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class CamabaVerificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.simak_main' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
        ]]);
        DB::purge('simak_main');
        $this->createSimakSchema();
        $this->seedSimak();
        Http::preventStrayRequests();
    }

    public function test_admin_can_upload_camaba_candidates_from_excel_to_local_simak(): void
    {
        $this->actingAs($this->user('admin'));
        $this->get(route('camaba-verification.index'))
            ->assertOk()
            ->assertSee('data-camaba-table', false)
            ->assertSee(route('camaba-verification.template'))
            ->assertSee('Upload Data MABA')
            ->assertDontSee('Sinkron dari PMB')
            ->assertDontSee('Koneksi PMB belum dikonfigurasi');
        $this->assertFalse(Route::has('camaba-verification.sync'));

        $template = $this->get(route('camaba-verification.template'))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=template-data-maba.xlsx');
        $templatePath = tempnam(sys_get_temp_dir(), 'camaba-template-');
        self::assertNotFalse($templatePath);
        try {
            file_put_contents($templatePath, $template->streamedContent());
            $sheet = IOFactory::load($templatePath)->getActiveSheet();
            self::assertSame('Data MABA', $sheet->getTitle());
            self::assertSame('Nomor Peserta', $sheet->getCell('B1')->getValue());
            self::assertSame('Nominal Uang Kuliah', $sheet->getCell('Q1')->getValue());
            self::assertSame('@', $sheet->getStyle('C2')->getNumberFormat()->getFormatCode());
        } finally {
            unlink($templatePath);
        }

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([
            ['No', 'Nomor Peserta', 'NPM', 'NISN', 'NIK', 'Email', 'Telepon', 'Nama Mahasiswa', 'Jenis Kelamin', 'Agama', 'Jalur', 'Tempat Lahir', 'Tanggal Lahir', 'Nama Ibu', 'Kelurahan', 'Kategori Uang Kuliah', 'Nominal Uang Kuliah'],
            [1, 'P-200', '0002026002', '1234567890', '1234567890123456', 'maba@example.test', '081234', 'Mahasiswa Upload', 'P', 'Islam', 'SNBT', 'Ternate', '2008-01-02', 'Ibu Upload', 'Kelurahan', 'REGULER', 5000000],
        ]);
        $uploadPath = tempnam(sys_get_temp_dir(), 'camaba-upload-');
        self::assertNotFalse($uploadPath);
        try {
            (new Xlsx($spreadsheet))->save($uploadPath);
            $upload = UploadedFile::fake()->createWithContent('data-maba.xlsx', (string) file_get_contents($uploadPath));
        } finally {
            unlink($uploadPath);
        }

        $this->post(route('camaba-verification.upload'), ['program' => 'program-1', 'file' => $upload])
            ->assertRedirect(route('camaba-verification.index', ['period' => now()->year.'1']))
            ->assertSessionHas('status');
        $this->assertDatabaseHas('s1m4k_camaba', [
            'no_peserta' => 'P-200', 'nim' => '0002026002', 'id_prodi' => 'program-1',
            'id_jalur_daftar' => '14', 'id_jenis_daftar' => '17', 'status' => 0,
        ], 'simak_main');
        $this->assertDatabaseHas('log_aktivitas', ['modul' => 'Verifikasi CAMABA'], 'simak_main');
    }

    public function test_regulang_can_verify_camaba_through_feeder_before_local_insert(): void
    {
        $this->insertCandidate('candidate-1');
        Http::fake(function (ClientRequest $request) {
            return match ($request['act'] ?? '') {
                'GetToken' => Http::response(['error_code' => '0', 'data' => ['token' => 'token-camaba']]),
                'InsertBiodataMahasiswa' => Http::response(['error_code' => '0', 'data' => ['id_mahasiswa' => 'feeder-student-1']]),
                'InsertRiwayatPendidikanMahasiswa' => Http::response(['error_code' => '0', 'data' => ['id_registrasi_mahasiswa' => 'feeder-registration-1']]),
                default => Http::response(['error_code' => '0', 'data' => []]),
            };
        });
        $this->actingAs($this->user('regulang'));
        $this->get(route('camaba-verification.show', 'candidate-1'))
            ->assertOk()->assertSee('Simpan dan verifikasi');

        $this->post(route('camaba-verification.verify', 'candidate-1'), $this->verificationData())
            ->assertRedirect(route('camaba-verification.index'))
            ->assertSessionHas('status');
        $this->assertDatabaseHas('s1m4k_camaba', ['id' => 'candidate-1', 'status' => 1], 'simak_main');
        $this->assertDatabaseHas('s1m4k_mastermhs', [
            'id_mahasiswa' => 'feeder-student-1', 'id_registrasi_mahasiswa' => 'feeder-registration-1',
            'nim' => '2026999', 'id_prodi' => 'program-1', 'id_status_mahasiswa' => 'A',
        ], 'simak_main');
        $student = DB::connection('simak_main')->table('s1m4k_mastermhs')->where('id_mahasiswa', 'feeder-student-1')->first();
        $this->assertTrue(Hash::check('2026999', $student->password));
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'InsertBiodataMahasiswa'
            && $request['token'] === 'token-camaba'
            && $request['record']['nama_mahasiswa'] === 'Mahasiswa Verifikasi');
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'InsertRiwayatPendidikanMahasiswa'
            && $request['token'] === 'token-camaba'
            && $request['record']['id_mahasiswa'] === 'feeder-student-1'
            && $request['record']['id_prodi'] === 'program-1');
        Http::assertSentCount(3);
    }

    public function test_camaba_verification_is_role_guarded_and_feeder_failure_keeps_local_records_unverified(): void
    {
        $this->insertCandidate('candidate-1');
        $this->actingAs($this->user('userprodi'));
        $this->get(route('camaba-verification.index'))->assertForbidden();
        $this->post(route('camaba-verification.verify', 'candidate-1'), [])->assertForbidden();

        Http::fake([
            'https://feeder.example.test' => Http::response(['error_code' => '0', 'data' => ['token' => 'token-camaba']]),
        ]);
        $this->actingAs($this->user('admin'));
        $this->post(route('camaba-verification.verify', 'candidate-1'), $this->verificationData())
            ->assertSessionHasErrors('feeder');
        $this->assertDatabaseHas('s1m4k_camaba', ['id' => 'candidate-1', 'status' => 0], 'simak_main');
        $this->assertDatabaseCount('s1m4k_mastermhs', 0, 'simak_main');
    }

    private function createSimakSchema(): void
    {
        Schema::connection('simak_main')->create('s1m4k_masterpt', function (Blueprint $table): void {
            $table->string('id_perguruan_tinggi')->primary();
            $table->string('alamat_feeder')->nullable();
            $table->string('user_feeder')->nullable();
            $table->string('password_feeder')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterprodi', function (Blueprint $table): void {
            $table->string('id_prodi')->primary();
            $table->string('id_fakultas')->nullable();
            $table->string('id_perguruan_tinggi')->nullable();
            $table->string('nama_program_studi');
            $table->string('nama_jenjang_pendidikan')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_kalender', function (Blueprint $table): void {
            $table->string('id_kalender')->primary();
            $table->string('tahun');
        });
        Schema::connection('simak_main')->create('s1m4k_kodereferensi', function (Blueprint $table): void {
            $table->string('id_referensi')->primary();
            $table->string('kode_referensi')->nullable();
            $table->string('ket_referensi')->nullable();
            $table->string('ket_referensi2')->nullable();
            $table->string('nama_provinsi')->nullable();
            $table->string('keterangan')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_camaba', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('id_perguruan_tinggi');
            $table->string('id_fakultas');
            $table->string('id_prodi');
            $table->string('no_peserta');
            $table->string('nim');
            $table->string('nama_mahasiswa');
            $table->string('jenis_kelamin');
            $table->string('tempat_lahir')->nullable();
            $table->string('tanggal_lahir');
            $table->string('nama_ibu')->nullable();
            $table->string('id_agama')->nullable();
            $table->string('id_negara')->nullable();
            $table->string('nik')->nullable();
            $table->string('nisn')->nullable();
            $table->string('telepon')->nullable();
            $table->string('handphone')->nullable();
            $table->string('kelurahan')->nullable();
            $table->string('email');
            $table->string('penerima_kps');
            $table->string('id_jenis_daftar')->nullable();
            $table->string('id_jalur_daftar')->nullable();
            $table->string('id_periode_masuk');
            $table->string('tanggal_daftar');
            $table->string('id_pembiayaan')->nullable();
            $table->integer('biaya_masuk_kuliah')->nullable();
            $table->string('id_kebutuhan_khusus_mahasiswa')->nullable();
            $table->integer('status')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_mastermhs', function (Blueprint $table): void {
            $table->string('id_mahasiswa')->primary();
            $table->string('id_registrasi_mahasiswa')->nullable();
            $table->string('id_perguruan_tinggi')->nullable();
            $table->string('id_fakultas')->nullable();
            $table->string('id_prodi')->nullable();
            $table->string('dosen_pa')->nullable();
            $table->string('nim')->nullable();
            $table->string('nama_mahasiswa')->nullable();
            $table->string('password')->nullable();
            foreach ([
                'jenis_kelamin', 'jalan', 'rt', 'rw', 'dusun', 'kelurahan', 'kode_pos', 'nisn', 'nik',
                'tempat_lahir', 'tanggal_lahir', 'nama_ayah', 'tanggal_lahir_ayah', 'nik_ayah',
                'id_pendidikan_ayah', 'id_pekerjaan_ayah', 'id_penghasilan_ayah', 'id_kebutuhan_khusus_ayah',
                'nama_ibu', 'tanggal_lahir_ibu', 'nik_ibu', 'id_pendidikan_ibu', 'id_pekerjaan_ibu',
                'id_penghasilan_ibu', 'id_kebutuhan_khusus_ibu', 'nama_wali', 'tanggal_lahir_wali',
                'id_pendidikan_wali', 'id_pekerjaan_wali', 'id_penghasilan_wali', 'id_kebutuhan_khusus_mahasiswa',
                'telepon', 'handphone', 'email', 'penerima_kps', 'nomor_kps', 'npwp', 'id_wilayah',
                'id_jenis_tinggal', 'id_agama', 'id_alat_transportasi', 'id_negara', 'id_jenis_daftar',
                'id_jalur_daftar', 'id_periode_masuk', 'tanggal_daftar', 'sks_diakui', 'id_perguruan_tinggi_asal',
                'id_prodi_asal', 'id_pembiayaan', 'id_status_mahasiswa', 'nama_status_mahasiswa',
            ] as $column) {
                $table->string($column)->nullable();
            }
            $table->integer('biaya_masuk_kuliah')->nullable();
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

    private function seedSimak(): void
    {
        DB::connection('simak_main')->table('s1m4k_masterpt')->insert([
            'id_perguruan_tinggi' => 'pt-1', 'alamat_feeder' => 'https://feeder.example.test',
            'user_feeder' => 'feeder-user', 'password_feeder' => $this->encrypt('feeder-secret'),
        ]);
        DB::connection('simak_main')->table('s1m4k_masterprodi')->insert([
            ['id_prodi' => 'program-1', 'id_fakultas' => 'faculty-1', 'id_perguruan_tinggi' => 'pt-1', 'nama_program_studi' => 'Program Satu', 'nama_jenjang_pendidikan' => 'S1'],
            ['id_prodi' => 'program-2', 'id_fakultas' => 'faculty-2', 'id_perguruan_tinggi' => 'pt-1', 'nama_program_studi' => 'Program Dua', 'nama_jenjang_pendidikan' => 'S1'],
        ]);
        DB::connection('simak_main')->table('s1m4k_kalender')->insert([
            ['id_kalender' => 'calendar-1', 'tahun' => '20261'],
        ]);
        DB::connection('simak_main')->table('s1m4k_kodereferensi')->insert([
            ['id_referensi' => 'agama-1', 'kode_referensi' => '1', 'ket_referensi' => 'Islam', 'ket_referensi2' => null, 'nama_provinsi' => null, 'keterangan' => 'GetAgama'],
            ['id_referensi' => 'negara-id', 'kode_referensi' => 'ID', 'ket_referensi' => 'Indonesia', 'ket_referensi2' => null, 'nama_provinsi' => null, 'keterangan' => 'GetNegara'],
            ['id_referensi' => 'region-1', 'kode_referensi' => 'WIL-1', 'ket_referensi' => 'ID', 'ket_referensi2' => 'Ternate', 'nama_provinsi' => 'Maluku Utara', 'keterangan' => 'GetWilayah'],
            ['id_referensi' => 'jenis-1', 'kode_referensi' => '1', 'ket_referensi' => 'Baru', 'ket_referensi2' => null, 'nama_provinsi' => null, 'keterangan' => 'GetJenisPendaftaran'],
            ['id_referensi' => 'jalur-1', 'kode_referensi' => '15', 'ket_referensi' => 'SNBP', 'ket_referensi2' => null, 'nama_provinsi' => null, 'keterangan' => 'GetJalurMasuk'],
            ['id_referensi' => 'biaya-1', 'kode_referensi' => '1', 'ket_referensi' => 'Reguler', 'ket_referensi2' => null, 'nama_provinsi' => null, 'keterangan' => 'GetPembiayaan'],
        ]);
    }

    private function insertCandidate(string $id): void
    {
        DB::connection('simak_main')->table('s1m4k_camaba')->insert([
            'id' => $id, 'id_perguruan_tinggi' => 'pt-1', 'id_fakultas' => 'faculty-1', 'id_prodi' => 'program-1',
            'no_peserta' => 'P-999', 'nim' => '2026999', 'nama_mahasiswa' => 'Mahasiswa Camaba',
            'jenis_kelamin' => 'L', 'tempat_lahir' => 'Ternate', 'tanggal_lahir' => '2007-01-01',
            'nama_ibu' => 'Ibu Camaba', 'id_agama' => '1', 'id_negara' => 'ID', 'nik' => '1111111111111111',
            'nisn' => '1234567890', 'telepon' => '0800', 'handphone' => '081234', 'kelurahan' => 'Ternate',
            'email' => 'camaba@example.test', 'penerima_kps' => '0', 'id_jenis_daftar' => '1',
            'id_jalur_daftar' => '15', 'id_periode_masuk' => now()->year.'1', 'tanggal_daftar' => now()->toDateString(),
            'id_pembiayaan' => '1', 'biaya_masuk_kuliah' => 0, 'id_kebutuhan_khusus_mahasiswa' => '0', 'status' => 0,
        ]);
    }

    private function verificationData(): array
    {
        return [
            'nim' => '2026999', 'nama_mahasiswa' => 'Mahasiswa Verifikasi', 'jenis_kelamin' => 'L',
            'tempat_lahir' => 'Ternate', 'tanggal_lahir' => '2007-01-01', 'nama_ibu' => 'Ibu Verifikasi',
            'id_agama' => '1', 'id_negara' => 'ID', 'id_wilayah' => 'WIL-1', 'kelurahan' => 'Ternate',
            'nik' => '1111111111111111', 'nisn' => '1234567890', 'email' => 'verify@example.test',
            'telepon' => '0800', 'handphone' => '081234', 'penerima_kps' => '0', 'id_jenis_daftar' => '1',
            'id_jalur_daftar' => '15', 'id_periode_masuk' => now()->year.'1', 'tanggal_daftar' => now()->toDateString(),
            'id_pembiayaan' => '1', 'biaya_masuk_kuliah' => 0, 'id_prodi' => 'program-1',
        ];
    }

    private function user(string $role): GenericUser
    {
        return new GenericUser([
            'id' => $role.'-1', 'legacy_id' => $role.'-1', 'role' => $role,
            'login' => $role.'-login', 'name' => ucfirst($role),
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
