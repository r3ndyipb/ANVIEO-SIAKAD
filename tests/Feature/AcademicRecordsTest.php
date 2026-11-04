<?php

namespace Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AcademicRecordsTest extends TestCase
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

    public function test_transcript_search_uses_best_grade_and_prints_transcript_and_curriculum_check(): void
    {
        $this->actingAs($this->user('admin', 'admin-1'));

        $this->get(route('transcripts.index'))->assertOk()->assertSee('NPM atau nama mahasiswa');
        $this->get(route('transcripts.index', ['search' => '2025001']))
            ->assertOk()->assertSee('Mahasiswa Satu')->assertSee('Cetak Transkrip');
        $this->get(route('transcripts.index', ['search' => '9999999']))
            ->assertRedirect(route('transcripts.index'))
            ->assertSessionHasErrors('search');
        $transcript = $this->get(route('transcripts.index', ['search' => '2025001', 'student' => 'student-1']))
            ->assertOk()
            ->assertSee('3.50')
            ->assertSee('AB')
            ->assertSee('3.30')
            ->assertSee('5 SKS')
            ->assertSee('Kembali ke pencarian')
            ->assertSee('Cetak Transkrip')
            ->assertSee('Cek Mata Kuliah Diprogram');
        $this->assertSame(1, substr_count($transcript->getContent(), 'Mata Kuliah Satu'));

        DB::connection('simak_main')->table('s1m4k_masterfakultas')->where('id_fakultas', 'faculty-1')
            ->update(['dekan' => 'lecturer-1']);
        DB::connection('simak_main')->table('s1m4k_masterprodi')->where('id_prodi', 'program-1')
            ->update(['ketua_prodi' => 'lecturer-2']);
        DB::connection('simak_main')->table('s1m4k_masterdosen')->where('id_dosen', 'lecturer-1')->update([
            'nama_dosen' => 'Dekan Uji', 'gelar_depan' => 'Dr.', 'gelar_belakang' => 'S.H.', 'nip' => '111111111111111111',
        ]);
        DB::connection('simak_main')->table('s1m4k_masterdosen')->where('id_dosen', 'lecturer-2')->update([
            'nama_dosen' => 'Ketua Prodi Uji', 'gelar_depan' => 'Prof.', 'gelar_belakang' => 'M.Kom.', 'nip' => '222222222222222222',
        ]);

        $printedTranscript = $this->get(route('transcripts.print', 'student-1'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertGreaterThanOrEqual(2, substr_count($printedTranscript->getContent(), '/Subtype /Image'));
        $validations = DB::connection('simak_main')->table('validasi_dok')
            ->whereIn('jenis_dokumen', ['TRANSKRIP_DEKAN', 'TRANSKRIP_KETUA_PRODI'])
            ->get();
        $this->assertCount(2, $validations);
        $deanValidation = $validations->firstWhere('jenis_dokumen', 'TRANSKRIP_DEKAN');
        $programHeadValidation = $validations->firstWhere('jenis_dokumen', 'TRANSKRIP_KETUA_PRODI');
        $this->assertNotSame($deanValidation->id, $programHeadValidation->id);
        $this->get(route('transcripts.documents.verify', [
            'code' => $deanValidation->id, 'student' => 'student-1', 'signatory' => 'dekan',
        ]))->assertOk()->assertSee('Transkrip Nilai Sementara')->assertSee('Dekan Uji')->assertSee('NIP. 111111111111111111');
        $this->get(route('transcripts.documents.verify', [
            'code' => $programHeadValidation->id, 'student' => 'student-1', 'signatory' => 'ketua-prodi',
        ]))->assertOk()->assertSee('Ketua Prodi Uji')->assertSee('NIP. 222222222222222222');
        $this->get(route('transcripts.documents.verify', [
            'code' => $deanValidation->id, 'student' => 'student-1', 'signatory' => 'ketua-prodi',
        ]))->assertNotFound();
        $this->get(route('transcripts.documents.verify', [
            'code' => $deanValidation->id, 'student' => 'student-2', 'signatory' => 'dekan',
        ]))->assertNotFound();
        $this->get(route('transcripts.curriculum', 'student-1'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_transcript_access_is_scoped_for_program_staff_and_pa_dosen(): void
    {
        $this->actingAs($this->user('userprodi', 'prodi-user', ['id_prodi' => 'program-1']));
        $this->get(route('transcripts.index', ['search' => '2025999']))
            ->assertRedirect(route('transcripts.index'))
            ->assertSessionHasErrors('search');
        $this->get(route('transcripts.print', 'student-2'))->assertNotFound();

        $this->actingAs($this->user('dosen', 'lecturer-user', ['login' => 'NIDN-1']));
        $this->get(route('transcripts.index'))
            ->assertOk()
            ->assertSee('Mahasiswa Satu')
            ->assertDontSee('Mahasiswa Dua');
        $this->get(route('transcripts.print', 'student-2'))->assertNotFound();

        $this->actingAs($this->user('mahasiswa', 'student-1'));
        $this->get(route('transcripts.index'))
            ->assertOk()
            ->assertSee('Mahasiswa Satu')
            ->assertDontSee('Mahasiswa Dua');
        $this->get(route('transcripts.print', 'student-2'))->assertNotFound();
    }

    public function test_wisuda_can_search_and_print_transcripts_across_programs(): void
    {
        $this->actingAs($this->user('wisuda', 'wisuda-user'));
        $this->get(route('transcripts.index', ['search' => '2025999', 'student' => 'student-2']))
            ->assertOk()
            ->assertSee('Mahasiswa Dua')
            ->assertSee('Cetak Transkrip');
        $this->get(route('transcripts.print', 'student-2'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_dosen_transcript_student_actions_link_to_transcript_and_khs(): void
    {
        $this->actingAs($this->user('dosen', 'lecturer-user', ['login' => 'NIDN-1']));

        $this->get(route('transcripts.index'))
            ->assertOk()
            ->assertSee('Transkrip dan KHS Mahasiswa')
            ->assertSee('Lihat transkrip Mahasiswa Satu')
            ->assertSee('Lihat KHS Mahasiswa Satu')
            ->assertSee('href="'.e(route('khs.index', ['search' => '2025001', 'student' => 'student-1'])).'"', false);
    }

    public function test_course_removal_deletes_all_attempts_and_related_edom_with_audit_log(): void
    {
        $this->actingAs($this->user('userprodi', 'prodi-user', ['id_prodi' => 'program-1']));
        $this->get(route('course-removals.index', ['search' => '2025001']))
            ->assertOk()
            ->assertSee('Mata Kuliah Satu')
            ->assertSee('Penghapusan Mata Kuliah');
        $this->get(route('course-removals.index', ['search' => '9999999']))
            ->assertRedirect(route('course-removals.index'))
            ->assertSessionHasErrors('search');
        $this->get(route('course-removals.index', ['search' => '2025001', 'student' => 'student-1']))
            ->assertOk()
            ->assertSee('AB')
            ->assertSee('3.50')
            ->assertSee('5 SKS')
            ->assertSee('3.30')
            ->assertDontSee('Jumlah Pengambilan');

        $this->delete(route('course-removals.destroy', [
            'student' => 'student-1',
            'course' => 'course-1',
            'search' => '2025001',
        ]), ['search' => '2025001'])
            ->assertRedirect(route('course-removals.index', ['search' => '2025001', 'student' => 'student-1']))
            ->assertSessionHas('status');

        $this->assertSame(0, DB::connection('simak_main')->table('s1m4k_krs')
            ->where('id_mahasiswa', 'student-1')->where('id_matkul', 'course-1')->count());
        $this->assertSame(1, DB::connection('simak_main')->table('s1m4k_krs')
            ->where('id_mahasiswa', 'student-1')->where('id_matkul', 'course-2')->count());
        $this->assertSame(0, DB::connection('simak_main')->table('s1m4k_edom')->count());
        $updated = $this->get(route('course-removals.index', ['search' => '2025001', 'student' => 'student-1']))
            ->assertOk()->assertSee('2 SKS')->assertSee('3.00');
        $this->assertStringNotContainsString('Mata Kuliah Satu', $updated->getContent());
        $this->delete(route('course-removals.destroy', [
            'student' => 'student-1', 'course' => 'course-2', 'search' => '2025001',
        ]), ['search' => '2025001'])->assertRedirect();
        $this->get(route('course-removals.index', ['search' => '2025001', 'student' => 'student-1']))
            ->assertOk()->assertSee('0 SKS')->assertSee('IPK 0.00');
        $log = DB::connection('simak_main')->table('log_aktivitas')
            ->where('modul', 'Penghapusan Mata Kuliah')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('Mata Kuliah Satu', $log->aktifitas);
        $this->assertStringContainsString('krs-old-1', $log->aktifitas);
    }

    public function test_course_removal_rejects_roles_outside_legacy_authorization(): void
    {
        $this->actingAs($this->user('usertu', 'tu-user', ['id_prodi' => 'program-1']));
        $this->get(route('course-removals.index'))->assertForbidden();
        $this->delete(route('course-removals.destroy', [
            'student' => 'student-1',
            'course' => 'course-1',
            'search' => '2025001',
        ]), ['search' => '2025001'])->assertForbidden();
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
            $table->string('ketua_prodi')->nullable();
            $table->string('nama_jenjang_pendidikan')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterfakultas', function (Blueprint $table): void {
            $table->string('id_fakultas')->primary();
            $table->string('nama_fakultas');
            $table->string('dekan')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterdosen', function (Blueprint $table): void {
            $table->string('id_dosen')->primary();
            $table->string('nidn');
            $table->string('nama_dosen')->nullable();
            $table->string('gelar_depan')->nullable();
            $table->string('gelar_belakang')->nullable();
            $table->string('nip')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_mastermk', function (Blueprint $table): void {
            $table->string('id_matkul')->primary();
            $table->string('id_prodi');
            $table->string('kode_mata_kuliah');
            $table->string('nama_mata_kuliah');
            $table->string('semester')->nullable();
            $table->string('id_jenis_mata_kuliah')->nullable();
            $table->string('sks_mata_kuliah');
            $table->string('status');
        });
        Schema::connection('simak_main')->create('s1m4k_krs', function (Blueprint $table): void {
            $table->string('id_krs')->primary();
            $table->string('id_mahasiswa');
            $table->string('id_matkul');
            $table->string('id_jadwal')->nullable();
            $table->string('id_kalender')->nullable();
            $table->string('tahun')->nullable();
            $table->string('nilai_huruf')->nullable();
            $table->string('nilai_bobot')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_edom', function (Blueprint $table): void {
            $table->string('id_edom')->primary();
            $table->string('id_krs');
            $table->string('id_jadwal')->nullable();
            $table->string('id_kalender')->nullable();
            $table->string('id_survey')->nullable();
            $table->integer('nilai')->nullable();
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
        Schema::connection('simak_main')->create('validasi_dok', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('id_dokumen');
            $table->string('jenis_dokumen');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    private function seedData(): void
    {
        DB::connection('simak_main')->table('s1m4k_masterfakultas')->insert([
            ['id_fakultas' => 'faculty-1', 'nama_fakultas' => 'Fakultas Satu'],
            ['id_fakultas' => 'faculty-2', 'nama_fakultas' => 'Fakultas Dua'],
        ]);
        DB::connection('simak_main')->table('s1m4k_masterprodi')->insert([
            ['id_prodi' => 'program-1', 'nama_program_studi' => 'Program Satu', 'id_fakultas' => 'faculty-1'],
            ['id_prodi' => 'program-2', 'nama_program_studi' => 'Program Dua', 'id_fakultas' => 'faculty-2'],
        ]);
        DB::connection('simak_main')->table('s1m4k_masterdosen')->insert([
            ['id_dosen' => 'lecturer-1', 'nidn' => 'NIDN-1', 'nama_dosen' => 'Dosen PA Satu'],
            ['id_dosen' => 'lecturer-2', 'nidn' => 'NIDN-2', 'nama_dosen' => 'Dosen PA Dua'],
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            ['id_mahasiswa' => 'student-1', 'nim' => '2025001', 'nama_mahasiswa' => 'Mahasiswa Satu', 'id_prodi' => 'program-1', 'id_fakultas' => 'faculty-1', 'dosen_pa' => 'lecturer-1', 'id_status_mahasiswa' => 'A'],
            ['id_mahasiswa' => 'student-2', 'nim' => '2025999', 'nama_mahasiswa' => 'Mahasiswa Dua', 'id_prodi' => 'program-2', 'id_fakultas' => 'faculty-2', 'dosen_pa' => 'lecturer-2', 'id_status_mahasiswa' => 'A'],
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermk')->insert([
            ['id_matkul' => 'course-1', 'id_prodi' => 'program-1', 'kode_mata_kuliah' => 'MK001', 'nama_mata_kuliah' => 'Mata Kuliah Satu', 'semester' => '1', 'id_jenis_mata_kuliah' => 'A', 'sks_mata_kuliah' => '3', 'status' => 'A'],
            ['id_matkul' => 'course-2', 'id_prodi' => 'program-1', 'kode_mata_kuliah' => 'MK002', 'nama_mata_kuliah' => 'Mata Kuliah Dua', 'semester' => '2', 'id_jenis_mata_kuliah' => 'B', 'sks_mata_kuliah' => '2', 'status' => 'A'],
        ]);
        DB::connection('simak_main')->table('s1m4k_krs')->insert([
            ['id_krs' => 'krs-current-1', 'id_mahasiswa' => 'student-1', 'id_matkul' => 'course-1', 'id_jadwal' => 'schedule-1', 'id_kalender' => 'calendar-current', 'tahun' => '20261', 'nilai_huruf' => 'B', 'nilai_bobot' => '3.00'],
            ['id_krs' => 'krs-old-1', 'id_mahasiswa' => 'student-1', 'id_matkul' => 'course-1', 'id_jadwal' => 'schedule-2', 'id_kalender' => 'calendar-previous', 'tahun' => '20252', 'nilai_huruf' => 'B', 'nilai_bobot' => '3.50'],
            ['id_krs' => 'krs-current-2', 'id_mahasiswa' => 'student-1', 'id_matkul' => 'course-2', 'id_jadwal' => 'schedule-3', 'id_kalender' => 'calendar-current', 'tahun' => '20261', 'nilai_huruf' => 'B', 'nilai_bobot' => '3.00'],
            ['id_krs' => 'krs-other', 'id_mahasiswa' => 'student-2', 'id_matkul' => 'course-1', 'id_jadwal' => 'schedule-4', 'id_kalender' => 'calendar-current', 'tahun' => '20261', 'nilai_huruf' => 'A', 'nilai_bobot' => '4.00'],
        ]);
        DB::connection('simak_main')->table('s1m4k_edom')->insert([
            ['id_edom' => 'edom-1', 'id_krs' => 'krs-current-1', 'id_jadwal' => 'schedule-1', 'id_kalender' => 'calendar-current', 'id_survey' => '1', 'nilai' => 5],
            ['id_edom' => 'edom-2', 'id_krs' => 'krs-old-1', 'id_jadwal' => 'schedule-2', 'id_kalender' => 'calendar-previous', 'id_survey' => '1', 'nilai' => 4],
        ]);
    }

    private function user(string $role, string $id, array $extra = []): GenericUser
    {
        return new GenericUser([
            'id' => $id,
            'legacy_id' => $id,
            'role' => $role,
            'login' => 'NIDN-1',
            'name' => 'Pengguna Uji',
            ...$extra,
        ]);
    }
}
