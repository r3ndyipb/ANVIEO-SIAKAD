<?php

namespace Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class StudentAcademicActivityTest extends TestCase
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

    public function test_admin_generates_akm_metrics_and_registration_status_for_a_calendar(): void
    {
        $this->actingAs($this->user('admin', 'admin-1'));
        $this->get(route('student-akm.index', ['program' => 'program-1', 'calendar' => 'calendar-current']))
            ->assertOk()->assertSee('Generate Data')->assertSee('Unduh Excel')->assertSee('2026 Ganjil');
        DB::connection('simak_main')->table('s1m4k_akm')->insert([
            $this->akmRow('akm-stale-active', 'student-1', 'program-1', 'calendar-current', 'N'),
            $this->akmRow('akm-stale-not-registered', 'student-3', 'program-1', 'calendar-current', 'A'),
        ]);
        $this->post(route('student-akm.generate'), [
            'program' => 'program-1', 'calendar' => 'calendar-current',
        ])->assertRedirect(route('student-akm.index', ['program' => 'program-1', 'calendar' => 'calendar-current']))
            ->assertSessionHas('status');

        $active = DB::connection('simak_main')->table('s1m4k_akm')->where('id_mahasiswa', 'student-1')->first();
        $notRegistered = DB::connection('simak_main')->table('s1m4k_akm')->where('id_mahasiswa', 'student-3')->first();
        $this->assertSame('A', $active->status_mahasiswa);
        $this->assertSame('3.00', $active->ips);
        $this->assertSame('3.00', $active->ipk);
        $this->assertSame('3.00', $active->sks_semester);
        $this->assertSame('N', $notRegistered->status_mahasiswa);
        $this->assertSame(1, DB::connection('simak_main')->table('log_aktivitas')
            ->where('modul', 'Aktivitas Kuliah Mahasiswa')->count());
    }

    public function test_akm_excel_export_includes_filtered_rows_and_ignores_program_override_for_staff(): void
    {
        DB::connection('simak_main')->table('s1m4k_akm')->insert([
            $this->akmRow('akm-export-one', 'student-1', 'program-1', 'calendar-current', 'A'),
            $this->akmRow('akm-export-cross', 'student-2', 'program-1', 'calendar-current', 'A'),
        ]);
        $this->actingAs($this->user('admin', 'admin-1'));
        $response = $this->get(route('student-akm.export.excel', [
            'program' => 'program-1',
            'calendar' => 'calendar-current',
        ]))->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertHeader('Content-Disposition', 'attachment; filename="aktivitas-kuliah-20261.xlsx"');
        $spreadsheet = $this->spreadsheetFromResponse($response->getContent());
        $this->assertSame('NPM', $spreadsheet->getActiveSheet()->getCell('A1')->getValue());
        $this->assertSame('2025001', $spreadsheet->getActiveSheet()->getCell('A2')->getValue());
        $this->assertSame('Mahasiswa Satu', $spreadsheet->getActiveSheet()->getCell('B2')->getValue());
        $this->assertSame(2, $spreadsheet->getActiveSheet()->getHighestRow());
        $spreadsheet->disconnectWorksheets();

        $this->actingAs($this->user('userprodi', 'operator-1', ['id_prodi' => 'program-1']));
        $response = $this->get(route('student-akm.export.excel', [
            'program' => 'program-2',
            'calendar' => 'calendar-current',
        ]))->assertOk();
        $spreadsheet = $this->spreadsheetFromResponse($response->getContent());
        $this->assertSame(2, $spreadsheet->getActiveSheet()->getHighestRow());
        $spreadsheet->disconnectWorksheets();
    }

    public function test_user_can_correct_generated_akm_status_before_permanent_lock(): void
    {
        DB::connection('simak_main')->table('s1m4k_akm')->insert(
            $this->akmRow('akm-one', 'student-3', 'program-1', 'calendar-current', 'N')
        );
        $this->actingAs($this->user('userprodi', 'operator-1', ['id_prodi' => 'program-1']));
        $this->post(route('student-akm.status.update', 'akm-one'), [
            'status' => 'C', 'program' => 'program-1', 'calendar' => 'calendar-current',
        ])->assertRedirect(route('student-akm.index', [
            'program' => 'program-1', 'calendar' => 'calendar-current',
        ]))->assertSessionHas('status');
        $this->assertSame('C', DB::connection('simak_main')->table('s1m4k_akm')
            ->where('id_ipmhs', 'akm-one')->value('status_mahasiswa'));
        $this->assertSame('C', DB::connection('simak_main')->table('s1m4k_mastermhs')
            ->where('id_mahasiswa', 'student-3')->value('id_status_mahasiswa'));
        $this->assertSame('Cuti', DB::connection('simak_main')->table('s1m4k_mastermhs')
            ->where('id_mahasiswa', 'student-3')->value('nama_status_mahasiswa'));

        $this->post(route('student-akm.lock'), [
            'program' => 'program-1', 'calendar' => 'calendar-current',
        ])->assertRedirect();
        $this->post(route('student-akm.status.update', 'akm-one'), [
            'status' => '2', 'program' => 'program-1', 'calendar' => 'calendar-current',
        ])->assertStatus(422);
        $this->assertSame('C', DB::connection('simak_main')->table('s1m4k_akm')
            ->where('id_ipmhs', 'akm-one')->value('status_mahasiswa'));
    }

    public function test_akm_rejects_cross_program_calendar_and_non_editor_roles(): void
    {
        $this->actingAs($this->user('admin', 'admin-1'));
        $this->get(route('student-akm.index', ['program' => 'program-1', 'calendar' => 'calendar-other']))->assertNotFound();
        $this->actingAs($this->user('usertu', 'operator-tu', ['id_prodi' => 'program-1']));
        $this->get(route('student-akm.index', ['calendar' => 'calendar-current']))
            ->assertOk()->assertSee('Program Satu')->assertDontSee('Program Dua');
        $this->post(route('student-akm.generate'), [
            'program' => 'program-2', 'calendar' => 'calendar-other',
        ])->assertNotFound();
        $this->actingAs($this->user('dosen', 'lecturer-1'));
        $this->get(route('student-akm.index'))->assertForbidden();
    }

    public function test_program_roles_only_generate_and_list_students_from_their_master_program(): void
    {
        DB::connection('simak_main')->table('s1m4k_akm')->insert(
            $this->akmRow('akm-cross-program', 'student-2', 'program-1', 'calendar-current', 'A')
        );

        foreach (['userprodi', 'usertu'] as $role) {
            $this->actingAs($this->user($role, $role.'-operator', ['id_prodi' => 'program-1']));
            $this->get(route('student-akm.index', ['calendar' => 'calendar-current']))
                ->assertOk()
                ->assertDontSee('Mahasiswa Dua')
                ->assertSee('Generate');
            $this->post(route('student-akm.generate'), [
                'program' => 'program-2', 'calendar' => 'calendar-current',
            ])->assertRedirect(route('student-akm.index', [
                'program' => 'program-1', 'calendar' => 'calendar-current',
            ]));

            $this->assertSame(0, DB::connection('simak_main')->table('s1m4k_akm')
                ->where('id_mahasiswa', 'student-2')->count());
            $this->get(route('student-akm.index', ['calendar' => 'calendar-current']))
                ->assertOk()
                ->assertSee('Mahasiswa Satu')
                ->assertDontSee('Mahasiswa Dua');
        }
    }

    public function test_akm_status_can_be_manually_corrected_until_permanent_lock(): void
    {
        DB::connection('simak_main')->table('s1m4k_akm')->insert(
            $this->akmRow('akm-one', 'student-1', 'program-1', 'calendar-current', 'A')
        );
        $this->actingAs($this->user('userprodi', 'operator-1', ['id_prodi' => 'program-1']));
        $this->get(route('student-akm.index', ['calendar' => 'calendar-current']))
            ->assertOk()
            ->assertSee('data-autosave-select', false)
            ->assertSee('data-autosave-form', false)
            ->assertSee('Aktif');
    }

    public function test_akm_list_uses_legacy_status_filters_for_admin_and_program_roles(): void
    {
        DB::connection('simak_main')->table('s1m4k_mastermhs')
            ->where('id_mahasiswa', 'student-2')
            ->update(['id_prodi' => 'program-1', 'nama_status_mahasiswa' => 'Dikeluarkan']);
        DB::connection('simak_main')->table('s1m4k_mastermhs')
            ->where('id_mahasiswa', 'student-3')
            ->update(['nama_status_mahasiswa' => 'Lulus']);
        DB::connection('simak_main')->table('s1m4k_akm')->insert([
            $this->akmRow('akm-dismissed', 'student-2', 'program-1', 'calendar-current', 'N'),
            $this->akmRow('akm-graduated', 'student-3', 'program-1', 'calendar-current', 'G'),
        ]);

        $this->actingAs($this->user('admin', 'admin-1'));
        $this->get(route('student-akm.index', ['program' => 'program-1', 'calendar' => 'calendar-current']))
            ->assertOk()->assertSee('Mahasiswa Dua')->assertDontSee('Mahasiswa Tiga');

        $this->actingAs($this->user('userprodi', 'operator-1', ['id_prodi' => 'program-1']));
        $this->get(route('student-akm.index', ['calendar' => 'calendar-current']))
            ->assertOk()->assertDontSee('Mahasiswa Dua')->assertDontSee('Mahasiswa Tiga');

        $this->actingAs($this->user('usertu', 'operator-tu', ['id_prodi' => 'program-1']));
        $this->get(route('student-akm.index', ['calendar' => 'calendar-current']))
            ->assertOk()->assertDontSee('Mahasiswa Dua')->assertDontSee('Mahasiswa Tiga');
    }

    public function test_akm_records_are_paginated_in_datatables_style(): void
    {
        $students = [];
        $records = [];
        for ($index = 1; $index <= 30; $index++) {
            $suffix = str_pad((string) $index, 3, '0', STR_PAD_LEFT);
            $studentId = 'extra-student-'.$suffix;
            $students[] = [
                'id_mahasiswa' => $studentId,
                'id_prodi' => 'program-1',
                'nim' => '2026'.$suffix,
                'nama_mahasiswa' => 'Mahasiswa Tambahan '.$suffix,
                'id_periode_masuk' => '20251',
                'id_status_mahasiswa' => 'A',
                'nama_status_mahasiswa' => 'Aktif',
            ];
            $records[] = $this->akmRow('extra-akm-'.$suffix, $studentId, 'program-1', 'calendar-current', 'A');
        }
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert($students);
        DB::connection('simak_main')->table('s1m4k_akm')->insert($records);
        $this->actingAs($this->user('admin', 'admin-1'));

        $this->get(route('student-akm.index', ['program' => 'program-1', 'calendar' => 'calendar-current']))
            ->assertOk()
            ->assertSee('data-akm-pagination', false)
            ->assertSee('Selanjutnya')
            ->assertSee('Mahasiswa Tambahan 001')
            ->assertDontSee('Mahasiswa Tambahan 030');
        $this->get(route('student-akm.index', ['program' => 'program-1', 'calendar' => 'calendar-current', 'page' => 2]))
            ->assertOk()
            ->assertSee('Mahasiswa Tambahan 030')
            ->assertSee('Halaman 2');

        $response = $this->get(route('student-akm.export.excel', [
            'program' => 'program-1',
            'calendar' => 'calendar-current',
        ]))->assertOk();
        $spreadsheet = $this->spreadsheetFromResponse($response->getContent());
        $this->assertSame(31, $spreadsheet->getActiveSheet()->getHighestRow());
        $spreadsheet->disconnectWorksheets();
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
        Schema::connection('simak_main')->create('s1m4k_mastermhs', function (Blueprint $table): void {
            $table->string('id_mahasiswa')->primary();
            $table->string('id_prodi');
            $table->string('nim');
            $table->string('nama_mahasiswa');
            $table->string('id_periode_masuk')->nullable();
            $table->string('nama_status_mahasiswa')->nullable();
            $table->string('id_status_mahasiswa')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_mastermk', function (Blueprint $table): void {
            $table->string('id_matkul')->primary();
            $table->string('sks_mata_kuliah');
        });
        Schema::connection('simak_main')->create('s1m4k_krs', function (Blueprint $table): void {
            $table->string('id_krs')->primary();
            $table->string('id_prodi');
            $table->string('id_mahasiswa');
            $table->string('id_kalender');
            $table->string('id_matkul');
            $table->string('tahun');
            $table->string('krs_reguler');
            $table->string('nilai_bobot')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_registrasiulang', function (Blueprint $table): void {
            $table->string('id_registrasi')->primary();
            $table->string('id_mahasiswa');
            $table->string('id_kalender');
        });
        Schema::connection('simak_main')->create('s1m4k_akm', function (Blueprint $table): void {
            $table->string('id_ipmhs')->primary();
            $table->string('id_mahasiswa');
            $table->string('id_prodi');
            $table->string('id_kalender');
            $table->string('tahun');
            $table->string('ips')->nullable();
            $table->string('ipk')->nullable();
            $table->string('sks_semester')->nullable();
            $table->string('sks_program')->nullable();
            $table->string('sks_lulus')->nullable();
            $table->string('status_mahasiswa');
            $table->integer('update_akm')->default(0);
            $table->integer('simpan')->default(0);
            $table->integer('flag_feeder')->default(0);
        });
        Schema::connection('simak_main')->create('s1m4k_kodereferensi', function (Blueprint $table): void {
            $table->string('id_referensi')->primary();
            $table->string('kode_referensi');
            $table->string('ket_referensi');
            $table->string('keterangan');
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
            ['id_kalender' => 'calendar-current', 'id_prodi' => 'program-1', 'tahun' => '20261', 'namatahun' => '2026 Ganjil', 'status' => 'Y'],
            ['id_kalender' => 'calendar-other', 'id_prodi' => 'program-2', 'tahun' => '20261', 'namatahun' => '2026 Ganjil', 'status' => 'Y'],
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            ['id_mahasiswa' => 'student-1', 'id_prodi' => 'program-1', 'nim' => '2025001', 'nama_mahasiswa' => 'Mahasiswa Satu', 'id_periode_masuk' => '20251', 'id_status_mahasiswa' => 'A', 'nama_status_mahasiswa' => 'Aktif'],
            ['id_mahasiswa' => 'student-2', 'id_prodi' => 'program-2', 'nim' => '2025002', 'nama_mahasiswa' => 'Mahasiswa Dua', 'id_periode_masuk' => '20251', 'id_status_mahasiswa' => 'A', 'nama_status_mahasiswa' => 'Aktif'],
            ['id_mahasiswa' => 'student-3', 'id_prodi' => 'program-1', 'nim' => '2025003', 'nama_mahasiswa' => 'Mahasiswa Tiga', 'id_periode_masuk' => '20251', 'id_status_mahasiswa' => 'A', 'nama_status_mahasiswa' => 'Aktif'],
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermk')->insert([
            ['id_matkul' => 'course-1', 'sks_mata_kuliah' => '3'],
            ['id_matkul' => 'course-2', 'sks_mata_kuliah' => '3'],
        ]);
        DB::connection('simak_main')->table('s1m4k_krs')->insert([
            ['id_krs' => 'krs-current', 'id_prodi' => 'program-1', 'id_mahasiswa' => 'student-1', 'id_kalender' => 'calendar-current', 'id_matkul' => 'course-1', 'tahun' => '20261', 'krs_reguler' => 'Y', 'nilai_bobot' => '3.00'],
            ['id_krs' => 'krs-previous', 'id_prodi' => 'program-1', 'id_mahasiswa' => 'student-1', 'id_kalender' => 'calendar-current', 'id_matkul' => 'course-1', 'tahun' => '20252', 'krs_reguler' => 'Y', 'nilai_bobot' => '3.00'],
        ]);
        DB::connection('simak_main')->table('s1m4k_registrasiulang')->insert([
            ['id_registrasi' => 'registration-1', 'id_mahasiswa' => 'student-1', 'id_kalender' => 'calendar-current'],
        ]);
        DB::connection('simak_main')->table('s1m4k_kodereferensi')->insert([
            ['id_referensi' => 'status-a', 'kode_referensi' => 'A', 'ket_referensi' => 'Aktif', 'keterangan' => 'GetStatusMahasiswa'],
            ['id_referensi' => 'status-c', 'kode_referensi' => 'C', 'ket_referensi' => 'Cuti', 'keterangan' => 'GetStatusMahasiswa'],
            ['id_referensi' => 'status-m', 'kode_referensi' => 'M', 'ket_referensi' => 'Kampus Merdeka', 'keterangan' => 'GetStatusMahasiswa'],
            ['id_referensi' => 'status-n', 'kode_referensi' => 'N', 'ket_referensi' => 'Non-Aktif', 'keterangan' => 'GetStatusMahasiswa'],
        ]);
    }

    private function akmRow(string $id, string $student, string $program, string $calendar, string $status): array
    {
        return [
            'id_ipmhs' => $id, 'id_mahasiswa' => $student, 'id_prodi' => $program,
            'id_kalender' => $calendar, 'tahun' => '20261', 'ips' => '3.00',
            'ipk' => '3.00', 'sks_semester' => '3.00', 'sks_program' => '3.00',
            'sks_lulus' => '3.00', 'status_mahasiswa' => $status, 'simpan' => 0,
        ];
    }

    private function user(string $role, string $id, array $extra = []): GenericUser
    {
        return new GenericUser([
            'id' => $id, 'legacy_id' => $id, 'role' => $role,
            'login' => 'admin-login', 'name' => 'Pengguna Uji', ...$extra,
        ]);
    }

    private function spreadsheetFromResponse(string $contents): Spreadsheet
    {
        $path = tempnam(sys_get_temp_dir(), 'simak-akm-export-');
        file_put_contents($path, $contents);

        try {
            return IOFactory::load($path);
        } finally {
            unlink($path);
        }
    }
}
