<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SimakNotificationService
{
    private const TEACHER_SLOTS = [
        'dosen1', 'dosen2', 'dosen3', 'dosen4', 'dosen5', 'dosen6', 'dosen7',
        'dosen8', 'dosen9', 'dosen10', 'dosen11', 'dosen12', 'dosen13', 'dosen14',
    ];

    public function send(
        string $role,
        string $recipientId,
        string $category,
        string $title,
        string $message,
        string $url
    ): void {
        $this->sendMany([[$role, $recipientId]], $category, $title, $message, $url);
    }

    public function sendProgram(
        string $programId,
        string $category,
        string $title,
        string $message,
        string $url
    ): void {
        if ($programId === '' || ! Schema::connection('simak_main')->hasTable('s1m4k_admin')) {
            return;
        }

        $accounts = DB::connection('simak_main')->table('s1m4k_admin')
            ->where('level', 'userprodi')
            ->where('id_prodi', $programId)
            ->where('blokir', 'N')
            ->pluck('id_admin')
            ->map(fn (mixed $id): array => ['userprodi', (string) $id])
            ->all();

        $this->sendMany($accounts, $category, $title, $message, $url);
    }

    public function sendScheduleLecturers(
        string $scheduleId,
        string $category,
        string $title,
        string $message,
        string $url
    ): void {
        $schema = Schema::connection('simak_main');
        if (! $schema->hasTable('s1m4k_jadwal')) {
            return;
        }

        $slots = array_values(array_filter(
            self::TEACHER_SLOTS,
            fn (string $column): bool => $schema->hasColumn('s1m4k_jadwal', $column)
        ));
        if ($slots === []) {
            return;
        }

        $schedule = DB::connection('simak_main')->table('s1m4k_jadwal')
            ->where('id_jadwal', $scheduleId)
            ->first($slots);
        if ($schedule === null) {
            return;
        }

        $recipients = collect($slots)
            ->map(fn (string $slot): string => trim((string) $schedule->{$slot}))
            ->filter()
            ->unique()
            ->map(fn (string $id): array => ['dosen', $id])
            ->all();

        $this->sendMany($recipients, $category, $title, $message, $url);
    }

    public function sendApprovedScheduleStudents(
        string $scheduleId,
        string $category,
        string $title,
        string $message,
        string $url
    ): void {
        $schema = Schema::connection('simak_main');
        foreach (['s1m4k_jadwal', 's1m4k_krs', 's1m4k_pengesahankrs'] as $table) {
            if (! $schema->hasTable($table)) {
                Log::warning('SIMAK schedule notification was not sent because a source table is missing.', [
                    'table' => $table,
                    'category' => $category,
                ]);

                return;
            }
        }

        $students = DB::connection('simak_main')->table('s1m4k_jadwal as schedule')
            ->join('s1m4k_krs as krs', function ($join): void {
                $join->on('krs.id_jadwal', '=', 'schedule.id_jadwal')
                    ->on('krs.id_kalender', '=', 'schedule.id_kalender');
            })
            ->where('schedule.id_jadwal', $scheduleId)
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('s1m4k_pengesahankrs as approval')
                    ->whereColumn('approval.id_mahasiswa', 'krs.id_mahasiswa')
                    ->whereColumn('approval.id_kalender', 'krs.id_kalender')
                    ->whereRaw("TRIM(COALESCE(approval.yang_mengesahkan, '')) <> ''")
                    ->whereRaw("TRIM(COALESCE(approval.nama_kpro, '')) <> ''");
            })
            ->distinct()
            ->pluck('krs.id_mahasiswa')
            ->map(fn (mixed $id): array => ['mahasiswa', (string) $id])
            ->all();

        $this->sendMany($students, $category, $title, $message, $url);
    }

    public function sendApprovedCourseStudents(
        string $courseId,
        string $category,
        string $title,
        string $message,
        string $url
    ): void {
        $schema = Schema::connection('simak_main');
        foreach (['s1m4k_jadwal', 's1m4k_kalender', 's1m4k_krs', 's1m4k_pengesahankrs'] as $table) {
            if (! $schema->hasTable($table)) {
                Log::warning('SIMAK course notification was not sent because a source table is missing.', [
                    'table' => $table,
                    'category' => $category,
                ]);

                return;
            }
        }

        $students = DB::connection('simak_main')->table('s1m4k_jadwal as schedule')
            ->join('s1m4k_kalender as calendar', 'calendar.id_kalender', '=', 'schedule.id_kalender')
            ->join('s1m4k_krs as krs', function ($join): void {
                $join->on('krs.id_jadwal', '=', 'schedule.id_jadwal')
                    ->on('krs.id_kalender', '=', 'schedule.id_kalender');
            })
            ->where('schedule.id_matkul', $courseId)
            ->where('calendar.status', 'Y')
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('s1m4k_pengesahankrs as approval')
                    ->whereColumn('approval.id_mahasiswa', 'krs.id_mahasiswa')
                    ->whereColumn('approval.id_kalender', 'krs.id_kalender')
                    ->whereRaw("TRIM(COALESCE(approval.yang_mengesahkan, '')) <> ''")
                    ->whereRaw("TRIM(COALESCE(approval.nama_kpro, '')) <> ''");
            })
            ->distinct()
            ->pluck('krs.id_mahasiswa')
            ->map(fn (mixed $id): array => ['mahasiswa', (string) $id])
            ->all();

        $this->sendMany($students, $category, $title, $message, $url);
    }

    private function sendMany(
        array $recipients,
        string $category,
        string $title,
        string $message,
        string $url
    ): void {
        if ($recipients === []) {
            return;
        }

        if (! Schema::connection('simak_main')->hasTable('eskripsi_notifikasi')) {
            Log::warning('SIMAK in-app notification was not stored because its table is missing.', [
                'category' => $category,
                'recipient_count' => count($recipients),
            ]);

            return;
        }

        $now = now();
        $rows = [];
        foreach ($recipients as [$role, $recipientId]) {
            $recipientId = trim((string) $recipientId);
            if (! in_array($role, ['mahasiswa', 'dosen', 'userprodi'], true) || $recipientId === '') {
                continue;
            }
            $rows[] = [
                'id' => (string) Str::uuid(),
                'recipient_role' => $role,
                'recipient_id' => $recipientId,
                'category' => $category,
                'title' => mb_substr($title, 0, 255),
                'message' => mb_substr($message, 0, 2000),
                'url' => mb_substr($url, 0, 500),
                'is_read' => 0,
                'created_at' => $now,
                'read_at' => null,
            ];
        }

        if ($rows !== []) {
            DB::connection('simak_main')->table('eskripsi_notifikasi')->insert($rows);
        }
    }
}
