<?php

namespace Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GraduationPeriodTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.simak_main' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
        ]]);
        DB::purge('simak_main');
        Schema::connection('simak_main')->create('s1m4k_periode', function (Blueprint $table): void {
            $table->string('id_periode')->primary();
            $table->string('nama_periode');
            $table->string('tahun')->nullable();
            $table->date('tanggal_mulai');
            $table->date('tanggal_akhir');
            $table->string('jenis');
            $table->integer('kuota_wisuda')->nullable();
            $table->string('aktif')->default('N');
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
        DB::connection('simak_main')->table('s1m4k_periode')->insert([
            ['id_periode' => 'wisuda-old', 'nama_periode' => 'Wisuda Lama', 'tanggal_mulai' => '2025-01-01', 'tanggal_akhir' => '2025-02-01', 'jenis' => 'wisuda', 'kuota_wisuda' => 500, 'aktif' => 'Y'],
            ['id_periode' => 'wisuda-new', 'nama_periode' => 'Wisuda Baru', 'tanggal_mulai' => '2026-01-01', 'tanggal_akhir' => '2026-02-01', 'jenis' => 'wisuda', 'kuota_wisuda' => 600, 'aktif' => 'N'],
            ['id_periode' => 'ukt-active', 'nama_periode' => 'Uang Kuliah Aktif', 'tanggal_mulai' => '2026-01-01', 'tanggal_akhir' => '2026-06-01', 'jenis' => 'ukt', 'kuota_wisuda' => null, 'aktif' => 'Y'],
        ]);
    }

    public function test_admin_can_only_read_graduation_periods_and_wisuda_can_manage_them(): void
    {
        $this->actingAs($this->admin());
        $filters = ['draw' => 1, 'start' => 0, 'length' => 25, 'search' => ['value' => 'Baru']];

        $this->get(route('graduation-periods.index'))
            ->assertOk()
            ->assertSee('data-graduation-period-table', false)
            ->assertSee('data-can-manage="false"', false)
            ->assertDontSee('data-dialog-open="graduation-period-create"', false);
        $this->getJson(route('graduation-periods.data', $filters))
            ->assertOk()
            ->assertJsonPath('recordsTotal', 2)
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.id_periode', 'wisuda-new')
            ->assertJsonPath('data.0.update_url', null)
            ->assertJsonPath('can_manage', false);
        $this->post(route('graduation-periods.store'), [
            'nama_periode' => 'Tidak boleh',
            'tanggal_mulai' => '2026-08-01',
            'tanggal_akhir' => '2026-09-01',
            'kuota_wisuda' => 700,
        ])->assertForbidden();
        $this->put(route('graduation-periods.update', 'wisuda-new'), [])->assertForbidden();
        $this->post(route('graduation-periods.activate', 'wisuda-new'))->assertForbidden();

        $this->actingAs(new GenericUser([
            'id' => 'wisuda-1', 'legacy_id' => 'wisuda-1', 'role' => 'wisuda',
            'login' => 'wisuda', 'name' => 'Petugas Wisuda',
        ]));
        $this->get(route('graduation-periods.index'))
            ->assertOk()
            ->assertSee('data-can-manage="true"', false)
            ->assertSee('data-dialog-open="graduation-period-create"', false);
        $this->getJson(route('graduation-periods.data', $filters))
            ->assertOk()
            ->assertJsonPath('data.0.update_url', route('graduation-periods.update', 'wisuda-new'))
            ->assertJsonPath('can_manage', true);

        $this->post(route('graduation-periods.store'), [
            'nama_periode' => 'Wisuda Oktober 2026',
            'tanggal_mulai' => '2026-08-01',
            'tanggal_akhir' => '2026-09-01',
            'kuota_wisuda' => 700,
        ])->assertRedirect(route('graduation-periods.index'));
        $created = DB::connection('simak_main')->table('s1m4k_periode')
            ->where('nama_periode', 'Wisuda Oktober 2026')->first();
        $this->assertSame('wisuda', $created->jenis);
        $this->assertSame('N', $created->aktif);

        $this->put(route('graduation-periods.update', 'wisuda-new'), [
            'nama_periode' => 'Wisuda Revisi',
            'tanggal_mulai' => '2026-03-01',
            'tanggal_akhir' => '2026-04-01',
            'kuota_wisuda' => 650,
        ])->assertRedirect(route('graduation-periods.index'));
        $this->assertDatabaseHas('s1m4k_periode', [
            'id_periode' => 'wisuda-new', 'nama_periode' => 'Wisuda Revisi', 'kuota_wisuda' => 650,
        ], 'simak_main');

        $this->post(route('graduation-periods.activate', 'wisuda-new'))
            ->assertRedirect(route('graduation-periods.index'));
        $this->assertDatabaseHas('s1m4k_periode', ['id_periode' => 'wisuda-old', 'aktif' => 'N'], 'simak_main');
        $this->assertDatabaseHas('s1m4k_periode', ['id_periode' => 'wisuda-new', 'aktif' => 'Y'], 'simak_main');
        $this->assertDatabaseHas('s1m4k_periode', ['id_periode' => 'ukt-active', 'aktif' => 'Y'], 'simak_main');
        $this->assertSame(3, DB::connection('simak_main')->table('log_aktivitas')->count());
    }

    public function test_graduation_period_routes_are_role_limited_and_reject_other_period_types(): void
    {
        $this->actingAs(new GenericUser(['id' => 'operator-1', 'role' => 'userprodi']));
        $this->get(route('graduation-periods.index'))->assertForbidden();
        $this->getJson(route('graduation-periods.data'))->assertForbidden();
        $this->post(route('graduation-periods.store'), [])->assertForbidden();

        $this->actingAs(new GenericUser([
            'id' => 'wisuda-1', 'role' => 'wisuda', 'login' => 'wisuda', 'name' => 'Petugas Wisuda',
        ]));
        $this->put(route('graduation-periods.update', 'ukt-active'), [
            'nama_periode' => 'Tidak boleh', 'tanggal_mulai' => '2026-01-01',
            'tanggal_akhir' => '2026-02-01', 'kuota_wisuda' => 100,
        ])->assertNotFound();
        $this->post(route('graduation-periods.activate', 'ukt-active'))->assertNotFound();
        $this->post(route('graduation-periods.store'), [
            'nama_periode' => 'Wisuda Invalid', 'tanggal_mulai' => '2026-09-02',
            'tanggal_akhir' => '2026-09-01', 'kuota_wisuda' => 100,
        ])->assertSessionHasErrors('tanggal_akhir');
    }

    private function admin(): GenericUser
    {
        return new GenericUser([
            'id' => 'admin-1', 'legacy_id' => 'admin-1', 'role' => 'admin',
            'login' => 'admin', 'name' => 'Administrator',
        ]);
    }
}
