<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class BootstrapAdmin extends Command
{
    protected $signature = 'siakad:bootstrap-admin';

    protected $description = 'Buat akun admin awal secara interaktif pada database yang masih kosong';

    public function handle(): int
    {
        $connection = DB::connection('simak_main');

        if (! Schema::connection('simak_main')->hasTable('s1m4k_admin')) {
            $this->error('Tabel s1m4k_admin tidak ditemukan. Jalankan migrasi schema terlebih dahulu.');

            return self::FAILURE;
        }

        if ($connection->table('s1m4k_admin')->exists()) {
            $this->error('Tabel s1m4k_admin harus kosong. Perintah ini tidak mengubah akun yang sudah ada.');

            return self::FAILURE;
        }

        $database = $connection->getDatabaseName();
        if (! $this->confirm("Buat akun admin awal pada database [{$database}]?", false)) {
            $this->warn('Pembuatan akun admin dibatalkan.');

            return self::FAILURE;
        }

        $password = $this->secret('Password admin (minimal 8 karakter)');
        if (! is_string($password) || mb_strlen($password) < 8 || mb_strlen($password) > 100) {
            $this->error('Password harus berisi 8 sampai 100 karakter.');

            return self::FAILURE;
        }

        $passwordConfirmation = $this->secret('Konfirmasi password admin');
        if (! is_string($passwordConfirmation) || ! hash_equals($password, $passwordConfirmation)) {
            $this->error('Konfirmasi password tidak cocok.');

            return self::FAILURE;
        }

        $now = now();
        $connection->table('s1m4k_admin')->insert([
            'username' => 'admin',
            'password' => Hash::make($password),
            'nama_lengkap' => 'Administrator',
            'level' => 'admin',
            'blokir' => 'N',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->info('Akun admin berhasil dibuat. Password tidak disimpan dalam teks biasa.');

        return self::SUCCESS;
    }
}
