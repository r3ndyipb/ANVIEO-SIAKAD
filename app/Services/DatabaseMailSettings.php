<?php

namespace App\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class DatabaseMailSettings
{
    public function configure(): bool
    {
        $connection = DB::connection('simak_main');
        if (! Schema::connection('simak_main')->hasTable('simak_mail_settings')) {
            Config::set('mail.default', 'log');
            app('mail.manager')->purge();

            return false;
        }

        $settings = $connection->table('simak_mail_settings')->where('id', 1)->first();
        if (! $settings || ! (bool) $settings->enabled) {
            Config::set('mail.default', 'log');
            app('mail.manager')->purge();

            return false;
        }

        $required = [
            'host' => $settings->host,
            'port' => $settings->port,
            'username' => $settings->username,
            'password_encrypted' => $settings->password_encrypted,
            'from_address' => $settings->from_address,
            'from_name' => $settings->from_name,
        ];
        foreach ($required as $key => $value) {
            if (! is_string($value) && ! is_int($value) || trim((string) $value) === '') {
                throw new RuntimeException('Pengaturan SMTP aktif belum lengkap: '.$key.'.');
            }
        }

        $password = Crypt::decryptString((string) $settings->password_encrypted);
        Config::set('mail.mailers.simak_database_smtp', [
            'transport' => 'smtp',
            'scheme' => $settings->scheme,
            'host' => $settings->host,
            'port' => (int) $settings->port,
            'username' => $settings->username,
            'password' => $password,
            'timeout' => 20,
            'local_domain' => parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost',
        ]);
        Config::set('mail.from', [
            'address' => $settings->from_address,
            'name' => $settings->from_name,
        ]);
        Config::set('mail.default', 'simak_database_smtp');
        app('mail.manager')->purge();

        return true;
    }

    public function sendTest(string $recipient): void
    {
        if (! $this->configure()) {
            throw new RuntimeException('Aktifkan dan simpan pengaturan SMTP terlebih dahulu.');
        }

        Mail::raw('Email uji konfigurasi SMTP Siakad berhasil dikirim.', function ($message) use ($recipient): void {
            $message->to($recipient)->subject('Uji konfigurasi SMTP Siakad');
        });
        DB::connection('simak_main')->table('simak_mail_settings')->where('id', 1)->update([
            'last_tested_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
