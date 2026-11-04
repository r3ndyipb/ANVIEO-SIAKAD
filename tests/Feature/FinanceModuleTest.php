<?php

namespace Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class FinanceModuleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.simak_main' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
        ]]);
        DB::purge('simak_main');

        Schema::connection('simak_main')->create('s1m4k_periode', function (Blueprint $table): void {
            $table->string('id_periode')->primary();
            $table->string('nama_periode');
            $table->string('tahun')->nullable();
            $table->date('tanggal_mulai');
            $table->date('tanggal_akhir');
            $table->string('jenis');
            $table->integer('kuota_wisuda')->nullable();
            $table->string('aktif');
        });
        Schema::connection('simak_main')->create('s1m4k_kalender', function (Blueprint $table): void {
            $table->string('id_kalender')->primary();
            $table->string('tahun')->nullable();
            $table->string('namatahun')->nullable();
            $table->string('id_prodi')->nullable();
        });
        Schema::connection('simak_main')->create('log_aktivitas', function (Blueprint $table): void {
            $table->string('id_log')->primary();
            $table->string('id_user');
            $table->string('level_user');
            $table->string('nama_user');
            $table->string('modul');
            $table->text('aktifitas');
            $table->dateTime('created_at');
        });
    }

    public function test_finance_role_can_open_period_page_with_create_form_in_a_modal(): void
    {
        DB::connection('simak_main')->table('s1m4k_kalender')->insert([
            'id_kalender' => 'calendar-1',
            'tahun' => '20261',
            'namatahun' => '2026 Ganjil',
            'id_prodi' => 'program-1',
        ]);
        DB::connection('simak_main')->table('s1m4k_periode')->insert([
            'id_periode' => 'period-1',
            'nama_periode' => '2026 Ganjil',
            'tahun' => '20261',
            'tanggal_mulai' => '2026-01-01',
            'tanggal_akhir' => '2026-06-30',
            'jenis' => 'ukt',
            'aktif' => 'N',
        ]);

        $this->actingAs(new GenericUser([
            'id' => 'finance-1', 'legacy_id' => 'finance-1', 'role' => 'keuangan', 'nama_lengkap' => 'Petugas Keuangan',
        ]));

        $this->get(route('finance.periods.index'))
            ->assertOk()
            ->assertSee('2026 Ganjil')
            ->assertSee('option value="20261"', false)
            ->assertSee('finance-period-create', false)
            ->assertSee('data-dialog-open="finance-period-create"', false)
            ->assertSee('data-searchable-select data-tom-select-dropdown-parent="wrapper"', false)
            ->assertSee('2026 Ganjil (20261)');
    }

    public function test_period_create_form_explains_when_no_academic_years_exist(): void
    {
        $this->actingAs(new GenericUser([
            'id' => 'finance-1', 'legacy_id' => 'finance-1', 'role' => 'keuangan', 'nama_lengkap' => 'Petugas Keuangan',
        ]));

        $this->get(route('finance.periods.index'))
            ->assertOk()
            ->assertSee('Belum ada data tahun akademik')
            ->assertSee('Buat kalender akademik terlebih dahulu agar tahun akademik dapat dipilih.')
            ->assertSee('name="tahun" data-searchable-select data-tom-select-dropdown-parent="wrapper" required disabled', false)
            ->assertSee('type="submit" disabled', false);
    }

    public function test_ukt_generate_action_is_available_after_selecting_period_and_keeps_faculty_filter(): void
    {
        DB::connection('simak_main')->table('s1m4k_periode')->insert([
            'id_periode' => 'period-1',
            'nama_periode' => '2026 Ganjil',
            'tahun' => '20261',
            'tanggal_mulai' => '2026-01-01',
            'tanggal_akhir' => '2026-06-30',
            'jenis' => 'ukt',
            'aktif' => 'Y',
        ]);
        Schema::connection('simak_main')->create('s1m4k_masterfakultas', function (Blueprint $table): void {
            $table->string('id_fakultas')->primary();
            $table->string('nama_fakultas');
        });
        DB::connection('simak_main')->table('s1m4k_masterfakultas')->insert([
            ['id_fakultas' => 'faculty-1', 'nama_fakultas' => 'Fakultas Satu'],
            ['id_fakultas' => 'faculty-2', 'nama_fakultas' => 'Fakultas Dua'],
        ]);
        $this->actingAs(new GenericUser([
            'id' => 'finance-1', 'legacy_id' => 'finance-1', 'role' => 'keuangan', 'nama_lengkap' => 'Petugas Keuangan',
        ]));

        $this->get(route('finance.ukt.index', ['tahun' => '20261']))
            ->assertOk()
            ->assertSee('Generate tagihan semua fakultas')
            ->assertSee('name="tahun" value="20261"', false)
            ->assertSee('name="id_fakultas"', false)
            ->assertSee('Semua fakultas')
            ->assertSee('Fakultas Satu');

        $this->get(route('finance.ukt.index', ['tahun' => '20261', 'id_fakultas' => 'faculty-2']))
            ->assertOk()
            ->assertSee('Generate tagihan semua fakultas')
            ->assertSee('name="id_fakultas" value="faculty-2"', false);
    }

    public function test_ukt_generate_creates_bills_for_all_faculties_even_when_table_filter_is_selected(): void
    {
        DB::connection('simak_main')->table('s1m4k_periode')->insert([
            'id_periode' => 'period-1',
            'nama_periode' => '2026 Ganjil',
            'tahun' => '20261',
            'tanggal_mulai' => '2026-01-01',
            'tanggal_akhir' => '2026-06-30',
            'jenis' => 'ukt',
            'aktif' => 'Y',
        ]);
        Schema::connection('simak_main')->create('s1m4k_mastermhs', function (Blueprint $table): void {
            $table->string('id_mahasiswa')->primary();
            $table->string('id_prodi');
            $table->string('id_status_mahasiswa');
            $table->string('kategori_ukt')->nullable();
            $table->integer('nominal_ukt')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterprodi', function (Blueprint $table): void {
            $table->string('id_prodi')->primary();
            $table->string('id_fakultas');
        });
        Schema::connection('simak_main')->create('s1m4k_pembayaranukt', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('id_mahasiswa');
            $table->string('tahun');
            $table->integer('nominal');
            $table->integer('lunas');
            $table->string('petugas')->nullable();
        });
        DB::connection('simak_main')->table('s1m4k_masterprodi')->insert([
            ['id_prodi' => 'program-1', 'id_fakultas' => 'faculty-1'],
            ['id_prodi' => 'program-2', 'id_fakultas' => 'faculty-2'],
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            ['id_mahasiswa' => 'student-1', 'id_prodi' => 'program-1', 'id_status_mahasiswa' => 'A', 'kategori_ukt' => '1', 'nominal_ukt' => 1000000],
            ['id_mahasiswa' => 'student-2', 'id_prodi' => 'program-2', 'id_status_mahasiswa' => 'A', 'kategori_ukt' => '2', 'nominal_ukt' => 2000000],
        ]);
        $this->actingAs(new GenericUser([
            'id' => 'finance-1', 'legacy_id' => 'finance-1', 'role' => 'keuangan', 'nama_lengkap' => 'Petugas Keuangan',
        ]));

        $this->post(route('finance.ukt.generate'), [
            'tahun' => '20261',
            'id_fakultas' => 'faculty-1',
        ])->assertRedirect(route('finance.ukt.index', ['tahun' => '20261', 'id_fakultas' => 'faculty-1']))
            ->assertSessionHas('status', 'Generate selesai: 2 tagihan dibuat, 0 diperbarui, 0 dilewati.');

        $this->assertDatabaseCount('s1m4k_pembayaranukt', 2, 'simak_main');
        $this->assertDatabaseHas('s1m4k_pembayaranukt', [
            'id_mahasiswa' => 'student-1', 'tahun' => '20261', 'nominal' => 1000000,
        ], 'simak_main');
        $this->assertDatabaseHas('s1m4k_pembayaranukt', [
            'id_mahasiswa' => 'student-2', 'tahun' => '20261', 'nominal' => 2000000,
        ], 'simak_main');
    }

    public function test_finance_can_filter_all_midtrans_transactions_by_date_and_search(): void
    {
        Schema::connection('simak_main')->create('s1m4k_mastermhs', function (Blueprint $table): void {
            $table->string('id_mahasiswa')->primary();
            $table->string('nim')->nullable();
            $table->string('nama_mahasiswa')->nullable();
        });
        foreach (['ukt' => 's1m4k_pembayaranukt', 'ipi' => 's1m4k_pembayaranipi'] as $tableName) {
            Schema::connection('simak_main')->create($tableName, function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->string('id_mahasiswa');
                $table->integer('lunas')->default(0);
            });
        }
        Schema::connection('simak_main')->create('simak_payment_transactions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('gateway');
            $table->string('payment_type');
            $table->string('payment_id');
            $table->string('student_id');
            $table->string('order_id');
            $table->integer('gross_amount');
            $table->string('transaction_status');
            $table->string('transaction_id')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            'id_mahasiswa' => 'student-1',
            'nim' => '01012011150',
            'nama_mahasiswa' => 'Siti Khairun',
        ]);
        DB::connection('simak_main')->table('s1m4k_pembayaranukt')->insert([
            'id' => 'ukt-1',
            'id_mahasiswa' => 'student-1',
            'lunas' => 0,
        ]);
        DB::connection('simak_main')->table('s1m4k_pembayaranipi')->insert([
            'id' => 'ipi-1',
            'id_mahasiswa' => 'student-1',
            'lunas' => 1,
        ]);
        DB::connection('simak_main')->table('simak_payment_transactions')->insert([
            [
                'id' => 'midtrans-sandbox-record',
                'gateway' => 'midtrans',
                'payment_type' => 'ukt',
                'payment_id' => 'ukt-1',
                'student_id' => 'student-1',
                'order_id' => 'SIAKAD-SANDBOX-1',
                'gross_amount' => 1003000,
                'transaction_status' => 'pending',
                'transaction_id' => null,
                'created_at' => today()->subDays(2)->toDateString().' 09:00:00',
            ],
            [
                'id' => 'midtrans-production-record',
                'gateway' => 'midtrans',
                'payment_type' => 'ipi',
                'payment_id' => 'ipi-1',
                'student_id' => 'student-1',
                'order_id' => 'SIAKAD-PRODUCTION-1',
                'gross_amount' => 2000000,
                'transaction_status' => 'settlement',
                'transaction_id' => 'midtrans-id-1',
                'created_at' => today()->toDateTimeString(),
            ],
            [
                'id' => 'midtrans-outside-range',
                'gateway' => 'midtrans',
                'payment_type' => 'ukt',
                'payment_id' => 'ukt-2',
                'student_id' => 'student-1',
                'order_id' => 'SIAKAD-OLD-1',
                'gross_amount' => 1000000,
                'transaction_status' => 'expire',
                'transaction_id' => null,
                'created_at' => today()->subDays(4)->toDateTimeString(),
            ],
            [
                'id' => 'other-gateway-record',
                'gateway' => 'other-gateway',
                'payment_type' => 'ukt',
                'payment_id' => 'ukt-3',
                'student_id' => 'student-1',
                'order_id' => 'OTHER-GATEWAY-1',
                'gross_amount' => 1000000,
                'transaction_status' => 'settlement',
                'transaction_id' => null,
                'created_at' => today()->toDateTimeString(),
            ],
        ]);
        $this->actingAs(new GenericUser([
            'id' => 'finance-1', 'legacy_id' => 'finance-1', 'role' => 'keuangan', 'nama_lengkap' => 'Petugas Keuangan',
        ]));

        $this->get(route('finance.midtrans-transactions.index', [
            'tanggal_mulai' => today()->subDays(2)->toDateString(),
            'tanggal_akhir' => today()->toDateString(),
        ]))
            ->assertOk()
            ->assertSee('SIAKAD-SANDBOX-1')
            ->assertSee('SIAKAD-PRODUCTION-1')
            ->assertSee('Siti Khairun')
            ->assertSee('data-payment-status="unpaid"', false)
            ->assertSee('data-payment-status="paid"', false)
            ->assertSee('Belum lunas')
            ->assertSee('Lunas')
            ->assertDontSee('SIAKAD-OLD-1')
            ->assertDontSee('OTHER-GATEWAY-1');

        $this->get(route('finance.midtrans-transactions.index', [
            'tanggal_mulai' => today()->subDays(2)->toDateString(),
            'tanggal_akhir' => today()->toDateString(),
            'cari' => 'midtrans-id-1',
        ]))
            ->assertOk()
            ->assertSee('SIAKAD-PRODUCTION-1')
            ->assertDontSee('SIAKAD-SANDBOX-1');

        $this->get(route('finance.midtrans-transactions.index', [
            'tanggal_mulai' => today()->toDateString(),
            'tanggal_akhir' => today()->subDay()->toDateString(),
        ]))->assertSessionHasErrors('tanggal_akhir');

        $this->actingAs(new GenericUser(['id' => 'program-user', 'role' => 'userprodi']));
        $this->get(route('finance.midtrans-transactions.index'))->assertForbidden();
    }

    public function test_nonfinance_role_cannot_open_finance_routes(): void
    {
        $this->actingAs(new GenericUser([
            'id' => 'program-user', 'role' => 'userprodi', 'nama_lengkap' => 'Operator Prodi',
        ]));

        $this->get(route('finance.periods.index'))->assertForbidden();
        $this->get(route('finance.categories.index'))->assertForbidden();
        $this->get(route('finance.ukt.index'))->assertForbidden();
        $this->get(route('finance.ipi.index'))->assertForbidden();
        $this->get(route('finance.ipi.template'))->assertForbidden();
    }

    public function test_payment_uses_only_local_database_without_external_pmb_or_billing_connections(): void
    {
        $connections = config('database.connections');
        $this->assertArrayNotHasKey('finance_pmb', $connections);
        $this->assertArrayNotHasKey('finance_ebilling', $connections);
        $this->assertFalse(Route::has('finance.new-students.index'));
        $this->assertFalse(Route::has('finance.new-students.export'));
        $this->assertFalse(Route::has('finance.ipi.sync'));
        $this->assertTrue(Route::has('finance.payments.manual-paid'));
        $this->assertFalse(Route::has('finance.ukt.paid'));
        $this->assertFalse(Route::has('finance.ipi.paid'));
    }

    public function test_category_datatable_searches_students_server_side(): void
    {
        Schema::connection('simak_main')->create('s1m4k_mastermhs', function (Blueprint $table): void {
            $table->string('id_mahasiswa')->primary();
            $table->string('nim');
            $table->string('nama_mahasiswa');
            $table->string('id_status_mahasiswa');
            $table->string('nama_status_mahasiswa')->nullable();
            $table->string('id_prodi')->nullable();
            $table->string('kategori_ukt')->nullable();
            $table->integer('nominal_ukt')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterprodi', function (Blueprint $table): void {
            $table->string('id_prodi')->primary();
            $table->string('nama_program_studi')->nullable();
            $table->string('nama_jenjang_pendidikan')->nullable();
        });
        DB::connection('simak_main')->table('s1m4k_masterprodi')->insert([
            'id_prodi' => 'program-1',
            'nama_program_studi' => 'Informatika',
            'nama_jenjang_pendidikan' => 'S1',
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            ['id_mahasiswa' => 'student-1', 'nim' => '01012011150', 'nama_mahasiswa' => 'Siti Mencari', 'id_status_mahasiswa' => 'A', 'nama_status_mahasiswa' => 'Aktif', 'id_prodi' => 'program-1', 'kategori_ukt' => '1', 'nominal_ukt' => 1000000],
            ['id_mahasiswa' => 'student-2', 'nim' => '1002', 'nama_mahasiswa' => 'Nama Lain', 'id_status_mahasiswa' => 'A', 'nama_status_mahasiswa' => 'Aktif', 'id_prodi' => 'program-1', 'kategori_ukt' => '2', 'nominal_ukt' => 2000000],
        ]);
        $this->actingAs(new GenericUser(['id' => 'admin-1', 'role' => 'admin']));

        $this->getJson(route('finance.categories.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'search' => ['value' => 'Mencari'],
        ]))
            ->assertOk()
            ->assertJsonPath('recordsTotal', 2)
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.nim', '01012011150')
            ->assertJsonPath('data.0.angkatan', '2020');
    }

    public function test_ukt_category_list_includes_only_active_leave_inactive_and_mbkm_statuses(): void
    {
        Schema::connection('simak_main')->create('s1m4k_mastermhs', function (Blueprint $table): void {
            $table->string('id_mahasiswa')->primary();
            $table->string('nim');
            $table->string('nama_mahasiswa');
            $table->string('id_status_mahasiswa');
            $table->string('nama_status_mahasiswa')->nullable();
            $table->string('id_prodi')->nullable();
            $table->string('kategori_ukt')->nullable();
            $table->integer('nominal_ukt')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterprodi', function (Blueprint $table): void {
            $table->string('id_prodi')->primary();
            $table->string('nama_program_studi')->nullable();
            $table->string('nama_jenjang_pendidikan')->nullable();
        });
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            ['id_mahasiswa' => 'student-a', 'nim' => '1001', 'nama_mahasiswa' => 'Aktif', 'id_status_mahasiswa' => 'A', 'nama_status_mahasiswa' => 'Lainnya'],
            ['id_mahasiswa' => 'student-c', 'nim' => '1002', 'nama_mahasiswa' => 'Cuti', 'id_status_mahasiswa' => 'C', 'nama_status_mahasiswa' => 'Status lama'],
            ['id_mahasiswa' => 'student-n', 'nim' => '1003', 'nama_mahasiswa' => 'Non Aktif', 'id_status_mahasiswa' => 'N', 'nama_status_mahasiswa' => 'Lainnya'],
            ['id_mahasiswa' => 'student-m', 'nim' => '1004', 'nama_mahasiswa' => 'MBKM', 'id_status_mahasiswa' => 'M', 'nama_status_mahasiswa' => 'Status lama'],
            ['id_mahasiswa' => 'student-g', 'nim' => '1005', 'nama_mahasiswa' => 'Lulus', 'id_status_mahasiswa' => 'G', 'nama_status_mahasiswa' => 'Lulus'],
            ['id_mahasiswa' => 'student-z', 'nim' => '1006', 'nama_mahasiswa' => 'Status lainnya', 'id_status_mahasiswa' => 'Z', 'nama_status_mahasiswa' => 'Lainnya'],
        ]);
        $this->actingAs(new GenericUser(['id' => 'admin-1', 'role' => 'admin']));

        $response = $this->getJson(route('finance.categories.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
        ]))->assertOk()
            ->assertJsonPath('recordsTotal', 4)
            ->assertJsonPath('recordsFiltered', 4)
            ->assertJsonCount(4, 'data');

        $statuses = collect($response->json('data'))->pluck('nama_status_mahasiswa')->all();
        sort($statuses);
        $this->assertSame(['Aktif', 'Cuti', 'MBKM', 'Non Aktif'], $statuses);
    }

    public function test_ukt_category_upload_uses_dropzone_and_downloadable_excel_template(): void
    {
        Schema::connection('simak_main')->create('s1m4k_masterprodi', function (Blueprint $table): void {
            $table->string('id_prodi')->primary();
            $table->string('nama_program_studi')->nullable();
            $table->string('nama_jenjang_pendidikan')->nullable();
        });
        $this->actingAs(new GenericUser([
            'id' => 'finance-1', 'role' => 'keuangan', 'nama_lengkap' => 'Petugas Keuangan',
        ]));

        $this->get(route('finance.categories.index'))
            ->assertOk()
            ->assertSee('data-finance-dropzone', false)
            ->assertSee(route('finance.categories.template'))
            ->assertSee('NPM pada kolom B');

        $template = $this->get(route('finance.categories.template'))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=template-kategori-uang-kuliah.xlsx');
        $templatePath = tempnam(sys_get_temp_dir(), 'simak-ukt-template-');
        self::assertNotFalse($templatePath);
        try {
            file_put_contents($templatePath, $template->streamedContent());
            $workbook = IOFactory::load($templatePath);
            self::assertSame('Uang Kuliah', $workbook->getSheet(0)->getTitle());
            self::assertSame(['No', 'NPM', 'Kategori Uang Kuliah', 'Nominal Uang Kuliah'], $workbook->getSheet(0)->rangeToArray('A1:D1')[0]);
            self::assertSame('@', $workbook->getSheet(0)->getStyle('B2')->getNumberFormat()->getFormatCode());
            self::assertSame('Petunjuk', $workbook->getSheet(1)->getTitle());
        } finally {
            unlink($templatePath);
        }
    }

    public function test_payment_datatable_searches_nim_and_name_server_side(): void
    {
        Schema::connection('simak_main')->create('s1m4k_mastermhs', function (Blueprint $table): void {
            $table->string('id_mahasiswa')->primary();
            $table->string('nim');
            $table->string('nama_mahasiswa');
            $table->string('kategori_ukt')->nullable();
            $table->string('id_prodi')->nullable();
            $table->string('id_fakultas')->nullable();
            $table->string('id_perguruan_tinggi')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterprodi', function (Blueprint $table): void {
            $table->string('id_prodi')->primary();
            $table->string('id_fakultas')->nullable();
            $table->string('nama_program_studi')->nullable();
            $table->string('nama_jenjang_pendidikan')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterfakultas', function (Blueprint $table): void {
            $table->string('id_fakultas')->primary();
            $table->string('nama_fakultas')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_pembayaranipi', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('id_mahasiswa');
            $table->string('nama_bank')->nullable();
            $table->string('trx_id')->nullable();
            $table->string('no_va')->nullable();
            $table->integer('nominal');
            $table->string('tgl_expire')->nullable();
            $table->integer('lunas');
            $table->string('tanggal_bayar')->nullable();
            $table->string('petugas')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_pembayaranukt', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('id_mahasiswa');
            $table->string('tahun');
            $table->string('nama_bank')->nullable();
            $table->string('trx_id')->nullable();
            $table->string('no_va')->nullable();
            $table->integer('nominal');
            $table->string('tgl_expire')->nullable();
            $table->integer('lunas');
            $table->string('tanggal_bayar')->nullable();
            $table->string('petugas')->nullable();
        });
        Schema::connection('simak_main')->create('simak_payment_transactions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('gateway');
            $table->string('payment_type');
            $table->string('payment_id');
            $table->string('student_id');
            $table->string('order_id');
            $table->integer('gross_amount');
            $table->integer('service_fee')->default(0);
            $table->string('transaction_status');
            $table->string('transaction_id')->nullable();
            $table->string('redirect_url')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });
        DB::connection('simak_main')->table('s1m4k_masterfakultas')->insert([
            'id_fakultas' => 'faculty-1',
            'nama_fakultas' => 'Fakultas Satu',
        ]);
        DB::connection('simak_main')->table('s1m4k_masterprodi')->insert([
            'id_prodi' => 'program-1',
            'id_fakultas' => null,
            'nama_program_studi' => 'Informatika',
            'nama_jenjang_pendidikan' => 'S1',
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            ['id_mahasiswa' => 'student-1', 'nim' => '01012011150', 'nama_mahasiswa' => 'Siti Khairun', 'kategori_ukt' => '1', 'id_prodi' => 'program-1', 'id_fakultas' => 'faculty-1'],
            ['id_mahasiswa' => 'student-2', 'nim' => '2002', 'nama_mahasiswa' => 'Nama Lain', 'kategori_ukt' => '2', 'id_prodi' => 'program-1', 'id_fakultas' => 'faculty-1'],
        ]);
        DB::connection('simak_main')->table('s1m4k_pembayaranipi')->insert([
            ['id' => 'ipi-1', 'id_mahasiswa' => 'student-1', 'nominal' => 7000000, 'lunas' => 0, 'tanggal_bayar' => null],
            ['id' => 'ipi-2', 'id_mahasiswa' => 'student-2', 'nominal' => 8000000, 'lunas' => 1, 'tanggal_bayar' => '2026-10-20 12:30:00'],
        ]);
        DB::connection('simak_main')->table('s1m4k_pembayaranukt')->insert([
            ['id' => 'ukt-1', 'id_mahasiswa' => 'student-1', 'tahun' => '20261', 'nominal' => 7000000, 'lunas' => 0, 'tanggal_bayar' => null],
            ['id' => 'ukt-2', 'id_mahasiswa' => 'student-2', 'tahun' => '20261', 'nominal' => 8000000, 'lunas' => 1, 'tanggal_bayar' => '2026-10-21 10:15:00'],
        ]);
        DB::connection('simak_main')->table('simak_payment_transactions')->insert([
            ['id' => 'transaction-ipi-1', 'gateway' => 'midtrans', 'payment_type' => 'ipi', 'payment_id' => 'ipi-1', 'student_id' => 'student-1', 'order_id' => 'SIMAK-IPI-pending', 'gross_amount' => 7000000, 'service_fee' => 0, 'transaction_status' => 'pending', 'transaction_id' => null, 'created_at' => now()],
            ['id' => 'transaction-ipi-2', 'gateway' => 'midtrans', 'payment_type' => 'ipi', 'payment_id' => 'ipi-2', 'student_id' => 'student-2', 'order_id' => 'SIMAK-IPI-settled', 'gross_amount' => 8003000, 'service_fee' => 3000, 'transaction_status' => 'settlement', 'transaction_id' => 'midtrans-ipi-2', 'created_at' => now()],
            ['id' => 'transaction-ukt-2', 'gateway' => 'midtrans', 'payment_type' => 'ukt', 'payment_id' => 'ukt-2', 'student_id' => 'student-2', 'order_id' => 'SIMAK-UKT-settled', 'gross_amount' => 8000000, 'service_fee' => 0, 'transaction_status' => 'settlement', 'transaction_id' => 'midtrans-ukt-2', 'created_at' => now()],
        ]);
        $this->actingAs(new GenericUser(['id' => 'admin-1', 'role' => 'admin']));

        $response = $this->getJson(route('finance.ipi.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'search' => ['value' => 'Khairun'],
        ]))
            ->assertOk()
            ->assertJsonPath('recordsTotal', 2)
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.nim', '01012011150')
            ->assertJsonPath('data.0.angkatan', '2020')
            ->assertJsonPath('data.0.nama_mahasiswa', 'Siti Khairun')
            ->assertJsonPath('data.0.nama_fakultas', 'Fakultas Satu')
            ->assertJsonPath('data.0.nama_program_studi', 'Informatika')
            ->assertJsonPath('data.0.nama_jenjang_pendidikan', 'S1')
            ->assertJsonPath('data.0.tanggal_bayar', null)
            ->assertJsonPath('data.0.midtrans_order_id', 'SIMAK-IPI-pending')
            ->assertJsonPath('data.0.midtrans_status', 'pending')
            ->assertJsonPath('data.0.service_fee', 0)
            ->assertJsonPath('data.0.total_amount', 7000000)
            ->assertJsonPath('data.0.actions', '');
        $this->getJson(route('finance.ipi.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
        ]))
            ->assertOk()
            ->assertJsonPath('data.1.lunas', 1)
            ->assertJsonPath('data.1.service_fee', 3000)
            ->assertJsonPath('data.1.total_amount', 8003000)
            ->assertJsonPath('data.1.tanggal_bayar', '2026-10-20 12:30:00');

        $this->actingAs(new GenericUser([
            'id' => 'finance-1', 'legacy_id' => 'finance-1', 'role' => 'keuangan', 'nama_lengkap' => 'Petugas Keuangan',
        ]));
        $paymentActions = $this->getJson(route('finance.ipi.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'search' => ['value' => 'Khairun'],
        ]))->json('data.0.actions');
        $this->assertStringNotContainsString('data-lucide="printer"', $paymentActions);
        $this->assertStringContainsString(route('finance.payments.midtrans-status', ['type' => 'ipi', 'payment' => 'ipi-1']), $paymentActions);
        $this->assertStringNotContainsString('data-finance-manual-paid', $paymentActions);
        $this->get(route('finance.payments.billing', ['type' => 'ipi', 'payment' => 'ipi-1']))
            ->assertNotFound();

        $paidPaymentActions = $this->getJson(route('finance.ipi.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
        ]))->json('data.1.actions');
        $this->assertStringContainsString(route('finance.payments.billing', ['type' => 'ipi', 'payment' => 'ipi-2']), $paidPaymentActions);
        $this->get(route('finance.payments.billing', ['type' => 'ipi', 'payment' => 'ipi-2']))
            ->assertOk()
            ->assertSee('Bukti Pelunasan')
            ->assertSee('Biaya layanan Midtrans')
            ->assertSee('Rp 3.000')
            ->assertSee('Rp 8.003.000')
            ->assertSee('Iuran Pengembangan Institusi')
            ->assertSee('Midtrans')
            ->assertSee('SIAKAD-IPI-settled')
            ->assertDontSee('SIMAK-IPI-settled')
            ->assertSee('midtrans-ipi-2')
            ->assertSee('LUNAS')
            ->assertSee('2026-10-20 12:30:00')
            ->assertDontSee('Nomor Virtual Account')
            ->assertDontSee('images/payments/btn.png')
            ->assertDontSee('009876543210987654');

        $this->post(route('finance.payments.manual-paid', ['type' => 'ipi', 'payment' => 'ipi-1']), [
            'method' => 'beasiswa',
            'reference' => 'SK-BEASISWA-2026-001',
        ])->assertSessionHasErrors('payment');
        $this->assertSame(0, (int) DB::connection('simak_main')->table('s1m4k_pembayaranipi')
            ->where('id', 'ipi-1')->value('lunas'));

        $this->getJson(route('finance.ukt.data', [
            'tahun' => '20261',
            'id_fakultas' => 'faculty-1',
            'draw' => 1,
            'start' => 0,
            'length' => 25,
        ]))
            ->assertOk()
            ->assertJsonPath('recordsTotal', 2)
            ->assertJsonPath('data.0.nama_fakultas', 'Fakultas Satu')
            ->assertJsonPath('data.0.nama_program_studi', 'Informatika')
            ->assertJsonPath('data.0.nama_jenjang_pendidikan', 'S1');

        $this->get(route('finance.ipi.export', ['lunas' => 0]))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=pembayaran-uang-pembangunan-belum-lunas.xlsx');

        $assertWorkbook = function (string $content, string $dateColumn, string $expectedDate): void {
            $path = tempnam(sys_get_temp_dir(), 'simak-finance-');
            self::assertNotFalse($path);
            try {
                file_put_contents($path, $content);
                $sheet = IOFactory::load($path)->getActiveSheet();
                self::assertSame('Tanggal bayar', $sheet->getCell($dateColumn.'1')->getValue());
                self::assertSame($expectedDate, $sheet->getCell($dateColumn.'2')->getValue());
            } finally {
                unlink($path);
            }
        };

        $ipiExport = $this->get(route('finance.ipi.export', ['lunas' => 1]))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=pembayaran-uang-pembangunan-lunas.xlsx');
        $assertWorkbook($ipiExport->streamedContent(), 'L', '2026-10-20 12:30:00');
        $assertOrderId = function (string $content, string $column, string $expected): void {
            $path = tempnam(sys_get_temp_dir(), 'simak-order-export-');
            self::assertNotFalse($path);
            try {
                file_put_contents($path, $content);
                $cell = IOFactory::load($path)->getActiveSheet()->getCell($column.'2');
                self::assertSame($expected, (string) $cell->getValue());
                self::assertSame(DataType::TYPE_STRING, $cell->getDataType());
            } finally {
                unlink($path);
            }
        };
        $assertOrderId($ipiExport->streamedContent(), 'F', 'SIMAK-IPI-settled');

        $uktExport = $this->get(route('finance.ukt.export', ['tahun' => '20261', 'lunas' => 1]))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=pembayaran-uang-kuliah-lunas-20261.xlsx');
        $assertWorkbook($uktExport->streamedContent(), 'N', '2026-10-21 10:15:00');
        $assertOrderId($uktExport->streamedContent(), 'H', 'SIMAK-UKT-settled');

        $manualActions = $this->getJson(route('finance.ukt.data', [
            'tahun' => '20261',
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'search' => ['value' => 'Siti Khairun'],
        ]))->assertOk()->json('data.0.actions');
        $this->assertStringContainsString('data-finance-manual-paid', $manualActions);
        $this->assertStringContainsString(
            route('finance.payments.manual-paid', ['type' => 'ukt', 'payment' => 'ukt-1']),
            $manualActions
        );

        $this->post(route('finance.payments.manual-paid', ['type' => 'ukt', 'payment' => 'ukt-1']), [
            'method' => 'pemda',
            'reference' => 'SP2D-2026-001',
        ])->assertRedirect(route('finance.ukt.index'));
        $manualUkt = DB::connection('simak_main')->table('s1m4k_pembayaranukt')
            ->where('id', 'ukt-1')->first();
        $this->assertSame(1, (int) $manualUkt->lunas);
        $this->assertSame('PEMDA', $manualUkt->nama_bank);
        $this->assertSame('SP2D-2026-001', $manualUkt->trx_id);
        $this->assertSame('finance-1', $manualUkt->petugas);
        $this->get(route('finance.payments.billing', ['type' => 'ukt', 'payment' => 'ukt-1']))
            ->assertOk()
            ->assertSee('Bantuan pemerintah daerah')
            ->assertSee('SP2D-2026-001')
            ->assertDontSee('images/payments/midtrans.svg');
        $this->assertTrue(DB::connection('simak_main')->table('log_aktivitas')
            ->where('aktifitas', 'like', '%Mencatat pembayaran manual ukt%')->exists());
    }

    public function test_admin_can_upload_ipi_with_npm_without_name_column(): void
    {
        Schema::connection('simak_main')->create('s1m4k_mastermhs', function (Blueprint $table): void {
            $table->string('id_mahasiswa')->primary();
            $table->string('nim');
            $table->string('nama_mahasiswa')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_pembayaranipi', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('id_mahasiswa');
            $table->integer('nominal');
            $table->integer('lunas');
        });
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            'id_mahasiswa' => 'student-1',
            'nim' => '3001',
            'nama_mahasiswa' => 'Nama dari Master SIMAK',
        ]);
        $this->actingAs(new GenericUser([
            'id' => 'finance-1',
            'role' => 'admin',
            'legacy_id' => 'finance-1',
            'nama_lengkap' => 'Administrator',
        ]));

        Schema::connection('simak_main')->create('s1m4k_masterfakultas', function (Blueprint $table): void {
            $table->string('id_fakultas')->primary();
            $table->string('nama_fakultas')->nullable();
        });

        $this->get(route('finance.ipi.index'))
            ->assertOk()
            ->assertSee('Iuran Pengembangan Institusi')
            ->assertSee('Upload tagihan Iuran Pengembangan Institusi')
            ->assertDontSee('Uang Pembangunan');

        $this->get(route('finance.ipi.index'))
            ->assertSee(route('finance.ipi.template'))
            ->assertSee('data-finance-dropzone', false)
            ->assertSee('data-finance-file-name', false);

        $template = $this->get(route('finance.ipi.template'))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=template-iuran-pengembangan-institusi.xlsx');
        $templatePath = tempnam(sys_get_temp_dir(), 'simak-ipi-template-');
        self::assertNotFalse($templatePath);
        try {
            file_put_contents($templatePath, $template->streamedContent());
            $workbook = IOFactory::load($templatePath);
            self::assertSame('Iuran Pengembangan Institusi', $workbook->getSheet(0)->getTitle());
            self::assertSame(
                ['No', 'NPM', 'Nominal Iuran Pengembangan Institusi'],
                $workbook->getSheet(0)->rangeToArray('A1:C1')[0],
            );
            self::assertSame('@', $workbook->getSheet(0)->getStyle('B2')->getNumberFormat()->getFormatCode());
            self::assertSame('Petunjuk', $workbook->getSheet(1)->getTitle());
            self::assertStringContainsString('Nama otomatis mengikuti data master mahasiswa', $workbook->getSheet(1)->getCell('B4')->getValue());
            self::assertStringContainsString('Nominal Iuran Pengembangan Institusi', $workbook->getSheet(1)->getCell('A3')->getValue());
            $workbook->getSheet(0)->fromArray([[1, '3001', 5000000]], null, 'A2');
            (new Xlsx($workbook))->save($templatePath);

            $this->post(route('finance.ipi.upload'), [
                'file' => UploadedFile::fake()->createWithContent('tagihan.xlsx', file_get_contents($templatePath)),
            ])->assertRedirect(route('finance.ipi.index'));

            $bill = DB::connection('simak_main')->table('s1m4k_pembayaranipi')->first();
            $this->assertSame(5000000, (int) $bill->nominal);
            $this->assertSame(0, (int) $bill->lunas);
            $this->assertSame(
                'Nama dari Master SIMAK',
                DB::connection('simak_main')->table('s1m4k_pembayaranipi as payment')
                    ->join('s1m4k_mastermhs as student', 'payment.id_mahasiswa', '=', 'student.id_mahasiswa')
                    ->where('payment.id', $bill->id)
                    ->value('student.nama_mahasiswa'),
            );
        } finally {
            unlink($templatePath);
        }
    }

    public function test_finance_role_can_create_and_activate_a_tuition_period(): void
    {
        DB::connection('simak_main')->table('s1m4k_kalender')->insert([
            'id_kalender' => 'calendar-1',
            'tahun' => '20261',
            'namatahun' => '2026 Ganjil',
            'id_prodi' => 'program-1',
        ]);
        $this->actingAs(new GenericUser([
            'id' => 'finance-1', 'role' => 'keuangan', 'nama_lengkap' => 'Petugas Keuangan',
        ]));

        $this->post(route('finance.periods.store'), [
            'tahun' => '20261',
            'tanggal_mulai' => '2026-01-01',
            'tanggal_akhir' => '2026-06-30',
        ])->assertRedirect(route('finance.periods.index'));

        $periodId = DB::connection('simak_main')->table('s1m4k_periode')->value('id_periode');
        $this->assertNotEmpty($periodId);
        $this->assertDatabaseHas('s1m4k_periode', [
            'id_periode' => $periodId,
            'nama_periode' => '2026 Ganjil',
            'jenis' => 'ukt',
            'aktif' => 'N',
        ], 'simak_main');

        $this->post(route('finance.periods.activate', $periodId))
            ->assertRedirect(route('finance.periods.index'));
        $this->assertDatabaseHas('s1m4k_periode', ['id_periode' => $periodId, 'aktif' => 'Y'], 'simak_main');
    }

    public function test_finance_dashboard_is_available_for_leadership_roles_and_scoped_to_faculty(): void
    {
        Schema::connection('simak_main')->create('s1m4k_mastermhs', function (Blueprint $table): void {
            $table->string('id_mahasiswa')->primary();
            $table->string('nim');
            $table->string('nama_mahasiswa');
            $table->string('kategori_ukt')->nullable();
            $table->string('id_prodi')->nullable();
            $table->string('id_fakultas')->nullable();
            $table->string('id_perguruan_tinggi')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterprodi', function (Blueprint $table): void {
            $table->string('id_prodi')->primary();
            $table->string('id_fakultas')->nullable();
            $table->string('nama_program_studi')->nullable();
            $table->string('nama_jenjang_pendidikan')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterfakultas', function (Blueprint $table): void {
            $table->string('id_fakultas')->primary();
            $table->string('nama_fakultas')->nullable();
        });
        foreach (['s1m4k_pembayaranukt', 's1m4k_pembayaranipi'] as $tableName) {
            Schema::connection('simak_main')->create($tableName, function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->string('id_mahasiswa');
                $table->string('tahun')->nullable();
                $table->string('nama_bank')->nullable();
                $table->string('trx_id')->nullable();
                $table->string('no_va')->nullable();
                $table->integer('nominal');
                $table->string('tgl_expire')->nullable();
                $table->integer('lunas');
            });
        }
        DB::connection('simak_main')->table('s1m4k_periode')->insert([
            'id_periode' => 'period-1',
            'nama_periode' => '2026 Ganjil',
            'tahun' => '20261',
            'tanggal_mulai' => '2026-01-01',
            'tanggal_akhir' => '2026-06-30',
            'jenis' => 'ukt',
            'aktif' => 'Y',
        ]);
        DB::connection('simak_main')->table('s1m4k_masterfakultas')->insert([
            ['id_fakultas' => 'faculty-1', 'nama_fakultas' => 'Fakultas Satu'],
            ['id_fakultas' => 'faculty-2', 'nama_fakultas' => 'Fakultas Dua'],
        ]);
        DB::connection('simak_main')->table('s1m4k_masterprodi')->insert([
            ['id_prodi' => 'program-1', 'id_fakultas' => 'faculty-1', 'nama_program_studi' => 'Prodi Satu'],
            ['id_prodi' => 'program-2', 'id_fakultas' => 'faculty-2', 'nama_program_studi' => 'Prodi Dua'],
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            ['id_mahasiswa' => 'student-1', 'nim' => '01012011150', 'nama_mahasiswa' => 'Mahasiswa Satu', 'kategori_ukt' => '1', 'id_prodi' => 'program-1', 'id_fakultas' => 'faculty-1', 'id_perguruan_tinggi' => 'pt-1'],
            ['id_mahasiswa' => 'student-2', 'nim' => '01012111150', 'nama_mahasiswa' => 'Mahasiswa Dua', 'kategori_ukt' => '1', 'id_prodi' => 'program-2', 'id_fakultas' => 'faculty-2', 'id_perguruan_tinggi' => 'pt-1'],
        ]);
        DB::connection('simak_main')->table('s1m4k_pembayaranukt')->insert([
            ['id' => 'ukt-1', 'id_mahasiswa' => 'student-1', 'tahun' => '20261', 'nominal' => 1000000, 'lunas' => 1],
            ['id' => 'ukt-2', 'id_mahasiswa' => 'student-2', 'tahun' => '20261', 'nominal' => 2000000, 'lunas' => 0],
        ]);
        DB::connection('simak_main')->table('s1m4k_pembayaranipi')->insert([
            ['id' => 'ipi-1', 'id_mahasiswa' => 'student-1', 'nominal' => 3000000, 'lunas' => 0],
            ['id' => 'ipi-2', 'id_mahasiswa' => 'student-2', 'nominal' => 4000000, 'lunas' => 1],
        ]);

        foreach (['pimuniv', 'keuangan'] as $role) {
            $this->actingAs(new GenericUser([
                'id' => $role.'-1',
                'role' => $role,
                'name' => 'Petugas '.$role,
            ]));
            $response = $this->get(route('finance.dashboard'))
                ->assertOk()
                ->assertSee('Fakultas Satu')
                ->assertSee('Fakultas Dua')
                ->assertSee('Ringkasan per fakultas');
            if ($role === 'keuangan') {
                $response->assertSee('Selamat datang, Petugas keuangan.')
                    ->assertSee('Tagihan Iuran Pengembangan Institusi')
                    ->assertSee('Nominal Iuran Pengembangan Institusi lunas')
                    ->assertDontSee('Uang Pembangunan');
            }
        }

        $this->actingAs(new GenericUser([
            'id' => 'faculty-leader', 'role' => 'pimfak', 'id_fakultas' => 'faculty-1',
        ]));
        $this->get(route('finance.dashboard'))->assertForbidden();

        $this->actingAs(new GenericUser(['id' => 'admin-1', 'role' => 'admin']));
        $this->get(route('finance.dashboard'))->assertForbidden();
        $this->actingAs(new GenericUser(['id' => 'student-1', 'role' => 'mahasiswa']));
        $this->get(route('finance.dashboard'))->assertForbidden();
    }

    public function test_english_ui_dictionary_covers_finance_labels(): void
    {
        $translations = Lang::get('ui.translations', [], 'en');

        $this->assertSame('Payment History', $translations['History Pembayaran']);
        $this->assertSame('Finance Dashboard', $translations['Dashboard Keuangan']);
        $this->assertSame('Add tuition period', $translations['Tambah periode uang kuliah']);
        $this->assertSame('Institutional Development Contribution', $translations['Iuran Pengembangan Institusi']);
        $this->assertSame('Midtrans Billing', $translations['Billing Midtrans']);
        $this->assertSame('Midtrans Transaction History', $translations['Riwayat Transaksi Midtrans']);
        $this->assertSame('The currently active tuition period will be deactivated. Continue?', $translations['Periode uang kuliah yang sedang aktif akan dinonaktifkan. Lanjutkan?']);
    }
}
