<?php

namespace Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MidtransPaymentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'database.connections.simak_main' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
        ]);
        DB::purge('simak_main');

        Schema::connection('simak_main')->create('simak_payment_gateways', function (Blueprint $table): void {
            $table->string('gateway')->primary();
            $table->boolean('enabled')->default(false);
            $table->string('environment')->default('sandbox');
            $table->string('client_key')->nullable();
            $table->unsignedBigInteger('service_fee')->default(0);
            $table->text('credentials_encrypted')->nullable();
            $table->timestamps();
        });
        Schema::connection('simak_main')->create('simak_payment_transactions', function (Blueprint $table): void {
            $table->char('id', 36)->primary();
            $table->string('gateway');
            $table->string('payment_type', 10);
            $table->string('payment_id', 100);
            $table->string('student_id', 100);
            $table->string('order_id', 100)->unique();
            $table->unsignedBigInteger('gross_amount');
            $table->unsignedBigInteger('service_fee')->default(0);
            $table->string('transaction_status', 40)->default('pending');
            $table->string('transaction_id', 100)->nullable();
            $table->string('snap_token', 255)->nullable();
            $table->string('redirect_url', 1000)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('notification_received_at')->nullable();
            $table->timestamps();
        });
        Schema::connection('simak_main')->create('simak_manual_bills', function (Blueprint $table): void {
            $table->char('id', 36)->primary();
            $table->string('description', 255);
            $table->string('payer_name', 100);
            $table->string('payer_email')->nullable();
            $table->string('payer_phone', 30)->nullable();
            $table->unsignedBigInteger('amount');
            $table->timestamp('expires_at')->nullable();
            $table->string('checkout_status', 20)->default('creating');
            $table->string('created_by', 100)->nullable();
            $table->timestamps();
        });
        Schema::connection('simak_main')->create('s1m4k_pembayaranukt', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('id_mahasiswa');
            $table->string('tahun', 5);
            $table->string('nama_bank')->nullable();
            $table->string('trx_id')->nullable();
            $table->string('no_va')->nullable();
            $table->integer('nominal');
            $table->string('tgl_expire')->nullable();
            $table->integer('lunas')->default(0);
            $table->string('tanggal_bayar')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_pembayaranipi', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('id_mahasiswa');
            $table->string('nama_bank')->nullable();
            $table->string('trx_id')->nullable();
            $table->integer('nominal');
            $table->integer('lunas')->default(0);
            $table->string('tanggal_bayar')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_periode', function (Blueprint $table): void {
            $table->string('id_periode')->primary();
            $table->string('tahun', 5);
            $table->string('jenis');
            $table->string('nama_periode');
            $table->date('tanggal_mulai');
            $table->date('tanggal_akhir');
        });
        Schema::connection('simak_main')->create('s1m4k_mastermhs', function (Blueprint $table): void {
            $table->string('id_mahasiswa')->primary();
            $table->string('nim');
            $table->string('nama_mahasiswa');
            $table->string('kategori_ukt')->nullable();
            $table->string('id_perguruan_tinggi')->nullable();
            $table->string('id_fakultas')->nullable();
            $table->string('id_prodi')->nullable();
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
        Schema::connection('simak_main')->create('s1m4k_kalender', function (Blueprint $table): void {
            $table->string('id_kalender')->primary();
            $table->string('tahun');
            $table->string('id_prodi');
            $table->string('status')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_registrasiulang', function (Blueprint $table): void {
            $table->string('id_registrasi')->primary();
            $table->string('id_perguruan_tinggi');
            $table->string('id_fakultas');
            $table->string('id_prodi');
            $table->string('id_mahasiswa');
            $table->string('id_kalender');
            $table->string('tgl_registasi');
            $table->string('user');
        });
        Schema::connection('simak_main')->create('log_aktivitas', function (Blueprint $table): void {
            $table->string('id_log')->primary();
            $table->string('id_user')->nullable();
            $table->string('level_user')->nullable();
            $table->string('nama_user');
            $table->string('modul');
            $table->text('aktifitas');
            $table->dateTime('created_at');
        });

        DB::connection('simak_main')->table('simak_payment_gateways')->insert([
            'gateway' => 'midtrans',
            'enabled' => true,
            'environment' => 'sandbox',
            'client_key' => 'client-test',
            'credentials_encrypted' => Crypt::encryptString(json_encode(['server_key' => 'server-test'])),
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            'id_mahasiswa' => 'student-1',
            'nim' => '3001',
            'nama_mahasiswa' => 'Mahasiswa Pertama',
            'kategori_ukt' => '1',
            'id_perguruan_tinggi' => 'campus-1',
            'id_fakultas' => 'faculty-1',
            'id_prodi' => 'program-1',
        ]);
        DB::connection('simak_main')->table('s1m4k_masterprodi')->insert([
            'id_prodi' => 'program-1',
            'id_fakultas' => 'faculty-1',
            'nama_program_studi' => 'Program Uji',
            'nama_jenjang_pendidikan' => 'S1',
        ]);
        DB::connection('simak_main')->table('s1m4k_masterfakultas')->insert([
            'id_fakultas' => 'faculty-1',
            'nama_fakultas' => 'Fakultas Uji',
        ]);
        DB::connection('simak_main')->table('s1m4k_kalender')->insert([
            [
                'id_kalender' => 'calendar-1',
                'tahun' => '20261',
                'id_prodi' => 'program-1',
                'status' => 'Y',
            ],
            [
                'id_kalender' => 'calendar-newer',
                'tahun' => '20262',
                'id_prodi' => 'program-1',
                'status' => 'Y',
            ],
        ]);
        DB::connection('simak_main')->table('s1m4k_pembayaranukt')->insert([
            'id' => 'ukt-1',
            'id_mahasiswa' => 'student-1',
            'tahun' => '20261',
            'nominal' => 1500000,
            'lunas' => 0,
        ]);
        DB::connection('simak_main')->table('s1m4k_periode')->insert([
            'id_periode' => 'period-1',
            'tahun' => '20261',
            'jenis' => 'ukt',
            'nama_periode' => 'Uang Kuliah Ganjil',
            'tanggal_mulai' => today()->subDay()->toDateString(),
            'tanggal_akhir' => today()->addDay()->toDateString(),
        ]);
    }

    public function test_student_creates_a_sandbox_snap_checkout_for_their_unpaid_bill(): void
    {
        DB::connection('simak_main')->table('simak_payment_gateways')
            ->where('gateway', 'midtrans')->update(['service_fee' => 2500]);
        Http::fake([
            'https://app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'token' => 'snap-token',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-token',
            ], 201),
        ]);
        $this->actingAs($this->studentUser());

        $this->post(route('student-payments.midtrans', 'ukt-1'), ['jenis' => 'ukt'])
            ->assertRedirect('https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-token');

        $transaction = DB::connection('simak_main')->table('simak_payment_transactions')->first();
        $this->assertSame('midtrans', $transaction->gateway);
        $this->assertSame('ukt-1', $transaction->payment_id);
        $this->assertSame(1502500, (int) $transaction->gross_amount);
        $this->assertSame(2500, (int) $transaction->service_fee);
        $this->assertSame('pending', $transaction->transaction_status);
        $this->assertSame(0, (int) DB::connection('simak_main')->table('s1m4k_pembayaranukt')->where('id', 'ukt-1')->value('lunas'));
        $this->actingAs($this->studentUser())
            ->get(route('student-payments.index'))
            ->assertOk()
            ->assertSee('Biaya layanan Midtrans: Rp 2.500')
            ->assertSee('Total Midtrans: Rp 1.502.500');
        Http::assertSent(fn ($request): bool => $request->url() === 'https://app.sandbox.midtrans.com/snap/v1/transactions'
            && $request['transaction_details']['gross_amount'] === 1502500
            && str_starts_with($request['transaction_details']['order_id'], 'SIAKAD-UKT-')
            && $request['item_details'][0]['price'] === 1500000
            && $request['item_details'][1]['price'] === 2500
            && $request['item_details'][1]['name'] === 'Biaya layanan Midtrans'
            && $request['callbacks']['finish'] === route('student-payments.index')
            && $request['callbacks']['unfinish'] === route('student-payments.index')
            && $request['callbacks']['error'] === route('student-payments.index'));
    }

    public function test_finance_can_create_custom_manual_billing_and_settlement_does_not_touch_ukt_or_ipi(): void
    {
        DB::connection('simak_main')->table('simak_payment_gateways')
            ->where('gateway', 'midtrans')->update(['service_fee' => 2500]);
        Http::fake([
            'https://app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'token' => 'manual-snap-token',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/manual-snap-token',
            ], 201),
        ]);
        $this->actingAs(new GenericUser([
            'id' => 'finance-1',
            'legacy_id' => 'finance-1',
            'role' => 'keuangan',
            'nama_lengkap' => 'Petugas Keuangan',
        ]));

        $this->get(route('finance.manual-bills.index'))
            ->assertOk()
            ->assertSee('data-dialog-open="manual-bill-create"', false)
            ->assertSee('<dialog class="manual-bill-dialog"', false)
            ->assertSee('Buat billing')
            ->assertSee('Masa berlaku tautan (jam)')
            ->assertDontSee('Buat billing dan tautan Midtrans');
        $this->post(route('finance.manual-bills.store'), [
            'payer_name' => 'Pembayar Umum',
            'payer_email' => 'payer@example.test',
            'payer_phone' => '08123456789',
            'description' => 'Biaya layanan kampus',
            'amount' => 100000,
            'expires_in_hours' => 6,
        ])
            ->assertRedirect(route('finance.manual-bills.index'))
            ->assertSessionHas('checkout_url', 'https://app.sandbox.midtrans.com/snap/v2/vtweb/manual-snap-token');

        $bill = DB::connection('simak_main')->table('simak_manual_bills')->first();
        $transaction = DB::connection('simak_main')->table('simak_payment_transactions')->first();
        $this->assertSame('Pembayar Umum', $bill->payer_name);
        $this->assertSame('Biaya layanan kampus', $bill->description);
        $this->assertSame(100000, (int) $bill->amount);
        $this->assertSame('created', $bill->checkout_status);
        $this->assertSame('manual', $transaction->payment_type);
        $this->assertSame($bill->id, $transaction->payment_id);
        $this->assertSame('MANUAL', $transaction->student_id);
        $this->assertSame(102500, (int) $transaction->gross_amount);
        $this->assertSame(2500, (int) $transaction->service_fee);
        $this->assertSame(6, (int) round(now()->diffInHours($transaction->expires_at)));
        $this->get(route('finance.manual-bills.index'))
            ->assertOk()
            ->assertSee('data-payment-status="unpaid"', false)
            ->assertSee('Belum lunas')
            ->assertSee('title="Cetak billing"', false)
            ->assertSee('title="Cek status bayar"', false)
            ->assertSee(route('finance.manual-bills.print', $bill->id))
            ->assertSee(route('finance.manual-bills.status', $bill->id));
        $this->get(route('finance.manual-bills.print', $bill->id))
            ->assertOk()
            ->assertSee('Billing Manual')
            ->assertSee('Rp 102.500')
            ->assertSee('Biaya layanan kampus')
            ->assertSee('https://app.sandbox.midtrans.com/snap/v2/vtweb/manual-snap-token');
        Http::assertSent(fn ($request): bool => $request->url() === 'https://app.sandbox.midtrans.com/snap/v1/transactions'
            && $request['transaction_details']['gross_amount'] === 102500
            && str_starts_with($request['transaction_details']['order_id'], 'SIAKAD-MANUAL-')
            && $request['item_details'][0]['name'] === 'Biaya layanan kampus'
            && $request['customer_details']['first_name'] === 'Pembayar Umum'
            && $request['customer_details']['email'] === 'payer@example.test'
            && $request['customer_details']['phone'] === '08123456789'
            && $request['expiry']['unit'] === 'hours'
            && $request['expiry']['duration'] === 6
            && $request['callbacks']['finish'] === route('public.home'));

        $orderId = (string) $transaction->order_id;
        $grossAmount = '102500.00';
        $this->postJson('/api/payments/midtrans/notification', [
            'order_id' => $orderId,
            'status_code' => '200',
            'gross_amount' => $grossAmount,
            'signature_key' => hash('sha512', $orderId.'200'.$grossAmount.'server-test'),
            'transaction_status' => 'settlement',
            'transaction_id' => 'manual-midtrans-transaction',
        ])->assertOk();

        $this->assertSame('settlement', DB::connection('simak_main')->table('simak_payment_transactions')->value('transaction_status'));
        $this->assertSame(0, (int) DB::connection('simak_main')->table('s1m4k_pembayaranukt')->where('id', 'ukt-1')->value('lunas'));
        $this->assertSame(0, DB::connection('simak_main')->table('s1m4k_pembayaranipi')->count());
        $this->get(route('finance.manual-bills.index'))
            ->assertOk()
            ->assertSee('data-payment-status="paid"', false)
            ->assertSee('Lunas')
            ->assertSee('Settlement')
            ->assertSee('Biaya layanan kampus')
            ->assertSee('Pembayar Umum');
        $this->get(route('finance.midtrans-transactions.index'))
            ->assertOk()
            ->assertSee('data-payment-status="paid"', false)
            ->assertSee('Lunas')
            ->assertSee('Billing manual: Biaya layanan kampus')
            ->assertSee('Pembayar Umum');
    }

    public function test_non_finance_cannot_create_manual_billing_and_invalid_expiry_is_rejected(): void
    {
        $payload = [
            'payer_name' => 'Pembayar',
            'description' => 'Tagihan manual',
            'amount' => 10000,
            'expires_in_hours' => 24,
        ];
        $this->actingAs($this->studentUser())
            ->post(route('finance.manual-bills.store'), $payload)
            ->assertForbidden();

        $this->actingAs(new GenericUser([
            'id' => 'finance-1', 'legacy_id' => 'finance-1', 'role' => 'keuangan',
        ]))
            ->from(route('finance.manual-bills.index'))
            ->post(route('finance.manual-bills.store'), [...$payload, 'expires_in_hours' => 721])
            ->assertRedirect(route('finance.manual-bills.index'))
            ->assertSessionHasErrors('expires_in_hours');

        Http::assertNothingSent();
        $this->assertDatabaseCount('simak_manual_bills', 0, 'simak_main');
        $this->assertDatabaseCount('simak_payment_transactions', 0, 'simak_main');
    }

    public function test_manual_billing_uses_the_configured_production_environment(): void
    {
        DB::connection('simak_main')->table('simak_payment_gateways')
            ->where('gateway', 'midtrans')->update(['environment' => 'production']);
        Http::fake([
            'https://app.midtrans.com/snap/v1/transactions' => Http::response([
                'token' => 'production-manual-token',
                'redirect_url' => 'https://app.midtrans.com/snap/v2/vtweb/production-manual-token',
            ], 201),
        ]);
        $this->actingAs(new GenericUser([
            'id' => 'finance-1', 'legacy_id' => 'finance-1', 'role' => 'keuangan',
        ]))
            ->post(route('finance.manual-bills.store'), [
                'payer_name' => 'Pembayar Produksi',
                'description' => 'Tagihan produksi',
                'amount' => 50000,
                'expires_in_hours' => 12,
            ])
            ->assertRedirect(route('finance.manual-bills.index'))
            ->assertSessionHas('checkout_url', 'https://app.midtrans.com/snap/v2/vtweb/production-manual-token');

        Http::assertSent(fn ($request): bool => $request->url() === 'https://app.midtrans.com/snap/v1/transactions'
            && $request['expiry']['duration'] === 12);
        $this->assertSame(
            'https://app.midtrans.com/snap/v2/vtweb/production-manual-token',
            DB::connection('simak_main')->table('simak_payment_transactions')->value('redirect_url')
        );
    }

    public function test_finance_can_check_status_for_manual_billing_and_settle_it_without_touching_ukt_ipi(): void
    {
        $orderId = 'SIAKAD-MANUAL-status-check';
        DB::connection('simak_main')->table('simak_manual_bills')->insert([
            'id' => 'manual-bill-status',
            'description' => 'Biaya pendaftaran',
            'payer_name' => 'Pembayar Umum',
            'amount' => 75000,
            'expires_at' => now()->addDay(),
            'checkout_status' => 'created',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::connection('simak_main')->table('simak_payment_transactions')->insert([
            'id' => 'manual-transaction-status',
            'gateway' => 'midtrans',
            'payment_type' => 'manual',
            'payment_id' => 'manual-bill-status',
            'student_id' => 'MANUAL',
            'order_id' => $orderId,
            'gross_amount' => 75000,
            'service_fee' => 0,
            'transaction_status' => 'pending',
        ]);
        Http::fake([
            'https://api.sandbox.midtrans.com/v2/'.$orderId.'/status' => Http::response([
                'order_id' => $orderId,
                'status_code' => '200',
                'gross_amount' => '75000.00',
                'transaction_status' => 'settlement',
                'transaction_id' => 'manual-provider-transaction',
            ]),
        ]);
        $this->actingAs(new GenericUser([
            'id' => 'finance-1', 'legacy_id' => 'finance-1', 'role' => 'keuangan',
        ]))
            ->post(route('finance.manual-bills.status', 'manual-bill-status'))
            ->assertRedirect(route('finance.manual-bills.index'))
            ->assertSessionHas('status', 'Status pembayaran Midtrans diperbarui: settlement.');

        $this->assertSame('paid', DB::connection('simak_main')->table('simak_manual_bills')
            ->where('id', 'manual-bill-status')->value('checkout_status'));
        $this->assertSame('settlement', DB::connection('simak_main')->table('simak_payment_transactions')
            ->where('id', 'manual-transaction-status')->value('transaction_status'));
        $this->assertSame(0, (int) DB::connection('simak_main')->table('s1m4k_pembayaranukt')
            ->where('id', 'ukt-1')->value('lunas'));
        $this->assertSame(0, DB::connection('simak_main')->table('s1m4k_pembayaranipi')->count());
        Http::assertSent(fn ($request): bool => $request->method() === 'GET'
            && $request->url() === 'https://api.sandbox.midtrans.com/v2/'.$orderId.'/status');
    }

    public function test_checkout_failure_shows_midtrans_provider_message_without_exposing_credentials(): void
    {
        Http::fake([
            'https://app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'error' => 'Access denied. Please check the server key.',
            ], 401),
        ]);
        $this->actingAs($this->studentUser());

        $this->from(route('student-payments.index'))
            ->post(route('student-payments.midtrans', 'ukt-1'), ['jenis' => 'ukt'])
            ->assertRedirect(route('student-payments.index'))
            ->assertSessionHasErrors([
                'payment' => 'Pembuatan pembayaran Midtrans gagal: Access denied. Please check the server key.',
            ]);

        $this->assertDatabaseCount('simak_payment_transactions', 0, 'simak_main');
    }

    public function test_checkout_rejects_production_keys_when_sandbox_is_selected(): void
    {
        DB::connection('simak_main')->table('simak_payment_gateways')
            ->where('gateway', 'midtrans')
            ->update([
                'client_key' => 'Mid-client-production-test',
                'credentials_encrypted' => Crypt::encryptString(json_encode([
                    'server_key' => 'Mid-server-production-test',
                ])),
            ]);
        Http::fake();
        $this->actingAs($this->studentUser());

        $response = $this->from(route('student-payments.index'))
            ->post(route('student-payments.midtrans', 'ukt-1'), ['jenis' => 'ukt'])
            ->assertRedirect(route('student-payments.index'));
        $this->assertSame(
            'Kunci Midtrans yang tersimpan berformat Production, tetapi lingkungan diatur ke Sandbox. Pilih lingkungan yang sesuai dengan Client key dan Server key.',
            $response->getSession()->get('errors')->first('payment')
        );

        Http::assertNothingSent();
        $this->assertDatabaseCount('simak_payment_transactions', 0, 'simak_main');
    }

    public function test_signed_settlement_notification_marks_only_the_matching_bill_paid(): void
    {
        $orderId = 'SIMAK-UANG-KULIAH-test';
        DB::connection('simak_main')->table('simak_payment_transactions')->insert([
            'id' => 'transaction-1',
            'gateway' => 'midtrans',
            'payment_type' => 'ukt',
            'payment_id' => 'ukt-1',
            'student_id' => 'student-1',
            'order_id' => $orderId,
            'gross_amount' => 1502000,
            'service_fee' => 2000,
            'transaction_status' => 'pending',
        ]);
        $grossAmount = '1502000.00';
        $notification = [
            'order_id' => $orderId,
            'status_code' => '200',
            'gross_amount' => $grossAmount,
            'signature_key' => hash('sha512', $orderId.'200'.$grossAmount.'server-test'),
            'transaction_status' => 'settlement',
            'transaction_id' => 'midtrans-transaction-1',
        ];

        $this->postJson('/api/payments/midtrans/notification', $notification)
            ->assertOk()
            ->assertJson(['message' => 'Notifikasi Midtrans diterima.']);

        $payment = DB::connection('simak_main')->table('s1m4k_pembayaranukt')->where('id', 'ukt-1')->first();
        $this->assertSame(1, (int) $payment->lunas);
        $this->assertSame('MIDTRANS', $payment->nama_bank);
        $this->assertSame($orderId, $payment->trx_id);
        $this->assertSame(1500000, (int) $payment->nominal);
        $this->assertSame('settlement', DB::connection('simak_main')->table('simak_payment_transactions')->value('transaction_status'));
        $this->assertSame('calendar-1', DB::connection('simak_main')->table('s1m4k_registrasiulang')
            ->where('id_mahasiswa', 'student-1')->value('id_kalender'));
        $this->assertSame(1, DB::connection('simak_main')->table('s1m4k_registrasiulang')->count());
        $this->actingAs($this->studentUser())
            ->get(route('student-payments.billing', ['type' => 'ukt', 'payment' => 'ukt-1']))
            ->assertOk()
            ->assertSee('Nominal tagihan UKT / IPI')
            ->assertSee('Rp 1.500.000')
            ->assertSee('Biaya layanan Midtrans')
            ->assertSee('Rp 2.000')
            ->assertSee('Total pembayaran')
            ->assertSee('Rp 1.502.000');

        $this->postJson('/api/payments/midtrans/notification', $notification)->assertOk();
        $this->assertSame('settlement', DB::connection('simak_main')->table('simak_payment_transactions')->value('transaction_status'));
        $this->assertSame(1, DB::connection('simak_main')->table('s1m4k_registrasiulang')->count());
    }

    public function test_ipi_settlement_callback_updates_finance_and_student_payment_views(): void
    {
        $orderId = 'SIMAK-IPI-test';
        DB::connection('simak_main')->table('s1m4k_pembayaranipi')->insert([
            'id' => 'ipi-1',
            'id_mahasiswa' => 'student-1',
            'nominal' => 7000000,
            'lunas' => 0,
        ]);
        DB::connection('simak_main')->table('simak_payment_transactions')->insert([
            'id' => 'transaction-ipi-1',
            'gateway' => 'midtrans',
            'payment_type' => 'ipi',
            'payment_id' => 'ipi-1',
            'student_id' => 'student-1',
            'order_id' => $orderId,
            'gross_amount' => 7000000,
            'transaction_status' => 'pending',
        ]);
        $grossAmount = '7000000.00';
        $notification = [
            'order_id' => $orderId,
            'status_code' => '200',
            'gross_amount' => $grossAmount,
            'signature_key' => hash('sha512', $orderId.'200'.$grossAmount.'server-test'),
            'transaction_status' => 'settlement',
            'transaction_id' => 'midtrans-ipi-transaction-1',
        ];

        $this->postJson('/api/payments/midtrans/notification', $notification)
            ->assertOk();

        $payment = DB::connection('simak_main')->table('s1m4k_pembayaranipi')->where('id', 'ipi-1')->first();
        $this->assertSame(1, (int) $payment->lunas);
        $this->assertSame('MIDTRANS', $payment->nama_bank);
        $this->assertSame($orderId, $payment->trx_id);
        $this->assertSame('settlement', DB::connection('simak_main')
            ->table('simak_payment_transactions')->where('order_id', $orderId)->value('transaction_status'));

        $this->actingAs($this->studentUser())
            ->get(route('student-payments.index'))
            ->assertOk()
            ->assertSee('student-payments-status is-paid', false);

        $this->actingAs(new GenericUser(['id' => 'finance-1', 'role' => 'admin']))
            ->getJson(route('finance.ipi.data', ['draw' => 1, 'start' => 0, 'length' => 25]))
            ->assertOk()
            ->assertJsonPath('data.0.lunas', 1)
            ->assertJsonPath('data.0.payment_reference', $orderId)
            ->assertJsonPath('data.0.payment_method', 'Midtrans')
            ->assertJsonPath('data.0.midtrans_status', 'settlement');
    }

    public function test_student_can_check_status_through_midtrans_and_settle_the_matching_bill(): void
    {
        $orderId = 'SIMAK-UKT-status-check';
        DB::connection('simak_main')->table('simak_payment_transactions')->insert([
            'id' => 'transaction-status-check',
            'gateway' => 'midtrans',
            'payment_type' => 'ukt',
            'payment_id' => 'ukt-1',
            'student_id' => 'student-1',
            'order_id' => $orderId,
            'gross_amount' => 1500000,
            'transaction_status' => 'pending',
        ]);
        Http::fake([
            'https://api.sandbox.midtrans.com/v2/'.$orderId.'/status' => Http::response([
                'order_id' => $orderId,
                'status_code' => '200',
                'gross_amount' => '1500000.00',
                'transaction_status' => 'settlement',
                'transaction_id' => 'provider-transaction-1',
            ]),
        ]);
        $this->actingAs($this->studentUser())
            ->from(route('student-payments.index'))
            ->post(route('student-payments.midtrans.status', 'ukt-1'), ['jenis' => 'ukt'])
            ->assertRedirect(route('student-payments.index'))
            ->assertSessionHas('status');

        $payment = DB::connection('simak_main')->table('s1m4k_pembayaranukt')->where('id', 'ukt-1')->first();
        $transaction = DB::connection('simak_main')->table('simak_payment_transactions')->first();
        $this->assertSame(1, (int) $payment->lunas);
        $this->assertSame('settlement', $transaction->transaction_status);
        $this->assertSame('provider-transaction-1', $transaction->transaction_id);
        $this->assertSame(1, DB::connection('simak_main')->table('s1m4k_registrasiulang')->count());
        Http::assertSent(fn ($request): bool => $request->method() === 'GET'
            && $request->url() === 'https://api.sandbox.midtrans.com/v2/'.$orderId.'/status');
    }

    public function test_invalid_midtrans_signature_cannot_mark_a_bill_paid(): void
    {
        DB::connection('simak_main')->table('simak_payment_transactions')->insert([
            'id' => 'transaction-1',
            'gateway' => 'midtrans',
            'payment_type' => 'ukt',
            'payment_id' => 'ukt-1',
            'student_id' => 'student-1',
            'order_id' => 'SIMAK-UANG-KULIAH-test',
            'gross_amount' => 1500000,
            'transaction_status' => 'pending',
        ]);

        $this->postJson('/api/payments/midtrans/notification', [
            'order_id' => 'SIMAK-UANG-KULIAH-test',
            'status_code' => '200',
            'gross_amount' => '1500000.00',
            'signature_key' => 'invalid',
            'transaction_status' => 'settlement',
        ])->assertForbidden();

        $this->assertSame(0, (int) DB::connection('simak_main')->table('s1m4k_pembayaranukt')->where('id', 'ukt-1')->value('lunas'));
    }

    private function studentUser(): GenericUser
    {
        return new GenericUser([
            'id' => 'student-1',
            'legacy_id' => 'student-1',
            'legacy_type' => 'mahasiswa',
            'login' => '3001',
            'name' => 'Mahasiswa Pertama',
            'role' => 'mahasiswa',
        ]);
    }
}
