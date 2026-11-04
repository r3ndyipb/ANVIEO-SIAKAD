<?php

namespace Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ActivityTeachingTest extends TestCase
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

    public function test_activity_teaching_lists_active_calendar_schedules_and_all_assigned_teachers(): void
    {
        $this->actingAs($this->user('admin', 'admin-1'));
        $this->get(route('activity-teaching.index', ['program' => 'program-1']))
            ->assertOk()
            ->assertSee('Aktivitas Mengajar')
            ->assertSee('2026 Ganjil')
            ->assertSee('Mata Kuliah Satu')
            ->assertSee('Dosen Satu')
            ->assertSee('Asisten Satu')
            ->assertSee('value="rencana"', false)
            ->assertSee('value="realisasi"', false)
            ->assertSee('data-autosave-select', false)
            ->assertSee('data-autosave-form', false)
            ->assertDontSee('Simpan rencana')
            ->assertSee('value="16"', false);

        $this->actingAs($this->user('pimfak', 'faculty-leader', ['id_fakultas' => 'faculty-1']));
        $this->get(route('activity-teaching.index', ['program' => 'program-2']))->assertNotFound();
        $this->get(route('activity-teaching.index', ['program' => 'program-1']))
            ->assertOk()->assertSee('Dosen Satu')->assertDontSee('name="rencana"', false);
    }

    public function test_admin_and_program_operators_can_update_assigned_teacher_meetings(): void
    {
        $this->actingAs($this->user('admin', 'admin-1'));
        $this->post(route('activity-teaching.update', 'schedule-1'), [
            'program' => 'program-1',
            'teacher_id' => 'lecturer-1',
            'teacher_type' => 'dosen',
            'field' => 'rencana',
            'meeting_count' => '16',
        ])->assertRedirect(route('activity-teaching.index', ['program' => 'program-1']))
            ->assertSessionHas('status');

        $activity = DB::connection('simak_main')->table('s1m4k_akd')->where('id_dosen', 'lecturer-1')->first();
        $this->assertSame('16', $activity->rencana);
        $this->assertSame('dosen', $activity->ket);
        $this->assertSame('calendar-1', $activity->id_kalender);
        $this->assertSame(1, DB::connection('simak_main')->table('log_aktivitas')
            ->where('modul', 'Aktivitas Mengajar')->count());

        $this->post(route('activity-teaching.update', 'schedule-1'), [
            'program' => 'program-1',
            'teacher_id' => 'other-lecturer',
            'teacher_type' => 'dosen',
            'field' => 'realisasi',
            'meeting_count' => '2',
        ])->assertNotFound();
        $this->post(route('activity-teaching.update', 'schedule-1'), [
            'program' => 'program-1',
            'teacher_id' => 'lecturer-1',
            'teacher_type' => 'dosen',
            'field' => 'realisasi',
            'meeting_count' => '17',
        ])->assertSessionHasErrors('meeting_count');

        foreach ([['userprodi', 'operator-prodi'], ['usertu', 'operator-tu']] as [$role, $id]) {
            $this->actingAs($this->user($role, $id, ['id_prodi' => 'program-1']));
            $this->post(route('activity-teaching.update', 'schedule-1'), [
                'program' => 'program-1',
                'teacher_id' => 'lecturer-1',
                'field' => 'realisasi',
                'meeting_count' => '5',
            ])->assertRedirect(route('activity-teaching.index', ['program' => 'program-1']))
                ->assertSessionHas('status');
        }
        $this->assertSame('5', DB::connection('simak_main')->table('s1m4k_akd')
            ->where('id_dosen', 'lecturer-1')->value('realisasi'));
    }

    private function createSchema(): void
    {
        Schema::connection('simak_main')->create('s1m4k_masterprodi', function (Blueprint $table): void {
            $table->string('id_prodi')->primary();
            $table->string('id_fakultas');
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
        Schema::connection('simak_main')->create('s1m4k_mastermk', function (Blueprint $table): void {
            $table->string('id_matkul')->primary();
            $table->string('kode_mata_kuliah');
            $table->string('nama_mata_kuliah');
        });
        Schema::connection('simak_main')->create('s1m4k_masterdosen', function (Blueprint $table): void {
            $table->string('id_dosen')->primary();
            $table->string('nama_dosen');
            $table->string('gelar_depan')->nullable();
            $table->string('gelar_belakang')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterasdos', function (Blueprint $table): void {
            $table->string('id_asdos')->primary();
            $table->string('nama');
            $table->string('gelard')->nullable();
            $table->string('gelarb')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_jadwal', function (Blueprint $table): void {
            $table->string('id_jadwal')->primary();
            $table->string('id_prodi');
            $table->string('id_kalender');
            $table->string('id_matkul');
            for ($slot = 1; $slot <= 14; $slot++) {
                $table->string('dosen'.$slot)->nullable();
            }
            $table->string('nama_kelas');
        });
        Schema::connection('simak_main')->create('s1m4k_akd', function (Blueprint $table): void {
            $table->string('id_akd')->primary();
            $table->string('id_jadwal');
            $table->string('id_dosen');
            $table->string('rencana')->nullable();
            $table->string('realisasi')->nullable();
            $table->string('ket')->nullable();
            $table->string('id_kalender');
            $table->string('tahun');
            $table->string('id_prodi');
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
        DB::connection('simak_main')->table('s1m4k_masterprodi')->insert([
            ['id_prodi' => 'program-1', 'id_fakultas' => 'faculty-1', 'nama_program_studi' => 'Program Satu', 'nama_jenjang_pendidikan' => 'S1'],
            ['id_prodi' => 'program-2', 'id_fakultas' => 'faculty-2', 'nama_program_studi' => 'Program Dua', 'nama_jenjang_pendidikan' => 'S1'],
        ]);
        DB::connection('simak_main')->table('s1m4k_kalender')->insert([
            ['id_kalender' => 'calendar-1', 'id_prodi' => 'program-1', 'tahun' => '20261', 'namatahun' => '2026 Ganjil', 'status' => 'Y'],
            ['id_kalender' => 'calendar-2', 'id_prodi' => 'program-2', 'tahun' => '20261', 'namatahun' => '2026 Ganjil', 'status' => 'Y'],
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermk')->insert([
            ['id_matkul' => 'course-1', 'kode_mata_kuliah' => 'MK001', 'nama_mata_kuliah' => 'Mata Kuliah Satu'],
            ['id_matkul' => 'course-2', 'kode_mata_kuliah' => 'MK002', 'nama_mata_kuliah' => 'Mata Kuliah Dua'],
        ]);
        DB::connection('simak_main')->table('s1m4k_masterdosen')->insert([
            ['id_dosen' => 'lecturer-1', 'nama_dosen' => 'Dosen Satu', 'gelar_depan' => 'Dr.', 'gelar_belakang' => null],
            ['id_dosen' => 'other-lecturer', 'nama_dosen' => 'Dosen Dua', 'gelar_depan' => null, 'gelar_belakang' => null],
        ]);
        DB::connection('simak_main')->table('s1m4k_masterasdos')->insert([
            ['id_asdos' => 'assistant-1', 'nama' => 'Asisten Satu'],
        ]);
        $slots = array_fill_keys(array_map(fn (int $slot): string => 'dosen'.$slot, range(1, 14)), null);
        DB::connection('simak_main')->table('s1m4k_jadwal')->insert([
            ['id_jadwal' => 'schedule-1', 'id_prodi' => 'program-1', 'id_kalender' => 'calendar-1', 'id_matkul' => 'course-1', 'nama_kelas' => 'Kelas Satu', ...$slots, 'dosen1' => 'lecturer-1', 'dosen2' => 'assistant-1'],
            ['id_jadwal' => 'schedule-2', 'id_prodi' => 'program-2', 'id_kalender' => 'calendar-2', 'id_matkul' => 'course-2', 'nama_kelas' => 'Kelas Dua', ...$slots, 'dosen1' => 'other-lecturer'],
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
}
