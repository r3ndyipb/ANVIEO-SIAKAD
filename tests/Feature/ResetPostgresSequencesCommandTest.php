<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ResetPostgresSequencesCommandTest extends TestCase
{
    public function test_it_handles_non_pgsql_driver_safely(): void
    {
        config([
            'database.connections.simak_main' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
        ]);
        DB::purge('simak_main');

        $this->artisan('siakad:reset-sequences')
            ->expectsOutputToContain('Reset sequence hanya diperlukan pada PostgreSQL.')
            ->assertExitCode(0);
    }
}
