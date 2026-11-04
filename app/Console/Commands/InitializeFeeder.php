<?php

namespace App\Console\Commands;

use App\Services\FeederInitializationService;
use App\Services\LegacyFeederService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class InitializeFeeder extends Command
{
    private const STATE_KEY = 'feeder-initialization:terminal-job';

    private const STATE_TTL_DAYS = 7;

    protected $signature = 'siakad:initialize-feeder
        {--url= : URL WebService Feeder (default dari Master PT)}
        {--username= : Username Feeder (default dari Master PT)}
        {--resume : Lanjutkan checkpoint terminal yang tersimpan}
        {--restart-current-stage : Ulangi tahap checkpoint saat ini dari awal (gunakan bersama --resume)}
        {--yes : Jalankan tanpa konfirmasi interaktif}';

    protected $aliases = ['simak:initialize-feeder'];

    protected $description = 'Jalankan seluruh tahapan inisialisasi Feeder secara berurutan dari terminal';

    public function handle(FeederInitializationService $initialization, LegacyFeederService $feeder): int
    {
        $lockHandle = fopen(storage_path('framework/cache/feeder-initialization-terminal.lock'), 'c');
        if ($lockHandle === false) {
            $this->error('File pengunci inisialisasi Feeder tidak dapat dibuka.');

            return self::FAILURE;
        }
        if (! flock($lockHandle, LOCK_EX | LOCK_NB)) {
            fclose($lockHandle);
            $this->error('Inisialisasi Feeder terminal lain sedang berjalan.');

            return self::FAILURE;
        }

        $state = null;
        try {
            if ($this->option('resume')) {
                $state = Cache::get(self::STATE_KEY);
                if (! is_array($state)) {
                    $this->error('Checkpoint terminal tidak tersedia atau sudah kedaluwarsa. Mulai proses baru setelah memeriksa kondisi data.');

                    return self::FAILURE;
                }
                $state['terminal_mode'] = true;
                unset($state['terminal_full_fetch']);
                if ($this->option('restart-current-stage')) {
                    $stageLabel = $initialization->stageLabel($state);
                    if (! $this->option('yes')
                        && ! $this->confirm(
                            "Tahap {$stageLabel} akan diulang dari awal dan data tahap tersebut dapat diperbarui.",
                        )) {
                        $this->warn('Pengulangan tahap dibatalkan.');

                        return self::FAILURE;
                    }
                    $state = $initialization->restartCurrentStage($state);
                    $this->info("Mengulang tahap {$stageLabel} dari awal.");
                }
                Cache::put(self::STATE_KEY, $state, now()->addDays(self::STATE_TTL_DAYS));
                $this->info('Melanjutkan dari tahap '.$initialization->stageLabel($state).'.');
                $this->info('Melanjutkan dengan pagination batch untuk menjaga penggunaan memori.');
            } else {
                if (Cache::has(self::STATE_KEY)) {
                    $this->error('Checkpoint terminal sebelumnya masih tersimpan. Gunakan --resume atau hapus checkpoint setelah memastikan proses sebelumnya tidak dilanjutkan.');

                    return self::FAILURE;
                }

                [$url, $username, $password] = $this->credentials($feeder);
                if ($url === null || $username === null || $password === null) {
                    return self::FAILURE;
                }
                if (! $this->confirm(
                    'Proses ini akan mengimpor dan memperbarui data SIMAK dari Feeder sampai semua tahapan selesai. Pastikan database terisolasi/backup tersedia dan Fakultas sudah diisi.',
                    (bool) $this->option('yes'),
                )) {
                    $this->warn('Inisialisasi dibatalkan.');

                    return self::FAILURE;
                }

                $this->info('Menguji koneksi Feeder...');
                $token = $feeder->testInitializationConnection($url, $username, $password);
                $state = $initialization->newTerminalState($url, $username, $password, $token);
                Cache::put(self::STATE_KEY, $state, now()->addDays(self::STATE_TTL_DAYS));
                $this->info('Mode terminal menggunakan pagination batch untuk membatasi ukuran respons dan penggunaan memori.');
            }

            set_time_limit(0);
            while (($state['stage'] ?? null) !== 'complete') {
                $stage = (string) ($state['stage'] ?? '');
                $initialization->assertStagePrerequisites($stage);

                if (($state['stage_complete'] ?? false) === true) {
                    $state = $initialization->nextStage($state);
                    Cache::put(self::STATE_KEY, $state, now()->addDays(self::STATE_TTL_DAYS));
                    $this->info('Memulai tahap '.$initialization->stageLabel($state).'.');

                    continue;
                }

                $previousRecords = (int) ($state['records'] ?? 0);
                $state = $initialization->processBatch($state, $feeder);
                Cache::put(self::STATE_KEY, $state, now()->addDays(self::STATE_TTL_DAYS));
                $this->line(sprintf(
                    '%s | %s | +%s data | total %s | %s',
                    $initialization->stageLabel($state),
                    (int) ($state['batch_duration_ms'] ?? 0).' ms',
                    number_format((int) ($state['records'] ?? 0) - $previousRecords, 0, ',', '.'),
                    number_format((int) ($state['records'] ?? 0), 0, ',', '.'),
                    (string) ($state['last_batch'] ?? ''),
                ));
            }

            Cache::forget(self::STATE_KEY);
            $this->info('Inisialisasi seluruh data Feeder selesai.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            if (is_array($state)) {
                Cache::put(self::STATE_KEY, $state, now()->addDays(self::STATE_TTL_DAYS));
                $this->line('Checkpoint tersimpan. Setelah prasyarat diperbaiki, lanjutkan dengan --resume.');
            }
            $this->error('Inisialisasi Feeder gagal: '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    private function credentials(LegacyFeederService $feeder): array
    {
        $storedCredentials = $feeder->storedInitializationCredentials();
        $url = trim((string) ($this->option('url') ?: ($storedCredentials['url'] ?? '')));
        $username = trim((string) ($this->option('username') ?: ($storedCredentials['username'] ?? '')));
        $password = trim((string) env('FEEDER_PASSWORD', ''));
        if ($password === '') {
            $password = (string) ($storedCredentials['password'] ?? '');
        }

        if ($url === '') {
            $url = trim((string) $this->ask('URL WebService Feeder'));
        }
        if ($username === '') {
            $username = trim((string) $this->ask('Username Feeder'));
        }
        if ($password === '') {
            $password = (string) $this->secret('Password Feeder');
        }

        $parts = parse_url($url);
        if (! is_array($parts)
            || ! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            || ! isset($parts['host'])
            || filter_var($url, FILTER_VALIDATE_URL) === false) {
            $this->error('URL WebService Feeder harus berupa alamat HTTP atau HTTPS yang valid. Gunakan --url atau lengkapi Master PT.');

            return [null, null, null];
        }
        if ($username === '') {
            $this->error('Username Feeder wajib diisi melalui --username atau Master PT.');

            return [null, null, null];
        }
        if ($password === '') {
            $this->error('Password Feeder wajib diisi melalui prompt tersembunyi atau variabel lingkungan FEEDER_PASSWORD.');

            return [null, null, null];
        }

        return [$url, $username, $password];
    }
}
