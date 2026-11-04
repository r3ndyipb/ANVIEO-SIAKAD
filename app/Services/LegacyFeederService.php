<?php

namespace App\Services;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class LegacyFeederService
{
    private const KEY = '979a218e0632df2935317f98d47956c7';

    private const EXPORT_REQUEST_TIMEOUT_SECONDS = 5;

    private const EXPORT_TOKEN_TTL_MINUTES = 4;

    private const STUDENT_ACTIVITY_ACTIONS = [
        'InsertAktivitasMahasiswa', 'UpdateAktivitasMahasiswa', 'DeleteAktivitasMahasiswa',
        'InsertAnggotaAktivitasMahasiswa', 'DeleteAnggotaAktivitasMahasiswa',
        'InsertBimbingMahasiswa', 'DeleteBimbingMahasiswa',
        'InsertUjiMahasiswa', 'DeleteUjiMahasiswa',
    ];

    public function studentActivityAction(string $action, array $parameters): array
    {
        if (! in_array($action, self::STUDENT_ACTIVITY_ACTIONS, true)) {
            throw new RuntimeException('Tindakan aktivitas mahasiswa tidak diizinkan.');
        }

        return $this->request($action, $parameters);
    }

    /**
     * @return array{url: string, username: string, password: string}|null
     */
    public function storedInitializationCredentials(): ?array
    {
        $schema = Schema::connection('simak_main');
        if (! $schema->hasTable('s1m4k_masterpt')) {
            return null;
        }

        foreach (['alamat_feeder', 'user_feeder', 'password_feeder'] as $column) {
            if (! $schema->hasColumn('s1m4k_masterpt', $column)) {
                return null;
            }
        }

        $settings = DB::connection('simak_main')->table('s1m4k_masterpt')->first([
            'alamat_feeder', 'user_feeder', 'password_feeder',
        ]);
        if ($settings === null
            || trim((string) $settings->alamat_feeder) === ''
            || trim((string) $settings->user_feeder) === ''
            || trim((string) $settings->password_feeder) === '') {
            return null;
        }

        return [
            'url' => trim((string) $settings->alamat_feeder),
            'username' => trim((string) $settings->user_feeder),
            'password' => $this->decrypt((string) $settings->password_feeder),
        ];
    }

    public function synchronizeStudentExitStatus(
        string $action,
        string $studentId,
        array $record,
        bool $administrator
    ): void {
        if (! in_array($action, ['InsertMahasiswaLulusDO', 'UpdateMahasiswaLulusDO'], true)) {
            throw new RuntimeException('Tindakan status keluar mahasiswa tidak diizinkan.');
        }

        $lookupAction = $action === 'UpdateMahasiswaLulusDO' && $administrator
            ? 'GetListRiwayatPendidikanMahasiswa'
            : 'GetListMahasiswa';
        [$url, $token] = $this->connectionToken();
        $studentResponse = $this->post($url, [
            'act' => $lookupAction,
            'token' => $token,
            'filter' => "id_mahasiswa='".$this->filterValue($studentId)."'",
            'order' => '',
            'limit' => '',
            'offset' => 0,
        ]);
        $students = $studentResponse['data'] ?? [];
        $student = is_array($students) && array_is_list($students)
            ? ($students[0] ?? null)
            : $students;
        $registrationId = is_array($student)
            ? trim((string) ($student['id_registrasi_mahasiswa'] ?? ''))
            : '';
        if ($registrationId === '') {
            throw new RuntimeException('Feeder tidak menemukan ID registrasi pendidikan mahasiswa.');
        }

        $record['id_registrasi_mahasiswa'] = $registrationId;
        $payload = [
            'act' => $action,
            'token' => $token,
            'record' => $record,
        ];
        if ($action === 'UpdateMahasiswaLulusDO') {
            $payload['key'] = $registrationId;
        }

        $response = $this->post($url, $payload);
        $returnedRegistrationId = trim((string) ($response['data']['id_registrasi_mahasiswa'] ?? ''));
        if ($returnedRegistrationId === '') {
            throw new RuntimeException('Feeder tidak mengembalikan ID registrasi mahasiswa setelah menyimpan status keluar.');
        }
    }

    public function refreshUniversityProfile(): int
    {
        $data = $this->request('GetProfilPT', [
            'filter' => '',
            'order' => '',
            'limit' => 1,
            'offset' => 0,
        ]);
        $profile = $data[0] ?? null;
        if (! is_array($profile) || trim((string) ($profile['id_perguruan_tinggi'] ?? '')) === '') {
            throw new RuntimeException('Feeder tidak mengembalikan data profil perguruan tinggi.');
        }

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

        $connection = DB::connection('simak_main');
        $id = trim((string) $profile['id_perguruan_tinggi']);
        $existingId = $connection->table('s1m4k_masterpt')
            ->where('id_perguruan_tinggi', $id)
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
                'id_perguruan_tinggi' => $id,
                ...$values,
            ]);
        }

        return 1;
    }

    public function syncPrograms(): int
    {
        $programs = $this->request('GetProdi', [
            'filter' => '',
            'order' => '',
            'limit' => 0,
            'offset' => 0,
        ]);
        $universityId = DB::connection('simak_main')->table('s1m4k_masterpt')
            ->value('id_perguruan_tinggi');
        if (! is_string($universityId) || trim($universityId) === '') {
            throw new RuntimeException('Data perguruan tinggi belum tersedia di SIMAK.');
        }

        $connection = DB::connection('simak_main');

        return $connection->transaction(function () use ($connection, $programs, $universityId): int {
            $synced = 0;
            foreach ($programs as $program) {
                if (! is_array($program) || trim((string) ($program['id_prodi'] ?? '')) === '') {
                    continue;
                }

                $id = trim((string) $program['id_prodi']);
                $values = [
                    'id_perguruan_tinggi' => $universityId,
                    'kode_program_studi' => $program['kode_program_studi'] ?? '',
                    'nama_program_studi' => $program['nama_program_studi'] ?? '',
                    'status' => $program['status'] ?? '',
                    'id_jenjang_pendidikan' => $program['id_jenjang_pendidikan'] ?? '',
                    'nama_jenjang_pendidikan' => $program['nama_jenjang_pendidikan'] ?? '',
                ];
                $exists = $connection->table('s1m4k_masterprodi')
                    ->where('id_prodi', $id)->exists();
                if ($exists) {
                    $connection->table('s1m4k_masterprodi')
                        ->where('id_prodi', $id)->update($values);
                } else {
                    $connection->table('s1m4k_masterprodi')->insert([
                        'id_prodi' => $id,
                        'k1' => 0,
                        'k2' => 0,
                        'k3' => 0,
                        'k4' => 0,
                        'k5' => 0,
                        'k6' => 0,
                        'k7' => 0,
                        'k8' => 0,
                        ...$values,
                    ]);
                }
                $synced++;
            }

            return $synced;
        });
    }

    public function updateCourse(string $courseId, array $record): void
    {
        $this->request('UpdateMataKuliah', [
            'key' => ['id_matkul' => $courseId],
            'record' => $record,
        ]);
    }

    public function courseEvaluations(string $courseId): array
    {
        return $this->request('GetListRencanaEvaluasi', [
            'filter' => "id_matkul='".$this->filterValue($courseId)."'",
            'order' => '',
            'limit' => '',
            'offset' => 0,
        ]);
    }

    public function studentAcademicStatuses(string $semesterId, array $students): array
    {
        [$url, $token] = $this->connectionToken(8);
        $statuses = [];

        foreach ($students as $student) {
            $nim = trim((string) ($student['nim'] ?? ''));
            if ($nim === '') {
                continue;
            }
            $response = $this->post($url, [
                'act' => 'GetAktivitasKuliahMahasiswa',
                'token' => $token,
                'filter' => "id_semester='".$this->filterValue($semesterId)."' AND nim='".$this->filterValue($nim)."'",
                'order' => '',
                'limit' => 1,
                'offset' => 0,
            ], timeout: 8);
            $records = $response['data'] ?? [];
            $record = is_array($records) && array_is_list($records) ? ($records[0] ?? null) : $records;
            $statuses[$nim] = is_array($record)
                ? trim((string) ($record['nama_status_mahasiswa'] ?? '')) ?: null
                : null;
        }

        return $statuses;
    }

    public function verifyProspectiveStudent(array $biodata, array $academicHistory): array
    {
        [$url, $token] = $this->connectionToken();
        $biodataResponse = $this->post($url, [
            'act' => 'InsertBiodataMahasiswa',
            'token' => $token,
            'record' => $biodata,
        ]);
        $studentId = trim((string) ($biodataResponse['data']['id_mahasiswa'] ?? ''));
        if ($studentId === '') {
            throw new RuntimeException('Feeder tidak mengembalikan ID mahasiswa setelah menyimpan biodata.');
        }

        $academicHistory['id_mahasiswa'] = $studentId;
        $historyResponse = $this->post($url, [
            'act' => 'InsertRiwayatPendidikanMahasiswa',
            'token' => $token,
            'record' => $academicHistory,
        ]);
        $registrationId = trim((string) ($historyResponse['data']['id_registrasi_mahasiswa'] ?? ''));
        if ($registrationId === '') {
            throw new RuntimeException('Feeder tidak mengembalikan ID registrasi mahasiswa setelah menyimpan riwayat pendidikan.');
        }

        return [
            'id_mahasiswa' => $studentId,
            'id_registrasi_mahasiswa' => $registrationId,
        ];
    }

    public function deleteCourseEvaluation(string $evaluationId): void
    {
        $this->request('DeleteRencanaEvaluasi', [
            'key' => ['id_rencana_evaluasi' => $evaluationId],
        ]);
    }

    public function syncCourse(string $courseCode, string $programId): int
    {
        $connection = DB::connection('simak_main');
        $program = $connection->table('s1m4k_masterprodi')
            ->where('id_prodi', $programId)
            ->first(['id_prodi', 'id_fakultas', 'id_perguruan_tinggi']);
        if ($program === null) {
            throw new RuntimeException('Program studi tidak ditemukan di SIMAK.');
        }

        $courses = $this->request('GetDetailMataKuliah', [
            'filter' => "id_prodi='".$this->filterValue($programId)."' AND kode_mata_kuliah='".$this->filterValue($courseCode)."'",
            'order' => '',
            'limit' => '',
            'offset' => 0,
        ]);
        $course = collect($courses)->first(fn ($row) => is_array($row)
            && trim((string) ($row['kode_mata_kuliah'] ?? '')) === $courseCode
            && trim((string) ($row['id_prodi'] ?? '')) === $programId);
        if (! is_array($course)) {
            throw new RuntimeException('Mata kuliah dengan kode tersebut tidak ditemukan pada program studi terpilih di Feeder.');
        }

        $id = trim((string) ($course['id_matkul'] ?? ''));
        if ($id === '') {
            throw new RuntimeException('Respons Feeder tidak memuat ID mata kuliah.');
        }
        $values = [
            'id_perguruan_tinggi' => $program->id_perguruan_tinggi,
            'id_fakultas' => $program->id_fakultas,
            'id_prodi' => $programId,
            'kode_mata_kuliah' => $course['kode_mata_kuliah'] ?? '',
            'nama_mata_kuliah' => $course['nama_mata_kuliah'] ?? '',
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
            'flag_feeder' => 1,
            'tanggal_mulai_efektif' => $this->legacyDate($course['tanggal_mulai_efektif'] ?? null),
            'tanggal_selesai_efektif' => $this->legacyDate($course['tanggal_selesai_efektif'] ?? null),
        ];
        $existing = $connection->table('s1m4k_mastermk')->where('id_matkul', $id)->exists();
        if ($existing) {
            $connection->table('s1m4k_mastermk')->where('id_matkul', $id)->update($values);
        } else {
            $connection->table('s1m4k_mastermk')->insert(['id_matkul' => $id, 'status' => 'A', ...$values]);
        }

        return 1;
    }

    public function updateStudentBiodata(string $studentId, array $record): void
    {
        $feederRecord = [
            'kewarganegaraan' => $record['id_negara'] ?? $record['kewarganegaraan'] ?? '',
            ...array_diff_key($record, ['id_negara' => true]),
        ];
        if (! isset($record['nama_mahasiswa'])) {
            unset($feederRecord['nama_mahasiswa']);
        }

        $this->request('UpdateBiodataMahasiswa', [
            'key' => ['id_mahasiswa' => $studentId],
            'record' => $feederRecord,
        ]);
    }

    public function updateStudentAcademicHistory(
        string $studentId,
        string $registrationId,
        string $previousProgramId,
        array $record
    ): array {
        $programChangedWithOrigin = $previousProgramId !== (string) ($record['id_prodi'] ?? '')
            && trim((string) ($record['id_prodi_asal'] ?? '')) !== '';
        $history = [
            'id_mahasiswa' => $studentId,
            ...$record,
        ];

        if ($programChangedWithOrigin) {
            $response = $this->request('InsertRiwayatPendidikanMahasiswa', [
                'record' => $history,
            ]);
            $newRegistrationId = trim((string) ($response[0]['id_registrasi_mahasiswa'] ?? ''));
            if ($newRegistrationId === '') {
                throw new RuntimeException('Feeder tidak mengembalikan ID registrasi mahasiswa yang baru.');
            }

            return [
                'id_registrasi_mahasiswa' => $newRegistrationId,
                'new_registration' => true,
            ];
        }

        if (trim($registrationId) === '') {
            throw new RuntimeException('ID registrasi pendidikan mahasiswa tidak tersedia untuk diperbarui.');
        }

        $this->request('UpdateRiwayatPendidikanMahasiswa', [
            'key' => ['id_registrasi_mahasiswa' => $registrationId],
            'record' => $history,
        ]);

        return [
            'id_registrasi_mahasiswa' => $registrationId,
            'new_registration' => false,
        ];
    }

    public function syncAllLecturers(): array
    {
        [$url, $token] = $this->connectionToken();
        $lecturers = $this->requestWithToken($url, $token, 'GetListDosen', [
            'filter' => '',
            'order' => '',
            'limit' => 0,
            'offset' => 0,
        ]);
        if ($lecturers === []) {
            throw new RuntimeException('Feeder tidak mengembalikan data dosen; sinkronisasi dibatalkan.');
        }
        $assignments = $this->requestWithToken($url, $token, 'GetListPenugasanDosen', [
            'filter' => '',
            'order' => 'id_tahun_ajaran DESC',
            'limit' => 0,
            'offset' => 0,
        ]);

        $connection = DB::connection('simak_main');
        $programIds = collect($assignments)
            ->filter(static fn (mixed $assignment): bool => is_array($assignment))
            ->pluck('id_prodi')
            ->map(static fn (mixed $id): string => trim((string) $id))
            ->filter()
            ->unique()
            ->values();
        $programs = $programIds->isEmpty()
            ? collect()
            : $connection->table('s1m4k_masterprodi')
                ->whereIn('id_prodi', $programIds)
                ->get(['id_prodi', 'id_fakultas', 'id_perguruan_tinggi'])
                ->keyBy('id_prodi');
        $facultyIds = $programs->pluck('id_fakultas')
            ->map(static fn (mixed $id): string => trim((string) $id))
            ->filter()
            ->unique()
            ->values();
        $facultyIds = $facultyIds->isEmpty()
            ? collect()
            : $connection->table('s1m4k_masterfakultas')
                ->whereIn('id_fakultas', $facultyIds)
                ->pluck('id_fakultas');

        $latestAssignments = [];
        foreach ($assignments as $assignment) {
            if (! is_array($assignment)) {
                continue;
            }
            $lecturerId = trim((string) ($assignment['id_dosen'] ?? ''));
            if ($lecturerId === '' || isset($latestAssignments[$lecturerId])) {
                continue;
            }
            $programId = trim((string) ($assignment['id_prodi'] ?? ''));
            $program = $programId !== '' ? $programs->get($programId) : null;
            $facultyId = trim((string) ($program->id_fakultas ?? ''));
            if ($program !== null && $facultyId !== '' && $facultyIds->contains($facultyId)) {
                $latestAssignments[$lecturerId] = $assignment;
            }
        }

        $universityId = trim((string) $connection->table('s1m4k_masterpt')
            ->value('id_perguruan_tinggi'));
        if ($universityId === '') {
            throw new RuntimeException('ID perguruan tinggi belum tersedia di master perguruan tinggi SIMAK.');
        }

        $supportsNuptk = Schema::connection('simak_main')->hasColumn('s1m4k_masterdosen', 'nuptk');

        return $connection->transaction(function () use (
            $connection,
            $lecturers,
            $latestAssignments,
            $programs,
            $universityId,
            $supportsNuptk,
        ): array {
            $synced = 0;
            $created = 0;
            $updated = 0;
            $syncedWithoutHomebase = 0;
            $skippedIdentifier = 0;
            $seenIds = [];
            $seenNidns = [];

            foreach ($lecturers as $lecturer) {
                if (! is_array($lecturer)) {
                    continue;
                }
                $feederId = trim((string) ($lecturer['id_dosen'] ?? ''));
                $nidn = trim((string) ($lecturer['nidn'] ?? ''));
                $nip = trim((string) ($lecturer['nip'] ?? ''));
                $loginIdentifier = $nidn !== '' ? $nidn : $nip;
                if ($feederId === '' || isset($seenIds[$feederId])) {
                    continue;
                }
                $seenIds[$feederId] = true;
                $assignment = $latestAssignments[$feederId] ?? [];
                $programId = trim((string) ($assignment['id_prodi'] ?? ''));
                $program = $programId !== '' ? $programs->get($programId) : null;
                if ($loginIdentifier === '') {
                    $skippedIdentifier++;

                    continue;
                }
                if (isset($seenNidns[$loginIdentifier])) {
                    continue;
                }
                $seenNidns[$loginIdentifier] = true;

                $values = [
                    'id_perguruan_tinggi' => $universityId,
                    'nama_dosen' => trim((string) (
                        ($program !== null ? ($assignment['nama_dosen'] ?? null) : null)
                        ?? $lecturer['nama_dosen']
                        ?? ''
                    )),
                    'nidn' => $loginIdentifier,
                    'nip' => $nip !== '' ? $nip : null,
                    'jenis_kelamin' => $lecturer['jenis_kelamin'] ?? null,
                    'id_agama' => $lecturer['id_agama'] ?? null,
                    'tanggal_lahir' => $this->legacyDate($lecturer['tanggal_lahir'] ?? null),
                    'id_status_aktif' => $lecturer['id_status_aktif'] ?? null,
                    'nama_status_aktif' => $lecturer['nama_status_aktif'] ?? null,
                    'flag_feeder' => 1,
                ];
                if ($program !== null) {
                    $values += [
                        'id_registrasi_dosen' => $assignment['id_registrasi_dosen'] ?? null,
                        'id_fakultas' => $program->id_fakultas,
                        'id_prodi' => $program->id_prodi,
                        'id_tahun_ajaran' => $assignment['id_tahun_ajaran'] ?? null,
                        'nomor_surat_tugas' => $assignment['nomor_surat_tugas'] ?? null,
                        'tanggal_surat_tugas' => $this->legacyDate($assignment['tanggal_surat_tugas'] ?? null),
                        'mulai_surat_tugas' => $this->legacyDate($assignment['mulai_surat_tugas'] ?? null),
                    ];
                } else {
                    $syncedWithoutHomebase++;
                }
                if ($supportsNuptk) {
                    $values['nuptk'] = $lecturer['nuptk'] ?? null;
                }

                $existing = $connection->table('s1m4k_masterdosen')
                    ->where(function ($query) use ($feederId, $loginIdentifier): void {
                        $query->where('id_dosen', $feederId);
                        if ($loginIdentifier !== '') {
                            $query->orWhere('nidn', $loginIdentifier);
                        }
                    })
                    ->first(['id_dosen']);

                if ($existing !== null) {
                    $connection->table('s1m4k_masterdosen')
                        ->where('id_dosen', $existing->id_dosen)->update($values);
                    $updated++;
                } else {
                    $connection->table('s1m4k_masterdosen')->insert([
                        'id_dosen' => $feederId,
                        'password' => bcrypt('dosen'.$loginIdentifier),
                        'id_registrasi_dosen' => null,
                        'id_fakultas' => null,
                        'id_prodi' => null,
                        'id_tahun_ajaran' => null,
                        'nomor_surat_tugas' => null,
                        'tanggal_surat_tugas' => null,
                        'mulai_surat_tugas' => null,
                        ...$values,
                    ]);
                    $created++;
                }
                $synced++;
            }

            return [
                'synced' => $synced,
                'created' => $created,
                'updated' => $updated,
                'skipped' => count($lecturers) - $synced,
                'synced_without_homebase' => $syncedWithoutHomebase,
                'skipped_identifier' => $skippedIdentifier,
            ];
        });
    }

    public function syncStudentRegistrationIds(?Closure $progress = null): array
    {
        $connection = DB::connection('simak_main');
        foreach (['nim', 'nama_mahasiswa', 'id_registrasi_mahasiswa'] as $column) {
            if (! Schema::connection('simak_main')->hasColumn('s1m4k_mastermhs', $column)) {
                throw new RuntimeException("Kolom {$column} belum tersedia pada tabel s1m4k_mastermhs.");
            }
        }

        [$url, $token] = $this->connectionToken();
        $localStudents = $connection->table('s1m4k_mastermhs')
            ->whereNotNull('nama_mahasiswa')
            ->where('nama_mahasiswa', '<>', '')
            ->whereNotNull('nim')
            ->where('nim', '<>', '')
            ->whereIn('id_status_mahasiswa', ['A', 'C', 'M', 'N'])
            ->get(['id_mahasiswa', 'nim', 'id_registrasi_mahasiswa']);
        $updated = 0;
        $unchanged = 0;
        $failures = [];
        $total = $localStudents->count();
        $progress?->__invoke([
            'event' => 'start',
            'total' => $total,
        ]);

        foreach ($localStudents as $index => $student) {
            $studentId = trim((string) $student->id_mahasiswa);
            $nim = trim((string) $student->nim);
            $current = $index + 1;
            $progress?->__invoke([
                'event' => 'checking',
                'current' => $current,
                'total' => $total,
                'nim' => $nim,
            ]);
            try {
                $rows = $this->requestWithToken($url, $token, 'GetListMahasiswa', [
                    'filter' => "nim='".$this->filterValue($nim)."'",
                    'limit' => '1',
                    'offset' => 0,
                ]);
            } catch (RuntimeException $exception) {
                $failures[] = ['nim' => $nim, 'reason' => $exception->getMessage()];
                Log::warning('Sinkronisasi mahasiswa Feeder gagal untuk NIM.', [
                    'nim' => $nim,
                    'reason' => $exception->getMessage(),
                ]);
                $progress?->__invoke([
                    'event' => 'completed',
                    'current' => $current,
                    'total' => $total,
                    'nim' => $nim,
                    'updated' => $updated,
                    'unchanged' => $unchanged,
                    'failed' => count($failures),
                ]);

                continue;
            }
            $feederStudent = collect($rows)->first(fn (mixed $row): bool => is_array($row)
                && trim((string) ($row['nim'] ?? '')) === $nim);
            if (! is_array($feederStudent)) {
                $reason = 'Mahasiswa tidak ditemukan di Feeder.';
                $failures[] = ['nim' => $nim, 'reason' => $reason];
                Log::warning('Sinkronisasi mahasiswa Feeder tidak menemukan data lengkap untuk NIM.', [
                    'nim' => $nim,
                    'reason' => $reason,
                ]);
                $progress?->__invoke([
                    'event' => 'completed',
                    'current' => $current,
                    'total' => $total,
                    'nim' => $nim,
                    'updated' => $updated,
                    'unchanged' => $unchanged,
                    'failed' => count($failures),
                ]);

                continue;
            }
            $registrationId = trim((string) ($feederStudent['id_registrasi_mahasiswa'] ?? ''));
            $registrationId = $registrationId !== '' ? $registrationId : null;
            if ((string) ($student->id_registrasi_mahasiswa ?? '') === (string) ($registrationId ?? '')) {
                $unchanged++;
                $progress?->__invoke([
                    'event' => 'completed',
                    'current' => $current,
                    'total' => $total,
                    'nim' => $nim,
                    'updated' => $updated,
                    'unchanged' => $unchanged,
                    'failed' => count($failures),
                ]);

                continue;
            }

            $connection->table('s1m4k_mastermhs')
                ->where('id_mahasiswa', $studentId)
                ->update([
                    'id_registrasi_mahasiswa' => $registrationId,
                ]);
            $updated++;
            $progress?->__invoke([
                'event' => 'completed',
                'current' => $current,
                'total' => $total,
                'nim' => $nim,
                'updated' => $updated,
                'unchanged' => $unchanged,
                'failed' => count($failures),
            ]);
        }

        return [
            'checked' => $localStudents->count(),
            'updated' => $updated,
            'unchanged' => $unchanged,
            'failed' => count($failures),
            'failures' => $failures,
        ];
    }

    public function syncStudent(string $npm, string $programId): int
    {
        $connection = DB::connection('simak_main');
        $program = $connection->table('s1m4k_masterprodi')
            ->where('id_prodi', $programId)
            ->first(['id_prodi', 'id_fakultas', 'id_perguruan_tinggi']);
        if ($program === null) {
            throw new RuntimeException('Program studi tidak ditemukan di SIMAK.');
        }

        $filter = "nim='".$this->filterValue($npm)."' AND id_prodi='".$this->filterValue($programId)."'";
        $studentRows = $this->request('GetDataLengkapMahasiswaProdi', [
            'filter' => $filter,
            'order' => '',
            'limit' => '',
            'offset' => 0,
        ]);
        $student = collect($studentRows)->first(
            fn ($row) => is_array($row)
                && trim((string) ($row['nim'] ?? '')) === $npm
                && trim((string) ($row['id_prodi'] ?? '')) === $programId
        );
        if (! is_array($student)) {
            throw new RuntimeException('Mahasiswa dengan NPM tersebut tidak ditemukan pada program studi terpilih di Feeder.');
        }

        $studentId = trim((string) ($student['id_mahasiswa'] ?? ''));
        if ($studentId === '') {
            throw new RuntimeException('Respons Feeder tidak memuat ID mahasiswa.');
        }

        $histories = $this->request('GetListRiwayatPendidikanMahasiswa', [
            'filter' => "id_mahasiswa='".$this->filterValue($studentId)."' AND id_prodi='".$this->filterValue($programId)."'",
            'order' => '',
            'limit' => '',
            'offset' => 0,
        ]);
        $history = collect($histories)->first(fn ($row) => is_array($row));
        $status = $connection->table('s1m4k_kodereferensi')
            ->whereIn('keterangan', ['GetStatusMahasiswa', 'GetJenisKeluar'])
            ->whereRaw('LOWER(TRIM(ket_referensi)) = ?', [
                mb_strtolower(trim((string) ($student['nama_status_mahasiswa'] ?? ''))),
            ])
            ->orderByRaw("CASE WHEN keterangan = 'GetStatusMahasiswa' THEN 0 ELSE 1 END")
            ->first(['kode_referensi', 'ket_referensi']);

        $values = [
            'id_perguruan_tinggi' => $history['id_perguruan_tinggi'] ?? $program->id_perguruan_tinggi,
            'id_fakultas' => $program->id_fakultas,
            'id_prodi' => $program->id_prodi,
            'id_registrasi_mahasiswa' => $student['id_registrasi_mahasiswa'] ?? null,
            'id_status_mahasiswa' => $status->kode_referensi ?? $student['id_status_mahasiswa'] ?? null,
            'nama_status_mahasiswa' => $status->ket_referensi ?? $student['nama_status_mahasiswa'] ?? null,
            'nim' => $npm,
            'nama_mahasiswa' => $student['nama_mahasiswa'] ?? null,
            'password' => bcrypt($npm),
            'jenis_kelamin' => $student['jenis_kelamin'] ?? null,
            'jalan' => $student['jalan'] ?? null,
            'rt' => $student['rt'] ?? null,
            'rw' => $student['rw'] ?? null,
            'dusun' => $student['dusun'] ?? null,
            'kelurahan' => $student['kelurahan'] ?? null,
            'kode_pos' => $student['kode_pos'] ?? null,
            'nisn' => $student['nisn'] ?? null,
            'nik' => $student['nik'] ?? null,
            'tempat_lahir' => $student['tempat_lahir'] ?? null,
            'tanggal_lahir' => $this->legacyDate($student['tanggal_lahir'] ?? null),
            'nama_ayah' => $student['nama_ayah'] ?? null,
            'tanggal_lahir_ayah' => $this->legacyDate($student['tanggal_lahir_ayah'] ?? null),
            'nik_ayah' => $student['nik_ayah'] ?? null,
            'id_pendidikan_ayah' => $student['id_pendidikan_ayah'] ?? null,
            'id_pekerjaan_ayah' => $student['id_pekerjaan_ayah'] ?? null,
            'id_penghasilan_ayah' => $student['id_penghasilan_ayah'] ?? null,
            'id_kebutuhan_khusus_ayah' => $student['id_kebutuhan_khusus_ayah'] ?? null,
            'nama_ibu' => $student['nama_ibu_kandung'] ?? $student['nama_ibu'] ?? null,
            'tanggal_lahir_ibu' => $this->legacyDate($student['tanggal_lahir_ibu'] ?? null),
            'nik_ibu' => $student['nik_ibu'] ?? null,
            'id_pendidikan_ibu' => $student['id_pendidikan_ibu'] ?? null,
            'id_pekerjaan_ibu' => $student['id_pekerjaan_ibu'] ?? null,
            'id_penghasilan_ibu' => $student['id_penghasilan_ibu'] ?? null,
            'id_kebutuhan_khusus_ibu' => $student['id_kebutuhan_khusus_ibu'] ?? null,
            'nama_wali' => $student['nama_wali'] ?? null,
            'tanggal_lahir_wali' => $this->legacyDate($student['tanggal_lahir_wali'] ?? null),
            'id_pendidikan_wali' => $student['id_pendidikan_wali'] ?? null,
            'id_pekerjaan_wali' => $student['id_pekerjaan_wali'] ?? null,
            'id_penghasilan_wali' => $student['id_penghasilan_wali'] ?? null,
            'id_kebutuhan_khusus_mahasiswa' => $student['id_kebutuhan_khusus_mahasiswa'] ?? null,
            'telepon' => $student['telepon'] ?? null,
            'handphone' => $student['handphone'] ?? null,
            'email' => $student['email'] ?? null,
            'penerima_kps' => $student['penerima_kps'] ?? null,
            'nomor_kps' => $student['nomor_kps'] ?? null,
            'npwp' => $student['npwp'] ?? null,
            'id_wilayah' => $student['id_wilayah'] ?? null,
            'id_jenis_tinggal' => $student['id_jenis_tinggal'] ?? null,
            'id_agama' => $student['id_agama'] ?? null,
            'id_alat_transportasi' => $student['id_alat_transportasi'] ?? null,
            'id_negara' => $this->studentCountryCode($student),
            'id_jenis_daftar' => $history['id_jenis_daftar'] ?? null,
            'id_jalur_daftar' => $history['id_jalur_daftar'] ?? null,
            'id_periode_masuk' => $student['id_periode_masuk'] ?? null,
            'tanggal_daftar' => $this->legacyDate($history['tanggal_daftar'] ?? null),
            'sks_diakui' => number_format((float) ($student['sks_diakui'] ?? 0), 0, '.', ''),
            'id_perguruan_tinggi_asal' => $student['id_perguruan_tinggi_asal'] ?? null,
            'id_prodi_asal' => $student['id_prodi_asal'] ?? null,
            'id_pembiayaan' => $history['id_pembiayaan'] ?? null,
            'biaya_masuk_kuliah' => $history['biaya_masuk'] ?? null,
            'flag_feeder' => 1,
            'update_ukt' => 'N',
        ];

        return $connection->transaction(function () use ($connection, $studentId, $npm, $values): int {
            $existing = $connection->table('s1m4k_mastermhs')
                ->where('id_mahasiswa', $studentId)
                ->first(['id_mahasiswa', 'nim']);
            if ($existing !== null && (string) $existing->nim !== $npm) {
                $archivedId = $studentId.'-'.now()->format('Ymd');
                $connection->table('s1m4k_mastermhs')
                    ->where('id_mahasiswa', $studentId)->update(['id_mahasiswa' => $archivedId]);
                $connection->table('s1m4k_krs')
                    ->where('id_mahasiswa', $studentId)
                    ->update(['id_mahasiswa' => $archivedId, 'id_mahasiswa_old' => $studentId]);
                $existing = null;
            }

            if ($existing !== null) {
                $connection->table('s1m4k_mastermhs')
                    ->where('id_mahasiswa', $studentId)->update($values);
            } else {
                $connection->table('s1m4k_mastermhs')->insert([
                    'id_mahasiswa' => $studentId,
                    ...$values,
                ]);
            }

            return 1;
        });
    }

    public function updateFeederCredentials(string $username, ?string $password): void
    {
        $connection = DB::connection('simak_main');
        $universityId = $connection->table('s1m4k_masterpt')->value('id_perguruan_tinggi');
        if (! is_string($universityId) || trim($universityId) === '') {
            throw new RuntimeException('Data perguruan tinggi belum tersedia di SIMAK.');
        }

        $values = ['user_feeder' => trim($username)];
        if (filled($password)) {
            $values['password_feeder'] = $this->encrypt($password);
        }
        $connection->table('s1m4k_masterpt')->where('id_perguruan_tinggi', $universityId)->update($values);
    }

    public function testConnection(): void
    {
        $this->connectionToken();
    }

    public function initializationRequest(
        string $url,
        string $username,
        string $password,
        ?string $token,
        string $action,
        array $parameters,
        int $timeout = 8,
    ): array {
        if ($timeout < 1 || $timeout > 600) {
            throw new RuntimeException('Timeout request inisialisasi Feeder harus antara 1 dan 600 detik.');
        }
        if ($token === null || trim($token) === '') {
            $token = $this->initializationToken($url, $username, $password, $timeout);
        }

        $response = $this->post($url, [
            'act' => $action,
            'token' => $token,
            ...$parameters,
        ], throwFeederErrors: false, timeout: $timeout);
        if ($this->isInvalidTokenResponse($response)) {
            $token = $this->initializationToken($url, $username, $password, $timeout);
            $response = $this->post($url, [
                'act' => $action,
                'token' => $token,
                ...$parameters,
            ], throwFeederErrors: false, timeout: $timeout);
        }
        if (isset($response['error_code']) && (string) $response['error_code'] !== '0') {
            $code = trim((string) $response['error_code']);
            $description = trim((string) ($response['error_desc'] ?? 'Kesalahan tidak dijelaskan.'));
            throw new RuntimeException("Feeder gagal ({$code}): {$description}");
        }

        return [
            'rows' => $this->responseRows($response, $action),
            'token' => $token,
        ];
    }

    /**
     * @param  array<int, array{action: string, parameters: array}>  $requests
     * @return array{responses: array<int, array{rows?: array, error_code?: string, error_desc?: string}>, token: string}
     */
    public function initializationRequests(
        string $url,
        string $username,
        string $password,
        ?string $token,
        array $requests,
        int $timeout = 8,
    ): array {
        if ($timeout < 1 || $timeout > 600) {
            throw new RuntimeException('Timeout request inisialisasi Feeder harus antara 1 dan 600 detik.');
        }
        if ($requests === []) {
            throw new RuntimeException('Daftar request paralel Feeder tidak boleh kosong.');
        }
        if ($token === null || trim($token) === '') {
            $token = $this->initializationToken($url, $username, $password, $timeout);
        }

        $responses = $this->postInitializationPool($url, $token, $requests, $timeout);
        $invalidTokenIndexes = array_keys(array_filter(
            $responses,
            fn (array $response): bool => $this->isInvalidTokenResponse($response),
        ));
        if ($invalidTokenIndexes !== []) {
            $token = $this->initializationToken($url, $username, $password, $timeout);
            $retryRequests = array_intersect_key($requests, array_flip($invalidTokenIndexes));
            $retryResponses = $this->postInitializationPool($url, $token, $retryRequests, $timeout);
            foreach ($invalidTokenIndexes as $index) {
                $responses[$index] = $retryResponses[$index];
            }
        }

        $results = [];
        foreach ($responses as $index => $response) {
            if (isset($response['error_code']) && (string) $response['error_code'] !== '0') {
                $results[$index] = [
                    'error_code' => trim((string) $response['error_code']),
                    'error_desc' => trim((string) ($response['error_desc'] ?? 'Kesalahan tidak dijelaskan.')),
                ];

                continue;
            }
            $action = $requests[$index]['action'];
            $results[$index] = ['rows' => $this->responseRows($response, $action)];
        }

        return ['responses' => $results, 'token' => $token];
    }

    public function testInitializationConnection(string $url, string $username, string $password): string
    {
        return $this->initializationToken($url, $username, $password, 8);
    }

    public function storeInitializationConnectionSettings(string $url, string $username, string $password): void
    {
        $connection = DB::connection('simak_main');
        $universityId = $connection->table('s1m4k_masterpt')->value('id_perguruan_tinggi');
        if (! is_string($universityId) || trim($universityId) === '') {
            throw new RuntimeException('Profil perguruan tinggi harus diimpor sebelum menyimpan konfigurasi Feeder.');
        }

        $connection->table('s1m4k_masterpt')->where('id_perguruan_tinggi', $universityId)->update([
            'alamat_feeder' => trim($url),
            'user_feeder' => trim($username),
            'password_feeder' => $this->encrypt($password),
        ]);
    }

    public function shortSemesterCurricula(): array
    {
        return $this->request('GetListKurikulum', [
            'filter' => "semester_mulai_berlaku LIKE '%Pendek'",
            'order' => '',
            'limit' => '',
            'offset' => 0,
        ]);
    }

    public function ensureShortSemesterCurricula(
        array $programIds,
        string $semesterId,
        string $period,
        string $curriculumName
    ): array {
        if ($programIds === []) {
            return [];
        }

        $timeout = 7;
        [$url, $token] = $this->connectionToken($timeout);
        $curricula = [];
        $programIds = array_values(array_unique(array_map(static function (mixed $programId): string {
            if (! is_string($programId) || trim($programId) === '') {
                throw new RuntimeException('ID program studi untuk sinkronisasi SP tidak valid.');
            }

            return trim($programId);
        }, $programIds)));

        foreach ($programIds as $programId) {
            $filter = "id_semester = '".$this->filterValue($semesterId)
                ."' AND id_prodi = '".$this->filterValue($programId)."'";
            $response = $this->post($url, [
                'act' => 'GetListKurikulum',
                'token' => $token,
                'filter' => $filter,
                'order' => '',
                'limit' => 1,
                'offset' => 0,
            ], timeout: $timeout);
            $rows = array_slice($this->responseRows($response, 'GetListKurikulum'), 0, 1);

            if ($rows === []) {
                $record = [
                    'nama_kurikulum' => $curriculumName,
                    'id_prodi' => $programId,
                    'id_semester' => $semesterId,
                    'semester_mulai_berlaku' => $period,
                    'jumlah_sks_lulus' => 120,
                    'jumlah_sks_wajib' => 100,
                    'jumlah_sks_pilihan' => 20,
                ];
                $insertResponse = $this->post($url, [
                    'act' => 'InsertKurikulum',
                    'token' => $token,
                    'record' => $record,
                ], timeout: $timeout);
                $insertedRows = $this->responseRows($insertResponse, 'InsertKurikulum');
                $inserted = $insertedRows[0] ?? $insertResponse['data'] ?? null;
                $curriculumId = is_array($inserted)
                    ? trim((string) ($inserted['id_kurikulum'] ?? ''))
                    : '';
                if ($curriculumId === '') {
                    throw new RuntimeException("Feeder tidak mengembalikan id_kurikulum setelah membuat kurikulum SP untuk prodi {$programId}.");
                }

                $rows = [['id_kurikulum' => $curriculumId, ...$record]];
            }

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    throw new RuntimeException("Respons kurikulum SP Feeder untuk prodi {$programId} tidak valid.");
                }
                $returnedProgramId = trim((string) ($row['id_prodi'] ?? ''));
                $returnedSemesterId = trim((string) ($row['id_semester'] ?? ''));
                if ($returnedProgramId !== $programId || $returnedSemesterId !== $semesterId) {
                    throw new RuntimeException("Respons kurikulum Feeder tidak sesuai dengan prodi {$programId} dan kode semester {$semesterId} yang diminta.");
                }

                $curricula[] = $row;
            }
        }

        return $curricula;
    }

    public function exportAction(string $action, array $parameters = []): array
    {
        $timeout = self::EXPORT_REQUEST_TIMEOUT_SECONDS;
        [$url, $token] = $this->connectionToken($timeout, true);
        $payload = [
            'act' => $action,
            'token' => $token,
            ...$parameters,
        ];
        $response = $this->post($url, $payload, throwFeederErrors: false, timeout: $timeout);
        if (! $this->isInvalidTokenResponse($response)) {
            return $response;
        }

        [$url, $token] = $this->connectionToken($timeout, true, true);
        $payload['token'] = $token;

        return $this->post($url, $payload, throwFeederErrors: false, timeout: $timeout);
    }

    private function request(string $action, array $parameters): array
    {
        [$url, $token] = $this->connectionToken();

        return $this->requestWithToken($url, $token, $action, $parameters);
    }

    private function requestWithToken(string $url, string $token, string $action, array $parameters): array
    {
        $response = $this->post($url, [
            'act' => $action,
            'token' => $token,
            ...$parameters,
        ]);

        return $this->responseRows($response, $action);
    }

    private function responseRows(array $response, string $action): array
    {
        if (! array_key_exists('data', $response)) {
            throw new RuntimeException('Respons Feeder tidak memuat data untuk tindakan '.$action.'.');
        }
        $data = $response['data'] ?? [];
        if (! is_array($data)) {
            throw new RuntimeException('Format data Feeder tidak sesuai.');
        }

        return array_is_list($data) ? $data : [$data];
    }

    private function postInitializationPool(string $url, string $token, array $requests, int $timeout): array
    {
        try {
            $responses = Http::pool(function (Pool $pool) use ($url, $token, $requests, $timeout): array {
                $pending = [];
                foreach ($requests as $index => $request) {
                    $pending[$index] = $pool->as((string) $index)
                        ->asJson()
                        ->connectTimeout(min(4, $timeout))
                        ->timeout($timeout)
                        ->post($url, [
                            'act' => $request['action'],
                            'token' => $token,
                            ...$request['parameters'],
                        ]);
                }

                return $pending;
            });
        } catch (ConnectionException $exception) {
            report($exception);
            throw new RuntimeException('Tidak dapat terhubung ke server Feeder.', previous: $exception);
        }

        $results = [];
        foreach ($requests as $index => $_request) {
            $response = $responses[(string) $index] ?? null;
            if (! $response instanceof Response) {
                throw new RuntimeException('Request paralel Feeder tidak menghasilkan respons yang valid.');
            }
            if (! $response->successful()) {
                throw new RuntimeException('Server Feeder mengembalikan HTTP '.$response->status().'.');
            }
            $data = $response->json();
            if (! is_array($data)) {
                throw new RuntimeException('Server Feeder mengembalikan respons yang tidak valid.');
            }
            $results[$index] = $data;
        }

        return $results;
    }

    private function initializationToken(string $url, string $username, string $password, int $timeout): string
    {
        $response = $this->post($url, [
            'act' => 'GetToken',
            'username' => $username,
            'password' => $password,
        ], timeout: $timeout);
        $token = $response['data']['token'] ?? null;
        if (! is_string($token) || trim($token) === '') {
            throw new RuntimeException('Feeder tidak memberikan token akses.');
        }

        return $token;
    }

    private function connectionToken(int $timeout = 120, bool $reuse = false, bool $refresh = false): array
    {
        $settings = DB::connection('simak_main')->table('s1m4k_masterpt')->first([
            'alamat_feeder', 'user_feeder', 'password_feeder',
        ]);
        if ($settings === null
            || trim((string) $settings->alamat_feeder) === ''
            || trim((string) $settings->user_feeder) === ''
            || trim((string) $settings->password_feeder) === '') {
            throw new RuntimeException('Konfigurasi koneksi Feeder belum lengkap pada data perguruan tinggi.');
        }

        $url = trim((string) $settings->alamat_feeder);
        if (! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)
            || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException('Alamat server Feeder tidak valid.');
        }

        $username = trim((string) $settings->user_feeder);
        $password = $this->decrypt((string) $settings->password_feeder);
        $cacheKey = 'feeder-export-token:'.hash('sha256', json_encode([
            $url,
            $username,
            (string) $settings->password_feeder,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $token = $reuse && ! $refresh ? Cache::get($cacheKey) : null;
        if (! is_string($token) || trim($token) === '') {
            $tokenResponse = $this->post($url, [
                'act' => 'GetToken',
                'username' => $username,
                'password' => $password,
            ], timeout: $timeout);
            $token = $tokenResponse['data']['token'] ?? null;
            if (! is_string($token) || trim($token) === '') {
                throw new RuntimeException('Feeder tidak memberikan token akses.');
            }
            if ($reuse) {
                Cache::put($cacheKey, $token, now()->addMinutes(self::EXPORT_TOKEN_TTL_MINUTES));
            }
        }

        return [$url, $token];
    }

    private function isInvalidTokenResponse(array $response): bool
    {
        $description = mb_strtolower(trim((string) ($response['error_desc'] ?? '')));
        if (! str_contains($description, 'token')) {
            return false;
        }

        foreach (['tidak valid', 'invalid', 'expired', 'kadaluarsa', 'kedaluwarsa', 'salah'] as $indicator) {
            if (str_contains($description, $indicator)) {
                return true;
            }
        }

        return false;
    }

    private function post(
        string $url,
        array $payload,
        bool $throwFeederErrors = true,
        int $timeout = 120,
    ): array {
        try {
            $response = Http::asJson()->connectTimeout(min(4, $timeout))->timeout($timeout)->post($url, $payload);
        } catch (ConnectionException $exception) {
            report($exception);
            throw new RuntimeException('Tidak dapat terhubung ke server Feeder.', previous: $exception);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Server Feeder mengembalikan HTTP '.$response->status().'.');
        }

        $result = $response->json();
        if (! is_array($result)) {
            throw new RuntimeException('Server Feeder mengembalikan respons yang tidak valid.');
        }
        if ($throwFeederErrors && isset($result['error_code']) && (string) $result['error_code'] !== '0') {
            $code = trim((string) $result['error_code']);
            $description = trim((string) ($result['error_desc'] ?? 'Kesalahan tidak dijelaskan.'));
            throw new RuntimeException("Feeder gagal ({$code}): {$description}");
        }

        return $result;
    }

    private function decrypt(string $value): string
    {
        $decoded = base64_decode(urldecode($value), false);
        if ($decoded === false) {
            throw new RuntimeException('Kata sandi Feeder tersimpan dalam format yang tidak valid.');
        }

        $plain = '';
        $length = strlen(self::KEY);
        for ($index = 0, $size = strlen($decoded); $index < $size; $index++) {
            $keyCharacter = substr(self::KEY, ($index % $length) - 1, 1);
            $plain .= chr(ord($decoded[$index]) - ord($keyCharacter));
        }

        return $plain;
    }

    private function encrypt(string $value): string
    {
        $encrypted = '';
        $length = strlen(self::KEY);
        for ($index = 0, $size = strlen($value); $index < $size; $index++) {
            $keyCharacter = substr(self::KEY, ($index % $length) - 1, 1);
            $encrypted .= chr((ord($value[$index]) + ord($keyCharacter)) % 256);
        }

        return urlencode(base64_encode($encrypted));
    }

    private function filterValue(string $value): string
    {
        return str_replace("'", "''", $value);
    }

    private function legacyDate(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '0000-00-00') {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : date('Y-m-d', $timestamp);
    }

    private function studentCountryCode(array $student): ?string
    {
        $countryValues = array_filter([
            trim((string) ($student['id_negara'] ?? '')),
            trim((string) ($student['kewarganegaraan'] ?? '')),
        ], static fn (string $value): bool => $value !== '');

        foreach ($countryValues as $value) {
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

        if ($countryValues === []) {
            return null;
        }

        throw new RuntimeException('Negara mahasiswa dari Feeder tidak ditemukan pada referensi negara SIMAK: '.reset($countryValues));
    }
}
