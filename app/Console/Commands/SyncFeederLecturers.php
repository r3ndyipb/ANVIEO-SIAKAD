<?php

namespace App\Console\Commands;

use App\Services\LegacyFeederService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class SyncFeederLecturers extends Command
{
    protected $signature = 'siakad:sync-feeder-lecturers';

    protected $aliases = ['simak:sync-feeder-lecturers'];

    protected $description = 'Sinkronkan data dosen dan penugasan terbaru dari Feeder ke SIMAK';

    public function handle(LegacyFeederService $feeder): int
    {
        $lock = Cache::lock('simak:sync-feeder-lecturers', 21600);
        if (! $lock->get()) {
            $this->warn('Sinkronisasi dosen Feeder lain masih berjalan; proses ini dilewati.');

            return self::SUCCESS;
        }

        try {
            $result = $feeder->syncAllLecturers();
            $this->info(sprintf(
                'Sinkronisasi dosen selesai: %d tersinkron (%d baru, %d diperbarui), %d tanpa homebase SIMAK, %d dilewati (%d tanpa NIDN/NIP).',
                $result['synced'],
                $result['created'],
                $result['updated'],
                $result['synced_without_homebase'],
                $result['skipped'],
                $result['skipped_identifier'],
            ));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Sinkronisasi dosen Feeder gagal: '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }
}
