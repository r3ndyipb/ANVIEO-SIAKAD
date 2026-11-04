<?php

namespace Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SurveyEdomTest extends TestCase
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
    }

    public function test_admin_manages_questions_and_pimpinan_views_them_read_only(): void
    {
        $this->actingAs($this->user('admin'));
        $this->get(route('survey-edom.questions.index'))->assertOk()
            ->assertSee('Tambah Pertanyaan');
        $this->post(route('survey-edom.questions.store'), ['pertanyaan' => 'Pertanyaan baru?'])
            ->assertRedirect(route('survey-edom.questions.index'));
        $questionId = DB::connection('simak_main')->table('s1m4k_survey_edom')
            ->where('pertanyaan', 'Pertanyaan baru?')->value('id_survey');
        $this->put(route('survey-edom.questions.update', $questionId), ['pertanyaan' => 'Pertanyaan diubah?'])
            ->assertRedirect(route('survey-edom.questions.index'));
        $this->assertDatabaseHas('s1m4k_survey_edom', [
            'id_survey' => $questionId,
            'pertanyaan' => 'Pertanyaan diubah?',
        ], 'simak_main');

        foreach (['pimuniv', 'pimfak'] as $role) {
            $this->actingAs($this->user($role, ['id_fakultas' => 'faculty-1']));
            $this->get(route('survey-edom.questions.index'))->assertOk()
                ->assertDontSee('Tambah Pertanyaan')
                ->assertDontSee('survey-edom.questions.edit', false);
            $this->post(route('survey-edom.questions.store'), ['pertanyaan' => 'Tidak boleh'])
                ->assertForbidden();
        }
        $this->actingAs($this->user('usertu'));
        $this->get(route('survey-edom.questions.index'))->assertForbidden();
    }

    public function test_edom_results_aggregate_six_aspects_and_scope_by_role(): void
    {
        $this->actingAs($this->user('admin'));
        $this->get(route('survey-edom.results.index'))
            ->assertOk()
            ->assertSee('Hasil Survey EDOM')
            ->assertSee('data-edom-results', false);
        $this->get(route('survey-edom.results.index', [
            'program' => 'program-1', 'calendar' => 'calendar-1',
        ]))->assertOk()->assertSee('data-selected-program="program-1"', false);
        $result = $this->getJson(route('survey-edom.results.data', [
            'program' => 'program-1', 'calendar' => 'calendar-1',
        ]))->assertOk()->assertJsonPath('program', 'Program Satu');
        $lecturers = collect($result->json('results'))->keyBy('id');
        $this->assertSame(1, $lecturers['lecturer-1']['course_count']);
        $this->assertSame(2, $lecturers['lecturer-1']['class_count']);
        $this->assertSame(2, $lecturers['lecturer-1']['respondent_count']);
        $this->assertSame(93.3, $lecturers['lecturer-1']['aspects'][0]['percentage']);
        $this->assertSame(3, $lecturers['lecturer-1']['aspects'][0]['responses']);
        $this->assertSame(73.3, $lecturers['lecturer-1']['aspects'][1]['percentage']);
        $this->assertEquals(60.0, $lecturers['lecturer-1']['aspects'][2]['percentage']);

        $this->getJson(route('survey-edom.results.data', [
            'program' => 'program-2', 'calendar' => 'calendar-2',
        ]))->assertOk()->assertJsonPath('program', 'Program Dua');

        $this->actingAs($this->user('pimfak', ['id_fakultas' => 'faculty-2']));
        $this->get(route('survey-edom.results.index', ['program' => 'program-1']))->assertNotFound();
        $this->get(route('survey-edom.results.index', ['program' => 'program-2', 'calendar' => 'calendar-1']))
            ->assertOk()
            ->assertSee('data-selected-program="program-2"', false)
            ->assertSee('data-selected-calendar=""', false);
        $facultyResult = $this->getJson(route('survey-edom.results.data', [
            'program' => 'program-2', 'calendar' => 'calendar-2',
        ]))->assertOk();
        $this->assertSame('Dosen Dua', $facultyResult->json('results.0.name'));
        $this->getJson(route('survey-edom.results.data', [
            'program' => 'program-2', 'calendar' => 'calendar-1',
        ]))->assertNotFound();

        $this->actingAs($this->user('userprodi', ['id_prodi' => 'program-1']));
        $this->get(route('survey-edom.results.index', ['program' => 'program-2']))->assertNotFound();

        $this->actingAs($this->user('dosen', ['login' => 'NIDN-1', 'id_prodi' => 'program-1']));
        $this->get(route('survey-edom.results.index', ['calendar' => 'calendar-1']))->assertOk();
        $teacher = $this->getJson(route('survey-edom.results.data', ['program' => 'program-1', 'calendar' => 'calendar-1']))
            ->assertOk();
        $teacherLecturers = collect($teacher->json('results'));
        $this->assertCount(1, $teacherLecturers);
        $this->assertSame('lecturer-1', $teacherLecturers->first()['id']);

        $this->actingAs($this->user('asdos', ['login' => 'ASDOS-1', 'id_prodi' => 'program-1']));
        $this->get(route('survey-edom.results.index', ['calendar' => 'calendar-1']))->assertOk();
        $assistant = $this->getJson(route('survey-edom.results.data', ['program' => 'program-1', 'calendar' => 'calendar-1']))
            ->assertOk();
        $this->assertSame(['assistant-1'], collect($assistant->json('results'))->pluck('id')->all());

        $this->actingAs($this->user('usertu', ['id_prodi' => 'program-1']));
        $this->get(route('survey-edom.results.index'))->assertOk()
            ->assertSee('data-selected-program="program-1"', false);
        $this->get(route('survey-edom.results.index', ['program' => 'program-2']))->assertNotFound();
        $tuResult = $this->getJson(route('survey-edom.results.data', [
            'program' => 'program-1', 'calendar' => 'calendar-1',
        ]))->assertOk()->assertJsonPath('program', 'Program Satu');
        $this->assertSame(['lecturer-1'], collect($tuResult->json('results'))->pluck('id')->all());
        $this->getJson(route('survey-edom.results.data', [
            'program' => 'program-2', 'calendar' => 'calendar-2',
        ]))->assertNotFound();
        $this->get(route('survey-edom.results.export', [
            'program' => 'program-1', 'calendar' => 'calendar-1', 'lecturer' => 'lecturer-1',
        ]))->assertOk();
        $this->get(route('survey-edom.results.export', [
            'program' => 'program-2', 'calendar' => 'calendar-2', 'lecturer' => 'lecturer-2',
        ]))->assertNotFound();
    }

    public function test_edom_detail_export_masks_student_identifiers(): void
    {
        $this->actingAs($this->user('admin'));
        $export = $this->get(route('survey-edom.results.export', [
            'program' => 'program-1', 'calendar' => 'calendar-1', 'lecturer' => 'lecturer-1',
        ]))->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $body = $export->streamedContent();
        $this->assertStringContainsString('EDOM-anonim', $export->headers->get('content-disposition'));
        $this->assertStringContainsString('stu****001', $body);
        $this->assertStringNotContainsString('student-000001', $body);
        $this->assertStringNotContainsString('Nama Mahasiswa', $body);

        $this->actingAs($this->user('dosen', ['login' => 'NIDN-2', 'id_prodi' => 'program-1']));
        $this->get(route('survey-edom.results.export', [
            'program' => 'program-1', 'calendar' => 'calendar-1', 'lecturer' => 'lecturer-1',
        ]))->assertNotFound();
    }

    private function createSchema(): void
    {
        Schema::connection('simak_main')->create('s1m4k_masterfakultas', function (Blueprint $table): void {
            $table->string('id_fakultas')->primary();
            $table->string('nama_fakultas');
        });
        Schema::connection('simak_main')->create('s1m4k_masterprodi', function (Blueprint $table): void {
            $table->string('id_prodi')->primary();
            $table->string('id_fakultas')->nullable();
            $table->string('nama_program_studi');
            $table->string('nama_jenjang_pendidikan')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterdosen', function (Blueprint $table): void {
            $table->string('id_dosen')->primary();
            $table->string('id_prodi')->nullable();
            $table->string('nidn')->nullable();
            $table->string('nama_dosen')->nullable();
            $table->string('gelar_depan')->nullable();
            $table->string('gelar_belakang')->nullable();
            $table->string('nama_status_aktif')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterasdos', function (Blueprint $table): void {
            $table->string('id_asdos')->primary();
            $table->string('id_prodi')->nullable();
            $table->string('kdasisten')->nullable();
            $table->string('nama')->nullable();
            $table->string('gelard')->nullable();
            $table->string('gelarb')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_kalender', function (Blueprint $table): void {
            $table->string('id_kalender')->primary();
            $table->string('id_prodi');
            $table->string('tahun');
            $table->string('namatahun');
            $table->string('status');
        });
        Schema::connection('simak_main')->create('s1m4k_jadwal', function (Blueprint $table): void {
            $table->string('id_jadwal')->primary();
            $table->string('id_prodi');
            $table->string('id_matkul');
            $table->string('nama_kelas')->nullable();
            for ($slot = 1; $slot <= 9; $slot++) {
                $table->string('dosen'.$slot)->nullable();
            }
        });
        Schema::connection('simak_main')->create('s1m4k_mastermk', function (Blueprint $table): void {
            $table->string('id_matkul')->primary();
            $table->string('nama_mata_kuliah')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_mastermhs', function (Blueprint $table): void {
            $table->string('id_mahasiswa')->primary();
            $table->string('nim')->nullable();
            $table->string('nama_mahasiswa')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_krs', function (Blueprint $table): void {
            $table->string('id_krs')->primary();
            $table->string('id_mahasiswa');
        });
        Schema::connection('simak_main')->create('s1m4k_survey_edom', function (Blueprint $table): void {
            $table->increments('id_survey');
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
        $connection = DB::connection('simak_main');
        $connection->table('s1m4k_masterfakultas')->insert([
            ['id_fakultas' => 'faculty-1', 'nama_fakultas' => 'Fakultas Satu'],
            ['id_fakultas' => 'faculty-2', 'nama_fakultas' => 'Fakultas Dua'],
        ]);
        $connection->table('s1m4k_masterprodi')->insert([
            ['id_prodi' => 'program-1', 'id_fakultas' => 'faculty-1', 'nama_program_studi' => 'Program Satu'],
            ['id_prodi' => 'program-2', 'id_fakultas' => 'faculty-2', 'nama_program_studi' => 'Program Dua'],
        ]);
        $connection->table('s1m4k_masterdosen')->insert([
            ['id_dosen' => 'lecturer-1', 'id_prodi' => 'program-1', 'nidn' => 'NIDN-1', 'nama_dosen' => 'Dosen Satu', 'nama_status_aktif' => 'aktif'],
            ['id_dosen' => 'lecturer-2', 'id_prodi' => 'program-2', 'nidn' => 'NIDN-2', 'nama_dosen' => 'Dosen Dua', 'nama_status_aktif' => 'aktif'],
        ]);
        $connection->table('s1m4k_masterasdos')->insert([
            ['id_asdos' => 'assistant-1', 'id_prodi' => 'program-1', 'kdasisten' => 'ASDOS-1', 'nama' => 'Asisten Satu'],
        ]);
        $connection->table('s1m4k_mastermk')->insert([
            ['id_matkul' => 'course-1', 'nama_mata_kuliah' => 'Mata Kuliah Satu'],
            ['id_matkul' => 'course-2', 'nama_mata_kuliah' => 'Mata Kuliah Dua'],
        ]);
        $connection->table('s1m4k_kalender')->insert([
            ['id_kalender' => 'calendar-1', 'id_prodi' => 'program-1', 'tahun' => '20251', 'namatahun' => '2025 Ganjil', 'status' => 'Y'],
            ['id_kalender' => 'calendar-2', 'id_prodi' => 'program-2', 'tahun' => '20251', 'namatahun' => '2025 Ganjil', 'status' => 'Y'],
        ]);
        $connection->table('s1m4k_mastermhs')->insert([
            ['id_mahasiswa' => 'student-000001', 'nim' => 'A001', 'nama_mahasiswa' => 'Nama Mahasiswa'],
            ['id_mahasiswa' => 'student-000002', 'nim' => 'A002', 'nama_mahasiswa' => 'Mahasiswa Dua'],
        ]);
        $connection->table('s1m4k_jadwal')->insert([
            ['id_jadwal' => 'schedule-1', 'id_prodi' => 'program-1', 'id_matkul' => 'course-1', 'nama_kelas' => 'Kelas A', 'dosen1' => 'lecturer-1', 'dosen2' => 'assistant-1'],
            ['id_jadwal' => 'schedule-2', 'id_prodi' => 'program-1', 'id_matkul' => 'course-1', 'nama_kelas' => 'Kelas B', 'dosen1' => 'lecturer-1', 'dosen2' => null],
            ['id_jadwal' => 'schedule-3', 'id_prodi' => 'program-2', 'id_matkul' => 'course-2', 'nama_kelas' => 'Kelas C', 'dosen1' => 'lecturer-2', 'dosen2' => null],
        ]);
        $connection->table('s1m4k_krs')->insert([
            ['id_krs' => 'krs-1', 'id_mahasiswa' => 'student-000001'],
            ['id_krs' => 'krs-2', 'id_mahasiswa' => 'student-000002'],
        ]);
        $questions = [];
        foreach (range(1, 6) as $id) {
            $questions[] = ['id_survey' => $id, 'pertanyaan' => 'Pertanyaan '.$id];
        }
        $connection->table('s1m4k_survey_edom')->insert($questions);

        $answers = [];
        foreach ([
            ['krs-1', 'schedule-1', [5, 4, 3, 2, 1, 5]],
            ['krs-2', 'schedule-1', [4, 3, 3, 2, 1, 4]],
            ['krs-1', 'schedule-2', [5, 4, 3, 2, 1, 5]],
        ] as [$krsId, $scheduleId, $scores]) {
            foreach ($scores as $index => $score) {
                $answers[] = [
                    'id_edom' => 'edom-'.$scheduleId.'-'.$krsId.'-'.($index + 1),
                    'id_krs' => $krsId,
                    'id_jadwal' => $scheduleId,
                    'id_kalender' => 'calendar-1',
                    'id_survey' => (string) ($index + 1),
                    'nilai' => $score,
                ];
            }
        }
        $connection->table('s1m4k_edom')->insert($answers);
    }

    private function user(string $role, array $extra = []): GenericUser
    {
        return new GenericUser(array_merge([
            'id' => $role.'-edom-test',
            'legacy_id' => $role.'-edom-test',
            'legacy_type' => $role === 'dosen' ? 'dosen' : ($role === 'asdos' ? 'asdos' : 'admin'),
            'login' => '',
            'role' => $role,
            'name' => 'Pengguna EDOM',
            'id_prodi' => 'program-1',
            'id_fakultas' => 'faculty-1',
        ], $extra));
    }
}
