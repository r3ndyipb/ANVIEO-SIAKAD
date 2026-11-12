<?php

namespace Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StudentPaymentsTest extends TestCase
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

        Schema::connection('simak_main')->create('s1m4k_mastermhs', function (Blueprint $table): void {
            $table->string('id_mahasiswa')->primary();
            $table->string('nim');
            $table->string('nama_mahasiswa');
            $table->string('id_prodi')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterpt', function (Blueprint $table): void {
            $table->string('id_perguruan_tinggi')->primary();
            $table->string('nama_perguruan_tinggi')->nullable();
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
        Schema::connection('simak_main')->create('s1m4k_periode', function (Blueprint $table): void {
            $table->string('id_periode')->primary();
            $table->string('tahun')->nullable();
            $table->string('jenis');
            $table->string('nama_periode');
            $table->date('tanggal_mulai');
            $table->date('tanggal_akhir');
        });
        foreach (['s1m4k_pembayaranukt', 's1m4k_pembayaranipi'] as $tableName) {
            Schema::connection('simak_main')->create($tableName, function (Blueprint $table) use ($tableName): void {
                $table->string('id')->primary();
                $table->string('id_mahasiswa');
                if ($tableName === 's1m4k_pembayaranukt') {
                    $table->string('tahun');
                }
                $table->string('nama_bank')->nullable();
                $table->string('trx_id')->nullable();
                $table->string('no_va')->nullable();
                $table->integer('nominal');
                $table->string('tgl_expire')->nullable();
                $table->integer('lunas');
                $table->string('tanggal_bayar')->nullable();
                $table->string('petugas')->nullable();
            });
        }
        Schema::connection('simak_main')->create('log_aktivitas', function (Blueprint $table): void {
            $table->string('id_log')->primary();
            $table->string('id_user')->nullable();
            $table->string('level_user')->nullable();
            $table->string('nama_user');
            $table->string('modul');
            $table->text('aktifitas');
            $table->dateTime('created_at');
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
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });

        DB::connection('simak_main')->table('s1m4k_masterfakultas')->insert([
            'id_fakultas' => 'faculty-1',
            'nama_fakultas' => 'Fakultas Satu',
        ]);
        DB::connection('simak_main')->table('s1m4k_masterprodi')->insert([
            'id_prodi' => 'program-1',
            'id_fakultas' => 'faculty-1',
            'nama_program_studi' => 'Program Satu',
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            ['id_mahasiswa' => 'student-1', 'nim' => '3001', 'nama_mahasiswa' => 'Mahasiswa Pertama', 'id_prodi' => 'program-1'],
            ['id_mahasiswa' => 'student-2', 'nim' => '3002', 'nama_mahasiswa' => 'Mahasiswa Kedua', 'id_prodi' => 'program-1'],
        ]);
        DB::connection('simak_main')->table('s1m4k_masterpt')->insert([
            'id_perguruan_tinggi' => 'campus-1',
            'nama_perguruan_tinggi' => 'Universitas Dinamis Uji',
        ]);
    }

    public function test_student_only_sees_their_own_ukt_and_ipi_bills(): void
    {
        $this->seedStudentBills();
        $this->actingAs($this->studentUser('student-1'));

        $this->get(route('student-payments.index'))
            ->assertOk()
            ->assertSee('Mahasiswa Pertama')
            ->assertSee('Uang Kuliah Ganjil')
            ->assertSee('Iuran Pengembangan Institusi')
            ->assertSee('Pembayaran online diproses melalui Midtrans')
            ->assertDontSee('e-Coll')
            ->assertDontSee('Virtual account')
            ->assertDontSee('data-lucide="printer"', false)
            ->assertDontSee('Mahasiswa Kedua')
            ->assertSee('data-finance-confirm-dialog', false);
    }

    public function test_student_bills_and_receipt_work_when_service_fee_upgrade_is_not_applied(): void
    {
        Schema::connection('simak_main')->table('simak_payment_transactions', function (Blueprint $table): void {
            $table->dropColumn('service_fee');
        });
        $this->seedStudentBills();
        DB::connection('simak_main')->table('s1m4k_pembayaranipi')
            ->where('id', 'ipi-own')
            ->update(['lunas' => 1]);
        $this->insertSettledTransaction('ipi-own', 'student-1', 'ipi', 7000000);
        $this->actingAs($this->studentUser('student-1'));

        $this->get(route('student-payments.index'))
            ->assertOk()
            ->assertSee('Mahasiswa Pertama')
            ->assertSee('Tagihan: Rp 7.000.000');
        $this->get(route('student-payments.billing', ['type' => 'ipi', 'payment' => 'ipi-own']))
            ->assertOk()
            ->assertSee('Total pembayaran')
            ->assertSee('Rp 7.000.000');
    }

    public function test_student_can_only_print_paid_bills_and_uses_midtrans_receipts(): void
    {
        $this->seedStudentBills();
        DB::connection('simak_main')->table('s1m4k_pembayaranipi')->where('id', 'ipi-own')->update(['lunas' => 1]);
        $this->insertSettledTransaction('ipi-own', 'student-1', 'ipi', 7000000);
        $this->actingAs($this->studentUser('student-1'));

        $this->get(route('student-payments.billing', ['type' => 'ukt', 'payment' => 'ukt-own']))
            ->assertNotFound();
        $this->get(route('student-payments.billing', ['type' => 'ipi', 'payment' => 'ipi-own']))
            ->assertOk()
            ->assertSee('billing-watermark is-paid')
            ->assertSee('Iuran Pengembangan Institusi')
            ->assertSee('Midtrans')
            ->assertSee('SIAKAD-IPI-ipi-own')
            ->assertSee('SIAKAD Universitas Dinamis Uji')
            ->assertDontSee('Universitas Khairun')
            ->assertDontSee('SIMAK-IPI-ipi-own')
            ->assertSee('LUNAS')
            ->assertSee('Pembayaran tagihan ini telah diverifikasi melalui Midtrans')
            ->assertSee('images/payments/midtrans.svg')
            ->assertDontSee('images/payments/bni.jpg')
            ->assertDontSee('images/payments/btn.png');
        $this->get(route('student-payments.billing', ['type' => 'ukt', 'payment' => 'ukt-other']))
            ->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_student_can_print_paid_bill_without_virtual_account_as_payment_proof(): void
    {
        $this->seedStudentBills();
        DB::connection('simak_main')->table('s1m4k_pembayaranukt')->where('id', 'ukt-own')->update([
            'lunas' => 1,
            'tanggal_bayar' => '2026-10-21 10:15:00',
        ]);
        $this->insertSettledTransaction('ukt-own', 'student-1', 'ukt', 1500000);
        $this->actingAs($this->studentUser('student-1'));

        $this->get(route('student-payments.index'))
            ->assertOk()
            ->assertSee(route('student-payments.billing', ['type' => 'ukt', 'payment' => 'ukt-own']));
        $this->get(route('student-payments.billing', ['type' => 'ukt', 'payment' => 'ukt-own']))
            ->assertOk()
            ->assertSee('Bukti Pelunasan')
            ->assertSee('SIAKAD-UKT-ukt-own')
            ->assertDontSee('SIMAK-UKT-ukt-own')
            ->assertSee('LUNAS')
            ->assertSee('2026-10-21 10:15:00')
            ->assertDontSee('Nomor Virtual Account');
        Http::assertNothingSent();
    }

    public function test_student_can_print_a_manually_recorded_scholarship_payment(): void
    {
        $this->seedStudentBills();
        DB::connection('simak_main')->table('s1m4k_pembayaranukt')->where('id', 'ukt-own')->update([
            'lunas' => 1,
            'nama_bank' => 'BEASISWA',
            'trx_id' => 'SK-BEASISWA-2026-001',
            'tanggal_bayar' => '2026-10-22 09:00:00',
        ]);
        $this->actingAs($this->studentUser('student-1'));

        $this->get(route('student-payments.index'))
            ->assertOk()
            ->assertSee('Beasiswa')
            ->assertSee('SK-BEASISWA-2026-001')
            ->assertSee('dicatat oleh petugas Keuangan');
        $this->get(route('student-payments.billing', ['type' => 'ukt', 'payment' => 'ukt-own']))
            ->assertOk()
            ->assertSee('Beasiswa')
            ->assertSee('SK-BEASISWA-2026-001')
            ->assertSee('dicatat lunas secara manual')
            ->assertDontSee('images/payments/midtrans.svg');
    }

    public function test_student_and_staff_can_print_bank_paid_bill(): void
    {
        $this->seedStudentBills();
        DB::connection('simak_main')->table('s1m4k_pembayaranukt')->where('id', 'ukt-own')->update([
            'lunas' => 1,
            'nama_bank' => 'Bank Mandiri',
            'trx_id' => 'TRX20261001',
            'tanggal_bayar' => '2026-08-05 10:15:30',
        ]);

        // Student owner can print
        $this->actingAs($this->studentUser('student-1'));
        $this->get(route('student-payments.index'))
            ->assertOk()
            ->assertSee('Bank Mandiri')
            ->assertSee('TRX20261001');
        $this->get(route('student-payments.billing', ['type' => 'ukt', 'payment' => 'ukt-own']))
            ->assertOk()
            ->assertSee('Bank Mandiri')
            ->assertSee('TRX20261001')
            ->assertSee('LUNAS');

        // Staff (admin) can print
        $this->actingAs(new GenericUser(['id' => 'admin-1', 'legacy_id' => '1', 'role' => 'admin']));
        $this->get(route('student-payments.billing', ['type' => 'ukt', 'payment' => 'ukt-own']))
            ->assertOk()
            ->assertSee('Bank Mandiri')
            ->assertSee('TRX20261001');

        // Other student cannot view other student's bill
        $this->actingAs($this->studentUser('student-2'));
        $this->get(route('student-payments.billing', ['type' => 'ukt', 'payment' => 'ukt-own']))
            ->assertNotFound();
    }

    public function test_nonstudent_cannot_open_student_payment_page(): void
    {
        $this->actingAs(new GenericUser(['id' => 'admin', 'legacy_id' => 'admin', 'role' => 'admin']));

        $this->get(route('student-payments.index'))->assertForbidden();
    }

    public function test_legacy_e_coll_payment_endpoints_are_no_longer_available(): void
    {
        $this->assertFalse(Route::has('student-payments.virtual-account'));
        $this->assertFalse(Route::has('student-payments.sync-ukt'));
    }

    private function seedStudentBills(): void
    {
        DB::connection('simak_main')->table('s1m4k_periode')->insert([
            'id_periode' => 'period-1',
            'tahun' => '20261',
            'jenis' => 'ukt',
            'nama_periode' => 'Uang Kuliah Ganjil',
            'tanggal_mulai' => '2026-01-01',
            'tanggal_akhir' => '2026-12-31',
        ]);
        DB::connection('simak_main')->table('s1m4k_pembayaranukt')->insert([
            [
                'id' => 'ukt-own',
                'id_mahasiswa' => 'student-1',
                'tahun' => '20261',
                'nama_bank' => null,
                'trx_id' => null,
                'no_va' => null,
                'nominal' => 1500000,
                'tgl_expire' => null,
                'lunas' => 0,
                'tanggal_bayar' => null,
                'petugas' => null,
            ],
            [
                'id' => 'ukt-other',
                'id_mahasiswa' => 'student-2',
                'tahun' => '20261',
                'nama_bank' => null,
                'trx_id' => null,
                'no_va' => null,
                'nominal' => 2000000,
                'tgl_expire' => null,
                'lunas' => 0,
                'tanggal_bayar' => null,
                'petugas' => null,
            ],
        ]);
        DB::connection('simak_main')->table('s1m4k_pembayaranipi')->insert([
            [
                'id' => 'ipi-own',
                'id_mahasiswa' => 'student-1',
                'nama_bank' => null,
                'trx_id' => null,
                'no_va' => null,
                'nominal' => 7000000,
                'tgl_expire' => null,
                'lunas' => 0,
                'tanggal_bayar' => null,
                'petugas' => null,
            ],
        ]);
    }

    private function insertSettledTransaction(string $paymentId, string $studentId, string $type, int $amount): void
    {
        DB::connection('simak_main')->table('simak_payment_transactions')->insert([
            'id' => 'transaction-'.$paymentId,
            'gateway' => 'midtrans',
            'payment_type' => $type,
            'payment_id' => $paymentId,
            'student_id' => $studentId,
            'order_id' => 'SIMAK-'.strtoupper($type).'-'.$paymentId,
            'gross_amount' => $amount,
            'transaction_status' => 'settlement',
            'transaction_id' => 'midtrans-'.$paymentId,
            'created_at' => now(),
        ]);
    }

    private function studentUser(string $studentId): GenericUser
    {
        return new GenericUser([
            'id' => $studentId,
            'legacy_id' => $studentId,
            'role' => 'mahasiswa',
            'nama_lengkap' => 'Mahasiswa Pertama',
        ]);
    }
}
