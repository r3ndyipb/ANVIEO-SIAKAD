<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Services\FeederExportService;
use App\Services\LegacyFeederService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class FeederExportController extends Controller
{
    public function index(Request $request, FeederExportService $exportService): View
    {
        $user = $request->user();
        $this->authorizeRole($user);

        $programId = $user->role === 'usertu'
            ? trim((string) $user->id_prodi)
            : trim((string) $request->query('prodi', ''));
        if ($user->role === 'usertu') {
            abort_if($programId === '', 403, 'Akun Operator Prodi belum memiliki scope program studi.');
        }

        $connection = DB::connection('simak_main');
        $programs = $user->role === 'admin'
            ? $connection->table('s1m4k_masterprodi')
                ->orderBy('nama_program_studi')->get(['id_prodi', 'nama_program_studi', 'nama_jenjang_pendidikan'])
            : collect();

        if ($programId !== '') {
            abort_unless($connection->table('s1m4k_masterprodi')->where('id_prodi', $programId)->exists(), 404);
        }
        $program = $programId === ''
            ? null
            : $connection->table('s1m4k_masterprodi')
                ->where('id_prodi', $programId)
                ->first(['id_prodi', 'nama_program_studi', 'nama_jenjang_pendidikan']);

        $calendars = $programId === ''
            ? collect()
            : $connection->table('s1m4k_kalender')
                ->where('id_prodi', $programId)->orderByDesc('tahun')
                ->get(['id_kalender', 'tahun', 'periode', 'namatahun']);
        $curricula = $connection->table('s1m4k_kurikulum as kur')
            ->leftJoin('s1m4k_masterprodi as prodi', 'kur.id_prodi', '=', 'prodi.id_prodi')
            ->where('kur.id_prodi', $programId)
            ->orderByDesc('kur.id_semester')
            ->paginate(25, [
                'kur.id_kurikulum', 'kur.nama_kurikulum', 'kur.id_prodi', 'kur.id_semester',
                'kur.semester_mulai_berlaku', 'kur.jumlah_sks_lulus', 'kur.jumlah_sks_wajib',
                'kur.jumlah_sks_pilihan', 'kur.jumlah_sks_mata_kuliah_wajib',
                'kur.jumlah_sks_mata_kuliah_pilihan', 'prodi.nama_program_studi',
                'prodi.nama_jenjang_pendidikan',
            ])
            ->withQueryString();
        $curriculumOptions = $programId === ''
            ? collect()
            : $connection->table('s1m4k_kurikulum')
                ->where('id_prodi', $programId)
                ->orderBy('nama_kurikulum')
                ->get(['id_kurikulum', 'nama_kurikulum', 'id_semester']);

        return view('feeder-export.index', [
            'programId' => $programId,
            'program' => $program,
            'programs' => $programs,
            'calendars' => $calendars,
            'curricula' => $curricula,
            'curriculumOptions' => $curriculumOptions,
            'selectedCurriculum' => null,
            'exports' => collect(),
            'counts' => [],
            'types' => $exportService->types(),
            'reportPage' => false,
        ]);
    }

    public function reports(
        Request $request,
        string $curriculum,
        FeederExportService $exportService,
    ): View {
        $user = $request->user();
        $this->authorizeRole($user);
        $connection = DB::connection('simak_main');
        $selectedCurriculum = $connection->table('s1m4k_kurikulum')
            ->where('id_kurikulum', $curriculum)->first();
        abort_if($selectedCurriculum === null, 404);

        $programId = trim((string) $selectedCurriculum->id_prodi);
        if ($user->role === 'usertu') {
            abort_unless($programId !== '' && $programId === trim((string) $user->id_prodi), 403);
        }
        $program = $connection->table('s1m4k_masterprodi')
            ->where('id_prodi', $programId)
            ->first(['id_prodi', 'nama_program_studi', 'nama_jenjang_pendidikan']);
        abort_if($program === null, 404);

        $calendars = $connection->table('s1m4k_kalender')
            ->where('id_prodi', $programId)->orderByDesc('tahun')
            ->get(['id_kalender', 'tahun', 'periode', 'namatahun']);
        $exports = $connection->table('s1m4k_export')
            ->where('id_kurikulum', $selectedCurriculum->id_kurikulum)
            ->where('id_prodi', $programId)
            ->orderByRaw("CASE data WHEN 'MatkulKurikulum' THEN 1 WHEN 'KelasKuliah' THEN 2 WHEN 'PesertaKelasKuliah' THEN 3 WHEN 'DosenPengajarKelasKuliah' THEN 4 WHEN 'NilaiPerkuliahanKelas' THEN 5 WHEN 'PerkuliahanMahasiswa' THEN 6 WHEN 'MahasiswaLulusDO' THEN 7 WHEN 'DosenPembimbing' THEN 8 ELSE 9 END")
            ->paginate(25)
            ->withQueryString();
        $calendar = $exports->isNotEmpty()
            ? $connection->table('s1m4k_kalender')
                ->where('id_kalender', $exports->first()->id_kalender)
                ->where('id_prodi', $programId)->first(['id_kalender', 'tahun'])
            : $connection->table('s1m4k_kalender')
                ->where('id_prodi', $programId)
                ->where('tahun', $selectedCurriculum->id_semester)
                ->orderByDesc('status')->first(['id_kalender', 'tahun']);
        $counts = $calendar === null ? [] : $exportService->counts(
            $programId,
            $calendar->id_kalender,
            $calendar->tahun,
            $exports->getCollection()->pluck('data')->all(),
        );
        $detailCounts = $exportService->detailCounts($exports->getCollection()->pluck('id_export')->all());

        return view('feeder-export.index', [
            'programId' => $programId,
            'program' => $program,
            'programs' => collect(),
            'calendars' => $calendars,
            'curricula' => collect(),
            'curriculumOptions' => collect(),
            'selectedCurriculum' => $selectedCurriculum,
            'exports' => $exports,
            'counts' => $counts,
            'detailCounts' => $detailCounts,
            'types' => $exportService->types(),
            'reportPage' => true,
        ]);
    }

    public function storeCurriculum(
        Request $request,
        FeederExportService $exports,
        LegacyFeederService $feeder,
        ActivityLogger $activityLogger,
    ): JsonResponse {
        $user = $request->user();
        $this->authorizeRole($user);
        $programId = $this->programId($request, $user);
        $validated = $request->validate([
            'calendar_id' => ['required', 'string', 'max:100'],
        ]);
        $calendar = DB::connection('simak_main')->table('s1m4k_kalender')
            ->where('id_kalender', $validated['calendar_id'])
            ->where('id_prodi', $programId)->first();
        abort_if($calendar === null, 404, 'Kalender akademik tidak ditemukan pada program studi ini.');

        try {
            $curriculumId = $exports->createCurriculum($programId, $calendar, $feeder);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
        $activityLogger->record($user, 'Export ke Feeder', 'Menambahkan kurikulum di Feeder', [
            'id_prodi' => $programId,
            'id_kalender' => (string) $calendar->id_kalender,
        ], $curriculumId);

        return response()->json([
            'message' => 'Kurikulum berhasil disimpan ke Feeder dan daftar ekspor telah disiapkan.',
            'redirect' => route('feeder-export.index', ['prodi' => $programId]),
        ]);
    }

    public function updateCurriculum(
        Request $request,
        string $curriculum,
        FeederExportService $exports,
        LegacyFeederService $feeder,
        ActivityLogger $activityLogger,
    ): JsonResponse {
        $user = $request->user();
        $this->authorizeRole($user);
        $programId = $this->programId($request, $user);
        $validated = $request->validate([
            'calendar_id' => ['required', 'string', 'max:100'],
            'admin_sync' => ['nullable', 'boolean'],
        ]);
        if ($request->boolean('admin_sync')) {
            abort_unless($user->role === 'admin', 403);
        }
        $connection = DB::connection('simak_main');
        abort_unless($connection->table('s1m4k_kurikulum')
            ->where('id_kurikulum', $curriculum)->where('id_prodi', $programId)->exists(), 404);
        $calendar = $connection->table('s1m4k_kalender')
            ->where('id_kalender', $validated['calendar_id'])
            ->where('id_prodi', $programId)->first();
        abort_if($calendar === null, 404, 'Kalender akademik tidak ditemukan pada program studi ini.');

        try {
            $exports->updateCurriculum($curriculum, $programId, $calendar, $feeder);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
        $activityLogger->record($user, 'Export ke Feeder', 'Memperbarui kurikulum di Feeder', [
            'id_prodi' => $programId,
            'id_kalender' => (string) $calendar->id_kalender,
        ], $curriculum);

        return response()->json(['message' => 'Data kurikulum berhasil diperbarui di Feeder.']);
    }

    public function startJob(
        Request $request,
        FeederExportService $exports,
        LegacyFeederService $feeder,
        ActivityLogger $activityLogger,
    ): JsonResponse {
        $user = $request->user();
        $this->authorizeRole($user);
        $validated = $request->validate(['export_id' => ['required', 'string', 'max:100']]);
        $export = DB::connection('simak_main')->table('s1m4k_export')
            ->where('id_export', $validated['export_id'])->first();
        abort_if($export === null, 404);
        $this->authorizeExport($export, $user);
        $this->validateExportReferences($export);

        try {
            $result = $exports->start($export, $user, $feeder);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
        if ($result['complete'] ?? false) {
            $activityLogger->record($user, 'Export ke Feeder', 'Mencatat aktivitas dosen pembimbing realtime', [
                'id_prodi' => (string) $export->id_prodi,
                'status' => 'Y',
            ], (string) $export->id_export);
        }

        return response()->json($result, ($result['empty'] ?? false) ? 422 : 200);
    }

    public function step(
        Request $request,
        string $job,
        FeederExportService $exports,
        LegacyFeederService $feeder,
        ActivityLogger $activityLogger,
    ): JsonResponse {
        $this->authorizeRole($request->user());
        try {
            $result = $exports->step($job, $request->user(), $feeder);
            if ($result['just_completed'] ?? false) {
                $activityLogger->record($request->user(), 'Export ke Feeder', 'Menyelesaikan ekspor data', [
                    'jenis_data' => $result['type'],
                    'jumlah_data' => $result['total'],
                    'berhasil' => $result['success_count'],
                    'gagal' => $result['failure_count'],
                ], (string) $result['export_id']);
            }

            return response()->json($result);
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json(['message' => $exception->getMessage()], 502);
        }
    }

    public function status(Request $request, string $job, FeederExportService $exports): JsonResponse
    {
        $this->authorizeRole($request->user());

        return response()->json($exports->status($job, $request->user()));
    }

    public function retry(Request $request, string $job, FeederExportService $exports): JsonResponse
    {
        $this->authorizeRole($request->user());
        try {
            return response()->json($exports->retry($job, $request->user()));
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    private function validateExportReferences(object $export): void
    {
        $connection = DB::connection('simak_main');
        abort_unless($connection->table('s1m4k_kurikulum')
            ->where('id_kurikulum', $export->id_kurikulum)
            ->where('id_prodi', $export->id_prodi)->exists(), 404);
        abort_unless($connection->table('s1m4k_kalender')
            ->where('id_kalender', $export->id_kalender)
            ->where('id_prodi', $export->id_prodi)
            ->where('tahun', $export->id_semester)->exists(), 404);
    }

    private function authorizeExport(object $export, object $user): void
    {
        abort_unless(
            $user->role === 'admin'
                || ((string) $user->id_prodi !== '' && (string) $user->id_prodi === (string) $export->id_prodi),
            403,
        );
    }

    private function programId(Request $request, object $user): string
    {
        $programId = $user->role === 'usertu'
            ? trim((string) $user->id_prodi)
            : trim((string) $request->input('program_id', ''));
        abort_if($programId === '', 422, 'Pilih program studi terlebih dahulu.');
        abort_unless(DB::connection('simak_main')->table('s1m4k_masterprodi')
            ->where('id_prodi', $programId)->exists(), 404);

        return $programId;
    }

    private function authorizeRole(?object $user): void
    {
        abort_unless(in_array($user?->role, ['admin', 'usertu'], true), 403);
    }
}
