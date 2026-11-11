<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetPostgresSequences extends Command
{
    protected $signature = 'siakad:reset-sequences
        {--connection=simak_main : Nama koneksi database (default: simak_main)}
        {--table= : Batasi sinkronisasi ke tabel tertentu}';

    protected $description = 'Sinkronkan nilai PostgreSQL sequence auto-increment dengan MAX(id) untuk mencegah collision';

    public function handle(): int
    {
        $connectionName = (string) $this->option('connection');
        $connection = DB::connection($connectionName);

        if ($connection->getDriverName() !== 'pgsql') {
            $this->info("Koneksi [{$connectionName}] menggunakan driver [{$connection->getDriverName()}]. Reset sequence hanya diperlukan pada PostgreSQL.");

            return self::SUCCESS;
        }

        $specificTable = $this->option('table');

        $query = "
            SELECT table_name, column_name, column_default
            FROM information_schema.columns
            WHERE table_schema = 'public'
              AND column_default LIKE 'nextval(%'
        ";
        $bindings = [];

        if (filled($specificTable)) {
            $query .= ' AND table_name = ?';
            $bindings[] = (string) $specificTable;
        }

        $query .= ' ORDER BY table_name, column_name';

        $columns = $connection->select($query, $bindings);

        if (empty($columns)) {
            $this->warn('Tidak ada kolom berbasis sequence yang ditemukan.');

            return self::SUCCESS;
        }

        $results = [];
        foreach ($columns as $col) {
            $table = (string) $col->table_name;
            $column = (string) $col->column_name;

            try {
                $maxId = $connection->table($table)->max($column);
                $count = $connection->table($table)->count();

                $targetVal = $maxId !== null ? (int) $maxId : 1;
                $isCalled = $count > 0;

                $seqResult = $connection->selectOne(
                    "SELECT pg_get_serial_sequence(?, ?) AS seq_name",
                    [$table, $column]
                );

                $seqName = $seqResult?->seq_name;

                if (! $seqName) {
                    $results[] = [$table, $column, $maxId ?? '-', '-', 'Gagal (Sequence tidak ditemukan)'];
                    continue;
                }

                $connection->statement(
                    'SELECT setval(?, ?, ?)',
                    [$seqName, $targetVal, $isCalled]
                );

                $results[] = [$table, $column, $maxId ?? 0, $seqName, 'OK'];
            } catch (\Throwable $e) {
                $results[] = [$table, $column, '-', '-', 'Error: '.$e->getMessage()];
            }
        }

        $this->table(['Tabel', 'Kolom', 'MAX ID', 'Sequence', 'Status'], $results);
        $this->info(sprintf('Berhasil menyinkronkan %d sequence pada koneksi [%s].', count($results), $connectionName));

        return self::SUCCESS;
    }
}
