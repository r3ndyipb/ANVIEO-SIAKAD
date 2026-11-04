<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class FeederExportService
{
    private const JOB_TTL_MINUTES = 1440;

    private const TYPES = [
        'MatkulKurikulum' => 'Mata Kuliah Kurikulum',
        'KelasKuliah' => 'Kelas Perkuliahan',
        'PesertaKelasKuliah' => 'Mahasiswa Peserta Kelas Perkuliahan',
        'DosenPengajarKelasKuliah' => 'Dosen Pengampu Kelas Perkuliahan',
        'NilaiPerkuliahanKelas' => 'Nilai Mahasiswa Kelas Perkuliahan',
        'PerkuliahanMahasiswa' => 'Aktivitas Perkuliahan Mahasiswa',
        'MahasiswaLulusDO' => 'Mahasiswa Lulus/DO',
        'DosenPembimbing' => 'Aktivitas Mahasiswa dan Dosen Pembimbing',
    ];

    public function types(): array
    {
        return self::TYPES;
    }

    public function calendarCourseSummary(string $programId, string $calendarId): array
    {
        $courses = $this->courseQuery($programId, $calendarId)->get();
        $required = 0;
        $elective = 0;

        foreach ($courses as $course) {
            $credits = (float) ($course->sks_mata_kuliah ?? 0);
            if (in_array($course->id_jenis_mata_kuliah, ['B', 'D'], true)) {
                $elective += (int) round($credits);
            } elseif (in_array($course->id_jenis_mata_kuliah, ['A', 'C', 'S'], true)) {
                $required += (int) round($credits);
            }
        }

        return [
            'courses' => $courses,
            'required' => $required,
            'elective' => $elective,
            'total' => $required + $elective,
        ];
    }

    public function counts(string $programId, string $calendarId, string $semesterId, ?array $types = null): array
    {
        $counts = [];
        foreach ($types ?? array_keys(self::TYPES) as $type) {
            $label = self::TYPES[$type] ?? $type;
            $counts[$type] = [
                'label' => $label,
                'count' => $type === 'DosenPembimbing'
                    ? null
                    : $this->countCandidates($type, $this->query($type, $programId, $calendarId, $semesterId)),
                'realtime' => $type === 'DosenPembimbing',
            ];
        }

        return $counts;
    }

    public function detailCounts(array $exportIds): array
    {
        $this->ensureDetailTable();
        if ($exportIds === []) {
            return [];
        }

        $counts = [];
        foreach (DB::connection('simak_main')->table('s1m4k_export_detail')
            ->whereIn('id_export', $exportIds)
            ->selectRaw('id_export, status, COUNT(*) AS total')
            ->groupBy('id_export', 'status')->get() as $row) {
            $counts[(string) $row->id_export][(string) $row->status] = (int) $row->total;
        }

        return $counts;
    }

    public function createCurriculum(string $programId, object $calendar, LegacyFeederService $feeder): string
    {
        $summary = $this->calendarCourseSummary($programId, (string) $calendar->id_kalender);
        if ($summary['courses']->isEmpty()) {
            throw new RuntimeException('Data mata kuliah pada kalender terpilih kosong. Periksa jadwal dan data internal SIMAK terlebih dahulu.');
        }

        $programName = DB::connection('simak_main')->table('s1m4k_masterprodi')
            ->where('id_prodi', $programId)->value('nama_program_studi');
        if (! is_string($programName) || trim($programName) === '') {
            throw new RuntimeException('Nama program studi tidak ditemukan pada data internal SIMAK.');
        }

        $record = [
            'nama_kurikulum' => ucwords(strtolower($programName)).' '.$calendar->periode,
            'id_prodi' => $programId,
            'id_semester' => (string) $calendar->tahun,
            'jumlah_sks_pilihan' => $summary['elective'],
            'jumlah_sks_wajib' => $summary['required'],
            'jumlah_sks_lulus' => $summary['total'],
        ];
        $response = $feeder->exportAction('InsertKurikulum', ['record' => $record]);
        $curriculumId = trim((string) ($response['data']['id_kurikulum'] ?? ''));
        if ((string) ($response['error_code'] ?? '') !== '0'
            && $this->isDuplicateInsertResponse('Kurikulum', $response)) {
            $curriculumId = $this->existingCurriculumId(
                $programId,
                (string) $calendar->tahun,
                $record['nama_kurikulum'],
                $feeder,
            );
        }
        if ((string) ($response['error_code'] ?? '') !== '0' && $curriculumId === '') {
            $this->assertFeederSuccess($response);
        }
        if ($curriculumId === '') {
            throw new RuntimeException('Feeder menyatakan berhasil, tetapi tidak mengembalikan ID kurikulum.');
        }

        $this->saveCurriculumAndQueue($curriculumId, $programId, $calendar, $record, $summary);

        return $curriculumId;
    }

    private function existingCurriculumId(
        string $programId,
        string $semesterId,
        string $name,
        LegacyFeederService $feeder,
    ): string {
        $response = $feeder->exportAction('GetListKurikulum', [
            'filter' => "id_prodi='".$this->escapeFilter($programId)
                ."' AND id_semester='".$this->escapeFilter($semesterId)."'",
            'order' => '',
            'limit' => 100,
            'offset' => 0,
        ]);
        if ((string) ($response['error_code'] ?? '') !== '0') {
            return '';
        }

        $rows = $response['data'] ?? [];
        $rows = is_array($rows) && array_is_list($rows) ? $rows : [$rows];
        foreach ($rows as $curriculum) {
            if (! is_array($curriculum)
                || trim((string) ($curriculum['id_prodi'] ?? '')) !== $programId
                || trim((string) ($curriculum['id_semester'] ?? '')) !== $semesterId
                || trim((string) ($curriculum['nama_kurikulum'] ?? '')) !== $name) {
                continue;
            }

            $curriculumId = trim((string) ($curriculum['id_kurikulum'] ?? ''));
            if ($curriculumId !== '') {
                return $curriculumId;
            }
        }

        return '';
    }

    public function updateCurriculum(string $curriculumId, string $programId, object $calendar, LegacyFeederService $feeder): void
    {
        $summary = $this->calendarCourseSummary($programId, (string) $calendar->id_kalender);
        if ($summary['courses']->isEmpty()) {
            throw new RuntimeException('Data mata kuliah pada kalender terpilih kosong. Periksa jadwal dan data internal SIMAK terlebih dahulu.');
        }

        $programName = DB::connection('simak_main')->table('s1m4k_masterprodi')
            ->where('id_prodi', $programId)->value('nama_program_studi');
        if (! is_string($programName) || trim($programName) === '') {
            throw new RuntimeException('Nama program studi tidak ditemukan pada data internal SIMAK.');
        }

        $record = [
            'nama_kurikulum' => ucwords(strtolower($programName)).' '.$calendar->periode,
            'id_prodi' => $programId,
            'id_semester' => (string) $calendar->tahun,
            'jumlah_sks_pilihan' => $summary['elective'],
            'jumlah_sks_wajib' => $summary['required'],
            'jumlah_sks_lulus' => $summary['total'],
        ];
        $response = $feeder->exportAction('UpdateKurikulum', [
            'key' => ['id_kurikulum' => $curriculumId],
            'record' => $record,
        ]);
        $this->assertFeederSuccess($response);

        $this->saveCurriculumAndQueue($curriculumId, $programId, $calendar, $record, $summary);
    }

    public function start(object $export, object $user, LegacyFeederService $feeder): array
    {
        $this->ensureDetailTable();
        $type = (string) $export->data;
        if (! isset(self::TYPES[$type])) {
            throw new RuntimeException('Jenis data ekspor tidak dikenal.');
        }

        $programId = (string) $export->id_prodi;
        $calendarId = (string) $export->id_kalender;
        $semesterId = (string) $export->id_semester;

        if ($type === 'DosenPembimbing') {
            DB::connection('simak_main')->table('s1m4k_export')
                ->where('id_export', $export->id_export)
                ->update(['status' => 'Y', 'waktu' => '00:00:00']);

            return [
                'complete' => true,
                'progress' => 100,
                'type' => $type,
                'export_id' => (string) $export->id_export,
                'export_status' => 'Y',
                'export_time' => '00:00:00',
                'message' => 'Data aktivitas mahasiswa dan dosen pembimbing dicatat melalui menu Aktivitas Mahasiswa SIMAK, sesuai alur legacy.',
                'failures' => [],
            ];
        }

        $total = $this->countCandidates($type, $this->query($type, $programId, $calendarId, $semesterId));
        if ($total === 0) {
            return [
                'empty' => true,
                'message' => 'Data yang akan diekspor kosong. Periksa data internal SIMAK terlebih dahulu.',
                'count' => 0,
            ];
        }

        $jobId = (string) Str::uuid();
        $state = [
            'id' => $jobId,
            'export_id' => (string) $export->id_export,
            'type' => $type,
            'program_id' => $programId,
            'calendar_id' => $calendarId,
            'semester_id' => $semesterId,
            'owner_id' => (string) $user->legacy_id,
            'owner_role' => (string) $user->role,
            'cursor' => 0,
            'last_source_id' => null,
            'total' => $total,
            'success_count' => 0,
            'skipped_count' => 0,
            'failures' => [],
            'only_ids' => null,
            'started_at' => now()->timestamp,
            'complete' => false,
        ];
        Cache::put($this->cacheKey($jobId), $state, now()->addMinutes(self::JOB_TTL_MINUTES));

        return [
            'job_id' => $jobId,
            'type' => $type,
            'label' => self::TYPES[$type],
            'total' => $total,
            'processed' => 0,
            'progress' => 0,
            'complete' => false,
            'failures' => [],
        ];
    }

    public function step(string $jobId, object $user, LegacyFeederService $feeder): array
    {
        $lock = Cache::lock('feeder-export-lock:'.$jobId, 30);
        if (! $lock->get()) {
            throw new RuntimeException('Ekspor ini sedang diproses pada permintaan lain. Tunggu sebentar lalu lanjutkan.');
        }

        try {
            $state = $this->ownedState($jobId, $user);
            $state['skipped_count'] ??= 0;
            if ($state['complete']) {
                return $this->publicState($state);
            }

            $type = $state['type'];
            $query = $this->query(
                $type,
                $state['program_id'],
                $state['calendar_id'],
                $state['semester_id'],
                $state['only_ids'],
            )->distinct();
            if ($state['last_source_id'] !== null) {
                $query->where($this->sourceColumn($type), '>', $state['last_source_id']);
            }
            $row = $query->orderBy('source_id')->limit(1)->first();

            if ($row === null) {
                $state['cursor'] = $state['total'];
                $state['complete'] = true;
                Cache::put($this->cacheKey($jobId), $state, now()->addMinutes(self::JOB_TTL_MINUTES));
                $this->finish($state);
                $result = $this->publicState($state);
                $result['just_completed'] = true;

                return $result;
            }

            $sourceId = (string) $row->source_id;
            $fingerprint = $this->fingerprintPayload($type, $row, $state);
            $payloadHash = hash('sha256', json_encode(
                $fingerprint,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ));
            $detailQuery = DB::connection('simak_main')->table('s1m4k_export_detail')
                ->where('id_export', $state['export_id'])
                ->where('source_id', $sourceId);
            $previous = $detailQuery->first();
            $alreadyCurrent = $previous !== null
                && hash_equals((string) ($previous->payload_hash ?? ''), $payloadHash);
            if ($alreadyCurrent) {
                if ($previous->status !== 'Y') {
                    $this->saveDetail($state, $sourceId, $payloadHash, $fingerprint, null, false);
                }
                $state['skipped_count']++;
                $state['cursor']++;
                $state['last_source_id'] = $sourceId;
                Cache::put($this->cacheKey($jobId), $state, now()->addMinutes(self::JOB_TTL_MINUTES));
                if ($state['cursor'] >= $state['total']) {
                    $state['complete'] = true;
                    Cache::put($this->cacheKey($jobId), $state, now()->addMinutes(self::JOB_TTL_MINUTES));
                    $this->finish($state);
                }
                $result = $this->publicState($state, $state['complete']);
                $result['just_completed'] = $state['complete'];

                return $result;
            }

            $latestFailure = null;
            try {
                $response = $this->exportRow($type, $row, $state, $feeder, $previous);
                $errorCode = (string) ($response['error_code'] ?? '');
                if ($errorCode === '0' || $this->isAlreadyExported($type, $response)) {
                    $this->applySuccess($type, $row, $state);
                    $this->saveDetail($state, $sourceId, $payloadHash, $fingerprint, null);
                    $state['success_count']++;
                } else {
                    $latestFailure = $this->failure(
                        $sourceId,
                        $this->identity($type, $row),
                        $errorCode === '' ? 'Respons Feeder tidak memuat error_code.' : $errorCode,
                        trim((string) ($response['error_desc'] ?? 'Feeder tidak menjelaskan penyebab kegagalan.')),
                    );
                    $this->saveDetail($state, $sourceId, $payloadHash, $fingerprint, $latestFailure);
                    $state['failures'][] = $latestFailure;
                }
            } catch (RuntimeException $exception) {
                $latestFailure = $this->failure(
                    $sourceId,
                    $this->identity($type, $row),
                    'SIMAK',
                    $exception->getMessage(),
                );
                $this->saveDetail($state, $sourceId, $payloadHash, $fingerprint, $latestFailure);
                $state['failures'][] = $latestFailure;
            }

            $state['cursor']++;
            $state['last_source_id'] = $sourceId;
            Cache::put($this->cacheKey($jobId), $state, now()->addMinutes(self::JOB_TTL_MINUTES));
            if ($state['cursor'] >= $state['total']) {
                $state['complete'] = true;
                Cache::put($this->cacheKey($jobId), $state, now()->addMinutes(self::JOB_TTL_MINUTES));
                $this->finish($state);
            }

            $result = $this->publicState($state, $state['complete']);
            if ($latestFailure !== null && ! $state['complete']) {
                $result['failure'] = $latestFailure;
            }
            $result['just_completed'] = $state['complete'];

            return $result;
        } finally {
            $lock->release();
        }
    }

    public function retry(string $jobId, object $user): array
    {
        $previous = $this->ownedState($jobId, $user);
        if (! $previous['complete'] || $previous['failures'] === []) {
            throw new RuntimeException('Tidak ada data gagal yang siap dicoba ulang.');
        }

        $failedIds = array_values(array_unique(array_map(
            static fn (array $failure): string => (string) $failure['source_id'],
            $previous['failures'],
        )));
        $newId = (string) Str::uuid();
        $state = [
            ...$previous,
            'id' => $newId,
            'cursor' => 0,
            'last_source_id' => null,
            'total' => count($failedIds),
            'success_count' => 0,
            'skipped_count' => 0,
            'failures' => [],
            'only_ids' => $failedIds,
            'started_at' => now()->timestamp,
            'complete' => false,
        ];
        Cache::put($this->cacheKey($newId), $state, now()->addMinutes(self::JOB_TTL_MINUTES));

        return [
            'job_id' => $newId,
            'type' => $state['type'],
            'label' => self::TYPES[$state['type']],
            'export_time' => DB::connection('simak_main')->table('s1m4k_export')
                ->where('id_export', $state['export_id'])->value('waktu'),
            'total' => $state['total'],
            'processed' => 0,
            'progress' => 0,
            'complete' => false,
            'failures' => [],
        ];
    }

    public function status(string $jobId, object $user): array
    {
        return $this->publicState($this->ownedState($jobId, $user));
    }

    private function saveCurriculumAndQueue(
        string $curriculumId,
        string $programId,
        object $calendar,
        array $record,
        array $summary,
    ): void {
        $connection = DB::connection('simak_main');
        $connection->transaction(function () use ($connection, $curriculumId, $programId, $calendar, $record, $summary): void {
            $values = [
                'nama_kurikulum' => $record['nama_kurikulum'],
                'id_prodi' => $programId,
                'id_semester' => (string) $calendar->tahun,
                'semester_mulai_berlaku' => (string) $calendar->periode,
                'jumlah_sks_lulus' => (string) $summary['total'],
                'jumlah_sks_wajib' => (string) $summary['required'],
                'jumlah_sks_pilihan' => (string) $summary['elective'],
            ];
            $existing = $connection->table('s1m4k_kurikulum')
                ->where('id_kurikulum', $curriculumId)
                ->where('id_prodi', $programId)
                ->exists();
            if ($existing) {
                $connection->table('s1m4k_kurikulum')
                    ->where('id_kurikulum', $curriculumId)->where('id_prodi', $programId)->update($values);
            } else {
                $connection->table('s1m4k_kurikulum')->insert([
                    'id_kurikulum' => $curriculumId,
                    ...$values,
                ]);
            }

            $hasExports = $connection->table('s1m4k_export')
                ->where('id_kurikulum', $curriculumId)->where('id_prodi', $programId)->exists();
            if (! $hasExports) {
                $connection->table('s1m4k_export')->insert(array_map(
                    static fn (string $type): array => [
                        'id_export' => (string) Str::uuid(),
                        'id_prodi' => $programId,
                        'id_kurikulum' => $curriculumId,
                        'id_kalender' => (string) $calendar->id_kalender,
                        'id_semester' => (string) $calendar->tahun,
                        'id_data' => '',
                        'data' => $type,
                        'status' => 'N',
                    ],
                    array_keys(self::TYPES),
                ));
            } else {
                $connection->table('s1m4k_export')
                    ->where('id_kurikulum', $curriculumId)
                    ->where('id_prodi', $programId)
                    ->update([
                        'id_kalender' => (string) $calendar->id_kalender,
                        'id_semester' => (string) $calendar->tahun,
                        'status' => 'N',
                        'waktu' => null,
                    ]);
            }
        });
    }

    private function courseQuery(string $programId, string $calendarId): Builder
    {
        return DB::connection('simak_main')->table('s1m4k_kalender as kal')
            ->leftJoin('s1m4k_jadwal as jad', 'kal.id_kalender', '=', 'jad.id_kalender')
            ->leftJoin('s1m4k_mastermk as mk', 'jad.id_matkul', '=', 'mk.id_matkul')
            ->where('kal.id_kalender', $calendarId)
            ->where('kal.id_prodi', $programId)
            ->whereNotNull('jad.id_matkul')
            ->select([
                'jad.id_matkul as source_id',
                'jad.id_matkul',
                'mk.sks_mata_kuliah',
                'mk.sks_tatap_muka',
                'mk.sks_praktek',
                'mk.sks_praktek_lapangan',
                'mk.sks_simulasi',
                'mk.id_jenis_mata_kuliah',
                'mk.semester',
            ])
            ->distinct();
    }

    private function countCandidates(string $type, Builder $query): int
    {
        $candidates = (clone $query)->reorder()->select($this->sourceColumn($type))->distinct();

        return DB::connection('simak_main')->query()
            ->fromSub($candidates, 'export_candidates')
            ->count();
    }

    private function query(string $type, string $programId, string $calendarId, string $semesterId, ?array $onlyIds = null): Builder
    {
        $connection = DB::connection('simak_main');
        $query = match ($type) {
            'MatkulKurikulum' => $this->courseQuery($programId, $calendarId),
            'KelasKuliah' => $connection->table('s1m4k_kalender as kal')
                ->join('s1m4k_jadwal as jad', 'kal.id_kalender', '=', 'jad.id_kalender')
                ->leftJoin('s1m4k_masterdosen as dos', 'jad.dosen1', '=', 'dos.id_dosen')
                ->leftJoin('s1m4k_mastermk as mk', 'jad.id_matkul', '=', 'mk.id_matkul')
                ->leftJoin('s1m4k_masterruang as ruang', 'jad.id_ruang', '=', 'ruang.id_ruang')
                ->where('kal.id_kalender', $calendarId)->where('kal.id_prodi', $programId)
                ->select([
                    'jad.id_jadwal as source_id', 'jad.id_jadwal', 'jad.id_matkul', 'jad.nama_kelas',
                    'jad.kampus_merdeka', 'jad.lingkup_km', 'jad.mode_km',
                    'jad.id_kelas_kuliah', 'kal.tgl_mulai_krs', 'kal.tgl_akhir_isinilai',
                    'mk.nama_mata_kuliah', 'mk.sks_mata_kuliah', 'mk.sks_tatap_muka', 'mk.sks_praktek',
                    'mk.sks_praktek_lapangan', 'mk.sks_simulasi', 'ruang.kapasitas',
                ]),
            'PesertaKelasKuliah' => $connection->table('s1m4k_jadwal as jad')
                ->join('s1m4k_krs as krs', 'jad.id_jadwal', '=', 'krs.id_jadwal')
                ->join('s1m4k_mastermhs as mhs', 'krs.id_mahasiswa', '=', 'mhs.id_mahasiswa')
                ->where('jad.id_kalender', $calendarId)->where('jad.id_prodi', $programId)
                ->whereNotNull('jad.id_kelas_kuliah')->where('jad.id_kelas_kuliah', '<>', '')
                ->whereNotNull('mhs.id_registrasi_mahasiswa')->where('mhs.id_registrasi_mahasiswa', '<>', '')
                ->select([
                    'krs.id_krs as source_id', 'jad.id_kelas_kuliah',
                    'mhs.id_registrasi_mahasiswa', 'mhs.nim', 'mhs.nama_mahasiswa',
                ]),
            'DosenPengajarKelasKuliah' => $connection->table('s1m4k_akd as akd')
                ->join('s1m4k_jadwal as jad', 'akd.id_jadwal', '=', 'jad.id_jadwal')
                ->leftJoin('s1m4k_masterdosen as dos', 'akd.id_dosen', '=', 'dos.id_dosen')
                ->leftJoin('s1m4k_mastermk as mk', 'jad.id_matkul', '=', 'mk.id_matkul')
                ->where('akd.id_kalender', $calendarId)->where('akd.id_prodi', $programId)
                ->where('akd.ket', 'dosen')
                ->whereNotNull('jad.id_kelas_kuliah')->where('jad.id_kelas_kuliah', '<>', '')
                ->whereNotNull('dos.id_dosen')->where('dos.id_dosen', '<>', '')
                ->select([
                    'akd.id_akd as source_id', 'akd.id_jadwal', 'dos.id_dosen', 'dos.nidn', 'dos.nama_dosen',
                    'mk.sks_mata_kuliah', 'jad.id_kelas_kuliah', 'akd.rencana', 'akd.realisasi',
                ]),
            'NilaiPerkuliahanKelas' => $connection->table('s1m4k_jadwal as jad')
                ->join('s1m4k_krs as krs', 'jad.id_jadwal', '=', 'krs.id_jadwal')
                ->join('s1m4k_mastermhs as mhs', 'krs.id_mahasiswa', '=', 'mhs.id_mahasiswa')
                ->where('jad.id_kalender', $calendarId)->where('jad.id_prodi', $programId)
                ->whereNotNull('jad.id_kelas_kuliah')->where('jad.id_kelas_kuliah', '<>', '')
                ->whereNotNull('mhs.id_registrasi_mahasiswa')->where('mhs.id_registrasi_mahasiswa', '<>', '')
                ->select([
                    'krs.id_krs as source_id', 'jad.id_kelas_kuliah',
                    'mhs.id_registrasi_mahasiswa', 'mhs.nim', 'mhs.nama_mahasiswa',
                    'krs.nilai_angka', 'krs.nilai_bobot', 'krs.nilai_huruf',
                ]),
            'PerkuliahanMahasiswa' => $connection->table('s1m4k_akm as akm')
                ->join('s1m4k_mastermhs as mhs', 'akm.id_mahasiswa', '=', 'mhs.id_mahasiswa')
                ->where('akm.id_kalender', $calendarId)->where('akm.id_prodi', $programId)
                ->whereNotNull('mhs.id_registrasi_mahasiswa')->where('mhs.id_registrasi_mahasiswa', '<>', '')
                ->select([
                    'akm.id_ipmhs as source_id', 'akm.id_ipmhs', 'akm.id_mahasiswa',
                    'mhs.id_registrasi_mahasiswa', 'mhs.nim', 'mhs.nama_mahasiswa',
                    'akm.status_mahasiswa', 'akm.ips', 'akm.ipk', 'akm.sks_semester', 'akm.sks_program',
                ]),
            'MahasiswaLulusDO' => $connection->table('s1m4k_mastermhs as mhs')
                ->leftJoin('s1m4k_akm as akm', function ($join) use ($calendarId): void {
                    $join->on('mhs.id_mahasiswa', '=', 'akm.id_mahasiswa')
                        ->where('akm.id_kalender', '=', $calendarId);
                })
                ->where('mhs.id_prodi', $programId)
                ->whereNotIn('mhs.id_status_mahasiswa', ['A', 'C'])
                ->select([
                    'mhs.id_mahasiswa as source_id', 'mhs.id_mahasiswa', 'mhs.nim', 'mhs.nama_mahasiswa',
                    'mhs.id_registrasi_mahasiswa', 'mhs.id_status_mahasiswa',
                    'akm.tgl_keluar', 'akm.ket', 'akm.no_sk', 'akm.tgl_sk', 'akm.ipk', 'akm.id_ipmhs',
                    'akm.no_ijazah',
                ]),
            default => throw new RuntimeException('Jenis data ekspor tidak didukung.'),
        };

        if ($onlyIds !== null) {
            $query->whereIn($this->sourceColumn($type), $onlyIds);
        }

        return $query;
    }

    private function sourceColumn(string $type): string
    {
        return match ($type) {
            'MatkulKurikulum' => 'jad.id_matkul',
            'KelasKuliah' => 'jad.id_jadwal',
            'PesertaKelasKuliah', 'NilaiPerkuliahanKelas' => 'krs.id_krs',
            'DosenPengajarKelasKuliah' => 'akd.id_akd',
            'PerkuliahanMahasiswa' => 'akm.id_ipmhs',
            'MahasiswaLulusDO' => 'mhs.id_mahasiswa',
            default => throw new RuntimeException('Jenis data ekspor tidak didukung.'),
        };
    }

    private function exportRow(
        string $type,
        object $row,
        array $state,
        LegacyFeederService $feeder,
        ?object $previous,
    ): array {
        return match ($type) {
            'MatkulKurikulum' => $this->exportCurriculumCourse($row, $state, $feeder, $previous),
            'KelasKuliah' => $this->exportClass($row, $state, $feeder),
            'PesertaKelasKuliah' => $this->exportParticipant($row, $state, $feeder, $previous),
            'DosenPengajarKelasKuliah' => $this->exportLecturer($row, $state, $feeder, $previous),
            'NilaiPerkuliahanKelas' => $feeder->exportAction('UpdateNilaiPerkuliahanKelas', [
                'key' => [
                    'id_kelas_kuliah' => $row->id_kelas_kuliah,
                    'id_registrasi_mahasiswa' => $row->id_registrasi_mahasiswa,
                ],
                'record' => $this->fingerprintPayload($type, $row, $state),
            ]),
            'PerkuliahanMahasiswa' => $this->exportStudentActivity($row, $state, $feeder, $previous),
            'MahasiswaLulusDO' => $this->exportStudentExit($row, $state, $feeder, $previous),
            default => throw new RuntimeException('Jenis data ekspor tidak didukung.'),
        };
    }

    private function fingerprintPayload(string $type, object $row, array $state): array
    {
        return match ($type) {
            'MatkulKurikulum' => [
                'id_kurikulum' => $this->curriculumId($state),
                'id_matkul' => (string) $row->id_matkul,
                'semester' => (int) ($row->semester ?? 0),
                'sks_mata_kuliah' => $this->formatNumber($row->sks_mata_kuliah, 2),
                'sks_tatap_muka' => $this->formatNumber($row->sks_tatap_muka ?? 0, 2),
                'sks_praktek' => $this->formatNumber($row->sks_praktek ?? 0, 2),
                'sks_praktek_lapangan' => $this->formatNumber($row->sks_praktek_lapangan ?? 0, 2),
                'sks_simulasi' => $this->formatNumber($row->sks_simulasi ?? 0, 2),
                'apakah_wajib' => in_array($row->id_jenis_mata_kuliah, ['A', 'C', 'S'], true) ? '1' : '0',
            ],
            'KelasKuliah' => [
                'id_prodi' => $state['program_id'],
                'id_semester' => $state['semester_id'],
                'id_matkul' => (string) $row->id_matkul,
                'nama_kelas_kuliah' => mb_substr((string) $row->nama_kelas, 0, 5),
                'bahasan' => '',
                'tanggal_mulai_efektif' => $this->feederDate($row->tgl_mulai_krs),
                'tanggal_akhir_efektif' => $this->feederDate($row->tgl_akhir_isinilai),
                'sks_mata_kuliah' => $this->formatNumber($row->sks_mata_kuliah ?? 0, 2),
                'sks_tatap_muka' => $this->formatNumber($row->sks_tatap_muka ?? 0, 2),
                'sks_praktek' => $this->formatNumber($row->sks_praktek ?? 0, 2),
                'sks_praktek_lapangan' => $this->formatNumber($row->sks_praktek_lapangan ?? 0, 2),
                'sks_simulasi' => $this->formatNumber($row->sks_simulasi ?? 0, 2),
                'kapasitas' => $row->kapasitas,
                'lingkup' => $row->lingkup_km,
                'mode' => $row->mode_km,
            ],
            'PesertaKelasKuliah' => [
                'id_kelas_kuliah' => (string) $row->id_kelas_kuliah,
                'id_registrasi_mahasiswa' => (string) $row->id_registrasi_mahasiswa,
            ],
            'DosenPengajarKelasKuliah' => [
                'id_dosen' => (string) $row->id_dosen,
                'id_kelas_kuliah' => (string) $row->id_kelas_kuliah,
                'sks_substansi_total' => $this->formatNumber((float) ($row->sks_mata_kuliah ?? 0) / max(1, (int) DB::connection('simak_main')->table('s1m4k_akd')
                    ->where('id_jadwal', $row->id_jadwal)
                    ->where('id_kalender', $state['calendar_id'])
                    ->where('id_prodi', $state['program_id'])
                    ->where('ket', 'dosen')
                    ->count()), 2),
                'rencana_minggu_pertemuan' => empty($row->rencana) ? 16 : $row->rencana,
                'realisasi_minggu_pertemuan' => empty($row->realisasi) ? 0 : $row->realisasi,
                'id_jenis_evaluasi' => '1',
            ],
            'NilaiPerkuliahanKelas' => [
                'nilai_huruf' => $row->nilai_huruf,
                'nilai_indeks' => $this->formatNumber($row->nilai_bobot, 2),
                'nilai_angka' => $this->formatNumber($row->nilai_angka, 1),
            ],
            'PerkuliahanMahasiswa' => [
                'id_registrasi_mahasiswa' => (string) $row->id_registrasi_mahasiswa,
                'id_semester' => $state['semester_id'],
                'id_status_mahasiswa' => $row->status_mahasiswa,
                'ips' => $row->ips,
                'ipk' => $row->ipk,
                'sks_semester' => (int) ($row->sks_semester ?? 0),
                'total_sks' => (int) ($row->sks_program ?? 0),
                'biaya_kuliah_smt' => 1000000,
                'id_pembiayaan' => 1,
            ],
            'MahasiswaLulusDO' => [
                'id_registrasi_mahasiswa' => trim((string) $row->id_registrasi_mahasiswa),
                'id_jenis_keluar' => trim((string) $row->id_status_mahasiswa),
                'tanggal_keluar' => trim((string) $row->tgl_keluar),
                'id_periode_keluar' => $state['semester_id'],
                'keterangan' => trim((string) $row->ket),
                'nomor_sk_yudisium' => trim((string) $row->no_sk),
                'tanggal_sk_yudisium' => trim((string) $row->tgl_sk),
                'ipk' => trim((string) $row->ipk),
                'nomor_ijazah' => trim((string) ($row->no_ijazah ?? '')),
                'jalur_skripsi' => (string) $row->id_status_mahasiswa === '1' ? '1' : '0',
                'judul_skripsi' => '',
                'bulan_awal_bimbingan' => '',
                'bulan_akhir_bimbingan' => '',
            ],
            default => throw new RuntimeException('Jenis data ekspor tidak didukung.'),
        };
    }

    private function exportCurriculumCourse(
        object $row,
        array $state,
        LegacyFeederService $feeder,
        ?object $previous,
    ): array {
        $record = $this->fingerprintPayload('MatkulKurikulum', $row, $state);
        if ($previous !== null && $previous->payload_hash !== null) {
            $oldPayload = json_decode((string) $previous->payload_json, true);
            if (is_array($oldPayload) && trim((string) ($oldPayload['id_matkul'] ?? '')) !== '') {
                $deleted = $feeder->exportAction('DeleteMatkulKurikulum', [
                    'key' => [
                        'id_kurikulum' => $this->curriculumId($state),
                        'id_matkul' => $oldPayload['id_matkul'],
                    ],
                ]);
                $this->assertFeederDeleteSuccess($deleted);
            }
        }

        $response = $feeder->exportAction('InsertMatkulKurikulum', ['record' => $record]);
        if ($this->isDuplicateInsertResponse('MatkulKurikulum', $response)) {
            $deleted = $feeder->exportAction('DeleteMatkulKurikulum', [
                'key' => [
                    'id_kurikulum' => $this->curriculumId($state),
                    'id_matkul' => $record['id_matkul'],
                ],
            ]);
            $this->assertFeederDeleteSuccess($deleted);

            $replacement = $feeder->exportAction('InsertMatkulKurikulum', ['record' => $record]);
            if ($this->isDuplicateInsertResponse('MatkulKurikulum', $replacement)) {
                return [
                    'error_code' => 'DUPLICATE_AFTER_REPLACE',
                    'error_desc' => 'Relasi mata kuliah masih terdeteksi setelah dihapus. Data belum dapat diverifikasi ulang.',
                ];
            }

            return $replacement;
        }

        return $response;
    }

    private function exportParticipant(
        object $row,
        array $state,
        LegacyFeederService $feeder,
        ?object $previous,
    ): array {
        $record = $this->fingerprintPayload('PesertaKelasKuliah', $row, $state);
        if ($previous !== null && $previous->payload_hash !== null) {
            $oldPayload = json_decode((string) $previous->payload_json, true);
            if (is_array($oldPayload)
                && trim((string) ($oldPayload['id_kelas_kuliah'] ?? '')) !== ''
                && trim((string) ($oldPayload['id_registrasi_mahasiswa'] ?? '')) !== '') {
                $deleted = $feeder->exportAction('DeletePesertaKelasKuliah', [
                    'key' => [
                        'id_kelas_kuliah' => $oldPayload['id_kelas_kuliah'],
                        'id_registrasi_mahasiswa' => $oldPayload['id_registrasi_mahasiswa'],
                    ],
                ]);
                $this->assertFeederDeleteSuccess($deleted);
            }
        }

        $response = $feeder->exportAction('InsertPesertaKelasKuliah', ['record' => $record]);
        if ($this->isDuplicateInsertResponse('PesertaKelasKuliah', $response)) {
            $deleted = $feeder->exportAction('DeletePesertaKelasKuliah', ['key' => $record]);
            $this->assertFeederDeleteSuccess($deleted);

            return $feeder->exportAction('InsertPesertaKelasKuliah', ['record' => $record]);
        }

        return $response;
    }

    private function exportStudentActivity(
        object $row,
        array $state,
        LegacyFeederService $feeder,
        ?object $previous,
    ): array {
        $record = $this->fingerprintPayload('PerkuliahanMahasiswa', $row, $state);
        $action = $previous?->payload_hash !== null
            ? 'UpdatePerkuliahanMahasiswa'
            : 'InsertPerkuliahanMahasiswa';
        $response = $feeder->exportAction($action, $action === 'UpdatePerkuliahanMahasiswa'
            ? [
                'key' => [
                    'id_registrasi_mahasiswa' => $record['id_registrasi_mahasiswa'],
                    'id_semester' => $record['id_semester'],
                ],
                'record' => $record,
            ]
            : ['record' => $record]);
        if ($action === 'InsertPerkuliahanMahasiswa' && $this->isDuplicateInsertResponse('PerkuliahanMahasiswa', $response)) {
            return $feeder->exportAction('UpdatePerkuliahanMahasiswa', [
                'key' => [
                    'id_registrasi_mahasiswa' => $record['id_registrasi_mahasiswa'],
                    'id_semester' => $record['id_semester'],
                ],
                'record' => $record,
            ]);
        }

        return $response;
    }

    private function exportStudentExit(
        object $row,
        array $state,
        LegacyFeederService $feeder,
        ?object $previous,
    ): array {
        $record = $this->fingerprintPayload('MahasiswaLulusDO', $row, $state);
        $action = $previous?->payload_hash !== null
            ? 'UpdateMahasiswaLulusDO'
            : 'InsertMahasiswaLulusDO';
        $parameters = $action === 'UpdateMahasiswaLulusDO'
            ? [
                'key' => ['id_registrasi_mahasiswa' => $record['id_registrasi_mahasiswa']],
                'record' => $record,
            ]
            : ['record' => $record];
        $response = $feeder->exportAction($action, $parameters);
        if ($action === 'InsertMahasiswaLulusDO' && $this->isDuplicateInsertResponse('MahasiswaLulusDO', $response)) {
            return $feeder->exportAction('UpdateMahasiswaLulusDO', [
                'key' => ['id_registrasi_mahasiswa' => $record['id_registrasi_mahasiswa']],
                'record' => $record,
            ]);
        }

        return $response;
    }

    private function exportClass(object $row, array $state, LegacyFeederService $feeder): array
    {
        $payload = $this->fingerprintPayload('KelasKuliah', $row, $state);
        if (trim((string) $row->id_kelas_kuliah) !== '') {
            return $feeder->exportAction('UpdateKelasKuliah', [
                'key' => ['id_kelas_kuliah' => $row->id_kelas_kuliah],
                'record' => $this->classUpdateRecord($payload),
            ]);
        }

        $response = $feeder->exportAction('InsertKelasKuliah', [
            'record' => $this->classInsertRecord($payload, $row->kampus_merdeka),
        ]);
        if ((string) ($response['error_code'] ?? '') === '0') {
            $feederClassId = trim((string) ($response['data']['id_kelas_kuliah'] ?? ''));
            if ($feederClassId === '') {
                return [
                    'error_code' => 'SIMAK',
                    'error_desc' => 'Feeder menyatakan berhasil tetapi tidak mengembalikan ID kelas kuliah.',
                ];
            }
            DB::connection('simak_main')->table('s1m4k_jadwal')
                ->where('id_jadwal', $row->id_jadwal)
                ->where('id_prodi', $state['program_id'])
                ->update(['id_kelas_kuliah' => $feederClassId]);

            return $feeder->exportAction('UpdateKelasKuliah', [
                'key' => ['id_kelas_kuliah' => $feederClassId],
                'record' => $this->classUpdateRecord($payload),
            ]);
        } elseif ($this->isDuplicateInsertResponse('KelasKuliah', $response)) {
            $feederClassId = $this->existingClassId($row, $state, $feeder);
            if ($feederClassId === '') {
                return [
                    ...$response,
                    'error_desc' => trim((string) ($response['error_desc'] ?? 'Kelas sudah ada di Feeder.'))
                        .' ID kelas yang cocok tidak ditemukan; data SIMAK belum diperbarui.',
                ];
            }
            DB::connection('simak_main')->table('s1m4k_jadwal')
                ->where('id_jadwal', $row->id_jadwal)
                ->where('id_prodi', $state['program_id'])
                ->update(['id_kelas_kuliah' => $feederClassId]);

            return $feeder->exportAction('UpdateKelasKuliah', [
                'key' => ['id_kelas_kuliah' => $feederClassId],
                'record' => $this->classUpdateRecord($payload),
            ]);
        }

        return $response;
    }

    private function classInsertRecord(array $payload, mixed $pditt): array
    {
        return [
            ...array_intersect_key($payload, array_flip([
                'id_prodi',
                'id_semester',
                'id_matkul',
                'nama_kelas_kuliah',
                'bahasan',
                'tanggal_mulai_efektif',
                'tanggal_akhir_efektif',
                'kapasitas',
                'lingkup',
                'mode',
            ])),
            'apa_untuk_pditt' => $pditt,
        ];
    }

    private function classUpdateRecord(array $payload): array
    {
        return array_intersect_key($payload, array_flip([
            'id_prodi',
            'id_semester',
            'id_matkul',
            'nama_kelas_kuliah',
            'bahasan',
            'tanggal_mulai_efektif',
            'tanggal_akhir_efektif',
            'sks_mata_kuliah',
            'sks_tatap_muka',
            'sks_praktek',
            'sks_praktek_lapangan',
            'sks_simulasi',
            'kapasitas',
            'lingkup',
            'mode',
        ]));
    }

    private function existingClassId(object $row, array $state, LegacyFeederService $feeder): string
    {
        $className = mb_substr((string) $row->nama_kelas, 0, 5);
        $response = $feeder->exportAction('GetListKelasKuliah', [
            'filter' => "id_prodi='".$this->escapeFilter($state['program_id'])
                ."' AND id_semester='".$this->escapeFilter($state['semester_id'])
                ."' AND id_matkul='".$this->escapeFilter((string) $row->id_matkul)
                ."' AND nama_kelas_kuliah='".$this->escapeFilter($className)."'",
            'order' => '',
            'limit' => 100,
            'offset' => 0,
        ]);
        if ((string) ($response['error_code'] ?? '') !== '0') {
            return '';
        }

        $rows = $response['data'] ?? [];
        $rows = is_array($rows) && array_is_list($rows) ? $rows : [$rows];
        foreach ($rows as $class) {
            if (! is_array($class)
                || trim((string) ($class['id_prodi'] ?? '')) !== $state['program_id']
                || trim((string) ($class['id_semester'] ?? '')) !== $state['semester_id']
                || trim((string) ($class['id_matkul'] ?? '')) !== (string) $row->id_matkul
                || trim((string) ($class['nama_kelas_kuliah'] ?? '')) !== $className) {
                continue;
            }

            $classId = trim((string) ($class['id_kelas_kuliah'] ?? ''));
            if ($classId !== '') {
                return $classId;
            }
        }

        return '';
    }

    private function exportLecturer(
        object $row,
        array $state,
        LegacyFeederService $feeder,
        ?object $previous,
    ): array {
        $lecturerId = trim((string) $row->id_dosen);
        if ($lecturerId === '') {
            return [
                'error_code' => 'DATA_SIMAK_TIDAK_VALID',
                'error_desc' => 'ID dosen pada data pengampu SIMAK kosong.',
            ];
        }

        $assignments = $feeder->exportAction('GetListPenugasanDosen', [
            'filter' => "id_dosen='".$this->escapeFilter($lecturerId)."'",
            'order' => 'id_tahun_ajaran DESC',
            'limit' => 100,
            'offset' => 0,
        ]);
        if ((string) ($assignments['error_code'] ?? '') !== '0') {
            return $assignments;
        }

        $rows = $assignments['data'] ?? [];
        $rows = is_array($rows) && array_is_list($rows) ? $rows : [$rows];
        $registrationId = '';
        foreach ($rows as $assignment) {
            if (! is_array($assignment)) {
                continue;
            }
            if (trim((string) ($assignment['id_dosen'] ?? '')) !== $lecturerId) {
                continue;
            }

            $candidateId = trim((string) ($assignment['id_registrasi_dosen'] ?? ''));
            if ($candidateId !== '') {
                $registrationId = $candidateId;
                break;
            }
        }
        if ($registrationId === '') {
            return [
                'error_code' => 'DATA_TIDAK_DITEMUKAN',
                'error_desc' => 'ID registrasi dosen untuk ID dosen '.$lecturerId.' tidak ditemukan pada penugasan yang dikembalikan Feeder.',
            ];
        }

        $lecturerCount = DB::connection('simak_main')->table('s1m4k_akd')
            ->where('id_jadwal', $row->id_jadwal)
            ->where('id_kalender', $state['calendar_id'])
            ->where('id_prodi', $state['program_id'])
            ->where('ket', 'dosen')
            ->count();
        if ($lecturerCount < 1) {
            return [
                'error_code' => 'DATA_SIMAK_TIDAK_VALID',
                'error_desc' => 'Jumlah dosen pengampu pada SIMAK tidak valid.',
            ];
        }

        $record = [
            'id_registrasi_dosen' => $registrationId,
            'id_kelas_kuliah' => $row->id_kelas_kuliah,
            'id_substansi' => '',
            'sks_substansi_total' => $this->formatNumber((float) ($row->sks_mata_kuliah ?? 0) / $lecturerCount, 2),
            'rencana_minggu_pertemuan' => empty($row->rencana) ? 16 : $row->rencana,
            'realisasi_minggu_pertemuan' => empty($row->realisasi) ? 0 : $row->realisasi,
            'id_jenis_evaluasi' => '1',
        ];
        $activityId = '';
        if ($previous?->payload_hash !== null) {
            $activityId = $this->existingLecturerActivityId($record, $feeder);
        }
        if ($activityId !== '') {
            return $feeder->exportAction('UpdateDosenPengajarKelasKuliah', [
                'key' => ['id_aktivitas_mengajar' => $activityId],
                'record' => $record,
            ]);
        }

        $response = $feeder->exportAction('InsertDosenPengajarKelasKuliah', ['record' => $record]);
        if ($this->isDuplicateInsertResponse('DosenPengajarKelasKuliah', $response)) {
            $activityId = $this->existingLecturerActivityId($record, $feeder);
            if ($activityId !== '') {
                return $feeder->exportAction('UpdateDosenPengajarKelasKuliah', [
                    'key' => ['id_aktivitas_mengajar' => $activityId],
                    'record' => $record,
                ]);
            }

            return [
                'error_code' => 'DATA_FEEDER_TIDAK_DITEMUKAN',
                'error_desc' => 'Data dosen pengajar sudah ada, tetapi ID aktivitas mengajar tidak ditemukan untuk pembaruan.',
            ];
        }

        return $response;
    }

    private function existingLecturerActivityId(array $record, LegacyFeederService $feeder): string
    {
        $response = $feeder->exportAction('GetDosenPengajarKelasKuliah', [
            'filter' => "id_registrasi_dosen='".$this->escapeFilter((string) $record['id_registrasi_dosen'])
                ."' AND id_kelas_kuliah='".$this->escapeFilter((string) $record['id_kelas_kuliah'])."'",
            'order' => '',
            'limit' => 100,
            'offset' => 0,
        ]);
        if ((string) ($response['error_code'] ?? '') !== '0') {
            return '';
        }

        $rows = $response['data'] ?? [];
        $rows = is_array($rows) && array_is_list($rows) ? $rows : [$rows];
        foreach ($rows as $activity) {
            if (! is_array($activity)
                || trim((string) ($activity['id_registrasi_dosen'] ?? '')) !== (string) $record['id_registrasi_dosen']
                || trim((string) ($activity['id_kelas_kuliah'] ?? '')) !== (string) $record['id_kelas_kuliah']) {
                continue;
            }
            $activityId = trim((string) ($activity['id_aktivitas_mengajar'] ?? ''));
            if ($activityId !== '') {
                return $activityId;
            }
        }

        return '';
    }

    private function isAlreadyExported(string $type, array $response): bool
    {
        return $this->isDuplicateInsertResponse($type, $response)
            && $type !== 'KelasKuliah';
    }

    private function isDuplicateInsertResponse(string $type, array $response): bool
    {
        $insertTypes = [
            'Kurikulum',
            'MatkulKurikulum',
            'KelasKuliah',
            'PesertaKelasKuliah',
            'DosenPengajarKelasKuliah',
            'PerkuliahanMahasiswa',
            'MahasiswaLulusDO',
        ];
        if (! in_array($type, $insertTypes, true) || (string) ($response['error_code'] ?? '') === '0') {
            return false;
        }

        $description = mb_strtolower(trim((string) ($response['error_desc'] ?? '')));
        $isDuplicate = str_contains($description, 'sudah ada')
            || str_contains($description, 'telah ada')
            || str_contains($description, 'sudah terdaftar')
            || str_contains($description, 'telah terdaftar')
            || str_contains($description, 'sudah tercatat')
            || str_contains($description, 'telah tercatat')
            || str_contains($description, 'already exists')
            || str_contains($description, 'already registered')
            || str_contains($description, 'duplicate');

        return $isDuplicate;
    }

    private function applySuccess(string $type, object $row, array $state): void
    {
        if ($type === 'PerkuliahanMahasiswa') {
            DB::connection('simak_main')->table('s1m4k_akm')
                ->where('id_ipmhs', $row->id_ipmhs)
                ->where('id_kalender', $state['calendar_id'])
                ->where('id_prodi', $state['program_id'])
                ->update(['flag_feeder' => 1]);
        } elseif ($type === 'MahasiswaLulusDO') {
            DB::connection('simak_main')->table('s1m4k_mastermhs')
                ->where('id_mahasiswa', $row->id_mahasiswa)
                ->where('id_prodi', $state['program_id'])
                ->update(['flag_feeder' => 1]);
        }
    }

    private function finish(array &$state): void
    {
        $duration = max(0, now()->timestamp - (int) $state['started_at']);
        $connection = DB::connection('simak_main');
        $detailStatuses = $connection->table('s1m4k_export_detail')
            ->where('id_export', $state['export_id'])
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $failedCount = (int) ($detailStatuses['N'] ?? 0);
        $successCount = (int) ($detailStatuses['Y'] ?? 0);
        $status = $failedCount === 0 ? 'Y' : ($successCount > 0 ? 'P' : 'N');
        $state['export_status'] = $status;
        $connection->table('s1m4k_export')
            ->where('id_export', $state['export_id'])
            ->where('id_prodi', $state['program_id'])
            ->update([
                'status' => $status,
                'waktu' => gmdate('H:i:s', $duration),
            ]);

        if ($state['type'] === 'MatkulKurikulum' && $state['failures'] === []) {
            $summary = $this->calendarCourseSummary($state['program_id'], $state['calendar_id']);
            $connection->table('s1m4k_kurikulum')
                ->where('id_kurikulum', $this->curriculumId($state))
                ->where('id_prodi', $state['program_id'])
                ->update([
                    'jumlah_sks_mata_kuliah_wajib' => (string) $summary['required'],
                    'jumlah_sks_mata_kuliah_pilihan' => (string) $summary['elective'],
                ]);
        }

        Cache::put($this->cacheKey($state['id']), $state, now()->addMinutes(self::JOB_TTL_MINUTES));
    }

    private function curriculumId(array $state): string
    {
        return (string) DB::connection('simak_main')->table('s1m4k_export')
            ->where('id_export', $state['export_id'])
            ->where('id_prodi', $state['program_id'])
            ->value('id_kurikulum');
    }

    private function assertFeederSuccess(array $response): void
    {
        if ((string) ($response['error_code'] ?? '') !== '0') {
            $code = trim((string) ($response['error_code'] ?? 'TIDAK_DIKETAHUI'));
            $description = trim((string) ($response['error_desc'] ?? 'Feeder tidak menjelaskan penyebab kegagalan.'));
            throw new RuntimeException("Feeder gagal ({$code}): {$description}");
        }
    }

    private function assertFeederDeleteSuccess(array $response): void
    {
        if ((string) ($response['error_code'] ?? '') === '0') {
            return;
        }

        $description = mb_strtolower(trim((string) ($response['error_desc'] ?? '')));
        if (str_contains($description, 'tidak ditemukan')
            || str_contains($description, 'tidak ada')
            || str_contains($description, 'not found')
            || str_contains($description, 'no data')) {
            return;
        }

        $this->assertFeederSuccess($response);
    }

    private function ownedState(string $jobId, object $user): array
    {
        $state = Cache::get($this->cacheKey($jobId));
        abort_unless(is_array($state), 404, 'Sesi ekspor sudah kedaluwarsa atau tidak ditemukan.');
        abort_unless(
            $state['owner_id'] === (string) $user->legacy_id
                && $state['owner_role'] === (string) $user->role
                && ($user->role === 'admin' || $state['program_id'] === (string) $user->id_prodi),
            403,
        );

        return $state;
    }

    private function publicState(array $state, bool $includeFailures = true): array
    {
        $processed = min((int) $state['cursor'], (int) $state['total']);
        $detailStatuses = DB::connection('simak_main')->table('s1m4k_export_detail')
            ->where('id_export', $state['export_id'])
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'job_id' => $state['id'],
            'export_id' => $state['export_id'],
            'type' => $state['type'],
            'label' => self::TYPES[$state['type']],
            'export_time' => DB::connection('simak_main')->table('s1m4k_export')
                ->where('id_export', $state['export_id'])->value('waktu'),
            'total' => $state['total'],
            'processed' => $processed,
            'success_count' => $state['success_count'],
            'failure_count' => count($state['failures']),
            'skipped_count' => $state['skipped_count'] ?? 0,
            'stored_success_count' => (int) ($detailStatuses['Y'] ?? 0),
            'stored_failure_count' => (int) ($detailStatuses['N'] ?? 0),
            'export_status' => $state['export_status'] ?? null,
            'progress' => $state['total'] === 0 ? 100 : (int) floor(($processed / $state['total']) * 100),
            'complete' => $state['complete'],
            'failures' => $includeFailures ? $state['failures'] : [],
        ];
    }

    private function failure(string $sourceId, string $identity, string $code, string $description): array
    {
        return [
            'source_id' => $sourceId,
            'identity' => $identity,
            'error_code' => $code,
            'error_desc' => $description,
        ];
    }

    private function identity(string $type, object $row): string
    {
        $values = match ($type) {
            'MatkulKurikulum' => [$row->id_matkul ?? null],
            'KelasKuliah' => [$row->nama_mata_kuliah ?? null, 'kelas '.($row->nama_kelas ?? '')],
            'DosenPengajarKelasKuliah' => [$row->nidn ?? null, $row->nama_dosen ?? null],
            'PesertaKelasKuliah', 'NilaiPerkuliahanKelas', 'PerkuliahanMahasiswa', 'MahasiswaLulusDO' => [
                $row->nim ?? null,
                $row->nama_mahasiswa ?? null,
            ],
            default => [],
        };

        return implode(' - ', array_filter(array_map(static fn ($value): string => trim((string) $value), $values)));
    }

    private function formatNumber(mixed $value, int $decimals): string
    {
        return number_format((float) $value, $decimals, '.', '');
    }

    private function feederDate(mixed $date): string
    {
        $date = trim((string) $date);

        return $date === '' || $date === '0000-00-00' ? '' : $date;
    }

    private function escapeFilter(string $value): string
    {
        return str_replace("'", "''", $value);
    }

    private function cacheKey(string $jobId): string
    {
        return 'feeder-export-job:'.$jobId;
    }

    private function ensureDetailTable(): void
    {
        if (! Schema::connection('simak_main')->hasTable('s1m4k_export_detail')) {
            throw new RuntimeException(
                'Tabel s1m4k_export_detail belum tersedia. Jalankan database/manual/s1m4k_export_detail.sql pada database SIMAK sebelum menggunakan ekspor.',
            );
        }
    }

    private function saveDetail(
        array $state,
        string $sourceId,
        string $payloadHash,
        array $payload,
        ?array $failure,
        bool $countAttempt = true,
    ): void {
        $connection = DB::connection('simak_main');
        $query = $connection->table('s1m4k_export_detail')
            ->where('id_export', $state['export_id'])
            ->where('source_id', $sourceId);
        $existing = $query->first();
        $now = now();
        $values = [
            'status' => $failure === null ? 'Y' : 'N',
            'error_code' => $failure['error_code'] ?? null,
            'error_desc' => $failure['error_desc'] ?? null,
            'attempts' => (int) ($existing->attempts ?? 0) + (int) $countAttempt,
            'last_attempt_at' => $countAttempt ? $now : ($existing->last_attempt_at ?? null),
            'updated_at' => $now,
        ];
        if ($failure === null) {
            $values['payload_hash'] = $payloadHash;
            $values['payload_json'] = json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            );
            $values['succeeded_at'] = $now;
        }

        if ($existing === null) {
            $connection->table('s1m4k_export_detail')->insert([
                'id_export_detail' => (string) Str::uuid(),
                'id_export' => $state['export_id'],
                'source_id' => $sourceId,
                'payload_hash' => null,
                'payload_json' => null,
                'succeeded_at' => null,
                'created_at' => $now,
                ...$values,
            ]);

            return;
        }

        $query->update($values);
    }
}
