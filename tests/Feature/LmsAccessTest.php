<?php

namespace Tests\Feature;

use App\Http\Middleware\LogUntrackedActivity;
use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class LmsAccessTest extends TestCase
{
    public function test_student_material_library_groups_visible_materials_by_lecturer_and_course(): void
    {
        config(['database.connections.simak_main' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]]);
        DB::purge('simak_main');
        Schema::connection('simak_main')->create('lms_materi_kuliah', function (Blueprint $table): void {
            $table->string('id_materi')->primary();
            $table->string('id_dosen');
            $table->string('id_matkul')->nullable();
            $table->string('id_prodi')->nullable();
            $table->string('judul');
            $table->text('deskripsi')->nullable();
            $table->string('nama_berkas')->nullable();
            $table->string('path_berkas')->nullable();
            $table->string('status_publikasi');
            $table->string('dibuat_pada')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_mastermk', function (Blueprint $table): void {
            $table->string('id_matkul')->primary();
            $table->string('kode_mata_kuliah');
            $table->string('nama_mata_kuliah');
            $table->string('id_prodi')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterdosen', function (Blueprint $table): void {
            $table->string('id_dosen')->primary();
            $table->string('nama_dosen');
            $table->string('gelar_depan')->nullable();
            $table->string('gelar_belakang')->nullable();
            $table->string('nidn')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterprodi', function (Blueprint $table): void {
            $table->string('id_prodi')->primary();
            $table->string('nama_program_studi');
            $table->string('id_fakultas')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_mastermhs', function (Blueprint $table): void {
            $table->string('id_mahasiswa')->primary();
            $table->string('nim');
            $table->string('id_prodi')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_jadwal', function (Blueprint $table): void {
            $table->string('id_jadwal')->primary();
            $table->string('id_prodi');
            $table->string('id_kalender');
            $table->string('id_matkul');
            $table->string('nama_kelas');
            $table->string('dosen1')->nullable();
            $table->string('dosen2')->nullable();
            $table->string('dosen3')->nullable();
            $table->string('dosen4')->nullable();
            $table->string('dosen5')->nullable();
            $table->string('dosen6')->nullable();
            $table->string('dosen7')->nullable();
            $table->string('dosen8')->nullable();
            $table->string('dosen9')->nullable();
            $table->string('dosen10')->nullable();
            $table->string('dosen11')->nullable();
            $table->string('dosen12')->nullable();
            $table->string('dosen13')->nullable();
            $table->string('dosen14')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_kalender', function (Blueprint $table): void {
            $table->string('id_kalender')->primary();
            $table->string('namatahun');
            $table->string('tahun');
            $table->string('status');
            $table->string('status_sp')->default('N');
        });
        Schema::connection('simak_main')->create('s1m4k_krs', function (Blueprint $table): void {
            $table->string('id_krs')->primary();
            $table->string('id_mahasiswa');
            $table->string('id_jadwal');
            $table->string('id_kalender');
        });
        Schema::connection('simak_main')->create('s1m4k_pengesahankrs', function (Blueprint $table): void {
            $table->string('id_pengesahankrs')->primary();
            $table->string('id_mahasiswa');
            $table->string('id_kalender');
            $table->string('yang_mengesahkan')->nullable();
            $table->string('nama_kpro')->nullable();
        });
        Schema::connection('simak_main')->create('lms_pertemuan_materi', function (Blueprint $table): void {
            $table->string('id_pertemuan_materi')->primary();
            $table->string('id_pertemuan');
            $table->string('id_materi');
        });
        Schema::connection('simak_main')->create('lms_pertemuan', function (Blueprint $table): void {
            $table->string('id_pertemuan')->primary();
            $table->string('id_materi')->nullable();
        });

        DB::connection('simak_main')->table('s1m4k_masterdosen')->insert([
            ['id_dosen' => 'lecturer-1', 'nama_dosen' => 'Dosen Satu', 'nidn' => '0001'],
            ['id_dosen' => 'lecturer-2', 'nama_dosen' => 'Dosen Dua', 'nidn' => '0002'],
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            ['id_mahasiswa' => 'student-1', 'nim' => 'NIM-1', 'id_prodi' => 'program-1'],
        ]);
        DB::connection('simak_main')->table('s1m4k_masterprodi')->insert([
            ['id_prodi' => 'program-1', 'nama_program_studi' => 'Teknik Informatika', 'id_fakultas' => 'faculty-1'],
            ['id_prodi' => 'program-2', 'nama_program_studi' => 'Manajemen', 'id_fakultas' => 'faculty-2'],
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermk')->insert([
            ['id_matkul' => 'course-1', 'id_prodi' => 'program-1', 'kode_mata_kuliah' => 'MK-001', 'nama_mata_kuliah' => 'Algoritma'],
            ['id_matkul' => 'course-2', 'id_prodi' => 'program-1', 'kode_mata_kuliah' => 'MK-002', 'nama_mata_kuliah' => 'Basis Data'],
            ['id_matkul' => 'course-3', 'id_prodi' => 'program-1', 'kode_mata_kuliah' => 'MK-003', 'nama_mata_kuliah' => 'Struktur Data'],
            ['id_matkul' => 'course-foreign', 'id_prodi' => 'program-2', 'kode_mata_kuliah' => 'MK-999', 'nama_mata_kuliah' => 'Manajemen Keuangan'],
        ]);
        DB::connection('simak_main')->table('s1m4k_kalender')->insert([
            ['id_kalender' => 'calendar-1', 'namatahun' => '2025/2026 Ganjil', 'tahun' => '20261', 'status' => 'Y'],
            ['id_kalender' => 'calendar-2', 'namatahun' => '2024/2025 Genap', 'tahun' => '20201', 'status' => 'Y'],
        ]);
        DB::connection('simak_main')->table('s1m4k_jadwal')->insert([
            ['id_jadwal' => 'schedule-1', 'id_prodi' => 'program-1', 'id_kalender' => 'calendar-1', 'id_matkul' => 'course-1', 'nama_kelas' => 'A', 'dosen1' => 'lecturer-1', 'dosen2' => null],
            ['id_jadwal' => 'schedule-2', 'id_prodi' => 'program-1', 'id_kalender' => 'calendar-2', 'id_matkul' => 'course-2', 'nama_kelas' => 'B',             'dosen1' => 'lecturer-2', 'dosen2' => null],
            ['id_jadwal' => 'schedule-3', 'id_prodi' => 'program-1', 'id_kalender' => 'calendar-2', 'id_matkul' => 'course-3', 'nama_kelas' => 'C', 'dosen1' => 'lecturer-1', 'dosen2' => null],
            ['id_jadwal' => 'schedule-4', 'id_prodi' => 'program-1', 'id_kalender' => 'calendar-1', 'id_matkul' => 'course-3', 'nama_kelas' => 'D', 'dosen1' => 'lecturer-1', 'dosen2' => null],
            ['id_jadwal' => 'schedule-foreign', 'id_prodi' => 'program-2', 'id_kalender' => 'calendar-1', 'id_matkul' => 'course-foreign', 'nama_kelas' => 'Z', 'dosen1' => 'lecturer-2', 'dosen2' => null],
        ]);
        DB::connection('simak_main')->table('s1m4k_krs')->insert([
            ['id_krs' => 'krs-1', 'id_mahasiswa' => 'student-1', 'id_jadwal' => 'schedule-1', 'id_kalender' => 'calendar-1'],
            ['id_krs' => 'krs-2', 'id_mahasiswa' => 'student-1', 'id_jadwal' => 'schedule-2', 'id_kalender' => 'calendar-2'],
        ]);
        DB::connection('simak_main')->table('s1m4k_pengesahankrs')->insert([
            ['id_pengesahankrs' => 'approval-1', 'id_mahasiswa' => 'student-1', 'id_kalender' => 'calendar-1', 'yang_mengesahkan' => 'Ketua Program Studi', 'nama_kpro' => 'Validasi'],
            ['id_pengesahankrs' => 'approval-2', 'id_mahasiswa' => 'student-1', 'id_kalender' => 'calendar-2', 'yang_mengesahkan' => 'Ketua Program Studi', 'nama_kpro' => 'Validasi'],
        ]);
        DB::connection('simak_main')->table('lms_materi_kuliah')->insert([
            ['id_materi' => 'material-1', 'id_dosen' => 'lecturer-1', 'id_matkul' => 'course-1', 'id_prodi' => 'program-1', 'judul' => 'Materi Algoritma', 'nama_berkas' => 'algoritma.pdf', 'path_berkas' => 'materi/algoritma.pdf', 'status_publikasi' => 'terbit'],
            ['id_materi' => 'material-2', 'id_dosen' => 'lecturer-2', 'id_matkul' => 'course-2', 'id_prodi' => 'program-1', 'judul' => 'Materi Basis Data', 'nama_berkas' => null, 'path_berkas' => null, 'status_publikasi' => 'terbit'],
            ['id_materi' => 'material-foreign', 'id_dosen' => 'lecturer-2', 'id_matkul' => 'course-foreign', 'id_prodi' => 'program-2', 'judul' => 'Materi Luar Prodi', 'nama_berkas' => 'luar-prodi.pdf', 'path_berkas' => 'materi/luar-prodi.pdf', 'status_publikasi' => 'terbit'],
        ]);

        $this->actingAs(new GenericUser(['id' => 'student-1', 'login' => 'NIM-1', 'role' => 'mahasiswa']))
            ->get(route('lms.materials.index'))
            ->assertOk()
            ->assertSee('Materi dari dosen')
            ->assertSee('data-material-library-search', false)
            ->assertSee('Cari nama dosen atau mata kuliah')
            ->assertSee('data-material-library-grid', false)
            ->assertSee('data-material-lecturer=', false)
            ->assertSee('data-material-course=', false)
            ->assertSee('data-material-library-empty', false)
            ->assertSee('Dosen Satu')
            ->assertSee('Dosen Dua')
            ->assertSee('Algoritma')
            ->assertSee('1 materi')
            ->assertSee('Struktur Data')
            ->assertDontSee('Manajemen Keuangan')
            ->assertDontSee('Materi Luar Prodi');

        $this->actingAs(new GenericUser(['id' => 'student-1', 'login' => 'NIM-1', 'role' => 'mahasiswa']))
            ->get(route('lms.materials.index', ['id_dosen' => 'lecturer-1', 'id_matkul' => 'course-1', 'id_prodi' => 'program-1']))
            ->assertOk()
            ->assertSee('Materi Algoritma')
            ->assertDontSee('Materi Basis Data')
            ->assertSee('Unduh')
            ->assertSee(route('lms.materials.download', 'material-1'));

        $this->actingAs(new GenericUser(['id' => 'student-1', 'login' => 'NIM-1', 'role' => 'mahasiswa']))
            ->get(route('lms.materials.index', ['id_dosen' => 'lecturer-1', 'id_matkul' => 'course-3', 'id_prodi' => 'program-1']))
            ->assertOk()
            ->assertSee('Belum ada materi tersedia');
        $this->actingAs(new GenericUser(['id' => 'student-1', 'login' => 'NIM-1', 'role' => 'mahasiswa']))
            ->get(route('lms.materials.index', ['id_dosen' => 'lecturer-1', 'id_matkul' => 'course-1', 'id_prodi' => 'program-2']))
            ->assertOk()
            ->assertSee('Materi Algoritma');

        $this->actingAs(new GenericUser(['id' => 'prodi-1', 'role' => 'userprodi', 'id_prodi' => 'program-1']))
            ->get(route('lms.materials.index'))
            ->assertOk()
            ->assertSee('Materi dari dosen')
            ->assertSee('Dosen Satu')
            ->assertSee('Dosen Dua')
            ->assertSee('Algoritma')
            ->assertDontSee('Manajemen Keuangan');
        $this->actingAs(new GenericUser(['id' => 'tu-1', 'role' => 'usertu', 'id_prodi' => 'program-1']))
            ->get(route('lms.materials.index'))
            ->assertOk()
            ->assertSee('Dosen Dua')
            ->assertDontSee('Manajemen Keuangan');

        $this->actingAs(new GenericUser(['id' => 'faculty-1', 'role' => 'pimfak', 'id_fakultas' => 'faculty-1']))
            ->get(route('lms.materials.index'))
            ->assertOk()
            ->assertSee('id="material-program-select"', false)
            ->assertSee('Pilih program studi terlebih dahulu')
            ->assertDontSee('data-material-lecturer=', false);
        $this->actingAs(new GenericUser(['id' => 'faculty-1', 'role' => 'pimfak', 'id_fakultas' => 'faculty-1']))
            ->get(route('lms.materials.index', ['id_prodi' => 'program-1']))
            ->assertOk()
            ->assertSee('Dosen Satu')
            ->assertSee('Dosen Dua')
            ->assertDontSee('Manajemen Keuangan');

        foreach (['admin', 'pimuniv'] as $role) {
            $this->actingAs(new GenericUser(['id' => $role.'-1', 'role' => $role]))
                ->get(route('lms.materials.index'))
                ->assertOk()
                ->assertSee('id="material-program-select"', false)
                ->assertSee('Pilih program studi terlebih dahulu')
                ->assertDontSee('data-material-lecturer=', false);
            $this->actingAs(new GenericUser(['id' => $role.'-1', 'role' => $role]))
                ->get(route('lms.materials.index', ['id_prodi' => 'program-2']))
                ->assertOk()
                ->assertSee('Dosen Dua')
                ->assertSee('Manajemen Keuangan')
                ->assertDontSee('Dosen Satu')
                ->assertDontSee('Algoritma');
        }
        $this->actingAs(new GenericUser(['id' => 'faculty-1', 'role' => 'pimfak', 'id_fakultas' => 'faculty-1']))
            ->get(route('lms.materials.index'))
            ->assertOk()
            ->assertSee('Teknik Informatika')
            ->assertDontSee('Manajemen</option>', false);
        $this->actingAs(new GenericUser(['id' => 'faculty-1', 'role' => 'pimfak', 'id_fakultas' => 'faculty-1']))
            ->get(route('lms.materials.index', ['id_prodi' => 'program-2']))
            ->assertOk()
            ->assertSee('Pilih program studi terlebih dahulu')
            ->assertDontSee('Dosen Dua')
            ->assertDontSee('Manajemen Keuangan');
    }

    public function test_students_cannot_open_question_bank(): void
    {
        $this->actingAs(new GenericUser(['id' => 'student-1', 'login' => 'NIM-1', 'role' => 'mahasiswa']))
            ->get(route('lms.questions.index'))
            ->assertForbidden();
    }

    public function test_non_lms_roles_are_denied_before_data_access(): void
    {
        $user = new GenericUser(['id' => 'finance-1', 'role' => 'keuangan']);
        $this->actingAs($user)
            ->get(route('lms.materials.index'))
            ->assertForbidden();

        $this->get(route('lms.materials.download', 'material-secret'))->assertForbidden();
    }

    public function test_lms_navigation_is_available_for_each_requested_role(): void
    {
        $navigation = config('simak.navigation');
        $lmsModules = static function (string $role) use ($navigation): array {
            foreach ($navigation[$role] as $group) {
                if (($group['label'] ?? null) === 'LMS Virtual Class') {
                    return array_column($group['items'], 'label');
                }
            }

            return [];
        };

        foreach (['admin', 'pimuniv', 'pimfak', 'userprodi', 'usertu', 'dosen'] as $role) {
            $this->assertSame(
                ['Materi Kuliah', 'Pertemuan Kuliah', 'Tugas', 'Bank Soal', 'Diskusi'],
                $lmsModules($role),
                "LMS menu for {$role} should expose all modules."
            );
        }
        foreach (['admin', 'dosen', 'mahasiswa', 'pimuniv', 'pimfak', 'userprodi', 'usertu'] as $role) {
            $groupLabels = array_column($navigation[$role], 'label');
            $this->assertLessThan(
                array_search('LMS Virtual Class', $groupLabels, true),
                array_search('Akademik', $groupLabels, true),
                "LMS menu for {$role} should appear after Akademik."
            );
        }
        $this->assertSame(
            ['Materi Kuliah', 'Pertemuan Kuliah', 'Tugas', 'Diskusi'],
            $lmsModules('mahasiswa')
        );

        $groupIcons = config('simak.navigation_group_icons');
        foreach ($navigation as $role => $groups) {
            foreach ($groups as $group) {
                $this->assertArrayHasKey($group['label'], $groupIcons, "Navigation parent {$group['label']} for {$role} needs an icon.");
            }
        }
    }

    public function test_exam_creation_back_links_return_to_meeting_list(): void
    {
        $this->prepareExamTables();
        $this->actingAs(new GenericUser(['id' => 'lecturer-user', 'login' => 'NIDN-12345', 'role' => 'dosen']))
            ->get(route('lms.exams.create', 'meeting-2'))
            ->assertOk()
            ->assertSee(route('lms.meetings.index'))
            ->assertSee('Pilih soal')
            ->assertSee('lms-exam-question-modal')
            ->assertSee('data-exam-randomize-answer-field', false)
            ->assertSee('data-exam-score-field', false)
            ->assertSee('hidden', false)
            ->assertSee('syncRandomizeAnswerVisibility', false)
            ->assertSee('data-exam-question-table', false)
            ->assertSee('data-exam-select-all', false)
            ->assertSee('Pilih semua')
            ->assertSee('Soal yang dipilih masih kurang', false)
            ->assertSee('Soal berhasil dipilih', false)
            ->assertSee('is-success', false)
            ->assertSeeInOrder(['data-exam-select-all', '<th>Jenis</th>', '<th>Soal</th>'], false)
            ->assertSee('data-multiple-choice-count="0"', false)
            ->assertSee('data-essay-count="0"', false)
            ->assertDontSee('name="sumber_soal"', false);

        $this->withSession(['_old_input' => ['jenis_ujian' => 'pilihan_ganda']])
            ->get(route('lms.exams.create', 'meeting-2'))
            ->assertOk()
            ->assertSee('data-exam-randomize-answer-field', false)
            ->assertSee('data-exam-score-field', false)
            ->assertDontSee('data-exam-randomize-answer-field hidden', false);
    }

    public function test_exam_creation_requires_enough_selected_questions(): void
    {
        $this->prepareExamTables();
        $questionIds = [(string) Str::uuid(), (string) Str::uuid()];
        foreach ($questionIds as $questionId) {
            DB::connection('simak_main')->table('lms_bank_soal')->insert([
                'id_soal' => $questionId,
                'id_dosen' => 'lecturer-1',
                'id_matkul' => 'course-1',
                'jenis_soal' => 'essay',
                'pertanyaan' => 'Jelaskan konsep dasar pemrograman web.',
                'aktif' => true,
            ]);
        }

        $this->actingAs(new GenericUser(['id' => 'lecturer-user', 'login' => 'NIDN-12345', 'role' => 'dosen']))
            ->from(route('lms.exams.create', 'meeting-2'))
            ->post(route('lms.exams.store', 'meeting-2'), [
                'judul' => 'Ujian essay',
                'jenis_ujian' => 'essay',
                'id_soal' => [$questionIds[0]],
                'jumlah_soal_disajikan' => 2,
                'urutan_soal' => 'acak',
                'acak_pilihan_jawaban' => '0',
                'durasi_menit' => 60,
                'batas_mulai' => '2030-01-01T09:00',
                'batas_selesai' => '2030-01-01T10:00',
            ])
            ->assertRedirect(route('lms.exams.create', 'meeting-2'))
            ->assertSessionHasErrors('id_soal');
    }

    public function test_successful_exam_creation_redirects_to_meeting_list(): void
    {
        $this->prepareExamTables();
        $this->withoutMiddleware(LogUntrackedActivity::class);
        $questionIds = [(string) Str::uuid(), (string) Str::uuid()];
        foreach ($questionIds as $questionId) {
            DB::connection('simak_main')->table('lms_bank_soal')->insert([
                'id_soal' => $questionId,
                'id_dosen' => 'lecturer-1',
                'id_matkul' => 'course-1',
                'jenis_soal' => 'essay',
                'pertanyaan' => 'Jelaskan konsep dasar pemrograman web.',
                'aktif' => true,
            ]);
        }

        $this->actingAs(new GenericUser(['id' => 'lecturer-user', 'login' => 'NIDN-12345', 'role' => 'dosen']))
            ->post(route('lms.exams.store', 'meeting-2'), [
                'judul' => 'Ujian essay',
                'jenis_ujian' => 'essay',
                'id_soal' => $questionIds,
                'jumlah_soal_disajikan' => 2,
                'urutan_soal' => 'acak',
                'acak_pilihan_jawaban' => '0',
                'durasi_menit' => 60,
                'batas_mulai' => '2030-01-01T09:00',
                'batas_selesai' => '2030-01-01T10:00',
            ])
            ->assertRedirect(route('lms.meetings.index'))
            ->assertSessionHas('success', 'Ujian berhasil dibuat untuk pertemuan ini.');

        $this->assertDatabaseHas('lms_ujian', [
            'id_pertemuan' => 'meeting-2',
            'judul' => 'Ujian essay',
        ], 'simak_main');
        $this->assertSame(2, DB::connection('simak_main')->table('lms_ujian_soal')->count());
    }

    public function test_essay_exam_creation_normalizes_multiple_choice_only_fields(): void
    {
        $this->prepareExamTables();
        $this->withoutMiddleware(LogUntrackedActivity::class);
        $questionId = (string) Str::uuid();
        DB::connection('simak_main')->table('lms_bank_soal')->insert([
            'id_soal' => $questionId,
            'id_dosen' => 'lecturer-1',
            'id_matkul' => 'course-1',
            'jenis_soal' => 'essay',
            'pertanyaan' => 'Jelaskan konsep dasar.',
            'aktif' => true,
        ]);

        $this->actingAs(new GenericUser(['id' => 'lecturer-user', 'login' => 'NIDN-12345', 'role' => 'dosen']))
            ->post(route('lms.exams.store', 'meeting-2'), [
                'judul' => 'Ujian essay',
                'jenis_ujian' => 'essay',
                'id_soal' => [$questionId],
                'jumlah_soal_disajikan' => 1,
                'urutan_soal' => 'acak',
                'acak_pilihan_jawaban' => '1',
                'nilai_per_soal' => '1',
                'durasi_menit' => 60,
                'batas_mulai' => '2030-01-01T09:00',
                'batas_selesai' => '2030-01-01T10:00',
            ])
            ->assertRedirect(route('lms.meetings.index'));

        $this->assertDatabaseHas('lms_ujian', [
            'id_pertemuan' => 'meeting-2',
            'jenis_ujian' => 'essay',
            'acak_pilihan_jawaban' => false,
            'nilai_per_soal' => null,
        ], 'simak_main');
    }

    public function test_student_can_autosave_answers_and_submit_exam_early(): void
    {
        $this->prepareExamTables();
        $this->withoutMiddleware(LogUntrackedActivity::class);
        $this->prepareStudentExamAttempt(now('Asia/Jayapura')->subMinute()->toDateTimeString());
        DB::connection('simak_main')->table('lms_detail_hasil_ujian')->insert([
            'id_detail_hasil' => 'detail-active-1',
            'id_hasil_ujian' => 'result-active-1',
            'id_soal' => null,
            'urutan_tampil' => 1,
            'soal_snapshot' => json_encode(['pertanyaan' => 'Jelaskan.', 'jenis_soal' => 'essay', 'opsi' => []]),
        ]);

        $this->actingAs(new GenericUser(['id' => 'student-user', 'login' => 'NIM-1', 'role' => 'mahasiswa']))
            ->get(route('lms.exams.show', 'exam-1'))
            ->assertOk()
            ->assertSee('data-deadline-ms=', false)
            ->assertSee('data-server-now-ms=', false)
            ->assertSee('Kumpulkan ujian?')
            ->assertSee('SOAL 01');

        $this->actingAs(new GenericUser(['id' => 'student-user', 'login' => 'NIM-1', 'role' => 'mahasiswa']))
            ->putJson(route('lms.exams.answers', 'exam-1'), ['jawaban' => ['detail-active-1' => 'Jawaban autosave']])
            ->assertOk()
            ->assertJsonPath('message', 'Jawaban tersimpan.');
        $this->assertDatabaseHas('lms_detail_hasil_ujian', [
            'id_detail_hasil' => 'detail-active-1',
            'jawaban_mahasiswa' => 'Jawaban autosave',
        ], 'simak_main');

        $this->post(route('lms.exams.submit', 'exam-1'), ['jawaban' => ['detail-active-1' => 'Jawaban final']])
            ->assertRedirect(route('lms.exams.show', 'exam-1'));
        $this->assertDatabaseHas('lms_hasil_ujian', [
            'id_hasil_ujian' => 'result-active-1',
            'status_hasil' => 'terkumpul',
        ], 'simak_main');
        $this->assertDatabaseHas('lms_detail_hasil_ujian', [
            'id_detail_hasil' => 'detail-active-1',
            'jawaban_mahasiswa' => 'Jawaban final',
        ], 'simak_main');
        $notification = DB::connection('simak_main')->table('eskripsi_notifikasi')->first();
        $this->assertNotNull($notification);
        $this->assertSame('dosen', $notification->recipient_role);
        $this->assertSame('lecturer-1', $notification->recipient_id);
        $this->assertSame('lms-exam-submitted', $notification->category);
        $this->assertStringNotContainsString('Jawaban final', $notification->message);
        $this->actingAs(new GenericUser(['id' => 'lecturer-1', 'login' => 'NIDN-12345', 'role' => 'dosen']))
            ->put(route('lms.exams.grade', ['record' => 'exam-1', 'result' => 'result-active-1']), [
                'nilai_akhir' => 85,
                'komentar_dosen' => 'Mohon revisi jawaban.',
            ])->assertRedirect()
            ->assertSessionHas('success', 'Nilai essay berhasil disimpan.');
        $gradeNotification = DB::connection('simak_main')->table('eskripsi_notifikasi')
            ->where('category', 'lms-exam-graded')->first();
        $this->assertNotNull($gradeNotification);
        $this->assertSame('mahasiswa', $gradeNotification->recipient_role);
        $this->assertSame('student-1', $gradeNotification->recipient_id);
        $this->assertStringNotContainsString('85', $gradeNotification->message);
    }

    public function test_student_can_review_submitted_answers_without_per_question_grading_data(): void
    {
        $this->prepareExamTables();
        $this->prepareStudentExamAttempt(now('Asia/Jayapura')->subHours(2)->toDateTimeString());
        DB::connection('simak_main')->table('lms_hasil_ujian')->where('id_hasil_ujian', 'result-active-1')->update([
            'status_hasil' => 'terkumpul',
            'dikumpulkan_pada' => now('Asia/Jayapura')->toDateTimeString(),
            'nilai_akhir' => 82,
        ]);
        DB::connection('simak_main')->table('lms_detail_hasil_ujian')->insert([
            'id_detail_hasil' => 'detail-review-1',
            'id_hasil_ujian' => 'result-active-1',
            'id_soal' => null,
            'urutan_tampil' => 1,
            'soal_snapshot' => json_encode([
                'pertanyaan' => 'Jelaskan konsep yang dipelajari.',
                'jenis_soal' => 'essay',
                'opsi' => [],
                'kunci_jawaban' => 'Kunci rahasia dosen',
            ]),
            'jawaban_mahasiswa' => 'Ini jawaban mahasiswa.',
            'jawaban_benar' => true,
            'nilai_jawaban' => 10,
        ]);

        $this->actingAs(new GenericUser(['id' => 'student-user', 'login' => 'NIM-1', 'role' => 'mahasiswa']))
            ->get(route('lms.exams.show', 'exam-1'))
            ->assertOk()
            ->assertSee('Hasil kerja Anda')
            ->assertSee('Jelaskan konsep yang dipelajari.')
            ->assertSee('Ini jawaban mahasiswa.')
            ->assertSee('Sedang diperiksa oleh dosen')
            ->assertDontSee('Kunci rahasia dosen')
            ->assertDontSee('jawaban_benar')
            ->assertDontSee('nilai_jawaban')
            ->assertDontSee('Ada draft lokal yang belum tersinkron.');
    }

    public function test_multiple_choice_exam_scores_correct_answers_with_case_normalized_keys(): void
    {
        $this->prepareExamTables();
        $this->withoutMiddleware(LogUntrackedActivity::class);
        $this->prepareStudentExamAttempt(now('Asia/Jayapura')->subMinute()->toDateTimeString());
        DB::connection('simak_main')->table('lms_ujian')->where('id_ujian', 'exam-1')->update([
            'jenis_ujian' => 'pilihan_ganda',
            'nilai_per_soal' => 10,
        ]);
        DB::connection('simak_main')->table('lms_detail_hasil_ujian')->insert([
            'id_detail_hasil' => 'detail-choice-1',
            'id_hasil_ujian' => 'result-active-1',
            'id_soal' => null,
            'urutan_tampil' => 1,
            'soal_snapshot' => json_encode([
                'pertanyaan' => 'Berapakah hasilnya?',
                'jenis_soal' => 'pilihan_ganda',
                'opsi' => ['A' => 'Dua', 'B' => 'Empat'],
                'kunci_jawaban' => 'B',
            ]),
        ]);

        $this->actingAs(new GenericUser(['id' => 'student-user', 'login' => 'NIM-1', 'role' => 'mahasiswa']))
            ->post(route('lms.exams.submit', 'exam-1'), ['jawaban' => ['detail-choice-1' => 'b']])
            ->assertRedirect(route('lms.exams.show', 'exam-1'));

        $this->assertDatabaseHas('lms_detail_hasil_ujian', [
            'id_detail_hasil' => 'detail-choice-1',
            'jawaban_benar' => true,
            'nilai_jawaban' => 10,
        ], 'simak_main');
        $this->assertDatabaseHas('lms_hasil_ujian', [
            'id_hasil_ujian' => 'result-active-1',
            'nilai_akhir' => 100,
            'status_hasil' => 'dinilai',
        ], 'simak_main');
        $this->get(route('lms.exams.show', 'exam-1'))
            ->assertOk()
            ->assertSee('100,00');
    }

    public function test_randomized_multiple_choice_answers_keep_their_key_and_score_as_percentage(): void
    {
        $this->travelTo(Carbon::parse('2026-10-25 09:30:00', 'Asia/Jayapura'));
        $this->prepareExamTables();
        $this->withoutMiddleware(LogUntrackedActivity::class);
        $this->prepareStudentExamAttempt(now('Asia/Jayapura')->subMinute()->toDateTimeString());
        DB::connection('simak_main')->table('lms_hasil_ujian')->where('id_hasil_ujian', 'result-active-1')->delete();
        DB::connection('simak_main')->table('lms_ujian')->where('id_ujian', 'exam-1')->update([
            'jenis_ujian' => 'pilihan_ganda',
            'jumlah_soal_disajikan' => 5,
            'acak_pilihan_jawaban' => true,
            'nilai_per_soal' => 1,
        ]);

        $expectedAnswers = [];
        for ($number = 1; $number <= 5; $number++) {
            $questionId = (string) Str::uuid();
            $options = [
                'opsi_a' => "Soal {$number} - opsi A",
                'opsi_b' => "Soal {$number} - opsi B",
                'opsi_c' => "Soal {$number} - opsi C",
                'opsi_d' => "Soal {$number} - opsi D",
                'opsi_e' => "Soal {$number} - opsi E",
            ];
            $correctKey = ['A', 'B', 'C', 'D', 'E'][$number - 1];
            $expectedAnswers[$questionId] = $options['opsi_'.strtolower($correctKey)];
            DB::connection('simak_main')->table('lms_bank_soal')->insert(array_merge([
                'id_soal' => $questionId,
                'id_dosen' => 'lecturer-1',
                'id_matkul' => 'course-1',
                'jenis_soal' => 'pilihan_ganda',
                'pertanyaan' => "Pertanyaan {$number}",
                'kunci_jawaban' => $correctKey,
                'aktif' => true,
            ], $options));
            DB::connection('simak_main')->table('lms_ujian_soal')->insert([
                'id_ujian_soal' => (string) Str::uuid(),
                'id_ujian' => 'exam-1',
                'id_soal' => $questionId,
                'urutan_tetap' => $number,
            ]);
        }

        $this->actingAs(new GenericUser(['id' => 'student-user', 'login' => 'NIM-1', 'role' => 'mahasiswa']))
            ->post(route('lms.exams.start', 'exam-1'))
            ->assertRedirect(route('lms.exams.show', 'exam-1'));

        $details = DB::connection('simak_main')->table('lms_detail_hasil_ujian')
            ->where('id_hasil_ujian', '!=', '')->get();
        $answers = [];
        foreach ($details as $index => $detail) {
            $snapshot = json_decode($detail->soal_snapshot, true, 512, JSON_THROW_ON_ERROR);
            $correctText = $expectedAnswers[$detail->id_soal];
            $correctKey = array_search($correctText, $snapshot['opsi'], true);
            $this->assertNotFalse($correctKey);
            $answers[$detail->id_detail_hasil] = $index < 4
                ? $correctKey
                : (array_key_first(array_diff_key($snapshot['opsi'], [$correctKey => true])));
        }

        $this->post(route('lms.exams.submit', 'exam-1'), ['jawaban' => $answers])
            ->assertRedirect(route('lms.exams.show', 'exam-1'));

        $this->assertDatabaseHas('lms_hasil_ujian', [
            'id_ujian' => 'exam-1',
            'nilai_akhir' => 80,
            'status_hasil' => 'dinilai',
        ], 'simak_main');
        $this->assertSame(4, DB::connection('simak_main')->table('lms_detail_hasil_ujian')
            ->where('jawaban_benar', true)->count());
    }

    public function test_exam_results_table_has_no_colspan_placeholder_when_there_are_no_attempts(): void
    {
        $this->prepareExamTables();

        $this->actingAs(new GenericUser(['id' => 'lecturer-user', 'login' => 'NIDN-12345', 'role' => 'dosen']))
            ->get(route('lms.exams.show', 'exam-1'))
            ->assertOk()
            ->assertSee('data-datatable="lms-exam-results"', false)
            ->assertSee('<th>NPM</th>', false)
            ->assertDontSee('colspan="5"', false);
    }

    public function test_lecturer_can_edit_exam_configuration_before_any_student_starts(): void
    {
        $this->prepareExamTables();
        $this->withoutMiddleware(LogUntrackedActivity::class);
        DB::connection('simak_main')->table('lms_ujian')->where('id_ujian', 'exam-1')->update([
            'jenis_ujian' => 'pilihan_ganda',
            'acak_pilihan_jawaban' => true,
            'nilai_per_soal' => 1,
            'id_matkul' => 'course-other',
        ]);
        $questionId = (string) Str::uuid();
        $otherCourseQuestionId = (string) Str::uuid();
        DB::connection('simak_main')->table('lms_bank_soal')->insert([
            [
                'id_soal' => (string) Str::uuid(),
                'id_dosen' => 'lecturer-1',
                'id_matkul' => 'course-1',
                'jenis_soal' => 'pilihan_ganda',
                'pertanyaan' => 'Soal pilihan ganda',
                'aktif' => true,
            ],
            [
                'id_soal' => (string) Str::uuid(),
                'id_dosen' => 'lecturer-1',
                'id_matkul' => 'course-1',
                'jenis_soal' => 'essay',
                'pertanyaan' => 'Soal essay',
                'aktif' => true,
            ],
            [
                'id_soal' => $otherCourseQuestionId,
                'id_dosen' => 'lecturer-1',
                'id_matkul' => 'course-other',
                'jenis_soal' => 'essay',
                'pertanyaan' => 'Soal dari mata kuliah lain',
                'aktif' => true,
            ],
        ]);
        $this->actingAs(new GenericUser(['id' => 'lecturer-user', 'login' => 'NIDN-12345', 'role' => 'dosen']))
            ->get(route('lms.exams.show', 'exam-1'))
            ->assertOk()
            ->assertSee('lms-exam-meta-alert', false)
            ->assertSee('name="judul"', false)
            ->assertSee('name="id_soal[]"', false)
            ->assertSee('data-question-type="pilihan_ganda"', false)
            ->assertSee(' disabled', false)
            ->assertSee(' hidden', false)
            ->assertSee('data-question-type="essay"', false)
            ->assertSee('data-exam-randomize-answer-field', false)
            ->assertSee('data-exam-score-field', false)
            ->assertSee('syncExamScoreRequirement', false)
            ->assertDontSee('@hidden', false)
            ->assertDontSee('$exam->jenis_ujian', false)
            ->assertSee('syncExamQuestionOptions', false)
            ->assertSee('syncRandomizeAnswerVisibility', false)
            ->assertSee('name="status_ujian"', false)
            ->assertDontSee('Soal dari mata kuliah lain')
            ->assertSee('Pilih soal minimal sebanyak jumlah soal yang akan disajikan.');
        DB::connection('simak_main')->table('lms_bank_soal')->insert([
            'id_soal' => $questionId,
            'id_dosen' => 'lecturer-1',
            'id_matkul' => 'course-1',
            'jenis_soal' => 'essay',
            'pertanyaan' => 'Jelaskan konsep dasar pemrograman web.',
            'aktif' => true,
        ]);

        $this->put(route('lms.exams.update', 'exam-1'), [
            'judul' => 'Ujian essay revisi',
            'jenis_ujian' => 'essay',
            'id_soal' => [$otherCourseQuestionId],
            'jumlah_soal_disajikan' => 1,
            'urutan_soal' => 'acak',
            'acak_pilihan_jawaban' => '1',
            'nilai_per_soal' => '1',
            'durasi_menit' => 90,
            'batas_mulai' => '2030-01-01T09:00',
            'batas_selesai' => '2030-01-01T10:30',
            'status_ujian' => 'terbit',
        ])->assertSessionHasErrors('id_soal');

        $this->actingAs(new GenericUser(['id' => 'lecturer-user', 'login' => 'NIDN-12345', 'role' => 'dosen']))
            ->put(route('lms.exams.update', 'exam-1'), [
                'judul' => 'Ujian essay revisi',
                'petunjuk' => 'Baca petunjuk dengan teliti.',
                'jenis_ujian' => 'essay',
                'id_soal' => [$questionId],
                'jumlah_soal_disajikan' => 1,
                'urutan_soal' => 'berurutan',
                'acak_pilihan_jawaban' => '1',
                'nilai_per_soal' => '1',
                'durasi_menit' => 90,
                'batas_mulai' => '2030-01-01T09:00',
                'batas_selesai' => '2030-01-01T10:30',
                'status_ujian' => 'terbit',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('lms_ujian', [
            'id_ujian' => 'exam-1',
            'judul' => 'Ujian essay revisi',
            'petunjuk' => 'Baca petunjuk dengan teliti.',
            'jenis_ujian' => 'essay',
            'acak_pilihan_jawaban' => false,
            'nilai_per_soal' => null,
            'durasi_menit' => 90,
            'batas_selesai' => '2030-01-01 10:30:00',
        ], 'simak_main');
        $this->assertDatabaseHas('lms_ujian_soal', [
            'id_ujian' => 'exam-1',
            'id_soal' => $questionId,
            'urutan_tetap' => 1,
        ], 'simak_main');
    }

    public function test_lecturer_can_only_change_exam_end_time_after_a_student_starts(): void
    {
        $this->prepareExamTables();
        $this->withoutMiddleware(LogUntrackedActivity::class);
        $this->prepareStudentExamAttempt(now('Asia/Jayapura')->subMinute()->toDateTimeString());

        $this->actingAs(new GenericUser(['id' => 'lecturer-user', 'login' => 'NIDN-12345', 'role' => 'dosen']))
            ->get(route('lms.exams.show', 'exam-1'))
            ->assertOk()
            ->assertSee('Hanya waktu berakhir yang dapat diubah.')
            ->assertSee('name="batas_selesai"', false)
            ->assertSee('lms-exam-meta-alert', false)
            ->assertDontSee('name="judul"', false);

        $this->put(route('lms.exams.update', 'exam-1'), [
            'batas_selesai' => '2030-01-01T11:00',
            'judul' => 'Judul tidak boleh berubah',
            'durasi_menit' => 15,
            'id_soal' => [],
        ])->assertRedirect();

        $this->assertDatabaseHas('lms_ujian', [
            'id_ujian' => 'exam-1',
            'judul' => 'Ujian contoh',
            'durasi_menit' => 60,
            'batas_selesai' => '2030-01-01 11:00:00',
        ], 'simak_main');
    }

    public function test_lecturer_cannot_edit_an_exam_owned_by_another_lecturer(): void
    {
        $this->prepareExamTables();
        DB::connection('simak_main')->table('s1m4k_masterdosen')->insert([
            'id_dosen' => 'lecturer-2',
            'nama_dosen' => 'Dosen Lain',
            'nidn' => '0002',
        ]);

        $this->actingAs(new GenericUser(['id' => 'other-lecturer', 'login' => '0002', 'role' => 'dosen']))
            ->put(route('lms.exams.update', 'exam-1'), ['batas_selesai' => '2030-01-01T11:00'])
            ->assertNotFound();

        $this->assertDatabaseHas('lms_ujian', [
            'id_ujian' => 'exam-1',
            'batas_selesai' => '2026-10-25 10:00:00',
        ], 'simak_main');
    }

    public function test_lecturer_can_review_each_exam_attempt_and_student_answers(): void
    {
        $this->prepareExamTables();
        $this->prepareStudentExamAttempt(now('Asia/Jayapura')->subHours(2)->toDateTimeString());
        DB::connection('simak_main')->table('lms_hasil_ujian')->where('id_hasil_ujian', 'result-active-1')->update([
            'status_hasil' => 'terkumpul',
            'dikumpulkan_pada' => now('Asia/Jayapura')->toDateTimeString(),
        ]);
        DB::connection('simak_main')->table('lms_detail_hasil_ujian')->insert([
            'id_detail_hasil' => 'detail-lecturer-review-1',
            'id_hasil_ujian' => 'result-active-1',
            'id_soal' => null,
            'urutan_tampil' => 1,
            'soal_snapshot' => json_encode([
                'pertanyaan' => 'Jelaskan konsep dasar.',
                'jenis_soal' => 'essay',
                'opsi' => [],
                'kunci_jawaban' => 'Jawaban acuan',
            ]),
            'jawaban_mahasiswa' => 'Jawaban dari mahasiswa.',
        ]);

        $this->actingAs(new GenericUser(['id' => 'lecturer-user', 'login' => 'NIDN-12345', 'role' => 'dosen']))
            ->get(route('lms.exams.show', 'exam-1'))
            ->assertOk()
            ->assertSee('Lihat pekerjaan')
            ->assertSee('NIM-1')
            ->assertSee('Jelaskan konsep dasar.')
            ->assertSee('Jawaban dari mahasiswa.')
            ->assertDontSee('Jawaban acuan');
    }

    public function test_expired_exam_autosave_finalizes_without_accepting_late_answers(): void
    {
        $this->prepareExamTables();
        $this->prepareStudentExamAttempt(now('Asia/Jayapura')->subHours(2)->toDateTimeString());
        DB::connection('simak_main')->table('lms_detail_hasil_ujian')->insert([
            'id_detail_hasil' => 'detail-expired-1',
            'id_hasil_ujian' => 'result-active-1',
            'id_soal' => null,
            'urutan_tampil' => 1,
            'soal_snapshot' => json_encode(['pertanyaan' => 'Jelaskan.', 'jenis_soal' => 'essay', 'opsi' => []]),
            'jawaban_mahasiswa' => 'Jawaban yang sudah tersimpan',
        ]);

        $this->actingAs(new GenericUser(['id' => 'student-user', 'login' => 'NIM-1', 'role' => 'mahasiswa']))
            ->putJson(route('lms.exams.answers', 'exam-1'), ['jawaban' => ['detail-expired-1' => 'Jawaban terlambat']])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Waktu ujian telah habis dan jawaban tersimpan telah dikumpulkan.');
        $this->assertDatabaseHas('lms_hasil_ujian', [
            'id_hasil_ujian' => 'result-active-1',
            'status_hasil' => 'terkumpul',
        ], 'simak_main');
        $this->assertDatabaseHas('lms_detail_hasil_ujian', [
            'id_detail_hasil' => 'detail-expired-1',
            'jawaban_mahasiswa' => 'Jawaban yang sudah tersimpan',
        ], 'simak_main');
    }

    public function test_client_cannot_inject_exam_deadline_to_start_early(): void
    {
        $this->prepareExamTables();
        $this->prepareStudentExamAttempt(now('Asia/Jayapura')->subMinute()->toDateTimeString());
        DB::connection('simak_main')->table('lms_hasil_ujian')->where('id_ujian', 'exam-1')->delete();
        DB::connection('simak_main')->table('lms_ujian')->where('id_ujian', 'exam-1')->update([
            'batas_mulai' => now('Asia/Jayapura')->addHour()->toDateTimeString(),
            'batas_selesai' => now('Asia/Jayapura')->addHours(2)->toDateTimeString(),
        ]);

        $this->withoutMiddleware(LogUntrackedActivity::class)
            ->actingAs(new GenericUser(['id' => 'student-user', 'login' => 'NIM-1', 'role' => 'mahasiswa']))
            ->from(route('lms.exams.show', 'exam-1'))
            ->post(route('lms.exams.start', 'exam-1'), [
                'deadline' => now('Asia/Jayapura')->addDays(1)->toIso8601String(),
                'remaining_seconds' => 86400,
            ])
            ->assertRedirect(route('lms.exams.show', 'exam-1'))
            ->assertSessionHasErrors('ujian');

        $this->assertDatabaseMissing('lms_hasil_ujian', ['id_ujian' => 'exam-1'], 'simak_main');
    }

    public function test_student_can_resume_with_remaining_attempt_time_but_not_past_exam_close(): void
    {
        $this->prepareExamTables();
        $this->prepareStudentExamAttempt('2026-10-25 10:41:00');
        DB::connection('simak_main')->table('lms_hasil_ujian')->where('id_ujian', 'exam-1')->delete();
        DB::connection('simak_main')->table('lms_ujian')->where('id_ujian', 'exam-1')->update([
            'durasi_menit' => 10,
            'batas_mulai' => '2026-10-25 10:40:00',
            'batas_selesai' => '2026-10-25 11:40:00',
        ]);
        $questionId = (string) Str::uuid();
        DB::connection('simak_main')->table('lms_bank_soal')->insert([
            'id_soal' => $questionId,
            'id_dosen' => 'lecturer-1',
            'id_matkul' => 'course-1',
            'jenis_soal' => 'essay',
            'pertanyaan' => 'Jelaskan konsep dasar pemrograman web.',
            'aktif' => true,
        ]);
        DB::connection('simak_main')->table('lms_ujian_soal')->insert([
            'id_ujian_soal' => 'exam-question-1',
            'id_ujian' => 'exam-1',
            'id_soal' => $questionId,
            'urutan_tetap' => 1,
        ]);

        $student = new GenericUser(['id' => 'student-user', 'login' => 'NIM-1', 'role' => 'mahasiswa']);
        $this->travelTo(Carbon::parse('2026-10-25 10:31:00', 'Asia/Jayapura'));
        $this->withoutMiddleware(LogUntrackedActivity::class)
            ->actingAs($student)
            ->from(route('lms.exams.show', 'exam-1'))
            ->post(route('lms.exams.start', 'exam-1'))
            ->assertSessionHasErrors('ujian');
        $this->assertDatabaseMissing('lms_hasil_ujian', ['id_ujian' => 'exam-1'], 'simak_main');

        $this->travelTo(Carbon::parse('2026-10-25 10:41:00', 'Asia/Jayapura'));
        $this->actingAs($student)
            ->post(route('lms.exams.start', 'exam-1'))
            ->assertRedirect(route('lms.exams.show', 'exam-1'));
        $attempt = DB::connection('simak_main')->table('lms_hasil_ujian')->where('id_ujian', 'exam-1')->first();
        $this->assertSame('2026-10-25 10:41:00', $attempt->dimulai_pada);

        $this->travelTo(Carbon::parse('2026-10-25 10:45:00', 'Asia/Jayapura'));
        $this->get(route('lms.exams.show', 'exam-1'))
            ->assertOk()
            ->assertSee('data-deadline-ms="'.(Carbon::parse('2026-10-25 10:51:00', 'Asia/Jayapura')->getTimestamp() * 1000).'"', false)
            ->assertSee('Ujian berlangsung');
        $this->assertDatabaseHas('lms_hasil_ujian', [
            'id_hasil_ujian' => $attempt->id_hasil_ujian,
            'status_hasil' => 'berlangsung',
        ], 'simak_main');

        $this->travelTo(Carbon::parse('2026-10-25 10:51:00', 'Asia/Jayapura'));
        $this->get(route('lms.exams.show', 'exam-1'))
            ->assertOk()
            ->assertSee('UJIAN SELESAI')
            ->assertSee('Hasil kerja Anda')
            ->assertSee('SOAL 01');
        $this->assertDatabaseHas('lms_hasil_ujian', [
            'id_hasil_ujian' => $attempt->id_hasil_ujian,
            'status_hasil' => 'terkumpul',
        ], 'simak_main');
        $this->assertTrue(DB::connection('simak_main')->table('log_aktivitas')->where('modul', 'LMS')
            ->pluck('aktifitas')->contains(
                static fn (string $activity): bool => str_contains($activity, 'Mengumpulkan ujian otomatis karena waktu habis')
            ));

        $this->travelTo(Carbon::parse('2026-10-25 11:40:00', 'Asia/Jayapura'));
        $this->from(route('lms.exams.show', 'exam-1'))
            ->post(route('lms.exams.start', 'exam-1'))
            ->assertSessionHasErrors('ujian');
        $this->assertSame(1, DB::connection('simak_main')->table('lms_hasil_ujian')->where('id_ujian', 'exam-1')->count());
    }

    public function test_exam_creation_rejects_question_count_above_available_bank(): void
    {
        $this->prepareExamTables();
        $questionId = (string) Str::uuid();
        DB::connection('simak_main')->table('lms_bank_soal')->insert([
            'id_soal' => $questionId,
            'id_dosen' => 'lecturer-1',
            'id_matkul' => 'course-1',
            'jenis_soal' => 'essay',
            'pertanyaan' => 'Jelaskan konsep dasar pemrograman web.',
            'aktif' => true,
        ]);

        $this->actingAs(new GenericUser(['id' => 'lecturer-user', 'login' => 'NIDN-12345', 'role' => 'dosen']))
            ->from(route('lms.exams.create', 'meeting-2'))
            ->post(route('lms.exams.store', 'meeting-2'), [
                'judul' => 'Ujian essay',
                'jenis_ujian' => 'essay',
                'id_soal' => [$questionId],
                'jumlah_soal_disajikan' => 2,
                'urutan_soal' => 'acak',
                'acak_pilihan_jawaban' => '0',
                'durasi_menit' => 60,
                'batas_mulai' => '2030-01-01T09:00',
                'batas_selesai' => '2030-01-01T10:00',
            ])
            ->assertRedirect(route('lms.exams.create', 'meeting-2'))
            ->assertSessionHasErrors('jumlah_soal_disajikan');
    }

    public function test_faculty_manager_cannot_view_an_exam_outside_their_scope(): void
    {
        $this->prepareExamTables();
        $this->actingAs(new GenericUser(['id' => 'faculty-leader', 'role' => 'pimfak', 'id_fakultas' => 'faculty-2']))
            ->get(route('lms.exams.show', 'exam-1'))
            ->assertNotFound();
    }

    public function test_university_manager_can_view_read_only_exam_results(): void
    {
        $this->prepareExamTables();
        $this->actingAs(new GenericUser(['id' => 'university-leader', 'role' => 'pimuniv']))
            ->get(route('lms.exams.show', 'exam-1'))
            ->assertOk()
            ->assertSee('Ujian LMS')
            ->assertSee(route('lms.meetings.index'))
            ->assertDontSee('Simpan nilai');
    }

    public function test_materials_and_question_bank_are_limited_to_courses_previously_taught_by_lecturer(): void
    {
        config(['database.connections.simak_main' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]]);
        DB::purge('simak_main');
        Schema::connection('simak_main')->create('lms_materi_kuliah', function (Blueprint $table): void {
            $table->string('id_materi')->primary();
            $table->string('id_dosen');
            $table->string('id_matkul')->nullable();
            $table->string('id_prodi')->nullable();
            $table->string('judul');
            $table->text('deskripsi')->nullable();
            $table->string('nama_berkas')->nullable();
            $table->string('path_berkas')->nullable();
            $table->string('tipe_berkas')->nullable();
            $table->unsignedBigInteger('ukuran_berkas')->nullable();
            $table->string('status_publikasi');
            $table->string('dibuat_pada')->nullable();
        });
        Schema::connection('simak_main')->create('lms_label_materi', function (Blueprint $table): void {
            $table->string('id_label_materi')->primary();
            $table->string('id_materi');
            $table->unsignedTinyInteger('pertemuan_ke');
        });
        Schema::connection('simak_main')->create('lms_pertemuan', function (Blueprint $table): void {
            $table->string('id_pertemuan')->primary();
            $table->string('id_materi')->nullable();
        });
        Schema::connection('simak_main')->create('lms_pertemuan_materi', function (Blueprint $table): void {
            $table->string('id_pertemuan_materi')->primary();
            $table->string('id_pertemuan');
            $table->string('id_materi');
        });
        Schema::connection('simak_main')->create('s1m4k_mastermk', function (Blueprint $table): void {
            $table->string('id_matkul')->primary();
            $table->string('id_prodi')->nullable();
            $table->string('kode_mata_kuliah');
            $table->string('nama_mata_kuliah');
        });
        Schema::connection('simak_main')->create('s1m4k_masterdosen', function (Blueprint $table): void {
            $table->string('id_dosen')->primary();
            $table->string('nama_dosen');
            $table->string('gelar_depan')->nullable();
            $table->string('gelar_belakang')->nullable();
            $table->string('nidn')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_kalender', function (Blueprint $table): void {
            $table->string('id_kalender')->primary();
            $table->string('namatahun');
            $table->string('tahun');
            $table->string('status');
            $table->string('status_sp')->default('N');
        });
        Schema::connection('simak_main')->create('s1m4k_jadwal', function (Blueprint $table): void {
            $table->string('id_jadwal')->primary();
            $table->string('id_prodi');
            $table->string('id_kalender');
            $table->string('id_matkul');
            $table->string('id_ruang')->nullable();
            $table->string('nama_kelas');
            for ($slot = 1; $slot <= 14; $slot++) {
                $table->string('dosen'.$slot)->nullable();
            }
        });
        Schema::connection('simak_main')->create('lms_bank_soal', function (Blueprint $table): void {
            $table->string('id_soal')->primary();
            $table->string('id_dosen');
            $table->string('id_matkul');
            $table->string('jenis_soal');
            $table->text('pertanyaan');
            $table->text('opsi_a')->nullable();
            $table->text('opsi_b')->nullable();
            $table->text('opsi_c')->nullable();
            $table->text('opsi_d')->nullable();
            $table->text('opsi_e')->nullable();
            $table->string('kunci_jawaban')->nullable();
            $table->boolean('aktif');
            $table->string('dibuat_pada')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterprodi', function (Blueprint $table): void {
            $table->string('id_prodi')->primary();
            $table->string('nama_program_studi');
            $table->string('id_fakultas')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_mastermhs', function (Blueprint $table): void {
            $table->string('id_mahasiswa')->primary();
            $table->string('nim');
            $table->string('id_prodi')->nullable();
        });
        Schema::connection('simak_main')->create('log_aktivitas', function (Blueprint $table): void {
            $table->string('id_log')->primary();
            $table->string('id_user')->nullable();
            $table->string('level_user');
            $table->string('nama_user')->nullable();
            $table->string('modul');
            $table->text('aktifitas');
            $table->string('created_at');
        });
        DB::connection('simak_main')->table('s1m4k_mastermk')->insert([
            ['id_matkul' => 'course-1', 'id_prodi' => 'program-other', 'kode_mata_kuliah' => 'MK001', 'nama_mata_kuliah' => 'Mata Kuliah Dosen'],
            ['id_matkul' => 'course-2', 'id_prodi' => 'program-other', 'kode_mata_kuliah' => 'MK002', 'nama_mata_kuliah' => 'Mata Kuliah Dosen Lain'],
        ]);
        DB::connection('simak_main')->table('s1m4k_masterdosen')->insert([
            'id_dosen' => 'lecturer-1',
            'nama_dosen' => 'Dosen 1',
            'nidn' => 'NIDN-1',
        ]);
        DB::connection('simak_main')->table('s1m4k_masterdosen')->insert([
            'id_dosen' => 'lecturer-2',
            'nama_dosen' => 'Dosen 2',
            'nidn' => 'NIDN-2',
        ]);
        DB::connection('simak_main')->table('s1m4k_kalender')->insert([
            ['id_kalender' => 'calendar-past', 'namatahun' => '2020 Ganjil', 'tahun' => '20201', 'status' => 'N'],
            ['id_kalender' => 'calendar-current', 'namatahun' => '2026 Ganjil', 'tahun' => '20261', 'status' => 'Y'],
        ]);
        DB::connection('simak_main')->table('s1m4k_jadwal')->insert([
            ['id_jadwal' => 'schedule-past', 'id_prodi' => 'program-other', 'id_kalender' => 'calendar-past', 'id_matkul' => 'course-1', 'nama_kelas' => 'A', 'dosen1' => 'lecturer-1', 'dosen2' => null, 'dosen14' => null],
            ['id_jadwal' => 'schedule-current-course', 'id_prodi' => 'program-other', 'id_kalender' => 'calendar-current', 'id_matkul' => 'course-1', 'nama_kelas' => 'C', 'dosen1' => null, 'dosen2' => null, 'dosen14' => 'lecturer-1'],
            ['id_jadwal' => 'schedule-other', 'id_prodi' => 'program-other', 'id_kalender' => 'calendar-current', 'id_matkul' => 'course-2', 'nama_kelas' => 'B', 'dosen1' => 'lecturer-2', 'dosen2' => null, 'dosen14' => null],
        ]);
        DB::connection('simak_main')->table('s1m4k_masterprodi')->insert([
            'id_prodi' => 'program-other',
            'nama_program_studi' => 'Program Lain',
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            'id_mahasiswa' => 'student-1',
            'nim' => 'NIM-1',
            'id_prodi' => 'program-other',
        ]);
        DB::connection('simak_main')->table('lms_materi_kuliah')->insert([
            [
                'id_materi' => 'material-published',
                'id_dosen' => 'lecturer-1',
                'id_matkul' => null,
                'id_prodi' => null,
                'judul' => 'Materi lintas prodi',
                'nama_berkas' => 'materi-lintas-prodi.pdf',
                'path_berkas' => 'lms/materi/material-published/materi-lintas-prodi.pdf',
                'status_publikasi' => 'terbit',
                'dibuat_pada' => '2026-10-24 09:00:00',
            ],
            [
                'id_materi' => 'material-draft',
                'id_dosen' => 'lecturer-1',
                'id_matkul' => 'course-1',
                'id_prodi' => 'program-other',
                'judul' => 'Materi draf tersembunyi',
                'nama_berkas' => null,
                'path_berkas' => null,
                'status_publikasi' => 'draf',
                'dibuat_pada' => '2026-10-24 10:00:00',
            ],
        ]);
        DB::connection('simak_main')->table('lms_bank_soal')->insert([
            [
                'id_soal' => 'question-course-1',
                'id_dosen' => 'lecturer-1',
                'id_matkul' => 'course-1',
                'jenis_soal' => 'pilihan_ganda',
                'pertanyaan' => 'Pertanyaan pilihan ganda course 1',
                'opsi_a' => 'Opsi A',
                'opsi_b' => 'Opsi B',
                'opsi_c' => 'Opsi C',
                'opsi_d' => 'Opsi D',
                'opsi_e' => 'Opsi E',
                'kunci_jawaban' => 'A',
                'aktif' => true,
                'dibuat_pada' => '2026-10-24 09:00:00',
            ],
            [
                'id_soal' => 'question-essay-course-1',
                'id_dosen' => 'lecturer-1',
                'id_matkul' => 'course-1',
                'jenis_soal' => 'essay',
                'pertanyaan' => 'Pertanyaan essay course 1',
                'opsi_a' => null,
                'opsi_b' => null,
                'opsi_c' => null,
                'opsi_d' => null,
                'opsi_e' => null,
                'kunci_jawaban' => null,
                'aktif' => true,
                'dibuat_pada' => '2026-10-24 10:00:00',
            ],
        ]);

        $this->actingAs(new GenericUser(['id' => 'lecturer-user', 'login' => 'NIDN-1', 'role' => 'dosen']))
            ->get(route('lms.materials.index'))
            ->assertOk()
            ->assertDontSee('Mata kuliah / kelas')
            ->assertSee('Seret file materi ke sini')
            ->assertSee('data-lms-required-upload required', false)
            ->assertSee('<th>Materi & Mata Kuliah</th>', false)
            ->assertSee('<th>Program Studi</th>', false)
            ->assertDontSee('<th>Label Pertemuan</th>', false)
            ->assertDontSee('Materi untuk pertemuan')
            ->assertSee('Pilih mata kuliah yang pernah diampu')
            ->assertSee('MK001 — Mata Kuliah Dosen (2026 Ganjil)')
            ->assertDontSee('MK001 — Mata Kuliah Dosen (2020 Ganjil)')
            ->assertSee('Program Lain')
            ->assertDontSee('MK002 — Mata Kuliah Dosen Lain')
            ->assertSee('Draf (belum ditayangkan)')
            ->assertSee('application/pdf')
            ->assertSee('data-datatable="lms-materi"', false)
            ->assertSee('aria-label="Unduh materi materi-lintas-prodi.pdf"', false)
            ->assertSee('data-page-loader-skip', false)
            ->assertDontSee('aria-label="Lihat materi"', false)
            ->assertSee('aria-label="Ubah materi"', false)
            ->assertSee('aria-label="Hapus materi"', false);

        $this->get(route('lms.questions.index'))
            ->assertOk()
            ->assertSee('Pilih mata kuliah yang pernah diampu')
            ->assertSee('data-lms-activity-filters', false)
            ->assertSee('name="jenis_soal"', false)
            ->assertSee('data-lms-open="lms-question-edit-question-course-1"', false)
            ->assertSee('id="lms-question-edit-question-course-1"', false)
            ->assertDontSee('aria-label="Lihat detail"', false)
            ->assertSee('lms-question-edit-dialog[open]', false)
            ->assertSee('data-question-options', false)
            ->assertSee('options.hidden = select.value !== \'pilihan_ganda\'', false)
            ->assertSee('Upload Soal')
            ->assertSee('id="lms-question-upload-modal"', false)
            ->assertSee(route('lms.questions.template'))
            ->assertSee('MK001 — Mata Kuliah Dosen (2026 Ganjil)')
            ->assertDontSee('MK002 — Mata Kuliah Dosen Lain');
        $this->get(route('lms.questions.index', ['tahun' => '20201']))
            ->assertOk()
            ->assertSee('Tampilan baca-saja')
            ->assertDontSee('data-lms-open="lms-question-edit-question-course-1"', false);
        $this->put(route('lms.questions.update', 'question-course-1'), [
            'tahun' => '20201',
            'jenis_soal' => 'essay',
            'pertanyaan' => 'Pertanyaan lama',
            'aktif' => true,
        ])->assertForbidden();
        $this->get(route('lms.questions.index', ['id_matkul' => 'course-1', 'jenis_soal' => 'pilihan_ganda']))
            ->assertOk()
            ->assertSee('Pertanyaan pilihan ganda course 1')
            ->assertDontSee('Pertanyaan essay course 1')
            ->assertSee('value="course-1" selected', false)
            ->assertSee('value="pilihan_ganda" selected', false);
        $this->get(route('lms.show', ['module' => 'bank-soal', 'record' => 'question-course-1']))
            ->assertOk()
            ->assertSee('Program Lain')
            ->assertSee('Pertanyaan pilihan ganda course 1');
        $this->put(route('lms.questions.update', 'question-course-1'), [
            'tahun' => '20261',
            'jenis_soal' => 'pilihan_ganda',
            'pertanyaan' => 'Pertanyaan pilihan ganda telah diubah.',
            'opsi_a' => 'Jawaban A',
            'opsi_b' => 'Jawaban B',
            'opsi_c' => 'Jawaban C',
            'opsi_d' => 'Jawaban D',
            'opsi_e' => 'Jawaban E',
            'kunci_jawaban' => 'B',
            'aktif' => '1',
        ])->assertRedirect();
        $this->assertDatabaseHas('lms_bank_soal', [
            'id_soal' => 'question-course-1',
            'pertanyaan' => 'Pertanyaan pilihan ganda telah diubah.',
            'kunci_jawaban' => 'B',
        ], 'simak_main');
        $this->from(route('lms.questions.index'))->post(route('lms.questions.store'), [
            'id_matkul' => 'course-2',
            'tahun' => '20261',
            'jenis_soal' => 'essay',
            'pertanyaan' => 'Soal di luar matakuliah yang diampu',
        ])->assertSessionHasErrors('id_matkul');

        $this->from(route('lms.materials.index'))->post(route('lms.materials.store'), [
            'id_matkul' => 'course-1',
            'judul' => 'Materi tanpa berkas',
            'status_publikasi' => 'terbit',
        ])->assertSessionHasErrors('berkas');
        $this->assertDatabaseMissing('lms_materi_kuliah', ['judul' => 'Materi tanpa berkas'], 'simak_main');

        $this->followingRedirects()->from(route('lms.materials.index'))->post(route('lms.materials.store'), [
            'id_matkul' => 'course-1',
            'judul' => 'File tidak diizinkan',
            'status_publikasi' => 'terbit',
            'berkas' => UploadedFile::fake()->createWithContent('malware.php', '<?php echo "unsafe";'),
        ])->assertOk()
            ->assertSee('data-lms-notification', false)
            ->assertSee('Gagal');

        Storage::fake('local');
        $this->from(route('lms.materials.index'))->post(route('lms.materials.store'), [
            'id_matkul' => 'course-2',
            'judul' => 'Materi untuk mata kuliah dosen lain',
            'status_publikasi' => 'terbit',
            'berkas' => UploadedFile::fake()->create('invalid-course.pdf', 20, 'application/pdf'),
        ])->assertSessionHasErrors('id_matkul');
        $this->assertDatabaseMissing('lms_materi_kuliah', [
            'judul' => 'Materi untuk mata kuliah dosen lain',
        ], 'simak_main');

        $this->followingRedirects()->post(route('lms.materials.store'), [
            'id_matkul' => 'course-1',
            'judul' => 'Materi terkait matakuliah',
            'deskripsi' => 'Materi matakuliah yang pernah diampu',
            'status_publikasi' => 'terbit',
            'berkas' => UploadedFile::fake()->create('lecture.pdf', 20, 'application/pdf'),
        ])->assertOk()
            ->assertSee('data-lms-notification', false)
            ->assertSee('Materi kuliah berhasil disimpan.');

        $createdMaterial = DB::connection('simak_main')->table('lms_materi_kuliah')
            ->where('judul', 'Materi terkait matakuliah')->first();
        $this->assertNotNull($createdMaterial);
        $this->assertSame('course-1', $createdMaterial->id_matkul);
        $this->assertSame('program-other', $createdMaterial->id_prodi);
        Storage::disk('local')->assertExists($createdMaterial->path_berkas);
        $this->actingAs(new GenericUser(['id' => 'lecturer-user', 'login' => 'NIDN-1', 'role' => 'dosen']))
            ->from(route('lms.materials.index'))
            ->put(route('lms.materials.update', $createdMaterial->id_materi), [
                'id_matkul' => 'course-2',
                'judul' => 'Perubahan ke mata kuliah dosen lain',
                'status_publikasi' => 'terbit',
            ])
            ->assertSessionHasErrors('id_matkul');
        $this->assertDatabaseHas('lms_materi_kuliah', [
            'id_materi' => $createdMaterial->id_materi,
            'id_matkul' => 'course-1',
        ], 'simak_main');
        $this->put(route('lms.materials.update', $createdMaterial->id_materi), [
            'id_matkul' => 'course-1',
            'judul' => 'Materi matakuliah diperbarui',
            'status_publikasi' => 'terbit',
        ])
            ->assertRedirect(route('lms.materials.index'));
        $createdMaterial = DB::connection('simak_main')->table('lms_materi_kuliah')
            ->where('id_materi', $createdMaterial->id_materi)->first();
        $this->assertSame('Materi matakuliah diperbarui', $createdMaterial->judul);
        Storage::disk('local')->assertExists($createdMaterial->path_berkas);
        DB::connection('simak_main')->table('lms_pertemuan_materi')->insert([
            'id_pertemuan_materi' => (string) Str::uuid(),
            'id_pertemuan' => 'meeting-uses-material',
            'id_materi' => $createdMaterial->id_materi,
        ]);
        $this->get(route('lms.materials.index'))
            ->assertOk()
            ->assertSee('Materi ini sudah digunakan pada pertemuan mata kuliah, hanya bisa dilakukan edit.')
            ->assertDontSee('data-lms-open="lms-material-delete-'.$createdMaterial->id_materi.'"', false)
            ->assertSee('data-lms-open="lms-material-edit-'.$createdMaterial->id_materi.'"', false);
        $this->from(route('lms.materials.index'))
            ->delete(route('lms.materials.destroy', $createdMaterial->id_materi))
            ->assertRedirect(route('lms.materials.index'))
            ->assertSessionHas('error', 'Materi ini sudah digunakan pada pertemuan mata kuliah, hanya bisa dilakukan edit.');
        $this->assertDatabaseHas('lms_materi_kuliah', [
            'id_materi' => $createdMaterial->id_materi,
        ], 'simak_main');
        Storage::disk('local')->assertExists($createdMaterial->path_berkas);
        $this->get(route('lms.materials.download', $createdMaterial->id_materi))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/octet-stream')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('lms.show', ['module' => 'materi', 'record' => $createdMaterial->id_materi]))
            ->assertOk()
            ->assertSee('accept=".doc,.docx', false)
            ->assertSee('Materi ini sudah digunakan pada pertemuan mata kuliah, hanya bisa dilakukan edit.')
            ->assertDontSee('data-lms-open="lms-delete-modal"', false)
            ->assertDontSee('Label pertemuan');

        $this->actingAs(new GenericUser(['id' => 'student-1', 'login' => 'NIM-1', 'role' => 'mahasiswa']))
            ->get(route('lms.materials.index'))
            ->assertOk()
            ->assertSee('Materi dari dosen')
            ->assertSee('Mata Kuliah Dosen')
            ->assertDontSee('Materi lintas prodi')
            ->assertDontSee('Materi matakuliah diperbarui')
            ->assertDontSee('Materi draf tersembunyi');

        $this->actingAs(new GenericUser(['id' => 'student-1', 'login' => 'NIM-1', 'role' => 'mahasiswa']))
            ->get(route('lms.materials.index', ['id_dosen' => 'lecturer-1', 'id_matkul' => 'course-1', 'id_prodi' => 'program-other']))
            ->assertOk()
            ->assertSee('Materi matakuliah diperbarui')
            ->assertDontSee('Materi lintas prodi')
            ->assertDontSee('Materi draf tersembunyi')
            ->assertSee('aria-label="Unduh materi lecture.pdf"', false)
            ->assertDontSee('aria-label="Lihat materi"', false);

        $this->get(route('lms.show', ['module' => 'materi', 'record' => 'material-published']))
            ->assertOk()
            ->assertSee('Materi lintas prodi')
            ->assertSee(route('lms.materials.index'));
    }

    public function test_meeting_list_only_includes_classes_with_approved_student_krs(): void
    {
        config(['database.connections.simak_main' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]]);
        DB::purge('simak_main');
        Schema::connection('simak_main')->create('lms_pertemuan', function (Blueprint $table): void {
            $table->string('id_pertemuan')->primary();
            $table->string('id_jadwal');
            $table->string('id_dosen');
            $table->string('id_materi')->nullable();
            $table->unsignedTinyInteger('pertemuan_ke');
            $table->string('judul');
            $table->string('tanggal_kuliah');
            $table->string('waktu_mulai');
            $table->string('waktu_selesai');
            $table->boolean('absen_dibuka');
            $table->string('tautan_video')->nullable();
            $table->string('status_pertemuan');
        });
        Schema::connection('simak_main')->create('s1m4k_jadwal', function (Blueprint $table): void {
            $table->string('id_jadwal')->primary();
            $table->string('id_prodi');
            $table->string('id_kalender');
            $table->string('id_matkul');
            $table->string('id_ruang')->nullable();
            $table->string('nama_kelas');
            $table->string('dosen1')->nullable();
            $table->string('dosen2')->nullable();
            $table->string('dosen3')->nullable();
            $table->string('dosen4')->nullable();
            $table->string('dosen5')->nullable();
            $table->string('dosen6')->nullable();
            $table->string('dosen7')->nullable();
            $table->string('dosen8')->nullable();
            $table->string('dosen9')->nullable();
            $table->string('dosen10')->nullable();
            $table->string('dosen11')->nullable();
            $table->string('dosen12')->nullable();
            $table->string('dosen13')->nullable();
            $table->string('dosen14')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterprodi', function (Blueprint $table): void {
            $table->string('id_prodi')->primary();
            $table->string('nama_program_studi');
            $table->string('id_fakultas')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_kalender', function (Blueprint $table): void {
            $table->string('id_kalender')->primary();
            $table->string('namatahun');
            $table->string('tahun');
            $table->string('status')->default('Y');
            $table->string('status_sp')->default('N');
            $table->string('id_prodi')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_mastermk', function (Blueprint $table): void {
            $table->string('id_matkul')->primary();
            $table->string('id_prodi')->nullable();
            $table->string('kode_mata_kuliah');
            $table->string('nama_mata_kuliah');
        });
        Schema::connection('simak_main')->create('s1m4k_mastermhs', function (Blueprint $table): void {
            $table->string('id_mahasiswa')->primary();
            $table->string('nim');
            $table->string('id_prodi')->nullable();
            $table->string('nama_mahasiswa')->default('Mahasiswa');
        });
        Schema::connection('simak_main')->create('s1m4k_krs', function (Blueprint $table): void {
            $table->string('id_jadwal');
            $table->string('id_kalender');
            $table->string('id_mahasiswa');
        });
        Schema::connection('simak_main')->create('s1m4k_pengesahankrs', function (Blueprint $table): void {
            $table->string('id_mahasiswa');
            $table->string('id_kalender');
            $table->string('yang_mengesahkan')->nullable();
            $table->string('nama_kpro')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterdosen', function (Blueprint $table): void {
            $table->string('id_dosen')->primary();
            $table->string('nidn');
            $table->string('nama_dosen')->default('Dosen');
            $table->string('gelar_depan')->nullable();
            $table->string('gelar_belakang')->nullable();
            $table->string('nip')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterruang', function (Blueprint $table): void {
            $table->string('id_ruang')->primary();
            $table->string('nama_ruang');
        });
        Schema::connection('simak_main')->create('lms_absensi', function (Blueprint $table): void {
            $table->string('id_absensi')->nullable();
            $table->string('id_pertemuan');
            $table->string('id_mahasiswa');
            $table->string('status_kehadiran')->nullable();
            $table->string('metode_absen')->nullable();
            $table->string('waktu_absen')->nullable();
            $table->string('id_pencatat')->nullable();
            $table->string('catatan')->nullable();
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
        Schema::connection('simak_main')->create('lms_pertemuan_materi', function (Blueprint $table): void {
            $table->string('id_pertemuan');
            $table->string('id_materi');
        });
        Schema::connection('simak_main')->create('lms_materi_kuliah', function (Blueprint $table): void {
            $table->string('id_materi')->primary();
            $table->string('id_dosen');
            $table->string('judul');
        });
        Schema::connection('simak_main')->create('lms_ujian', function (Blueprint $table): void {
            $table->string('id_ujian')->primary();
            $table->string('id_pertemuan');
            $table->string('judul');
            $table->string('status_ujian');
            $table->string('batas_mulai');
            $table->string('batas_selesai');
        });
        Schema::connection('simak_main')->create('lms_hasil_ujian', function (Blueprint $table): void {
            $table->string('id_hasil_ujian')->primary();
            $table->string('id_ujian');
            $table->string('id_mahasiswa');
            $table->string('status_hasil');
        });
        Schema::connection('simak_main')->create('lms_tugas', function (Blueprint $table): void {
            $table->string('id_tugas')->primary();
            $table->string('id_jadwal');
            $table->string('id_dosen');
            $table->string('judul');
            $table->text('deskripsi');
            $table->string('batas_pengumpulan');
            $table->string('status_tugas');
            $table->string('dibuat_pada')->nullable();
        });
        Schema::connection('simak_main')->create('lms_diskusi', function (Blueprint $table): void {
            $table->string('id_diskusi')->primary();
            $table->string('id_jadwal');
            $table->string('id_dosen');
            $table->string('judul');
            $table->text('isi_diskusi');
            $table->string('status_diskusi');
            $table->string('dibuat_pada')->default('2026-10-24 10:00:00');
            $table->string('diperbarui_pada')->default('2026-10-24 10:00:00');
        });
        Schema::connection('simak_main')->create('lms_pesan_diskusi', function (Blueprint $table): void {
            $table->string('id_pesan')->primary();
            $table->string('id_diskusi');
            $table->string('id_pesan_induk')->nullable();
            $table->string('jenis_pengguna');
            $table->string('id_pengguna');
            $table->text('isi_pesan');
            $table->string('dibuat_pada')->default('2026-10-24 10:00:00');
            $table->string('diperbarui_pada')->default('2026-10-24 10:00:00');
        });
        Schema::connection('simak_main')->create('lms_pengumpulan_tugas', function (Blueprint $table): void {
            $table->string('id_pengumpulan')->primary();
            $table->string('id_tugas');
            $table->string('id_mahasiswa');
            $table->text('jawaban_teks')->nullable();
            $table->string('tautan_berkas')->nullable();
            $table->string('dikumpulkan_pada')->nullable();
            $table->string('status_pengumpulan');
            $table->decimal('nilai', 5, 2)->nullable();
            $table->text('komentar_dosen')->nullable();
            $table->string('dinilai_oleh')->nullable();
            $table->string('dinilai_pada')->nullable();
        });
        Schema::connection('simak_main')->create('lms_bank_soal', function (Blueprint $table): void {
            $table->string('id_soal')->primary();
            $table->string('id_dosen');
            $table->string('id_matkul');
            $table->boolean('aktif');
            $table->string('dibuat_pada');
            $table->string('jenis_soal');
            $table->text('pertanyaan');
        });
        Schema::connection('simak_main')->create('log_aktivitas', function (Blueprint $table): void {
            $table->string('id_log')->primary();
            $table->string('id_user')->nullable();
            $table->string('level_user');
            $table->string('nama_user')->nullable();
            $table->string('modul');
            $table->text('aktifitas');
            $table->string('created_at');
        });

        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            ['id_mahasiswa' => 'student-1', 'nim' => 'NIM-1', 'id_prodi' => 'program-1'],
            ['id_mahasiswa' => 'student-2', 'nim' => 'NIM-2', 'id_prodi' => 'program-1'],
        ]);
        DB::connection('simak_main')->table('s1m4k_masterprodi')->insert([
            'id_prodi' => 'program-1',
            'nama_program_studi' => 'Program 1',
        ]);
        DB::connection('simak_main')->table('s1m4k_kalender')->insert([
            ['id_kalender' => 'calendar-1', 'namatahun' => '2026/2027 Ganjil', 'tahun' => '20261', 'status' => 'Y', 'id_prodi' => 'program-1'],
            ['id_kalender' => 'calendar-2', 'namatahun' => '2025/2026 Genap', 'tahun' => '20252', 'status' => 'N', 'id_prodi' => 'program-1'],
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermk')->insert([
            ['id_matkul' => 'course-1', 'id_prodi' => 'program-1', 'kode_mata_kuliah' => 'MK001', 'nama_mata_kuliah' => 'Mata Kuliah 1'],
            ['id_matkul' => 'course-2', 'id_prodi' => 'program-1', 'kode_mata_kuliah' => 'MK002', 'nama_mata_kuliah' => 'Mata Kuliah 2'],
        ]);
        DB::connection('simak_main')->table('s1m4k_masterruang')->insert([
            'id_ruang' => 'room-1',
            'nama_ruang' => 'Ruang Kuliah Utama',
        ]);
        DB::connection('simak_main')->table('s1m4k_jadwal')->insert([
            ['id_jadwal' => 'schedule-approved', 'id_prodi' => 'program-1', 'id_kalender' => 'calendar-1', 'id_matkul' => 'course-1', 'id_ruang' => 'room-1', 'nama_kelas' => 'A', 'dosen1' => 'lecturer-1'],
            ['id_jadwal' => 'schedule-second-course', 'id_prodi' => 'program-1', 'id_kalender' => 'calendar-1', 'id_matkul' => 'course-2', 'id_ruang' => 'room-1', 'nama_kelas' => 'D', 'dosen1' => 'lecturer-1'],
            ['id_jadwal' => 'schedule-pending', 'id_prodi' => 'program-1', 'id_kalender' => 'calendar-1', 'id_matkul' => 'course-1', 'id_ruang' => 'room-1', 'nama_kelas' => 'B', 'dosen1' => 'lecturer-1'],
            ['id_jadwal' => 'schedule-past', 'id_prodi' => 'program-1', 'id_kalender' => 'calendar-2', 'id_matkul' => 'course-1', 'id_ruang' => 'room-1', 'nama_kelas' => 'C', 'dosen1' => 'lecturer-1'],
        ]);
        DB::connection('simak_main')->table('s1m4k_masterdosen')->insert([
            'id_dosen' => 'lecturer-1',
            'nidn' => 'NIDN-1',
            'nama_dosen' => 'Dosen Utama',
            'nip' => 'NIP-12345',
        ]);
        DB::connection('simak_main')->table('lms_pertemuan')->insert([
            ['id_pertemuan' => 'meeting-approved', 'id_jadwal' => 'schedule-approved', 'id_dosen' => 'lecturer-1', 'pertemuan_ke' => 1, 'judul' => 'Pertemuan dapat diakses', 'tanggal_kuliah' => '2026-10-24', 'waktu_mulai' => '09:00', 'waktu_selesai' => '10:00', 'absen_dibuka' => 1, 'status_pertemuan' => 'terbit'],
            ['id_pertemuan' => 'meeting-pending', 'id_jadwal' => 'schedule-pending', 'id_dosen' => 'lecturer-1', 'pertemuan_ke' => 1, 'judul' => 'Pertemuan tanpa pengesahan', 'tanggal_kuliah' => '2026-10-24', 'waktu_mulai' => '09:00', 'waktu_selesai' => '10:00', 'absen_dibuka' => 1, 'status_pertemuan' => 'terbit'],
            ['id_pertemuan' => 'meeting-past', 'id_jadwal' => 'schedule-past', 'id_dosen' => 'lecturer-1', 'pertemuan_ke' => 2, 'judul' => 'Pertemuan kalender lampau', 'tanggal_kuliah' => '2026-10-23', 'waktu_mulai' => '09:00', 'waktu_selesai' => '10:00', 'absen_dibuka' => 1, 'status_pertemuan' => 'terbit'],
        ]);
        DB::connection('simak_main')->table('lms_ujian')->insert([
            'id_ujian' => 'exam-active',
            'id_pertemuan' => 'meeting-approved',
            'judul' => 'Ujian aktif',
            'status_ujian' => 'terbit',
            'batas_mulai' => now('Asia/Jayapura')->subMinute()->toDateTimeString(),
            'batas_selesai' => now('Asia/Jayapura')->addHour()->toDateTimeString(),
        ]);
        DB::connection('simak_main')->table('lms_hasil_ujian')->insert([
            'id_hasil_ujian' => 'result-completed',
            'id_ujian' => 'exam-active',
            'id_mahasiswa' => 'student-2',
            'status_hasil' => 'terkumpul',
        ]);
        DB::connection('simak_main')->table('lms_materi_kuliah')->insert([
            ['id_materi' => '11111111-1111-4111-8111-111111111111', 'id_dosen' => 'lecturer-1', 'judul' => 'Materi unggahan dosen login'],
            ['id_materi' => '22222222-2222-4222-8222-222222222222', 'id_dosen' => 'lecturer-2', 'judul' => 'Materi unggahan dosen lain'],
        ]);
        DB::connection('simak_main')->table('s1m4k_krs')->insert([
            ['id_jadwal' => 'schedule-approved', 'id_kalender' => 'calendar-1', 'id_mahasiswa' => 'student-1'],
            ['id_jadwal' => 'schedule-second-course', 'id_kalender' => 'calendar-1', 'id_mahasiswa' => 'student-1'],
            ['id_jadwal' => 'schedule-approved', 'id_kalender' => 'calendar-1', 'id_mahasiswa' => 'student-2'],
            ['id_jadwal' => 'schedule-pending', 'id_kalender' => 'calendar-2', 'id_mahasiswa' => 'student-1'],
            ['id_jadwal' => 'schedule-past', 'id_kalender' => 'calendar-2', 'id_mahasiswa' => 'student-1'],
        ]);
        DB::connection('simak_main')->table('s1m4k_pengesahankrs')->insert([
            [
                'id_mahasiswa' => 'student-1',
                'id_kalender' => 'calendar-1',
                'yang_mengesahkan' => 'Kaprodi',
                'nama_kpro' => 'Ketua Prodi',
            ],
            [
                'id_mahasiswa' => 'student-2',
                'id_kalender' => 'calendar-1',
                'yang_mengesahkan' => 'Kaprodi',
                'nama_kpro' => 'Ketua Prodi',
            ],
            [
                'id_mahasiswa' => 'student-1',
                'id_kalender' => 'calendar-2',
                'yang_mengesahkan' => 'Kaprodi',
                'nama_kpro' => 'Ketua Prodi',
            ],
        ]);
        DB::connection('simak_main')->table('lms_absensi')->insert([
            'id_absensi' => 'attendance-1',
            'id_pertemuan' => 'meeting-approved',
            'id_mahasiswa' => 'student-1',
            'status_kehadiran' => 'hadir',
            'metode_absen' => 'dosen',
            'waktu_absen' => '2026-10-24 09:00:00',
            'id_pencatat' => 'lecturer-1',
        ]);
        DB::connection('simak_main')->table('lms_tugas')->insert([
            'id_tugas' => 'task-approved',
            'id_jadwal' => 'schedule-approved',
            'id_dosen' => 'lecturer-1',
            'judul' => 'Tugas Pemrograman',
            'deskripsi' => 'Buat program sederhana.',
            'batas_pengumpulan' => '2026-10-25 23:59:00',
            'status_tugas' => 'terbit',
            'dibuat_pada' => '2026-10-20 09:00:00',
        ]);
        DB::connection('simak_main')->table('lms_tugas')->insert([
            'id_tugas' => 'task-past',
            'id_jadwal' => 'schedule-past',
            'id_dosen' => 'lecturer-1',
            'judul' => 'Tugas kalender lampau',
            'deskripsi' => 'Tugas untuk uji tampilan baca-saja.',
            'batas_pengumpulan' => '2026-10-25 23:59:00',
            'status_tugas' => 'terbit',
            'dibuat_pada' => '2026-10-20 09:00:00',
        ]);
        DB::connection('simak_main')->table('lms_diskusi')->insert([
            'id_diskusi' => 'discussion-past',
            'id_jadwal' => 'schedule-past',
            'id_dosen' => 'lecturer-1',
            'judul' => 'Diskusi kalender lampau',
            'isi_diskusi' => 'Topik untuk uji tampilan baca-saja.',
            'status_diskusi' => 'terbuka',
            'dibuat_pada' => '2026-10-20 09:00:00',
            'diperbarui_pada' => '2026-10-20 09:00:00',
        ]);
        DB::connection('simak_main')->table('lms_pengumpulan_tugas')->insert([
            'id_pengumpulan' => 'submission-1',
            'id_tugas' => 'task-approved',
            'id_mahasiswa' => 'student-1',
            'jawaban_teks' => 'Jawaban yang sudah disimpan',
            'tautan_berkas' => 'https://example.test/tugas',
            'dikumpulkan_pada' => '2026-10-24 08:30:00',
            'status_pengumpulan' => 'terkumpul',
            'nilai' => null,
            'komentar_dosen' => null,
        ]);

        $this->actingAs(new GenericUser(['id' => 'student-1', 'login' => 'NIM-1', 'role' => 'mahasiswa']))
            ->get(route('lms.meetings.index'))
            ->assertOk()
            ->assertSee('data-student-activity-filters', false)
            ->assertSee('Tahun akademik')
            ->assertSee('value="calendar-1" selected', false)
            ->assertSee('Pilih mata kuliah')
            ->assertDontSee('data-datatable="lms-pertemuan"', false)
            ->assertDontSee('Pertemuan dapat diakses');

        $this->get(route('lms.meetings.index', ['id_kalender' => 'calendar-1', 'id_matkul' => 'course-1']))
            ->assertOk()
            ->assertSee('data-datatable="lms-pertemuan"', false)
            ->assertSee('Keterangan Kehadiran')
            ->assertSee('Sudah absen')
            ->assertSee('attendance-recorded', false)
            ->assertDontSee('aria-label="Cetak absensi dan berita acara"', false)
            ->assertDontSee('student-meeting-agenda', false)
            ->assertSee('Pertemuan dapat diakses')
            ->assertSee('aria-label="Ikut ujian"', false)
            ->assertSee(route('lms.exams.show', 'exam-active'))
            ->assertDontSee('Pertemuan tanpa pengesahan');

        $this->get(route('lms.meetings.index', ['id_kalender' => 'calendar-1', 'id_matkul' => 'course-2']))
            ->assertOk()
            ->assertSee('value="course-2" selected', false);
        $this->get(route('lms.meetings.index', ['id_kalender' => 'calendar-2', 'id_matkul' => 'course-2']))
            ->assertOk()
            ->assertSee('value="course-1"', false)
            ->assertDontSee('value="course-2"', false)
            ->assertSee('Pilih tahun akademik dan mata kuliah');

        $this->actingAs(new GenericUser(['id' => 'student-2', 'login' => 'NIM-2', 'role' => 'mahasiswa']))
            ->get(route('lms.meetings.index', ['id_kalender' => 'calendar-1', 'id_matkul' => 'course-1']))
            ->assertOk()
            ->assertSee('Belum absen')
            ->assertSee('attendance-missing', false)
            ->assertDontSee('aria-label="Ikut ujian"', false);

        $this->actingAs(new GenericUser(['id' => 'student-1', 'login' => 'NIM-1', 'role' => 'mahasiswa']))
            ->get(route('lms.tasks.index', ['id_kalender' => 'calendar-1', 'id_matkul' => 'course-1']))
            ->assertOk()
            ->assertSee('Kumpul tugas')
            ->assertSee('data-lms-open="lms-task-submit-task-approved"', false)
            ->assertSee('data-lms-task-modal', false)
            ->assertSee('Jawaban Text langsung')
            ->assertSee('Link URL Tugas')
            ->assertSee('Sudah kumpul tugas')
            ->assertSee('Nilai / Komentar Dosen')
            ->assertSee('Nilai tugas belum dinilai oleh dosen')
            ->assertSee('task-submitted', false)
            ->assertSee('Jawaban yang sudah disimpan')
            ->assertSee('https://example.test/tugas')
            ->assertDontSee('Lihat detail');

        DB::connection('simak_main')->table('lms_pengumpulan_tugas')->where('id_pengumpulan', 'submission-1')->update([
            'nilai' => 88.5,
            'komentar_dosen' => 'Pertahankan kualitas jawaban.',
        ]);
        $this->get(route('lms.tasks.index', ['id_kalender' => 'calendar-1', 'id_matkul' => 'course-1']))
            ->assertOk()
            ->assertSee('Nilai: 88,50')
            ->assertSee('Komentar dosen: Pertahankan kualitas jawaban.');

        $this->get(route('lms.meetings.index', ['id_kalender' => 'calendar-2', 'id_matkul' => 'course-1']))
            ->assertOk()
            ->assertSee('Tampilan baca-saja')
            ->assertSee('Tidak aktif · Baca-saja')
            ->assertSee('Pertemuan kalender lampau')
            ->assertDontSee('Ikut ujian')
            ->assertSee(route('lms.show', [
                'module' => 'pertemuan',
                'record' => 'meeting-past',
                'id_kalender' => 'calendar-2',
                'id_matkul' => 'course-1',
            ]));
        $this->get(route('lms.tasks.index', ['id_kalender' => 'calendar-2', 'id_matkul' => 'course-1']))
            ->assertOk()
            ->assertSee('Tampilan baca-saja')
            ->assertSee('Tugas kalender lampau')
            ->assertDontSee('data-lms-open="lms-task-submit-task-past"', false)
            ->assertSee(route('lms.show', [
                'module' => 'tugas',
                'record' => 'task-past',
                'id_kalender' => 'calendar-2',
                'id_matkul' => 'course-1',
            ]));
        $this->get(route('lms.discussions.index', ['id_kalender' => 'calendar-2', 'id_matkul' => 'course-1']))
            ->assertOk()
            ->assertSee('Tampilan baca-saja')
            ->assertSee('Diskusi kalender lampau')
            ->assertSee(route('lms.show', [
                'module' => 'diskusi',
                'record' => 'discussion-past',
                'id_kalender' => 'calendar-2',
                'id_matkul' => 'course-1',
            ]));
        $this->get(route('lms.show', [
            'module' => 'diskusi',
            'record' => 'discussion-past',
            'id_kalender' => 'calendar-2',
            'id_matkul' => 'course-1',
        ]))
            ->assertOk()
            ->assertSee('Tampilan baca-saja')
            ->assertSee(route('lms.discussions.index', ['id_kalender' => 'calendar-2', 'id_matkul' => 'course-1']))
            ->assertDontSee('class="lms-chat-composer"', false);

        $this->post(route('lms.attendance.self', 'meeting-past'))->assertForbidden();
        $this->post(route('lms.tasks.submit', 'task-past'), ['jawaban_teks' => 'Tidak boleh disimpan'])->assertForbidden();
        $this->post(route('lms.discussions.messages.store', 'discussion-past'), ['isi_pesan' => 'Tidak boleh dikirim'])->assertForbidden();

        $this->actingAs(new GenericUser(['id' => 'student-2', 'login' => 'NIM-2', 'role' => 'mahasiswa']))
            ->get(route('lms.tasks.index', ['id_kalender' => 'calendar-1', 'id_matkul' => 'course-1']))
            ->assertOk()
            ->assertSee('Belum kumpul tugas')
            ->assertSee('task-unsubmitted', false);

        $this->actingAs(new GenericUser(['id' => 'student-1', 'login' => 'NIM-1', 'role' => 'mahasiswa']))
            ->get(route('lms.show', ['module' => 'tugas', 'record' => 'task-approved']))
            ->assertOk()
            ->assertSee('lms-task-submission-link-alert', false)
            ->assertSee('https://example.test/tugas')
            ->assertDontSee('aria-label="Ubah tugas"', false)
            ->assertDontSee('aria-label="Hapus tugas"', false);

        $this->actingAs(new GenericUser(['id' => 'student-1', 'login' => 'NIM-1', 'role' => 'mahasiswa']))
            ->get(route('lms.show', ['module' => 'pertemuan', 'record' => 'meeting-approved']))
            ->assertOk()
            ->assertSee('lms-student-meeting-detail', false)
            ->assertSee('Jadwal perkuliahan')
            ->assertSee('Materi pertemuan')
            ->assertSee('Absensi pertemuan')
            ->assertSee('Anda sudah melakukan absen')
            ->assertSee('Status kehadiran tercatat: Hadir');

        $this->travelTo(Carbon::parse('2026-10-24 09:30:00', 'Asia/Jayapura'));
        $this->from(route('lms.show', ['module' => 'pertemuan', 'record' => 'meeting-approved']))
            ->post(route('lms.attendance.self', 'meeting-approved'))
            ->assertRedirect(route('lms.show', ['module' => 'pertemuan', 'record' => 'meeting-approved']))
            ->assertSessionHas('success', 'Absensi berhasil dicatat.');
        $this->get(route('lms.show', ['module' => 'pertemuan', 'record' => 'meeting-approved']))
            ->assertOk()
            ->assertSee('Anda sudah melakukan absen')
            ->assertSee('Status kehadiran tercatat: Hadir');
        $this->assertDatabaseHas('lms_absensi', [
            'id_pertemuan' => 'meeting-approved',
            'id_mahasiswa' => 'student-1',
            'status_kehadiran' => 'hadir',
            'metode_absen' => 'mandiri',
        ], 'simak_main');
        $studentAttendanceActivity = DB::connection('simak_main')->table('log_aktivitas')
            ->where('modul', 'LMS')
            ->where('aktifitas', 'like', '%lms.attendance.self%')
            ->first();
        $this->assertNotNull($studentAttendanceActivity);
        $studentAttendanceDetails = json_decode($studentAttendanceActivity->aktifitas, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('Mengisi absensi mandiri', $studentAttendanceDetails['aksi']);
        $this->assertSame('Mahasiswa', $studentAttendanceDetails['identitas_data']['jenis']);
        $this->assertSame('NIM-1', $studentAttendanceDetails['identitas_data']['nomor_identitas']);
        $this->assertSame('Pertemuan dapat diakses', $studentAttendanceDetails['perubahan']['konteks']['data_terkait']['judul']);
        $this->assertDatabaseHas('eskripsi_notifikasi', [
            'recipient_role' => 'dosen',
            'recipient_id' => 'lecturer-1',
            'category' => 'lms-attendance',
        ], 'simak_main');
        $this->from(route('lms.tasks.index'))->post(route('lms.tasks.submit', 'task-approved'), [
            'jawaban_teks' => 'Jawaban yang sudah disimpan',
            'tautan_berkas' => 'https://example.test/tugas',
        ])->assertRedirect()
            ->assertSessionHas('success', 'Pengumpulan tugas berhasil disimpan.');
        $this->assertDatabaseHas('eskripsi_notifikasi', [
            'recipient_role' => 'dosen',
            'recipient_id' => 'lecturer-1',
            'category' => 'lms-task-submitted',
        ], 'simak_main');

        $this->actingAs(new GenericUser(['id' => 'lecturer-1', 'login' => 'NIDN-1', 'role' => 'dosen']))
            ->get(route('lms.meetings.index'))
            ->assertOk()
            ->assertSee('aria-label="Ubah pertemuan"', false)
            ->assertSee('aria-label="Hapus pertemuan"', false)
            ->assertSee('aria-label="Kelola absensi"', false)
            ->assertSee('aria-label="Lihat ujian"', false)
            ->assertSee('aria-label="Cetak absensi dan berita acara"', false)
            ->assertSee(route('lms.exams.show', 'exam-active'))
            ->assertDontSee('data-lms-open="lms-meeting-exam-meeting-approved"', false)
            ->assertDontSee('aria-label="Lihat pertemuan"', false)
            ->assertSee('id="lms-meeting-edit-meeting-approved"', false)
            ->assertSee('Materi unggahan dosen login')
            ->assertDontSee('Materi unggahan dosen lain')
            ->assertDontSee('NIM-1');

        DB::connection('simak_main')->table('lms_ujian')->where('id_ujian', 'exam-active')->delete();

        $this->get(route('lms.tasks.index'))
            ->assertOk()
            ->assertSee('aria-label="Lihat detail tugas"', false)
            ->assertSee('aria-label="Ubah tugas"', false)
            ->assertSee('aria-label="Hapus tugas"', false)
            ->assertSee('id="lms-task-edit-task-approved"', false)
            ->assertSee('id="lms-task-delete-task-approved"', false);

        $this->get(route('lms.show', ['module' => 'tugas', 'record' => 'task-approved']))
            ->assertOk()
            ->assertSee('lms-teacher-task-detail', false)
            ->assertSee('Ringkasan tugas')
            ->assertSee('Batas pengumpulan')
            ->assertSee('Total pengumpulan')
            ->assertSee('Sudah dinilai')
            ->assertSee('Pengumpulan mahasiswa')
            ->assertSee('Jawaban yang sudah disimpan');
        $this->actingAs(new GenericUser(['id' => 'lecturer-1', 'login' => 'NIDN-1', 'role' => 'dosen']))
            ->get(route('lms.show', ['module' => 'tugas', 'record' => 'task-approved']))
            ->assertOk()
            ->assertSee('lms-task-link-alert', false)
            ->assertSee('Link tugas')
            ->assertSee('target="_blank" rel="noopener noreferrer"', false)
            ->assertDontSee('Tautan jawaban');
        $this->put(route('lms.tasks.grade', [
            'record' => 'task-approved',
            'submission' => 'submission-1',
        ]), [
            'nilai' => 88,
            'komentar_dosen' => 'Jawaban sudah diperiksa.',
        ])->assertRedirect()
            ->assertSessionHas('success', 'Nilai tugas berhasil disimpan.');
        $this->assertDatabaseHas('eskripsi_notifikasi', [
            'recipient_role' => 'mahasiswa',
            'recipient_id' => 'student-1',
            'category' => 'lms-task-graded',
        ], 'simak_main');
        $taskGradeNotification = DB::connection('simak_main')->table('eskripsi_notifikasi')
            ->where('category', 'lms-task-graded')->first();
        $this->assertNotNull($taskGradeNotification);
        $this->assertStringNotContainsString('88', $taskGradeNotification->message);

        $this->from(route('lms.meetings.index'))
            ->delete(route('lms.meetings.destroy', 'meeting-approved'))
            ->assertRedirect(route('lms.meetings.index'))
            ->assertSessionHas('error', 'Pertemuan tidak dapat dihapus karena sudah memiliki absensi mahasiswa atau ujian.');
        $this->assertDatabaseHas('lms_pertemuan', ['id_pertemuan' => 'meeting-approved'], 'simak_main');

        DB::connection('simak_main')->table('lms_ujian')->insert([
            'id_ujian' => 'exam-pending-meeting',
            'id_pertemuan' => 'meeting-pending',
            'judul' => 'Ujian draf',
            'status_ujian' => 'draf',
            'batas_mulai' => now('Asia/Jayapura')->subMinute()->toDateTimeString(),
            'batas_selesai' => now('Asia/Jayapura')->addHour()->toDateTimeString(),
        ]);
        $this->from(route('lms.meetings.index'))
            ->delete(route('lms.meetings.destroy', 'meeting-pending'))
            ->assertRedirect(route('lms.meetings.index'))
            ->assertSessionHas('error', 'Pertemuan tidak dapat dihapus karena sudah memiliki absensi mahasiswa atau ujian.');
        $this->assertDatabaseHas('lms_pertemuan', ['id_pertemuan' => 'meeting-pending'], 'simak_main');

        $this->from(route('lms.tasks.index'))
            ->delete(route('lms.tasks.destroy', 'task-approved'))
            ->assertRedirect(route('lms.tasks.index'))
            ->assertSessionHas('error', 'Tugas tidak dapat dihapus karena sudah ada mahasiswa yang mengumpulkan.');
        $this->assertDatabaseHas('lms_tugas', ['id_tugas' => 'task-approved'], 'simak_main');

        $this->from(route('lms.meetings.index'))->post(route('lms.meetings.store'), [
            'id_jadwal' => 'schedule-approved',
            'pertemuan_ke' => 2,
            'id_materi' => ['22222222-2222-4222-8222-222222222222'],
            'judul' => 'Pertemuan dengan materi dosen lain',
            'tanggal_kuliah' => '2026-10-25',
            'waktu_mulai' => '09:00',
            'waktu_selesai' => '10:00',
            'absen_dibuka' => 0,
        ])->assertSessionHasErrors('id_materi');
        $this->assertDatabaseMissing('lms_pertemuan', [
            'judul' => 'Pertemuan dengan materi dosen lain',
        ], 'simak_main');

        $this->from(route('lms.meetings.index'))->put(route('lms.meetings.update', 'meeting-approved'), [
            'pertemuan_ke' => 1,
            'id_materi' => ['22222222-2222-4222-8222-222222222222'],
            'judul' => 'Perubahan dengan materi dosen lain',
            'tanggal_kuliah' => '2026-10-24',
            'waktu_mulai' => '09:00',
            'waktu_selesai' => '10:00',
            'absen_dibuka' => 1,
            'status_pertemuan' => 'terbit',
        ])->assertSessionHasErrors('id_materi');
        $this->assertDatabaseMissing('lms_pertemuan_materi', [
            'id_pertemuan' => 'meeting-approved',
            'id_materi' => '22222222-2222-4222-8222-222222222222',
        ], 'simak_main');

        $this->get(route('lms.exams.create', 'meeting-approved'))
            ->assertOk()
            ->assertSee('Buat ujian')
            ->assertSee('data-searchable-select', false)
            ->assertSee(route('lms.exams.store', 'meeting-approved'));

        $this->get(route('lms.attendance.roster', 'meeting-approved'))
            ->assertOk()
            ->assertSee('Absensi Pertemuan 1')
            ->assertSee('NIM-1')
            ->assertSee('Mahasiswa')
            ->assertSee('data-lms-attendance-toggle', false)
            ->assertDontSee('Simpan absensi', false);
        $pdfResponse = $this->get(route('lms.meetings.attendance.print', 'meeting-approved'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename="absen-mata-kuliah-1-a-pertemuan-1.pdf"');
        $pdfContent = $pdfResponse->getContent();
        $this->assertStringStartsWith('%PDF-', $pdfContent);
        $this->assertSame(3, substr_count($pdfContent, '/Type /Page'));
        $this->assertGreaterThan(0, substr_count($pdfContent, '/Subtype /Image'));
        $verificationUrl = URL::signedRoute('lms.meetings.minutes.verify', ['record' => 'meeting-approved']);
        $this->get($verificationUrl)
            ->assertOk()
            ->assertSee('Dokumen terdaftar dan valid')
            ->assertSee('images/landing/default-campus-logo.png')
            ->assertSee('Ruang Kuliah Utama')
            ->assertSee('Dosen Utama')
            ->assertSee('NIP-12345');
        $this->get($verificationUrl.'&record=meeting-pending')->assertForbidden();
        $this->putJson(route('lms.attendance.mark', ['record' => 'meeting-approved', 'student' => 'student-1']), [
            'status_kehadiran' => 'hadir',
        ])->assertOk()
            ->assertJsonPath('message', 'Absensi mahasiswa berhasil disimpan.');
        $this->assertDatabaseHas('eskripsi_notifikasi', [
            'recipient_role' => 'mahasiswa',
            'recipient_id' => 'student-1',
            'category' => 'lms-attendance',
        ], 'simak_main');
        $this->assertDatabaseHas('lms_absensi', [
            'id_pertemuan' => 'meeting-approved',
            'id_mahasiswa' => 'student-1',
            'status_kehadiran' => 'hadir',
        ], 'simak_main');
        $attendanceActivity = DB::connection('simak_main')->table('log_aktivitas')
            ->where('modul', 'LMS')
            ->where('aktifitas', 'like', '%lms.attendance.mark%')
            ->first();
        $this->assertNotNull($attendanceActivity);
        $attendanceDetails = json_decode($attendanceActivity->aktifitas, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('Mencatat absensi mahasiswa', $attendanceDetails['aksi']);
        $this->assertSame('meeting-approved', $attendanceDetails['id_data']);
        $this->assertSame('Mahasiswa', $attendanceDetails['identitas_data']['jenis']);
        $this->assertSame('NIM-1', $attendanceDetails['identitas_data']['nomor_identitas']);
        $this->assertSame('Absensi pertemuan', $attendanceDetails['perubahan']['entitas']);
        $this->assertSame('hadir', $attendanceDetails['perubahan']['input']['status_kehadiran']);
        $this->assertSame('Pertemuan dapat diakses', $attendanceDetails['perubahan']['konteks']['data_terkait']['judul']);
        $this->assertSame('student-1', $attendanceDetails['perubahan']['konteks']['mahasiswa']['id']);
        $this->deleteJson(route('lms.attendance.cancel', ['record' => 'meeting-approved', 'student' => 'student-1']))
            ->assertOk()
            ->assertJsonPath('message', 'Absensi mahasiswa berhasil dibatalkan.');
        $this->assertDatabaseMissing('lms_absensi', [
            'id_pertemuan' => 'meeting-approved',
            'id_mahasiswa' => 'student-1',
        ], 'simak_main');
        $this->get(route('lms.meetings.questions', 'meeting-approved'))
            ->assertOk()
            ->assertExactJson([]);
        $this->actingAs(new GenericUser(['id' => 'student-1', 'login' => 'NIM-1', 'role' => 'mahasiswa']))
            ->get(route('lms.meetings.attendance.print', 'meeting-approved'))
            ->assertForbidden();

        DB::connection('simak_main')->table('s1m4k_masterprodi')
            ->where('id_prodi', 'program-1')
            ->update(['id_fakultas' => 'faculty-1']);
        DB::connection('simak_main')->table('s1m4k_jadwal')->insert([
            'id_jadwal' => 'schedule-parallel',
            'id_prodi' => 'program-1',
            'id_kalender' => 'calendar-1',
            'id_matkul' => 'course-1',
            'id_ruang' => 'room-1',
            'nama_kelas' => 'B',
            'dosen1' => 'lecturer-1',
        ]);
        DB::connection('simak_main')->table('s1m4k_krs')->insert([
            'id_jadwal' => 'schedule-parallel',
            'id_kalender' => 'calendar-1',
            'id_mahasiswa' => 'student-1',
        ]);
        DB::connection('simak_main')->table('lms_pertemuan')->insert([
            'id_pertemuan' => 'meeting-parallel',
            'id_jadwal' => 'schedule-parallel',
            'id_dosen' => 'lecturer-1',
            'pertemuan_ke' => 3,
            'judul' => 'Pertemuan kelas B',
            'tanggal_kuliah' => '2026-10-25',
            'waktu_mulai' => '10:00',
            'waktu_selesai' => '11:00',
            'absen_dibuka' => 1,
            'status_pertemuan' => 'terbit',
        ]);
        DB::connection('simak_main')->table('lms_tugas')->insert([
            'id_tugas' => 'task-parallel',
            'id_jadwal' => 'schedule-parallel',
            'id_dosen' => 'lecturer-1',
            'judul' => 'Tugas kelas B',
            'deskripsi' => 'Tugas untuk kelas B.',
            'batas_pengumpulan' => '2026-10-26 23:59:00',
            'status_tugas' => 'terbit',
            'dibuat_pada' => '2026-10-25 09:00:00',
        ]);
        DB::connection('simak_main')->table('lms_diskusi')->insert([
            'id_diskusi' => 'discussion-parallel',
            'id_jadwal' => 'schedule-parallel',
            'id_dosen' => 'lecturer-1',
            'judul' => 'Diskusi kelas B',
            'isi_diskusi' => 'Topik khusus kelas B.',
            'status_diskusi' => 'terbuka',
            'dibuat_pada' => '2026-10-25 09:00:00',
            'diperbarui_pada' => '2026-10-25 09:00:00',
        ]);

        $accounts = [
            ['role' => 'admin'],
            ['role' => 'pimuniv'],
            ['role' => 'pimfak', 'id_fakultas' => 'faculty-1'],
            ['role' => 'userprodi', 'id_prodi' => 'program-1'],
            ['role' => 'usertu', 'id_prodi' => 'program-1'],
            ['role' => 'dosen', 'login' => 'NIDN-1'],
            ['role' => 'mahasiswa', 'id' => 'student-1', 'login' => 'NIM-1'],
        ];
        $activityRoutes = [
            'pertemuan' => ['route' => 'lms.meetings.index', 'matching' => 'Pertemuan kelas B', 'other' => 'Pertemuan dapat diakses'],
            'tugas' => ['route' => 'lms.tasks.index', 'matching' => 'Tugas kelas B', 'other' => 'Tugas Pemrograman'],
            'diskusi' => ['route' => 'lms.discussions.index', 'matching' => 'Diskusi kelas B', 'other' => 'Diskusi kalender lampau'],
        ];
        foreach ($accounts as $account) {
            $this->actingAs(new GenericUser($account + ['id' => $account['id'] ?? 'account-'.$account['role']]));
            foreach ($activityRoutes as $activity) {
                $filters = $account['role'] === 'mahasiswa'
                    ? ['id_kalender' => 'calendar-1', 'id_matkul' => 'course-1']
                    : ['tahun' => '20261', 'id_matkul' => 'course-1'];
                $this->get(route($activity['route'], $filters + ['id_jadwal' => 'schedule-parallel']))
                    ->assertOk()
                    ->assertSee('value="schedule-parallel" selected', false)
                    ->assertSee($activity['matching'])
                    ->assertDontSee($activity['other']);
            }
        }

        DB::connection('simak_main')->table('s1m4k_kalender')->where('id_kalender', 'calendar-1')->update(['status' => 'N']);
        DB::connection('simak_main')->table('s1m4k_kalender')->where('id_kalender', 'calendar-2')->update([
            'tahun' => '20253',
            'status' => 'N',
            'status_sp' => 'Y',
        ]);
        DB::connection('simak_main')->table('lms_pertemuan')->where('id_pertemuan', 'meeting-past')
            ->update(['tanggal_kuliah' => '2026-10-24']);

        $this->actingAs(new GenericUser(['id' => 'student-1', 'login' => 'NIM-1', 'role' => 'mahasiswa']))
            ->get(route('lms.meetings.index'))
            ->assertOk()
            ->assertSee('value="calendar-2" selected', false)
            ->assertSee('2025/2026 Genap · Aktif')
            ->assertSee('Seluruh proses perubahan semester pendek secara realtime terintegrasi dengan feeder sehingga tidak perlu dilakukan export feeder maupun penginputan secara manual di feeder.')
            ->assertDontSee('Tampilan baca-saja');

        $this->actingAs(new GenericUser(['id' => 'lecturer-1', 'login' => 'NIDN-1', 'role' => 'dosen']))
            ->from(route('lms.meetings.index'))
            ->post(route('lms.meetings.store'), [
                'id_jadwal' => 'schedule-past',
                'pertemuan_ke' => 4,
                'judul' => 'Pertemuan SP baru',
                'tanggal_kuliah' => '2026-10-24',
                'waktu_mulai' => '11:00',
                'waktu_selesai' => '12:00',
                'absen_dibuka' => 1,
                'status_pertemuan' => 'terbit',
            ])->assertRedirect(route('lms.meetings.index'));
        $spMeetingId = DB::connection('simak_main')->table('lms_pertemuan')
            ->where('judul', 'Pertemuan SP baru')->value('id_pertemuan');
        $this->put(route('lms.meetings.update', $spMeetingId), [
            'pertemuan_ke' => 4,
            'judul' => 'Pertemuan SP diperbarui',
            'tanggal_kuliah' => '2026-10-24',
            'waktu_mulai' => '11:00',
            'waktu_selesai' => '12:00',
            'absen_dibuka' => 1,
            'status_pertemuan' => 'terbit',
        ])->assertRedirect();
        $this->delete(route('lms.meetings.destroy', $spMeetingId))->assertRedirect(route('lms.meetings.index'));

        $this->post(route('lms.tasks.store'), [
            'id_jadwal' => 'schedule-past',
            'judul' => 'Tugas SP baru',
            'deskripsi' => 'Tugas untuk kelas semester pendek.',
            'batas_pengumpulan' => Carbon::now('Asia/Jayapura')->addDay()->toDateTimeString(),
            'status_tugas' => 'terbit',
        ])->assertRedirect();
        $spTaskId = DB::connection('simak_main')->table('lms_tugas')
            ->where('judul', 'Tugas SP baru')->value('id_tugas');
        $this->put(route('lms.tasks.update', $spTaskId), [
            'judul' => 'Tugas SP diperbarui',
            'deskripsi' => 'Instruksi tugas semester pendek.',
            'batas_pengumpulan' => Carbon::now('Asia/Jayapura')->addDay()->toDateTimeString(),
            'status_tugas' => 'terbit',
        ])->assertRedirect();
        $this->delete(route('lms.tasks.destroy', $spTaskId))->assertRedirect(route('lms.tasks.index'));

        $this->post(route('lms.discussions.store'), [
            'id_jadwal' => 'schedule-past',
            'judul' => 'Diskusi SP baru',
            'isi_diskusi' => 'Diskusi untuk kelas semester pendek.',
        ])->assertRedirect(route('lms.discussions.index'));
        $spDiscussionId = DB::connection('simak_main')->table('lms_diskusi')
            ->where('judul', 'Diskusi SP baru')->value('id_diskusi');
        $this->put(route('lms.discussions.update', $spDiscussionId), [
            'judul' => 'Diskusi SP diperbarui',
            'isi_diskusi' => 'Topik diskusi semester pendek.',
            'status_diskusi' => 'terbuka',
        ])->assertRedirect();
        $this->delete(route('lms.discussions.destroy', $spDiscussionId))
            ->assertRedirect(route('lms.discussions.index'));

        $this->actingAs(new GenericUser(['id' => 'student-1', 'login' => 'NIM-1', 'role' => 'mahasiswa']))
            ->post(route('lms.attendance.self', 'meeting-past'))
            ->assertRedirect();
        $this->post(route('lms.tasks.submit', 'task-past'), ['jawaban_teks' => 'Tugas semester pendek'])
            ->assertRedirect();
        $this->post(route('lms.discussions.messages.store', 'discussion-past'), ['isi_pesan' => 'Diskusi semester pendek'])
            ->assertRedirect();
        $spMessageId = DB::connection('simak_main')->table('lms_pesan_diskusi')
            ->where('id_diskusi', 'discussion-past')->where('id_pengguna', 'student-1')->value('id_pesan');

        DB::connection('simak_main')->table('s1m4k_kalender')->where('id_kalender', 'calendar-2')
            ->update(['status_sp' => 'N']);
        $this->delete(route('lms.discussions.messages.destroy', ['record' => 'discussion-past', 'message' => $spMessageId]))
            ->assertForbidden();
        $this->assertDatabaseHas('lms_pesan_diskusi', ['id_pesan' => $spMessageId], 'simak_main');
    }

    public function test_discussion_creation_listing_and_reply_thread_work_for_lecturer(): void
    {
        config(['database.connections.simak_main' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]]);
        DB::purge('simak_main');
        Schema::connection('simak_main')->create('s1m4k_jadwal', function (Blueprint $table): void {
            $table->string('id_jadwal')->primary();
            $table->string('id_prodi');
            $table->string('id_kalender');
            $table->string('id_matkul');
            $table->string('id_ruang')->nullable();
            $table->string('nama_kelas');
            for ($slot = 1; $slot <= 14; $slot++) {
                $table->string('dosen'.$slot)->nullable();
            }
        });
        Schema::connection('simak_main')->create('s1m4k_kalender', function (Blueprint $table): void {
            $table->string('id_kalender')->primary();
            $table->string('namatahun');
            $table->string('tahun');
            $table->string('status');
            $table->string('status_sp')->default('N');
        });
        Schema::connection('simak_main')->create('s1m4k_masterprodi', function (Blueprint $table): void {
            $table->string('id_prodi')->primary();
            $table->string('id_fakultas')->nullable();
            $table->string('nama_program_studi');
        });
        Schema::connection('simak_main')->create('s1m4k_mastermk', function (Blueprint $table): void {
            $table->string('id_matkul')->primary();
            $table->string('kode_mata_kuliah');
            $table->string('nama_mata_kuliah');
        });
        Schema::connection('simak_main')->create('s1m4k_masterruang', function (Blueprint $table): void {
            $table->string('id_ruang')->primary();
            $table->string('nama_ruang');
        });
        Schema::connection('simak_main')->create('s1m4k_masterdosen', function (Blueprint $table): void {
            $table->string('id_dosen')->primary();
            $table->string('nidn');
            $table->string('nama_dosen');
            $table->string('gelar_depan')->nullable();
            $table->string('gelar_belakang')->nullable();
            $table->string('nip')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_mastermhs', function (Blueprint $table): void {
            $table->string('id_mahasiswa')->primary();
            $table->string('nim');
            $table->string('nama_mahasiswa');
        });
        Schema::connection('simak_main')->create('lms_diskusi', function (Blueprint $table): void {
            $table->string('id_diskusi')->primary();
            $table->string('id_jadwal');
            $table->string('id_dosen');
            $table->string('judul');
            $table->text('isi_diskusi');
            $table->string('status_diskusi');
            $table->string('dibuat_pada')->default('2026-10-24 10:00:00');
            $table->string('diperbarui_pada')->default('2026-10-24 10:00:00');
        });
        Schema::connection('simak_main')->create('lms_pesan_diskusi', function (Blueprint $table): void {
            $table->string('id_pesan')->primary();
            $table->string('id_diskusi');
            $table->string('id_pesan_induk')->nullable();
            $table->string('jenis_pengguna');
            $table->string('id_pengguna');
            $table->text('isi_pesan');
            $table->string('dibuat_pada')->default('2026-10-24 10:00:00');
            $table->string('diperbarui_pada')->default('2026-10-24 10:00:00');
        });
        Schema::connection('simak_main')->create('s1m4k_krs', function (Blueprint $table): void {
            $table->string('id_jadwal');
            $table->string('id_kalender');
            $table->string('id_mahasiswa');
        });
        Schema::connection('simak_main')->create('s1m4k_pengesahankrs', function (Blueprint $table): void {
            $table->string('id_mahasiswa');
            $table->string('id_kalender');
            $table->string('yang_mengesahkan')->nullable();
            $table->string('nama_kpro')->nullable();
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
        Schema::connection('simak_main')->create('log_aktivitas', function (Blueprint $table): void {
            $table->string('id_log')->primary();
            $table->string('id_user')->nullable();
            $table->string('level_user');
            $table->string('nama_user')->nullable();
            $table->string('modul');
            $table->text('aktifitas');
            $table->string('created_at');
        });
        DB::connection('simak_main')->table('s1m4k_kalender')->insert([
            'id_kalender' => 'calendar-1',
            'namatahun' => '2026 Ganjil',
            'tahun' => '20261',
            'status' => 'Y',
        ]);
        DB::connection('simak_main')->table('s1m4k_masterprodi')->insert([
            'id_prodi' => 'program-1',
            'id_fakultas' => 'faculty-1',
            'nama_program_studi' => 'Program 1',
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermk')->insert([
            'id_matkul' => 'course-1',
            'kode_mata_kuliah' => 'MK001',
            'nama_mata_kuliah' => 'Mata Kuliah Diskusi',
        ]);
        DB::connection('simak_main')->table('s1m4k_masterdosen')->insert([
            'id_dosen' => 'lecturer-1',
            'nidn' => 'NIDN-1',
            'nama_dosen' => 'Dosen Diskusi',
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            'id_mahasiswa' => 'student-1',
            'nim' => 'NIM-1',
            'nama_mahasiswa' => 'Mahasiswa Satu',
        ]);
        DB::connection('simak_main')->table('s1m4k_jadwal')->insert([
            'id_jadwal' => 'schedule-1',
            'id_prodi' => 'program-1',
            'id_kalender' => 'calendar-1',
            'id_matkul' => 'course-1',
            'id_ruang' => null,
            'nama_kelas' => 'A',
            'dosen1' => null,
            'dosen14' => 'lecturer-1',
        ]);
        DB::connection('simak_main')->table('s1m4k_krs')->insert([
            'id_jadwal' => 'schedule-1',
            'id_kalender' => 'calendar-1',
            'id_mahasiswa' => 'student-1',
        ]);
        DB::connection('simak_main')->table('s1m4k_pengesahankrs')->insert([
            'id_mahasiswa' => 'student-1',
            'id_kalender' => 'calendar-1',
            'yang_mengesahkan' => 'Dosen PA',
            'nama_kpro' => 'Ketua Prodi',
        ]);
        $teacher = new GenericUser(['id' => 'lecturer-user', 'login' => 'NIDN-1', 'role' => 'dosen']);
        $this->actingAs($teacher)
            ->post(route('lms.discussions.store'), [
                'id_jadwal' => 'schedule-1',
                'judul' => 'Diskusi materi awal',
                'isi_diskusi' => 'Bagaimana pendapat kelas?',
            ])
            ->assertRedirect(route('lms.discussions.index'))
            ->assertSessionHas('success', 'Diskusi berhasil dibuka.');

        $discussion = DB::connection('simak_main')->table('lms_diskusi')->where('judul', 'Diskusi materi awal')->first();
        $this->assertNotNull($discussion);
        $this->assertSame(1, DB::connection('simak_main')->table('lms_diskusi')->count());
        $this->assertDatabaseHas('eskripsi_notifikasi', [
            'recipient_role' => 'mahasiswa',
            'recipient_id' => 'student-1',
            'category' => 'lms-discussion-opened',
        ], 'simak_main');
        $this->assertDatabaseHas('log_aktivitas', [
            'modul' => 'LMS',
        ], 'simak_main');
        $this->assertTrue(DB::connection('simak_main')->table('log_aktivitas')
            ->where('modul', 'LMS')
            ->where('aktifitas', 'like', '%lms.discussions.store%')
            ->exists());
        $parentMessageId = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
        $brokenEmoji = "baik Pak\u{00F0}\u{0178}\u{2122}\u{008F}\u{00F0}\u{0178}\u{008F}\u{00BB}";
        $repairedEmoji = "baik Pak\u{1F64F}\u{1F3FB}";
        DB::connection('simak_main')->table('lms_pesan_diskusi')->insert([
            'id_pesan' => $parentMessageId,
            'id_diskusi' => $discussion->id_diskusi,
            'id_pesan_induk' => null,
            'jenis_pengguna' => 'mahasiswa',
            'id_pengguna' => 'student-1',
            'isi_pesan' => $brokenEmoji,
        ]);

        $this->get(route('lms.show', ['module' => 'diskusi', 'record' => $discussion->id_diskusi]))
            ->assertOk()
            ->assertSee('lms-chat', false)
            ->assertSee('height:min(60vh,620px)', false)
            ->assertSee('overflow-y:auto', false)
            ->assertSee('data-lms-reply', false)
            ->assertSee($repairedEmoji)
            ->assertDontSee('aria-label="Ubah diskusi"', false)
            ->assertDontSee('aria-label="Hapus diskusi"', false);
        $this->post(route('lms.discussions.messages.store', $discussion->id_diskusi), [
            'id_pesan_induk' => $parentMessageId,
            'isi_pesan' => $brokenEmoji,
        ])->assertRedirect();

        $reply = DB::connection('simak_main')->table('lms_pesan_diskusi')
            ->where('isi_pesan', 'baik Pak&#128591;&#127995;')->first();
        $this->assertNotNull($reply);
        $this->assertSame($parentMessageId, $reply->id_pesan_induk);
        $this->assertDatabaseHas('eskripsi_notifikasi', [
            'recipient_role' => 'mahasiswa',
            'recipient_id' => 'student-1',
            'category' => 'lms-discussion-message',
        ], 'simak_main');
        $studentNotification = DB::connection('simak_main')->table('eskripsi_notifikasi')
            ->where('recipient_role', 'mahasiswa')->first();
        $this->assertStringNotContainsString($repairedEmoji, $studentNotification->message);
        $teacherEmoji = 'Balasan dosen 👍😊';
        $this->post(route('lms.discussions.messages.store', $discussion->id_diskusi), [
            'id_pesan_induk' => $reply->id_pesan,
            'isi_pesan' => $teacherEmoji,
        ])->assertRedirect();
        $this->assertDatabaseHas('lms_pesan_diskusi', [
            'isi_pesan' => 'Balasan dosen &#128077;&#128522;',
            'jenis_pengguna' => 'dosen',
        ], 'simak_main');
        $this->actingAs(new GenericUser(['id' => 'student-1', 'login' => 'NIM-1', 'role' => 'mahasiswa']))
            ->post(route('lms.discussions.messages.store', $discussion->id_diskusi), [
                'id_pesan_induk' => $reply->id_pesan,
                'isi_pesan' => 'Terima <b>kasih</b><br>👍🏽😊<script>alert(1)</script>',
            ])->assertRedirect();
        $studentMessage = DB::connection('simak_main')->table('lms_pesan_diskusi')
            ->where('isi_pesan', 'Terima <b>kasih</b><br>&#128077;&#127997;&#128522;<script>alert(1)</script>')
            ->first();
        $this->assertNotNull($studentMessage);
        $this->assertDatabaseHas('eskripsi_notifikasi', [
            'recipient_role' => 'dosen',
            'recipient_id' => 'lecturer-1',
            'category' => 'lms-discussion-message',
        ], 'simak_main');
        $this->actingAs($teacher);
        DB::connection('simak_main')->table('lms_pesan_diskusi')
            ->where('id_pesan', $reply->id_pesan)
            ->update(['dibuat_pada' => '2026-10-24 14:00:00']);

        $this->get(route('lms.discussions.index'))
            ->assertOk()
            ->assertSee('<th>Tanggal Terakhir Diskusi</th>', false)
            ->assertSee('24/10/2026 14:00')
            ->assertSee('aria-label="Ubah diskusi"', false)
            ->assertSee('aria-label="Hapus diskusi"', false);
        $this->get(route('lms.show', ['module' => 'diskusi', 'record' => $discussion->id_diskusi]))
            ->assertOk()
            ->assertSee($repairedEmoji)
            ->assertSee($teacherEmoji)
            ->assertSee('<strong>kasih</strong><br>👍🏽😊&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<b>kasih</b>', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('lms-chat-quote', false)
            ->assertSee($repairedEmoji);

        $this->from(route('lms.discussions.index'))
            ->delete(route('lms.discussions.destroy', $discussion->id_diskusi))
            ->assertRedirect(route('lms.discussions.index'))
            ->assertSessionHas('error', 'Diskusi tidak dapat dihapus karena sudah diikuti mahasiswa.');
        $this->assertDatabaseHas('lms_diskusi', ['id_diskusi' => $discussion->id_diskusi], 'simak_main');
    }

    private function prepareExamTables(): void
    {
        config(['database.connections.simak_main' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]]);
        DB::purge('simak_main');

        Schema::connection('simak_main')->create('log_aktivitas', function (Blueprint $table): void {
            $table->string('id_log', 36)->primary();
            $table->string('id_user', 100)->nullable();
            $table->string('level_user', 50)->nullable();
            $table->string('nama_user', 255);
            $table->string('modul', 100);
            $table->longText('aktifitas');
            $table->timestamp('created_at')->useCurrent();
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
        Schema::connection('simak_main')->create('lms_ujian', function (Blueprint $table): void {
            $table->string('id_ujian')->primary();
            $table->string('id_pertemuan');
            $table->string('id_dosen');
            $table->string('id_matkul');
            $table->string('judul');
            $table->text('petunjuk')->nullable();
            $table->string('jenis_ujian');
            $table->integer('jumlah_soal_disajikan');
            $table->integer('durasi_menit');
            $table->string('batas_mulai');
            $table->string('batas_selesai');
            $table->string('sumber_soal')->nullable();
            $table->string('urutan_soal')->nullable();
            $table->boolean('acak_pilihan_jawaban')->nullable();
            $table->decimal('nilai_per_soal', 8, 2)->nullable();
            $table->string('status_ujian')->nullable();
        });
        Schema::connection('simak_main')->create('lms_ujian_soal', function (Blueprint $table): void {
            $table->string('id_ujian_soal')->primary();
            $table->string('id_ujian');
            $table->string('id_soal');
            $table->integer('urutan_tetap')->nullable();
        });
        Schema::connection('simak_main')->create('lms_pertemuan', function (Blueprint $table): void {
            $table->string('id_pertemuan')->primary();
            $table->string('id_jadwal');
            $table->string('id_dosen')->nullable();
            $table->unsignedTinyInteger('pertemuan_ke')->nullable();
            $table->string('judul')->nullable();
            $table->date('tanggal_kuliah')->nullable();
            $table->string('waktu_mulai')->nullable();
            $table->string('waktu_selesai')->nullable();
            $table->boolean('absen_dibuka')->nullable();
            $table->string('status_pertemuan')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_jadwal', function (Blueprint $table): void {
            $table->string('id_jadwal')->primary();
            $table->string('id_prodi');
            $table->string('id_kalender');
            $table->string('id_matkul');
            $table->string('id_ruang')->nullable();
            $table->string('nama_kelas');
            for ($slot = 1; $slot <= 14; $slot++) {
                $table->string('dosen'.$slot)->nullable();
            }
        });
        Schema::connection('simak_main')->create('s1m4k_masterprodi', function (Blueprint $table): void {
            $table->string('id_prodi')->primary();
            $table->string('id_fakultas');
            $table->string('nama_program_studi');
        });
        Schema::connection('simak_main')->create('s1m4k_kalender', function (Blueprint $table): void {
            $table->string('id_kalender')->primary();
            $table->string('status');
            $table->string('status_sp')->default('N');
            $table->string('namatahun')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_mastermk', function (Blueprint $table): void {
            $table->string('id_matkul')->primary();
            $table->string('kode_mata_kuliah');
            $table->string('nama_mata_kuliah');
        });
        Schema::connection('simak_main')->create('s1m4k_masterruang', function (Blueprint $table): void {
            $table->string('id_ruang')->primary();
            $table->string('nama_ruang');
        });
        Schema::connection('simak_main')->create('s1m4k_masterdosen', function (Blueprint $table): void {
            $table->string('id_dosen')->primary();
            $table->string('nama_dosen');
            $table->string('gelar_depan')->nullable();
            $table->string('gelar_belakang')->nullable();
            $table->string('nidn')->nullable();
            $table->string('nip')->nullable();
        });
        Schema::connection('simak_main')->create('lms_hasil_ujian', function (Blueprint $table): void {
            $table->string('id_hasil_ujian')->primary();
            $table->string('id_ujian');
            $table->string('id_mahasiswa');
            $table->string('dimulai_pada')->nullable();
            $table->string('dikumpulkan_pada')->nullable();
            $table->decimal('nilai_akhir', 7, 2)->nullable();
            $table->text('komentar_dosen')->nullable();
            $table->string('status_hasil')->nullable();
            $table->string('dinilai_oleh')->nullable();
            $table->string('dinilai_pada')->nullable();
        });
        Schema::connection('simak_main')->create('lms_detail_hasil_ujian', function (Blueprint $table): void {
            $table->string('id_detail_hasil')->primary();
            $table->string('id_hasil_ujian');
            $table->string('id_soal')->nullable();
            $table->unsignedSmallInteger('urutan_tampil');
            $table->text('soal_snapshot');
            $table->text('jawaban_mahasiswa')->nullable();
            $table->boolean('jawaban_benar')->nullable();
            $table->decimal('nilai_jawaban', 7, 2)->nullable();
            $table->string('dijawab_pada')->nullable();
        });
        Schema::connection('simak_main')->create('lms_bank_soal', function (Blueprint $table): void {
            $table->string('id_soal')->primary();
            $table->string('id_dosen');
            $table->string('id_matkul');
            $table->string('jenis_soal');
            $table->text('pertanyaan');
            $table->text('opsi_a')->nullable();
            $table->text('opsi_b')->nullable();
            $table->text('opsi_c')->nullable();
            $table->text('opsi_d')->nullable();
            $table->text('opsi_e')->nullable();
            $table->string('kunci_jawaban')->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamp('dibuat_pada')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_mastermhs', function (Blueprint $table): void {
            $table->string('id_mahasiswa')->primary();
            $table->string('nim');
            $table->string('nama_mahasiswa');
        });
        Schema::connection('simak_main')->create('s1m4k_krs', function (Blueprint $table): void {
            $table->string('id_jadwal');
            $table->string('id_kalender');
            $table->string('id_mahasiswa');
        });
        Schema::connection('simak_main')->create('s1m4k_pengesahankrs', function (Blueprint $table): void {
            $table->string('id_mahasiswa');
            $table->string('id_kalender');
            $table->string('yang_mengesahkan')->nullable();
            $table->string('nama_kpro')->nullable();
        });

        DB::connection('simak_main')->table('s1m4k_masterprodi')->insert([
            'id_prodi' => 'program-1',
            'id_fakultas' => 'faculty-1',
            'nama_program_studi' => 'Program 1',
        ]);
        DB::connection('simak_main')->table('s1m4k_kalender')->insert([
            'id_kalender' => 'calendar-1',
            'status' => 'Y',
            'namatahun' => '2026 Ganjil',
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermk')->insert([
            'id_matkul' => 'course-1',
            'kode_mata_kuliah' => 'MK001',
            'nama_mata_kuliah' => 'Mata Kuliah 1',
        ]);
        DB::connection('simak_main')->table('s1m4k_masterdosen')->insert([
            'id_dosen' => 'lecturer-1',
            'nama_dosen' => 'Dosen Utama',
            'nidn' => 'NIDN-12345',
            'nip' => 'NIP-12345',
        ]);
        DB::connection('simak_main')->table('s1m4k_jadwal')->insert([
            'id_jadwal' => 'schedule-1',
            'id_prodi' => 'program-1',
            'id_kalender' => 'calendar-1',
            'id_matkul' => 'course-1',
            'id_ruang' => null,
            'nama_kelas' => 'A',
        ]);
        DB::connection('simak_main')->table('lms_pertemuan')->insert([
            'id_pertemuan' => 'meeting-1',
            'id_jadwal' => 'schedule-1',
            'id_dosen' => 'lecturer-1',
            'pertemuan_ke' => 1,
            'judul' => 'Pertemuan 1',
            'tanggal_kuliah' => '2026-10-24',
            'waktu_mulai' => '08:00',
            'waktu_selesai' => '09:30',
            'absen_dibuka' => true,
            'status_pertemuan' => 'terbit',
        ]);
        DB::connection('simak_main')->table('lms_pertemuan')->insert([
            'id_pertemuan' => 'meeting-2',
            'id_jadwal' => 'schedule-1',
            'id_dosen' => 'lecturer-1',
            'pertemuan_ke' => 2,
            'judul' => 'Pertemuan 2',
            'tanggal_kuliah' => '2026-10-25',
            'waktu_mulai' => '08:00',
            'waktu_selesai' => '09:30',
            'absen_dibuka' => true,
            'status_pertemuan' => 'terbit',
        ]);
        DB::connection('simak_main')->table('lms_ujian')->insert([
            'id_ujian' => 'exam-1',
            'id_pertemuan' => 'meeting-1',
            'id_dosen' => 'lecturer-1',
            'id_matkul' => 'course-1',
            'judul' => 'Ujian contoh',
            'jenis_ujian' => 'essay',
            'jumlah_soal_disajikan' => 1,
            'durasi_menit' => 60,
            'batas_mulai' => '2026-10-25 09:00:00',
            'batas_selesai' => '2026-10-25 10:00:00',
            'status_ujian' => 'terbit',
        ]);
    }

    private function prepareStudentExamAttempt(string $startedAt): void
    {
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            'id_mahasiswa' => 'student-1',
            'nim' => 'NIM-1',
            'nama_mahasiswa' => 'Mahasiswa Satu',
        ]);
        DB::connection('simak_main')->table('s1m4k_krs')->insert([
            'id_jadwal' => 'schedule-1',
            'id_kalender' => 'calendar-1',
            'id_mahasiswa' => 'student-1',
        ]);
        DB::connection('simak_main')->table('s1m4k_pengesahankrs')->insert([
            'id_mahasiswa' => 'student-1',
            'id_kalender' => 'calendar-1',
            'yang_mengesahkan' => 'Kaprodi',
            'nama_kpro' => 'Ketua Prodi',
        ]);
        DB::connection('simak_main')->table('lms_ujian')->where('id_ujian', 'exam-1')->update([
            'batas_mulai' => '2026-01-01 00:00:00',
            'batas_selesai' => '2030-01-01 00:00:00',
        ]);
        DB::connection('simak_main')->table('lms_hasil_ujian')->insert([
            'id_hasil_ujian' => 'result-active-1',
            'id_ujian' => 'exam-1',
            'id_mahasiswa' => 'student-1',
            'dimulai_pada' => $startedAt,
            'status_hasil' => 'berlangsung',
        ]);
    }
}
