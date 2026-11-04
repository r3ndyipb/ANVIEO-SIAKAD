<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ActivityLogger
{
    public function record(
        object $user,
        string $module,
        string $action,
        array $changes = [],
        ?string $recordId = null,
        array $recordIdentity = []
    ): void {
        $activity = ['aksi' => $action];
        if ($recordId !== null) {
            $activity['id_data'] = $recordId;
        }
        if ($recordIdentity !== []) {
            $activity['identitas_data'] = $this->protectCredentials($recordIdentity);
        }
        if ($changes !== []) {
            $activity['perubahan'] = $this->protectCredentials($changes);
        }

        DB::connection('simak_main')->table('log_aktivitas')->insert([
            'id_log' => (string) Str::uuid(),
            'id_user' => (string) data_get($user, 'legacy_id', ''),
            'level_user' => (string) data_get($user, 'audit_role', data_get($user, 'role', '')),
            'nama_user' => (string) data_get($user, 'name', data_get($user, 'login', '')),
            'modul' => $module,
            'aktifitas' => json_encode($activity, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
        if (app()->bound('request')) {
            request()->attributes->set('activity_log_recorded', true);
        }
    }

    public function diff(array|object $before, array|object $after): array
    {
        $before = (array) $before;
        $after = (array) $after;
        $changes = [];

        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $field) {
            $oldValue = $before[$field] ?? null;
            $newValue = $after[$field] ?? null;
            if ($oldValue != $newValue) {
                $changes[$field] = ['sebelum' => $oldValue, 'sesudah' => $newValue];
            }
        }

        return $changes;
    }

    private function protectCredentials(array $values): array
    {
        $recorded = [];
        foreach ($values as $key => $value) {
            if (preg_match('/password|token|secret/i', (string) $key) === 1) {
                $recorded[$key] = true;
            } elseif (is_array($value)) {
                $recorded[$key] = $this->protectCredentials($value);
            } else {
                $recorded[$key] = $value;
            }
        }

        return $recorded;
    }
}
