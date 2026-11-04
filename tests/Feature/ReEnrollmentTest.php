<?php

namespace Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReEnrollmentTest extends TestCase
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
        $this->seedRecords();
    }

    public function test_only_admin_and_regulang_can_open_and_search_reenrollment(): void
    {
        foreach (['admin', 'regulang'] as $role) {
            $menuItems = collect(config('simak.navigation.'.$role))
                ->flatMap(static fn (array $group): array => $group['items']);
            $this->assertTrue($menuItems->contains(
                static fn (array $item): bool => ($item['route'] ?? null) === 're-enrollment.index'
            ));
            $this->signIn($role);
            $this->get(route('re-enrollment.index'))->assertOk()->assertSee('Registrasi Ulang');
            $this->post(route('re-enrollment.search'), ['npm' => 'NPM-001'])
                ->assertOk()
                ->assertSee('Mahasiswa Uji')
                ->assertSee('Program studi: Prodi Uji')
                ->assertDontSee('Program studi: prodi-1')
                ->assertSee('Tahun Akademik Aktif')
                ->assertSee('Belum teregistrasi')
                ->assertSee('data-reenrollment-confirm', false)
                ->assertSee('data-reenrollment-confirm-modal', false)
                ->assertSee('data-reenrollment-confirm-modal hidden', false)
                ->assertDontSee('onsubmit="return confirm(', false);
        }

        foreach (['userprodi', 'usertu', 'mahasiswa'] as $role) {
            $menuItems = collect(config('simak.navigation.'.$role))
                ->flatMap(static fn (array $group): array => $group['items']);
            $this->assertFalse($menuItems->contains(
                static fn (array $item): bool => ($item['route'] ?? null) === 're-enrollment.index'
            ));
            $this->signIn($role);
            $this->get(route('re-enrollment.index'))->assertForbidden();
            $this->post(route('re-enrollment.search'), ['npm' => 'NPM-001'])->assertForbidden();
        }
    }

    public function test_search_only_returns_active_calendar_for_student_program(): void
    {
        DB::connection('simak_main')->table('s1m4k_masterprodi')->insert([
            ['id_prodi' => 'prodi-lain', 'nama_program_studi' => 'Prodi Lain'],
        ]);
        DB::connection('simak_main')->table('s1m4k_kalender')->insert([
            ['id_kalender' => 'kalender-tidak-aktif', 'tahun' => '20252', 'namatahun' => '2025 Genap', 'id_prodi' => 'prodi-1', 'status' => 'N'],
            ['id_kalender' => 'kalender-prodi-lain', 'tahun' => '20261', 'namatahun' => '2026 Ganjil Lain', 'id_prodi' => 'prodi-lain', 'status' => 'Y'],
        ]);
        $this->signIn('admin');

        $this->post(route('re-enrollment.search'), ['npm' => 'NPM-001'])
            ->assertOk()
            ->assertSee('2026 Ganjil')
            ->assertDontSee('2025 Genap')
            ->assertDontSee('2026 Ganjil Lain');
        $this->post(route('re-enrollment.search'), ['npm' => 'NPM-TIDAK-ADA'])
            ->assertOk()
            ->assertSee('Mahasiswa tidak ditemukan');
    }

    public function test_registration_inserts_record_and_marks_ukt_paid_for_calendar_year(): void
    {
        $this->signIn('regulang');

        $this->post(route('re-enrollment.register', 'kalender-aktif'), [
            'id_mahasiswa' => 'mahasiswa-1',
        ])->assertRedirect(route('re-enrollment.index'));

        $this->get(route('re-enrollment.index'))
            ->assertOk()
            ->assertSee('Registrasi ulang berhasil disimpan.')
            ->assertSee('Telah teregistrasi')
            ->assertSee('Petugas: Petugas Registrasi');
        $this->assertDatabaseHas('s1m4k_registrasiulang', [
            'id_perguruan_tinggi' => 'pt-1',
            'id_fakultas' => 'fak-1',
            'id_prodi' => 'prodi-1',
            'id_mahasiswa' => 'mahasiswa-1',
            'id_kalender' => 'kalender-aktif',
            'user' => 'petugas-regulang',
        ], 'simak_main');
        $this->assertDatabaseHas('s1m4k_pembayaranukt', [
            'id_mahasiswa' => 'mahasiswa-1',
            'tahun' => '20261',
            'lunas' => 1,
            'petugas' => 'petugas-regulang',
        ], 'simak_main');
        $activity = DB::connection('simak_main')->table('log_aktivitas')
            ->where('modul', 'Registrasi Ulang')->first();
        $this->assertSame('Petugas Registrasi', $activity->nama_user);
        $this->assertSame('Registrasikan mahasiswa', json_decode($activity->aktifitas, true, flags: JSON_THROW_ON_ERROR)['aksi']);
        $this->assertSame(
            'mahasiswa-1',
            json_decode($activity->aktifitas, true, flags: JSON_THROW_ON_ERROR)['id_data']
        );

        $this->post(route('re-enrollment.register', 'kalender-aktif'), [
            'id_mahasiswa' => 'mahasiswa-1',
        ])->assertRedirect(route('re-enrollment.index'));
        $this->assertSame(1, DB::connection('simak_main')->table('s1m4k_registrasiulang')->count());
    }

    public function test_cancellation_is_blocked_after_krs_exists_and_resets_ukt_when_allowed(): void
    {
        DB::connection('simak_main')->table('s1m4k_registrasiulang')->insert([
            'id_registrasi' => 'registrasi-1',
            'id_perguruan_tinggi' => 'pt-1',
            'id_fakultas' => 'fak-1',
            'id_prodi' => 'prodi-1',
            'id_mahasiswa' => 'mahasiswa-1',
            'id_kalender' => 'kalender-aktif',
            'tgl_registasi' => '2026-01-01',
            'user' => 'petugas-regulang',
        ]);
        DB::connection('simak_main')->table('s1m4k_pembayaranukt')
            ->where('id', 'ukt-1')->update(['lunas' => 1]);
        $this->signIn('admin');

        $this->post(route('re-enrollment.search'), ['npm' => 'NPM-001'])
            ->assertOk()
            ->assertSee('Batal Registrasi')
            ->assertSee('button-danger button-small', false);

        DB::connection('simak_main')->table('s1m4k_krs')->insert([
            'id_krs' => 'krs-1', 'id_kalender' => 'kalender-aktif', 'id_mahasiswa' => 'mahasiswa-1',
        ]);
        $this->post(route('re-enrollment.cancel', 'kalender-aktif'), ['id_mahasiswa' => 'mahasiswa-1'])
            ->assertRedirect(route('re-enrollment.index'));
        $this->get(route('re-enrollment.index'))
            ->assertSee('sudah mengambil mata kuliah pada semester ini');
        $this->assertDatabaseHas('s1m4k_registrasiulang', ['id_registrasi' => 'registrasi-1'], 'simak_main');
        $this->assertDatabaseHas('s1m4k_pembayaranukt', ['id' => 'ukt-1', 'lunas' => 1], 'simak_main');

        DB::connection('simak_main')->table('s1m4k_krs')->delete();
        $this->post(route('re-enrollment.cancel', 'kalender-aktif'), ['id_mahasiswa' => 'mahasiswa-1'])
            ->assertRedirect(route('re-enrollment.index'));
        $this->assertDatabaseMissing('s1m4k_registrasiulang', ['id_registrasi' => 'registrasi-1'], 'simak_main');
        $this->assertDatabaseHas('s1m4k_pembayaranukt', [
            'id' => 'ukt-1', 'lunas' => 0, 'petugas' => 'petugas-admin',
        ], 'simak_main');
    }

    private function signIn(string $role): void
    {
        $this->actingAs(new GenericUser([
            'id' => $role.'-reenrollment-test',
            'legacy_type' => 'admin',
            'legacy_id' => $role === 'admin' ? 'petugas-admin' : 'petugas-regulang',
            'login' => $role.'-reenrollment-test',
            'role' => $role,
            'name' => 'Petugas Registrasi',
            'id_perguruan_tinggi' => 'pt-1',
        ]));
    }

    private function seedRecords(): void
    {
        DB::connection('simak_main')->table('s1m4k_masterpt')->insert([
            'id_perguruan_tinggi' => 'pt-1',
        ]);
        DB::connection('simak_main')->table('s1m4k_admin')->insert([
            'id_admin' => 'petugas-regulang',
            'nama_lengkap' => 'Petugas Registrasi',
        ]);
        DB::connection('simak_main')->table('s1m4k_masterprodi')->insert([
            'id_prodi' => 'prodi-1',
            'nama_program_studi' => 'Prodi Uji',
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            'id_mahasiswa' => 'mahasiswa-1',
            'nim' => 'NPM-001',
            'nama_mahasiswa' => 'Mahasiswa Uji',
            'id_prodi' => 'prodi-1',
            'id_fakultas' => 'fak-1',
        ]);
        DB::connection('simak_main')->table('s1m4k_kalender')->insert([
            'id_kalender' => 'kalender-aktif',
            'tahun' => '20261',
            'namatahun' => '2026 Ganjil',
            'id_prodi' => 'prodi-1',
            'id_fakultas' => 'fak-1',
            'status' => 'Y',
        ]);
        DB::connection('simak_main')->table('s1m4k_pembayaranukt')->insert([
            'id' => 'ukt-1',
            'id_mahasiswa' => 'mahasiswa-1',
            'tahun' => '20261',
            'nominal' => 100,
            'lunas' => 0,
        ]);
    }

    private function createSchema(): void
    {
        Schema::connection('simak_main')->create('s1m4k_masterpt', function (Blueprint $table): void {
            $table->string('id_perguruan_tinggi')->primary();
        });
        Schema::connection('simak_main')->create('s1m4k_admin', function (Blueprint $table): void {
            $table->string('id_admin')->primary();
            $table->string('nama_lengkap')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterprodi', function (Blueprint $table): void {
            $table->string('id_prodi')->primary();
            $table->string('nama_program_studi')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_mastermhs', function (Blueprint $table): void {
            $table->string('id_mahasiswa')->primary();
            $table->string('nim')->nullable();
            $table->string('nama_mahasiswa')->nullable();
            $table->string('id_prodi')->nullable();
            $table->string('id_fakultas')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_kalender', function (Blueprint $table): void {
            $table->string('id_kalender')->primary();
            $table->string('tahun')->nullable();
            $table->string('namatahun')->nullable();
            $table->string('id_prodi')->nullable();
            $table->string('id_fakultas')->nullable();
            $table->string('status')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_registrasiulang', function (Blueprint $table): void {
            $table->string('id_registrasi')->primary();
            $table->string('id_perguruan_tinggi')->nullable();
            $table->string('id_fakultas')->nullable();
            $table->string('id_prodi')->nullable();
            $table->string('id_mahasiswa')->nullable();
            $table->string('id_kalender')->nullable();
            $table->date('tgl_registasi')->nullable();
            $table->string('user')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_pembayaranukt', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('id_mahasiswa');
            $table->string('tahun');
            $table->integer('nominal');
            $table->integer('lunas');
            $table->string('petugas')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_krs', function (Blueprint $table): void {
            $table->string('id_krs')->primary();
            $table->string('id_kalender')->nullable();
            $table->string('id_mahasiswa')->nullable();
        });
        Schema::connection('simak_main')->create('log_aktivitas', function (Blueprint $table): void {
            $table->string('id_log', 36)->primary();
            $table->string('id_user', 100)->nullable();
            $table->string('level_user', 50)->nullable();
            $table->string('nama_user', 255);
            $table->string('modul', 100);
            $table->text('aktifitas');
            $table->timestamp('created_at')->nullable();
        });
    }
}
