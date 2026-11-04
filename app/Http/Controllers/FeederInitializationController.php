<?php

namespace App\Http\Controllers;

use App\Services\FeederInitializationService;
use App\Services\LegacyFeederService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

class FeederInitializationController extends Controller
{
    private const SESSION_KEY = 'feeder_initialization_job';

    private const CACHE_TTL_MINUTES = 240;

    public function index(Request $request): View
    {
        $this->authorizeAdmin($request->user());
        $settings = DB::connection('simak_main')->table('s1m4k_masterpt')->first([
            'alamat_feeder', 'user_feeder',
        ]);
        $jobId = $request->session()->get(self::SESSION_KEY);
        $state = is_string($jobId) ? Cache::get($this->cacheKey($jobId)) : null;
        if (is_array($state) && $this->ownsJob($request, $state)
            && in_array((int) ($state['wizard_version'] ?? 0), [2, 3], true)) {
            $state = $this->upgradeJobState($state);
            Cache::put($this->cacheKey($state['job_id']), $state, now()->addMinutes(self::CACHE_TTL_MINUTES));
        } else {
            if (is_string($jobId) && Str::isUuid($jobId)) {
                Cache::forget($this->cacheKey($jobId));
            }
            $request->session()->forget(self::SESSION_KEY);
            $state = null;
        }

        return view('feeder-initialization.index', [
            'settings' => $settings,
            'job' => $state === null ? null : $this->publicState($state),
        ]);
    }

    public function start(Request $request, LegacyFeederService $feeder): JsonResponse
    {
        $this->authorizeAdmin($request->user());
        $validated = $request->validate([
            'url' => ['required', 'string', 'max:200'],
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'max:100'],
        ]);
        $url = trim($validated['url']);
        $parts = parse_url($url);
        if (! is_array($parts)
            || ! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            || ! isset($parts['host'])
            || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return response()->json(['message' => 'URL WebService Feeder harus berupa alamat HTTP atau HTTPS yang valid.'], 422);
        }

        try {
            $token = $feeder->testInitializationConnection($url, trim($validated['username']), $validated['password']);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return $this->createJob(
            $request,
            $url,
            trim($validated['username']),
            $validated['password'],
            $token,
        );
    }

    private function createJob(
        Request $request,
        string $url,
        string $username,
        string $password,
        string $token,
    ): JsonResponse {
        $previousJobId = $request->session()->get(self::SESSION_KEY);
        if (is_string($previousJobId) && Str::isUuid($previousJobId)) {
            Cache::forget($this->cacheKey($previousJobId));
        }

        $jobId = (string) Str::uuid();
        $state = [
            'job_id' => $jobId,
            'owner_id' => (string) $request->user()->getAuthIdentifier(),
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
            'url_encrypted' => Crypt::encryptString($url),
            'username_encrypted' => Crypt::encryptString($username),
            'password_encrypted' => Crypt::encryptString($password),
            'token_encrypted' => Crypt::encryptString($token),
            'last_batch' => 'Koneksi Feeder berhasil diuji. Anda dapat melanjutkan ke tahap Master Referensi.',
            'created_at' => now()->toIso8601String(),
        ];
        Cache::put($this->cacheKey($jobId), $state, now()->addMinutes(self::CACHE_TTL_MINUTES));
        $request->session()->put(self::SESSION_KEY, $jobId);

        return response()->json($this->publicState($state), 201);
    }

    public function status(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request->user());
        $state = $this->currentJob($request);

        return response()->json($this->publicState($state));
    }

    public function next(Request $request, FeederInitializationService $initialization): JsonResponse
    {
        $this->authorizeAdmin($request->user());
        $state = $this->currentJob($request);
        $lock = Cache::lock('feeder-initialization-lock:'.$state['job_id'], 40);
        if (! $lock->get()) {
            return response()->json(['message' => 'Tahap Feeder sedang diproses. Tunggu sampai batch selesai sebelum melanjutkan.'], 409);
        }

        try {
            $freshState = $this->currentJob($request);
            $updated = $initialization->nextStage($freshState);
            Cache::put($this->cacheKey($updated['job_id']), $updated, now()->addMinutes(self::CACHE_TTL_MINUTES));

            return response()->json($this->publicState($updated));
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                ...$this->publicState($state),
            ], 422);
        } finally {
            $lock->release();
        }
    }

    public function activateStage(Request $request, FeederInitializationService $initialization): JsonResponse
    {
        $this->authorizeAdmin($request->user());
        $validated = $request->validate([
            'stage' => ['required', 'string', 'in:connection,references,university,programs,lecturers,students,courses,grades,universities,all_programs,calendar,student_grades'],
        ]);
        $state = $this->currentJob($request);
        $lock = Cache::lock('feeder-initialization-lock:'.$state['job_id'], 40);
        if (! $lock->get()) {
            return response()->json(['message' => 'Tahap Feeder sedang diproses. Tunggu sampai batch selesai sebelum berpindah tahap.'], 409);
        }

        try {
            $updated = $initialization->activateStage($this->currentJob($request), $validated['stage']);
            Cache::put($this->cacheKey($updated['job_id']), $updated, now()->addMinutes(self::CACHE_TTL_MINUTES));

            return response()->json($this->publicState($updated));
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                ...$this->publicState($state),
            ], 422);
        } finally {
            $lock->release();
        }
    }

    public function restartStage(Request $request, FeederInitializationService $initialization): JsonResponse
    {
        $this->authorizeAdmin($request->user());
        $state = $this->currentJob($request);
        $lock = Cache::lock('feeder-initialization-lock:'.$state['job_id'], 40);
        if (! $lock->get()) {
            return response()->json(['message' => 'Tahap Feeder sedang diproses. Tunggu sampai batch selesai sebelum mengulang.'], 409);
        }

        try {
            $updated = $initialization->restartCurrentStage($this->currentJob($request));
            Cache::put($this->cacheKey($updated['job_id']), $updated, now()->addMinutes(self::CACHE_TTL_MINUTES));

            return response()->json($this->publicState($updated));
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                ...$this->publicState($state),
            ], 422);
        } finally {
            $lock->release();
        }
    }

    public function step(
        Request $request,
        FeederInitializationService $initialization,
        LegacyFeederService $feeder,
    ): JsonResponse {
        $this->authorizeAdmin($request->user());
        $state = $this->currentJob($request);

        $lock = Cache::lock('feeder-initialization-lock:'.$state['job_id'], 40);
        if (! $lock->get()) {
            return response()->json(['message' => 'Batch lain sedang diproses. Tunggu sebentar lalu coba lagi.'], 409);
        }

        try {
            $freshState = $this->currentJob($request);
            $updated = $initialization->processBatch($freshState, $feeder);
            Cache::put($this->cacheKey($updated['job_id']), $updated, now()->addMinutes(self::CACHE_TTL_MINUTES));

            return response()->json($this->publicState($updated));
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                ...$this->publicState($state),
            ], 422);
        } finally {
            $lock->release();
        }
    }

    private function currentJob(Request $request): array
    {
        $jobId = $request->session()->get(self::SESSION_KEY);
        abort_unless(is_string($jobId) && Str::isUuid($jobId), 404, 'Sesi inisialisasi Feeder tidak ditemukan.');
        $state = Cache::get($this->cacheKey($jobId));
        abort_unless(
            is_array($state)
                && $this->ownsJob($request, $state)
                && in_array((int) ($state['wizard_version'] ?? 0), [2, 3], true),
            404,
            'Sesi inisialisasi Feeder sudah kedaluwarsa atau tidak tersedia.',
        );
        $state = $this->upgradeJobState($state);
        Cache::put($this->cacheKey($state['job_id']), $state, now()->addMinutes(self::CACHE_TTL_MINUTES));

        return $state;
    }

    private function upgradeJobState(array $state): array
    {
        if ((int) ($state['wizard_version'] ?? 0) === 2) {
            if (($state['stage'] ?? null) === 'complete') {
                $state['stage'] = 'student_grades';
                $state['stage_complete'] = false;
                $state['completed_stages'] = array_values(array_diff(
                    app(FeederInitializationService::class)->completedStages($state),
                    ['student_grades', 'complete'],
                ));
                $state['stage_records'] = 0;
                $state['grade_student_offset'] = 0;
                $state['grade_page_offset'] = 0;
                $state['grade_student_total'] = null;
                $state['student_grades_created'] = 0;
                $state['student_grades_updated'] = 0;
                $state['student_grades_skipped'] = 0;
                $state['student_approvals_created'] = 0;
            }
            $state['wizard_version'] = 3;
        }

        return $state;
    }

    private function ownsJob(Request $request, array $state): bool
    {
        return hash_equals(
            (string) ($state['owner_id'] ?? ''),
            (string) $request->user()->getAuthIdentifier(),
        );
    }

    private function publicState(array $state): array
    {
        $connection = DB::connection('simak_main');
        $stage = (string) ($state['stage'] ?? '');
        $facultyCount = $stage === 'university'
            && Schema::connection('simak_main')->hasTable('s1m4k_masterfakultas')
            ? $connection->table('s1m4k_masterfakultas')->count()
            : 0;
        $unassignedPrograms = $stage === 'programs'
            && Schema::connection('simak_main')->hasTable('s1m4k_masterprodi')
            ? app(FeederInitializationService::class)->unassignedPrograms()
            : 0;
        $stageComplete = ($state['stage_complete'] ?? false) === true;
        $readyForNext = match ($stage) {
            'connection' => ($state['connection_verified'] ?? false) === true,
            'university' => $stageComplete && $facultyCount > 0,
            'programs' => $stageComplete && $unassignedPrograms === 0,
            default => $stageComplete,
        };

        return [
            'job_id' => $state['job_id'],
            'stage' => $stage,
            'stage_label' => app(FeederInitializationService::class)->stageLabel($state),
            'completed_stages' => app(FeederInitializationService::class)->completedStages($state),
            'progress' => (int) $state['progress'],
            'records' => (int) $state['records'],
            'stage_records' => (int) ($state['stage_records'] ?? 0),
            'batch_duration_ms' => (int) ($state['batch_duration_ms'] ?? 0),
            'last_batch' => $state['last_batch'] ?? '',
            'history_not_found' => (int) ($state['history_not_found'] ?? 0),
            'connection_verified' => ($state['connection_verified'] ?? false) === true,
            'stage_complete' => $stageComplete,
            'faculty_count' => $facultyCount,
            'unassigned_programs' => $unassignedPrograms,
            'ready_for_next' => $readyForNext,
            'complete' => $stage === 'complete',
        ];
    }

    private function cacheKey(string $jobId): string
    {
        return 'feeder-initialization:'.$jobId;
    }

    private function authorizeAdmin(?object $user): void
    {
        abort_unless($user?->role === 'admin', 403);
    }
}
