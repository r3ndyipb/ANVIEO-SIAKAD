<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BootstrapAdminCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.connections.simak_main' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
        ]);
        DB::purge('simak_main');

        Schema::connection('simak_main')->create('s1m4k_admin', function (Blueprint $table): void {
            $table->bigIncrements('id_admin');
            $table->string('username');
            $table->string('password');
            $table->string('level')->nullable();
            $table->string('blokir')->default('N');
            $table->string('nama_lengkap')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function test_it_creates_one_admin_with_a_hashed_operator_supplied_password(): void
    {
        $this->artisan('siakad:bootstrap-admin')
            ->expectsConfirmation('Buat akun admin awal pada database [:memory:]?', 'yes')
            ->expectsQuestion('Password admin (minimal 8 karakter)', 'a-strong-password')
            ->expectsQuestion('Konfirmasi password admin', 'a-strong-password')
            ->expectsOutput('Akun admin berhasil dibuat. Password tidak disimpan dalam teks biasa.')
            ->assertExitCode(0);

        $admin = DB::connection('simak_main')->table('s1m4k_admin')->first();

        $this->assertSame(1, DB::connection('simak_main')->table('s1m4k_admin')->count());
        $this->assertSame('admin', $admin->username);
        $this->assertSame('admin', $admin->level);
        $this->assertSame('N', $admin->blokir);
        $this->assertTrue(Hash::check('a-strong-password', $admin->password));
    }

    public function test_it_refuses_to_seed_an_admin_table_that_is_not_empty(): void
    {
        DB::connection('simak_main')->table('s1m4k_admin')->insert([
            'username' => 'existing',
            'password' => 'existing-hash',
        ]);

        $this->artisan('siakad:bootstrap-admin')
            ->expectsOutput('Tabel s1m4k_admin harus kosong. Perintah ini tidak mengubah akun yang sudah ada.')
            ->assertExitCode(1);

        $this->assertSame(1, DB::connection('simak_main')->table('s1m4k_admin')->count());
    }

    public function test_it_rejects_a_password_shorter_than_eight_characters(): void
    {
        $this->artisan('siakad:bootstrap-admin')
            ->expectsConfirmation('Buat akun admin awal pada database [:memory:]?', 'yes')
            ->expectsQuestion('Password admin (minimal 8 karakter)', 'admin')
            ->expectsOutput('Password harus berisi 8 sampai 100 karakter.')
            ->assertExitCode(1);

        $this->assertSame(0, DB::connection('simak_main')->table('s1m4k_admin')->count());
    }
}
