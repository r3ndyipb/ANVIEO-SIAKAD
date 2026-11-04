<?php

namespace App\Services;

use RuntimeException;

class ShortSemesterScheduleFeederService
{
    public function insertClass(array $record): string
    {
        $response = app(LegacyFeederService::class)->exportAction('InsertKelasKuliah', [
            'record' => $record,
        ]);
        if ((string) ($response['error_code'] ?? '') !== '0') {
            throw new RuntimeException($this->errorMessage($response, 'Feeder gagal membuat kelas kuliah SP.'));
        }

        $classId = trim((string) ($response['data']['id_kelas_kuliah'] ?? ''));
        if ($classId === '') {
            throw new RuntimeException('Feeder menyatakan kelas berhasil dibuat tetapi tidak mengembalikan ID kelas kuliah. Periksa kelas di Feeder sebelum mencoba lagi.');
        }

        return $classId;
    }

    public function updateClass(string $classId, array $record): void
    {
        $response = app(LegacyFeederService::class)->exportAction('UpdateKelasKuliah', [
            'key' => ['id_kelas_kuliah' => $classId],
            'record' => $record,
        ]);
        $this->assertSuccess($response, 'Feeder gagal memperbarui kelas kuliah SP.');
    }

    public function synchronizeLecturers(
        string $classId,
        string $programId,
        array $lecturers,
        float $courseCredits
    ): void {
        $existing = $this->classLecturers($classId);
        $requestedByNidn = [];
        foreach ($lecturers as $lecturer) {
            $nidn = trim((string) ($lecturer['nidn'] ?? ''));
            if ($nidn === '') {
                throw new RuntimeException('NIDN dosen pengampu tidak tersedia di SIMAK.');
            }
            if (isset($requestedByNidn[$nidn])) {
                throw new RuntimeException('NIDN dosen pengampu harus unik pada jadwal SP.');
            }
            $requestedByNidn[$nidn] = $lecturer;
        }
        if ($requestedByNidn === []) {
            throw new RuntimeException('Jadwal SP harus memiliki minimal satu dosen pengampu.');
        }
        $creditsPerLecturer = number_format($courseCredits / count($requestedByNidn), 2, '.', '');

        $matched = [];
        foreach ($existing as $assignment) {
            $nidn = trim((string) ($assignment['nidn'] ?? ''));
            $activityId = trim((string) ($assignment['id_aktivitas_mengajar'] ?? ''));
            if ($activityId === '') {
                throw new RuntimeException('Feeder tidak mengembalikan ID aktivitas pengampu kelas SP.');
            }

            if ($nidn !== '' && isset($requestedByNidn[$nidn]) && ! isset($matched[$nidn])) {
                $requested = $requestedByNidn[$nidn];
                $record = $requested['record'];
                $record['id_registrasi_dosen'] = $this->registrationId(
                    $nidn,
                    $programId
                );
                $record['id_kelas_kuliah'] = $classId;
                $record['sks_substansi_total'] = $creditsPerLecturer;
                $response = app(LegacyFeederService::class)->exportAction('UpdateDosenPengajarKelasKuliah', [
                    'key' => ['id_aktivitas_mengajar' => $activityId],
                    'record' => $record,
                ]);
                $this->assertSuccess($response, 'Feeder gagal memperbarui dosen pengajar kelas kuliah.');
                $matched[$nidn] = true;

                continue;
            }

            $this->deleteLecturer($activityId);
        }

        foreach ($requestedByNidn as $nidn => $requested) {
            if (isset($matched[$nidn])) {
                continue;
            }

            $record = $requested['record'];
            $record['id_kelas_kuliah'] = $classId;
            $record['sks_substansi_total'] = $creditsPerLecturer;
            $this->insertLecturer($record, $nidn, $programId);
        }
    }

    public function deleteClass(string $classId): void
    {
        foreach ($this->classLecturers($classId) as $assignment) {
            $activityId = trim((string) ($assignment['id_aktivitas_mengajar'] ?? ''));
            if ($activityId === '') {
                throw new RuntimeException('Feeder tidak mengembalikan ID aktivitas pengampu kelas SP.');
            }
            $this->deleteLecturer($activityId);
        }

        $response = app(LegacyFeederService::class)->exportAction('DeleteKelasKuliah', [
            'key' => ['id_kelas_kuliah' => $classId],
        ]);
        $this->assertSuccess($response, 'Feeder gagal menghapus kelas kuliah SP.');
    }

    public function insertLecturer(array $record, string $nidn, string $programId): string
    {
        $feeder = app(LegacyFeederService::class);
        $record['id_registrasi_dosen'] = $this->registrationId($nidn, $programId);
        $response = $feeder->exportAction('InsertDosenPengajarKelasKuliah', [
            'record' => $record,
        ]);
        $this->assertSuccess($response, 'Feeder gagal menyimpan dosen pengajar kelas kuliah.');
        $activityId = trim((string) ($response['data']['id_aktivitas_mengajar'] ?? ''));
        if ($activityId === '') {
            throw new RuntimeException('Feeder menyatakan dosen berhasil disimpan tetapi tidak mengembalikan ID aktivitas mengajar. Periksa aktivitas dosen di Feeder sebelum mencoba lagi.');
        }

        return $activityId;
    }

    public function insertParticipant(string $classId, string $registrationId): void
    {
        $response = app(LegacyFeederService::class)->exportAction('InsertPesertaKelasKuliah', [
            'record' => [
                'id_kelas_kuliah' => $classId,
                'id_registrasi_mahasiswa' => $registrationId,
            ],
        ]);
        $this->assertSuccess($response, 'Feeder gagal menambahkan peserta kelas SP.');
    }

    public function deleteParticipant(string $classId, string $registrationId): void
    {
        $response = app(LegacyFeederService::class)->exportAction('DeletePesertaKelasKuliah', [
            'key' => [
                'id_kelas_kuliah' => $classId,
                'id_registrasi_mahasiswa' => $registrationId,
            ],
        ]);
        $this->assertSuccess($response, 'Feeder gagal menghapus peserta kelas SP.');
    }

    public function updateParticipantGrade(string $classId, string $registrationId, array $record): void
    {
        $response = app(LegacyFeederService::class)->exportAction('UpdateNilaiPerkuliahanKelas', [
            'key' => [
                'id_kelas_kuliah' => $classId,
                'id_registrasi_mahasiswa' => $registrationId,
            ],
            'record' => $record,
        ]);
        $this->assertSuccess($response, 'Feeder gagal memperbarui nilai peserta kelas SP.');
    }

    private function classLecturers(string $classId): array
    {
        $response = app(LegacyFeederService::class)->exportAction('GetDosenPengajarKelasKuliah', [
            'filter' => "id_kelas_kuliah='".$this->escapeFilter($classId)."'",
            'order' => '',
            'limit' => 100,
            'offset' => 0,
        ]);
        $this->assertSuccess($response, 'Feeder gagal membaca daftar dosen pengampu kelas SP.');

        $rows = $response['data'] ?? [];
        if (! is_array($rows) || $rows === []) {
            return [];
        }

        return array_is_list($rows) ? $rows : [$rows];
    }

    private function deleteLecturer(string $activityId): void
    {
        $response = app(LegacyFeederService::class)->exportAction('DeleteDosenPengajarKelasKuliah', [
            'key' => ['id_aktivitas_mengajar' => $activityId],
        ]);
        $this->assertSuccess($response, 'Feeder gagal menghapus dosen pengajar kelas kuliah.');
    }

    private function registrationId(string $nidn, string $programId): string
    {
        $assignments = app(LegacyFeederService::class)->exportAction('GetListPenugasanDosen', [
            'filter' => "nidn='".$this->escapeFilter($nidn)."'",
            'order' => 'id_tahun_ajaran DESC',
            'limit' => 100,
            'offset' => 0,
        ]);
        $this->assertSuccess($assignments, 'Feeder gagal mencari penugasan dosen.');

        $rows = $assignments['data'] ?? [];
        $rows = is_array($rows) && array_is_list($rows) ? $rows : [$rows];
        foreach ($rows as $assignment) {
            if (! is_array($assignment)) {
                continue;
            }
            $assignmentNidn = trim((string) ($assignment['nidn'] ?? ''));
            $assignmentProgramId = trim((string) ($assignment['id_prodi'] ?? ''));
            $candidateId = trim((string) ($assignment['id_registrasi_dosen'] ?? ''));
            if ($assignmentNidn === $nidn && $assignmentProgramId === $programId && $candidateId !== '') {
                return $candidateId;
            }
        }

        throw new RuntimeException('ID registrasi dosen untuk NIDN '.$nidn.' tidak ditemukan pada penugasan Feeder di program studi ini.');
    }

    private function assertSuccess(array $response, string $fallback): void
    {
        if ((string) ($response['error_code'] ?? '') !== '0') {
            throw new RuntimeException($this->errorMessage($response, $fallback));
        }
    }

    private function escapeFilter(string $value): string
    {
        return str_replace("'", "''", $value);
    }

    private function errorMessage(array $response, string $fallback): string
    {
        $code = trim((string) ($response['error_code'] ?? ''));
        $description = trim((string) ($response['error_desc'] ?? ''));
        if ($code === '' && $description === '') {
            return $fallback;
        }

        return $fallback.' ('.($code !== '' ? $code.': ' : '').($description !== '' ? $description : 'Kesalahan tidak dijelaskan.').')';
    }
}
