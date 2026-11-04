<?php

namespace Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StudentActivityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.simak_main' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]]);
        DB::purge('simak_main');
        $this->createSchema();
        $this->seedData();
    }

    public function test_admin_can_create_feeder_activity_and_add_a_scoped_participant(): void
    {
        $this->fakeFeeder([
            'InsertAktivitasMahasiswa' => ['id_aktivitas' => 'activity-new'],
            'InsertAnggotaAktivitasMahasiswa' => ['id_anggota' => 'participant-feeder-1'],
        ]);
        $this->actingAs($this->user('admin', 'admin-1'));

        $this->get(route('student-activities.index', ['program' => 'program-1']))
            ->assertOk()->assertSee('Aktivitas Mahasiswa')->assertSee('2026 Ganjil')
            ->assertSee('data-student-activity-open', false)
            ->assertSee('Tambah Aktivitas')
            ->assertSee('data-student-activity-modal data-open-on-load="false" hidden', false);
        $this->post(route('student-activities.store'), [
            'program' => 'program-1',
            'jenis_kegiatan' => '1',
            'program_mbkm' => '1',
            'judul' => 'Kegiatan Pengabdian',
            'lokasi' => 'Ternate',
            'no_sk' => 'SK-01',
            'tgl_sk' => '2026-01-10',
            'jenis_anggota' => '1',
            'ket' => 'Uji',
        ])->assertRedirect(route('student-activities.index', ['program' => 'program-1']))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('s1m4k_kegiatanmhs', [
            'id_kegiatan' => 'activity-new',
            'id_kalender' => 'calendar-1',
            'id_prodi' => 'program-1',
            'program_mbkm' => '1',
            'judul' => 'Kegiatan Pengabdian',
        ], 'simak_main');

        $this->post(route('student-activities.participants.store', 'activity-new'), [
            'id_registrasi_mahasiswa' => 'registration-1',
            'jenis_peran' => '1',
        ])->assertRedirect(route('student-activities.show', 'activity-new'))
            ->assertSessionHas('status');
        $this->assertDatabaseHas('s1m4k_peran_kegiatan', [
            'id_anggota' => 'participant-feeder-1',
            'id_aktivitas' => 'activity-new',
            'id_registrasi_mahasiswa' => 'registration-1',
            'jenis_peran' => '1',
            'status' => 'peserta',
        ], 'simak_main');
        $this->assertSame(2, DB::connection('simak_main')->table('log_aktivitas')
            ->where('modul', 'Aktivitas Mahasiswa')->count());
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'InsertAktivitasMahasiswa');
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'InsertAnggotaAktivitasMahasiswa');
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'InsertAktivitasMahasiswa'
            && ($request['record']['program_mbkm'] ?? null) === '1'
            && ($request['record']['tanggal_sk_tugas'] ?? null) === '2026-01-10');
    }

    public function test_activity_role_scope_and_feeder_failure_do_not_write_local_rows(): void
    {
        $this->fakeFeeder(['InsertAktivitasMahasiswa' => ['id_aktivitas' => 'activity-scoped']]);
        $this->actingAs($this->user('userprodi', 'operator-1', ['id_prodi' => 'program-1']));
        $this->get(route('student-activities.index'))
            ->assertOk()->assertSee('Tambah Aktivitas')->assertDontSee('Program Dua');
        $this->get(route('student-activities.index', ['program' => 'program-2']))
            ->assertOk()->assertDontSee('Program Dua');
        $this->post(route('student-activities.store'), [
            'program' => 'program-2',
            'jenis_kegiatan' => '1',
            'program_mbkm' => '0',
            'judul' => 'Kegiatan Scoped',
            'jenis_anggota' => '0',
        ])->assertRedirect(route('student-activities.index', ['program' => 'program-1']));
        $this->assertDatabaseHas('s1m4k_kegiatanmhs', [
            'id_kegiatan' => 'activity-scoped',
            'id_prodi' => 'program-1',
            'judul' => 'Kegiatan Scoped',
        ], 'simak_main');
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'InsertAktivitasMahasiswa'
            && ($request['record']['program_mbkm'] ?? null) === '0'
            && ! array_key_exists('tanggal_sk_tugas', $request['record'] ?? []));
    }

    public function test_program_activity_update_and_delete_use_their_legacy_feeder_scope(): void
    {
        $this->fakeFeeder([
            'UpdateAktivitasMahasiswa' => [],
            'DeleteAktivitasMahasiswa' => [],
        ]);
        DB::connection('simak_main')->table('s1m4k_kegiatanmhs')->insert([
            'id_kegiatan' => 'activity-existing',
            'id_kalender' => 'calendar-1',
            'id_prodi' => 'program-1',
            'jenis_kegiatan' => '1',
            'judul' => 'Kegiatan Lama',
            'jenis_anggota' => '0',
        ]);
        $this->actingAs($this->user('userprodi', 'operator-1', ['id_prodi' => 'program-1']));

        $this->put(route('student-activities.update', 'activity-existing'), [
            'jenis_kegiatan' => '1',
            'program_mbkm' => '1',
            'judul' => 'Kegiatan Baru',
            'jenis_anggota' => '0',
        ])->assertRedirect(route('student-activities.show', 'activity-existing'));
        $this->delete(route('student-activities.destroy', 'activity-existing'))->assertRedirect();

        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'UpdateAktivitasMahasiswa'
            && ($request['record']['program_mbkm'] ?? null) === '1'
            && ($request['record']['id_prodi'] ?? null) === 'program-1'
            && ($request['record']['id_semester'] ?? null) === '20261');
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'DeleteAktivitasMahasiswa'
            && ($request['key'] ?? null) === ['id_aktivitas' => 'activity-existing']
            && ! array_key_exists('record', $request->data()));
        $this->assertDatabaseMissing('s1m4k_kegiatanmhs', ['id_kegiatan' => 'activity-existing'], 'simak_main');
    }

    public function test_feeder_rejection_does_not_create_a_local_activity(): void
    {
        $this->fakeFeeder([], true);
        $this->actingAs($this->user('admin', 'admin-1'));
        $this->post(route('student-activities.store'), [
            'program' => 'program-1',
            'jenis_kegiatan' => '1',
            'program_mbkm' => '0',
            'judul' => 'Kegiatan Gagal Feeder',
            'jenis_anggota' => '0',
        ])->assertSessionHasErrors(['feeder' => 'Feeder gagal (100): Ditolak Feeder']);
        $this->assertSame(0, DB::connection('simak_main')->table('s1m4k_kegiatanmhs')->count());
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'InsertAktivitasMahasiswa');

        $this->get(route('student-activities.index', ['program' => 'program-1']))
            ->assertOk()
            ->assertSee('Feeder gagal (100): Ditolak Feeder');
    }

    private function fakeFeeder(array $successfulActions, bool $failActivityInsert = false): void
    {
        Http::fake(function (ClientRequest $request) use ($successfulActions, $failActivityInsert) {
            $action = (string) ($request['act'] ?? '');
            if ($action === 'GetToken') {
                return Http::response(['data' => ['token' => 'fake-token']]);
            }
            if ($failActivityInsert && $action === 'InsertAktivitasMahasiswa') {
                return Http::response(['error_code' => '100', 'error_desc' => 'Ditolak Feeder']);
            }

            return Http::response([
                'error_code' => '0',
                'data' => $successfulActions[$action] ?? [],
            ]);
        });
    }

    private function createSchema(): void
    {
        Schema::connection('simak_main')->create('s1m4k_masterpt', function (Blueprint $table): void {
            $table->string('id_perguruan_tinggi')->primary();
            $table->string('alamat_feeder')->nullable();
            $table->string('user_feeder')->nullable();
            $table->text('password_feeder')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterprodi', function (Blueprint $table): void {
            $table->string('id_prodi')->primary();
            $table->string('id_fakultas')->nullable();
            $table->string('nama_program_studi');
            $table->string('nama_jenjang_pendidikan')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_kalender', function (Blueprint $table): void {
            $table->string('id_kalender')->primary();
            $table->string('id_prodi');
            $table->string('tahun');
            $table->string('namatahun');
            $table->string('status');
        });
        Schema::connection('simak_main')->create('s1m4k_kodereferensi', function (Blueprint $table): void {
            $table->string('id_referensi')->primary();
            $table->string('kode_referensi');
            $table->string('ket_referensi');
            $table->string('keterangan');
            $table->string('untuk_kampus_merdeka')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_mastermhs', function (Blueprint $table): void {
            $table->string('id_mahasiswa')->primary();
            $table->string('id_registrasi_mahasiswa')->nullable();
            $table->string('id_prodi');
            $table->string('nim');
            $table->string('nama_mahasiswa');
            $table->string('nama_status_mahasiswa')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterdosen', function (Blueprint $table): void {
            $table->string('id_dosen')->primary();
            $table->string('nama_dosen');
            $table->string('gelar_depan')->nullable();
            $table->string('gelar_belakang')->nullable();
            $table->string('nidn')->nullable();
            $table->string('id_status_aktif')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_kegiatanmhs', function (Blueprint $table): void {
            $table->string('id_kegiatan')->primary();
            $table->string('id_kalender');
            $table->string('id_prodi');
            $table->string('id_mahasiswa')->nullable();
            $table->string('jenis_kegiatan');
            $table->text('judul');
            $table->string('lokasi')->nullable();
            $table->string('no_sk')->nullable();
            $table->date('tgl_sk')->nullable();
            $table->string('jenis_anggota');
            $table->tinyInteger('program_mbkm')->nullable();
            $table->text('ket')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_peran_kegiatan', function (Blueprint $table): void {
            $table->string('id_peran')->primary();
            $table->string('id_anggota')->nullable();
            $table->string('id_bimbing_mahasiswa')->nullable();
            $table->string('id_uji')->nullable();
            $table->string('id_aktivitas');
            $table->string('id_registrasi_mahasiswa')->nullable();
            $table->string('id_kategori')->nullable();
            $table->string('id_dosen')->nullable();
            $table->string('jenis_peran')->nullable();
            $table->string('pembimbing_ke')->nullable();
            $table->string('penguji_ke')->nullable();
            $table->string('status');
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
            'id_perguruan_tinggi' => 'pt-1',
            'alamat_feeder' => 'https://feeder.example.test',
            'user_feeder' => 'dummy-user',
            'password_feeder' => $this->encryptedFeederPassword('fake-password'),
        ]);
        DB::connection('simak_main')->table('s1m4k_masterprodi')->insert([
            ['id_prodi' => 'program-1', 'id_fakultas' => 'faculty-1', 'nama_program_studi' => 'Program Satu', 'nama_jenjang_pendidikan' => 'S1'],
            ['id_prodi' => 'program-2', 'id_fakultas' => 'faculty-2', 'nama_program_studi' => 'Program Dua', 'nama_jenjang_pendidikan' => 'S1'],
        ]);
        DB::connection('simak_main')->table('s1m4k_kalender')->insert([
            ['id_kalender' => 'calendar-1', 'id_prodi' => 'program-1', 'tahun' => '20261', 'namatahun' => '2026 Ganjil', 'status' => 'Y'],
            ['id_kalender' => 'calendar-2', 'id_prodi' => 'program-2', 'tahun' => '20261', 'namatahun' => '2026 Ganjil', 'status' => 'Y'],
        ]);
        DB::connection('simak_main')->table('s1m4k_kodereferensi')->insert([
            ['id_referensi' => 'activity-type-1', 'kode_referensi' => '1', 'ket_referensi' => 'Pengabdian', 'keterangan' => 'GetJenisAktivitasMahasiswa', 'untuk_kampus_merdeka' => '1'],
            ['id_referensi' => 'category-1', 'kode_referensi' => 'A', 'ket_referensi' => 'Pembimbing', 'keterangan' => 'getkategorikegiatan', 'untuk_kampus_merdeka' => null],
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            ['id_mahasiswa' => 'student-1', 'id_registrasi_mahasiswa' => 'registration-1', 'id_prodi' => 'program-1', 'nim' => '2025001', 'nama_mahasiswa' => 'Mahasiswa Satu', 'nama_status_mahasiswa' => 'aktif'],
            ['id_mahasiswa' => 'student-2', 'id_registrasi_mahasiswa' => 'registration-2', 'id_prodi' => 'program-2', 'nim' => '2025002', 'nama_mahasiswa' => 'Mahasiswa Dua', 'nama_status_mahasiswa' => 'aktif'],
        ]);
        DB::connection('simak_main')->table('s1m4k_masterdosen')->insert([
            ['id_dosen' => 'lecturer-1', 'nama_dosen' => 'Dosen Satu', 'nidn' => 'NIDN-1', 'id_status_aktif' => '1'],
        ]);
    }

    private function user(string $role, string $id, array $extra = []): GenericUser
    {
        return new GenericUser([
            'id' => $id,
            'legacy_id' => $id,
            'role' => $role,
            'login' => 'admin-login',
            'name' => 'Pengguna Uji',
            ...$extra,
        ]);
    }

    private function encryptedFeederPassword(string $plainText): string
    {
        $key = '979a218e0632df2935317f98d47956c7';
        $encrypted = '';
        for ($index = 0, $length = strlen($plainText); $index < $length; $index++) {
            $keyCharacter = substr($key, ($index % strlen($key)) - 1, 1);
            $encrypted .= chr((ord($plainText[$index]) + ord($keyCharacter)) % 256);
        }

        return urlencode(base64_encode($encrypted));
    }
}
