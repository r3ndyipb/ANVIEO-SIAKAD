<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->role === 'admin', 403);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'module' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $query = DB::connection('simak_main')->table('log_aktivitas');

        if (filled($filters['search'] ?? null)) {
            $search = '%'.trim($filters['search']).'%';
            $query->where(function ($builder) use ($search): void {
                $builder->where('nama_user', 'like', $search)
                    ->orWhere('id_user', 'like', $search)
                    ->orWhere('modul', 'like', $search)
                    ->orWhere('aktifitas', 'like', $search);
            });
        }
        if (filled($filters['module'] ?? null)) {
            if ($filters['module'] === 'LMS') {
                $query->where(function ($builder): void {
                    $builder->where('modul', 'LMS')
                        ->orWhere(function ($mobile) {
                            $mobile->where('modul', 'SIMAK Mobile')
                                ->where('aktifitas', 'like', '%"route":"lms.%');
                        });
                });
            } else {
                $query->where('modul', $filters['module']);
            }
        }
        if (filled($filters['from'] ?? null)) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }
        if (filled($filters['to'] ?? null)) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        $activities = $query->orderByDesc('created_at')
            ->orderByDesc('id_log')
            ->paginate(50)
            ->withQueryString()
            ->through(function (object $activity): object {
                $details = json_decode($activity->aktifitas, true)
                    ?? ['catatan' => $activity->aktifitas];
                $activity->details = $details;
                $route = data_get($details, 'perubahan.route');
                if ($activity->modul === 'SIMAK Mobile'
                    && is_string($route)
                    && str_starts_with($route, 'lms.')) {
                    $activity->modul = 'LMS (Mobile)';
                }

                return $activity;
            });
        $modules = DB::connection('simak_main')->table('log_aktivitas')
            ->distinct()
            ->orderBy('modul')
            ->pluck('modul');

        return view('activity-logs.index', [
            'activities' => $activities,
            'modules' => $modules,
            'filters' => $filters,
        ]);
    }
}
