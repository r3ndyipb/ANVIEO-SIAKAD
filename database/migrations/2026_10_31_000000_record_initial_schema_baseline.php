<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = DB::connection('simak_main');
        if (! Schema::connection('simak_main')->hasTable('s1m4k_admin')) {
            $driver = $connection->getDriverName();
            $schemaFile = $driver === 'pgsql'
                ? database_path('schema/siakad_main_schema_pgsql.sql')
                : database_path('schema/siakad_main_schema.sql');

            if (file_exists($schemaFile)) {
                $sql = file_get_contents($schemaFile);
                $connection->unprepared($sql);
            }
        }
    }

    public function down(): void
    {
        throw new LogicException('The initial database schema cannot be rolled back. Restore a database backup instead.');
    }
};
