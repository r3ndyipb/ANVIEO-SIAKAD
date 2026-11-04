<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillGradeSchedules extends Command
{
    protected $signature = 'siakad:backfill-grade-schedules
        {--apply : Simpan jadwal placeholder yang belum ada}
        {--yes : Jalankan tanpa konfirmasi interaktif}';

    protected $description = 'Buat jadwal placeholder dari data KRS yang sudah tersimpan tanpa mengulang impor Feeder';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        if ($apply && ! $this->option('yes')
            && ! $this->confirm('Jadwal placeholder akan dibuat dari data KRS lokal. Lanjutkan?')) {
            $this->warn('Backfill jadwal dibatalkan.');

            return self::SUCCESS;
        }

        $connection = DB::connection('simak_main');
        $rows = $connection->table('s1m4k_krs')
            ->whereNotNull('id_jadwal')
            ->whereRaw("TRIM(id_jadwal) <> ''")
            ->groupBy('id_jadwal')
            ->selectRaw('
                id_jadwal,
                MIN(id_perguruan_tinggi) AS id_perguruan_tinggi,
                MIN(id_fakultas) AS id_fakultas,
                MIN(id_prodi) AS id_prodi,
                MIN(id_kalender) AS id_kalender,
                MIN(id_matkul) AS id_matkul,
                COUNT(DISTINCT NULLIF(TRIM(id_perguruan_tinggi), \'\')) AS perguruan_tinggi_count,
                COUNT(DISTINCT NULLIF(TRIM(id_fakultas), \'\')) AS fakultas_count,
                COUNT(DISTINCT NULLIF(TRIM(id_prodi), \'\')) AS prodi_count,
                COUNT(DISTINCT NULLIF(TRIM(id_kalender), \'\')) AS kalender_count,
                COUNT(DISTINCT NULLIF(TRIM(id_matkul), \'\')) AS matkul_count,
                COUNT(DISTINCT id_mahasiswa) AS student_count
            ')
            ->orderBy('id_jadwal')
            ->cursor();

        $created = 0;
        $existing = 0;
        $ambiguous = 0;
        $invalid = 0;
        foreach ($rows as $row) {
            $scheduleId = trim((string) $row->id_jadwal);
            if (mb_strlen($scheduleId) > 50) {
                $invalid++;

                continue;
            }

            if ($connection->table('s1m4k_jadwal')->where('id_jadwal', $scheduleId)->exists()) {
                $existing++;

                continue;
            }

            if (max(
                (int) $row->perguruan_tinggi_count,
                (int) $row->fakultas_count,
                (int) $row->prodi_count,
                (int) $row->kalender_count,
                (int) $row->matkul_count,
            ) > 1) {
                $ambiguous++;

                continue;
            }

            if ($apply) {
                $connection->table('s1m4k_jadwal')->insert([
                    'id_jadwal' => $scheduleId,
                    'id_perguruan_tinggi' => $row->id_perguruan_tinggi,
                    'id_fakultas' => $row->id_fakultas,
                    'id_prodi' => $row->id_prodi,
                    'id_kalender' => $row->id_kalender,
                    'id_matkul' => $row->id_matkul,
                    'id_kelas_kuliah' => $scheduleId,
                    'nama_kelas' => 'Feeder',
                    'jml_mahasiswa' => (int) $row->student_count,
                ]);
            }
            $created++;
        }

        $verb = $apply ? 'Dibuat' : 'Dapat dibuat';
        $this->info(sprintf(
            '%s: %d jadwal placeholder; %d sudah ada, %d dilewati karena data KRS ambigu, %d ID jadwal tidak valid.',
            $verb,
            $created,
            $existing,
            $ambiguous,
            $invalid,
        ));

        if (! $apply) {
            $this->comment('Ini hanya pratinjau dan tidak mengubah data. Tambahkan --apply untuk membuat jadwal.');
        }

        return self::SUCCESS;
    }
}
