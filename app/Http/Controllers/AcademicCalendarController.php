<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Services\LegacyFeederService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class AcademicCalendarController extends Controller
{
    private const VIEW_ROLES = ['admin', 'pimuniv', 'pimfak', 'userprodi', 'usertu'];

    private const DATE_FIELDS = [
        'tgl_mulai_krs',
        'tgl_akhir_krs',
        'tgl_mulai_isinilai',
        'tgl_akhir_isinilai',
    ];

    public function index(Request $request): View
    {
        $user = $request->user();
        $this->ensureRole($user, self::VIEW_ROLES);
        $isShortSemester = $this->isShortSemester($request);

        $query = DB::connection('simak_main')
            ->table('s1m4k_kalender as kalender')
            ->leftJoin('s1m4k_masterprodi as prodi', 'kalender.id_prodi', '=', 'prodi.id_prodi')
            ->select([
                'kalender.id_kalender',
                'kalender.id_perguruan_tinggi',
                'kalender.id_fakultas',
                'kalender.id_prodi',
                'kalender.periode',
                'kalender.tahun',
                'kalender.namatahun',
                'kalender.tgl_mulai_krs',
                'kalender.tgl_akhir_krs',
                'kalender.tgl_mulai_isinilai',
                'kalender.tgl_akhir_isinilai',
                $isShortSemester ? 'kalender.status_sp as status' : 'kalender.status',
                'prodi.nama_program_studi',
                'prodi.nama_jenjang_pendidikan',
            ]);
        $this->constrainCalendarType($query, $isShortSemester, 'kalender.periode');

        if ($user->role === 'pimfak') {
            $facultyId = data_get($user, 'id_fakultas');

            if (! is_string($facultyId) || trim($facultyId) === '') {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('kalender.id_fakultas', trim($facultyId));
            }
        }

        if (in_array($user->role, ['userprodi', 'usertu'], true)) {
            $programId = data_get($user, 'id_prodi');

            if (! is_string($programId) || trim($programId) === '') {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('kalender.id_prodi', trim($programId));
            }
        }

        $calendars = $query
            ->orderByDesc('kalender.tahun')
            ->orderBy('kalender.id_prodi')
            ->orderBy('kalender.id_kalender')
            ->limit(500)
            ->get();

        return view('academic-calendars.index', [
            'calendars' => $calendars,
            'canCreate' => $user->role === 'admin',
            'canSync' => $isShortSemester && $user->role === 'admin',
            'canActivate' => $user->role === 'admin',
            'canEdit' => in_array($user->role, ['admin', 'userprodi'], true),
            'role' => $user->role,
            'periodOptions' => $this->periodOptions($isShortSemester),
            'isShortSemester' => $isShortSemester,
            'calendarLabel' => $isShortSemester ? 'Kurikulum SP' : 'Kalender Akademik',
            'routePrefix' => $this->calendarRoutePrefix($isShortSemester),
        ]);
    }

    public function syncShortSemester(Request $request, LegacyFeederService $feeder, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->ensureRole($request->user(), ['admin']);

        try {
            $rows = $feeder->shortSemesterCurricula();
            $result = DB::connection('simak_main')->transaction(function () use ($rows): array {
                $connection = DB::connection('simak_main');
                $counts = [
                    'curricula_created' => 0,
                    'curricula_updated' => 0,
                    'calendars_created' => 0,
                    'calendars_updated' => 0,
                    'skipped' => 0,
                ];
                $seenCurricula = [];

                foreach ($rows as $row) {
                    if (! is_array($row)) {
                        throw new RuntimeException('Respons daftar kurikulum Feeder berisi format data yang tidak valid.');
                    }

                    $curriculumId = $this->requiredFeederField($row, 'id_kurikulum', 50);
                    $programId = $this->requiredFeederField($row, 'id_prodi', 50);
                    $semesterId = $this->requiredFeederField($row, 'id_semester', 5);
                    $period = $this->requiredFeederField($row, 'semester_mulai_berlaku', 50);
                    if (! $this->isShortSemesterPeriod($period)) {
                        $counts['skipped']++;

                        continue;
                    }
                    if (mb_strlen($period) > 20) {
                        throw new RuntimeException("Periode '{$period}' melebihi panjang kolom periode kalender SIMAK.");
                    }

                    if (isset($seenCurricula[$curriculumId])) {
                        throw new RuntimeException('Feeder mengirim ID kurikulum SP yang sama lebih dari satu kali.');
                    }
                    $seenCurricula[$curriculumId] = true;

                    $program = $connection->table('s1m4k_masterprodi')
                        ->where('id_prodi', $programId)
                        ->first(['id_prodi', 'id_perguruan_tinggi', 'id_fakultas']);
                    if ($program === null) {
                        $counts['skipped']++;

                        continue;
                    }

                    $curriculumValues = [
                        'nama_kurikulum' => $this->optionalFeederField($row, 'nama_kurikulum', 100),
                        'id_prodi' => $programId,
                        'id_semester' => $semesterId,
                        'semester_mulai_berlaku' => $period,
                        'jumlah_sks_lulus' => $this->optionalFeederField($row, 'jumlah_sks_lulus', 10),
                        'jumlah_sks_wajib' => $this->optionalFeederField($row, 'jumlah_sks_wajib', 10),
                        'jumlah_sks_pilihan' => $this->optionalFeederField($row, 'jumlah_sks_pilihan', 10),
                    ];
                    $curriculumExists = $connection->table('s1m4k_kurikulum')
                        ->where('id_kurikulum', $curriculumId)
                        ->exists();
                    $connection->table('s1m4k_kurikulum')->updateOrInsert(
                        ['id_kurikulum' => $curriculumId],
                        $curriculumValues
                    );

                    $calendar = $connection->table('s1m4k_kalender')
                        ->where('id_kalender', $curriculumId)
                        ->where('periode', $period)
                        ->where('id_prodi', $programId)
                        ->where('tahun', $semesterId)
                        ->lockForUpdate()
                        ->first(['id_kalender']);

                    $calendarValues = [
                        'id_perguruan_tinggi' => $program->id_perguruan_tinggi,
                        'id_fakultas' => $program->id_fakultas,
                        'id_prodi' => $programId,
                        'periode' => $period,
                        'tahun' => $semesterId,
                        'namatahun' => $period.' - SP',
                    ];
                    if ($calendar !== null) {
                        $connection->table('s1m4k_kalender')
                            ->where('id_kalender', $curriculumId)
                            ->update($calendarValues);
                        $counts['calendars_updated']++;
                    } else {
                        $conflictingCalendar = $connection->table('s1m4k_kalender')
                            ->where('id_kalender', $curriculumId)
                            ->lockForUpdate()
                            ->first(['id_prodi', 'periode', 'tahun']);
                        if ($conflictingCalendar !== null) {
                            throw new RuntimeException(
                                "ID kurikulum Feeder {$curriculumId} sudah dipakai kalender dengan prodi, periode, atau kode tahun berbeda; periksa data SIMAK sebelum sinkronisasi."
                            );
                        }

                        $connection->table('s1m4k_kalender')->insert([
                            'id_kalender' => $curriculumId,
                            ...$calendarValues,
                            'status' => 'N',
                            'status_sp' => 'N',
                        ]);
                        $counts['calendars_created']++;
                    }

                    if ($curriculumExists) {
                        $counts['curricula_updated']++;
                    } else {
                        $counts['curricula_created']++;
                    }
                }

                return $counts;
            });
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->withErrors(['feeder' => $exception->getMessage()]);
        } catch (QueryException $exception) {
            report($exception);

            return back()->withErrors([
                'feeder' => 'Sinkronisasi gagal karena struktur atau data tabel SIMAK tidak sesuai. Periksa tabel s1m4k_kurikulum dan kolom status_sp pada s1m4k_kalender.',
            ]);
        }

        $activityLogger->record($request->user(), 'Kurikulum SP', 'Sinkronisasi Kurikulum SP dari Feeder', [
            'kurikulum_baru' => $result['curricula_created'],
            'kurikulum_diperbarui' => $result['curricula_updated'],
            'kalender_baru' => $result['calendars_created'],
            'kalender_diperbarui' => $result['calendars_updated'],
            'data_dilewati' => $result['skipped'],
        ]);
        Log::info('Short-semester curriculum sync completed', [
            'actor_id' => data_get($request->user(), 'legacy_id'),
            'actor_role' => $request->user()->role,
            ...$result,
        ]);

        if (array_sum($result) === 0) {
            return redirect()->route('short-semester-curricula.index')->with(
                'status',
                'Feeder tidak mengembalikan data kurikulum Semester Pendek untuk disinkronkan.'
            );
        }

        return redirect()->route('short-semester-curricula.index')->with(
            'status',
            "Sinkronisasi selesai: kurikulum {$result['curricula_created']} baru/{$result['curricula_updated']} diperbarui; kalender SP {$result['calendars_created']} baru/{$result['calendars_updated']} diperbarui; {$result['skipped']} data dilewati."
        );
    }

    public function create(Request $request): View
    {
        $this->ensureRole($request->user(), ['admin']);
        $isShortSemester = $this->isShortSemester($request);

        return view('academic-calendars.create', [
            'periodOptions' => $this->periodOptions($isShortSemester),
            'isShortSemester' => $isShortSemester,
            'calendarLabel' => $isShortSemester ? 'Kurikulum SP' : 'Kalender Akademik',
            'routePrefix' => $this->calendarRoutePrefix($isShortSemester),
        ]);
    }

    public function store(
        Request $request,
        ActivityLogger $activityLogger,
        LegacyFeederService $feeder
    ): RedirectResponse|JsonResponse {
        $user = $request->user();
        $this->ensureRole($user, ['admin']);
        $isShortSemester = $this->isShortSemester($request);

        $periodOptions = $this->periodOptions($isShortSemester);
        $rules = [
            'periode' => ['required', 'string', Rule::in(array_keys($periodOptions))],
            ...array_fill_keys(self::DATE_FIELDS, ['required', 'date_format:Y-m-d']),
        ];
        if ($isShortSemester) {
            $rules['program_offset'] = ['sometimes', 'integer', 'min:0'];
        }
        $validated = $request->validate($rules);

        $periodData = $periodOptions[$validated['periode']];
        if ($isShortSemester) {
            return $this->storeShortSemesterCalendars($request, $feeder, $activityLogger, $validated, $periodData);
        }

        $calendarName = 'Tahun Akademik '.$validated['periode'];
        $createdIds = DB::connection('simak_main')->transaction(function () use ($validated, $periodData, $calendarName, $activityLogger, $user) {
            $programs = DB::connection('simak_main')
                ->table('s1m4k_masterprodi')
                ->where('status', 'A')
                ->select(['id_prodi', 'id_fakultas', 'id_perguruan_tinggi'])
                ->lockForUpdate()
                ->get();

            $created = [];

            foreach ($programs as $program) {
                $existingCalendarQuery = DB::connection('simak_main')
                    ->table('s1m4k_kalender')
                    ->where('tahun', $periodData['year'])
                    ->where('id_prodi', $program->id_prodi);
                $this->constrainCalendarType($existingCalendarQuery, false);

                if ($existingCalendarQuery->exists()) {
                    continue;
                }

                $calendarId = (string) Str::uuid();
                DB::connection('simak_main')->table('s1m4k_kalender')->insert([
                    'id_kalender' => $calendarId,
                    'id_perguruan_tinggi' => $program->id_perguruan_tinggi,
                    'id_fakultas' => $program->id_fakultas,
                    'id_prodi' => $program->id_prodi,
                    'periode' => $validated['periode'],
                    'tahun' => $periodData['year'],
                    'namatahun' => $calendarName,
                    ...array_intersect_key($validated, array_flip(self::DATE_FIELDS)),
                ]);

                $created[] = $calendarId;
            }

            if ($created !== []) {
                $moduleName = 'Kalender Akademik';
                $activityLogger->record($user, $moduleName, 'Tambah '.$moduleName, [
                    'tahun' => $periodData['year'],
                    'periode' => $validated['periode'],
                    'kalender_baru' => array_map(static fn (string $id): array => [
                        'id_kalender' => $id,
                        'namatahun' => $calendarName,
                        ...array_intersect_key($validated, array_flip(self::DATE_FIELDS)),
                    ], $created),
                ], null, [
                    'jenis' => $moduleName,
                    'nama' => $calendarName,
                    'jumlah_kalender' => count($created),
                ]);
            }

            return $created;
        });

        $createdCount = count($createdIds);
        Log::info('Academic calendars created', [
            'actor_id' => data_get($user, 'legacy_id'),
            'actor_role' => $user->role,
            'period' => $validated['periode'],
            'short_semester' => $isShortSemester,
            'created_count' => $createdCount,
        ]);

        $indexRoute = $this->calendarIndexRoute($request);
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $createdCount > 0
                    ? "{$createdCount} kalender akademik berhasil dibuat."
                    : 'Tidak ada kalender baru. Semua prodi aktif sudah memiliki periode tersebut.',
                'calendars' => $this->calendarPayloads($createdIds, false),
            ]);
        }

        return redirect()->route($indexRoute)->with(
            'status',
            $createdCount > 0
                ? "{$createdCount} kalender akademik berhasil dibuat."
                : 'Tidak ada kalender baru. Semua prodi aktif sudah memiliki periode tersebut.',
        );
    }

    private function storeShortSemesterCalendars(
        Request $request,
        LegacyFeederService $feeder,
        ActivityLogger $activityLogger,
        array $validated,
        array $periodData
    ): RedirectResponse|JsonResponse {
        $user = $request->user();
        $connection = DB::connection('simak_main');
        $programQuery = $connection->table('s1m4k_masterprodi')
            ->where('status', 'A')
            ->orderBy('id_prodi');
        $totalPrograms = (clone $programQuery)->count();
        $programOffset = (int) ($validated['program_offset'] ?? 0);
        $programs = $programQuery
            ->offset($programOffset)
            ->limit(1)
            ->get(['id_prodi', 'id_fakultas', 'id_perguruan_tinggi']);
        $processedPrograms = min($programOffset + $programs->count(), $totalPrograms);
        $progress = [
            'processed' => $processedPrograms,
            'total' => $totalPrograms,
            'complete' => $processedPrograms >= $totalPrograms,
        ];
        if ($programs->isEmpty()) {
            $message = $totalPrograms === 0
                ? 'Tidak ada program studi aktif untuk dibuatkan periode SP.'
                : 'Semua program studi aktif sudah diproses.';

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'calendars' => [],
                    'counts' => [
                        'curricula_created' => 0,
                        'curricula_updated' => 0,
                        'calendars_created' => 0,
                        'calendars_updated' => 0,
                    ],
                    'progress' => $progress,
                ]);
            }

            return redirect()->route('short-semester-curricula.index')->with('status', $message);
        }
        $programsById = $programs->keyBy('id_prodi');
        $semesterId = $periodData['year'];
        $startYear = (int) substr($semesterId, 0, 4);
        $feederPeriod = $startYear.'/'.($startYear + 1).' Pendek';
        $curriculumName = 'SEMESTER PENDEK '.($startYear + 1);
        $dateValues = array_intersect_key($validated, array_flip(self::DATE_FIELDS));

        try {
            $feederRows = $feeder->ensureShortSemesterCurricula(
                $programs->pluck('id_prodi')->all(),
                $semesterId,
                $feederPeriod,
                $curriculumName
            );
            $result = $connection->transaction(function () use (
                $connection,
                $feederRows,
                $programsById,
                $semesterId,
                $periodData,
                $dateValues,
                $activityLogger,
                $user
            ): array {
                $counts = [
                    'curricula_created' => 0,
                    'curricula_updated' => 0,
                    'calendars_created' => 0,
                    'calendars_updated' => 0,
                ];
                $calendarIds = [];
                $seenCurricula = [];

                foreach ($feederRows as $row) {
                    if (! is_array($row)) {
                        throw new RuntimeException('Respons kurikulum SP Feeder berisi format data yang tidak valid.');
                    }

                    $curriculumId = $this->requiredFeederField($row, 'id_kurikulum', 50);
                    $programId = $this->requiredFeederField($row, 'id_prodi', 50);
                    $rowSemesterId = $this->requiredFeederField($row, 'id_semester', 5);
                    $period = $this->requiredFeederField($row, 'semester_mulai_berlaku', 50);
                    if ($rowSemesterId !== $semesterId || ! $programsById->has($programId)) {
                        throw new RuntimeException("Kurikulum Feeder {$curriculumId} tidak cocok dengan semester yang dipilih atau program studi aktif.");
                    }
                    if (! $this->isShortSemesterPeriod($period) || mb_strlen($period) > 20) {
                        throw new RuntimeException("Periode '{$period}' dari kurikulum Feeder tidak valid untuk kalender SP.");
                    }
                    if (isset($seenCurricula[$curriculumId])) {
                        throw new RuntimeException('Feeder mengirim ID kurikulum SP yang sama lebih dari satu kali.');
                    }
                    $seenCurricula[$curriculumId] = true;

                    $program = $programsById->get($programId);
                    $curriculum = $connection->table('s1m4k_kurikulum')
                        ->where('id_kurikulum', $curriculumId)
                        ->first(['id_prodi', 'id_semester']);
                    if ($curriculum !== null
                        && ($curriculum->id_prodi !== $programId || $curriculum->id_semester !== $semesterId)) {
                        throw new RuntimeException("ID kurikulum {$curriculumId} sudah terdaftar untuk prodi atau kode semester berbeda di SIMAK.");
                    }

                    $curriculumValues = [
                        'nama_kurikulum' => $this->optionalFeederField($row, 'nama_kurikulum', 100),
                        'id_prodi' => $programId,
                        'id_semester' => $semesterId,
                        'semester_mulai_berlaku' => $period,
                        'jumlah_sks_lulus' => $this->optionalFeederField($row, 'jumlah_sks_lulus', 10),
                        'jumlah_sks_wajib' => $this->optionalFeederField($row, 'jumlah_sks_wajib', 10),
                        'jumlah_sks_pilihan' => $this->optionalFeederField($row, 'jumlah_sks_pilihan', 10),
                    ];
                    $curriculumExists = $curriculum !== null;
                    $connection->table('s1m4k_kurikulum')->updateOrInsert(
                        ['id_kurikulum' => $curriculumId],
                        $curriculumValues
                    );
                    $counts[$curriculumExists ? 'curricula_updated' : 'curricula_created']++;

                    $calendar = $connection->table('s1m4k_kalender')
                        ->where('id_kalender', $curriculumId)
                        ->lockForUpdate()
                        ->first(['id_prodi', 'periode', 'tahun']);
                    if ($calendar !== null
                        && ($calendar->id_prodi !== $programId
                            || $calendar->periode !== $period
                            || $calendar->tahun !== $semesterId)) {
                        throw new RuntimeException("ID kalender {$curriculumId} sudah digunakan untuk prodi, periode, atau kode semester berbeda.");
                    }

                    $calendarValues = [
                        'id_perguruan_tinggi' => $program->id_perguruan_tinggi,
                        'id_fakultas' => $program->id_fakultas,
                        'id_prodi' => $programId,
                        'periode' => $period,
                        'tahun' => $semesterId,
                        'namatahun' => $periodData['label'],
                        ...$dateValues,
                    ];
                    if ($calendar !== null) {
                        $connection->table('s1m4k_kalender')
                            ->where('id_kalender', $curriculumId)
                            ->update($calendarValues);
                        $counts['calendars_updated']++;
                    } else {
                        $connection->table('s1m4k_kalender')->insert([
                            'id_kalender' => $curriculumId,
                            ...$calendarValues,
                            'status' => 'N',
                            'status_sp' => 'N',
                        ]);
                        $counts['calendars_created']++;
                    }
                    $calendarIds[] = $curriculumId;
                }

                if ($calendarIds !== []) {
                    $activityLogger->record($user, 'Kurikulum SP', 'Buat Periode SP dari Feeder', [
                        'id_semester' => $semesterId,
                        'kurikulum_baru' => $counts['curricula_created'],
                        'kurikulum_diperbarui' => $counts['curricula_updated'],
                        'kalender_baru' => $counts['calendars_created'],
                        'kalender_diperbarui' => $counts['calendars_updated'],
                        'kalender' => array_map(static fn (string $id): array => [
                            'id_kalender' => $id,
                            ...$dateValues,
                        ], $calendarIds),
                    ], null, [
                        'jenis' => 'Kurikulum SP',
                        'nama' => $periodData['label'],
                        'jumlah_kurikulum' => count($calendarIds),
                    ]);
                }

                return [...$counts, 'calendar_ids' => $calendarIds];
            });
        } catch (RuntimeException $exception) {
            report($exception);

            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }

            return back()->withErrors(['feeder' => $exception->getMessage()]);
        } catch (QueryException $exception) {
            report($exception);
            $message = 'Pembuatan periode SP gagal karena struktur atau data tabel SIMAK tidak sesuai. Periksa tabel kurikulum dan kolom status_sp pada kalender.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 500);
            }

            return back()->withErrors(['feeder' => $message]);
        }

        $message = "Periode SP diproses: kurikulum {$result['curricula_created']} baru/{$result['curricula_updated']} diperbarui; kalender SP {$result['calendars_created']} baru/{$result['calendars_updated']} diperbarui.";
        Log::info('Short-semester calendars created from Feeder', [
            'actor_id' => data_get($user, 'legacy_id'),
            'actor_role' => $user->role,
            'semester_id' => $semesterId,
            ...$result,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'calendars' => $this->calendarPayloads($result['calendar_ids'], true),
                'counts' => [
                    'curricula_created' => $result['curricula_created'],
                    'curricula_updated' => $result['curricula_updated'],
                    'calendars_created' => $result['calendars_created'],
                    'calendars_updated' => $result['calendars_updated'],
                ],
                'progress' => $progress,
            ]);
        }

        return redirect()->route('short-semester-curricula.index')->with('status', $message);
    }

    public function edit(Request $request, string $calendar): View
    {
        $user = $request->user();
        $this->ensureRole($user, ['admin', 'userprodi']);
        $isShortSemester = $this->isShortSemester($request);

        $query = DB::connection('simak_main')->table('s1m4k_kalender')->where('id_kalender', $calendar);
        $this->constrainCalendarType($query, $isShortSemester);

        if ($user->role === 'userprodi') {
            $programId = data_get($user, 'id_prodi');
            abort_unless(is_string($programId) && trim($programId) !== '', 403);
            $query->where('id_prodi', trim($programId));
        }

        $record = $query->first();
        abort_if($record === null, 404);

        return view('academic-calendars.edit', [
            'calendar' => $record,
            'isShortSemester' => $isShortSemester,
            'calendarLabel' => $isShortSemester ? 'Kurikulum SP' : 'Kalender Akademik',
            'routePrefix' => $this->calendarRoutePrefix($isShortSemester),
        ]);
    }

    public function update(Request $request, string $calendar, ActivityLogger $activityLogger): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $this->ensureRole($user, ['admin', 'userprodi']);
        $isShortSemester = $this->isShortSemester($request);

        $validated = $request->validate(array_fill_keys(self::DATE_FIELDS, ['required', 'date_format:Y-m-d']));
        $query = DB::connection('simak_main')->table('s1m4k_kalender')->where('id_kalender', $calendar);
        $this->constrainCalendarType($query, $isShortSemester);

        if ($user->role === 'userprodi') {
            $programId = data_get($user, 'id_prodi');
            abort_unless(is_string($programId) && trim($programId) !== '', 403);
            $query->where('id_prodi', trim($programId));
        }

        $current = $query->first();
        abort_if($current === null, 404);
        $moduleName = $isShortSemester ? 'Kurikulum SP' : 'Kalender Akademik';
        DB::connection('simak_main')->transaction(function () use ($query, $validated, $current, $activityLogger, $user, $calendar, $moduleName, $isShortSemester): void {
            $query->update($validated);
            $activityLogger->record(
                $user,
                $moduleName,
                'Ubah tanggal '.$moduleName,
                $activityLogger->diff(array_intersect_key((array) $current, $validated), $validated),
                $calendar,
                $this->calendarIdentity($current, $isShortSemester)
            );
        });

        Log::info('Academic calendar dates updated', [
            'actor_id' => data_get($user, 'legacy_id'),
            'actor_role' => $user->role,
            'calendar_id' => $calendar,
            'short_semester' => $isShortSemester,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Tanggal '.($isShortSemester ? 'kalender SP' : 'kalender akademik').' berhasil diperbarui.',
                'calendar' => $this->calendarPayloads([$calendar], $isShortSemester)[0],
            ]);
        }

        return redirect()->route($this->calendarIndexRoute($request))->with(
            'status',
            'Tanggal '.($isShortSemester ? 'kalender SP' : 'kalender akademik').' berhasil diperbarui.'
        );
    }

    public function activate(Request $request, string $calendar, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->changeStatus($request, $calendar, true, $activityLogger);

        return redirect()->route($this->calendarIndexRoute($request))->with(
            'status',
            ($this->isShortSemester($request) ? 'Kalender SP' : 'Kalender akademik').' berhasil diaktifkan.'
        );
    }

    public function setStatus(Request $request, string $calendar, ActivityLogger $activityLogger): JsonResponse
    {
        $this->ensureRole($request->user(), ['admin']);
        $validated = $request->validate(['active' => ['required', 'boolean']]);
        $statuses = $this->changeStatus($request, $calendar, (bool) $validated['active'], $activityLogger);
        $isShortSemester = $this->isShortSemester($request);

        return response()->json([
            'message' => $validated['active']
                ? ($isShortSemester ? 'Kalender SP' : 'Kalender akademik').' berhasil diaktifkan. Kalender lain pada prodi yang sama dinonaktifkan.'
                : 'Kalender pada prodi yang sama berhasil dinonaktifkan.',
            'statuses' => $statuses,
        ]);
    }

    private function changeStatus(Request $request, string $calendar, bool $active, ActivityLogger $activityLogger): array
    {
        $user = $request->user();
        $this->ensureRole($user, ['admin']);
        $isShortSemester = $this->isShortSemester($request);
        $statusColumn = $isShortSemester ? 'status_sp' : 'status';
        $moduleName = $isShortSemester ? 'Kurikulum SP' : 'Kalender Akademik';
        $statuses = DB::connection('simak_main')->transaction(function () use ($calendar, $active, $activityLogger, $user, $isShortSemester, $statusColumn, $moduleName) {
            $connection = DB::connection('simak_main');
            $recordQuery = $connection
                ->table('s1m4k_kalender')
                ->where('id_kalender', $calendar);
            $this->constrainCalendarType($recordQuery, $isShortSemester);
            $record = $recordQuery->first(['id_kalender', 'id_prodi', 'tahun', 'namatahun']);
            abort_if($record === null, 404);

            $programCalendarsQuery = $connection
                ->table('s1m4k_kalender')
                ->where('id_prodi', $record->id_prodi)
                ->orderBy('id_kalender');
            $this->constrainCalendarType($programCalendarsQuery, $isShortSemester);
            $programCalendars = $programCalendarsQuery
                ->lockForUpdate()
                ->get(['id_kalender', $statusColumn.' as status']);

            $programCalendarsToUpdate = $connection
                ->table('s1m4k_kalender')
                ->where('id_prodi', $record->id_prodi);
            $this->constrainCalendarType($programCalendarsToUpdate, $isShortSemester);
            $programCalendarsToUpdate->update([$statusColumn => 'N']);

            if ($active) {
                $connection->table('s1m4k_kalender')
                    ->where('id_kalender', $record->id_kalender)
                    ->update([$statusColumn => 'Y']);
            }

            $statuses = $programCalendars->map(fn ($item) => [
                'id' => $item->id_kalender,
                'active' => $active && $item->id_kalender === $record->id_kalender,
            ])->all();
            $activityLogger->record($user, $moduleName, $active
                ? 'Aktifkan '.$moduleName
                : 'Nonaktifkan '.$moduleName, [
                    'status_kalender' => $programCalendars->map(fn ($item): array => [
                        'id_kalender' => $item->id_kalender,
                        'sebelum' => $item->status,
                        'sesudah' => $active && $item->id_kalender === $record->id_kalender ? 'Y' : 'N',
                    ])->all(),
                ], $calendar, $this->calendarIdentity($record, $isShortSemester));

            return $statuses;
        });

        Log::info($active ? 'Academic calendar activated' : 'Academic calendar deactivated', [
            'actor_id' => data_get($user, 'legacy_id'),
            'actor_role' => $user->role,
            'calendar_id' => $calendar,
            'short_semester' => $isShortSemester,
            'program_id' => DB::connection('simak_main')
                ->table('s1m4k_kalender')
                ->where('id_kalender', $calendar)
                ->value('id_prodi'),
        ]);

        return $statuses;
    }

    private function calendarIdentity(object $calendar, bool $isShortSemester = false): array
    {
        $programName = DB::connection('simak_main')->table('s1m4k_masterprodi')
            ->where('id_prodi', $calendar->id_prodi)
            ->value('nama_program_studi');

        return array_filter([
            'jenis' => $isShortSemester ? 'Kurikulum SP' : 'Kalender Akademik',
            'nama' => $calendar->namatahun ?? (isset($calendar->tahun) ? 'Tahun Akademik '.$calendar->tahun : null),
            'program_studi' => $programName,
        ], static fn (mixed $value): bool => is_string($value) && trim($value) !== '');
    }

    private function calendarPayloads(array $ids, bool $isShortSemester = false): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::connection('simak_main')
            ->table('s1m4k_kalender as kalender')
            ->leftJoin('s1m4k_masterprodi as prodi', 'kalender.id_prodi', '=', 'prodi.id_prodi')
            ->whereIn('kalender.id_kalender', $ids)
            ->select([
                'kalender.id_kalender',
                'kalender.id_prodi',
                'kalender.periode',
                'kalender.tahun',
                'kalender.namatahun',
                'kalender.tgl_mulai_krs',
                'kalender.tgl_akhir_krs',
                'kalender.tgl_mulai_isinilai',
                'kalender.tgl_akhir_isinilai',
                $isShortSemester ? 'kalender.status_sp as status' : 'kalender.status',
                'prodi.nama_program_studi',
                'prodi.nama_jenjang_pendidikan',
            ])
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    private function ensureRole(mixed $user, array $roles): void
    {
        abort_unless($user !== null && in_array(data_get($user, 'role'), $roles, true), 403);
    }

    private function periodOptions(bool $isShortSemester = false): array
    {
        $currentYear = now()->year;
        $options = [];

        $startYears = $isShortSemester
            ? [$currentYear - 1]
            : [$currentYear - 1, $currentYear];

        foreach ($startYears as $startYear) {
            $periodYears = $startYear.'/'.($startYear + 1);

            $semesters = $isShortSemester
                ? ['SP' => '3']
                : ['Ganjil' => '1', 'Genap' => '2'];
            foreach ($semesters as $semester => $suffix) {
                $period = $periodYears.' '.$semester;
                $options[$period] = [
                    'year' => $startYear.$suffix,
                    'label' => $isShortSemester
                        ? "SP {$startYear} / ".($startYear + 1).' (Periode SP Tahun '.($startYear + 1).')'
                        : $period,
                ];
            }
        }

        return $options;
    }

    private function isShortSemester(Request $request): bool
    {
        return (bool) $request->route('shortSemester', false);
    }

    private function constrainCalendarType($query, bool $isShortSemester, string $periodColumn = 'periode'): void
    {
        if ($isShortSemester) {
            $query->where(function ($periodQuery) use ($periodColumn): void {
                $periodQuery->where($periodColumn, 'like', '% SP')
                    ->orWhere($periodColumn, 'like', '%Pendek');
            });

            return;
        }

        $query->where(function ($periodQuery) use ($periodColumn): void {
            $periodQuery->whereNull($periodColumn)
                ->orWhere(function ($regularPeriodQuery) use ($periodColumn): void {
                    $regularPeriodQuery->where($periodColumn, 'not like', '% SP')
                        ->where($periodColumn, 'not like', '%Pendek');
                });
        });
    }

    private function isShortSemesterPeriod(string $period): bool
    {
        $period = mb_strtolower(trim($period));

        return str_ends_with($period, ' sp') || str_ends_with($period, 'pendek');
    }

    private function requiredFeederField(array $row, string $field, int $maxLength): string
    {
        $value = $row[$field] ?? null;
        if (! is_string($value) && ! is_int($value)) {
            throw new RuntimeException("Respons Feeder tidak memuat nilai valid untuk {$field}.");
        }
        $value = trim((string) $value);
        if ($value === '' || mb_strlen($value) > $maxLength) {
            throw new RuntimeException("Nilai {$field} dari Feeder kosong atau melebihi batas kolom SIMAK.");
        }

        return $value;
    }

    private function optionalFeederField(array $row, string $field, int $maxLength): ?string
    {
        $value = $row[$field] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            throw new RuntimeException("Respons Feeder tidak memuat nilai valid untuk {$field}.");
        }
        $value = trim((string) $value);
        if (mb_strlen($value) > $maxLength) {
            throw new RuntimeException("Nilai {$field} dari Feeder melebihi batas kolom SIMAK.");
        }

        return $value === '' ? null : $value;
    }

    private function calendarRoutePrefix(bool $isShortSemester): string
    {
        return $isShortSemester ? 'short-semester-curricula' : 'academic-calendars';
    }

    private function calendarIndexRoute(Request $request): string
    {
        return $this->calendarRoutePrefix($this->isShortSemester($request)).'.index';
    }
}
