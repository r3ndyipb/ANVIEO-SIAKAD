<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ThesisNotificationController extends Controller
{
    private const TABLES = ['eskripsi_notifikasi', 'etesis_notifikasi'];

    public function index(Request $request): JsonResponse
    {
        if (! $this->tableAvailable()) {
            return $this->tableUnavailable();
        }

        [$role, $recipientId] = $this->recipient($request);
        $unreadCount = 0;
        $notifications = collect();
        foreach ($this->availableTables() as $table) {
            $query = DB::connection('simak_main')->table($table)
                ->where('recipient_role', $role)
                ->where('recipient_id', $recipientId);
            $unreadCount += (clone $query)->where('is_read', 0)->count();
            $notifications = $notifications->concat(
                $query->orderByDesc('created_at')->orderByDesc('id')->limit(30)->get([
                    'id', 'category', 'title', 'message', 'url', 'is_read', 'created_at',
                ])
            );
        }
        $notifications = $notifications->sortByDesc(
            static fn (object $notification): string => (string) $notification->created_at.'|'.(string) $notification->id
        )->take(30)->map(static fn (object $notification): array => [
            'id' => (string) $notification->id,
            'category' => (string) $notification->category,
            'title' => (string) $notification->title,
            'message' => (string) $notification->message,
            'url' => (string) $notification->url,
            'is_read' => (bool) $notification->is_read,
            'created_at' => (string) $notification->created_at,
        ])->values()->all();

        return response()->json([
            'unread_count' => $unreadCount,
            'notifications' => $notifications,
            'can_mark_read' => in_array($request->user()->role, ['mahasiswa', 'dosen', 'userprodi'], true),
        ]);
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        if (! $this->tableAvailable()) {
            return $this->tableUnavailable();
        }

        [$role, $recipientId] = $this->recipient($request);
        foreach ($this->availableTables() as $table) {
            $query = DB::connection('simak_main')->table($table)
                ->where('id', $notification)
                ->where('recipient_role', $role)
                ->where('recipient_id', $recipientId);
            if ($query->where('is_read', 0)->update(['is_read' => 1, 'read_at' => now()]) === 1
                || DB::connection('simak_main')->table($table)
                    ->where('id', $notification)
                    ->where('recipient_role', $role)
                    ->where('recipient_id', $recipientId)
                    ->where('is_read', 1)->exists()) {
                return response()->json(['message' => 'Notifikasi ditandai sudah dibaca.']);
            }
        }

        abort(404);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        if (! $this->tableAvailable()) {
            return $this->tableUnavailable();
        }

        [$role, $recipientId] = $this->recipient($request);
        foreach ($this->availableTables() as $table) {
            DB::connection('simak_main')->table($table)
                ->where('recipient_role', $role)
                ->where('recipient_id', $recipientId)
                ->where('is_read', 0)
                ->update(['is_read' => 1, 'read_at' => now()]);
        }

        return response()->json(['message' => 'Semua notifikasi telah dibaca.', 'unread_count' => 0]);
    }

    private function recipient(Request $request): array
    {
        $user = $request->user();
        if (! in_array($user->role, ['mahasiswa', 'dosen', 'userprodi'], true)) {
            return ['', ''];
        }
        $recipientId = trim((string) data_get($user, 'legacy_id'));

        return [$user->role, $recipientId];
    }

    private function tableAvailable(): bool
    {
        return $this->availableTables() !== [];
    }

    private function availableTables(): array
    {
        return array_values(array_filter(
            self::TABLES,
            static fn (string $table): bool => Schema::connection('simak_main')->hasTable($table)
        ));
    }

    private function tableUnavailable(): JsonResponse
    {
        return response()->json([
            'message' => 'Penyimpanan notifikasi belum tersedia. Buat tabel notifikasi E-Skripsi atau E-Tesis & Disertasi menggunakan SQL yang disediakan.',
        ], 503);
    }
}
