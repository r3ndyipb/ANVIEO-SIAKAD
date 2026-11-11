<?php

namespace Tests\Feature;

use Tests\TestCase;

class MigrateMysqlToPostgresCommandTest extends TestCase
{
    public function test_it_aborts_gracefully_when_source_cannot_connect(): void
    {
        config([
            'database.connections.invalid_source' => [
                'driver' => 'mysql',
                'host' => 'nonexistent.local',
                'database' => 'invalid_db',
                'username' => 'user',
                'password' => 'secret',
            ],
        ]);

        $this->artisan('siakad:migrate-mysql-to-pgsql', [
            '--source' => 'invalid_source',
            '--target' => 'simak_main',
        ])
            ->expectsOutputToContain('Gagal membuka koneksi database')
            ->assertExitCode(1);
    }
}
