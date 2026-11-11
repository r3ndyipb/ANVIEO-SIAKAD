<?php

namespace Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PersonalAcademicDashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.simak_main' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
        ]]);
        DB::purge('simak_main');

        Schema::connection('simak_main')->create('s1m4k_mastermhs', function (Blueprint $table): void {
            $table->string('id_mahasiswa')->primary();
            $table->string('nim');
            $table->string('nama_mahasiswa');
            $table->string('id_prodi');
        });
        Schema::connection('simak_main')->create('s1m4k_kalender', function (Blueprint $table): void {
            $table->string('id_kalender')->primary();
            $table->string('id_prodi');
            $table->string('tahun');
            $table->string('namatahun');
            $table->string('status');
        });
        Schema::connection('simak_main')->create('s1m4k_akm', function (Blueprint $table): void {
            $table->string('id_ipmhs')->primary();
            $table->string('id_mahasiswa');
            $table->string('id_kalender');
            $table->string('tahun');
            $table->string('ips')->nullable();
            $table->string('ipk')->nullable();
            $table->string('sks_semester')->nullable();
            $table->string('sks_lulus')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_krs', function (Blueprint $table): void {
            $table->string('id_krs')->primary();
            $table->string('id_mahasiswa');
            $table->string('id_kalender');
            $table->string('id_matkul');
            $table->string('nilai_huruf')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_mastermk', function (Blueprint $table): void {
            $table->string('id_matkul')->primary();
            $table->string('kode_mata_kuliah');
            $table->string('nama_mata_kuliah');
            $table->integer('sks_mata_kuliah');
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
        Schema::connection('simak_main')->create('s1m4k_masterprodi', function (Blueprint $table): void {
            $table->string('id_prodi')->primary();
            $table->string('nama_program_studi');
            $table->string('nama_jenjang_pendidikan')->nullable();
            $table->string('status')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterdosen', function (Blueprint $table): void {
            $table->string('id_dosen')->primary();
        });
        Schema::connection('simak_main')->create('s1m4k_akd', function (Blueprint $table): void {
            $table->string('id_akd')->primary();
            $table->string('id_jadwal');
            $table->string('id_dosen');
            $table->string('rencana')->nullable();
            $table->string('realisasi')->nullable();
        });
    }

    public function test_student_dashboard_shows_only_their_enrollment_and_own_akm_history(): void
    {
        $connection = DB::connection('simak_main');
        $connection->table('s1m4k_mastermhs')->insert([
            ['id_mahasiswa' => 'student-own', 'nim' => 'NIM-OWN', 'nama_mahasiswa' => 'Mahasiswa Sendiri', 'id_prodi' => 'program-1'],
            ['id_mahasiswa' => 'student-other', 'nim' => 'NIM-OTHER', 'nama_mahasiswa' => 'Mahasiswa Lain', 'id_prodi' => 'program-1'],
        ]);
        $connection->table('s1m4k_masterprodi')->insert([
            'id_prodi' => 'program-1', 'nama_program_studi' => 'Program Satu', 'status' => 'A',
        ]);
        $connection->table('s1m4k_kalender')->insert([
            ['id_kalender' => 'calendar-active', 'id_prodi' => 'program-1', 'tahun' => '20261', 'namatahun' => '2026 Ganjil', 'status' => 'Y'],
        ]);
        $connection->table('s1m4k_akm')->insert([
            ['id_ipmhs' => 'akm-own', 'id_mahasiswa' => 'student-own', 'id_kalender' => 'calendar-active', 'tahun' => '20261', 'ips' => '3.75', 'ipk' => '3.50', 'sks_semester' => '20', 'sks_lulus' => '80'],
            ['id_ipmhs' => 'akm-other', 'id_mahasiswa' => 'student-other', 'id_kalender' => 'calendar-active', 'tahun' => '20261', 'ips' => '1.00', 'ipk' => '1.00', 'sks_semester' => '12', 'sks_lulus' => '12'],
        ]);
        $connection->table('s1m4k_mastermk')->insert([
            ['id_matkul' => 'course-own', 'kode_mata_kuliah' => 'MK-OWN', 'nama_mata_kuliah' => 'Mata Kuliah Sendiri', 'sks_mata_kuliah' => 3],
        ]);
        $connection->table('s1m4k_krs')->insert([
            ['id_krs' => 'krs-own', 'id_mahasiswa' => 'student-own', 'id_kalender' => 'calendar-active', 'id_matkul' => 'course-own', 'nilai_huruf' => null],
            ['id_krs' => 'krs-other', 'id_mahasiswa' => 'student-other', 'id_kalender' => 'calendar-active', 'id_matkul' => 'course-own', 'nilai_huruf' => 'A'],
        ]);

        $this->actingAs($this->user('mahasiswa', 'student-own'));
        $this->get(route('dashboard'))->assertOk()
            ->assertSee('data-page-loader')
            ->assertSee('logo.png')
            ->assertSee('loading')
            ->assertSee('Aktivitas Akademik Saya')
            ->assertSee('NIM-OWN')
            ->assertSee('Mata Kuliah Sendiri')
            ->assertSee('3.75')
            ->assertSee('Grafik SKS mata kuliah semester aktif')
            ->assertSee('Grafik riwayat IPS dan IPK maksimal 4')
            ->assertDontSee('NIM-OTHER')
            ->assertDontSee('1.00');
    }

    public function test_lecturer_dashboard_summarizes_only_their_active_teaching_assignments(): void
    {
        $connection = DB::connection('simak_main');
        $connection->table('s1m4k_masterdosen')->insert([
            ['id_dosen' => 'lecturer-own'],
            ['id_dosen' => 'lecturer-other'],
        ]);
        $connection->table('s1m4k_kalender')->insert([
            ['id_kalender' => 'calendar-active', 'id_prodi' => 'program-1', 'tahun' => '20261', 'namatahun' => '2026 Ganjil', 'status' => 'Y'],
            ['id_kalender' => 'calendar-old', 'id_prodi' => 'program-1', 'tahun' => '20252', 'namatahun' => '2025 Genap', 'status' => 'N'],
        ]);
        $connection->table('s1m4k_masterprodi')->insert([
            ['id_prodi' => 'program-1', 'nama_program_studi' => 'Program Satu', 'status' => 'A'],
        ]);
        $connection->table('s1m4k_mastermk')->insert([
            ['id_matkul' => 'course-own', 'kode_mata_kuliah' => 'MK-OWN', 'nama_mata_kuliah' => 'Mata Kuliah Dosen', 'sks_mata_kuliah' => 3],
            ['id_matkul' => 'course-other', 'kode_mata_kuliah' => 'MK-OTHER', 'nama_mata_kuliah' => 'Mata Kuliah Lain', 'sks_mata_kuliah' => 2],
        ]);
        $slots = array_fill_keys(array_map(fn (int $slot): string => 'dosen'.$slot, range(1, 14)), null);
        $connection->table('s1m4k_jadwal')->insert([
            ['id_jadwal' => 'schedule-own', 'id_prodi' => 'program-1', 'id_kalender' => 'calendar-active', 'id_matkul' => 'course-own', 'nama_kelas' => 'Kelas A', ...$slots, 'dosen1' => 'lecturer-own'],
            ['id_jadwal' => 'schedule-other', 'id_prodi' => 'program-1', 'id_kalender' => 'calendar-active', 'id_matkul' => 'course-other', 'nama_kelas' => 'Kelas B', ...$slots, 'dosen1' => 'lecturer-other'],
            ['id_jadwal' => 'schedule-old', 'id_prodi' => 'program-1', 'id_kalender' => 'calendar-old', 'id_matkul' => 'course-other', 'nama_kelas' => 'Kelas C', ...$slots, 'dosen1' => 'lecturer-own'],
        ]);
        $connection->table('s1m4k_akd')->insert([
            ['id_akd' => 'activity-own', 'id_jadwal' => 'schedule-own', 'id_dosen' => 'lecturer-own', 'rencana' => '16', 'realisasi' => '10'],
            ['id_akd' => 'activity-other', 'id_jadwal' => 'schedule-other', 'id_dosen' => 'lecturer-other', 'rencana' => '14', 'realisasi' => '14'],
        ]);

        $this->actingAs($this->user('dosen', 'lecturer-own'));
        $this->get(route('dashboard'))->assertOk()
            ->assertSee('Aktivitas Akademik Saya')
            ->assertSee('Mata Kuliah Dosen')
            ->assertSee('Grafik perbandingan rencana dan realisasi tatap muka dosen')
            ->assertSee('16')
            ->assertSee('10')
            ->assertDontSee('Mata Kuliah Lain')
            ->assertDontSee('Kelas C');
    }

    private function user(string $role, string $legacyId): GenericUser
    {
        return new GenericUser([
            'id' => $role.'-dashboard-test',
            'legacy_id' => $legacyId,
            'legacy_type' => $role,
            'login' => $legacyId,
            'name' => 'Dashboard '.$role,
            'role' => $role,
        ]);
    }
}
