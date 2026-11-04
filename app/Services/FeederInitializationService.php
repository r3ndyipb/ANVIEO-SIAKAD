<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class FeederInitializationService
{
    public const BATCH_SIZE = 100;

    public const STUDENT_BATCH_SIZE = 150;

    public const PASSWORD_BATCH_SIZE = 20;

    private const IMPORTABLE_STUDENT_STATUS_CODES = ['A', 'C', 'N', 'M'];

    private const STUDENT_HISTORY_STRATEGY = 'per-student-history-before-write-v3';

    private const STAGED_STUDENT_HISTORY_STRATEGY = 'batch-history-before-write-v2';

    private const STUDENT_HISTORY_CONCURRENCY = 50;

    private const STUDENT_REQUESTS_PER_BATCH = 4;

    private const STUDENT_BATCH_TIME_BUDGET_SECONDS = 12;

    private const STAGES = [
        'connection', 'references', 'university', 'programs', 'lecturers',
        'students', 'courses', 'grades', 'universities', 'all_programs', 'calendar',
        'student_grades', 'complete',
    ];

    private const REFERENCE_ACTIONS = [
        'GetPembiayaan' => ['id_pembiayaan', 'nama_pembiayaan'],
        'GetJenisPrestasi' => ['id_jenis_prestasi', 'nama_jenis_prestasi'],
        'GetTingkatPrestasi' => ['id_tingkat_prestasi', 'nama_tingkat_prestasi'],
        'GetJenisAktivitasMahasiswa' => ['id_jenis_aktivitas_mahasiswa', 'nama_jenis_aktivitas_mahasiswa', 'untuk_kampus_merdeka'],
        'GetKategoriKegiatan' => ['id_kategori_kegiatan', 'nama_kategori_kegiatan'],
        'GetAgama' => ['id_agama', 'nama_agama'],
        'GetBentukPendidikan' => ['id_bentuk_pendidikan', 'nama_bentuk_pendidikan'],
        'GetIkatanKerjaSdm' => ['id_ikatan_kerja', 'nama_ikatan_kerja'],
        'GetJabfung' => ['id_jabatan_fungsional', 'nama_jabatan_fungsional'],
        'GetJalurMasuk' => ['id_jalur_masuk', 'nama_jalur_masuk'],
        'GetJenisEvaluasi' => ['id_jenis_evaluasi', 'nama_jenis_evaluasi'],
        'GetJenisKeluar' => ['id_jenis_keluar', 'jenis_keluar', 'apa_mahasiswa'],
        'GetJenisSertifikasi' => ['id_jenis_sertifikasi', 'nama_jenis_sertifikasi'],
        'GetJenisPendaftaran' => ['id_jenis_daftar', 'nama_jenis_daftar', 'untuk_daftar_sekolah'],
        'GetJenisSMS' => ['id_jenis_sms', 'nama_jenis_sms'],
        'GetJenisSubstansi' => ['id_jenis_substansi', 'nama_jenis_substansi'],
        'GetJenisTinggal' => ['id_jenis_tinggal', 'nama_jenis_tinggal'],
        'GetJenjangPendidikan' => ['id_jenjang_didik', 'nama_jenjang_didik'],
        'GetLembagaPengangkat' => ['id_lembaga_angkat', 'nama_lembaga_angkat'],
        'GetLevelWilayah' => ['id_level_wilayah', 'nama_level_wilayah'],
        'GetNegara' => ['id_negara', 'nama_negara'],
        'GetPangkatGolongan' => ['id_pangkat_golongan', 'kode_golongan', 'nama_pangkat'],
        'GetPekerjaan' => ['id_pekerjaan', 'nama_pekerjaan'],
        'GetPenghasilan' => ['id_penghasilan', 'nama_penghasilan'],
        'GetStatusKeaktifanPegawai' => ['id_status_aktif', 'nama_status_aktif'],
        'GetStatusKepegawaian' => ['id_status_pegawai', 'nama_status_pegawai'],
        'GetStatusMahasiswa' => ['id_status_mahasiswa', 'nama_status_mahasiswa'],
        'GetKebutuhanKhusus' => ['id_kebutuhan_khusus', 'nama_kebutuhan_khusus'],
        'GetWilayah' => ['id_wilayah', 'id_negara', 'nama_wilayah'],
        'GetAlatTransportasi' => ['id_alat_transportasi', 'nama_alat_transportasi'],
    ];

    private const NEEDS = [
        ['0', 'Tidak ada'],
        ['1', 'A - Tuna netra'],
        ['2', 'B - Tuna rungu'],
        ['4', 'C - Tuna grahita ringan'],
        ['8', 'C1 - Tuna grahita ringan'],
        ['16', 'D - Tuna daksa ringan'],
        ['32', 'D1 - Tuna daksa sedang'],
        ['64', 'E - Tuna laras'],
        ['128', 'F - Tuna wicara'],
        ['256', 'H - Hiperaktif'],
        ['512', 'I - Cerdas Istimewa'],
        ['1024', 'J - Bakat Istimewa'],
        ['2048', 'K - Kesulitan Belajar'],
        ['4096', 'N - Narkoba'],
        ['8192', 'O - Indigo'],
        ['16384', 'P - Down Syndrome'],
        ['32768', 'Q - Autis'],
    ];

    public function processBatch(array $state, LegacyFeederService $feeder): array
    {
        if (($state['stage_complete'] ?? false) === true) {
            throw new RuntimeException('Tahap ini sudah selesai. Gunakan tombol Ulangi Tahap Ini untuk memulai ulang.');
        }

        $this->assertStagePrerequisites((string) ($state['stage'] ?? ''));
        $startedAt = hrtime(true);
        $updated = match ($state['stage']) {
            'references' => $this->processReferences($state, $feeder),
            'university' => $this->processUniversity($state, $feeder),
            'programs' => $this->processPrograms($state, $feeder),
            'lecturers' => $this->processLecturers($state, $feeder),
            'students' => $this->processStudents($state, $feeder),
            'courses' => $this->processCourses($state, $feeder),
            'grades' => $this->processGrades($state, $feeder),
            'universities' => $this->processUniversities($state, $feeder),
            'all_programs' => $this->processAllPrograms($state, $feeder),
            'calendar' => $this->processAcademicCalendar($state, $feeder),
            'student_grades' => $this->processStudentGrades($state, $feeder),
            default => throw new RuntimeException('Tahap inisialisasi Feeder tidak dikenal.'),
        };
        $updated['batch_duration_ms'] = (int) ((hrtime(true) - $startedAt) / 1_000_000);

        return $updated;
    }

    public function newTerminalState(string $url, string $username, string $password, string $token): array
    {
        return [
            'job_id' => (string) Str::uuid(),
            'wizard_version' => 3,
            'stage' => 'connection',
            'reference_index' => 0,
            'substage' => 0,
            'offset' => 0,
            'stage_records' => 0,
            'records' => 0,
            'progress' => 0,
            'connection_verified' => true,
            'stage_complete' => true,
            'terminal_mode' => true,
            'url_encrypted' => Crypt::encryptString($url),
            'username_encrypted' => Crypt::encryptString($username),
            'password_encrypted' => Crypt::encryptString($password),
            'token_encrypted' => Crypt::encryptString($token),
            'last_batch' => 'Koneksi Feeder berhasil diuji.',
            'created_at' => now()->toIso8601String(),
        ];
    }

    public function nextStage(array $state): array
    {
        $stage = (string) ($state['stage'] ?? '');
        if ($stage === 'connection') {
            if (($state['connection_verified'] ?? false) !== true) {
                throw new RuntimeException('Uji koneksi Feeder harus berhasil sebelum melanjutkan.');
            }

            return $this->advance($state, 'references');
        }
        if (($state['stage_complete'] ?? false) !== true) {
            throw new RuntimeException('Selesaikan tahap ini terlebih dahulu sebelum menekan Berikutnya.');
        }

        if ($stage === 'university'
            && (! Schema::connection('simak_main')->hasTable('s1m4k_masterfakultas')
                || ! DB::connection('simak_main')->table('s1m4k_masterfakultas')->exists())) {
            throw new RuntimeException('Data Fakultas belum tersedia. Lengkapi melalui modul Master Fakultas sebelum melanjutkan.');
        }
        if ($stage === 'programs') {
            $unassigned = $this->unassignedPrograms();
            if ($unassigned > 0) {
                throw new RuntimeException("Masih ada {$unassigned} Program Studi yang belum memiliki Fakultas valid. Atur Fakultas untuk semua Program Studi di modul Master Program Studi sebelum melanjutkan.");
            }
        }

        $nextStage = match ($stage) {
            'references' => 'university',
            'university' => 'programs',
            'programs' => 'lecturers',
            'lecturers' => 'students',
            'students' => 'courses',
            'courses' => 'grades',
            'grades' => 'universities',
            'universities' => 'all_programs',
            'all_programs' => 'calendar',
            'calendar' => 'student_grades',
            'student_grades' => 'complete',
            default => throw new RuntimeException('Tahap wizard Feeder tidak memiliki langkah berikutnya.'),
        };

        return $this->advance($state, $nextStage);
    }

    public function activateStage(array $state, string $targetStage): array
    {
        $currentIndex = array_search($state['stage'] ?? null, self::STAGES, true);
        $targetIndex = array_search($targetStage, self::STAGES, true);
        if (! is_int($currentIndex) || ! is_int($targetIndex) || $targetStage === 'complete') {
            throw new RuntimeException('Tahap wizard yang dipilih tidak valid.');
        }

        $completedStages = $this->completedStages($state);
        if ($targetIndex > $currentIndex && ! in_array($targetStage, $completedStages, true)) {
            throw new RuntimeException('Tahap ini belum dapat dibuka. Selesaikan tahap sebelumnya terlebih dahulu.');
        }
        if ($targetStage === 'connection') {
            $state['stage'] = 'connection';
            $state['stage_complete'] = true;

            return $this->updated($state, 'Tahap koneksi Feeder telah diverifikasi.');
        }

        $state['completed_stages'] = $completedStages;
        $state['stage'] = $targetStage;
        $state['stage_complete'] = true;

        return $this->updated($state, 'Tahap '.$this->stageLabel($state).' dibuka kembali. Anda dapat melanjutkan atau mengulangi tahap ini.');
    }

    public function restartCurrentStage(array $state): array
    {
        if (in_array($state['stage'] ?? '', ['connection', 'complete'], true)) {
            throw new RuntimeException('Tahap koneksi/selesai tidak memiliki proses impor untuk diulang.');
        }

        $state = $this->resetStageForRerun($state);

        return $this->updated($state, 'Tahap '.$this->stageLabel($state).' direset. Proses impor ulang siap dimulai.');
    }

    public function completedStages(array $state): array
    {
        $stages = $state['completed_stages'] ?? [];
        if (! is_array($stages)) {
            $stages = [];
        }

        $currentIndex = array_search($state['stage'] ?? null, self::STAGES, true);
        if (is_int($currentIndex)) {
            $stages = array_merge($stages, array_slice(self::STAGES, 0, $currentIndex));
            if (($state['stage_complete'] ?? false) === true) {
                $stages[] = (string) $state['stage'];
            }
        }

        return array_values(array_unique(array_filter($stages, static fn (mixed $stage): bool => is_string($stage))));
    }

    public function unassignedPrograms(): int
    {
        $schema = Schema::connection('simak_main');
        if (! $schema->hasTable('s1m4k_masterprodi')) {
            return 0;
        }

        $connection = DB::connection('simak_main');
        $programs = $connection->table('s1m4k_masterprodi as program');
        if (! $schema->hasTable('s1m4k_masterfakultas')) {
            return $programs->count();
        }

        $facultyMatch = $connection->table('s1m4k_masterfakultas as faculty')
            ->selectRaw('1')
            ->whereColumn('faculty.id_fakultas', 'program.id_fakultas');

        return $programs
            ->where(function ($query) use ($facultyMatch): void {
                $query->whereNull('program.id_fakultas')
                    ->orWhere('program.id_fakultas', '')
                    ->orWhereNotExists($facultyMatch);
            })
            ->count();
    }

    public function assertStagePrerequisites(string $stage): void
    {
        $schema = Schema::connection('simak_main');
        if ($stage === 'programs') {
            if (! $schema->hasTable('s1m4k_masterfakultas')
                || ! DB::connection('simak_main')->table('s1m4k_masterfakultas')->exists()) {
                throw new RuntimeException('Data Fakultas belum tersedia. Lengkapi melalui modul Master Fakultas sebelum mengimpor Program Studi.');
            }
        }
        if ($stage === 'lecturers') {
            $unassigned = $this->unassignedPrograms();
            if ($unassigned > 0) {
                throw new RuntimeException("Masih ada {$unassigned} Program Studi yang belum memiliki Fakultas valid. Lengkapi seluruh pemetaan Fakultas sebelum mengimpor Dosen.");
            }
        }
    }

    public function progress(array $state): int
    {
        if (($state['stage'] ?? null) === 'complete') {
            return 100;
        }

        $index = array_search($state['stage'], self::STAGES, true);
        if (! is_int($index)) {
            return (int) ($state['progress'] ?? 0);
        }

        $fraction = match ($state['stage']) {
            'connection' => ($state['connection_verified'] ?? false) === true ? 1 : 0,
            'references' => min(
                0.99,
                (((int) ($state['reference_index'] ?? 0))
                    + min(0.99, ((int) ($state['offset'] ?? 0)) / (((int) ($state['offset'] ?? 0)) + self::BATCH_SIZE)))
                    / count(self::REFERENCE_ACTIONS),
            ),
            'lecturers' => min(
                0.99,
                (((int) ($state['substage'] ?? 0))
                    + min(
                        0.99,
                        ((int) ($state['offset'] ?? 0))
                            / (((int) ($state['offset'] ?? 0)) + self::BATCH_SIZE),
                    ))
                    / 2,
            ),
            'students' => $this->studentProgress($state),
            'all_programs' => $this->allProgramsProgress($state),
            'student_grades' => $this->studentGradesProgress($state),
            default => min(
                0.99,
                ((int) ($state['stage_records'] ?? 0))
                    / (((int) ($state['stage_records'] ?? 0)) + self::BATCH_SIZE),
            ),
        };
        if (($state['stage_complete'] ?? false) === true) {
            $fraction = 1;
        }
        $progress = (int) floor((($index + $fraction) / count(self::STAGES)) * 100);

        return max((int) ($state['progress'] ?? 0), min(99, $progress));
    }

    public function stageLabel(array $state): string
    {
        return match ($state['stage'] ?? '') {
            'references' => 'Master Referensi',
            'university' => 'Profil Master PT dan konfigurasi Feeder',
            'programs' => 'Program Studi',
            'lecturers' => 'Dosen dan penugasan',
            'students' => 'Mahasiswa dan riwayat pendidikan',
            'courses' => 'Mata Kuliah',
            'grades' => 'Bobot Nilai',
            'universities' => 'Seluruh Perguruan Tinggi',
            'all_programs' => 'Seluruh Program Studi',
            'calendar' => 'Kalender Akademik',
            'student_grades' => 'Nilai Mahasiswa',
            'complete' => 'Inisialisasi selesai',
            'connection' => 'Koneksi Feeder',
            default => 'Inisialisasi Feeder',
        };
    }

    private function processReferences(array $state, LegacyFeederService $feeder): array
    {
        $actions = array_keys(self::REFERENCE_ACTIONS);
        $index = (int) ($state['reference_index'] ?? 0);
        if (! isset($actions[$index])) {
            return $this->enrichWilayahReferences($state);
        }

        $action = $actions[$index];
        if ($action === 'GetKebutuhanKhusus') {
            $this->importReferenceRows($action, array_map(
                static fn (array $row): array => [
                    'id_kebutuhan_khusus' => $row[0],
                    'nama_kebutuhan_khusus' => $row[1],
                ],
                self::NEEDS,
            ));
            $state['records'] += count(self::NEEDS);
            $state['reference_index']++;
            $state['offset'] = 0;

            return $this->updated($state, 'Referensi Kebutuhan Khusus selesai.');
        }

        [$rows, $state] = $this->fetch($state, $feeder, $action, limit: self::BATCH_SIZE);
        $this->importReferenceRows($action, $rows);
        $state['records'] += count($rows);
        $state['stage_records'] += count($rows);
        if (count($rows) < self::BATCH_SIZE) {
            $state['reference_index']++;
            $state['offset'] = 0;
        } else {
            $state['offset'] += count($rows);
        }

        return $this->updated($state, count($rows).' data referensi diproses.');
    }

    private function enrichWilayahReferences(array $state): array
    {
        $assetPath = resource_path('data/getwilayah-lat-lng.json');
        if (! is_file($assetPath)) {
            throw new RuntimeException('Aset koordinat GetWilayah tidak ditemukan: '.$assetPath);
        }
        $contents = file_get_contents($assetPath);
        if ($contents === false) {
            throw new RuntimeException('Aset koordinat GetWilayah tidak dapat dibaca: '.$assetPath);
        }

        $tables = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($tables)) {
            throw new RuntimeException('Format aset koordinat GetWilayah tidak valid.');
        }
        $regions = null;
        foreach ($tables as $table) {
            if (($table['type'] ?? null) === 'table'
                && ($table['name'] ?? null) === 's1m4k_kodereferensi'
                && is_array($table['data'] ?? null)) {
                $regions = $table['data'];
                break;
            }
        }
        if (! is_array($regions) || $regions === []) {
            throw new RuntimeException('Aset koordinat GetWilayah tidak memiliki data referensi wilayah.');
        }

        $offset = max(0, (int) ($state['wilayah_enrichment_offset'] ?? 0));
        $batch = array_slice($regions, $offset, self::BATCH_SIZE);
        $updated = 0;
        $skipped = 0;
        $connection = DB::connection('simak_main');
        foreach ($batch as $region) {
            $code = trim((string) ($region['kode_referensi'] ?? ''));
            $province = trim((string) ($region['nama_provinsi'] ?? ''));
            if ($code === '') {
                throw new RuntimeException('Aset koordinat GetWilayah berisi kode wilayah kosong.');
            }

            $coordinates = [];
            foreach (['lat', 'lng', 'lat_provinsi', 'lng_provinsi'] as $column) {
                $value = trim((string) ($region[$column] ?? ''));
                if ($value === '') {
                    continue;
                }
                if (! is_numeric($value)) {
                    throw new RuntimeException("Aset koordinat GetWilayah tidak memiliki nilai numerik {$column} untuk kode {$code}.");
                }
                $coordinates[$column] = $value;
            }

            $values = $coordinates;
            if ($province !== '') {
                $values['nama_provinsi'] = $province;
            }
            if ($values === []) {
                $skipped++;

                continue;
            }

            $updated += $connection->table('s1m4k_kodereferensi')
                ->where('keterangan', 'GetWilayah')
                ->where('kode_referensi', $code)
                ->update($values);
        }

        $state['wilayah_enrichment_offset'] = $offset + count($batch);
        if ($state['wilayah_enrichment_offset'] >= count($regions)) {
            return $this->completeStage(
                $state,
                "Seluruh data referensi selesai diimpor; {$updated} wilayah diperbarui dan {$skipped} wilayah tanpa data koordinat/provinsi dilewati pada batch terakhir.",
            );
        }

        return $this->updated(
            $state,
            "{$updated} wilayah diperkaya dengan koordinat/provinsi; {$skipped} tanpa data sumber dilewati ("
                .$state['wilayah_enrichment_offset'].' dari '.count($regions).').',
        );
    }

    private function processUniversity(array $state, LegacyFeederService $feeder): array
    {
        [$rows, $state] = $this->fetch($state, $feeder, 'GetProfilPT', limit: 1);
        $profile = $rows[0] ?? null;
        if (! is_array($profile) || trim((string) ($profile['id_perguruan_tinggi'] ?? '')) === '') {
            throw new RuntimeException('Feeder tidak mengembalikan data profil perguruan tinggi.');
        }

        $connection = DB::connection('simak_main');
        $columns = [
            'kode_perguruan_tinggi', 'nama_perguruan_tinggi', 'telepon', 'faximile',
            'email', 'website', 'jalan', 'dusun', 'rt_rw', 'kelurahan', 'kode_pos',
            'id_wilayah', 'nama_wilayah', 'lintang_bujur', 'bank', 'unit_cabang',
            'nomor_rekening', 'mbs', 'luas_tanah_milik', 'luas_tanah_bukan_milik',
            'sk_pendirian', 'tanggal_sk_pendirian', 'id_status_milik', 'nama_status_milik',
            'status_perguruan_tinggi', 'sk_izin_operasional', 'tanggal_izin_operasional',
        ];
        $values = [];
        foreach ($columns as $column) {
            if (array_key_exists($column, $profile)) {
                $values[$column] = in_array($column, ['tanggal_sk_pendirian', 'tanggal_izin_operasional'], true)
                    ? substr(trim((string) $profile[$column]), 0, 10)
                    : $profile[$column];
            }
        }

        $universityId = trim((string) $profile['id_perguruan_tinggi']);
        $connection->transaction(function () use ($connection, $universityId, $values): void {
            $existingId = $connection->table('s1m4k_masterpt')
                ->where('id_perguruan_tinggi', $universityId)
                ->value('id_perguruan_tinggi');
            if (! is_string($existingId) || trim($existingId) === '') {
                $existingId = $connection->table('s1m4k_masterpt')
                    ->value('id_perguruan_tinggi');
            }

            if (is_string($existingId) && trim($existingId) !== '') {
                $connection->table('s1m4k_masterpt')
                    ->where('id_perguruan_tinggi', $existingId)->update($values);
            } else {
                $connection->table('s1m4k_masterpt')->insert([
                    'id_perguruan_tinggi' => $universityId,
                    ...$values,
                ]);
            }
        });

        $feeder->storeInitializationConnectionSettings(
            Crypt::decryptString($state['url_encrypted']),
            Crypt::decryptString($state['username_encrypted']),
            Crypt::decryptString($state['password_encrypted']),
        );

        $state['records']++;

        return $this->completeStage($state, 'Profil perguruan tinggi berhasil diimpor dan koneksi Feeder disimpan.');
    }

    private function processPrograms(array $state, LegacyFeederService $feeder): array
    {
        if (DB::connection('simak_main')->table('s1m4k_masterpt')->doesntExist()) {
            throw new RuntimeException('Impor profil perguruan tinggi harus diselesaikan sebelum Program Studi.');
        }
        $universityId = (string) DB::connection('simak_main')->table('s1m4k_masterpt')
            ->value('id_perguruan_tinggi');
        [$rows, $state] = $this->fetch($state, $feeder, 'GetProdi', limit: self::BATCH_SIZE);
        foreach ($rows as $program) {
            if (! is_array($program) || trim((string) ($program['id_prodi'] ?? '')) === '') {
                continue;
            }
            $id = trim((string) $program['id_prodi']);
            $values = [
                'id_perguruan_tinggi' => $universityId,
                'kode_program_studi' => $program['kode_program_studi'] ?? null,
                'nama_program_studi' => $program['nama_program_studi'] ?? null,
                'status' => $program['status'] ?? null,
                'id_jenjang_pendidikan' => $program['id_jenjang_pendidikan'] ?? null,
                'nama_jenjang_pendidikan' => $program['nama_jenjang_pendidikan'] ?? null,
            ];
            $this->upsertById('s1m4k_masterprodi', 'id_prodi', $id, $values, [
                'k1' => 0, 'k2' => 0, 'k3' => 0, 'k4' => 0,
                'k5' => 0, 'k6' => 0, 'k7' => 0, 'k8' => 0,
            ]);
        }

        return $this->advancePagedStage($state, $rows, 'Program Studi diproses.');
    }

    private function processLecturers(array $state, LegacyFeederService $feeder): array
    {
        $substage = (int) ($state['substage'] ?? 0);
        $action = $substage === 0 ? 'GetListDosen' : 'GetListPenugasanDosen';
        $batchSize = self::BATCH_SIZE;
        $order = $action === 'GetListPenugasanDosen' ? 'id_tahun_ajaran DESC' : '';
        if ($substage === 0 && ($state['lecturer_assignments_fetched'] ?? false) !== true) {
            $result = $feeder->initializationRequests(
                Crypt::decryptString($state['url_encrypted']),
                Crypt::decryptString($state['username_encrypted']),
                Crypt::decryptString($state['password_encrypted']),
                isset($state['token_encrypted']) && $state['token_encrypted'] !== ''
                    ? Crypt::decryptString($state['token_encrypted'])
                    : null,
                [
                    0 => [
                        'action' => 'GetListDosen',
                        'parameters' => [
                            'filter' => '',
                            'order' => '',
                            'limit' => self::BATCH_SIZE,
                            'offset' => (int) ($state['offset'] ?? 0),
                        ],
                    ],
                    1 => [
                        'action' => 'GetListPenugasanDosen',
                        'parameters' => [
                            'filter' => '',
                            'order' => 'id_tahun_ajaran DESC',
                            'limit' => self::BATCH_SIZE,
                            'offset' => 0,
                        ],
                    ],
                ],
                ($state['terminal_mode'] ?? false) === true ? 300 : 8,
            );
            $state['token_encrypted'] = Crypt::encryptString($result['token']);
            foreach ($result['responses'] as $index => $response) {
                if (isset($response['error_code'])) {
                    throw new RuntimeException(
                        "Feeder gagal ({$response['error_code']}): "
                        .($response['error_desc'] ?? 'Kesalahan tidak dijelaskan.'),
                    );
                }
                if ($index === 0) {
                    $rows = $response['rows'] ?? [];
                } else {
                    $state['lecturer_assignments_pending'] = $response['rows'] ?? [];
                    $state['lecturer_assignments_fetched'] = true;
                }
            }
        } elseif ($substage === 1 && isset($state['lecturer_assignments_pending'])) {
            $rows = $state['lecturer_assignments_pending'];
        } else {
            [$rows, $state] = $this->fetch(
                $state,
                $feeder,
                $action,
                order: $order,
                limit: $batchSize,
                offset: $action === 'GetListPenugasanDosen'
                    ? (int) ($state['offset'] ?? 0)
                    : null,
            );
            if ($action === 'GetListPenugasanDosen') {
                $state['lecturer_assignments_fetched'] = true;
            }
        }
        $connection = DB::connection('simak_main');

        if ($action === 'GetListDosen') {
            $universityId = (string) $connection->table('s1m4k_masterpt')->value('id_perguruan_tinggi');
            foreach ($rows as $lecturer) {
                if (! is_array($lecturer) || trim((string) ($lecturer['id_dosen'] ?? '')) === '') {
                    continue;
                }
                $id = trim((string) $lecturer['id_dosen']);
                $nidn = trim((string) ($lecturer['nidn'] ?? ''));
                $nip = trim((string) ($lecturer['nip'] ?? ''));
                $nuptk = trim((string) ($lecturer['nuptk'] ?? ''));
                $loginIdentifier = $nidn !== '' ? $nidn : ($nip !== '' ? $nip : $nuptk);
                if ($loginIdentifier === '') {
                    continue;
                }
                $values = [
                    'id_perguruan_tinggi' => $universityId,
                    'nama_dosen' => $lecturer['nama_dosen'] ?? null,
                    'nidn' => $loginIdentifier,
                    'nip' => $nip !== '' ? $nip : null,
                    'jenis_kelamin' => $lecturer['jenis_kelamin'] ?? null,
                    'id_agama' => $lecturer['id_agama'] ?? null,
                    'tanggal_lahir' => $this->safeDate($lecturer['tanggal_lahir'] ?? null),
                    'id_status_aktif' => $lecturer['id_status_aktif'] ?? null,
                    'nama_status_aktif' => $lecturer['nama_status_aktif'] ?? null,
                    'flag_feeder' => 1,
                ];
                foreach (['gelar_depan', 'gelar_belakang'] as $titleColumn) {
                    if (array_key_exists($titleColumn, $lecturer)) {
                        $values[$titleColumn] = $lecturer[$titleColumn];
                    }
                }
                if (Schema::connection('simak_main')->hasColumn('s1m4k_masterdosen', 'nuptk')) {
                    $values['nuptk'] = $nuptk !== '' ? $nuptk : null;
                }
                $existing = $connection->table('s1m4k_masterdosen')
                    ->where('id_dosen', $id)
                    ->orWhere('nidn', $loginIdentifier)
                    ->first(['id_dosen']);
                if ($existing !== null && (string) $existing->id_dosen !== $id) {
                    continue;
                }
                if ($existing !== null) {
                    $connection->table('s1m4k_masterdosen')
                        ->where('id_dosen', $existing->id_dosen)->update([
                            ...$values,
                            'password' => bcrypt($loginIdentifier),
                        ]);
                } else {
                    $connection->table('s1m4k_masterdosen')->insert([
                        'id_dosen' => $id,
                        'password' => bcrypt($loginIdentifier),
                        'id_registrasi_dosen' => null,
                        'id_fakultas' => null,
                        'id_prodi' => null,
                        'id_tahun_ajaran' => null,
                        'nomor_surat_tugas' => null,
                        'tanggal_surat_tugas' => null,
                        'mulai_surat_tugas' => null,
                        ...$values,
                    ]);
                }
            }
        } else {
            foreach ($rows as $assignment) {
                if (! is_array($assignment)) {
                    continue;
                }
                $id = trim((string) ($assignment['id_dosen'] ?? ''));
                if ($id === '') {
                    continue;
                }
                $seenAssignments = $state['lecturer_assignment_ids_seen'] ?? [];
                if (isset($seenAssignments[$id])) {
                    continue;
                }
                $current = $connection->table('s1m4k_masterdosen')
                    ->where('id_dosen', $id)->first([
                        'id_tahun_ajaran', 'id_perguruan_tinggi', 'id_prodi', 'id_fakultas',
                    ]);
                if ($current === null) {
                    continue;
                }
                $year = trim((string) ($assignment['id_tahun_ajaran'] ?? ''));
                $currentYear = trim((string) ($current->id_tahun_ajaran ?? ''));
                $hasCurrentAssignment = filled($current->id_perguruan_tinggi)
                    && filled($current->id_prodi)
                    && filled($current->id_fakultas);
                if ($hasCurrentAssignment && $currentYear !== '' && strcmp($currentYear, $year) > 0) {
                    $state['lecturer_assignment_ids_seen'][$id] = true;

                    continue;
                }
                $programId = trim((string) ($assignment['id_prodi'] ?? ''));
                if ($programId === '') {
                    continue;
                }
                $program = $connection->table('s1m4k_masterprodi')
                    ->where('id_prodi', $programId)
                    ->first(['id_fakultas', 'nama_program_studi']);
                $facultyId = trim((string) ($program->id_fakultas ?? ''));
                if ($program === null || $facultyId === ''
                    || ! $connection->table('s1m4k_masterfakultas')
                        ->where('id_fakultas', $facultyId)->exists()) {
                    $state['lecturer_assignments_skipped'] = (int) ($state['lecturer_assignments_skipped'] ?? 0) + 1;

                    continue;
                }
                $state['lecturer_assignment_ids_seen'][$id] = true;
                $connection->table('s1m4k_masterdosen')->where('id_dosen', $id)->update([
                    'id_registrasi_dosen' => $assignment['id_registrasi_dosen'] ?? null,
                    'id_perguruan_tinggi' => filled($assignment['id_perguruan_tinggi'] ?? null)
                        ? $assignment['id_perguruan_tinggi']
                        : $connection->table('s1m4k_masterpt')->value('id_perguruan_tinggi'),
                    'id_prodi' => $programId ?: null,
                    'id_fakultas' => $facultyId,
                    'id_tahun_ajaran' => $year ?: null,
                    'nomor_surat_tugas' => $assignment['nomor_surat_tugas'] ?? null,
                    'tanggal_surat_tugas' => $this->safeDate($assignment['tanggal_surat_tugas'] ?? null),
                    'mulai_surat_tugas' => $this->safeDate($assignment['mulai_surat_tugas'] ?? null),
                ]);
            }
        }

        $state['records'] += count($rows);
        $state['stage_records'] += count($rows);
        if ($action === 'GetListPenugasanDosen') {
            unset($state['lecturer_assignments_pending']);
            if (count($rows) >= $batchSize) {
                $state['offset'] += count($rows);

                return $this->updated(
                    $state,
                    count($rows).' data penugasan dosen diproses; melanjutkan halaman berikutnya.',
                );
            }

            $skipped = (int) ($state['lecturer_assignments_skipped'] ?? 0);
            $message = 'Seluruh penugasan dosen terbaru selesai diproses.';
            if ($skipped > 0) {
                $message .= " {$skipped} penugasan dengan Program Studi/Fakultas yang belum valid dilewati.";
            }

            return $this->completeStage($state, $message);
        }
        if (count($rows) < $batchSize) {
            $state['substage'] = 1;
            $state['offset'] = 0;
        } else {
            $state['offset'] += count($rows);
        }

        return $this->updated($state, count($rows).' data '.$action.' diproses.');
    }

    private function processStudents(array $state, LegacyFeederService $feeder): array
    {
        $connection = DB::connection('simak_main');
        if (($state['student_history_strategy'] ?? null) !== self::STUDENT_HISTORY_STRATEGY) {
            $previousStrategy = $state['student_history_strategy'] ?? null;
            $state['student_history_strategy'] = self::STUDENT_HISTORY_STRATEGY;
            if ($previousStrategy === self::STAGED_STUDENT_HISTORY_STRATEGY) {
                $state['student_history_pending'] ??= [];
                $state['student_profile_batch'] ??= [];
                $state['student_history_legacy_backfill'] = false;
                $state['student_history_legacy_resume_profiles'] = false;
            } else {
                $legacyHistoryPending = ($state['student_history_pending'] ?? []) !== []
                    || ($state['student_history_bulk_mode'] ?? false) === true;
                $state['student_history_pending'] = [];
                $state['student_history_legacy_backfill'] = ($state['student_profiles_complete'] ?? false) === true
                    || $legacyHistoryPending;
                $state['student_history_legacy_resume_profiles'] = ! ($state['student_profiles_complete'] ?? false);
                $state['student_history_cursor'] = '';
            }
        }
        $statusReferences = $this->studentStatusReferences();
        $state['student_nim_conflict_ids'] ??= [];
        $state['student_nim_conflict_count'] ??= 0;
        $programIds = $state['program_ids'] ?? null;
        if (! is_array($programIds)) {
            $programIds = $connection->table('s1m4k_masterprodi')
                ->orderBy('id_prodi')->pluck('id_prodi')->map(
                    static fn (mixed $id): string => (string) $id
                )->all();
            $state['program_ids'] = $programIds;
            $state['program_index'] = 0;
        }
        $programIndex = (int) ($state['program_index'] ?? 0);
        if (! isset($programIds[$programIndex])) {
            return $this->completeStage($state, 'Data mahasiswa dan riwayat pendidikan selesai diimpor.');
        }
        $programId = (string) $programIds[$programIndex];
        $program = $connection->table('s1m4k_masterprodi')
            ->where('id_prodi', $programId)
            ->first(['id_prodi', 'id_perguruan_tinggi', 'id_fakultas']);
        if ($program === null) {
            $state['program_index']++;
            $state['offset'] = 0;
            $state['student_history_pending'] = [];
            $state['student_profiles_complete'] = false;
            $state['student_history_legacy_backfill'] = false;

            return $this->updated($state, 'Program Studi yang tidak lagi tersedia dilewati.');
        }

        if (($state['student_history_legacy_backfill'] ?? false) === true) {
            $this->backfillStudentStatusIdsIfNeeded($state, $statusReferences);

            return $this->processLegacyStudentHistory($state, $feeder, $program);
        }
        if (($state['student_profile_batch'] ?? []) !== []) {
            $savedProfiles = $this->flushStudentProfileBatch($state);

            return $this->updated(
                $state,
                "{$savedProfiles} profil mahasiswa yang sudah memiliki riwayat dipulihkan dari checkpoint.",
            );
        }
        if (($state['student_history_pending'] ?? []) !== []) {
            return $this->processStudentHistoryBatch($state, $feeder, $program);
        }
        if (($state['student_profiles_complete'] ?? false) === true) {
            $this->backfillStudentStatusIdsIfNeeded($state, $statusReferences);

            return $this->advanceStudentProgram($state);
        }

        [$rows, $state] = $this->fetch(
            $state,
            $feeder,
            'GetDataLengkapMahasiswaProdi',
            filter: "id_prodi='".$this->filterValue($programId)."' AND ("
                ."nama_status_mahasiswa LIKE '%Aktif%' "
                ."OR nama_status_mahasiswa LIKE '%Cuti%' "
                ."OR nama_status_mahasiswa LIKE '%Kampus Merdeka%' "
                ."OR nama_status_mahasiswa LIKE '%Non-Aktif%' "
                ."OR nama_status_mahasiswa LIKE '%Non Aktif%')",
            limit: self::STUDENT_BATCH_SIZE,
        );
        $studentRows = array_values(array_filter(
            $rows,
            static fn (mixed $student): bool => is_array($student)
                && trim((string) ($student['id_mahasiswa'] ?? '')) !== ''
                && self::isImportableStudentStatus($student['nama_status_mahasiswa'] ?? ''),
        ));
        $studentIds = array_values(array_unique(array_map(
            static fn (array $student): string => trim((string) $student['id_mahasiswa']),
            $studentRows,
        )));
        $existingStudents = collect();
        foreach (array_chunk($studentIds, 500) as $studentIdBatch) {
            $existingStudents = $existingStudents->union(
                $connection->table('s1m4k_mastermhs')
                    ->whereIn('id_mahasiswa', $studentIdBatch)
                    ->get(['id_mahasiswa', 'nim'])
                    ->keyBy('id_mahasiswa'),
            );
        }
        $studentIds = [];
        $pendingHistories = [];
        $conflictCount = 0;
        $conflictDetails = [];
        foreach ($studentRows as $student) {
            $id = trim((string) $student['id_mahasiswa']);
            $existing = $existingStudents->get($id);
            $nim = trim((string) ($student['nim'] ?? ''));
            if ($existing !== null && trim((string) $existing->nim) !== '' && $nim !== ''
                && trim((string) $existing->nim) !== $nim) {
                $state['student_nim_conflict_ids'][$id] = true;
                $state['student_nim_conflict_count']++;
                $conflictCount++;
                $conflictDetails[] = sprintf(
                    'ID mahasiswa %s (NIM lokal %s, NIM Feeder %s)',
                    str_replace(["\r", "\n"], ' ', $id),
                    str_replace(["\r", "\n"], ' ', trim((string) $existing->nim)),
                    str_replace(["\r", "\n"], ' ', $nim),
                );

                continue;
            }

            $statusName = trim((string) ($student['nama_status_mahasiswa'] ?? ''));
            $status = $statusReferences->get(mb_strtolower($statusName));
            $values = [
                'id_mahasiswa' => $id,
                'id_perguruan_tinggi' => $program->id_perguruan_tinggi,
                'id_fakultas' => $program->id_fakultas,
                'id_prodi' => $programId,
                'id_registrasi_mahasiswa' => $student['id_registrasi_mahasiswa'] ?? null,
                'id_status_mahasiswa' => $status->kode_referensi ?? $student['id_status_mahasiswa'] ?? null,
                'nama_status_mahasiswa' => $status->ket_referensi ?? $student['nama_status_mahasiswa'] ?? null,
                'nim' => $nim ?: null,
                'nama_mahasiswa' => $student['nama_mahasiswa'] ?? null,
                'jenis_kelamin' => $student['jenis_kelamin'] ?? null,
                'jalan' => $student['jalan'] ?? null,
                'rt' => $student['rt'] ?? null,
                'rw' => $student['rw'] ?? null,
                'dusun' => $student['dusun'] ?? null,
                'kelurahan' => $student['kelurahan'] ?? null,
                'kode_pos' => $student['kode_pos'] ?? null,
                'nisn' => $student['nisn'] ?? null,
                'nik' => $this->limitNik($student['nik'] ?? null),
                'tempat_lahir' => $student['tempat_lahir'] ?? null,
                'tanggal_lahir' => $this->safeDate($student['tanggal_lahir'] ?? null),
                'nama_ayah' => $student['nama_ayah'] ?? null,
                'tanggal_lahir_ayah' => $this->safeDate($student['tanggal_lahir_ayah'] ?? null),
                'nik_ayah' => $this->limitNik($student['nik_ayah'] ?? null),
                'id_pendidikan_ayah' => $student['id_pendidikan_ayah'] ?? null,
                'id_pekerjaan_ayah' => $student['id_pekerjaan_ayah'] ?? null,
                'id_penghasilan_ayah' => $student['id_penghasilan_ayah'] ?? null,
                'id_kebutuhan_khusus_ayah' => $student['id_kebutuhan_khusus_ayah'] ?? null,
                'nama_ibu' => $student['nama_ibu_kandung'] ?? $student['nama_ibu'] ?? null,
                'tanggal_lahir_ibu' => $this->safeDate($student['tanggal_lahir_ibu'] ?? null),
                'nik_ibu' => $this->limitNik($student['nik_ibu'] ?? null),
                'id_pendidikan_ibu' => $student['id_pendidikan_ibu'] ?? null,
                'id_pekerjaan_ibu' => $student['id_pekerjaan_ibu'] ?? null,
                'id_penghasilan_ibu' => $student['id_penghasilan_ibu'] ?? null,
                'id_kebutuhan_khusus_ibu' => $student['id_kebutuhan_khusus_ibu'] ?? null,
                'nama_wali' => $student['nama_wali'] ?? null,
                'tanggal_lahir_wali' => $this->safeDate($student['tanggal_lahir_wali'] ?? null),
                'id_pendidikan_wali' => $student['id_pendidikan_wali'] ?? null,
                'id_pekerjaan_wali' => $student['id_pekerjaan_wali'] ?? null,
                'id_penghasilan_wali' => $student['id_penghasilan_wali'] ?? null,
                'id_kebutuhan_khusus_mahasiswa' => $student['id_kebutuhan_khusus_mahasiswa'] ?? null,
                'telepon' => $student['telepon'] ?? null,
                'handphone' => $student['handphone'] ?? null,
                'email' => $student['email'] ?? null,
                'penerima_kps' => $student['penerima_kps'] ?? null,
                'nomor_kps' => $student['nomor_kps'] ?? $student['no_kps'] ?? null,
                'npwp' => $student['npwp'] ?? null,
                'id_wilayah' => $student['id_wilayah'] ?? null,
                'id_jenis_tinggal' => $student['id_jenis_tinggal'] ?? null,
                'id_agama' => $student['id_agama'] ?? null,
                'id_alat_transportasi' => $student['id_alat_transportasi'] ?? null,
                'id_negara' => $this->studentCountryCode($student),
                'id_periode_masuk' => $student['id_periode_masuk'] ?? null,
                'sks_diakui' => isset($student['sks_diakui']) ? (string) $student['sks_diakui'] : null,
                'id_perguruan_tinggi_asal' => $student['id_perguruan_tinggi_asal'] ?? null,
                'id_prodi_asal' => $student['id_prodi_asal'] ?? null,
                'flag_feeder' => 1,
            ];
            $pendingHistories[] = [
                'id_mahasiswa' => $id,
                'id_registrasi_mahasiswa' => trim((string) ($student['id_registrasi_mahasiswa'] ?? '')),
                'profile' => [
                    ...$values,
                    'password' => $nim !== '' ? bcrypt($nim) : '',
                ],
            ];
        }

        $state['offset'] += count($rows);
        $state['student_history_pending'] = $pendingHistories;
        $state['student_profile_batch'] = [];
        $state['student_profiles_complete'] = count($rows) < self::STUDENT_BATCH_SIZE;
        $state['student_history_pending'] = $pendingHistories;
        if ($pendingHistories === []) {
            $this->backfillStudentStatusIdsIfNeeded($state, $statusReferences);
            $message = 'Tidak ada profil mahasiswa yang memerlukan pencarian riwayat pada halaman ini.';
            if ($conflictCount > 0) {
                $message .= " {$conflictCount} konflik ID/NIM dilewati: ".implode('; ', $conflictDetails).'.';
            }

            return $this->updated($state, $message);
        }
        if ($conflictCount > 0) {
            $state['student_history_conflicts_message'] =
                "{$conflictCount} konflik ID/NIM dilewati: ".implode('; ', $conflictDetails).'.';
        }

        return $this->processStudentHistoryBatch($state, $feeder, $program);
    }

    private function studentStatusReferences(): Collection
    {
        $references = collect();
        foreach (DB::connection('simak_main')->table('s1m4k_kodereferensi')
            ->whereIn('keterangan', ['GetStatusMahasiswa', 'GetJenisKeluar'])
            ->get(['kode_referensi', 'ket_referensi', 'keterangan'])
            ->sortBy(static fn (object $status): int => $status->keterangan === 'GetStatusMahasiswa' ? 0 : 1) as $reference) {
            $name = mb_strtolower(trim((string) $reference->ket_referensi));
            if ($name !== '' && ! $references->has($name)) {
                $references->put($name, $reference);
            }
        }

        return $references;
    }

    private function backfillStudentStatusIds(Collection $statusReferences): int
    {
        $connection = DB::connection('simak_main');
        $updated = 0;
        foreach ($statusReferences as $name => $reference) {
            $code = trim((string) $reference->kode_referensi);
            if ($code === '' || ! in_array($code, self::IMPORTABLE_STUDENT_STATUS_CODES, true)) {
                continue;
            }
            $updated += $connection->table('s1m4k_mastermhs')
                ->where(static function ($query): void {
                    $query->whereNull('id_status_mahasiswa')->orWhere('id_status_mahasiswa', '');
                })
                ->whereRaw('LOWER(TRIM(nama_status_mahasiswa)) = ?', [$name])
                ->update(['id_status_mahasiswa' => $code]);
        }

        return $updated;
    }

    private function backfillStudentStatusIdsIfNeeded(array &$state, Collection $statusReferences): void
    {
        if (($state['student_status_backfill_complete'] ?? false) === true) {
            return;
        }

        $state['student_status_ids_backfilled'] = $this->backfillStudentStatusIds($statusReferences);
        $state['student_status_backfill_complete'] = true;
    }

    private static function isImportableStudentStatus(mixed $status): bool
    {
        $normalized = mb_strtolower(trim((string) $status));
        $normalized = str_replace(['-', ' '], '', $normalized);

        return in_array($normalized, ['aktif', 'cuti', 'nonaktif', 'kampusmerdeka'], true);
    }

    private function processStudentHistoryBatch(
        array $state,
        LegacyFeederService $feeder,
        object $program,
    ): array {
        $programId = (string) $program->id_prodi;
        $pending = array_slice($state['student_history_pending'], 0, self::STUDENT_HISTORY_CONCURRENCY, true);
        $results = $this->fetchStudentHistoryBatch($state, $feeder, $programId, $pending);
        $saved = 0;
        $notFound = 0;
        $failed = 0;
        foreach ($pending as $index => $student) {
            $result = $results[$index];
            $history = $result['history'];
            if ($result['failed']) {
                $failed++;
            } elseif ($history === null) {
                $notFound++;
            } else {
                $state['records']++;
            }

            $profile = is_array($student['profile'] ?? null) ? $student['profile'] : [];
            $profile['tanggal_daftar'] = $this->safeDate($history['tanggal_daftar'] ?? null);
            $profile['id_pembiayaan'] = $history['id_pembiayaan'] ?? null;
            $profile['biaya_masuk_kuliah'] = $history['biaya_masuk'] ?? null;
            $this->saveStudentProfile($profile);
            $saved++;
        }

        $state['student_history_pending'] = array_slice(
            $state['student_history_pending'],
            count($pending),
            null,
            true,
        );
        $state['records'] += $saved;
        $state['stage_records'] += $saved;
        $state['history_not_found'] = (int) ($state['history_not_found'] ?? 0) + $notFound;
        $state['student_history_lookup_failures'] = (int) ($state['student_history_lookup_failures'] ?? 0) + $failed;
        $message = "{$saved} profil mahasiswa disimpan setelah pencarian riwayat paralel.";
        if ($notFound > 0 || $failed > 0) {
            $message .= " {$notFound} riwayat tidak ditemukan; {$failed} pencarian gagal dengan error 121.";
        }
        if (isset($state['student_history_conflicts_message'])) {
            $message .= ' '.$state['student_history_conflicts_message'];
            unset($state['student_history_conflicts_message']);
        }

        return $this->updated($state, $message);
    }

    /**
     * @param  array<int, array>  $students
     * @return array<int, array{history: ?array, failed: bool}>
     */
    private function fetchStudentHistoryBatch(
        array &$state,
        LegacyFeederService $feeder,
        string $programId,
        array $students,
    ): array {
        $histories = [];
        $fallbackIndexes = [];
        $registrationRequests = [];
        foreach ($students as $index => $student) {
            $registrationId = trim((string) ($student['id_registrasi_mahasiswa'] ?? ''));
            if ($registrationId === '') {
                $fallbackIndexes[] = $index;

                continue;
            }
            $registrationRequests[$index] = [
                'action' => 'GetListRiwayatPendidikanMahasiswa',
                'parameters' => [
                    'filter' => "id_registrasi_mahasiswa='".$this->filterValue($registrationId)."'",
                    'order' => '',
                    'limit' => 1,
                    'offset' => 0,
                ],
            ];
        }

        if ($registrationRequests !== []) {
            $responses = $this->sendStudentHistoryRequests($state, $feeder, $registrationRequests);
            foreach ($registrationRequests as $index => $_request) {
                $student = $students[$index];
                $registrationId = trim((string) ($student['id_registrasi_mahasiswa'] ?? ''));
                $history = $this->matchingStudentHistory(
                    $responses[$index]['rows'] ?? [],
                    trim((string) ($student['id_mahasiswa'] ?? '')),
                    $programId,
                    $registrationId,
                );
                if ($history === null) {
                    $fallbackIndexes[] = $index;

                    continue;
                }
                $histories[$index] = ['history' => $history, 'failed' => false];
            }
        }

        $fallbackRequests = [];
        foreach ($fallbackIndexes as $index) {
            $studentId = trim((string) ($students[$index]['id_mahasiswa'] ?? ''));
            $fallbackRequests[$index] = [
                'action' => 'GetListRiwayatPendidikanMahasiswa',
                'parameters' => [
                    'filter' => "id_mahasiswa='".$this->filterValue($studentId)."' AND id_prodi='".$this->filterValue($programId)."'",
                    'order' => 'id_periode_masuk DESC',
                    'limit' => 1,
                    'offset' => 0,
                ],
            ];
        }
        if ($fallbackRequests !== []) {
            $responses = $this->sendStudentHistoryRequests($state, $feeder, $fallbackRequests);
            foreach ($fallbackRequests as $index => $_request) {
                if (($responses[$index]['error_code'] ?? null) === '121') {
                    $histories[$index] = ['history' => null, 'failed' => true];

                    continue;
                }
                $history = $this->matchingStudentHistory(
                    $responses[$index]['rows'] ?? [],
                    trim((string) ($students[$index]['id_mahasiswa'] ?? '')),
                    $programId,
                );
                $histories[$index] = ['history' => $history, 'failed' => false];
            }
        }

        return $histories;
    }

    /**
     * @param  array<int, array{action: string, parameters: array}>  $requests
     * @return array<int, array{rows?: array, error_code?: string, error_desc?: string}>
     */
    private function sendStudentHistoryRequests(
        array &$state,
        LegacyFeederService $feeder,
        array $requests,
    ): array {
        $result = $feeder->initializationRequests(
            Crypt::decryptString($state['url_encrypted']),
            Crypt::decryptString($state['username_encrypted']),
            Crypt::decryptString($state['password_encrypted']),
            isset($state['token_encrypted']) && $state['token_encrypted'] !== ''
                ? Crypt::decryptString($state['token_encrypted'])
                : null,
            $requests,
            ($state['terminal_mode'] ?? false) === true ? 300 : 8,
        );
        $state['token_encrypted'] = Crypt::encryptString($result['token']);
        $responses = $result['responses'];
        foreach ($responses as $response) {
            if (isset($response['error_code']) && ! in_array($response['error_code'], ['0', '121'], true)) {
                $code = $response['error_code'];
                $description = $response['error_desc'] ?? 'Kesalahan tidak dijelaskan.';
                throw new RuntimeException("Feeder gagal ({$code}): {$description}");
            }
        }

        return $responses;
    }

    private function saveStudentProfile(array $profile): void
    {
        $updateColumns = array_values(array_diff(
            array_keys($profile),
            ['id_mahasiswa', 'password'],
        ));
        DB::connection('simak_main')->table('s1m4k_mastermhs')->upsert(
            [$profile],
            ['id_mahasiswa'],
            $updateColumns,
        );
    }

    private function flushStudentProfileBatch(array &$state): int
    {
        $profiles = $state['student_profile_batch'] ?? [];
        if ($profiles === []) {
            return 0;
        }

        foreach ($profiles as $profile) {
            $this->saveStudentProfile($profile);
        }
        $count = count($profiles);
        $state['records'] += $count;
        $state['stage_records'] += $count;
        $state['student_profile_batch'] = [];

        return $count;
    }

    private function processLegacyStudentHistory(array $state, LegacyFeederService $feeder, object $program): array
    {
        $connection = DB::connection('simak_main');
        $programId = (string) $program->id_prodi;
        $cursor = trim((string) ($state['student_history_cursor'] ?? ''));
        $student = $connection->table('s1m4k_mastermhs')
            ->where('id_prodi', $programId)
            ->whereIn('id_status_mahasiswa', self::IMPORTABLE_STUDENT_STATUS_CODES)
            ->when($cursor !== '', static fn ($query) => $query->where('id_mahasiswa', '>', $cursor))
            ->orderBy('id_mahasiswa')
            ->first(['id_mahasiswa', 'id_registrasi_mahasiswa']);

        if ($student === null) {
            $state['student_history_legacy_backfill'] = false;
            $state['student_history_cursor'] = '';
            if (($state['student_history_legacy_resume_profiles'] ?? false) === true) {
                $state['student_history_legacy_resume_profiles'] = false;

                return $this->updated($state, 'Riwayat checkpoint lama selesai; melanjutkan pagination profil mahasiswa.');
            }

            return $this->advanceStudentProgram($state);
        }

        $studentId = trim((string) $student->id_mahasiswa);
        $state['student_history_cursor'] = $studentId;
        if (isset(($state['student_nim_conflict_ids'] ?? [])[$studentId])) {
            return $this->updated($state, "Riwayat mahasiswa konflik untuk ID {$studentId} dilewati.");
        }

        [$history, $state, $lookupFailed] = $this->fetchStudentHistory(
            $state,
            $feeder,
            $programId,
            $studentId,
            trim((string) ($student->id_registrasi_mahasiswa ?? '')),
        );
        if ($lookupFailed) {
            $state['student_history_lookup_failures'] = (int) ($state['student_history_lookup_failures'] ?? 0) + 1;

            return $this->updated($state, "Pencarian riwayat mahasiswa {$studentId} gagal dengan error 121; dilanjutkan ke mahasiswa berikutnya.");
        }
        if ($history === null) {
            $state['history_not_found'] = (int) ($state['history_not_found'] ?? 0) + 1;

            return $this->updated($state, "Riwayat pendidikan mahasiswa {$studentId} tidak ditemukan.");
        }

        $connection->table('s1m4k_mastermhs')
            ->where('id_mahasiswa', $studentId)
            ->where('id_prodi', $programId)
            ->whereIn('id_status_mahasiswa', self::IMPORTABLE_STUDENT_STATUS_CODES)
            ->update([
                'tanggal_daftar' => $this->safeDate($history['tanggal_daftar'] ?? null),
                'id_pembiayaan' => $history['id_pembiayaan'] ?? null,
                'biaya_masuk_kuliah' => $history['biaya_masuk'] ?? null,
            ]);
        $state['records']++;

        return $this->updated($state, "Riwayat pendidikan mahasiswa {$studentId} diperbarui dari checkpoint lama.");
    }

    private function fetchStudentHistory(
        array $state,
        LegacyFeederService $feeder,
        string $programId,
        string $studentId,
        string $registrationId,
    ): array {
        if ($registrationId !== '') {
            try {
                [$rows, $state] = $this->fetch(
                    $state,
                    $feeder,
                    'GetListRiwayatPendidikanMahasiswa',
                    filter: "id_registrasi_mahasiswa='".$this->filterValue($registrationId)."'",
                    limit: 1,
                    offset: 0,
                );
                $history = $this->matchingStudentHistory($rows, $studentId, $programId, $registrationId);
                if ($history !== null) {
                    return [$history, $state, false];
                }
            } catch (RuntimeException $exception) {
                if (! str_contains($exception->getMessage(), 'Feeder gagal (121)')) {
                    throw $exception;
                }
            }
        }

        try {
            [$rows, $state] = $this->fetch(
                $state,
                $feeder,
                'GetListRiwayatPendidikanMahasiswa',
                filter: "id_mahasiswa='".$this->filterValue($studentId)."' AND id_prodi='".$this->filterValue($programId)."'",
                order: 'id_periode_masuk DESC',
                limit: 1,
                offset: 0,
            );
        } catch (RuntimeException $exception) {
            if (! str_contains($exception->getMessage(), 'Feeder gagal (121)')) {
                throw $exception;
            }

            return [null, $state, true];
        }

        return [$this->matchingStudentHistory($rows, $studentId, $programId), $state, false];
    }

    private function matchingStudentHistory(
        array $rows,
        string $studentId,
        string $programId,
        string $registrationId = '',
    ): ?array {
        foreach ($rows as $row) {
            if (! is_array($row)
                || trim((string) ($row['id_mahasiswa'] ?? '')) !== $studentId
                || (array_key_exists('id_prodi', $row) && (string) $row['id_prodi'] !== $programId)
                || ($registrationId !== ''
                    && isset($row['id_registrasi_mahasiswa'])
                    && trim((string) $row['id_registrasi_mahasiswa']) !== $registrationId)) {
                continue;
            }

            return $row;
        }

        return null;
    }

    private function advanceStudentProgram(array $state): array
    {
        $state['program_index'] = (int) ($state['program_index'] ?? 0) + 1;
        $state['offset'] = 0;
        $state['student_profiles_complete'] = false;
        $state['student_history_pending'] = [];
        $state['student_history_legacy_backfill'] = false;
        $state['student_history_cursor'] = '';
        $state['student_nim_conflict_ids'] = [];
        $nextProgram = $this->currentProgram($state);
        if ($nextProgram === null) {
            $message = 'Data mahasiswa dan riwayat pendidikan selesai diimpor.';
            $conflicts = (int) ($state['student_nim_conflict_count'] ?? 0);
            if ($conflicts > 0) {
                $message .= " {$conflicts} konflik ID/NIM dilewati.";
            }
            $failures = (int) ($state['student_history_lookup_failures'] ?? 0);
            if ($failures > 0) {
                $message .= " {$failures} pencarian riwayat gagal dengan error 121.";
            }
            $notFound = (int) ($state['history_not_found'] ?? 0);
            if ($notFound > 0) {
                $message .= " {$notFound} riwayat pendidikan tidak ditemukan.";
            }

            return $this->completeStage($state, $message);
        }

        return $this->updated($state, 'Riwayat Program Studi selesai; melanjutkan ke Program Studi berikutnya.');
    }

    private function processCourses(array $state, LegacyFeederService $feeder): array
    {
        $program = $this->currentProgram($state);
        if ($program === null) {
            return $this->completeStage($state, 'Data Mata Kuliah selesai diimpor.');
        }
        $programId = (string) $program->id_prodi;
        [$rows, $state] = $this->fetch(
            $state,
            $feeder,
            'GetDetailMataKuliah',
            filter: "id_prodi='".$this->filterValue($programId)."'",
            limit: self::BATCH_SIZE,
        );
        foreach ($rows as $course) {
            if (! is_array($course) || trim((string) ($course['id_matkul'] ?? '')) === '') {
                continue;
            }
            $id = trim((string) $course['id_matkul']);
            $this->upsertById('s1m4k_mastermk', 'id_matkul', $id, [
                'kode_mata_kuliah' => $course['kode_mata_kuliah'] ?? null,
                'nama_mata_kuliah' => $course['nama_mata_kuliah'] ?? null,
                'id_perguruan_tinggi' => $program->id_perguruan_tinggi,
                'id_fakultas' => $program->id_fakultas,
                'id_prodi' => $programId,
                'id_jenis_mata_kuliah' => $course['id_jenis_mata_kuliah'] ?? null,
                'id_kelompok_mata_kuliah' => $course['id_kelompok_mata_kuliah'] ?? null,
                'sks_mata_kuliah' => $course['sks_mata_kuliah'] ?? null,
                'sks_tatap_muka' => $course['sks_tatap_muka'] ?? null,
                'sks_praktek' => $course['sks_praktek'] ?? null,
                'sks_praktek_lapangan' => $course['sks_praktek_lapangan'] ?? null,
                'sks_simulasi' => $course['sks_simulasi'] ?? null,
                'metode_kuliah' => $course['metode_kuliah'] ?? null,
                'ada_sap' => $course['ada_sap'] ?? null,
                'ada_silabus' => $course['ada_silabus'] ?? null,
                'ada_bahan_ajar' => $course['ada_bahan_ajar'] ?? null,
                'ada_acara_praktek' => $course['ada_acara_praktek'] ?? null,
                'ada_diktat' => $course['ada_diktat'] ?? null,
                'tanggal_mulai_efektif' => $this->safeDate($course['tanggal_mulai_efektif'] ?? null),
                'tanggal_selesai_efektif' => $this->safeDate($course['tanggal_selesai_efektif'] ?? null),
                'flag_feeder' => 1,
            ]);
        }

        return $this->advanceProgramPage($state, $rows, 'Mata Kuliah diproses.');
    }

    private function processGrades(array $state, LegacyFeederService $feeder): array
    {
        $program = $this->currentProgram($state);
        if ($program === null) {
            return $this->completeStage($state, 'Data Bobot Nilai selesai diimpor.');
        }
        $programId = (string) $program->id_prodi;
        [$rows, $state] = $this->fetch(
            $state,
            $feeder,
            'GetListSkalaNilaiProdi',
            filter: "id_prodi='".$this->filterValue($programId)."'",
            limit: self::BATCH_SIZE,
        );
        foreach ($rows as $grade) {
            if (! is_array($grade) || trim((string) ($grade['id_bobot_nilai'] ?? '')) === '') {
                continue;
            }
            $id = trim((string) $grade['id_bobot_nilai']);
            $this->upsertById('s1m4k_masterbobotnilai', 'id_bobot_nilai', $id, [
                'id_perguruan_tinggi' => $program->id_perguruan_tinggi,
                'nilai_huruf' => $grade['nilai_huruf'] ?? null,
                'nilai_indeks' => $grade['nilai_indeks'] ?? null,
                'bobot_minimum' => $grade['bobot_minimum'] ?? null,
                'bobot_maksimum' => $grade['bobot_maksimum'] ?? null,
                'tanggal_mulai_efektif' => $this->safeDate($grade['tanggal_mulai_efektif'] ?? null),
                'tanggal_akhir_efektif' => $this->safeDate($grade['tanggal_akhir_efektif'] ?? null),
                'status' => $grade['status'] ?? 'Y',
                'flag_feeder' => 1,
            ]);
        }

        return $this->advanceProgramPage($state, $rows, 'Bobot Nilai diproses.');
    }

    private function processUniversities(array $state, LegacyFeederService $feeder): array
    {
        [$rows, $state] = $this->fetch($state, $feeder, 'GetAllPT', limit: self::BATCH_SIZE);
        foreach ($rows as $university) {
            if (! is_array($university) || trim((string) ($university['id_perguruan_tinggi'] ?? '')) === '') {
                continue;
            }
            $id = trim((string) $university['id_perguruan_tinggi']);
            $this->upsertById('s1m4k_masterallpt', 'id_perguruan_tinggi', $id, [
                'kode_perguruan_tinggi' => $university['kode_perguruan_tinggi'] ?? null,
                'nama_perguruan_tinggi' => $university['nama_perguruan_tinggi'] ?? null,
                'nama_singkat' => $university['nama_singkat'] ?? null,
            ]);
        }

        return $this->advancePagedStage($state, $rows, 'Data perguruan tinggi diproses.');
    }

    private function processAllPrograms(array $state, LegacyFeederService $feeder): array
    {
        $this->assertCompositeAllProgramsKey();
        $connection = DB::connection('simak_main');
        $institution = $connection->table('s1m4k_masterallpt')
            ->orderBy('id_perguruan_tinggi')
            ->offset((int) ($state['institution_index'] ?? 0))
            ->first(['id_perguruan_tinggi', 'kode_perguruan_tinggi']);
        if ($institution === null) {
            return $this->completeStage($state, 'Semua Program Studi seluruh perguruan tinggi selesai diimpor.');
        }
        $code = trim((string) ($institution->kode_perguruan_tinggi ?? ''));
        if ($code === '') {
            $state['institution_index']++;
            $state['offset'] = 0;

            return $this->updated($state, 'Perguruan tinggi tanpa kode dilewati pada impor All Prodi.');
        }

        [$rows, $state] = $this->fetch(
            $state,
            $feeder,
            'GetAllProdi',
            filter: "kode_perguruan_tinggi='".$this->filterValue($code)."'",
            limit: self::BATCH_SIZE,
        );
        foreach ($rows as $program) {
            if (! is_array($program)
                || trim((string) ($program['id_perguruan_tinggi'] ?? '')) === ''
                || trim((string) ($program['id_prodi'] ?? '')) === '') {
                continue;
            }
            $id = trim((string) $program['id_prodi']);
            $universityId = trim((string) $program['id_perguruan_tinggi']);
            $values = [
                'kode_perguruan_tinggi' => $program['kode_perguruan_tinggi'] ?? $code,
                'kode_program_studi' => $program['kode_program_studi'] ?? null,
                'nama_program_studi' => $program['nama_program_studi'] ?? null,
                'status' => $program['status'] ?? null,
                'id_jenjang_pendidikan' => $program['id_jenjang_pendidikan'] ?? null,
            ];
            $this->upsertByComposite(
                's1m4k_masterallprodi',
                ['id_perguruan_tinggi' => $universityId, 'id_prodi' => $id],
                $values,
            );
        }
        $state['records'] += count($rows);
        $state['stage_records'] += count($rows);
        $state['institution_index'] = (int) ($state['institution_index'] ?? 0);
        if (count($rows) < self::BATCH_SIZE) {
            $state['institution_index']++;
            $state['offset'] = 0;
        } else {
            $state['offset'] += count($rows);
        }

        return $this->updated($state, count($rows).' data Program Studi seluruh PT diproses.');
    }

    private function processAcademicCalendar(array $state, LegacyFeederService $feeder): array
    {
        [$rows, $state] = $this->fetch(
            $state,
            $feeder,
            'GetDetailPeriodePerkuliahan',
            limit: self::BATCH_SIZE,
        );
        $connection = DB::connection('simak_main');
        $created = 0;
        $updated = 0;
        $schedulesCreated = 0;
        $skipped = 0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                $skipped++;

                continue;
            }
            $programId = trim((string) ($row['id_prodi'] ?? ''));
            $semesterId = trim((string) ($row['id_semester'] ?? ''));
            if ($programId === '' || $semesterId === '') {
                $skipped++;

                continue;
            }

            $program = $connection->table('s1m4k_masterprodi')
                ->where('id_prodi', $programId)
                ->first(['id_perguruan_tinggi', 'id_fakultas']);
            if ($program === null || trim((string) ($program->id_fakultas ?? '')) === ''
                || ! $connection->table('s1m4k_masterfakultas')
                    ->where('id_fakultas', $program->id_fakultas)->exists()) {
                $skipped++;

                continue;
            }

            $semesterName = trim((string) ($row['nama_semester'] ?? ''));
            $values = [
                'id_perguruan_tinggi' => $program->id_perguruan_tinggi,
                'id_fakultas' => $program->id_fakultas,
                'id_prodi' => $programId,
                'periode' => $semesterName !== '' ? $semesterName : null,
                'tahun' => $semesterId,
                'namatahun' => $semesterName !== '' ? 'Tahun Akademik '.$semesterName : null,
            ];
            $calendar = $connection->table('s1m4k_kalender')
                ->where('tahun', $semesterId)
                ->where('id_prodi', $programId)
                ->first(['id_kalender']);
            if ($calendar !== null) {
                $connection->table('s1m4k_kalender')
                    ->where('id_kalender', $calendar->id_kalender)->update($values);
                $updated++;
            } else {
                $connection->table('s1m4k_kalender')->insert([
                    'id_kalender' => (string) Str::uuid(),
                    ...$values,
                ]);
                $created++;
            }
        }

        $state['records'] += count($rows);
        $state['stage_records'] += count($rows);
        $message = "{$created} kalender ditambahkan, {$updated} diperbarui, {$skipped} dilewati.";
        if (count($rows) < self::BATCH_SIZE) {
            return $this->completeStage($state, $message.' Seluruh data kalender akademik selesai diproses.');
        }
        $state['offset'] += count($rows);

        return $this->updated($state, $message);
    }

    private function processStudentGrades(array $state, LegacyFeederService $feeder): array
    {
        $connection = DB::connection('simak_main');
        $students = $connection->table('s1m4k_mastermhs')
            ->whereIn('id_status_mahasiswa', ['A', 'C', 'M', 'N'])
            ->whereNotNull('nim')
            ->whereRaw('TRIM(nim) <> ?', [''])
            ->orderBy('id_mahasiswa');
        $state['grade_student_offset'] ??= 0;
        $state['grade_page_offset'] ??= 0;
        $state['grade_student_total'] ??= (clone $students)->count();
        $state['student_grade_skip_reasons'] ??= [];
        $created = 0;
        $updated = 0;
        $schedulesCreated = 0;
        $skipped = 0;
        $skipReasons = ['response_tidak_valid' => 0];
        $approvalsCreated = 0;
        $startedAt = microtime(true);
        for ($request = 0; $request < self::STUDENT_REQUESTS_PER_BATCH; $request++) {
            if ($request > 0
                && microtime(true) - $startedAt >= self::STUDENT_BATCH_TIME_BUDGET_SECONDS) {
                break;
            }

            $studentOffset = (int) ($state['grade_student_offset'] ?? 0);
            $studentQuery = clone $students;
            $studentCursor = trim((string) ($state['grade_student_cursor'] ?? ''));
            if ($studentCursor !== '') {
                $studentQuery->where('id_mahasiswa', '>', $studentCursor);
            } else {
                $studentQuery->offset($studentOffset);
            }
            $student = $studentQuery->first([
                'id_mahasiswa', 'nim', 'id_perguruan_tinggi', 'id_fakultas', 'id_prodi',
            ]);
            if ($student === null) {
                $state['student_grades_created'] = (int) ($state['student_grades_created'] ?? 0) + $created;
                $state['student_grades_updated'] = (int) ($state['student_grades_updated'] ?? 0) + $updated;
                $state['student_schedules_created'] = (int) ($state['student_schedules_created'] ?? 0) + $schedulesCreated;
                $state['student_grades_skipped'] = (int) ($state['student_grades_skipped'] ?? 0) + $skipped;
                $state['student_approvals_created'] = (int) ($state['student_approvals_created'] ?? 0) + $approvalsCreated;
                $this->accumulateStudentGradeSkipReasons($state, $skipReasons);

                return $this->completeStudentGrades($state);
            }

            $nim = trim((string) $student->nim);
            [$rows, $state] = $this->fetch(
                $state,
                $feeder,
                'GetDetailNilaiPerkuliahanKelas',
                filter: "nim='".$this->filterValue($nim)."'",
                limit: self::BATCH_SIZE,
                offset: (int) ($state['grade_page_offset'] ?? 0),
            );
            foreach ($rows as $grade) {
                if (! is_array($grade)) {
                    $skipped++;
                    $skipReasons['response_tidak_valid']++;

                    continue;
                }

                $semesterId = trim((string) ($grade['id_semester'] ?? ''));
                $classId = trim((string) ($grade['id_kelas_kuliah'] ?? ''));
                $courseId = trim((string) ($grade['id_mata_kuliah'] ?? $grade['id_matkul'] ?? ''));
                $calendarId = null;
                if ($semesterId !== '' && trim((string) ($student->id_prodi ?? '')) !== '') {
                    $calendarId = $connection->table('s1m4k_kalender')
                        ->where('tahun', $semesterId)
                        ->where('id_prodi', $student->id_prodi)
                        ->value('id_kalender');
                }
                $gradeContext = [
                    'id_jadwal' => $classId !== '' ? $classId : null,
                    'id_matkul' => $courseId !== '' ? $courseId : null,
                    'id_kalender' => $calendarId,
                    'tahun' => $semesterId !== '' ? $semesterId : null,
                    'nilai_angka' => $grade['nilai_angka'] ?? null,
                    'nilai_huruf' => $grade['nilai_huruf'] ?? null,
                    'nilai_bobot' => $grade['nilai_indeks'] ?? null,
                    'input_nilai' => 'Y',
                ];
                $className = trim((string) ($grade['nama_kelas_kuliah'] ?? $grade['nama_kelas'] ?? ''));
                $className = $className !== '' ? mb_substr($className, 0, 25) : 'Feeder';

                $result = $connection->transaction(function () use (
                    $connection, $student, $gradeContext, $semesterId, $courseId, $className
                ): array {
                    $scheduleCreated = false;
                    if ($gradeContext['id_jadwal'] !== null
                        && ! $connection->table('s1m4k_jadwal')
                            ->where('id_jadwal', $gradeContext['id_jadwal'])
                            ->exists()) {
                        $connection->table('s1m4k_jadwal')->insert([
                            'id_jadwal' => $gradeContext['id_jadwal'],
                            'id_perguruan_tinggi' => $student->id_perguruan_tinggi,
                            'id_fakultas' => $student->id_fakultas,
                            'id_prodi' => $student->id_prodi,
                            'id_kalender' => $gradeContext['id_kalender'],
                            'id_matkul' => $gradeContext['id_matkul'],
                            'id_kelas_kuliah' => $gradeContext['id_jadwal'],
                            'nama_kelas' => $className,
                        ]);
                        $scheduleCreated = true;
                    }

                    $values = [
                        ...$gradeContext,
                        'id_perguruan_tinggi' => $student->id_perguruan_tinggi,
                        'id_fakultas' => $student->id_fakultas,
                        'id_prodi' => $student->id_prodi,
                        'id_mahasiswa' => $student->id_mahasiswa,
                        'status_krs' => 'B',
                        'krs_mbkm' => 'N',
                        'isi_edom' => 'Y',
                    ];
                    $existing = null;
                    $matchingKrs = $connection->table('s1m4k_krs')
                        ->where('id_mahasiswa', $student->id_mahasiswa);
                    if ($gradeContext['id_jadwal'] !== null) {
                        $existing = (clone $matchingKrs)
                            ->where('id_jadwal', $gradeContext['id_jadwal'])
                            ->first(['id_krs']);
                    }
                    if ($existing === null && $semesterId !== '' && $courseId !== '') {
                        $legacyMatches = (clone $matchingKrs)
                            ->where('tahun', $semesterId)
                            ->where('id_matkul', $courseId)
                            ->limit(2)
                            ->get(['id_krs']);
                        if ($legacyMatches->count() === 1) {
                            $existing = $legacyMatches->first();
                        }
                    }
                    if ($existing !== null) {
                        $connection->table('s1m4k_krs')
                            ->where('id_krs', $existing->id_krs)
                            ->update($values);
                        $created = false;
                    } else {
                        $connection->table('s1m4k_krs')->insert([
                            'id_krs' => (string) Str::uuid(),
                            ...$values,
                        ]);
                        $created = true;
                    }

                    $approvalCreated = false;
                    if ($gradeContext['id_kalender'] !== null) {
                        $approvalExists = $connection->table('s1m4k_pengesahankrs')
                            ->where('id_mahasiswa', $student->id_mahasiswa)
                            ->where('id_kalender', $gradeContext['id_kalender'])
                            ->exists();
                        if (! $approvalExists) {
                            $connection->table('s1m4k_pengesahankrs')->insert([
                                'id_pengesahan' => (string) Str::uuid(),
                                'id_mahasiswa' => $student->id_mahasiswa,
                                'id_kalender' => $gradeContext['id_kalender'],
                                'yang_mengesahkan' => 'Admin',
                                'tgl_pengesahan_kpro' => now()->toDateString(),
                                'nama_kpro' => 'Admin',
                                'tgl_pengesahan' => now()->toDateString(),
                            ]);
                            $approvalCreated = true;
                        }
                    }

                    return [$created, $approvalCreated, $scheduleCreated];
                });

                $created += (int) $result[0];
                $updated += (int) ! $result[0];
                $approvalsCreated += (int) $result[1];
                $schedulesCreated += (int) $result[2];
            }

            $state['records'] += count($rows);
            $state['stage_records'] += count($rows);

            $pageOffset = (int) ($state['grade_page_offset'] ?? 0);
            if (count($rows) < self::BATCH_SIZE) {
                $state['grade_student_offset'] = $studentOffset + 1;
                $state['grade_student_cursor'] = (string) $student->id_mahasiswa;
                $state['grade_page_offset'] = 0;
            } else {
                $state['grade_page_offset'] = $pageOffset + count($rows);
            }

            if ($state['grade_student_offset'] >= (int) $state['grade_student_total']
                && count($rows) < self::BATCH_SIZE) {
                $state['student_grades_created'] = (int) ($state['student_grades_created'] ?? 0) + $created;
                $state['student_grades_updated'] = (int) ($state['student_grades_updated'] ?? 0) + $updated;
                $state['student_schedules_created'] = (int) ($state['student_schedules_created'] ?? 0) + $schedulesCreated;
                $state['student_grades_skipped'] = (int) ($state['student_grades_skipped'] ?? 0) + $skipped;
                $state['student_approvals_created'] = (int) ($state['student_approvals_created'] ?? 0) + $approvalsCreated;
                $this->accumulateStudentGradeSkipReasons($state, $skipReasons);

                return $this->completeStudentGrades($state);
            }
        }

        $state['student_grades_created'] = (int) ($state['student_grades_created'] ?? 0) + $created;
        $state['student_grades_updated'] = (int) ($state['student_grades_updated'] ?? 0) + $updated;
        $state['student_schedules_created'] = (int) ($state['student_schedules_created'] ?? 0) + $schedulesCreated;
        $state['student_grades_skipped'] = (int) ($state['student_grades_skipped'] ?? 0) + $skipped;
        $state['student_approvals_created'] = (int) ($state['student_approvals_created'] ?? 0) + $approvalsCreated;
        $this->accumulateStudentGradeSkipReasons($state, $skipReasons);

        $message = $this->studentGradeBatchMessage(
            $created, $updated, $skipped, $approvalsCreated, $skipReasons, $schedulesCreated
        );

        return $this->updated($state, $message);
    }

    private function completeStudentGrades(array $state): array
    {
        $created = (int) ($state['student_grades_created'] ?? 0);
        $updated = (int) ($state['student_grades_updated'] ?? 0);
        $schedulesCreated = (int) ($state['student_schedules_created'] ?? 0);
        $skipped = (int) ($state['student_grades_skipped'] ?? 0);
        $approvalsCreated = (int) ($state['student_approvals_created'] ?? 0);

        $message = $this->studentGradeBatchMessage(
            $created,
            $updated,
            $skipped,
            $approvalsCreated,
            $state['student_grade_skip_reasons'] ?? [],
            $schedulesCreated,
        );

        return $this->completeStage(
            $state,
            $message.' Seluruh nilai mahasiswa selesai diproses.',
        );
    }

    private function accumulateStudentGradeSkipReasons(array &$state, array $skipReasons): void
    {
        foreach ($skipReasons as $reason => $count) {
            $state['student_grade_skip_reasons'][$reason] =
                (int) ($state['student_grade_skip_reasons'][$reason] ?? 0) + (int) $count;
        }
    }

    private function studentGradeBatchMessage(
        int $created,
        int $updated,
        int $skipped,
        int $approvalsCreated,
        array $skipReasons = [],
        int $schedulesCreated = 0,
    ): string {
        $message = "{$created} KRS ditambahkan, {$updated} nilai KRS diperbarui, {$skipped} baris nilai dilewati, "
            ."{$approvalsCreated} pengesahan dibuat, {$schedulesCreated} jadwal feeder dibuat.";
        $reasonLabels = [
            'response_tidak_valid' => 'respons tidak valid',
            'data_nilai_tidak_lengkap' => 'field nilai/identitas wajib kosong atau tidak valid',
        ];
        $details = [];
        foreach ($reasonLabels as $reason => $label) {
            $count = (int) ($skipReasons[$reason] ?? 0);
            if ($count > 0) {
                $details[] = "{$label}: {$count}";
            }
        }
        if ($details !== []) {
            $message .= ' Penyebab dilewati: '.implode('; ', $details).'.';
        }

        return $message;
    }

    private function fetch(
        array $state,
        LegacyFeederService $feeder,
        string $action,
        string $filter = '',
        string $order = '',
        int $limit = self::BATCH_SIZE,
        ?int $offset = null,
    ): array {
        $terminalMode = ($state['terminal_mode'] ?? false) === true;
        $requestLimit = $limit;
        $result = $feeder->initializationRequest(
            Crypt::decryptString($state['url_encrypted']),
            Crypt::decryptString($state['username_encrypted']),
            Crypt::decryptString($state['password_encrypted']),
            isset($state['token_encrypted']) && $state['token_encrypted'] !== ''
                ? Crypt::decryptString($state['token_encrypted'])
                : null,
            $action,
            [
                'filter' => $filter,
                'order' => $order,
                'limit' => $requestLimit,
                'offset' => $offset ?? (int) ($state['offset'] ?? 0),
            ],
            $terminalMode ? 300 : 8,
        );
        $rows = $result['rows'];
        if ($requestLimit > 0 && count($rows) > $requestLimit) {
            throw new RuntimeException("Respons {$action} melebihi batas batch {$requestLimit} baris.");
        }
        $state['token_encrypted'] = Crypt::encryptString($result['token']);

        return [$rows, $state];
    }

    private function importReferenceRows(string $action, array $rows): void
    {
        [$keyColumn, $labelColumn, $extraColumn] = array_pad(self::REFERENCE_ACTIONS[$action], 3, null);
        $connection = DB::connection('simak_main');
        foreach ($rows as $row) {
            if (! is_array($row) || ! array_key_exists($keyColumn, $row)) {
                continue;
            }
            $code = trim((string) $row[$keyColumn]);
            if ($code === '') {
                continue;
            }
            $values = [
                'ket_referensi' => $row[$labelColumn] ?? '',
                'ket_referensi2' => $extraColumn === null ? '' : ($row[$extraColumn] ?? ''),
            ];
            if ($action === 'GetJenisAktivitasMahasiswa') {
                $values['untuk_kampus_merdeka'] = $row['untuk_kampus_merdeka'] ?? null;
            }
            $existingId = $connection->table('s1m4k_kodereferensi')
                ->where('keterangan', $action)->where('kode_referensi', $code)
                ->value('id_referensi');
            if (is_string($existingId) && $existingId !== '') {
                $connection->table('s1m4k_kodereferensi')
                    ->where('id_referensi', $existingId)->update($values);
            } else {
                $connection->table('s1m4k_kodereferensi')->insert([
                    'id_referensi' => (string) Str::uuid(),
                    'kode_referensi' => $code,
                    'keterangan' => $action,
                    ...$values,
                ]);
            }
        }
    }

    private function currentProgram(array $state): ?object
    {
        $programIds = $state['program_ids'] ?? null;
        if (! is_array($programIds)) {
            $programIds = DB::connection('simak_main')->table('s1m4k_masterprodi')
                ->orderBy('id_prodi')->pluck('id_prodi')->map(
                    static fn (mixed $id): string => (string) $id
                )->all();
            $state['program_ids'] = $programIds;
        }
        $index = (int) ($state['program_index'] ?? 0);
        if (! isset($programIds[$index])) {
            return null;
        }

        return DB::connection('simak_main')->table('s1m4k_masterprodi')
            ->where('id_prodi', $programIds[$index])->first([
                'id_prodi', 'id_perguruan_tinggi', 'id_fakultas',
            ]);
    }

    private function advanceProgramPage(array $state, array $rows, string $message): array
    {
        $state['records'] += count($rows);
        $state['stage_records'] += count($rows);

        return $this->advanceProgramCursor($state, $rows, $message);
    }

    private function advanceProgramCursor(array $state, array $rows, string $message): array
    {
        if (count($rows) < self::BATCH_SIZE) {
            $state['program_index'] = (int) ($state['program_index'] ?? 0) + 1;
            $state['offset'] = 0;
        } else {
            $state['offset'] += count($rows);
        }
        if ($this->currentProgram($state) === null) {
            return $this->completeStage($state, $message.' Seluruh data pada tahap ini selesai diimpor.');
        }

        return $this->updated($state, $message);
    }

    private function advancePagedStage(array $state, array $rows, string $message): array
    {
        $state['records'] += count($rows);
        $state['stage_records'] += count($rows);
        if (count($rows) < self::BATCH_SIZE) {
            return $this->completeStage($state, $message.' Seluruh data pada tahap ini selesai diimpor.');
        }
        $state['offset'] += count($rows);

        return $this->updated($state, $message);
    }

    private function completeStage(array $state, string $message): array
    {
        $state['stage_complete'] = true;

        return $this->updated($state, $message);
    }

    private function resetStageForRerun(array $state): array
    {
        $state['offset'] = 0;
        $state['stage_records'] = 0;
        unset($state['progress']);
        unset($state['stage_complete']);

        switch ($state['stage']) {
            case 'references':
                $this->resetReferenceStage($state);
                break;
            case 'lecturers':
                $this->resetLecturerStage($state);
                break;
            case 'students':
                $this->resetStudentStage($state);
                break;
            case 'courses':
            case 'grades':
                $this->resetProgramStage($state);
                break;
            case 'student_grades':
                $state['grade_student_offset'] = 0;
                $state['grade_page_offset'] = 0;
                $state['grade_student_total'] = null;
                unset($state['grade_student_cursor']);
                $state['student_grades_created'] = 0;
                $state['student_grades_updated'] = 0;
                $state['student_schedules_created'] = 0;
                $state['student_grades_skipped'] = 0;
                $state['student_approvals_created'] = 0;
                $state['student_grade_skip_reasons'] = [];
                break;
            case 'all_programs':
                $state['institution_index'] = 0;
                break;
        }

        return $state;
    }

    private function resetReferenceStage(array &$state): void
    {
        $state['reference_index'] = 0;
        unset($state['wilayah_enrichment_offset']);
    }

    private function resetLecturerStage(array &$state): void
    {
        $state['substage'] = 0;
        $state['lecturer_assignments_skipped'] = 0;
        $state['lecturer_assignment_ids_seen'] = [];
        $state['lecturer_assignments_fetched'] = false;
        unset($state['lecturer_assignments_pending']);
    }

    private function resetStudentStage(array &$state): void
    {
        $this->resetProgramStage($state);
        $state['student_history_strategy'] = self::STUDENT_HISTORY_STRATEGY;
        $state['student_history_pending'] = [];
        $state['student_profile_batch'] = [];
        $state['student_history_legacy_backfill'] = false;
        $state['student_history_legacy_resume_profiles'] = false;
        $state['student_history_cursor'] = '';
        $state['student_profiles_complete'] = false;
        $state['history_not_found'] = 0;
        $state['student_history_lookup_failures'] = 0;
        $state['student_status_backfill_complete'] = false;
        $state['student_nim_conflict_ids'] = [];
        $state['student_nim_conflict_count'] = 0;
    }

    private function resetProgramStage(array &$state): void
    {
        $state['program_index'] = 0;
        $state['program_ids'] = null;
    }

    private function advance(array $state, string $nextStage): array
    {
        $state['completed_stages'] = $this->completedStages($state);
        $state['stage'] = $nextStage;
        $state['offset'] = 0;
        $state['stage_records'] = 0;
        unset($state['stage_complete']);
        if ($nextStage === 'lecturers') {
            $state['substage'] = 0;
            $state['lecturer_assignment_ids_seen'] = [];
            $state['lecturer_assignments_skipped'] = 0;
            $state['lecturer_assignments_fetched'] = false;
            unset($state['lecturer_assignments_pending']);
        }
        if (in_array($nextStage, ['students', 'courses', 'grades'], true)) {
            $state['program_index'] = 0;
            $state['program_ids'] = null;
        }
        if ($nextStage === 'students') {
            $state['student_history_strategy'] = self::STUDENT_HISTORY_STRATEGY;
            $state['student_history_pending'] = [];
            $state['student_profile_batch'] = [];
            $state['student_history_legacy_backfill'] = false;
            $state['student_history_legacy_resume_profiles'] = false;
            $state['student_history_cursor'] = '';
            $state['student_profiles_complete'] = false;
            $state['history_not_found'] = 0;
            $state['student_history_lookup_failures'] = 0;
            $state['student_status_backfill_complete'] = false;
            $state['student_nim_conflict_ids'] = [];
            $state['student_nim_conflict_count'] = 0;
        }
        if ($nextStage === 'all_programs') {
            $state['institution_index'] = 0;
        }
        if ($nextStage === 'student_grades') {
            $state['grade_student_offset'] = 0;
            $state['grade_page_offset'] = 0;
            $state['grade_student_total'] = null;
            unset($state['grade_student_cursor']);
            $state['student_grades_created'] = 0;
            $state['student_grades_updated'] = 0;
            $state['student_schedules_created'] = 0;
            $state['student_grades_skipped'] = 0;
            $state['student_approvals_created'] = 0;
            $state['student_grade_skip_reasons'] = [];
        }
        if ($nextStage === 'complete') {
            $state['completed_stages'] = array_values(array_unique([
                ...$state['completed_stages'],
                'complete',
            ]));
            $state['completed_at'] = now()->toIso8601String();
        }

        return $this->updated($state, $nextStage === 'complete'
            ? 'Inisialisasi Feeder selesai.'
            : 'Tahap sebelumnya selesai. Siap melanjutkan ke '.$this->stageLabel(['stage' => $nextStage]).'.');
    }

    private function updated(array $state, string $message): array
    {
        $backfilledStatusIds = (int) ($state['student_status_ids_backfilled'] ?? 0);
        if ($backfilledStatusIds > 0) {
            $message .= " {$backfilledStatusIds} ID status mahasiswa yang kosong diperbaiki.";
            $state['student_status_ids_backfilled'] = 0;
        }
        $state['last_batch'] = $message;
        $state['progress'] = $this->progress($state);
        $state['updated_at'] = now()->toIso8601String();

        return $state;
    }

    private function upsertById(
        string $table,
        string $key,
        string $id,
        array $values,
        array|Closure $insertDefaults = [],
    ): void {
        $connection = DB::connection('simak_main');
        $query = $connection->table($table)->where($key, $id);
        if ($query->exists()) {
            $query->update($values);

            return;
        }
        if ($insertDefaults instanceof Closure) {
            $insertDefaults = $insertDefaults();
        }
        $connection->table($table)->insert([
            $key => $id,
            ...$insertDefaults,
            ...$values,
        ]);
    }

    private function upsertByComposite(string $table, array $keys, array $values): void
    {
        $connection = DB::connection('simak_main');
        $query = $connection->table($table);
        foreach ($keys as $column => $value) {
            $query->where($column, $value);
        }
        if ($query->exists()) {
            $query->update($values);

            return;
        }
        $connection->table($table)->insert([...$keys, ...$values]);
    }

    private function assertCompositeAllProgramsKey(): void
    {
        $indexes = Schema::connection('simak_main')->getIndexes('s1m4k_masterallprodi');
        foreach ($indexes as $index) {
            if (($index['primary'] ?? false) === true
                && ($index['columns'] ?? []) === ['id_perguruan_tinggi']) {
                throw new RuntimeException('Kunci gabungan All Prodi belum diterapkan. Jalankan SQL upgrade database/manual/s1m4k_masterallprodi_composite_key.sql.');
            }
        }
    }

    private function studentProgress(array $state): float
    {
        $programIds = $state['program_ids'] ?? [];
        $programCount = max(1, count(is_array($programIds) ? $programIds : []));
        $index = (int) ($state['program_index'] ?? 0);
        $withinProgram = min(
            0.99,
            ((int) ($state['stage_records'] ?? 0))
                / (((int) ($state['stage_records'] ?? 0)) + self::BATCH_SIZE),
        );

        return min(0.99, ($index + $withinProgram) / $programCount);
    }

    private function allProgramsProgress(array $state): float
    {
        $total = max(1, (int) DB::connection('simak_main')->table('s1m4k_masterallpt')->count());
        $index = (int) ($state['institution_index'] ?? 0);
        $withinInstitution = min(
            0.99,
            ((int) ($state['offset'] ?? 0))
                / (((int) ($state['offset'] ?? 0)) + self::BATCH_SIZE),
        );

        return min(0.99, ($index + $withinInstitution) / $total);
    }

    private function studentGradesProgress(array $state): float
    {
        $total = max(1, (int) ($state['grade_student_total'] ?? 0));

        return min(0.99, (int) ($state['grade_student_offset'] ?? 0) / $total);
    }

    private function limitNik(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $nik = trim((string) $value);

        return $nik === '' ? null : substr($nik, 0, 16);
    }

    private function safeDate(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '0000-00-00') {
            return null;
        }
        $date = substr($value, 0, 10);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 ? $date : null;
    }

    private function studentCountryCode(array $student): ?string
    {
        $values = array_filter([
            trim((string) ($student['id_negara'] ?? '')),
            trim((string) ($student['kewarganegaraan'] ?? '')),
        ], static fn (string $value): bool => $value !== '');
        foreach ($values as $value) {
            $country = DB::connection('simak_main')->table('s1m4k_kodereferensi')
                ->where('keterangan', 'GetNegara')
                ->where('kode_referensi', $value)
                ->first(['kode_referensi']);
            if ($country !== null) {
                return (string) $country->kode_referensi;
            }

            $country = DB::connection('simak_main')->table('s1m4k_kodereferensi')
                ->where('keterangan', 'GetNegara')
                ->whereRaw('LOWER(TRIM(ket_referensi)) = ?', [mb_strtolower($value)])
                ->first(['kode_referensi']);
            if ($country !== null) {
                return (string) $country->kode_referensi;
            }
        }
        if ($values === []) {
            return null;
        }

        throw new RuntimeException('Negara mahasiswa tidak ditemukan pada referensi Feeder yang sudah diimpor: '.reset($values));
    }

    private function filterValue(string $value): string
    {
        return str_replace("'", "''", $value);
    }
}
