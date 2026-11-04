<?php

namespace App\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ActivityNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! Schema::connection('simak_main')->hasTable('log_aktivitas')) {
            return response()->json([
                'message' => 'Tabel log_aktivitas belum tersedia pada koneksi simak_main.',
            ], 503);
        }

        $canMarkRead = $this->readTableAvailable();
        [$role, $userId] = $this->recipient($request);
        $query = $this->activitiesFor($role, $userId, $canMarkRead);
        $unreadCount = $canMarkRead
            ? (clone $query)->whereNull('activity_read.activity_id')->count()
            : 0;
        $columns = [
            'activity.id_log',
            'activity.modul',
            'activity.aktifitas',
            'activity.created_at',
        ];
        $columns[] = $canMarkRead
            ? 'activity_read.activity_id as read_activity_id'
            : DB::raw('NULL as read_activity_id');
        $notifications = $query->orderByDesc('activity.created_at')
            ->orderByDesc('activity.id_log')
            ->limit(30)
            ->get($columns)
            ->map(static function (object $activity) use ($canMarkRead): array {
                $details = json_decode($activity->aktifitas, true);
                $details = is_array($details) ? $details : [];
                $action = $details['aksi'] ?? 'Aktivitas tercatat';
                $description = trim(is_scalar($action) ? (string) $action : 'Aktivitas tercatat');
                $identity = trim((string) data_get($details, 'identitas_data.nama', ''));
                if ($identity !== '') {
                    $description .= ' · '.$identity;
                }

                return [
                    'id' => (string) $activity->id_log,
                    'title' => (string) $activity->modul,
                    'message' => $description,
                    'url' => '',
                    'is_read' => ! $canMarkRead || $activity->read_activity_id !== null,
                    'created_at' => (string) $activity->created_at,
                ];
            })
            ->all();

        return response()->json([
            'unread_count' => $unreadCount,
            'notifications' => $notifications,
            'can_mark_read' => $canMarkRead,
        ]);
    }

    public function markRead(Request $request, string $activity): JsonResponse
    {
        if (! $this->readTableAvailable()) {
            return $this->readStateUnavailable();
        }

        [$role, $userId] = $this->recipient($request);
        $exists = DB::connection('simak_main')->table('log_aktivitas')
            ->where('id_log', $activity)
            ->where('level_user', $role)
            ->where('id_user', $userId)
            ->exists();
        abort_unless($exists, 404);

        $this->storeRead($role, $userId, $activity);

        return response()->json(['message' => 'Log aktivitas ditandai sudah dibaca.']);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        if (! $this->readTableAvailable()) {
            return $this->readStateUnavailable();
        }

        [$role, $userId] = $this->recipient($request);
        $activityIds = $this->activitiesFor($role, $userId, true)
            ->whereNull('activity_read.activity_id')
            ->pluck('activity.id_log');

        foreach ($activityIds->chunk(500) as $chunk) {
            $readAt = now();
            $rows = $chunk->map(static fn (string $activityId): array => [
                'recipient_role' => $role,
                'recipient_id' => $userId,
                'activity_id' => $activityId,
                'read_at' => $readAt,
            ])->all();
            DB::connection('simak_main')->table('simak_activity_log_reads')->insertOrIgnore($rows);
        }

        return response()->json(['message' => 'Semua log aktivitas telah dibaca.', 'unread_count' => 0]);
    }

    private function activitiesFor(string $role, string $userId, bool $includeReadState): Builder
    {
        $query = DB::connection('simak_main')->table('log_aktivitas as activity');
        if ($includeReadState) {
            $query->leftJoin('simak_activity_log_reads as activity_read', function ($join) use ($role, $userId): void {
                $join->on('activity_read.activity_id', '=', 'activity.id_log')
                    ->where('activity_read.recipient_role', '=', $role)
                    ->where('activity_read.recipient_id', '=', $userId);
            });
        }

        return $query->where('activity.level_user', $role)
            ->where('activity.id_user', $userId);
    }

    private function recipient(Request $request): array
    {
        $user = $request->user();
        $role = trim((string) data_get($user, 'role', ''));
        $userId = trim((string) data_get($user, 'legacy_id', data_get($user, 'id', '')));
        abort_if($role === '' || $userId === '', 403);

        return [$role, $userId];
    }

    private function storeRead(string $role, string $userId, string $activityId): void
    {
        DB::connection('simak_main')->table('simak_activity_log_reads')->insertOrIgnore([
            'recipient_role' => $role,
            'recipient_id' => $userId,
            'activity_id' => $activityId,
            'read_at' => now(),
        ]);
    }

    private function readTableAvailable(): bool
    {
        return Schema::connection('simak_main')->hasTable('simak_activity_log_reads');
    }

    private function readStateUnavailable(): JsonResponse
    {
        return response()->json([
            'message' => 'Riwayat tetap dapat dilihat, tetapi status baca belum aktif. Jalankan SQL database/manual/activity_log_notification_reads.sql untuk mengaktifkan status baca.',
        ], 409);
    }
}
