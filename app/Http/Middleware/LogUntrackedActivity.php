<?php

namespace App\Http\Middleware;

use App\Services\ActivityLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LogUntrackedActivity
{
    private const EXCLUDED_ROUTES = [
        'activity-notifications.',
        'theses.notifications.',
        'login.store',
        'locale.update',
        'logout',
        'password.',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $route = $request->route();
        $user = $request->user();
        $method = strtoupper($request->method());
        $routeName = $route?->getName();
        $trackedLmsRead = in_array($routeName, [
            'lms.materials.download',
            'lms.meetings.attendance.print',
        ], true);

        if (
            $user === null
            || $routeName === null
            || (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true) && ! $trackedLmsRead)
            || $request->attributes->get('activity_log_recorded', false)
            || $this->isExcludedRoute($routeName)
            || ! $this->wasSuccessful($request, $response)
        ) {
            return $response;
        }

        $changes = ['route' => $routeName, 'method' => $method];
        $routeParameters = [];
        $recordId = null;
        $studentIdentity = [];
        if (Str::startsWith($routeName, 'lms.')) {
            $routeParameters = $this->routeParameters($route);
            $recordId = $this->lmsRecordId($routeParameters);
            [$context, $studentIdentity] = $this->lmsAuditContext($request, $routeName, $routeParameters);
            $changes['record'] = $routeParameters;
            $changes['input'] = $this->auditInput($request->except(['_token', '_method']));
            $changes['entitas'] = $this->lmsEntityName($routeName);
            if ($context !== []) {
                $changes['konteks'] = $context;
            }
            $files = $this->auditFiles($request->allFiles());
            if ($files !== []) {
                $changes['files'] = $files;
            }
        }

        app(ActivityLogger::class)->record(
            $user,
            $this->moduleName($routeName),
            $this->actionName($routeName, $method),
            $changes,
            $recordId,
            $studentIdentity,
        );

        return $response;
    }

    private function isExcludedRoute(string $routeName): bool
    {
        foreach (self::EXCLUDED_ROUTES as $excluded) {
            if ($routeName === $excluded || Str::startsWith($routeName, $excluded)) {
                return true;
            }
        }

        return false;
    }

    private function wasSuccessful(Request $request, Response $response): bool
    {
        if ($response->isSuccessful()) {
            return true;
        }

        return $response->isRedirection()
            && $request->hasSession()
            && ! $request->session()->has('errors');
    }

    private function moduleName(string $routeName): string
    {
        $prefix = Str::before($routeName, '.');

        return match ($prefix) {
            'lms' => 'LMS',
            'theses' => 'E-Skripsi',
            'master-data' => 'Data Master',
            'simak-users' => 'Pengguna SIMAK',
            'academic-calendars' => 'Kalender Akademik',
            'student-activities' => 'Kegiatan Mahasiswa',
            'student-academic-activities' => 'Aktivitas Akademik Mahasiswa',
            'activity-logs', 'activity-notifications' => 'Log Aktivitas',
            'survey-edom' => 'Survey EDOM',
            'class-schedules' => 'Jadwal Kuliah',
            default => Str::headline(str_replace(['_', '-'], ' ', $prefix)),
        };
    }

    private function actionName(string $routeName, string $method): string
    {
        if (Str::startsWith($routeName, 'lms.')) {
            return $this->lmsActionName($routeName);
        }

        $operation = Str::afterLast($routeName, '.');
        $action = match (true) {
            in_array($operation, ['store', 'create', 'generate', 'upload'], true) => 'Menambahkan atau memproses data',
            in_array($operation, ['update', 'save', 'edit'], true) => 'Memperbarui data',
            in_array($operation, ['destroy', 'delete', 'remove', 'unregister', 'cancel'], true) => 'Menghapus atau membatalkan data',
            in_array($operation, ['sync', 'synchronize'], true) => 'Menyinkronkan data',
            in_array($operation, ['send', 'message'], true) => 'Mengirim aktivitas',
            in_array($operation, ['approve', 'activate', 'status', 'lock', 'schedule', 'grade', 'reset'], true) => 'Memproses perubahan',
            default => match ($method) {
                'POST' => 'Menjalankan proses',
                'DELETE' => 'Menghapus data',
                default => 'Memperbarui data',
            },
        };

        return $action.' ('.Str::headline(str_replace(['_', '-'], ' ', $operation)).')';
    }

    private function lmsActionName(string $routeName): string
    {
        return match ($routeName) {
            'lms.materials.store' => 'Menambahkan materi kuliah',
            'lms.materials.update' => 'Memperbarui materi kuliah',
            'lms.materials.destroy' => 'Menghapus materi kuliah',
            'lms.materials.download' => 'Mengunduh materi LMS',
            'lms.meetings.store' => 'Membuat pertemuan kuliah',
            'lms.meetings.update' => 'Memperbarui pertemuan kuliah',
            'lms.meetings.destroy' => 'Menghapus pertemuan kuliah',
            'lms.meetings.attendance.print' => 'Mencetak daftar hadir pertemuan',
            'lms.attendance.self' => 'Mengisi absensi mandiri',
            'lms.attendance.mark' => 'Mencatat absensi mahasiswa',
            'lms.attendance.cancel' => 'Membatalkan absensi mahasiswa',
            'lms.questions.import' => 'Mengimpor bank soal',
            'lms.questions.store' => 'Menambahkan soal LMS',
            'lms.questions.update' => 'Memperbarui soal LMS',
            'lms.questions.destroy' => 'Menghapus soal LMS',
            'lms.exams.store' => 'Membuat ujian LMS',
            'lms.exams.update' => 'Memperbarui ujian LMS',
            'lms.exams.start' => 'Memulai ujian LMS',
            'lms.exams.answers' => 'Menyimpan jawaban ujian',
            'lms.exams.submit' => 'Mengumpulkan ujian',
            'lms.exams.grade' => 'Menilai jawaban ujian',
            'lms.tasks.store' => 'Membuat tugas LMS',
            'lms.tasks.update' => 'Memperbarui tugas LMS',
            'lms.tasks.destroy' => 'Menghapus tugas LMS',
            'lms.tasks.submit' => 'Mengumpulkan tugas',
            'lms.tasks.grade' => 'Menilai pengumpulan tugas',
            'lms.discussions.store' => 'Membuka diskusi LMS',
            'lms.discussions.update' => 'Memperbarui diskusi LMS',
            'lms.discussions.destroy' => 'Menghapus diskusi LMS',
            'lms.discussions.messages.store' => 'Mengirim pesan diskusi',
            'lms.discussions.messages.destroy' => 'Menghapus pesan diskusi',
            default => 'Memproses perubahan LMS ('.Str::headline(str_replace(['_', '-'], ' ', Str::afterLast($routeName, '.'))).')',
        };
    }

    private function routeParameters(?Route $route): array
    {
        if ($route === null) {
            return [];
        }

        return collect($route->parameters())
            ->filter(static fn (mixed $value): bool => is_scalar($value) || $value === null)
            ->all();
    }

    private function lmsRecordId(array $parameters): ?string
    {
        foreach (['record', 'student', 'message', 'submission', 'result'] as $key) {
            $value = trim((string) ($parameters[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function lmsEntityName(string $routeName): string
    {
        return match (true) {
            Str::startsWith($routeName, 'lms.attendance.') => 'Absensi pertemuan',
            Str::startsWith($routeName, 'lms.meetings.') => 'Pertemuan kuliah',
            Str::startsWith($routeName, 'lms.materials.') => 'Materi kuliah',
            Str::startsWith($routeName, 'lms.questions.') => 'Soal bank LMS',
            Str::startsWith($routeName, 'lms.exams.') => 'Ujian LMS',
            Str::startsWith($routeName, 'lms.tasks.') => 'Tugas LMS',
            Str::startsWith($routeName, 'lms.discussions.messages.') => 'Pesan diskusi LMS',
            Str::startsWith($routeName, 'lms.discussions.') => 'Diskusi LMS',
            default => 'Aktivitas LMS',
        };
    }

    private function lmsAuditContext(Request $request, string $routeName, array $parameters): array
    {
        $connection = DB::connection('simak_main');
        $schema = Schema::connection('simak_main');
        $context = [];
        $studentId = trim((string) ($parameters['student'] ?? ''));
        $student = null;

        if ($studentId === '' && isset($parameters['submission']) && $schema->hasTable('lms_pengumpulan_tugas')) {
            $studentId = trim((string) $connection->table('lms_pengumpulan_tugas')
                ->where('id_pengumpulan', $parameters['submission'])->value('id_mahasiswa'));
        }
        if ($studentId === '' && isset($parameters['result']) && $schema->hasTable('lms_hasil_ujian')) {
            $studentId = trim((string) $connection->table('lms_hasil_ujian')
                ->where('id_hasil_ujian', $parameters['result'])->value('id_mahasiswa'));
        }
        if ($studentId === '' && $request->user()?->role === 'mahasiswa'
            && $schema->hasTable('s1m4k_mastermhs')
            && $schema->hasColumn('s1m4k_mastermhs', 'nim')) {
            $studentId = trim((string) $connection->table('s1m4k_mastermhs')
                ->where('nim', (string) $request->user()->login)->value('id_mahasiswa'));
        }
        if ($studentId !== '' && $schema->hasTable('s1m4k_mastermhs')) {
            $columns = array_values(array_filter(
                ['id_mahasiswa', 'nim', 'nama_mahasiswa'],
                fn (string $column): bool => $schema->hasColumn('s1m4k_mastermhs', $column)
            ));
            if ($columns !== []) {
                $student = $connection->table('s1m4k_mastermhs')
                    ->where('id_mahasiswa', $studentId)->first($columns);
                if ($student !== null) {
                    $context['mahasiswa'] = array_filter([
                        'id' => $student->id_mahasiswa ?? null,
                        'nim' => $student->nim ?? null,
                        'nama' => $student->nama_mahasiswa ?? null,
                    ], static fn (mixed $value): bool => $value !== null && $value !== '');
                }
            }
        }

        $record = trim((string) ($parameters['record'] ?? ''));
        $recordTable = match (true) {
            Str::startsWith($routeName, 'lms.materials.') => ['lms_materi_kuliah', 'id_materi', ['judul', 'id_matkul', 'id_prodi']],
            Str::startsWith($routeName, 'lms.meetings.')
                || Str::startsWith($routeName, 'lms.attendance.')
                || $routeName === 'lms.exams.store' => ['lms_pertemuan', 'id_pertemuan', ['id_jadwal', 'pertemuan_ke', 'judul', 'tanggal_kuliah', 'status_pertemuan']],
            Str::startsWith($routeName, 'lms.tasks.') => ['lms_tugas', 'id_tugas', ['id_jadwal', 'judul', 'status_tugas', 'batas_pengumpulan']],
            Str::startsWith($routeName, 'lms.discussions.') => ['lms_diskusi', 'id_diskusi', ['id_jadwal', 'judul', 'status_diskusi']],
            Str::startsWith($routeName, 'lms.exams.') => ['lms_ujian', 'id_ujian', ['id_pertemuan', 'id_matkul', 'judul', 'jenis_ujian', 'status_ujian']],
            Str::startsWith($routeName, 'lms.questions.') => ['lms_bank_soal', 'id_soal', ['id_matkul', 'jenis_soal', 'aktif']],
            default => null,
        };

        if ($recordTable !== null && $record !== '' && $schema->hasTable($recordTable[0])) {
            [$table, $key, $candidateColumns] = $recordTable;
            $columns = array_values(array_filter(
                [$key, ...$candidateColumns],
                fn (string $column): bool => $schema->hasColumn($table, $column)
            ));
            if (count($columns) > 1) {
                $relatedRecord = $connection->table($table)->where($key, $record)->first($columns);
                if ($relatedRecord !== null) {
                    $context['data_terkait'] = (array) $relatedRecord;
                }
            }
        }

        if ($routeName === 'lms.discussions.messages.store' && $record !== ''
            && $schema->hasTable('lms_diskusi')) {
            $discussion = $connection->table('lms_diskusi')->where('id_diskusi', $record)->first(['judul', 'id_jadwal']);
            if ($discussion !== null) {
                $context['diskusi'] = ['judul' => $discussion->judul, 'id_jadwal' => $discussion->id_jadwal];
            }
        }

        $scheduleId = trim((string) ($request->input('id_jadwal')
            ?? $context['data_terkait']['id_jadwal']
            ?? ''));
        if ($scheduleId !== '' && $schema->hasTable('s1m4k_jadwal')) {
            $scheduleColumns = array_values(array_filter(
                ['id_jadwal', 'id_prodi', 'id_kalender', 'id_matkul', 'nama_kelas'],
                fn (string $column): bool => $schema->hasColumn('s1m4k_jadwal', $column)
            ));
            if ($scheduleColumns !== []) {
                $schedule = $connection->table('s1m4k_jadwal')
                    ->where('id_jadwal', $scheduleId)->first($scheduleColumns);
                if ($schedule !== null) {
                    $context['jadwal_kuliah'] = (array) $schedule;
                    if (isset($schedule->id_matkul) && $schema->hasTable('s1m4k_mastermk')) {
                        $courseColumns = array_values(array_filter(
                            ['kode_mata_kuliah', 'nama_mata_kuliah'],
                            fn (string $column): bool => $schema->hasColumn('s1m4k_mastermk', $column)
                        ));
                        if ($courseColumns !== []) {
                            $context['mata_kuliah'] = (array) $connection->table('s1m4k_mastermk')
                                ->where('id_matkul', $schedule->id_matkul)->first($courseColumns);
                        }
                    }
                }
            }
        }

        if (in_array($routeName, [
            'lms.attendance.mark',
            'lms.attendance.self',
            'lms.attendance.cancel',
        ], true)) {
            $meetingId = $record;
            if ($meetingId !== '' && $schema->hasTable('lms_absensi')) {
                $attendanceQuery = $connection->table('lms_absensi')->where('id_pertemuan', $meetingId);
                if ($studentId !== '') {
                    $attendanceQuery->where('id_mahasiswa', $studentId);
                }
                $attendanceColumns = array_values(array_filter(
                    ['id_mahasiswa', 'status_kehadiran', 'metode_absen', 'waktu_absen', 'catatan'],
                    fn (string $column): bool => $schema->hasColumn('lms_absensi', $column)
                ));
                if ($attendanceColumns !== []) {
                    $attendance = $attendanceQuery->first($attendanceColumns);
                    if ($attendance !== null) {
                        $context['absensi'] = (array) $attendance;
                    }
                }
            }
        }

        return [$context, $student === null ? [] : [
            'jenis' => 'Mahasiswa',
            'nama' => (string) ($student->nama_mahasiswa ?? ''),
            'nomor_identitas' => (string) ($student->nim ?? ''),
        ]];
    }

    private function auditInput(array $input): array
    {
        $input = array_slice($input, 0, 80, true);
        foreach ($input as $key => $value) {
            if (preg_match('/^(kunci_jawaban|jawaban_benar)$/i', (string) $key) === 1) {
                $input[$key] = true;
            } elseif (preg_match('/^(jawaban|isi_pesan|deskripsi|petunjuk|pertanyaan|opsi_[a-e])$/i', (string) $key) === 1) {
                $input[$key] = [
                    'tersimpan' => true,
                    'jumlah_karakter' => $this->stringCharacterCount($value),
                    'jumlah_item' => is_array($value) ? count($value) : null,
                ];
            } elseif (is_string($value) && mb_strlen($value) > 2000) {
                $input[$key] = mb_substr($value, 0, 2000).' [dipotong]';
            } elseif (is_array($value)) {
                $input[$key] = $this->auditInput($value);
            }
        }

        return $input;
    }

    private function stringCharacterCount(mixed $value): ?int
    {
        if (is_string($value)) {
            return mb_strlen($value);
        }
        if (! is_array($value)) {
            return null;
        }

        $counts = array_map(fn (mixed $item): ?int => $this->stringCharacterCount($item), $value);

        return array_sum(array_filter($counts, static fn (?int $count): bool => $count !== null));
    }

    private function auditFiles(array $files): array
    {
        $recorded = [];
        foreach ($files as $key => $file) {
            if (is_array($file)) {
                $recorded[$key] = $this->auditFiles($file);
            } elseif ($file instanceof UploadedFile) {
                $recorded[$key] = [
                    'nama' => mb_substr($file->getClientOriginalName(), 0, 255),
                    'ukuran_byte' => $file->getSize(),
                    'tipe' => $file->getClientMimeType(),
                ];
            }
        }

        return $recorded;
    }
}
