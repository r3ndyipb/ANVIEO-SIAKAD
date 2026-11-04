<?php

namespace App\Console\Commands;

use App\Services\LegacyFeederService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class SyncFeederStudentRegistrations extends Command
{
    protected $signature = 'siakad:sync-feeder-student-registrations';

    protected $aliases = ['simak:sync-feeder-student-registrations'];

    protected $description = 'Perbarui ID registrasi mahasiswa dari Feeder ke SIMAK';

    public function handle(LegacyFeederService $feeder): int
    {
        $lock = Cache::lock('simak:sync-feeder-student-registrations', 21600);
        if (! $lock->get()) {
            $this->warn('Sinkronisasi ID registrasi mahasiswa Feeder lain masih berjalan; proses ini dilewati.');

            return self::SUCCESS;
        }

        try {
            $result = $feeder->syncStudentRegistrationIds(function (array $progress): void {
                if ($progress['event'] === 'start') {
                    $this->info(sprintf(
                        'Memulai sinkronisasi %d mahasiswa. Progres dilaporkan setiap 100 data.',
                        $progress['total'],
                    ));

                    return;
                }

                if ($progress['event'] === 'checking'
                    && ($progress['current'] === 1 || $progress['current'] % 100 === 0)) {
                    $this->line(sprintf(
                        'Sedang memeriksa %d/%d (NIM %s)...',
                        $progress['current'],
                        $progress['total'],
                        $progress['nim'],
                    ));

                    return;
                }

                if ($progress['event'] === 'completed'
                    && ($progress['current'] % 100 === 0 || $progress['current'] === $progress['total'])) {
                    $this->line(sprintf(
                        'Progres: %d/%d selesai; %d diperbarui, %d tidak berubah, %d gagal.',
                        $progress['current'],
                        $progress['total'],
                        $progress['updated'],
                        $progress['unchanged'],
                        $progress['failed'],
                    ));
                }
            });
            $this->info(sprintf(
                'Sinkronisasi mahasiswa selesai: %d diperiksa, %d diperbarui, %d tidak berubah, %d gagal.',
                $result['checked'],
                $result['updated'],
                $result['unchanged'],
                $result['failed'],
            ));

            foreach ($result['failures'] as $failure) {
                $this->warn(sprintf('NIM %s gagal: %s', $failure['nim'], $failure['reason']));
            }

            return $result['failed'] === 0 ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Sinkronisasi ID registrasi mahasiswa Feeder gagal: '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }
}
