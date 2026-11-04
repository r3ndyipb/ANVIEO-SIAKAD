<?php

namespace App\Services;

use App\Exceptions\InvalidMidtransSignature;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class MidtransSnapService
{
    public function __construct(private readonly PaymentGatewaySettings $settings) {}

    public function testConnection(): void
    {
        $gateway = $this->settings->get('midtrans');
        $serverKey = $gateway['credentials']['server_key'] ?? null;
        if (! is_string($gateway['client_key']) || trim($gateway['client_key']) === ''
            || ! is_string($serverKey) || trim($serverKey) === '') {
            throw new RuntimeException('Client key dan Server key Midtrans harus disimpan sebelum melakukan pengecekan.');
        }

        $environment = $gateway['environment'];
        $environmentName = $environment === 'production' ? 'Production' : 'Sandbox';
        $otherEnvironmentName = $environment === 'production' ? 'Sandbox' : 'Production';
        $otherEnvironmentPrefixes = $environment === 'production'
            ? ['SB-Mid-client-', 'SB-Mid-server-']
            : ['Mid-client-', 'Mid-server-'];
        foreach ([$gateway['client_key'], $serverKey] as $index => $key) {
            if (Str::startsWith($key, $otherEnvironmentPrefixes)) {
                $keyType = $index === 0 ? 'Client key' : 'Server key';
                throw new RuntimeException(
                    "{$keyType} berformat {$otherEnvironmentName}, tetapi lingkungan diatur ke {$environmentName}. Pilih lingkungan yang sesuai dengan kedua kunci."
                );
            }
        }

        $baseUrl = $environment === 'production'
            ? 'https://app.midtrans.com'
            : 'https://app.sandbox.midtrans.com';
        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withBasicAuth($serverKey, '')
                ->timeout(15)
                ->post($baseUrl.'/snap/v1/transactions', []);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Tidak dapat terhubung ke layanan Midtrans.', previous: $exception);
        }

        $body = $response->json();
        $providerMessage = $this->providerErrorMessage($body);
        if ($response->status() === 400 && is_array($body)
            && is_array($body['error_messages'] ?? null) && $body['error_messages'] !== []) {
            Log::info('Midtrans connection test authenticated successfully', [
                'environment' => $environment,
                'http_status' => $response->status(),
            ]);

            return;
        }

        Log::warning('Midtrans connection test failed', [
            'environment' => $environment,
            'http_status' => $response->status(),
            'content_type' => $response->header('Content-Type'),
            'response_fields' => is_array($body) ? array_keys($body) : [],
        ]);
        if (in_array($response->status(), [401, 403], true)) {
            throw new RuntimeException($providerMessage !== ''
                ? 'Midtrans menolak autentikasi: '.mb_substr($providerMessage, 0, 300)
                : 'Midtrans menolak autentikasi. Periksa Client key, Server key, dan lingkungan yang dipilih.');
        }
        if ($response->status() === 400) {
            throw new RuntimeException('Midtrans menerima koneksi tetapi respons validasi tidak sesuai harapan. Periksa konfigurasi dan coba lagi.');
        }
        throw new RuntimeException(
            'Pengecekan koneksi Midtrans gagal'.($providerMessage !== '' ? ': '.mb_substr($providerMessage, 0, 300) : ' (HTTP '.$response->status().').')
        );
    }

    /**
     * @return array{order_id: string, redirect_url: string, service_fee: int, gross_amount: int, expires_at: string}
     */
    public function createCheckout(
        string $type,
        string $paymentId,
        string $studentId,
        string $studentName,
        int $amount,
        string $description,
        ?int $expiresInHours = null,
        ?string $customerEmail = null,
        ?string $customerPhone = null,
        ?string $returnUrl = null
    ): array {
        if (! in_array($type, ['ukt', 'ipi', 'manual'], true) || $amount < 1
            || ($type === 'manual' && ($expiresInHours === null || $expiresInHours < 1 || $expiresInHours > 720))) {
            throw new RuntimeException('Data tagihan untuk pembayaran Midtrans tidak valid.');
        }
        $gateway = $this->settings->get('midtrans');
        $serverKey = $gateway['credentials']['server_key'] ?? null;
        if (! $gateway['enabled'] || ! is_string($serverKey) || trim($serverKey) === '') {
            throw new RuntimeException('Payment gateway Midtrans belum diaktifkan atau kredensialnya belum lengkap.');
        }
        $environment = $gateway['environment'];
        $environmentName = $environment === 'production' ? 'Production' : 'Sandbox';
        $otherEnvironmentName = $environment === 'production' ? 'Sandbox' : 'Production';
        $otherEnvironmentPrefixes = $environment === 'production'
            ? ['SB-Mid-client-', 'SB-Mid-server-']
            : ['Mid-client-', 'Mid-server-'];
        $configuredKeys = [$gateway['client_key'], $serverKey];
        foreach ($configuredKeys as $key) {
            if (is_string($key) && Str::startsWith($key, $otherEnvironmentPrefixes)) {
                throw new RuntimeException(
                    "Kunci Midtrans yang tersimpan berformat {$otherEnvironmentName}, tetapi lingkungan diatur ke {$environmentName}. Pilih lingkungan yang sesuai dengan Client key dan Server key."
                );
            }
        }
        if (! Schema::connection('simak_main')->hasTable('simak_payment_transactions')) {
            throw new RuntimeException('Tabel transaksi payment gateway belum tersedia. Jalankan SQL upgrade database terlebih dahulu.');
        }
        if (! Schema::connection('simak_main')->hasColumn('simak_payment_transactions', 'service_fee')) {
            throw new RuntimeException('Kolom biaya layanan transaksi belum tersedia. Jalankan database/manual/midtrans_service_fee.sql terlebih dahulu.');
        }

        $serviceFee = max(0, (int) $gateway['service_fee']);
        if ($amount > PHP_INT_MAX - $serviceFee) {
            throw new RuntimeException('Total pembayaran Midtrans melebihi batas nominal yang didukung.');
        }
        $grossAmount = $amount + $serviceFee;
        $orderId = 'SIAKAD-'.strtoupper($type).'-'.Str::uuid();
        $baseUrl = $gateway['environment'] === 'production'
            ? 'https://app.midtrans.com'
            : 'https://app.sandbox.midtrans.com';
        $checkoutStartedAt = now();
        $expiresAt = $checkoutStartedAt->copy()->addHours($expiresInHours ?? 24);
        $customerDetails = [
            'first_name' => mb_substr($studentName, 0, 50),
        ];
        if (filled($customerEmail)) {
            $customerDetails['email'] = $customerEmail;
        }
        if (filled($customerPhone)) {
            $customerDetails['phone'] = $customerPhone;
        }

        try {
            $checkoutPayload = [
                'transaction_details' => [
                    'order_id' => $orderId,
                    'gross_amount' => $grossAmount,
                ],
                'item_details' => array_values(array_filter([
                    [
                        'id' => $paymentId,
                        'price' => $amount,
                        'quantity' => 1,
                        'name' => mb_substr($description, 0, 50),
                    ],
                    $serviceFee > 0 ? [
                        'id' => 'midtrans-service-fee',
                        'price' => $serviceFee,
                        'quantity' => 1,
                        'name' => 'Biaya layanan Midtrans',
                    ] : null,
                ])),
                'customer_details' => $customerDetails,
                'callbacks' => [
                    'finish' => $returnUrl ?? route('student-payments.index'),
                    'unfinish' => $returnUrl ?? route('student-payments.index'),
                    'error' => $returnUrl ?? route('student-payments.index'),
                ],
            ];
            if ($type === 'manual') {
                $checkoutPayload['expiry'] = [
                    'start_time' => $checkoutStartedAt->format('Y-m-d H:i:s O'),
                    'unit' => 'hours',
                    'duration' => $expiresInHours,
                ];
            }

            $response = Http::acceptJson()
                ->asJson()
                ->withBasicAuth($serverKey, '')
                ->timeout(20)
                ->post($baseUrl.'/snap/v1/transactions', $checkoutPayload);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Tidak dapat terhubung ke layanan Midtrans.', previous: $exception);
        }

        $body = $response->json();
        $token = is_array($body) ? ($body['token'] ?? null) : null;
        $redirectUrl = is_array($body) ? ($body['redirect_url'] ?? null) : null;
        if (! $response->successful() || ! is_string($token) || $token === '' || ! is_string($redirectUrl)
            || ! filter_var($redirectUrl, FILTER_VALIDATE_URL)
            || ! str_starts_with($redirectUrl, $baseUrl.'/')) {
            $providerMessage = $this->providerErrorMessage($body);

            Log::warning('Midtrans Snap checkout response was rejected', [
                'http_status' => $response->status(),
                'content_type' => $response->header('Content-Type'),
                'response_fields' => is_array($body) ? array_keys($body) : [],
                'has_token' => is_string($token) && $token !== '',
                'has_redirect_url' => is_string($redirectUrl) && $redirectUrl !== '',
            ]);

            if (! $response->successful()) {
                $message = $providerMessage !== ''
                    ? mb_substr($providerMessage, 0, 500)
                    : "Midtrans menolak permintaan (HTTP {$response->status()}). Periksa lingkungan dan server key.";
            } else {
                $message = "Respons checkout Midtrans tidak lengkap atau tidak valid (HTTP {$response->status()}).";
            }

            throw new RuntimeException('Pembuatan pembayaran Midtrans gagal: '.$message);
        }

        $now = now();
        DB::connection('simak_main')->table('simak_payment_transactions')->insert([
            'id' => (string) Str::uuid(),
            'gateway' => 'midtrans',
            'payment_type' => $type,
            'payment_id' => $paymentId,
            'student_id' => $studentId,
            'order_id' => $orderId,
            'gross_amount' => $grossAmount,
            'service_fee' => $serviceFee,
            'transaction_status' => 'pending',
            'snap_token' => $token,
            'redirect_url' => $redirectUrl,
            'expires_at' => $expiresAt,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'order_id' => $orderId,
            'redirect_url' => $redirectUrl,
            'service_fee' => $serviceFee,
            'gross_amount' => $grossAmount,
            'expires_at' => $expiresAt->toDateTimeString(),
        ];
    }

    private function providerErrorMessage(mixed $body): string
    {
        if (! is_array($body)) {
            return '';
        }
        $providerMessages = is_array($body['error_messages'] ?? null)
            ? array_filter($body['error_messages'], 'is_string')
            : [];
        if ($providerMessages !== []) {
            return implode('; ', $providerMessages);
        }
        foreach (['status_message', 'message', 'error'] as $messageField) {
            if (is_string($body[$messageField] ?? null) && trim($body[$messageField]) !== '') {
                return trim($body[$messageField]);
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $notification
     */
    public function receiveNotification(array $notification): void
    {
        $orderId = $notification['order_id'] ?? null;
        $statusCode = $notification['status_code'] ?? null;
        $grossAmount = $notification['gross_amount'] ?? null;
        $signature = $notification['signature_key'] ?? null;
        if (! is_string($orderId) || ! is_string($statusCode) || ! is_string($grossAmount)
            || ! is_string($signature) || ! preg_match('/^\d+(?:\.00)?$/', $grossAmount)) {
            throw new RuntimeException('Notifikasi pembayaran Midtrans tidak memiliki format yang valid.');
        }

        $gateway = $this->settings->get('midtrans');
        $serverKey = $gateway['credentials']['server_key'] ?? null;
        if (! is_string($serverKey) || $serverKey === '') {
            throw new RuntimeException('Kredensial Midtrans untuk verifikasi notifikasi belum tersedia.');
        }
        $expectedSignature = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);
        if (! hash_equals($expectedSignature, $signature)) {
            throw new InvalidMidtransSignature('Signature notifikasi Midtrans tidak valid.');
        }

        $this->applyTransactionStatus($orderId, $notification, true);
    }

    public function checkStatusForPayment(string $type, string $paymentId, ?string $studentId = null): string
    {
        if (! in_array($type, ['ukt', 'ipi', 'manual'], true)) {
            throw new RuntimeException('Jenis tagihan untuk pemeriksaan Midtrans tidak valid.');
        }
        if (! Schema::connection('simak_main')->hasTable('simak_payment_transactions')) {
            throw new RuntimeException('Tabel transaksi payment gateway belum tersedia. Jalankan SQL upgrade database terlebih dahulu.');
        }

        $query = DB::connection('simak_main')->table('simak_payment_transactions')
            ->where('gateway', 'midtrans')
            ->where('payment_type', $type)
            ->where('payment_id', $paymentId);
        if ($studentId !== null) {
            $query->where('student_id', $studentId);
        }
        $transaction = $query->orderByDesc('created_at')->orderByDesc('updated_at')->first();
        if ($transaction === null) {
            throw new RuntimeException('Transaksi Midtrans untuk tagihan ini belum tersedia.');
        }

        $gateway = $this->settings->get('midtrans');
        $serverKey = $gateway['credentials']['server_key'] ?? null;
        if (! is_string($serverKey) || trim($serverKey) === '') {
            throw new RuntimeException('Kredensial Midtrans untuk pemeriksaan status belum tersedia.');
        }
        $baseUrl = $gateway['environment'] === 'production'
            ? 'https://api.midtrans.com'
            : 'https://api.sandbox.midtrans.com';

        try {
            $response = Http::acceptJson()
                ->withBasicAuth($serverKey, '')
                ->timeout(20)
                ->get($baseUrl.'/v2/'.rawurlencode((string) $transaction->order_id).'/status');
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Tidak dapat terhubung ke layanan status Midtrans.', previous: $exception);
        }

        $body = $response->json();
        if (! $response->successful() || ! is_array($body)
            || ($body['order_id'] ?? null) !== $transaction->order_id
            || (string) ($body['status_code'] ?? '') !== '200') {
            $message = is_array($body) && isset($body['status_message']) && is_string($body['status_message'])
                ? $body['status_message']
                : 'Respons pemeriksaan status Midtrans tidak valid.';
            throw new RuntimeException('Pemeriksaan status Midtrans gagal: '.$message);
        }

        $this->applyTransactionStatus((string) $transaction->order_id, $body, false);

        return (string) $body['transaction_status'];
    }

    /**
     * @param  array<string, mixed>  $providerStatus
     */
    private function applyTransactionStatus(string $orderId, array $providerStatus, bool $isNotification): void
    {
        $grossAmount = $providerStatus['gross_amount'] ?? null;
        if (! is_string($grossAmount) || ! preg_match('/^\d+(?:\.00)?$/', $grossAmount)) {
            throw new RuntimeException('Status pembayaran Midtrans tidak memiliki nominal yang valid.');
        }

        $connection = DB::connection('simak_main');
        if (! Schema::connection('simak_main')->hasColumn('simak_payment_transactions', 'service_fee')) {
            throw new RuntimeException('Kolom biaya layanan transaksi belum tersedia. Jalankan database/manual/midtrans_service_fee.sql terlebih dahulu.');
        }
        $connection->transaction(function () use ($connection, $providerStatus, $orderId, $grossAmount, $isNotification): void {
            $transaction = $connection->table('simak_payment_transactions')
                ->where('gateway', 'midtrans')
                ->where('order_id', $orderId)
                ->lockForUpdate()
                ->first();
            if ($transaction === null) {
                throw new RuntimeException('Transaksi Midtrans tidak ditemukan.');
            }

            $notifiedAmount = (int) explode('.', $grossAmount, 2)[0];
            $status = (string) ($providerStatus['transaction_status'] ?? '');
            $now = now();
            if ($notifiedAmount !== (int) $transaction->gross_amount) {
                $values = [
                    'transaction_status' => 'amount_mismatch',
                    'updated_at' => $now,
                ];
                if ($isNotification) {
                    $values['notification_received_at'] = $now;
                }
                $connection->table('simak_payment_transactions')->where('id', $transaction->id)->update($values);

                return;
            }

            if (! in_array($status, [
                'pending', 'authorize', 'capture', 'settlement', 'deny', 'cancel', 'expire',
                'refund', 'partial_refund', 'chargeback', 'partial_chargeback', 'failure',
            ], true)) {
                throw new RuntimeException('Status transaksi pada notifikasi Midtrans tidak dikenal.');
            }

            $storedStatus = $transaction->transaction_status === 'settlement' && $status !== 'refund'
                ? 'settlement'
                : $status;
            $values = [
                'transaction_status' => $storedStatus,
                'transaction_id' => is_string($providerStatus['transaction_id'] ?? null) ? $providerStatus['transaction_id'] : null,
                'updated_at' => $now,
            ];
            if ($isNotification) {
                $values['notification_received_at'] = $now;
            }
            $connection->table('simak_payment_transactions')->where('id', $transaction->id)->update($values);

            $settled = $status === 'settlement'
                || ($status === 'capture' && ($providerStatus['fraud_status'] ?? null) === 'accept');
            if (! $settled) {
                return;
            }

            if ($transaction->payment_type === 'manual') {
                $updated = $connection->table('simak_manual_bills')
                    ->where('id', $transaction->payment_id)
                    ->update([
                        'checkout_status' => 'paid',
                        'updated_at' => $now,
                    ]);
                if ($updated !== 1) {
                    throw new RuntimeException('Data billing manual untuk transaksi Midtrans tidak ditemukan.');
                }

                return;
            }

            $table = match ($transaction->payment_type) {
                'ukt' => 's1m4k_pembayaranukt',
                'ipi' => 's1m4k_pembayaranipi',
                default => throw new RuntimeException('Jenis tagihan pada transaksi Midtrans tidak valid.'),
            };
            $paymentColumns = ['lunas', 'nominal', 'nama_bank', 'trx_id'];
            if ($transaction->payment_type === 'ukt') {
                $paymentColumns[] = 'tahun';
            }
            $payment = $connection->table($table)
                ->where('id', $transaction->payment_id)
                ->where('id_mahasiswa', $transaction->student_id)
                ->first($paymentColumns);
            if ($payment !== null
                && (int) $payment->lunas === 1
                && strtoupper((string) $payment->nama_bank) === 'MIDTRANS'
                && (string) $payment->trx_id === (string) $transaction->order_id) {
                return;
            }
            $baseAmount = (int) $transaction->gross_amount - (int) $transaction->service_fee;
            if ($baseAmount < 1) {
                throw new RuntimeException('Nominal dasar transaksi Midtrans tidak valid.');
            }
            $updated = $connection->table($table)
                ->where('id', $transaction->payment_id)
                ->where('id_mahasiswa', $transaction->student_id)
                ->where('lunas', 0)
                ->where('nominal', $baseAmount)
                ->update([
                    'nama_bank' => 'MIDTRANS',
                    'trx_id' => $transaction->order_id,
                    'lunas' => 1,
                    'tanggal_bayar' => $now->toDateTimeString(),
                ]);
            if ($updated !== 1) {
                $connection->table('simak_payment_transactions')->where('id', $transaction->id)->update([
                    'transaction_status' => 'payment_conflict',
                    'updated_at' => $now,
                ]);

                return;
            }

            if ($transaction->payment_type === 'ukt') {
                $student = $connection->table('s1m4k_mastermhs')
                    ->where('id_mahasiswa', $transaction->student_id)
                    ->first(['id_perguruan_tinggi', 'id_fakultas', 'id_prodi']);
                if ($student === null) {
                    throw new RuntimeException('Data mahasiswa untuk registrasi ulang tidak ditemukan.');
                }
                $calendar = $connection->table('s1m4k_kalender')
                    ->where('tahun', $payment->tahun)
                    ->where('id_prodi', $student->id_prodi)
                    ->orderByDesc('status')
                    ->first(['id_kalender']);
                if ($calendar === null) {
                    throw new RuntimeException('Kalender akademik mahasiswa tidak ditemukan untuk tagihan uang kuliah.');
                }

                $registered = $connection->table('s1m4k_registrasiulang')
                    ->where('id_kalender', $calendar->id_kalender)
                    ->where('id_mahasiswa', $transaction->student_id)
                    ->exists();
                if (! $registered) {
                    $connection->table('s1m4k_registrasiulang')->insert([
                        'id_registrasi' => (string) Str::uuid(),
                        'id_perguruan_tinggi' => $student->id_perguruan_tinggi,
                        'id_fakultas' => $student->id_fakultas,
                        'id_prodi' => $student->id_prodi,
                        'id_mahasiswa' => $transaction->student_id,
                        'id_kalender' => $calendar->id_kalender,
                        'tgl_registasi' => today()->toDateString(),
                        'user' => 'MIDTRANS',
                    ]);
                }
            }
        });
    }
}
