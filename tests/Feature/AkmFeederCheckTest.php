<?php

namespace Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AkmFeederCheckTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.simak_main' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
        ]]);
        DB::purge('simak_main');
        $this->createSchema();
        $this->seedData();
        Http::preventStrayRequests();
    }

    public function test_program_roles_compare_only_their_own_program_and_save_status_only_to_akm(): void
    {
        Http::fake(function (ClientRequest $request) {
            if (($request['act'] ?? '') === 'GetToken') {
                return Http::response(['error_code' => '0', 'data' => ['token' => 'akm-token']]);
            }
            if (($request['act'] ?? '') === 'GetAktivitasKuliahMahasiswa') {
                return Http::response(['error_code' => '0', 'data' => [['nim' => 'NIM-1', 'nama_status_mahasiswa' => 'Aktif']]]);
            }

            return Http::response(['error_code' => '0', 'data' => []]);
        });
        $this->actingAs($this->user('userprodi', 'program-1'));

        $this->get(route('akm-feeder.index', ['program' => 'program-2']))->assertNotFound();
        $this->getJson(route('akm-feeder.data', [
            'program' => 'program-2', 'calendar' => 'calendar-2',
        ]))->assertNotFound();

        $this->get(route('akm-feeder.index', ['program' => 'program-1', 'calendar' => 'calendar-1']))
            ->assertOk()->assertSee('data-akm-feeder-table', false);
        $this->getJson(route('akm-feeder.data', [
            'program' => 'program-1', 'calendar' => 'calendar-1', 'draw' => 1, 'start' => 0, 'length' => 25,
        ]))
            ->assertOk()
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('data.0.nim', 'NIM-1')
            ->assertJsonPath('data.0.status_simak', 'A')
            ->assertJsonPath('data.0.status_feeder', 'Aktif');
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'GetAktivitasKuliahMahasiswa'
            && $request['filter'] === "id_semester='20261' AND nim='NIM-1'");

        $this->postJson(route('akm-feeder.status.update'), [
            'program' => 'program-1', 'calendar' => 'calendar-1', 'id_ipmhs' => 'akm-1', 'status' => 'C',
        ])->assertOk()->assertJsonPath('message', 'Status AKM SIMAK berhasil diperbarui.');
        $this->assertDatabaseHas('s1m4k_akm', ['id_ipmhs' => 'akm-1', 'status_mahasiswa' => 'C'], 'simak_main');
        $this->assertDatabaseHas('s1m4k_mastermhs', ['id_mahasiswa' => 'student-1', 'id_status_mahasiswa' => 'A'], 'simak_main');
        $this->postJson(route('akm-feeder.status.update'), [
            'program' => 'program-1', 'calendar' => 'calendar-1', 'id_ipmhs' => 'akm-2', 'status' => 'C',
        ])->assertNotFound();
        $this->assertDatabaseHas('s1m4k_akm', ['id_ipmhs' => 'akm-2', 'status_mahasiswa' => 'A'], 'simak_main');
        $this->assertSame(1, DB::connection('simak_main')->table('log_aktivitas')
            ->where('modul', 'Cek AKM SIMAK - Feeder')->count());
    }

    public function test_admin_can_choose_program_and_operator_without_assignment_is_denied(): void
    {
        $this->actingAs($this->user('admin'));
        $this->get(route('akm-feeder.index', ['program' => 'program-1']))
            ->assertOk()->assertSee('Program Satu')->assertSee('Program Dua');

        $this->actingAs($this->user('usertu', ''));
        $this->get(route('akm-feeder.index'))->assertForbidden();
        $this->getJson(route('akm-feeder.data', ['program' => 'program-1', 'calendar' => 'calendar-1']))
            ->assertForbidden();

        $this->actingAs($this->user('mahasiswa', 'program-1'));
        $this->get(route('akm-feeder.index'))->assertForbidden();
    }

    public function test_feeder_error_description_is_returned_for_the_status_check_notification(): void
    {
        Http::fake(function (ClientRequest $request) {
            return match ($request['act'] ?? '') {
                'GetToken' => Http::response(['error_code' => '0', 'data' => ['token' => 'akm-token']]),
                'GetAktivitasKuliahMahasiswa' => Http::response([
                    'error_code' => '91',
                    'error_desc' => 'Koneksi database Feeder gagal',
                ]),
                default => Http::response(['error_code' => '0', 'data' => []]),
            };
        });
        $this->actingAs($this->user('admin', 'admin-1'));

        $this->getJson(route('akm-feeder.data', [
            'program' => 'program-1',
            'calendar' => 'calendar-1',
        ]))->assertOk()
            ->assertJsonPath('feederError', 'Status Feeder gagal dimuat: Feeder gagal (91): Koneksi database Feeder gagal');
    }

    private function createSchema(): void
    {
        Schema::connection('simak_main')->create('s1m4k_masterpt', function (Blueprint $table): void {
            $table->string('id_perguruan_tinggi')->primary();
            $table->string('alamat_feeder')->nullable();
            $table->string('user_feeder')->nullable();
            $table->string('password_feeder')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterprodi', function (Blueprint $table): void {
            $table->string('id_prodi')->primary();
            $table->string('nama_program_studi');
            $table->string('nama_jenjang_pendidikan')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_kalender', function (Blueprint $table): void {
            $table->string('id_kalender')->primary();
            $table->string('id_prodi');
            $table->string('tahun');
            $table->string('namatahun');
        });
        Schema::connection('simak_main')->create('s1m4k_mastermhs', function (Blueprint $table): void {
            $table->string('id_mahasiswa')->primary();
            $table->string('id_prodi');
            $table->string('id_status_mahasiswa');
            $table->string('nim');
            $table->string('nama_mahasiswa');
        });
        Schema::connection('simak_main')->create('s1m4k_akm', function (Blueprint $table): void {
            $table->string('id_ipmhs')->primary();
            $table->string('id_mahasiswa');
            $table->string('id_prodi');
            $table->string('id_kalender');
            $table->string('status_mahasiswa');
        });
        Schema::connection('simak_main')->create('s1m4k_kodereferensi', function (Blueprint $table): void {
            $table->string('id_referensi')->primary();
            $table->string('kode_referensi');
            $table->string('ket_referensi');
            $table->string('keterangan');
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

    private function seedData(): void
    {
        DB::connection('simak_main')->table('s1m4k_masterpt')->insert([
            'id_perguruan_tinggi' => 'pt-1', 'alamat_feeder' => 'https://feeder.example.test',
            'user_feeder' => 'feeder-user', 'password_feeder' => $this->encrypt('feeder-secret'),
        ]);
        DB::connection('simak_main')->table('s1m4k_masterprodi')->insert([
            ['id_prodi' => 'program-1', 'nama_program_studi' => 'Program Satu', 'nama_jenjang_pendidikan' => 'S1'],
            ['id_prodi' => 'program-2', 'nama_program_studi' => 'Program Dua', 'nama_jenjang_pendidikan' => 'S1'],
        ]);
        DB::connection('simak_main')->table('s1m4k_kalender')->insert([
            ['id_kalender' => 'calendar-1', 'id_prodi' => 'program-1', 'tahun' => '20261', 'namatahun' => '2026 Ganjil'],
            ['id_kalender' => 'calendar-2', 'id_prodi' => 'program-2', 'tahun' => '20261', 'namatahun' => '2026 Ganjil Dua'],
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            ['id_mahasiswa' => 'student-1', 'id_prodi' => 'program-1', 'id_status_mahasiswa' => 'A', 'nim' => 'NIM-1', 'nama_mahasiswa' => 'Mahasiswa Satu'],
            ['id_mahasiswa' => 'student-2', 'id_prodi' => 'program-2', 'id_status_mahasiswa' => 'A', 'nim' => 'NIM-2', 'nama_mahasiswa' => 'Mahasiswa Dua'],
        ]);
        DB::connection('simak_main')->table('s1m4k_akm')->insert([
            ['id_ipmhs' => 'akm-1', 'id_mahasiswa' => 'student-1', 'id_prodi' => 'program-1', 'id_kalender' => 'calendar-1', 'status_mahasiswa' => 'A'],
            ['id_ipmhs' => 'akm-2', 'id_mahasiswa' => 'student-2', 'id_prodi' => 'program-2', 'id_kalender' => 'calendar-2', 'status_mahasiswa' => 'A'],
        ]);
        DB::connection('simak_main')->table('s1m4k_kodereferensi')->insert([
            ['id_referensi' => 'status-a', 'kode_referensi' => 'A', 'ket_referensi' => 'Aktif', 'keterangan' => 'GetStatusMahasiswa'],
            ['id_referensi' => 'status-c', 'kode_referensi' => 'C', 'ket_referensi' => 'Cuti', 'keterangan' => 'GetStatusMahasiswa'],
            ['id_referensi' => 'status-n', 'kode_referensi' => 'N', 'ket_referensi' => 'Non-Aktif', 'keterangan' => 'GetStatusMahasiswa'],
            ['id_referensi' => 'status-g', 'kode_referensi' => 'G', 'ket_referensi' => 'Double Degree', 'keterangan' => 'GetStatusMahasiswa'],
        ]);
    }

    private function user(string $role, string $programId = 'program-1'): GenericUser
    {
        return new GenericUser([
            'id' => $role.'-1', 'legacy_id' => $role.'-1', 'role' => $role,
            'id_prodi' => $programId, 'login' => $role.'-login', 'name' => ucfirst($role),
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
