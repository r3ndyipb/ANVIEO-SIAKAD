<?php

namespace Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ClassTransferTest extends TestCase
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

    public function test_admin_and_program_user_can_move_a_student_to_another_class_of_the_same_course(): void
    {
        $this->actingAs($this->user('admin', 'admin-1'));
        $this->get(route('class-transfers.index', ['search' => '2025001']))
            ->assertOk()
            ->assertSee('Mahasiswa Satu')
            ->assertSee('Kelas Lama')
            ->assertSee('Kelas Tujuan')
            ->assertSee('data-class-transfer-select', false)
            ->assertSee('aria-label="Pindahkan kelas"', false);

        $this->put(route('class-transfers.update', ['student' => 'student-1', 'krs' => 'krs-1']), [
            'id_jadwal' => 'schedule-target',
        ])->assertRedirect(route('class-transfers.index', ['search' => '2025001']))
            ->assertSessionHas('status');

        $this->assertSame('schedule-target', DB::connection('simak_main')->table('s1m4k_krs')
            ->where('id_krs', 'krs-1')->value('id_jadwal'));
        $this->assertSame(1, DB::connection('simak_main')->table('log_aktivitas')
            ->where('modul', 'Transfer Kelas')->count());
    }

    public function test_class_transfer_enforces_registration_course_calendar_and_program_scope(): void
    {
        $this->actingAs($this->user('userprodi', 'operator-1', ['id_prodi' => 'program-1']));
        $this->put(route('class-transfers.update', ['student' => 'student-1', 'krs' => 'krs-1']), [
            'id_jadwal' => 'schedule-other-course',
        ])->assertSessionHasErrors('id_jadwal');

        $this->put(route('class-transfers.update', ['student' => 'student-2', 'krs' => 'krs-2']), [
            'id_jadwal' => 'schedule-target',
        ])->assertNotFound();

        DB::connection('simak_main')->table('s1m4k_registrasiulang')->delete();
        $this->put(route('class-transfers.update', ['student' => 'student-1', 'krs' => 'krs-1']), [
            'id_jadwal' => 'schedule-target',
        ])->assertStatus(422);
        $this->assertSame('schedule-current', DB::connection('simak_main')->table('s1m4k_krs')
            ->where('id_krs', 'krs-1')->value('id_jadwal'));
    }

    private function createSchema(): void
    {
        Schema::connection('simak_main')->create('s1m4k_mastermhs', function (Blueprint $table): void {
            $table->string('id_mahasiswa')->primary();
            $table->string('nim');
            $table->string('nama_mahasiswa');
            $table->string('id_prodi');
        });
        Schema::connection('simak_main')->create('s1m4k_masterprodi', function (Blueprint $table): void {
            $table->string('id_prodi')->primary();
            $table->string('nama_program_studi');
        });
        Schema::connection('simak_main')->create('s1m4k_kalender', function (Blueprint $table): void {
            $table->string('id_kalender')->primary();
            $table->string('id_prodi');
            $table->string('tahun');
            $table->string('namatahun');
            $table->string('status');
        });
        Schema::connection('simak_main')->create('s1m4k_registrasiulang', function (Blueprint $table): void {
            $table->string('id_registrasi')->primary();
            $table->string('id_mahasiswa');
            $table->string('id_kalender');
        });
        Schema::connection('simak_main')->create('s1m4k_mastermk', function (Blueprint $table): void {
            $table->string('id_matkul')->primary();
            $table->string('kode_mata_kuliah');
            $table->string('nama_mata_kuliah');
            $table->string('semester')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_jadwal', function (Blueprint $table): void {
            $table->string('id_jadwal')->primary();
            $table->string('id_prodi');
            $table->string('id_kalender');
            $table->string('id_matkul');
            $table->string('nama_kelas');
        });
        Schema::connection('simak_main')->create('s1m4k_krs', function (Blueprint $table): void {
            $table->string('id_krs')->primary();
            $table->string('id_mahasiswa');
            $table->string('id_prodi');
            $table->string('id_kalender');
            $table->string('id_matkul');
            $table->string('id_jadwal');
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
            ['id_prodi' => 'program-1', 'nama_program_studi' => 'Program Satu'],
            ['id_prodi' => 'program-2', 'nama_program_studi' => 'Program Dua'],
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            ['id_mahasiswa' => 'student-1', 'nim' => '2025001', 'nama_mahasiswa' => 'Mahasiswa Satu', 'id_prodi' => 'program-1'],
            ['id_mahasiswa' => 'student-2', 'nim' => '2025002', 'nama_mahasiswa' => 'Mahasiswa Dua', 'id_prodi' => 'program-2'],
        ]);
        DB::connection('simak_main')->table('s1m4k_kalender')->insert([
            ['id_kalender' => 'calendar-1', 'id_prodi' => 'program-1', 'tahun' => '20261', 'namatahun' => '2026 Ganjil', 'status' => 'Y'],
            ['id_kalender' => 'calendar-2', 'id_prodi' => 'program-2', 'tahun' => '20261', 'namatahun' => '2026 Ganjil', 'status' => 'Y'],
        ]);
        DB::connection('simak_main')->table('s1m4k_registrasiulang')->insert([
            ['id_registrasi' => 'registration-1', 'id_mahasiswa' => 'student-1', 'id_kalender' => 'calendar-1'],
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermk')->insert([
            ['id_matkul' => 'course-1', 'kode_mata_kuliah' => 'MK001', 'nama_mata_kuliah' => 'Mata Kuliah Satu'],
            ['id_matkul' => 'course-2', 'kode_mata_kuliah' => 'MK002', 'nama_mata_kuliah' => 'Mata Kuliah Dua'],
        ]);
        DB::connection('simak_main')->table('s1m4k_jadwal')->insert([
            ['id_jadwal' => 'schedule-current', 'id_prodi' => 'program-1', 'id_kalender' => 'calendar-1', 'id_matkul' => 'course-1', 'nama_kelas' => 'Kelas Lama'],
            ['id_jadwal' => 'schedule-target', 'id_prodi' => 'program-1', 'id_kalender' => 'calendar-1', 'id_matkul' => 'course-1', 'nama_kelas' => 'Kelas Tujuan'],
            ['id_jadwal' => 'schedule-other-course', 'id_prodi' => 'program-1', 'id_kalender' => 'calendar-1', 'id_matkul' => 'course-2', 'nama_kelas' => 'Kelas Lain MK'],
            ['id_jadwal' => 'schedule-2', 'id_prodi' => 'program-2', 'id_kalender' => 'calendar-2', 'id_matkul' => 'course-1', 'nama_kelas' => 'Kelas Prodi Dua'],
        ]);
        DB::connection('simak_main')->table('s1m4k_krs')->insert([
            ['id_krs' => 'krs-1', 'id_mahasiswa' => 'student-1', 'id_prodi' => 'program-1', 'id_kalender' => 'calendar-1', 'id_matkul' => 'course-1', 'id_jadwal' => 'schedule-current'],
            ['id_krs' => 'krs-2', 'id_mahasiswa' => 'student-2', 'id_prodi' => 'program-2', 'id_kalender' => 'calendar-2', 'id_matkul' => 'course-1', 'id_jadwal' => 'schedule-2'],
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
