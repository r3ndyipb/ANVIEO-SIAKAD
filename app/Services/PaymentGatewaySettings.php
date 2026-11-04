<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use JsonException;
use RuntimeException;

class PaymentGatewaySettings
{
    /**
     * @return array{gateway: string, enabled: bool, environment: string, client_key: ?string, service_fee: int, credentials: array<string, mixed>}
     */
    public function get(string $gateway): array
    {
        if ($gateway !== 'midtrans') {
            throw new RuntimeException('Gateway pembayaran yang didukung hanya Midtrans.');
        }

        if (! Schema::connection('simak_main')->hasTable('simak_payment_gateways')) {
            throw new RuntimeException('Tabel pengaturan payment gateway belum tersedia. Jalankan SQL upgrade database terlebih dahulu.');
        }

        $row = DB::connection('simak_main')->table('simak_payment_gateways')->where('gateway', $gateway)->first();
        $credentials = [];
        if (filled($row?->credentials_encrypted)) {
            try {
                $decoded = json_decode(Crypt::decryptString($row->credentials_encrypted), true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new RuntimeException('Kredensial payment gateway tidak dapat dibaca.', previous: $exception);
            }
            if (! is_array($decoded)) {
                throw new RuntimeException('Format kredensial payment gateway tidak valid.');
            }
            $credentials = $decoded;
        }

        return [
            'gateway' => $gateway,
            'enabled' => (bool) ($row?->enabled ?? false),
            'environment' => (string) ($row?->environment ?? 'sandbox'),
            'client_key' => $row?->client_key,
            'service_fee' => max(0, (int) ($row?->service_fee ?? 0)),
            'credentials' => $credentials,
        ];
    }

    public function isEnabled(string $gateway): bool
    {
        if ($gateway !== 'midtrans') {
            return false;
        }

        if (! Schema::connection('simak_main')->hasTable('simak_payment_gateways')) {
            return false;
        }

        return (bool) DB::connection('simak_main')->table('simak_payment_gateways')
            ->where('gateway', $gateway)
            ->value('enabled');
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function save(
        string $gateway,
        bool $enabled,
        string $environment,
        ?string $clientKey,
        array $credentials,
        int $serviceFee = 0
    ): void {
        if ($gateway !== 'midtrans') {
            throw new RuntimeException('Gateway pembayaran yang didukung hanya Midtrans.');
        }

        if (! Schema::connection('simak_main')->hasTable('simak_payment_gateways')) {
            throw new RuntimeException('Tabel pengaturan payment gateway belum tersedia. Jalankan SQL upgrade database terlebih dahulu.');
        }
        if (! Schema::connection('simak_main')->hasColumn('simak_payment_gateways', 'service_fee')) {
            throw new RuntimeException('Kolom biaya layanan Midtrans belum tersedia. Jalankan database/manual/midtrans_service_fee.sql terlebih dahulu.');
        }
        if ($serviceFee < 0) {
            throw new RuntimeException('Biaya layanan Midtrans tidak boleh negatif.');
        }

        $now = now();
        $existing = DB::connection('simak_main')->table('simak_payment_gateways')->where('gateway', $gateway)->exists();
        $values = [
            'enabled' => $enabled,
            'environment' => $environment,
            'client_key' => $clientKey,
            'service_fee' => $serviceFee,
            'credentials_encrypted' => Crypt::encryptString(json_encode($credentials, JSON_THROW_ON_ERROR)),
            'updated_at' => $now,
        ];
        if (! $existing) {
            $values['created_at'] = $now;
        }
        DB::connection('simak_main')->table('simak_payment_gateways')->updateOrInsert(
            ['gateway' => $gateway],
            $values,
        );
    }
}
