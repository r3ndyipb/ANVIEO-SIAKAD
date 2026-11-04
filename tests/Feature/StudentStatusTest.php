<?php

namespace Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class StudentStatusTest extends TestCase
{
    private bool $rejectStatusWrite = false;

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
        Http::fake(function (ClientRequest $request) {
            $action = (string) ($request['act'] ?? '');
            if ($action === 'GetToken') {
                return Http::response(['error_code' => '0', 'data' => ['token' => 'fake-token']]);
            }
            if (in_array($action, ['GetListMahasiswa', 'GetListRiwayatPendidikanMahasiswa'], true)) {
                return Http::response(['error_code' => '0', 'data' => [
                    ['id_registrasi_mahasiswa' => 'registration-1'],
                ]]);
            }
            if ($this->rejectStatusWrite && in_array($action, ['InsertMahasiswaLulusDO', 'UpdateMahasiswaLulusDO'], true)) {
                return Http::response(['error_code' => '42', 'error_desc' => 'Uji gagal Feeder']);
            }
            if (in_array($action, ['InsertMahasiswaLulusDO', 'UpdateMahasiswaLulusDO'], true)) {
                return Http::response(['error_code' => '0', 'data' => [
                    'id_registrasi_mahasiswa' => 'registration-1',
                ]]);
            }

            return Http::response(['error_code' => '0', 'data' => []]);
        });
    }

    public function test_admin_and_program_staff_can_view_only_statuses_in_their_program_and_calendar(): void
    {
        DB::connection('simak_main')->table('s1m4k_akm')->insert([
            $this->statusRecord('record-1', 'student-1', 'program-1', 'calendar-1', '20261', '1'),
            $this->statusRecord('record-2', 'student-2', 'program-2', 'calendar-2', '20261', 'C'),
        ]);

        $this->actingAs($this->user('admin', 'admin-1'));
        $this->get(route('student-status.create'))
            ->assertOk()
            ->assertSee('Pilih program studi');
        $this->get(route('student-status.index', ['prodi' => 'program-1', 'tahun' => '20261']))
            ->assertOk()
            ->assertSee('Mahasiswa Satu')
            ->assertSee('Unduh Excel')
            ->assertSee('Lulus')
            ->assertDontSee('Mahasiswa Dua');

        $this->actingAs($this->user('userprodi', 'prodi-user', ['id_prodi' => 'program-1']));
        $this->get(route('student-status.create'))
            ->assertOk()
            ->assertSee('Pilih tahun akademik')
            ->assertDontSee('Mahasiswa aktif');
        $this->get(route('student-status.create', ['tahun' => '20261']))
            ->assertOk()
            ->assertSee('Mahasiswa aktif');
        $this->get(route('student-status.edit', 'record-1'))
            ->assertOk()
            ->assertSee('Ubah Data Lulus, Cuti, DO');
        $this->get(route('student-status.index', ['tahun' => '20261']))
            ->assertOk()
            ->assertSee('Mahasiswa Satu')
            ->assertDontSee('Mahasiswa Dua');
        $this->get(route('student-status.index', ['tahun' => '20241']))->assertNotFound();
        $this->get(route('student-status.edit', 'record-2'))->assertNotFound();
    }

    public function test_status_excel_export_contains_only_exit_records_in_the_selected_program_and_year(): void
    {
        DB::connection('simak_main')->table('s1m4k_akm')->insert([
            $this->statusRecord('record-export-one', 'student-1', 'program-1', 'calendar-1', '20261', '1'),
            $this->statusRecord('record-export-other', 'student-2', 'program-2', 'calendar-2', '20261', 'C'),
        ]);
        $this->actingAs($this->user('userprodi', 'prodi-user', ['id_prodi' => 'program-1']));
        $response = $this->get(route('student-status.export.excel', [
            'prodi' => 'program-2',
            'tahun' => '20261',
        ]))->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertHeader('Content-Disposition', 'attachment; filename="lulus-cuti-do-20261.xlsx"');
        $spreadsheet = $this->spreadsheetFromResponse($response->getContent());
        $sheet = $spreadsheet->getActiveSheet();
        $this->assertSame('NPM', $sheet->getCell('A1')->getValue());
        $this->assertSame('2025001', $sheet->getCell('A2')->getValue());
        $this->assertSame('Lulus', $sheet->getCell('D2')->getValue());
        $this->assertSame(2, $sheet->getHighestRow());
        $spreadsheet->disconnectWorksheets();
    }

    public function test_status_list_requires_a_program_and_year_and_reports_missing_calendars(): void
    {
        DB::connection('simak_main')->table('s1m4k_akm')->insert(
            $this->statusRecord('record-1', 'student-1', 'program-1', 'calendar-1', '20261', '1')
        );
        DB::connection('simak_main')->table('s1m4k_masterprodi')->insert([
            'id_prodi' => 'program-no-calendar',
            'nama_program_studi' => 'Program Tanpa Kalender',
            'nama_jenjang_pendidikan' => 'S1',
        ]);
        $this->actingAs($this->user('admin', 'admin-1'));

        $this->get(route('student-status.index', ['prodi' => 'program-1']))
            ->assertOk()
            ->assertSee('2026 Ganjil')
            ->assertDontSee('Tambah Data')
            ->assertDontSee('Mahasiswa Satu');
        $this->get(route('student-status.index', [
            'prodi' => 'program-1',
            'tahun' => '20261',
        ]))->assertOk()->assertSee('Tambah Data')->assertSee('Mahasiswa Satu');
        $this->get(route('student-status.index', ['prodi' => 'program-no-calendar']))
            ->assertOk()
            ->assertSee('Kalender akademik belum tersedia')
            ->assertDontSee('Tambah Data');
    }

    public function test_admin_must_select_a_calendar_year_before_entering_student_status(): void
    {
        $this->actingAs($this->user('admin', 'admin-1'));

        $this->get(route('student-status.create', ['prodi' => 'program-1']))
            ->assertOk()
            ->assertSee('Pilih tahun akademik')
            ->assertSee('2026 Ganjil')
            ->assertDontSee('Mahasiswa aktif');
        $this->get(route('student-status.create', [
            'prodi' => 'program-1',
            'tahun' => '20261',
        ]))
            ->assertOk()
            ->assertSee('Mahasiswa aktif')
            ->assertSee('value="calendar-1"', false)
            ->assertSee('2026 Ganjil');
    }

    public function test_program_staff_can_create_update_and_delete_status_with_audit_and_latest_master_status(): void
    {
        $this->actingAs($this->user('usertu', 'tu-user', ['id_prodi' => 'program-1']));
        $this->post(route('student-status.store'), [
            'id_mahasiswa' => 'student-1',
            'id_kalender' => 'calendar-1',
            'tahun' => '20261',
            'status_mahasiswa' => 'C',
            'tgl_keluar' => '2026-02-01',
            'id_prodi' => 'program-2',
            'ipk' => '3.25',
            'no_sk' => 'SK-001',
        ])
            ->assertRedirect(route('student-status.index', ['prodi' => 'program-1', 'tahun' => '20261']))
            ->assertSessionHas('status');

        $record = DB::connection('simak_main')->table('s1m4k_akm')->where('id_mahasiswa', 'student-1')->first();
        $this->assertNotNull($record);
        $this->assertSame('program-1', $record->id_prodi);
        $this->assertSame('20261', $record->tahun);
        $this->assertSame('C', $record->status_mahasiswa);
        $this->assertSame('C', DB::connection('simak_main')->table('s1m4k_mastermhs')
            ->where('id_mahasiswa', 'student-1')->value('id_status_mahasiswa'));

        $this->put(route('student-status.update', $record->id_ipmhs), [
            'id_mahasiswa' => 'student-1',
            'id_kalender' => 'calendar-1',
            'tahun' => '20261',
            'status_mahasiswa' => '1',
            'tgl_keluar' => '2026-03-01',
            'ipk' => '3.50',
            'no_sk' => 'SK-002',
            'tgl_sk' => '2026-03-01',
            'no_ijazah' => 'IJ-001',
            'ket' => 'Lulus',
        ])
            ->assertRedirect(route('student-status.index', ['prodi' => 'program-1', 'tahun' => '20261']))
            ->assertSessionHas('status');

        $actions = Http::recorded()->map(fn (array $requestResponse): string => (string) ($requestResponse[0]['act'] ?? ''))->all();
        $this->assertSame([
            'GetToken',
            'GetListMahasiswa',
            'InsertMahasiswaLulusDO',
            'GetToken',
            'GetListMahasiswa',
            'UpdateMahasiswaLulusDO',
        ], $actions);
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'UpdateMahasiswaLulusDO'
            && ($request['key'] ?? null) === 'registration-1');

        $this->assertSame('1', DB::connection('simak_main')->table('s1m4k_mastermhs')
            ->where('id_mahasiswa', 'student-1')->value('id_status_mahasiswa'));

        $this->delete(route('student-status.destroy', $record->id_ipmhs))
            ->assertRedirect(route('student-status.index', ['prodi' => 'program-1', 'tahun' => '20261']))
            ->assertSessionHas('status');
        $this->assertSame('A', DB::connection('simak_main')->table('s1m4k_mastermhs')
            ->where('id_mahasiswa', 'student-1')->value('id_status_mahasiswa'));

        $logs = DB::connection('simak_main')->table('log_aktivitas')
            ->where('modul', 'Lulus, Cuti, DO')->orderBy('created_at')->get();
        $this->assertCount(3, $logs);
        $this->assertStringContainsString('SK-001', $logs[0]->aktifitas);
        $this->assertStringContainsString('Mengubah status keluar mahasiswa', $logs[1]->aktifitas);
        $this->assertStringContainsString('Menghapus riwayat status keluar mahasiswa', $logs[2]->aktifitas);
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'GetListMahasiswa'
            && ($request['filter'] ?? '') === "id_mahasiswa='student-1'");
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'InsertMahasiswaLulusDO'
            && ($request['record']['id_jenis_keluar'] ?? null) === 'C'
            && ($request['record']['id_periode_keluar'] ?? null) === '20261'
            && ($request['record']['id_registrasi_mahasiswa'] ?? null) === 'registration-1');
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'GetListMahasiswa'
            && ($request['filter'] ?? '') === "id_mahasiswa='student-1'");
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'UpdateMahasiswaLulusDO'
            && ($request['key'] ?? null) === 'registration-1'
            && ($request['record']['id_registrasi_mahasiswa'] ?? null) === 'registration-1');
    }

    public function test_admin_status_write_uses_feeder_before_persisting_the_akm_record(): void
    {
        $this->actingAs($this->user('admin', 'admin-1'));

        $this->post(route('student-status.store'), [
            'id_prodi' => 'program-1',
            'id_mahasiswa' => 'student-1',
            'id_kalender' => 'calendar-1',
            'tahun' => '20261',
            'status_mahasiswa' => '1',
            'tgl_keluar' => '2026-03-01',
            'ipk' => '3.50',
            'no_sk' => 'SK-ADMIN',
        ])->assertRedirect(route('student-status.index', ['prodi' => 'program-1', 'tahun' => '20261']))
            ->assertSessionHas('status');

        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'InsertMahasiswaLulusDO'
            && ($request['record']['id_registrasi_mahasiswa'] ?? null) === 'registration-1'
            && ($request['record']['nomor_sk_yudisium'] ?? null) === 'SK-ADMIN');
        $this->assertDatabaseHas('s1m4k_akm', [
            'id_mahasiswa' => 'student-1',
            'status_mahasiswa' => '1',
        ], 'simak_main');

        $recordId = DB::connection('simak_main')->table('s1m4k_akm')->value('id_ipmhs');
        $this->put(route('student-status.update', $recordId), [
            'id_mahasiswa' => 'student-1',
            'id_kalender' => 'calendar-1',
            'tahun' => '20261',
            'status_mahasiswa' => '1',
            'tgl_keluar' => '2026-03-01',
            'ipk' => '3.50',
            'no_sk' => 'SK-ADMIN',
        ])->assertRedirect(route('student-status.index', ['prodi' => 'program-1', 'tahun' => '20261']));

        $actions = Http::recorded()->map(fn (array $requestResponse): string => (string) ($requestResponse[0]['act'] ?? ''))->all();
        $this->assertSame([
            'GetToken',
            'GetListMahasiswa',
            'InsertMahasiswaLulusDO',
            'GetToken',
            'GetListRiwayatPendidikanMahasiswa',
            'UpdateMahasiswaLulusDO',
        ], $actions);
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'UpdateMahasiswaLulusDO'
            && ($request['key'] ?? null) === 'registration-1');
    }

    public function test_feeder_failure_displays_its_error_and_does_not_write_akm_or_master_status(): void
    {
        $this->rejectStatusWrite = true;
        $this->actingAs($this->user('usertu', 'tu-user', ['id_prodi' => 'program-1']));

        $this->from(route('student-status.create', ['tahun' => '20261']))->post(route('student-status.store'), [
            'id_mahasiswa' => 'student-1',
            'id_kalender' => 'calendar-1',
            'tahun' => '20261',
            'status_mahasiswa' => 'C',
            'tgl_keluar' => '2026-02-01',
            'ipk' => '3.25',
        ])->assertRedirect(route('student-status.create', ['tahun' => '20261']))
            ->assertSessionHasErrors(['feeder' => 'Feeder gagal (42): Uji gagal Feeder']);

        $this->assertSame(0, DB::connection('simak_main')->table('s1m4k_akm')->count());
        $this->assertSame('A', DB::connection('simak_main')->table('s1m4k_mastermhs')
            ->where('id_mahasiswa', 'student-1')->value('id_status_mahasiswa'));
        $this->get(route('student-status.create', ['tahun' => '20261']))
            ->assertOk()
            ->assertSee('Feeder gagal (42): Uji gagal Feeder');
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'InsertMahasiswaLulusDO');
    }

    public function test_updating_an_older_record_preserves_latest_status_and_rejects_duplicate_calendar(): void
    {
        DB::connection('simak_main')->table('s1m4k_akm')->insert([
            $this->statusRecord('record-old', 'student-1', 'program-1', 'calendar-old', '20252', 'C'),
            $this->statusRecord('record-latest', 'student-1', 'program-1', 'calendar-1', '20261', '1'),
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermhs')
            ->where('id_mahasiswa', 'student-1')
            ->update(['id_status_mahasiswa' => '1', 'nama_status_mahasiswa' => 'Lulus']);

        $this->actingAs($this->user('userprodi', 'prodi-user', ['id_prodi' => 'program-1']));
        $this->put(route('student-status.update', 'record-old'), [
            'id_mahasiswa' => 'student-1',
            'id_kalender' => 'calendar-old',
            'tahun' => '20252',
            'status_mahasiswa' => '2',
            'tgl_keluar' => '2025-08-01',
        ])->assertRedirect(route('student-status.index', ['prodi' => 'program-1', 'tahun' => '20252']));

        $this->assertSame('1', DB::connection('simak_main')->table('s1m4k_mastermhs')
            ->where('id_mahasiswa', 'student-1')->value('id_status_mahasiswa'));

        $this->put(route('student-status.update', 'record-old'), [
            'id_mahasiswa' => 'student-1',
            'id_kalender' => 'calendar-1',
            'tahun' => '20261',
            'status_mahasiswa' => '2',
            'tgl_keluar' => '2026-04-01',
        ])->assertStatus(422);
    }

    public function test_roles_outside_legacy_dispatcher_cannot_access_student_status(): void
    {
        $this->actingAs($this->user('mahasiswa', 'student-1'));
        $this->get(route('student-status.index'))->assertForbidden();
        $this->get(route('student-status.export.excel', ['prodi' => 'program-1', 'tahun' => '20261']))->assertForbidden();
        $this->post(route('student-status.store'))->assertForbidden();
    }

    private function createSchema(): void
    {
        Schema::connection('simak_main')->create('s1m4k_masterpt', function (Blueprint $table): void {
            $table->string('id_perguruan_tinggi')->primary();
            $table->string('alamat_feeder')->nullable();
            $table->string('user_feeder')->nullable();
            $table->text('password_feeder')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterprodi', function (Blueprint $table): void {
            $table->string('id_prodi')->primary();
            $table->string('nama_program_studi');
            $table->string('nama_jenjang_pendidikan')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_kalender', function (Blueprint $table): void {
            $table->string('id_kalender')->primary();
            $table->string('id_prodi');
            $table->string('tahun', 5);
            $table->string('namatahun')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_mastermhs', function (Blueprint $table): void {
            $table->string('id_mahasiswa')->primary();
            $table->string('id_registrasi_mahasiswa')->nullable();
            $table->string('nim');
            $table->string('nama_mahasiswa');
            $table->string('id_prodi');
            $table->string('id_status_mahasiswa')->nullable();
            $table->string('nama_status_mahasiswa')->nullable();
            $table->string('flag_feeder')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_akm', function (Blueprint $table): void {
            $table->string('id_ipmhs')->primary();
            $table->string('id_prodi');
            $table->string('id_mahasiswa');
            $table->string('id_kalender');
            $table->string('tahun', 5);
            $table->string('status_mahasiswa');
            $table->string('no_ijazah')->nullable();
            $table->string('ipk')->nullable();
            $table->date('tgl_keluar')->nullable();
            $table->text('ket')->nullable();
            $table->string('no_sk')->nullable();
            $table->date('tgl_sk')->nullable();
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
        DB::connection('simak_main')->table('s1m4k_masterpt')->insert([
            'id_perguruan_tinggi' => 'pt-1',
            'alamat_feeder' => 'https://feeder.example.test',
            'user_feeder' => 'dummy-user',
            'password_feeder' => 'ZmFrZQ==',
        ]);
        DB::connection('simak_main')->table('s1m4k_masterprodi')->insert([
            ['id_prodi' => 'program-1', 'nama_program_studi' => 'Program Satu', 'nama_jenjang_pendidikan' => 'S1'],
            ['id_prodi' => 'program-2', 'nama_program_studi' => 'Program Dua', 'nama_jenjang_pendidikan' => 'S1'],
        ]);
        DB::connection('simak_main')->table('s1m4k_kalender')->insert([
            ['id_kalender' => 'calendar-1', 'id_prodi' => 'program-1', 'tahun' => '20261', 'namatahun' => '2026 Ganjil'],
            ['id_kalender' => 'calendar-old', 'id_prodi' => 'program-1', 'tahun' => '20252', 'namatahun' => '2025 Genap'],
            ['id_kalender' => 'calendar-2', 'id_prodi' => 'program-2', 'tahun' => '20261', 'namatahun' => '2026 Ganjil'],
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            ['id_mahasiswa' => 'student-1', 'id_registrasi_mahasiswa' => 'registration-1', 'nim' => '2025001', 'nama_mahasiswa' => 'Mahasiswa Satu', 'id_prodi' => 'program-1', 'id_status_mahasiswa' => 'A', 'nama_status_mahasiswa' => 'Aktif'],
            ['id_mahasiswa' => 'student-2', 'id_registrasi_mahasiswa' => 'registration-2', 'nim' => '2025999', 'nama_mahasiswa' => 'Mahasiswa Dua', 'id_prodi' => 'program-2', 'id_status_mahasiswa' => 'A', 'nama_status_mahasiswa' => 'Aktif'],
        ]);
    }

    private function statusRecord(string $id, string $student, string $program, string $calendar, string $year, string $status): array
    {
        return [
            'id_ipmhs' => $id,
            'id_prodi' => $program,
            'id_mahasiswa' => $student,
            'id_kalender' => $calendar,
            'tahun' => $year,
            'status_mahasiswa' => $status,
            'ipk' => '3.00',
            'tgl_keluar' => $year === '20261' ? '2026-01-01' : '2025-01-01',
        ];
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

    private function spreadsheetFromResponse(string $contents): Spreadsheet
    {
        $path = tempnam(sys_get_temp_dir(), 'simak-status-export-');
        file_put_contents($path, $contents);

        try {
            return IOFactory::load($path);
        } finally {
            unlink($path);
        }
    }
}
