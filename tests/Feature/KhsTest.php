<?php

namespace Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class KhsTest extends TestCase
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

    public function test_managers_search_students_and_read_calculated_khs_for_selected_year(): void
    {
        $this->actingAs($this->user('admin', 'admin-1', 'admin-login'));

        $this->get(route('khs.index'))->assertOk()->assertSee('NPM mahasiswa');
        $this->get(route('khs.index', ['npm' => 'not-found']))
            ->assertRedirect(route('khs.index'))
            ->assertSessionHasErrors(['npm' => 'Data mahasiswa dengan NPM tersebut tidak ditemukan.']);
        $this->get(route('khs.index', ['npm' => '2025001']))
            ->assertOk()
            ->assertSee('id="khs-npm"', false)
            ->assertSee('value="2025001"', false)
            ->assertSee('Mahasiswa Satu')
            ->assertSee('Pilih tahun akademik')
            ->assertSee('2026 Ganjil')
            ->assertSee('value="calendar-current" selected', false)
            ->assertSee('3.60');
        $this->get(route('khs.index', [
            'npm' => '2025001',
            'calendar' => 'calendar-current',
        ]))
            ->assertOk()
            ->assertSee('IPS')
            ->assertSee('3.60')
            ->assertSee('IPK')
            ->assertSee('Maksimum SKS Berikutnya')
            ->assertSee('24 SKS')
            ->assertSee('Nilai Huruf')
            ->assertSee(route('khs.print', ['student' => 'student-1', 'calendar' => 'calendar-current']));

        DB::connection('simak_main')->table('s1m4k_masterdosen')->where('id_dosen', 'dean-1')->update([
            'nama_dosen' => 'Wakil Dekan Satu',
            'gelar_depan' => 'Ir.',
            'gelar_belakang' => 'M.T.',
            'nip' => '111111111111111111',
        ]);
        $printedKhs = $this->get(route('khs.print', ['student' => 'student-1', 'calendar' => 'calendar-current']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('/Subtype /Image', $printedKhs->getContent());
        $validation = DB::connection('simak_main')->table('validasi_dok')
            ->where('jenis_dokumen', 'KHS')->first();
        $this->assertNotNull($validation);
        $verifiedKhs = $this->get(route('khs.documents.verify', [
            'code' => $validation->id,
            'student' => 'student-1',
            'calendar' => 'calendar-current',
        ]));
        $verifiedKhs->assertOk()
            ->assertSee('Dokumen KHS Terverifikasi')
            ->assertSee('Mahasiswa Satu')
            ->assertSee('2026 Ganjil')
            ->assertSee('Ir. Wakil Dekan Satu, M.T.')
            ->assertSee('NIP. 111111111111111111')
            ->assertDontSee('Dekan Dua');
        $this->get(route('khs.documents.verify', [
            'code' => $validation->id,
            'student' => 'student-2',
            'calendar' => 'calendar-current',
        ]))->assertNotFound();
        $this->get(route('khs.index', ['npm' => '2025001', 'calendar' => 'calendar-previous']))
            ->assertOk()->assertSee('2025 Genap');
    }

    public function test_khs_scopes_program_and_faculty_roles(): void
    {
        $this->actingAs($this->user('userprodi', 'prodi-user', 'prodi-login', ['id_prodi' => 'program-1']));
        $this->get(route('khs.index', ['npm' => '2025001']))
            ->assertOk()
            ->assertSee('Mahasiswa Satu')
            ->assertSee('name="npm" value="2025001"', false)
            ->assertSee('href="'.route('khs.index', ['npm' => '2025001']).'"', false);
        $this->get(route('khs.index', [
            'search' => '2025001',
            'student' => 'student-1',
            'calendar' => 'calendar-current',
        ]))
            ->assertOk()
            ->assertSee('IPS')
            ->assertSee('3.60');
        $this->get(route('khs.index', ['search' => '2025999']))
            ->assertRedirect(route('khs.index'))
            ->assertSessionHasErrors('search');
        $this->get(route('khs.index', [
            'search' => '2025999',
            'student' => 'student-2',
            'calendar' => 'calendar-other',
        ]))
            ->assertRedirect(route('khs.index'))
            ->assertSessionHasErrors('search');

        $this->actingAs($this->user('pimfak', 'faculty-user', 'faculty-login', ['id_fakultas' => 'faculty-1']));
        $this->get(route('khs.index', ['search' => '2025999']))
            ->assertOk()
            ->assertDontSee('Mahasiswa Dua');
        $this->get(route('khs.index', [
            'search' => '2025999',
            'student' => 'student-2',
            'calendar' => 'calendar-other',
        ]))->assertNotFound();

        $this->actingAs($this->user('pimuniv', 'university-user', 'university-login'));
        $this->get(route('khs.index', [
            'search' => '2025999',
            'student' => 'student-2',
            'calendar' => 'calendar-other',
        ]))->assertOk()->assertSee('Mahasiswa Dua');
    }

    public function test_wisuda_can_read_and_print_khs_without_access_to_student_survey(): void
    {
        $this->actingAs($this->user('wisuda', 'wisuda-user', 'wisuda-login'));
        $this->get(route('khs.index', ['npm' => '2025001', 'calendar' => 'calendar-current']))
            ->assertOk()
            ->assertSee('Mahasiswa Satu')
            ->assertSee(route('khs.print', ['student' => 'student-1', 'calendar' => 'calendar-current']))
            ->assertDontSee('Isi survey EDOM');
        $this->get(route('khs.print', ['student' => 'student-1', 'calendar' => 'calendar-current']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->post(route('khs.survey.save', 'krs-1'), ['answers' => []])->assertForbidden();
    }

    public function test_dosen_can_open_khs_from_the_transcript_list_only_for_their_advisees(): void
    {
        $this->actingAs($this->user('dosen', 'lecturer-1', 'NIDN-1'));

        $this->get(route('khs.index', [
            'search' => '2025001',
            'student' => 'student-1',
        ]))->assertOk()->assertSee('Pilih tahun akademik')->assertSee('2026 Ganjil');
        $this->get(route('khs.index', [
            'search' => '2025999',
            'student' => 'student-2',
        ]))->assertNotFound();
    }

    public function test_student_sees_only_own_khs_and_must_submit_all_edom_answers_before_grades_show(): void
    {
        DB::connection('simak_main')->table('s1m4k_admin')->insert([
            'id_admin' => 'operator-1',
            'level' => 'userprodi',
            'id_prodi' => 'program-1',
            'blokir' => 'N',
        ]);
        $this->actingAs($this->user('mahasiswa', 'student-1', '2025001'));

        $this->get(route('khs.index'))->assertOk()->assertSee('Pilih tahun akademik');
        $this->get(route('khs.index', ['calendar' => 'calendar-current']))
            ->assertOk()
            ->assertSee('Tersembunyi')
            ->assertSee('Isi survey EDOM')
            ->assertDontSee('Nilai Diakui');
        $this->get(route('khs.print', ['student' => 'student-2', 'calendar' => 'calendar-current']))->assertNotFound();

        $this->post(route('khs.survey.save', 'krs-current-1'), [
            'calendar' => 'calendar-current',
            'answers' => [1 => 5, 2 => 4, 3 => 5],
        ])->assertRedirect(route('khs.index', ['calendar' => 'calendar-current']))
            ->assertSessionHas('status');
        $this->get(route('khs.index', ['calendar' => 'calendar-current']))
            ->assertOk()
            ->assertSee('Survey telah diisi')
            ->assertSee('A');
        $this->assertSame(3, DB::connection('simak_main')->table('s1m4k_edom')
            ->where('id_krs', 'krs-current-1')->count());
        $edomNotifications = DB::connection('simak_main')->table('eskripsi_notifikasi')->get();
        $this->assertCount(2, $edomNotifications);
        $this->assertSame(['dosen', 'userprodi'], $edomNotifications->pluck('recipient_role')->sort()->values()->all());
        foreach ($edomNotifications as $notification) {
            $this->assertSame('edom-submitted', $notification->category);
            $this->assertStringNotContainsString('Mahasiswa Satu', $notification->message);
            $this->assertStringNotContainsString('2025001', $notification->message);
            $this->assertStringNotContainsString('5', $notification->message);
            $this->assertStringNotContainsString('4', $notification->message);
        }
        $this->assertSame(1, DB::connection('simak_main')->table('log_aktivitas')
            ->where('modul', 'KHS Mahasiswa')->count());
        $this->post(route('khs.survey.save', 'krs-current-1'), [
            'calendar' => 'calendar-current',
            'answers' => [1 => 5, 2 => 4, 3 => 5],
        ])->assertStatus(409);
        $this->post(route('khs.survey.save', 'krs-current-2'), [
            'calendar' => 'calendar-current',
            'answers' => [1 => 5, 2 => 4],
        ])->assertSessionHasErrors('answers');
    }

    public function test_student_khs_calendar_list_includes_short_semester_with_enrolled_grades(): void
    {
        DB::connection('simak_main')->table('s1m4k_krs')
            ->where('id_krs', 'krs-old-1')
            ->update(['nilai_bobot' => '2.50', 'krs_reguler' => 'Y']);
        DB::connection('simak_main')->table('s1m4k_kalender')->insert([
            'id_kalender' => 'calendar-sp',
            'id_prodi' => 'program-1',
            'tahun' => '20253',
            'namatahun' => 'SP 2025 / 2026',
            'status' => 'N',
        ]);
        DB::connection('simak_main')->table('s1m4k_krs')->insert([
            'id_krs' => 'krs-sp',
            'id_mahasiswa' => 'student-1',
            'id_kalender' => 'calendar-sp',
            'id_matkul' => 'course-1',
            'id_jadwal' => 'schedule-1',
            'tahun' => '20253',
            'nilai_huruf' => 'A',
            'nilai_bobot' => '3.00',
            'krs_reguler' => 'Y',
        ]);
        $this->actingAs($this->user('mahasiswa', 'student-1', '2025001'));

        $this->get(route('khs.index'))
            ->assertOk()
            ->assertSee('value="calendar-sp"', false)
            ->assertSee('SP 2025 / 2026');
        $this->get(route('khs.index', ['calendar' => 'calendar-sp']))
            ->assertOk()
            ->assertSee('SP 2025 / 2026')
            ->assertSee('IPS')
            ->assertSee('3.00')
            ->assertSee('Maksimum SKS Berikutnya')
            ->assertSee('19 SKS')
            ->assertSee(route('khs.print', ['student' => 'student-1', 'calendar' => 'calendar-sp']));

        $this->actingAs($this->user('admin', 'admin-1', 'admin-login'));
        $this->get(route('khs.index', ['npm' => '2025001', 'calendar' => 'calendar-sp']))
            ->assertOk()
            ->assertSee('SP 2025 / 2026')
            ->assertSee('A');

        $this->actingAs($this->user('userprodi', 'operator-1', 'prodi-login', ['id_prodi' => 'program-1']));
        $this->get(route('khs.index', ['npm' => '2025001', 'calendar' => 'calendar-sp']))
            ->assertOk()
            ->assertSee('SP 2025 / 2026');
    }

    private function createSchema(): void
    {
        Schema::connection('simak_main')->create('s1m4k_mastermhs', function (Blueprint $table): void {
            $table->string('id_mahasiswa')->primary();
            $table->string('nim');
            $table->string('nama_mahasiswa');
            $table->string('id_prodi');
            $table->string('id_fakultas')->nullable();
            $table->string('dosen_pa')->nullable();
            $table->string('id_status_mahasiswa')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterprodi', function (Blueprint $table): void {
            $table->string('id_prodi')->primary();
            $table->string('nama_program_studi');
            $table->string('id_fakultas')->nullable();
            $table->string('nama_jenjang_pendidikan')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterfakultas', function (Blueprint $table): void {
            $table->string('id_fakultas')->primary();
            $table->string('nama_fakultas');
            $table->string('pd1')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterdosen', function (Blueprint $table): void {
            $table->string('id_dosen')->primary();
            $table->string('nidn');
            $table->string('nama_dosen');
            $table->string('nip')->nullable();
            $table->string('gelar_depan')->nullable();
            $table->string('gelar_belakang')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_kalender', function (Blueprint $table): void {
            $table->string('id_kalender')->primary();
            $table->string('id_prodi');
            $table->string('tahun');
            $table->string('namatahun');
            $table->string('status')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_mastermk', function (Blueprint $table): void {
            $table->string('id_matkul')->primary();
            $table->string('kode_mata_kuliah');
            $table->string('nama_mata_kuliah');
            $table->string('semester')->nullable();
            $table->string('sks_mata_kuliah');
        });
        Schema::connection('simak_main')->create('s1m4k_jadwal', function (Blueprint $table): void {
            $table->string('id_jadwal')->primary();
            $table->string('nama_kelas')->nullable();
            $table->string('dosen1')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_krs', function (Blueprint $table): void {
            $table->string('id_krs')->primary();
            $table->string('id_mahasiswa');
            $table->string('id_kalender');
            $table->string('id_matkul');
            $table->string('id_jadwal');
            $table->string('tahun');
            $table->string('nilai_huruf')->nullable();
            $table->string('nilai_bobot')->nullable();
            $table->string('krs_reguler')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_survey_edom', function (Blueprint $table): void {
            $table->integer('id_survey')->primary();
            $table->text('pertanyaan');
        });
        Schema::connection('simak_main')->create('s1m4k_edom', function (Blueprint $table): void {
            $table->string('id_edom')->primary();
            $table->string('id_krs');
            $table->string('id_jadwal');
            $table->string('id_kalender');
            $table->string('id_survey');
            $table->integer('nilai');
        });
        Schema::connection('simak_main')->create('validasi_dok', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('id_dokumen');
            $table->string('jenis_dokumen');
            $table->timestamp('created_at')->useCurrent();
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
        Schema::connection('simak_main')->create('s1m4k_admin', function (Blueprint $table): void {
            $table->string('id_admin')->primary();
            $table->string('level');
            $table->string('id_prodi')->nullable();
            $table->string('blokir')->default('N');
        });
        Schema::connection('simak_main')->create('eskripsi_notifikasi', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('recipient_role', 30);
            $table->string('recipient_id', 100);
            $table->string('category', 80);
            $table->string('title', 255);
            $table->text('message');
            $table->string('url', 500);
            $table->boolean('is_read')->default(false);
            $table->dateTime('created_at');
            $table->dateTime('read_at')->nullable();
        });
    }

    private function seedData(): void
    {
        DB::connection('simak_main')->table('s1m4k_masterfakultas')->insert([
            ['id_fakultas' => 'faculty-1', 'nama_fakultas' => 'Fakultas Satu', 'pd1' => 'dean-1'],
            ['id_fakultas' => 'faculty-2', 'nama_fakultas' => 'Fakultas Dua', 'pd1' => 'dean-2'],
        ]);
        DB::connection('simak_main')->table('s1m4k_masterprodi')->insert([
            ['id_prodi' => 'program-1', 'nama_program_studi' => 'Program Satu', 'id_fakultas' => 'faculty-1'],
            ['id_prodi' => 'program-2', 'nama_program_studi' => 'Program Dua', 'id_fakultas' => 'faculty-2'],
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            ['id_mahasiswa' => 'student-1', 'nim' => '2025001', 'nama_mahasiswa' => 'Mahasiswa Satu', 'id_prodi' => 'program-1', 'id_fakultas' => 'faculty-1', 'dosen_pa' => 'lecturer-1', 'id_status_mahasiswa' => 'A'],
            ['id_mahasiswa' => 'student-2', 'nim' => '2025999', 'nama_mahasiswa' => 'Mahasiswa Dua', 'id_prodi' => 'program-2', 'id_fakultas' => 'faculty-2', 'dosen_pa' => 'lecturer-2', 'id_status_mahasiswa' => 'A'],
        ]);
        DB::connection('simak_main')->table('s1m4k_masterdosen')->insert([
            ['id_dosen' => 'lecturer-1', 'nidn' => 'NIDN-1', 'nama_dosen' => 'Dosen PA Satu', 'gelar_depan' => 'Dr.', 'gelar_belakang' => 'M.Kom.'],
            ['id_dosen' => 'lecturer-2', 'nidn' => 'NIDN-2', 'nama_dosen' => 'Dosen PA Dua', 'gelar_depan' => null, 'gelar_belakang' => null],
            ['id_dosen' => 'dean-1', 'nidn' => 'NIDN-DEAN-1', 'nama_dosen' => 'Dekan Satu', 'gelar_depan' => null, 'gelar_belakang' => null],
            ['id_dosen' => 'dean-2', 'nidn' => 'NIDN-DEAN-2', 'nama_dosen' => 'Dekan Dua', 'gelar_depan' => null, 'gelar_belakang' => null],
        ]);
        DB::connection('simak_main')->table('s1m4k_kalender')->insert([
            ['id_kalender' => 'calendar-current', 'id_prodi' => 'program-1', 'tahun' => '20261', 'namatahun' => '2026 Ganjil', 'status' => 'Y'],
            ['id_kalender' => 'calendar-previous', 'id_prodi' => 'program-1', 'tahun' => '20252', 'namatahun' => '2025 Genap', 'status' => 'N'],
            ['id_kalender' => 'calendar-other', 'id_prodi' => 'program-2', 'tahun' => '20261', 'namatahun' => '2026 Ganjil', 'status' => 'Y'],
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermk')->insert([
            ['id_matkul' => 'course-1', 'kode_mata_kuliah' => 'MK001', 'nama_mata_kuliah' => 'Mata Kuliah Satu', 'semester' => '1', 'sks_mata_kuliah' => '3'],
            ['id_matkul' => 'course-2', 'kode_mata_kuliah' => 'MK002', 'nama_mata_kuliah' => 'Mata Kuliah Dua', 'semester' => '1', 'sks_mata_kuliah' => '2'],
        ]);
        DB::connection('simak_main')->table('s1m4k_jadwal')->insert([
            ['id_jadwal' => 'schedule-1', 'nama_kelas' => 'A', 'dosen1' => 'lecturer-1'],
            ['id_jadwal' => 'schedule-2', 'nama_kelas' => 'A', 'dosen1' => null],
            ['id_jadwal' => 'schedule-3', 'nama_kelas' => 'A', 'dosen1' => null],
        ]);
        DB::connection('simak_main')->table('s1m4k_krs')->insert([
            ['id_krs' => 'krs-current-1', 'id_mahasiswa' => 'student-1', 'id_kalender' => 'calendar-current', 'id_matkul' => 'course-1', 'id_jadwal' => 'schedule-1', 'tahun' => '20261', 'nilai_huruf' => 'A', 'nilai_bobot' => '4.00'],
            ['id_krs' => 'krs-current-2', 'id_mahasiswa' => 'student-1', 'id_kalender' => 'calendar-current', 'id_matkul' => 'course-2', 'id_jadwal' => 'schedule-2', 'tahun' => '20261', 'nilai_huruf' => 'B', 'nilai_bobot' => '3.00'],
            ['id_krs' => 'krs-old-1', 'id_mahasiswa' => 'student-1', 'id_kalender' => 'calendar-previous', 'id_matkul' => 'course-1', 'id_jadwal' => 'schedule-3', 'tahun' => '20252', 'nilai_huruf' => 'C', 'nilai_bobot' => '2.00'],
            ['id_krs' => 'krs-other', 'id_mahasiswa' => 'student-2', 'id_kalender' => 'calendar-other', 'id_matkul' => 'course-1', 'id_jadwal' => 'schedule-1', 'tahun' => '20261', 'nilai_huruf' => 'A', 'nilai_bobot' => '4.00'],
        ]);
        DB::connection('simak_main')->table('s1m4k_survey_edom')->insert([
            ['id_survey' => 1, 'pertanyaan' => 'Pertanyaan pertama'],
            ['id_survey' => 2, 'pertanyaan' => 'Pertanyaan kedua'],
            ['id_survey' => 3, 'pertanyaan' => 'Pertanyaan ketiga'],
        ]);
    }

    private function user(string $role, string $id, string $login, array $extra = []): GenericUser
    {
        return new GenericUser([
            'id' => $id,
            'legacy_id' => $id,
            'role' => $role,
            'login' => $login,
            'name' => 'Pengguna Uji',
            ...$extra,
        ]);
    }
}
