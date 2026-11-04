<?php

namespace Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class CourseAttendanceTest extends TestCase
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

    public function test_attendance_schedule_list_obeys_dispatcher_roles_and_organization_scope(): void
    {
        $this->actingAs($this->user('admin', 'admin-1'));
        $this->get(route('course-attendance.index', ['program' => 'program-1']))
            ->assertOk()->assertSee('Mata Kuliah Satu')->assertSee('Cetak Absen');

        $this->actingAs($this->user('userprodi', 'operator-1', ['id_prodi' => 'program-1']));
        $this->get(route('course-attendance.index'))
            ->assertOk()->assertSee('Kelas Satu')->assertDontSee('Kelas Dua');
        $this->get(route('course-attendance.index', ['program' => 'program-2']))
            ->assertOk()->assertSee('Kelas Satu')->assertDontSee('Kelas Dua');

        $this->actingAs($this->user('usertu', 'operator-tu', ['id_prodi' => 'program-1']));
        $this->get(route('course-attendance.index'))->assertOk()->assertSee('Kelas Satu');

        $this->actingAs($this->user('dosen', 'lecturer-1', ['login' => 'NIDN-1']));
        $this->get(route('course-attendance.index', ['program' => 'program-1']))
            ->assertOk()->assertSee('Kelas Satu')->assertDontSee('Kelas Dua');
        $this->get(route('course-attendance.print', ['schedule' => 'schedule-2', 'document' => 'attendance']))
            ->assertNotFound();

        $this->actingAs($this->user('asdos', 'assistant-1', ['login' => 'ASDOS-1']));
        $this->get(route('course-attendance.index', ['program' => 'program-1']))
            ->assertOk()->assertSee('Kelas Satu');

        $this->actingAs($this->user('mahasiswa', 'student-1'));
        $this->get(route('course-attendance.index'))->assertForbidden();
    }

    public function test_attendance_pdf_uses_approved_students_and_all_print_outputs_are_available(): void
    {
        $this->actingAs($this->user('admin', 'admin-1'));
        $this->get(route('course-attendance.index', ['program' => 'program-1']))
            ->assertOk()
            ->assertSee('Cetak Absen')
            ->assertSee('Berita Acara Kuliah')
            ->assertSee('Daftar Mahasiswa');

        $this->get(route('course-attendance.print', ['schedule' => 'schedule-1', 'document' => 'attendance']))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('course-attendance.print', ['schedule' => 'schedule-1', 'document' => 'class-record']))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('course-attendance.print', ['schedule' => 'schedule-1', 'document' => 'midterm']))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('course-attendance.print', ['schedule' => 'schedule-1', 'document' => 'final']))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $roster = $this->get(route('course-attendance.print', ['schedule' => 'schedule-1', 'document' => 'roster']))
            ->assertOk()->assertHeader('Content-Type', 'application/vnd.ms-excel')
            ->assertHeader('Content-Disposition', 'attachment; filename="Absen-Matakuliah-MataKuliahSatu(Kelas-KelasSatu).xls"');
        $this->assertStringStartsWith("\xD0\xCF\x11\xE0", $roster->getContent());
        $path = tempnam(sys_get_temp_dir(), 'attendance-roster-');
        $this->assertNotFalse($path);
        try {
            file_put_contents($path, $roster->getContent());
            $workbook = IOFactory::load($path);
            $sheet = $workbook->getActiveSheet();
            $this->assertSame('2025001', $sheet->getCell('B3')->getValue());
            $this->assertSame('Mahasiswa Satu', $sheet->getCell('C3')->getValue());
            $this->assertNull($sheet->getCell('B4')->getValue());
        } finally {
            unlink($path);
        }
        $this->get(route('course-attendance.print', ['schedule' => 'schedule-1', 'document' => 'unknown']))
            ->assertNotFound();
    }

    public function test_every_print_document_is_available_for_all_permitted_roles_and_scoped_to_their_schedule(): void
    {
        $documents = ['attendance', 'class-record', 'roster', 'midterm', 'final'];
        $roles = [
            ['admin', 'admin-1', [], 'schedule-1'],
            ['userprodi', 'operator-1', ['id_prodi' => 'program-1'], 'schedule-1'],
            ['usertu', 'operator-tu', ['id_prodi' => 'program-1'], 'schedule-1'],
            ['dosen', 'lecturer-1', ['login' => 'NIDN-1'], 'schedule-1'],
            ['asdos', 'assistant-1', ['login' => 'ASDOS-1'], 'schedule-1'],
        ];

        foreach ($roles as [$role, $id, $extra, $schedule]) {
            $this->actingAs($this->user($role, $id, $extra));
            foreach ($documents as $document) {
                $response = $this->get(route('course-attendance.print', [
                    'schedule' => $schedule,
                    'document' => $document,
                ]))->assertOk();

                if ($document === 'roster') {
                    $response->assertHeader('Content-Type', 'application/vnd.ms-excel');
                    $this->assertStringStartsWith("\xD0\xCF\x11\xE0", $response->getContent());
                } else {
                    $response->assertHeader('Content-Type', 'application/pdf');
                    $this->assertStringStartsWith('%PDF-', $response->getContent());
                }
            }
        }

        foreach ([
            ['userprodi', 'operator-1', ['id_prodi' => 'program-1']],
            ['usertu', 'operator-tu', ['id_prodi' => 'program-1']],
            ['dosen', 'lecturer-1', ['login' => 'NIDN-1']],
            ['asdos', 'assistant-1', ['login' => 'ASDOS-1']],
        ] as [$role, $id, $extra]) {
            $this->actingAs($this->user($role, $id, $extra));
            foreach ($documents as $document) {
                $this->get(route('course-attendance.print', [
                    'schedule' => 'schedule-2',
                    'document' => $document,
                ]))->assertNotFound();
            }
        }
    }

    public function test_attendance_can_select_and_print_a_historical_calendar(): void
    {
        DB::connection('simak_main')->table('s1m4k_kalender')->insert([
            'id_kalender' => 'calendar-old',
            'id_prodi' => 'program-1',
            'tahun' => '20252',
            'namatahun' => '2025 Genap',
            'status' => 'N',
        ]);
        $oldSchedule = DB::connection('simak_main')->table('s1m4k_jadwal')
            ->where('id_jadwal', 'schedule-1')->first();
        DB::connection('simak_main')->table('s1m4k_jadwal')->insert([
            ...(array) $oldSchedule,
            'id_jadwal' => 'schedule-old',
            'id_kalender' => 'calendar-old',
            'nama_kelas' => 'Kelas Historis',
        ]);
        DB::connection('simak_main')->table('s1m4k_krs')->insert([
            'id_krs' => 'krs-old',
            'id_mahasiswa' => 'student-1',
            'id_jadwal' => 'schedule-old',
            'id_kalender' => 'calendar-old',
        ]);
        DB::connection('simak_main')->table('s1m4k_pengesahankrs')->insert([
            'id_pengesahan' => 'approval-old',
            'id_mahasiswa' => 'student-1',
            'id_kalender' => 'calendar-old',
            'yang_mengesahkan' => 'Dosen PA',
            'nama_kpro' => 'Ketua Prodi',
        ]);
        $this->actingAs($this->user('admin', 'admin-1'));

        $this->get(route('course-attendance.index', ['program' => 'program-1']))
            ->assertOk()
            ->assertSee('id="attendance-calendar"', false)
            ->assertSee('2025 Genap');
        $this->get(route('course-attendance.index', [
            'program' => 'program-1',
            'calendar' => 'calendar-old',
        ]))
            ->assertOk()
            ->assertSee('Kelas Historis')
            ->assertSee('2025 Genap')
            ->assertSee('value="calendar-old" selected', false);
        $this->get(route('course-attendance.print', ['schedule' => 'schedule-old', 'document' => 'attendance']))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $this->actingAs($this->user('dosen', 'lecturer-1', ['login' => 'NIDN-1']));
        $this->get(route('course-attendance.index', [
            'program' => 'program-1',
            'calendar' => 'calendar-old',
        ]))->assertOk()->assertSee('Kelas Historis');
        $this->get(route('course-attendance.print', ['schedule' => 'schedule-old', 'document' => 'attendance']))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_attendance_counts_only_prodi_approved_students_for_printed_attendance(): void
    {
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            'id_mahasiswa' => 'student-3', 'nim' => '2025003', 'nama_mahasiswa' => 'Mahasiswa Tiga',
        ]);
        DB::connection('simak_main')->table('s1m4k_krs')->insert([
            'id_krs' => 'krs-3', 'id_mahasiswa' => 'student-3', 'id_jadwal' => 'schedule-1', 'id_kalender' => 'calendar-1',
        ]);
        DB::connection('simak_main')->table('s1m4k_pengesahankrs')->insert([
            'id_pengesahan' => 'approval-3', 'id_mahasiswa' => 'student-3', 'id_kalender' => 'calendar-1',
            'yang_mengesahkan' => 'Dosen PA', 'nama_kpro' => '',
        ]);
        $this->actingAs($this->user('admin', 'admin-1'));

        $this->get(route('course-attendance.index', ['program' => 'program-1']))
            ->assertOk()
            ->assertSee('1 disahkan dari 2 mahasiswa terdaftar')
            ->assertSee('1 belum disahkan prodi');

        $response = $this->get(route('course-attendance.print', [
            'schedule' => 'schedule-1', 'document' => 'attendance',
        ]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $response->getContent(), $streams);
        $pdfText = '';
        foreach ($streams[1] as $stream) {
            if (str_starts_with($stream, "\x78")) {
                $pdfText .= gzuncompress($stream) ?: '';
            }
        }
        $this->assertStringContainsString('Mahasiswa Satu', $pdfText);
        $this->assertStringNotContainsString('Mahasiswa Tiga', $pdfText);
    }

    public function test_pending_prodi_approval_names_are_available_in_modal_only_to_admin_and_program_staff(): void
    {
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            'id_mahasiswa' => 'student-3', 'nim' => '2025003', 'nama_mahasiswa' => 'Mahasiswa Tiga',
        ]);
        DB::connection('simak_main')->table('s1m4k_krs')->insert([
            'id_krs' => 'krs-3', 'id_mahasiswa' => 'student-3', 'id_jadwal' => 'schedule-1', 'id_kalender' => 'calendar-1',
        ]);
        DB::connection('simak_main')->table('s1m4k_pengesahankrs')->insert([
            'id_pengesahan' => 'approval-3', 'id_mahasiswa' => 'student-3', 'id_kalender' => 'calendar-1',
            'yang_mengesahkan' => 'Dosen PA', 'nama_kpro' => '',
        ]);
        $this->actingAs($this->user('admin', 'admin-1'));
        $this->get(route('course-attendance.index', ['program' => 'program-1']))
            ->assertOk()
            ->assertSee('data-attendance-pending-open', false)
            ->assertSee('data-attendance-pending-modal', false);
        $this->get(route('course-attendance.pending-approvals', 'schedule-1'))
            ->assertOk()
            ->assertExactJson(['students' => [[
                'nim' => '2025003', 'nama_mahasiswa' => 'Mahasiswa Tiga',
            ]]]);

        $this->actingAs($this->user('userprodi', 'operator-1', ['id_prodi' => 'program-1']));
        $this->get(route('course-attendance.pending-approvals', 'schedule-1'))
            ->assertOk()->assertJsonPath('students.0.nama_mahasiswa', 'Mahasiswa Tiga');

        $this->actingAs($this->user('usertu', 'operator-tu', ['id_prodi' => 'program-1']));
        $this->get(route('course-attendance.pending-approvals', 'schedule-1'))
            ->assertOk()->assertJsonPath('students.0.nim', '2025003');
        $this->get(route('course-attendance.pending-approvals', 'schedule-2'))->assertNotFound();

        $this->actingAs($this->user('dosen', 'lecturer-1', ['login' => 'NIDN-1']));
        $this->get(route('course-attendance.pending-approvals', 'schedule-1'))->assertForbidden();
    }

    private function createSchema(): void
    {
        Schema::connection('simak_main')->create('s1m4k_masterprodi', function (Blueprint $table): void {
            $table->string('id_prodi')->primary();
            $table->string('nama_program_studi');
            $table->string('nama_jenjang_pendidikan')->nullable();
            $table->string('ketua_prodi')->nullable();
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
            $table->string('semester')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterruang', function (Blueprint $table): void {
            $table->string('id_ruang')->primary();
            $table->string('nama_ruang')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterdosen', function (Blueprint $table): void {
            $table->string('id_dosen')->primary();
            $table->string('nidn');
            $table->string('nama_dosen');
            $table->string('gelar_depan')->nullable();
            $table->string('gelar_belakang')->nullable();
            $table->string('nip')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterasdos', function (Blueprint $table): void {
            $table->string('id_asdos')->primary();
            $table->string('kdasisten');
            $table->string('nama');
            $table->string('gelard')->nullable();
            $table->string('gelarb')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_mastermhs', function (Blueprint $table): void {
            $table->string('id_mahasiswa')->primary();
            $table->string('nim');
            $table->string('nama_mahasiswa');
        });
        Schema::connection('simak_main')->create('s1m4k_jadwal', function (Blueprint $table): void {
            $table->string('id_jadwal')->primary();
            $table->string('id_prodi');
            $table->string('id_kalender');
            $table->string('id_matkul');
            $table->string('id_ruang')->nullable();
            foreach (range(1, 7) as $slot) {
                $table->string('dosen'.$slot)->nullable();
            }
            $table->string('hari')->nullable();
            $table->string('jammulai')->nullable();
            $table->string('jamselesai')->nullable();
            $table->string('nama_kelas')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_krs', function (Blueprint $table): void {
            $table->string('id_krs')->primary();
            $table->string('id_mahasiswa');
            $table->string('id_jadwal');
            $table->string('id_kalender');
        });
        Schema::connection('simak_main')->create('s1m4k_pengesahankrs', function (Blueprint $table): void {
            $table->string('id_pengesahan')->primary();
            $table->string('id_mahasiswa');
            $table->string('id_kalender');
            $table->string('yang_mengesahkan')->nullable();
            $table->string('nama_kpro')->nullable();
        });
    }

    private function seedData(): void
    {
        DB::connection('simak_main')->table('s1m4k_masterprodi')->insert([
            ['id_prodi' => 'program-1', 'nama_program_studi' => 'Program Satu', 'nama_jenjang_pendidikan' => 'S1', 'ketua_prodi' => 'chair-1'],
            ['id_prodi' => 'program-2', 'nama_program_studi' => 'Program Dua', 'nama_jenjang_pendidikan' => 'S1', 'ketua_prodi' => 'chair-2'],
        ]);
        DB::connection('simak_main')->table('s1m4k_kalender')->insert([
            ['id_kalender' => 'calendar-1', 'id_prodi' => 'program-1', 'tahun' => '20261', 'namatahun' => '2026 Ganjil', 'status' => 'Y'],
            ['id_kalender' => 'calendar-2', 'id_prodi' => 'program-2', 'tahun' => '20261', 'namatahun' => '2026 Ganjil', 'status' => 'Y'],
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermk')->insert([
            ['id_matkul' => 'course-1', 'kode_mata_kuliah' => 'MK001', 'nama_mata_kuliah' => 'Mata Kuliah Satu', 'semester' => '1'],
            ['id_matkul' => 'course-2', 'kode_mata_kuliah' => 'MK002', 'nama_mata_kuliah' => 'Mata Kuliah Dua', 'semester' => '2'],
        ]);
        DB::connection('simak_main')->table('s1m4k_masterruang')->insert([
            ['id_ruang' => 'room-1', 'nama_ruang' => 'Ruang Satu'],
            ['id_ruang' => 'room-2', 'nama_ruang' => 'Ruang Dua'],
        ]);
        DB::connection('simak_main')->table('s1m4k_masterdosen')->insert([
            ['id_dosen' => 'lecturer-1', 'nidn' => 'NIDN-1', 'nama_dosen' => 'Dosen Satu'],
            ['id_dosen' => 'chair-1', 'nidn' => 'NIDN-C1', 'nama_dosen' => 'Ketua Satu'],
            ['id_dosen' => 'lecturer-2', 'nidn' => 'NIDN-2', 'nama_dosen' => 'Dosen Dua'],
            ['id_dosen' => 'chair-2', 'nidn' => 'NIDN-C2', 'nama_dosen' => 'Ketua Dua'],
        ]);
        DB::connection('simak_main')->table('s1m4k_masterasdos')->insert([
            ['id_asdos' => 'assistant-1', 'kdasisten' => 'ASDOS-1', 'nama' => 'Asisten Satu'],
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            ['id_mahasiswa' => 'student-1', 'nim' => '2025001', 'nama_mahasiswa' => 'Mahasiswa Satu'],
            ['id_mahasiswa' => 'student-2', 'nim' => '2025002', 'nama_mahasiswa' => 'Mahasiswa Dua'],
        ]);
        $scheduleDefaults = [
            'id_ruang' => 'room-1', 'hari' => 'senin', 'jammulai' => '08:00', 'jamselesai' => '10:00',
            'dosen1' => 'lecturer-1', 'dosen2' => null, 'dosen3' => null, 'dosen4' => null,
            'dosen5' => null, 'dosen6' => null, 'dosen7' => null,
        ];
        DB::connection('simak_main')->table('s1m4k_jadwal')->insert([
            ['id_jadwal' => 'schedule-1', 'id_prodi' => 'program-1', 'id_kalender' => 'calendar-1', 'id_matkul' => 'course-1', 'nama_kelas' => 'Kelas Satu', ...$scheduleDefaults, 'dosen2' => 'assistant-1'],
            ['id_jadwal' => 'schedule-2', 'id_prodi' => 'program-2', 'id_kalender' => 'calendar-2', 'id_matkul' => 'course-1', 'nama_kelas' => 'Kelas Dua', ...$scheduleDefaults, 'dosen1' => 'lecturer-2'],
        ]);
        DB::connection('simak_main')->table('s1m4k_krs')->insert([
            ['id_krs' => 'krs-1', 'id_mahasiswa' => 'student-1', 'id_jadwal' => 'schedule-1', 'id_kalender' => 'calendar-1'],
            ['id_krs' => 'krs-2', 'id_mahasiswa' => 'student-2', 'id_jadwal' => 'schedule-2', 'id_kalender' => 'calendar-2'],
        ]);
        DB::connection('simak_main')->table('s1m4k_pengesahankrs')->insert([
            ['id_pengesahan' => 'approval-1', 'id_mahasiswa' => 'student-1', 'id_kalender' => 'calendar-1', 'yang_mengesahkan' => 'Dosen PA', 'nama_kpro' => 'Ketua Prodi'],
            ['id_pengesahan' => 'approval-2', 'id_mahasiswa' => 'student-2', 'id_kalender' => 'calendar-2', 'yang_mengesahkan' => 'Dosen PA', 'nama_kpro' => 'Ketua Prodi'],
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
