<?php

namespace Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LmsDashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.simak_main' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
        ]]);
        DB::purge('simak_main');
        $schema = Schema::connection('simak_main');
        $schema->create('s1m4k_masterfakultas', function (Blueprint $table): void {
            $table->string('id_fakultas')->primary();
            $table->string('nama_fakultas')->nullable();
        });
        $schema->create('s1m4k_masterprodi', function (Blueprint $table): void {
            $table->string('id_prodi')->primary();
            $table->string('id_fakultas')->nullable();
            $table->string('nama_program_studi')->nullable();
            $table->string('nama_jenjang_pendidikan')->nullable();
            $table->string('status')->nullable();
        });
        $schema->create('s1m4k_mastermhs', function (Blueprint $table): void {
            $table->string('id_mahasiswa')->primary();
            $table->string('nim');
            $table->string('id_prodi')->nullable();
        });
        $schema->create('s1m4k_kalender', function (Blueprint $table): void {
            $table->string('id_kalender')->primary();
            $table->string('id_prodi');
            $table->string('tahun');
            $table->string('namatahun')->nullable();
            $table->string('status')->nullable();
        });
        $schema->create('s1m4k_masterdosen', function (Blueprint $table): void {
            $table->string('id_dosen')->primary();
            $table->string('nidn');
            $table->string('nama_dosen')->nullable();
        });
        $schema->create('s1m4k_mastermk', function (Blueprint $table): void {
            $table->string('id_matkul')->primary();
            $table->string('kode_mata_kuliah')->nullable();
            $table->string('nama_mata_kuliah')->nullable();
        });
        $schema->create('s1m4k_jadwal', function (Blueprint $table): void {
            $table->string('id_jadwal')->primary();
            $table->string('id_prodi');
            $table->string('id_kalender');
            $table->string('id_matkul');
            for ($slot = 1; $slot <= 14; $slot++) {
                $table->string('dosen'.$slot)->nullable();
            }
        });
        $schema->create('s1m4k_krs', function (Blueprint $table): void {
            $table->string('id_krs')->primary();
            $table->string('id_mahasiswa');
            $table->string('id_jadwal');
            $table->string('id_kalender');
        });
        $schema->create('s1m4k_pengesahankrs', function (Blueprint $table): void {
            $table->string('id_pengesahan')->primary();
            $table->string('id_mahasiswa');
            $table->string('id_kalender');
            $table->string('yang_mengesahkan')->nullable();
            $table->string('nama_kpro')->nullable();
        });
        $schema->create('lms_materi_kuliah', function (Blueprint $table): void {
            $table->string('id_materi')->primary();
            $table->string('id_matkul')->nullable();
            $table->string('status_publikasi');
        });
        $schema->create('lms_pertemuan', function (Blueprint $table): void {
            $table->string('id_pertemuan')->primary();
            $table->string('id_jadwal');
            $table->string('tanggal_kuliah')->nullable();
            $table->string('waktu_selesai')->nullable();
            $table->string('status_pertemuan')->nullable();
        });
        $schema->create('lms_tugas', function (Blueprint $table): void {
            $table->string('id_tugas')->primary();
            $table->string('id_jadwal');
        });
        $schema->create('lms_diskusi', function (Blueprint $table): void {
            $table->string('id_diskusi')->primary();
            $table->string('id_jadwal');
        });
        $schema->create('lms_bank_soal', function (Blueprint $table): void {
            $table->string('id_soal')->primary();
            $table->string('id_matkul');
        });
        $schema->create('lms_ujian', function (Blueprint $table): void {
            $table->string('id_ujian')->primary();
            $table->string('id_pertemuan');
        });
        $schema->create('lms_absensi', function (Blueprint $table): void {
            $table->string('id_absensi')->primary();
            $table->string('id_pertemuan');
            $table->string('id_mahasiswa');
            $table->string('status_kehadiran')->nullable();
        });

        DB::connection('simak_main')->table('s1m4k_masterdosen')->insert([
            ['id_dosen' => 'lecturer-1', 'nidn' => 'NIDN001', 'nama_dosen' => 'Dosen Satu'],
            ['id_dosen' => 'lecturer-2', 'nidn' => 'NIDN002', 'nama_dosen' => 'Dosen Dua'],
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermk')->insert([
            ['id_matkul' => 'course-1', 'kode_mata_kuliah' => 'MK001', 'nama_mata_kuliah' => 'Mata Kuliah Satu'],
            ['id_matkul' => 'course-2', 'kode_mata_kuliah' => 'MK002', 'nama_mata_kuliah' => 'Mata Kuliah Dua'],
        ]);
        $schema->create('lms_pengumpulan_tugas', function (Blueprint $table): void {
            $table->string('id_pengumpulan')->primary();
            $table->string('id_tugas');
            $table->string('id_mahasiswa');
        });
        $schema->create('lms_hasil_ujian', function (Blueprint $table): void {
            $table->string('id_hasil_ujian')->primary();
            $table->string('id_ujian');
            $table->string('id_mahasiswa');
        });

        DB::connection('simak_main')->table('s1m4k_masterfakultas')->insert([
            ['id_fakultas' => 'faculty-1', 'nama_fakultas' => 'Fakultas Satu'],
            ['id_fakultas' => 'faculty-2', 'nama_fakultas' => 'Fakultas Dua'],
        ]);
        DB::connection('simak_main')->table('s1m4k_masterprodi')->insert([
            ['id_prodi' => 'program-1', 'id_fakultas' => 'faculty-1', 'nama_program_studi' => 'Program Satu', 'nama_jenjang_pendidikan' => 'S1', 'status' => 'A'],
            ['id_prodi' => 'program-2', 'id_fakultas' => 'faculty-2', 'nama_program_studi' => 'Program Dua', 'nama_jenjang_pendidikan' => 'S1', 'status' => 'A'],
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            ['id_mahasiswa' => 'student-1', 'nim' => 'NIM001', 'id_prodi' => 'program-1'],
            ['id_mahasiswa' => 'student-2', 'nim' => 'NIM002', 'id_prodi' => 'program-2'],
        ]);
        DB::connection('simak_main')->table('s1m4k_kalender')->insert([
            ['id_kalender' => 'calendar-1', 'id_prodi' => 'program-1', 'tahun' => '20261', 'namatahun' => '2026 Ganjil', 'status' => 'Y'],
            ['id_kalender' => 'calendar-2', 'id_prodi' => 'program-2', 'tahun' => '20261', 'namatahun' => '2026 Ganjil', 'status' => 'Y'],
            ['id_kalender' => 'calendar-old', 'id_prodi' => 'program-1', 'tahun' => '20252', 'namatahun' => '2025 Genap', 'status' => 'N'],
        ]);
        DB::connection('simak_main')->table('s1m4k_jadwal')->insert([
            ['id_jadwal' => 'schedule-1', 'id_prodi' => 'program-1', 'id_kalender' => 'calendar-1', 'id_matkul' => 'course-1', 'dosen2' => 'lecturer-1'],
            ['id_jadwal' => 'schedule-2', 'id_prodi' => 'program-2', 'id_kalender' => 'calendar-2', 'id_matkul' => 'course-2', 'dosen1' => 'lecturer-2'],
        ]);
        DB::connection('simak_main')->table('s1m4k_krs')->insert([
            ['id_krs' => 'krs-1', 'id_mahasiswa' => 'student-1', 'id_jadwal' => 'schedule-1', 'id_kalender' => 'calendar-1'],
            ['id_krs' => 'krs-2', 'id_mahasiswa' => 'student-2', 'id_jadwal' => 'schedule-2', 'id_kalender' => 'calendar-2'],
        ]);
        DB::connection('simak_main')->table('s1m4k_pengesahankrs')->insert([
            ['id_pengesahan' => 'approval-1', 'id_mahasiswa' => 'student-1', 'id_kalender' => 'calendar-1', 'yang_mengesahkan' => 'Dosen PA', 'nama_kpro' => 'Kaprodi'],
            ['id_pengesahan' => 'approval-2', 'id_mahasiswa' => 'student-2', 'id_kalender' => 'calendar-2', 'yang_mengesahkan' => 'Dosen PA', 'nama_kpro' => 'Kaprodi'],
        ]);
        foreach ([
            'lms_materi_kuliah' => [
                ['id_materi' => 'material-1', 'id_matkul' => 'course-1', 'status_publikasi' => 'terbit'],
                ['id_materi' => 'material-2', 'id_matkul' => 'course-2', 'status_publikasi' => 'terbit'],
            ],
            'lms_pertemuan' => [
                ['id_pertemuan' => 'meeting-1', 'id_jadwal' => 'schedule-1', 'tanggal_kuliah' => '2026-10-05', 'waktu_selesai' => '11:00', 'status_pertemuan' => 'terbit'],
                ['id_pertemuan' => 'meeting-2', 'id_jadwal' => 'schedule-2', 'tanggal_kuliah' => '2026-10-05', 'waktu_selesai' => '11:00', 'status_pertemuan' => 'terbit'],
            ],
            'lms_tugas' => [
                ['id_tugas' => 'task-1', 'id_jadwal' => 'schedule-1'],
                ['id_tugas' => 'task-2', 'id_jadwal' => 'schedule-2'],
            ],
            'lms_diskusi' => [
                ['id_diskusi' => 'discussion-1', 'id_jadwal' => 'schedule-1'],
                ['id_diskusi' => 'discussion-2', 'id_jadwal' => 'schedule-2'],
            ],
            'lms_bank_soal' => [
                ['id_soal' => 'question-1', 'id_matkul' => 'course-1'],
                ['id_soal' => 'question-2', 'id_matkul' => 'course-2'],
            ],
            'lms_ujian' => [
                ['id_ujian' => 'exam-1', 'id_pertemuan' => 'meeting-1'],
                ['id_ujian' => 'exam-2', 'id_pertemuan' => 'meeting-2'],
            ],
            'lms_absensi' => [
                ['id_absensi' => 'attendance-1', 'id_pertemuan' => 'meeting-1', 'id_mahasiswa' => 'student-1', 'status_kehadiran' => 'hadir'],
                ['id_absensi' => 'attendance-2', 'id_pertemuan' => 'meeting-1', 'id_mahasiswa' => 'student-2', 'status_kehadiran' => 'hadir'],
                ['id_absensi' => 'attendance-3', 'id_pertemuan' => 'meeting-2', 'id_mahasiswa' => 'student-2', 'status_kehadiran' => 'hadir'],
            ],
            'lms_pengumpulan_tugas' => [
                ['id_pengumpulan' => 'submission-1', 'id_tugas' => 'task-1', 'id_mahasiswa' => 'student-1'],
                ['id_pengumpulan' => 'submission-2', 'id_tugas' => 'task-2', 'id_mahasiswa' => 'student-2'],
            ],
            'lms_hasil_ujian' => [
                ['id_hasil_ujian' => 'result-1', 'id_ujian' => 'exam-1', 'id_mahasiswa' => 'student-1'],
                ['id_hasil_ujian' => 'result-2', 'id_ujian' => 'exam-2', 'id_mahasiswa' => 'student-2'],
            ],
        ] as $table => $rows) {
            DB::connection('simak_main')->table($table)->insert($rows);
        }
    }

    public function test_prodi_dashboard_counts_lms_activity_only_inside_its_scope_and_calendar(): void
    {
        $this->actingAs(new GenericUser([
            'id' => 'operator-1', 'role' => 'userprodi', 'id_prodi' => 'program-1',
        ]));

        $this->get(route('lms-dashboard.index'))
            ->assertOk()
            ->assertSee('Program Satu')
            ->assertSee('2026 Ganjil')
            ->assertSee('data-activity-label="Mata kuliah" data-activity-count="1"', false)
            ->assertSee('data-activity-label="Absensi" data-activity-count="2"', false);
        $this->get(route('lms-dashboard.index', ['calendar' => '20252']))
            ->assertOk()
            ->assertSee('2025 Genap')
            ->assertSee('Tahun akademik lampau · hanya baca');
        $this->get(route('lms-dashboard.index', ['calendar' => '20261']))
            ->assertOk()
            ->assertDontSee('Tahun akademik lampau · hanya baca');
    }

    public function test_lms_dashboard_ignores_inactive_programs(): void
    {
        DB::connection('simak_main')->table('s1m4k_masterprodi')
            ->where('id_prodi', 'program-2')
            ->update(['status' => 'N']);
        $this->actingAs(new GenericUser(['id' => 'operator-admin', 'role' => 'admin']));

        $this->get(route('lms-dashboard.index'))
            ->assertOk()
            ->assertSee('Program Satu')
            ->assertDontSee('Program Dua')
            ->assertDontSee('MK002');
    }

    public function test_student_dashboard_shows_only_approved_class_and_personal_participation(): void
    {
        $this->actingAs(new GenericUser([
            'id' => 'student-1', 'role' => 'mahasiswa', 'login' => 'NIM001',
        ]));

        $this->get(route('lms-dashboard.index'))
            ->assertOk()
            ->assertSee('Program Satu')
            ->assertSee('data-activity-label="Absensi" data-activity-count="1"', false)
            ->assertSee('data-activity-label="Pengumpulan tugas" data-activity-count="1"', false)
            ->assertSee('data-activity-label="Pengerjaan ujian" data-activity-count="1"', false);
    }

    public function test_lms_dashboard_denies_roles_outside_the_requested_audience(): void
    {
        $this->actingAs(new GenericUser(['id' => 'assistant-1', 'role' => 'asdos']));

        $this->get(route('lms-dashboard.index'))->assertForbidden();
    }

    public function test_lecturer_dashboard_shows_only_their_courses_and_attendance_trend(): void
    {
        $this->actingAs(new GenericUser([
            'id' => 'lecturer-user', 'login' => 'NIDN001', 'role' => 'dosen',
        ]));

        $this->get(route('lms-dashboard.index'))
            ->assertOk()
            ->assertSee('Mata kuliah yang diampu')
            ->assertSee('MK001 — Mata Kuliah Satu')
            ->assertSee('Sesi lewat waktu selesai')
            ->assertDontSee('MK002 — Mata Kuliah Dua');
    }

    public function test_attendance_trend_counts_sessions_by_end_time_and_includes_scheduled_sessions_in_denominator(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-31 12:00:00', 'Asia/Jayapura'));
        try {
            $connection = DB::connection('simak_main');
            $connection->table('s1m4k_jadwal')->insert([
                'id_jadwal' => 'schedule-3',
                'id_prodi' => 'program-1',
                'id_kalender' => 'calendar-1',
                'id_matkul' => 'course-2',
            ]);
            $connection->table('s1m4k_krs')->insert([
                'id_krs' => 'krs-3', 'id_mahasiswa' => 'student-1',
                'id_jadwal' => 'schedule-3', 'id_kalender' => 'calendar-1',
            ]);
            $connection->table('s1m4k_pengesahankrs')->insert([
                'id_pengesahan' => 'approval-3', 'id_mahasiswa' => 'student-1',
                'id_kalender' => 'calendar-1', 'yang_mengesahkan' => 'Dosen PA', 'nama_kpro' => 'Kaprodi',
            ]);
            $connection->table('lms_pertemuan')->insert([
                ['id_pertemuan' => 'meeting-3', 'id_jadwal' => 'schedule-1', 'tanggal_kuliah' => '2026-10-06', 'waktu_selesai' => '11:00', 'status_pertemuan' => 'terbit'],
                ['id_pertemuan' => 'meeting-4', 'id_jadwal' => 'schedule-3', 'tanggal_kuliah' => '2026-10-07', 'waktu_selesai' => '11:00', 'status_pertemuan' => 'terbit'],
                ['id_pertemuan' => 'meeting-future', 'id_jadwal' => 'schedule-1', 'tanggal_kuliah' => '2026-11-05', 'waktu_selesai' => '11:00', 'status_pertemuan' => 'selesai'],
            ]);
            $connection->table('lms_absensi')->insert([
                ['id_absensi' => 'attendance-4', 'id_pertemuan' => 'meeting-3', 'id_mahasiswa' => 'student-1', 'status_kehadiran' => 'hadir'],
                ['id_absensi' => 'attendance-5', 'id_pertemuan' => 'meeting-4', 'id_mahasiswa' => 'student-1', 'status_kehadiran' => 'hadir'],
            ]);
            $this->actingAs(new GenericUser([
                'id' => 'operator-1', 'role' => 'userprodi', 'id_prodi' => 'program-1',
            ]));

            $this->get(route('lms-dashboard.index', ['calendar' => '20261']))
                ->assertOk()
                ->assertSee('Program Satu')
                ->assertSee('MK001 — Mata Kuliah Satu')
                ->assertSee('MK002 — Mata Kuliah Dua')
                ->assertSee('data-meeting-count="3"', false)
                ->assertSee('data-meeting-count="1"', false)
                ->assertSee('2 pertemuan')
                ->assertSee('3 pertemuan')
                ->assertSee('1 pertemuan')
                ->assertSee('100% / 100%')
                ->assertSee('— / 0%');
        } finally {
            Carbon::setTestNow();
        }
    }
}
