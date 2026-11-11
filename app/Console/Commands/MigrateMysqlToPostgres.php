<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateMysqlToPostgres extends Command
{
    protected $signature = 'siakad:migrate-mysql-to-pgsql
        {--source=mysql : Nama koneksi sumber MySQL (default: mysql)}
        {--target=simak_main : Nama koneksi tujuan PostgreSQL (default: simak_main)}
        {--chunk=1000 : Jumlah baris per batch penyalinan}
        {--table= : Batasi migrasi ke satu tabel tertentu}
        {--truncate : Kosongkan tabel tujuan sebelum menyalin data}
        {--skip-sequences : Lewati penyelarasan auto-increment sequence setelah salin}';

    protected $description = 'Salin data secara penuh dari database MySQL ke PostgreSQL dengan verifikasi row count dan sequence reset';

    public function handle(): int
    {
        $sourceConn = (string) $this->option('source');
        $targetConn = (string) $this->option('target');
        $chunkSize = max(100, (int) $this->option('chunk'));
        $shouldTruncate = (bool) $this->option('truncate');
        $specificTable = $this->option('table');

        $this->info("Menyiapkan migrasi data: [{$sourceConn}] -> [{$targetConn}]...");

        try {
            $source = DB::connection($sourceConn);
            $target = DB::connection($targetConn);

            // Test connections
            $source->getPdo();
            $target->getPdo();
        } catch (\Throwable $e) {
            $this->error('Gagal membuka koneksi database: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($target->getDriverName() !== 'pgsql') {
            $this->warn("Koneksi tujuan [{$targetConn}] menggunakan driver [{$target->getDriverName()}], bukan pgsql.");
        }

        // Disable constraints temporarily on target if supported
        if ($target->getDriverName() === 'pgsql') {
            $target->statement("SET session_replication_role = 'replica';");
        }

        $tables = $this->resolveTablesToMigrate($source, $specificTable);

        if (empty($tables)) {
            $this->warn('Tidak ada tabel yang ditemukan untuk dimigrasikan.');
            if ($target->getDriverName() === 'pgsql') {
                $target->statement("SET session_replication_role = 'origin';");
            }

            return self::SUCCESS;
        }

        $summary = [];

        foreach ($tables as $table) {
            $this->line("<comment>Memproses tabel:</comment> <info>{$table}</info>");

            if (! Schema::connection($targetConn)->hasTable($table)) {
                $summary[] = [$table, 0, 0, 'Dilewati (Tabel belum ada di target)'];
                continue;
            }

            if ($shouldTruncate) {
                if ($target->getDriverName() === 'pgsql') {
                    $target->statement("TRUNCATE TABLE \"{$table}\" CASCADE;");
                } else {
                    $target->table($table)->truncate();
                }
            }

            $sourceCount = $source->table($table)->count();
            $copiedCount = 0;

            if ($sourceCount > 0) {
                $bar = $this->output->createProgressBar($sourceCount);
                $bar->start();

                $source->table($table)->orderBy(
                    $this->resolveOrderColumn($source, $table)
                )->chunk($chunkSize, function ($rows) use ($target, $table, &$copiedCount, $bar): void {
                    $batch = array_map(function ($row) {
                        return (array) $row;
                    }, $rows->toArray());

                    $target->table($table)->insert($batch);
                    $count = count($batch);
                    $copiedCount += $count;
                    $bar->advance($count);
                });

                $bar->finish();
                $this->newLine();
            }

            $targetCount = $target->table($table)->count();
            $status = ($sourceCount === $targetCount) ? 'OK (Cocok)' : 'Peringatan (Selisih data)';

            $summary[] = [$table, $sourceCount, $targetCount, $status];
        }

        // Re-enable constraints
        if ($target->getDriverName() === 'pgsql') {
            $target->statement("SET session_replication_role = 'origin';");
        }

        $this->newLine();
        $this->table(['Tabel', 'Baris Sumber (MySQL)', 'Baris Target (Postgres)', 'Status Validasi'], $summary);

        // Reset sequences
        if (! $this->option('skip-sequences') && $target->getDriverName() === 'pgsql') {
            $this->newLine();
            $this->info('Menyelaraskan sequence auto-increment PostgreSQL...');
            $this->call('siakad:reset-sequences', ['--connection' => $targetConn]);
        }

        $this->info('Migrasi data selesai.');

        return self::SUCCESS;
    }

    private function resolveTablesToMigrate($connection, ?string $specificTable): array
    {
        if (filled($specificTable)) {
            return [(string) $specificTable];
        }

        $driver = $connection->getDriverName();
        if ($driver === 'mysql') {
            $rows = $connection->select('SHOW TABLES');
            $key = 'Tables_in_'.$connection->getDatabaseName();

            return array_map(function ($row) use ($key) {
                return ((array) $row)[$key] ?? array_values((array) $row)[0];
            }, $rows);
        }

        if ($driver === 'pgsql') {
            $rows = $connection->select("
                SELECT table_name
                FROM information_schema.tables
                WHERE table_schema = 'public' AND table_type = 'BASE TABLE'
                ORDER BY table_name
            ");

            return array_column($rows, 'table_name');
        }

        return Schema::connection($connection->getName())->getTableListing();
    }

    private function resolveOrderColumn($connection, string $table): string
    {
        $columns = Schema::connection($connection->getName())->getColumnListing($table);

        foreach (['id', 'id_'.$table, 'id_admin', 'id_mahasiswa', 'id_jadwal', 'id_matkul', 'id_prodi', 'created_at'] as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $candidate;
            }
        }

        return $columns[0] ?? '1';
    }
}
