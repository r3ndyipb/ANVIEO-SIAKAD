<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Services\ShortSemesterScheduleFeederService;
use App\Support\AcademicCalendarScope;
use App\Support\LecturerName;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClassScheduleController extends Controller
{
    private const READ_ROLES = ['admin', 'pimuniv', 'pimfak', 'userprodi', 'usertu', 'dosen', 'asdos', 'mahasiswa'];

    private const WRITE_ROLES = ['admin', 'userprodi', 'usertu'];

    private const DAYS = ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'ahad'];

    private const TEACHER_FIELDS = [
        'dosen1', 'dosen2', 'dosen3', 'dosen4', 'dosen5', 'dosen6', 'dosen7',
        'dosen8', 'dosen9', 'dosen10', 'dosen11', 'dosen12', 'dosen13', 'dosen14',
    ];

    private const TEACHER_SCHEDULE_FIELDS = [
        'dosen1', 'dosen2', 'dosen3', 'dosen4', 'dosen5', 'dosen6', 'dosen7',
    ];

    private const TEACHER_PROGRAM_FIELDS = [
        'dosen1', 'dosen2', 'dosen3', 'dosen4', 'dosen5', 'dosen6', 'dosen7',
        'dosen8', 'dosen9',
    ];

    private const COPY_FIELDS = [
        'id_perguruan_tinggi', 'id_fakultas', 'id_prodi', 'id_matkul',
        'dosen1', 'dosen2', 'dosen3', 'dosen4', 'dosen5', 'dosen6', 'dosen7',
        'dosen8', 'dosen9', 'dosen10', 'dosen11', 'dosen12', 'dosen13', 'dosen14',
        'id_ruang', 'nama_kelas', 'hari', 'jammulai', 'jamselesai',
        'kampus_merdeka', 'lingkup_km', 'mode_km', 'tgl_tutup_daftar_km',
    ];

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless(in_array($user->role, self::READ_ROLES, true), 403);

        $programs = $this->availablePrograms($user);
        $programId = $this->requestedProgram($request, $user, $programs);
        $calendars = $programId === null ? collect() : $this->calendarOptions($programId);
        if (in_array($user->role, ['dosen', 'asdos', 'mahasiswa'], true) && $programId !== null) {
            $calendars = $calendars->filter(
                fn (object $calendar): bool => $this->hasScheduleData($user, $programId, (string) $calendar->id_kalender)
            )->values();
        }
        $calendarId = $this->requestedCalendar($request, $programId, $calendars, $user);
        $schedules = $programId === null || $calendarId === null
            ? collect()
            : $this->scheduleRows(
                $user,
                $programId,
                $calendarId,
                in_array($user->role, ['admin', 'userprodi', 'usertu'], true)
            );

        return view('class-schedules.index', [
            'role' => $user->role,
            'programs' => $programs,
            'selectedProgram' => $programId,
            'calendars' => $calendars,
            'selectedCalendar' => $calendarId,
            'selectedCalendarStatus' => $calendars->firstWhere('id_kalender', $calendarId)?->status,
            'schedules' => $schedules,
            'canManage' => in_array($user->role, self::WRITE_ROLES, true),
            'canChooseProgram' => in_array($user->role, ['admin', 'pimuniv', 'pimfak', 'dosen', 'asdos'], true),
            'canChooseCalendar' => in_array($user->role, ['admin', 'pimuniv', 'pimfak', 'userprodi', 'usertu', 'dosen', 'asdos', 'mahasiswa'], true),
            'showTeachers' => $user->role !== 'mahasiswa',
            'canPrint' => in_array($user->role, ['admin', 'userprodi', 'usertu'], true),
            'studentSchedule' => $user->role === 'mahasiswa',
        ]);
    }

    public function shortSemesterIndex(Request $request): View
    {
        $user = $request->user();
        abort_unless(in_array($user->role, self::READ_ROLES, true), 403);

        $programs = $this->availablePrograms($user);
        $programId = $this->requestedProgram($request, $user, $programs);
        $calendars = $programId === null ? collect() : $this->shortSemesterCalendarOptions($programId);
        if (in_array($user->role, ['dosen', 'asdos', 'mahasiswa'], true) && $programId !== null) {
            $calendars = $calendars->filter(
                fn (object $calendar): bool => $this->hasScheduleData($user, $programId, (string) $calendar->id_kalender)
            )->values();
        }
        $calendarId = $this->requestedCalendar($request, $programId, $calendars, $user);
        $schedules = $programId === null || $calendarId === null
            ? collect()
            : $this->scheduleRows(
                $user,
                $programId,
                $calendarId,
                in_array($user->role, ['admin', 'userprodi', 'usertu'], true)
            );
        $scheduleRows = $schedules instanceof LengthAwarePaginator ? $schedules->getCollection() : $schedules;
        if ($scheduleRows->isNotEmpty()) {
            $participantCounts = DB::connection('simak_main')->table('s1m4k_krs')
                ->whereIn('id_jadwal', $scheduleRows->pluck('id_jadwal'))
                ->selectRaw('id_jadwal, COUNT(*) as participant_count')
                ->groupBy('id_jadwal')
                ->pluck('participant_count', 'id_jadwal');
            $statuses = DB::connection('simak_main')->table('s1m4k_jadwal_pengajar_feeder as sync')
                ->leftJoin('s1m4k_masterdosen as lecturer', 'sync.id_dosen', '=', 'lecturer.id_dosen')
                ->whereIn('sync.id_jadwal', $scheduleRows->pluck('id_jadwal'))
                ->get([
                    'sync.id_jadwal', 'sync.id_dosen', 'sync.status', 'sync.error_desc',
                    'lecturer.nama_dosen', 'lecturer.gelar_depan', 'lecturer.gelar_belakang',
                ])
                ->groupBy('id_jadwal');
            foreach ($scheduleRows as $schedule) {
                $schedule->feeder_teacher_statuses = $statuses->get((string) $schedule->id_jadwal, collect());
                $schedule->participant_count = (int) $participantCounts->get((string) $schedule->id_jadwal, 0);
            }
        }

        return view('class-schedules.index', [
            'role' => $user->role,
            'programs' => $programs,
            'selectedProgram' => $programId,
            'calendars' => $calendars,
            'selectedCalendar' => $calendarId,
            'selectedCalendarStatus' => $calendars->firstWhere('id_kalender', $calendarId)?->status,
            'schedules' => $schedules,
            'canManage' => in_array($user->role, self::WRITE_ROLES, true),
            'canChooseProgram' => in_array($user->role, ['admin', 'pimuniv', 'pimfak', 'dosen', 'asdos'], true),
            'canChooseCalendar' => in_array($user->role, ['admin', 'pimuniv', 'pimfak', 'userprodi', 'usertu', 'dosen', 'asdos', 'mahasiswa'], true),
            'showTeachers' => $user->role !== 'mahasiswa',
            'canPrint' => false,
            'studentSchedule' => $user->role === 'mahasiswa',
            'shortSemester' => true,
            'scheduleRoutes' => [
                'index' => 'short-semester-schedules.index',
                'create' => 'short-semester-schedules.create',
            ],
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $this->authorizeWrite($user);
        $programs = $this->availablePrograms($user);
        $programId = $this->requestedProgram($request, $user, $programs);

        if ($programId === null) {
            return redirect()->route('class-schedules.index')
                ->withErrors(['id_prodi' => 'Pilih program studi terlebih dahulu.']);
        }

        $calendar = $this->activeCalendar($programId);
        if ($calendar === null) {
            return redirect()->route('class-schedules.index', ['id_prodi' => $programId])
                ->withErrors(['calendar' => 'Tidak ada kalender aktif untuk program studi ini.']);
        }

        return view('class-schedules.form', [
            ...$this->formOptions($programId, $calendar),
            'schedule' => null,
            'hasKrs' => false,
            'canChooseProgram' => false,
            'programs' => $user->role === 'admin' ? $programs : [],
            'programLabel' => $programs[$programId] ?? $programId,
            'classNameMaxLength' => in_array($user->role, ['userprodi', 'usertu'], true) ? 5 : 50,
        ]);
    }

    public function createShortSemester(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $this->authorizeWrite($user);
        $programs = $this->availablePrograms($user);
        $programId = $this->requestedProgram($request, $user, $programs);

        if ($programId === null) {
            return redirect()->route('short-semester-schedules.index')
                ->withErrors(['id_prodi' => 'Pilih program studi terlebih dahulu.']);
        }

        $calendar = $this->activeShortSemesterCalendar($programId);
        if ($calendar === null) {
            return redirect()->route('short-semester-schedules.index', ['id_prodi' => $programId])
                ->withErrors(['calendar' => 'Tidak ada kalender SP aktif untuk program studi ini.']);
        }

        return view('class-schedules.form', [
            ...$this->formOptions($programId, $calendar, true),
            'schedule' => null,
            'hasKrs' => false,
            'canChooseProgram' => false,
            'programs' => $user->role === 'admin' ? $programs : [],
            'programLabel' => $programs[$programId] ?? $programId,
            'classNameMaxLength' => 5,
            'shortSemester' => true,
            'scheduleRoutes' => [
                'index' => 'short-semester-schedules.index',
                'store' => 'short-semester-schedules.store',
            ],
        ]);
    }

    public function store(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeWrite($user);
        $programs = $this->availablePrograms($user);
        $programId = $this->requestedProgram($request, $user, $programs, true);
        $calendar = $this->activeCalendar($programId);
        abort_if($calendar === null, 422, 'Tidak ada kalender aktif untuk program studi ini.');
        $validated = $this->validateSchedule($request, $programId, $calendar, false, $user->role);
        $this->assertUniqueClass($programId, $calendar->id_kalender, $validated['id_matkul'], $validated['nama_kelas']);

        $connection = DB::connection('simak_main');
        $program = $this->program($programId);
        $scheduleId = (string) Str::uuid();
        $connection->transaction(function () use ($connection, $program, $calendar, $validated, $scheduleId, $activityLogger, $user, $programId): void {
            $connection->table('s1m4k_jadwal')->insert([
                'id_jadwal' => $scheduleId,
                'id_perguruan_tinggi' => $program->id_perguruan_tinggi,
                'id_fakultas' => $program->id_fakultas,
                'id_prodi' => $program->id_prodi,
                'id_kalender' => $calendar->id_kalender,
                ...$validated,
                'jml_mahasiswa' => 0,
                'input_nilai' => 'N',
            ]);
            $activityLogger->record($user, 'Jadwal Kuliah', 'Tambah jadwal', [
                'data_baru' => [
                    'id_prodi' => $programId,
                    'id_kalender' => $calendar->id_kalender,
                    ...$validated,
                ],
            ], $scheduleId, $this->scheduleIdentity($validated, $programId));
        });

        Log::info('SIMAK class schedule created.', [
            'actor_id' => (string) $user->legacy_id,
            'program_id' => $programId,
            'calendar_id' => (string) $calendar->id_kalender,
        ]);

        return $this->backToSchedule($programId, (string) $calendar->id_kalender)
            ->with('status', 'Jadwal kuliah berhasil ditambahkan.');
    }

    public function storeShortSemester(
        Request $request,
        ActivityLogger $activityLogger,
        ShortSemesterScheduleFeederService $feeder
    ): RedirectResponse|JsonResponse {
        $user = $request->user();
        $this->authorizeWrite($user);
        $programs = $this->availablePrograms($user);
        $programId = $this->requestedProgram($request, $user, $programs, true);
        $calendar = $this->activeShortSemesterCalendar($programId);
        abort_if($calendar === null, 422, 'Tidak ada kalender SP aktif untuk program studi ini.');
        $validated = $this->validateSchedule($request, $programId, $calendar, false, $user->role, true);
        $this->assertUniqueClass($programId, $calendar->id_kalender, $validated['id_matkul'], $validated['nama_kelas']);

        $connection = DB::connection('simak_main');
        $program = $this->program($programId);
        $course = $connection->table('s1m4k_mastermk')
            ->where('id_matkul', $validated['id_matkul'])
            ->where('id_prodi', $programId)
            ->where('status', 'A')
            ->first(['sks_mata_kuliah']);
        abort_if($course === null, 422, 'Mata kuliah tidak aktif atau tidak tersedia pada program studi ini.');
        if (! is_numeric($course->sks_mata_kuliah) || (float) $course->sks_mata_kuliah <= 0) {
            throw ValidationException::withMessages([
                'id_matkul' => 'Jumlah SKS mata kuliah belum tersedia atau tidak valid pada data SIMAK.',
            ]);
        }
        $roomCapacity = $connection->table('s1m4k_masterruang')
            ->where('id_ruang', $validated['id_ruang'])
            ->where('id_prodi', $programId)
            ->value('kapasitas');
        $teacherIds = collect(self::TEACHER_FIELDS)
            ->map(fn (string $field): string => trim((string) ($validated[$field] ?? '')))
            ->filter()
            ->unique()
            ->values();
        $lecturers = $connection->table('s1m4k_masterdosen')
            ->whereIn('id_dosen', $teacherIds)
            ->get(['id_dosen', 'nidn'])
            ->keyBy('id_dosen');

        foreach ($teacherIds as $teacherId) {
            $lecturer = $lecturers->get($teacherId);
            if ($lecturer === null || trim((string) $lecturer->nidn) === '') {
                throw ValidationException::withMessages([
                    'dosen1' => 'Setiap dosen pengampu SP harus memiliki NIDN untuk dikirim ke Feeder.',
                ]);
            }
        }

        if (! Schema::connection('simak_main')->hasTable('s1m4k_jadwal_pengajar_feeder')) {
            $message = 'Jadwal SP belum dapat disimpan karena tabel status pengiriman dosen belum tersedia. Jalankan SQL dari database/manual/s1m4k_jadwal_pengajar_feeder.sql terlebih dahulu, lalu coba kembali.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 503);
            }

            return back()->withErrors(['schedule' => $message])->withInput();
        }

        try {
            $classId = $feeder->insertClass([
                'id_prodi' => $programId,
                'id_semester' => (string) $calendar->tahun,
                'id_matkul' => $validated['id_matkul'],
                'nama_kelas_kuliah' => $validated['nama_kelas'],
                'bahasan' => '',
                'tanggal_mulai_efektif' => $this->feederDate($calendar->tgl_mulai_krs),
                'tanggal_akhir_efektif' => $this->feederDate($calendar->tgl_akhir_isinilai),
                'apa_untuk_pditt' => $validated['kampus_merdeka'],
                'kapasitas' => $roomCapacity,
                'lingkup' => $validated['lingkup_km'] !== '' ? $validated['lingkup_km'] : null,
                'mode' => $validated['mode_km'] !== '' ? $validated['mode_km'] : null,
            ]);
        } catch (\RuntimeException $exception) {
            report($exception);

            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }

            return back()->withErrors(['feeder' => $exception->getMessage()])->withInput();
        }

        $scheduleId = (string) Str::uuid();
        $realizationBase = intdiv(16, $teacherIds->count());
        $realizationRemainder = 16 % $teacherIds->count();
        try {
            $connection->transaction(function () use (
                $connection, $program, $calendar, $validated, $scheduleId, $classId,
                $teacherIds, $activityLogger, $user, $programId, $realizationBase, $realizationRemainder
            ): void {
                $connection->table('s1m4k_jadwal')->insert([
                    'id_jadwal' => $scheduleId,
                    'id_perguruan_tinggi' => $program->id_perguruan_tinggi,
                    'id_fakultas' => $program->id_fakultas,
                    'id_prodi' => $program->id_prodi,
                    'id_kalender' => $calendar->id_kalender,
                    'id_kelas_kuliah' => $classId,
                    ...$validated,
                    'jml_mahasiswa' => 0,
                    'input_nilai' => 'N',
                ]);

                foreach ($teacherIds->values() as $index => $teacherId) {
                    $realization = $realizationBase + ($index < $realizationRemainder ? 1 : 0);
                    $connection->table('s1m4k_akd')->insert([
                        'id_akd' => (string) Str::uuid(),
                        'id_jadwal' => $scheduleId,
                        'id_dosen' => $teacherId,
                        'rencana' => 16,
                        'realisasi' => $realization,
                        'ket' => 'dosen',
                        'id_kalender' => $calendar->id_kalender,
                        'tahun' => $calendar->tahun,
                        'id_prodi' => $programId,
                    ]);
                    $connection->table('s1m4k_jadwal_pengajar_feeder')->insert([
                        'id_jadwal' => $scheduleId,
                        'id_dosen' => $teacherId,
                        'status' => 'pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $activityLogger->record($user, 'Jadwal SP', 'Tambah jadwal', [
                    'data_baru' => [
                        'id_prodi' => $programId,
                        'id_kalender' => $calendar->id_kalender,
                        'id_kelas_kuliah' => $classId,
                        ...$validated,
                    ],
                ], $scheduleId, $this->scheduleIdentity($validated, $programId, true));
            });
        } catch (QueryException $exception) {
            report($exception);
            Log::error('Feeder class was created but SIMAK could not save the short-semester schedule.', [
                'feeder_class_id' => $classId,
                'program_id' => $programId,
                'calendar_id' => (string) $calendar->id_kalender,
            ]);
            $message = 'Kelas Feeder berhasil dibuat dengan ID '.$classId.', tetapi jadwal SIMAK gagal disimpan. Periksa kelas di Feeder sebelum mencoba kembali.';

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'feeder_class_id' => $classId,
                ], 500);
            }

            return back()->withErrors(['schedule' => $message])->withInput();
        }

        Log::info('SIMAK short-semester class schedule created.', [
            'actor_id' => (string) $user->legacy_id,
            'program_id' => $programId,
            'calendar_id' => (string) $calendar->id_kalender,
            'feeder_class_id' => $classId,
            'lecturer_count' => $teacherIds->count(),
        ]);

        $redirectUrl = route('short-semester-schedules.index', [
            'id_prodi' => $programId,
            'id_kalender' => $calendar->id_kalender,
        ]);
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Kelas Feeder dan jadwal SIMAK berhasil dibuat. Mengirim dosen pengampu ke Feeder.',
                'schedule_id' => $scheduleId,
                'teacher_ids' => $teacherIds,
                'redirect_url' => $redirectUrl,
            ], 201);
        }

        return redirect($redirectUrl)
            ->with('status', 'Jadwal SP tersimpan; dosen pengampu menunggu pengiriman ke Feeder.');
    }

    public function editShortSemester(Request $request, string $scheduleId): View
    {
        $user = $request->user();
        $this->authorizeWrite($user);
        $schedule = $this->shortSemesterScheduleForWrite($user, $scheduleId);
        $calendar = $this->activeShortSemesterCalendar((string) $schedule->id_prodi);
        abort_unless((string) ($calendar?->id_kalender ?? '') === (string) $schedule->id_kalender, 404);
        $hasKrs = DB::connection('simak_main')->table('s1m4k_krs')
            ->where('id_jadwal', $scheduleId)
            ->where('id_prodi', $schedule->id_prodi)
            ->exists();
        $programs = $this->availablePrograms($user);

        return view('class-schedules.form', [
            ...$this->formOptions((string) $schedule->id_prodi, $calendar, true),
            'schedule' => $schedule,
            'hasKrs' => $hasKrs,
            'canChooseProgram' => false,
            'programs' => [],
            'programLabel' => $programs[(string) $schedule->id_prodi] ?? (string) $schedule->id_prodi,
            'classNameMaxLength' => 5,
            'shortSemester' => true,
            'scheduleRoutes' => [
                'index' => 'short-semester-schedules.index',
                'update' => 'short-semester-schedules.update',
            ],
        ]);
    }

    public function updateShortSemester(
        Request $request,
        string $scheduleId,
        ActivityLogger $activityLogger,
        ShortSemesterScheduleFeederService $feeder
    ): RedirectResponse|JsonResponse {
        $user = $request->user();
        $this->authorizeWrite($user);
        $schedule = $this->shortSemesterScheduleForWrite($user, $scheduleId);
        $calendar = $this->activeShortSemesterCalendar((string) $schedule->id_prodi);
        abort_unless((string) ($calendar?->id_kalender ?? '') === (string) $schedule->id_kalender, 404);
        $programId = (string) $schedule->id_prodi;
        $connection = DB::connection('simak_main');
        $hasKrs = $connection->table('s1m4k_krs')
            ->where('id_jadwal', $scheduleId)
            ->where('id_prodi', $programId)
            ->exists();
        $validated = $this->validateSchedule($request, $programId, $calendar, $hasKrs, $user->role, true);
        $editableFields = $hasKrs
            ? [...self::TEACHER_FIELDS, 'nama_kelas', 'kampus_merdeka', 'lingkup_km', 'mode_km', 'tgl_tutup_daftar_km']
            : [...self::TEACHER_FIELDS, 'id_matkul', 'id_ruang', 'nama_kelas', 'hari', 'jammulai', 'jamselesai', 'kampus_merdeka', 'lingkup_km', 'mode_km', 'tgl_tutup_daftar_km'];
        $updates = array_intersect_key($validated, array_flip($editableFields));
        $finalSchedule = array_merge((array) $schedule, $updates);
        $this->assertUniqueClass(
            $programId,
            (string) $schedule->id_kalender,
            (string) $finalSchedule['id_matkul'],
            (string) $finalSchedule['nama_kelas'],
            $scheduleId
        );

        if (! Schema::connection('simak_main')->hasTable('s1m4k_jadwal_pengajar_feeder')) {
            return back()->withErrors([
                'schedule' => 'Jadwal SP belum dapat diperbarui karena tabel status pengiriman dosen belum tersedia. Jalankan SQL dari database/manual/s1m4k_jadwal_pengajar_feeder.sql terlebih dahulu.',
            ])->withInput();
        }

        $classId = trim((string) ($schedule->id_kelas_kuliah ?? ''));
        abort_if($classId === '', 422, 'ID kelas Feeder belum tersedia; jadwal SP ini tidak dapat diperbarui.');
        $teacherIds = collect(self::TEACHER_FIELDS)
            ->map(fn (string $field): string => trim((string) ($finalSchedule[$field] ?? '')))
            ->filter()
            ->unique()
            ->values();
        $lecturers = $connection->table('s1m4k_masterdosen')
            ->whereIn('id_dosen', $teacherIds)
            ->get(['id_dosen', 'nidn'])
            ->keyBy('id_dosen');
        foreach ($teacherIds as $teacherId) {
            $lecturer = $lecturers->get($teacherId);
            if ($lecturer === null || trim((string) $lecturer->nidn) === '') {
                throw ValidationException::withMessages([
                    'dosen1' => 'Setiap dosen pengampu SP harus memiliki NIDN untuk dikirim ke Feeder.',
                ]);
            }
        }

        $course = $connection->table('s1m4k_mastermk')
            ->where('id_matkul', $finalSchedule['id_matkul'])
            ->where('id_prodi', $programId)
            ->where('status', 'A')
            ->first(['sks_mata_kuliah']);
        abort_if($course === null || ! is_numeric($course->sks_mata_kuliah), 422, 'SKS mata kuliah tidak valid untuk dikirim ke Feeder.');
        $roomCapacity = $connection->table('s1m4k_masterruang')
            ->where('id_ruang', $finalSchedule['id_ruang'])
            ->where('id_prodi', $programId)
            ->value('kapasitas');
        $classRecord = [
            'id_prodi' => $programId,
            'id_semester' => (string) $calendar->tahun,
            'id_matkul' => $finalSchedule['id_matkul'],
            'nama_kelas_kuliah' => $finalSchedule['nama_kelas'],
            'bahasan' => '',
            'tanggal_mulai_efektif' => $this->feederDate($calendar->tgl_mulai_krs),
            'tanggal_akhir_efektif' => $this->feederDate($calendar->tgl_akhir_isinilai),
            'apa_untuk_pditt' => $finalSchedule['kampus_merdeka'],
            'kapasitas' => $roomCapacity,
            'lingkup' => $finalSchedule['lingkup_km'] !== '' ? $finalSchedule['lingkup_km'] : null,
            'mode' => $finalSchedule['mode_km'] !== '' ? $finalSchedule['mode_km'] : null,
        ];
        $realizations = $connection->table('s1m4k_akd')
            ->where('id_jadwal', $scheduleId)
            ->where('ket', 'dosen')
            ->pluck('realisasi', 'id_dosen');
        $teacherRecords = $teacherIds->map(fn (string $teacherId): array => [
            'nidn' => trim((string) $lecturers->get($teacherId)->nidn),
            'id_dosen' => $teacherId,
            'realisasi' => (int) ($realizations->get($teacherId, 8)),
            'record' => [
                'id_substansi' => '',
                'rencana_minggu_pertemuan' => 16,
                'realisasi_minggu_pertemuan' => (int) ($realizations->get($teacherId, 8)),
                'id_jenis_evaluasi' => '1',
            ],
        ])->all();

        try {
            $feeder->updateClass($classId, $classRecord);
            $feeder->synchronizeLecturers(
                $classId,
                $programId,
                $teacherRecords,
                (float) $course->sks_mata_kuliah
            );
        } catch (\RuntimeException $exception) {
            report($exception);
            Log::error('Feeder could not fully update the short-semester schedule; SIMAK was not changed.', [
                'schedule_id' => $scheduleId,
                'feeder_class_id' => $classId,
                'program_id' => $programId,
                'error' => $exception->getMessage(),
            ]);

            return back()->withErrors([
                'feeder' => 'Pembaruan Feeder belum selesai sehingga data SIMAK tidak diubah. Periksa kelas dan dosen pengampu di Feeder sebelum mencoba kembali. '.$exception->getMessage(),
            ])->withInput();
        }

        $scheduleIdentity = $this->scheduleIdentity($finalSchedule, $programId, true);
        $connection->transaction(function () use (
            $connection, $scheduleId, $programId, $updates, $teacherIds, $teacherRecords,
            $activityLogger, $user, $scheduleIdentity, $schedule, $calendar
        ): void {
            $connection->table('s1m4k_jadwal')
                ->where('id_jadwal', $scheduleId)
                ->where('id_prodi', $programId)
                ->update($updates);

            $connection->table('s1m4k_akd')
                ->where('id_jadwal', $scheduleId)
                ->where('ket', 'dosen')
                ->whereNotIn('id_dosen', $teacherIds)
                ->delete();
            foreach ($teacherRecords as $teacher) {
                $activity = $connection->table('s1m4k_akd')
                    ->where('id_jadwal', $scheduleId)
                    ->where('id_dosen', $teacher['id_dosen'])
                    ->where('ket', 'dosen')
                    ->first(['id_akd']);
                $activityValues = [
                    'rencana' => 16,
                    'realisasi' => $teacher['realisasi'],
                    'id_kalender' => $calendar->id_kalender,
                    'tahun' => $calendar->tahun,
                    'id_prodi' => $programId,
                ];
                if ($activity === null) {
                    $connection->table('s1m4k_akd')->insert([
                        'id_akd' => (string) Str::uuid(),
                        'id_jadwal' => $scheduleId,
                        'id_dosen' => $teacher['id_dosen'],
                        'ket' => 'dosen',
                        ...$activityValues,
                    ]);
                } else {
                    $connection->table('s1m4k_akd')
                        ->where('id_akd', $activity->id_akd)
                        ->update($activityValues);
                }
            }

            $connection->table('s1m4k_jadwal_pengajar_feeder')
                ->where('id_jadwal', $scheduleId)
                ->delete();
            foreach ($teacherIds as $teacherId) {
                $connection->table('s1m4k_jadwal_pengajar_feeder')->insert([
                    'id_jadwal' => $scheduleId,
                    'id_dosen' => $teacherId,
                    'status' => 'synced',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $activityLogger->record(
                $user,
                'Jadwal SP',
                'Ubah jadwal',
                $activityLogger->diff(
                    array_intersect_key((array) $schedule, $updates),
                    $updates
                ),
                $scheduleId,
                $scheduleIdentity
            );
        });

        Log::info('SIMAK short-semester schedule updated after Feeder synchronization.', [
            'actor_id' => (string) $user->legacy_id,
            'schedule_id' => $scheduleId,
            'feeder_class_id' => $classId,
            'program_id' => $programId,
        ]);

        $redirectUrl = route('short-semester-schedules.index', [
            'id_prodi' => $programId,
            'id_kalender' => $schedule->id_kalender,
        ]);
        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'updated',
                'message' => 'Jadwal dan dosen pengampu SP berhasil diperbarui di Feeder dan SIMAK.',
                'schedule_id' => $scheduleId,
                'teacher_ids' => $teacherIds->values()->all(),
                'redirect_url' => $redirectUrl,
            ], 200);
        }

        return redirect($redirectUrl)->with('status', 'Jadwal dan dosen pengampu SP berhasil diperbarui di Feeder dan SIMAK.');
    }

    public function destroyShortSemester(
        Request $request,
        string $scheduleId,
        ActivityLogger $activityLogger,
        ShortSemesterScheduleFeederService $feeder
    ): RedirectResponse|JsonResponse {
        $user = $request->user();
        $this->authorizeWrite($user);
        $schedule = $this->shortSemesterScheduleForWrite($user, $scheduleId);
        $connection = DB::connection('simak_main');
        if ($connection->table('s1m4k_krs')
            ->where('id_jadwal', $scheduleId)
            ->exists()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'rejected',
                    'message' => 'Jadwal belum dapat dihapus karena masih memiliki peserta KRS. Hapus peserta kelas satu per satu terlebih dahulu.',
                ], 409);
            }

            return redirect()->route('short-semester-schedules.index', [
                'id_prodi' => $schedule->id_prodi,
                'id_kalender' => $schedule->id_kalender,
            ])->withErrors(['schedule' => 'Jadwal tidak dapat dihapus karena sudah dipakai pada KRS.']);
        }

        $classId = trim((string) ($schedule->id_kelas_kuliah ?? ''));
        abort_if($classId === '', 422, 'ID kelas Feeder belum tersedia; jadwal SP ini tidak dapat dihapus.');

        $teacherIds = $connection->table('s1m4k_jadwal_pengajar_feeder')
            ->where('id_jadwal', $scheduleId)
            ->pluck('id_dosen')
            ->all();

        try {
            $feeder->deleteClass($classId);
        } catch (\RuntimeException $exception) {
            report($exception);
            Log::error('Feeder could not fully delete the short-semester schedule; SIMAK was not changed.', [
                'schedule_id' => $scheduleId,
                'feeder_class_id' => $classId,
                'program_id' => (string) $schedule->id_prodi,
                'error' => $exception->getMessage(),
            ]);

            return redirect()->route('short-semester-schedules.index', [
                'id_prodi' => $schedule->id_prodi,
                'id_kalender' => $schedule->id_kalender,
            ])->withErrors([
                'feeder' => 'Penghapusan di Feeder belum selesai sehingga data SIMAK tidak dihapus. Periksa kelas Feeder sebelum mencoba kembali. '.$exception->getMessage(),
            ]);
        }

        $connection->transaction(function () use ($connection, $schedule, $scheduleId, $activityLogger, $user): void {
            $connection->table('s1m4k_jadwal_pengajar_feeder')
                ->where('id_jadwal', $scheduleId)
                ->delete();
            $connection->table('s1m4k_akd')
                ->where('id_jadwal', $scheduleId)
                ->where('ket', 'dosen')
                ->delete();
            $connection->table('s1m4k_jadwal')
                ->where('id_jadwal', $scheduleId)
                ->where('id_prodi', $schedule->id_prodi)
                ->delete();
            $activityLogger->record(
                $user,
                'Jadwal SP',
                'Hapus jadwal',
                ['data_lama' => (array) $schedule],
                $scheduleId,
                $this->scheduleIdentity($schedule, (string) $schedule->id_prodi, true)
            );
        });

        Log::info('SIMAK short-semester schedule deleted after Feeder deletion.', [
            'actor_id' => (string) $user->legacy_id,
            'schedule_id' => $scheduleId,
            'feeder_class_id' => $classId,
            'program_id' => (string) $schedule->id_prodi,
        ]);

        $redirectUrl = route('short-semester-schedules.index', [
            'id_prodi' => $schedule->id_prodi,
            'id_kalender' => $schedule->id_kalender,
        ]);
        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'deleted',
                'message' => 'Dosen pengampu, kelas Feeder, dan jadwal SP berhasil dihapus.',
                'schedule_id' => $scheduleId,
                'teacher_ids' => $teacherIds,
                'redirect_url' => $redirectUrl,
            ], 200);
        }

        return redirect($redirectUrl)->with('status', 'Dosen pengampu, kelas Feeder, dan jadwal SP berhasil dihapus.');
    }

    public function syncShortSemesterTeacher(
        Request $request,
        string $scheduleId,
        string $teacherId,
        ShortSemesterScheduleFeederService $feeder
    ): RedirectResponse|JsonResponse {
        $user = $request->user();
        $this->authorizeWrite($user);
        $schedule = $this->scheduleForWrite($user, $scheduleId);
        abort_unless(
            (string) ($this->activeShortSemesterCalendar((string) $schedule->id_prodi)?->id_kalender ?? '') === (string) $schedule->id_kalender,
            403,
            'Pengiriman dosen hanya dapat dilakukan pada kalender SP aktif.'
        );
        $calendar = $this->shortSemesterCalendar($schedule->id_kalender, (string) $schedule->id_prodi);
        abort_if($calendar === null || trim((string) ($schedule->id_kelas_kuliah ?? '')) === '', 404);
        $sync = DB::connection('simak_main')->table('s1m4k_jadwal_pengajar_feeder')
            ->where('id_jadwal', $scheduleId)->where('id_dosen', $teacherId)->first();
        abort_if($sync === null, 404);

        if ($sync->status === 'synced') {
            return $request->expectsJson()
                ? response()->json(['status' => 'synced', 'message' => 'Dosen ini sudah tersimpan di Feeder.'])
                : redirect()->route('short-semester-schedules.index', [
                    'id_prodi' => $schedule->id_prodi,
                    'id_kalender' => $schedule->id_kalender,
                ])->with('status', 'Dosen pengampu sudah tersimpan di Feeder.');
        }
        if ($sync->status === 'processing') {
            $message = 'Pengiriman dosen ini masih diproses. Jangan kirim ulang untuk menghindari data ganda.';

            return $request->expectsJson()
                ? response()->json(['status' => 'processing', 'message' => $message], 409)
                : redirect()->route('short-semester-schedules.index', [
                    'id_prodi' => $schedule->id_prodi,
                    'id_kalender' => $schedule->id_kalender,
                ])->withErrors(['feeder' => $message]);
        }

        $lecturer = DB::connection('simak_main')->table('s1m4k_masterdosen')
            ->where('id_dosen', $teacherId)->first(['nidn']);
        if ($lecturer === null || trim((string) $lecturer->nidn) === '') {
            $message = 'NIDN dosen pengampu tidak tersedia di SIMAK.';
            DB::connection('simak_main')->table('s1m4k_jadwal_pengajar_feeder')
                ->where('id_jadwal', $scheduleId)->where('id_dosen', $teacherId)
                ->update(['status' => 'failed', 'error_desc' => $message, 'updated_at' => now()]);

            return $this->teacherSyncFailure($request, $schedule, $teacherId, $message);
        }

        $courseCredits = DB::connection('simak_main')->table('s1m4k_mastermk')
            ->where('id_matkul', $schedule->id_matkul)
            ->value('sks_mata_kuliah');
        $teacherCount = collect(self::TEACHER_FIELDS)
            ->map(fn (string $field): string => trim((string) ($schedule->{$field} ?? '')))
            ->filter()
            ->unique()
            ->count();
        abort_if($teacherCount === 0, 422, 'Jadwal SP belum memiliki dosen pengampu.');
        $realization = DB::connection('simak_main')->table('s1m4k_akd')
            ->where('id_jadwal', $scheduleId)->where('id_dosen', $teacherId)
            ->value('realisasi');

        $connection = DB::connection('simak_main');
        $claimed = $connection->transaction(function () use ($connection, $scheduleId, $teacherId): bool {
            $current = $connection->table('s1m4k_jadwal_pengajar_feeder')
                ->where('id_jadwal', $scheduleId)
                ->where('id_dosen', $teacherId)
                ->lockForUpdate()
                ->first();
            if ($current === null || ! in_array($current->status, ['pending', 'failed'], true)) {
                return false;
            }

            $connection->table('s1m4k_jadwal_pengajar_feeder')
                ->where('id_jadwal', $scheduleId)->where('id_dosen', $teacherId)
                ->update(['status' => 'processing', 'error_desc' => null, 'updated_at' => now()]);

            return true;
        });
        if (! $claimed) {
            $message = 'Pengiriman dosen ini sudah diproses oleh permintaan lain. Periksa status sebelum mencoba lagi.';

            return $request->expectsJson()
                ? response()->json(['status' => 'processing', 'message' => $message], 409)
                : redirect()->route('short-semester-schedules.index', [
                    'id_prodi' => $schedule->id_prodi,
                    'id_kalender' => $schedule->id_kalender,
                ])->withErrors(['feeder' => $message]);
        }

        try {
            $feeder->insertLecturer([
                'id_kelas_kuliah' => $schedule->id_kelas_kuliah,
                'id_substansi' => '',
                'sks_substansi_total' => number_format((float) $courseCredits / $teacherCount, 2, '.', ''),
                'rencana_minggu_pertemuan' => 16,
                'realisasi_minggu_pertemuan' => (int) $realization,
                'id_jenis_evaluasi' => '1',
            ], trim((string) $lecturer->nidn), (string) $schedule->id_prodi);
            DB::connection('simak_main')->table('s1m4k_jadwal_pengajar_feeder')
                ->where('id_jadwal', $scheduleId)->where('id_dosen', $teacherId)
                ->update(['status' => 'synced', 'error_desc' => null, 'updated_at' => now()]);
        } catch (\RuntimeException $exception) {
            report($exception);
            DB::connection('simak_main')->table('s1m4k_jadwal_pengajar_feeder')
                ->where('id_jadwal', $scheduleId)->where('id_dosen', $teacherId)
                ->update(['status' => 'failed', 'error_desc' => $exception->getMessage(), 'updated_at' => now()]);

            return $this->teacherSyncFailure($request, $schedule, $teacherId, $exception->getMessage());
        }

        if ($request->expectsJson()) {
            return response()->json(['status' => 'synced', 'teacher_id' => $teacherId]);
        }

        return redirect()->route('short-semester-schedules.index', [
            'id_prodi' => $schedule->id_prodi,
            'id_kalender' => $schedule->id_kalender,
        ])->with('status', 'Dosen pengampu berhasil dikirim ke Feeder.');
    }

    public function shortSemesterEligibleStudents(Request $request, string $scheduleId): JsonResponse
    {
        $user = $request->user();
        $this->authorizeWrite($user);
        $schedule = $this->shortSemesterScheduleForWrite($user, $scheduleId);

        $students = DB::connection('simak_main')->table('s1m4k_mastermhs as student')
            ->leftJoin('s1m4k_krs as enrollment', function ($join) use ($scheduleId): void {
                $join->on('enrollment.id_mahasiswa', '=', 'student.id_mahasiswa')
                    ->where('enrollment.id_jadwal', '=', $scheduleId);
            })
            ->where('student.id_prodi', (string) $schedule->id_prodi)
            ->whereIn('student.id_status_mahasiswa', ['A', 'C', 'N', 'M'])
            ->whereNotNull('student.id_registrasi_mahasiswa')
            ->where('student.id_registrasi_mahasiswa', '<>', '')
            ->whereNull('enrollment.id_krs')
            ->orderBy('student.nama_mahasiswa')
            ->get([
                'student.id_mahasiswa',
                'student.nim',
                'student.nama_mahasiswa',
                'student.id_status_mahasiswa',
                'student.nama_status_mahasiswa',
                'student.id_registrasi_mahasiswa',
            ]);

        return response()->json([
            'schedule_id' => $scheduleId,
            'students' => $students->map(fn (object $student): array => [
                'id_mahasiswa' => (string) $student->id_mahasiswa,
                'nim' => trim((string) ($student->nim ?? '')),
                'nama_mahasiswa' => trim((string) ($student->nama_mahasiswa ?? '')),
                'id_status_mahasiswa' => trim((string) ($student->id_status_mahasiswa ?? '')),
                'nama_status_mahasiswa' => trim((string) ($student->nama_status_mahasiswa ?? '')),
                'id_registrasi_mahasiswa' => trim((string) ($student->id_registrasi_mahasiswa ?? '')),
            ])->values()->all(),
        ]);
    }

    public function storeShortSemesterStudent(
        Request $request,
        string $scheduleId,
        string $student,
        ShortSemesterScheduleFeederService $feeder
    ): JsonResponse {
        $user = $request->user();
        $this->authorizeWrite($user);
        $schedule = $this->shortSemesterScheduleForWrite($user, $scheduleId);
        $calendar = $this->shortSemesterCalendar((string) $schedule->id_kalender, (string) $schedule->id_prodi);
        abort_if($calendar === null, 404);

        $studentRow = DB::connection('simak_main')->table('s1m4k_mastermhs')
            ->where('id_mahasiswa', $student)
            ->where('id_prodi', (string) $schedule->id_prodi)
            ->first([
                'id_mahasiswa', 'id_prodi', 'id_registrasi_mahasiswa', 'id_status_mahasiswa', 'nama_mahasiswa', 'nim',
            ]);
        abort_if($studentRow === null, 404, 'Mahasiswa tidak ditemukan pada program studi ini.');

        if (! in_array((string) ($studentRow->id_status_mahasiswa ?? ''), ['A', 'C', 'N', 'M'], true)) {
            return response()->json([
                'status' => 'rejected',
                'student_id' => $student,
                'message' => 'Mahasiswa tidak memenuhi syarat untuk ditambahkan ke kelas SP.',
            ], 422);
        }

        $registrationId = trim((string) ($studentRow->id_registrasi_mahasiswa ?? ''));
        if ($registrationId === '') {
            return response()->json([
                'status' => 'rejected',
                'student_id' => $student,
                'message' => 'Mahasiswa belum memiliki ID registrasi Feeder.',
            ], 422);
        }

        $existing = DB::connection('simak_main')->table('s1m4k_krs')
            ->where('id_jadwal', $scheduleId)
            ->where('id_mahasiswa', $student)
            ->first();
        if ($existing !== null) {
            return response()->json([
                'status' => 'skipped',
                'student_id' => $student,
                'message' => 'Mahasiswa sudah terdaftar pada kelas SP ini.',
            ]);
        }

        try {
            $feeder->insertParticipant((string) $schedule->id_kelas_kuliah, $registrationId);
        } catch (\RuntimeException $exception) {
            report($exception);

            return response()->json([
                'status' => 'failed',
                'student_id' => $student,
                'message' => $exception->getMessage(),
            ], 422);
        }

        $connection = DB::connection('simak_main');
        $connection->transaction(function () use ($connection, $schedule, $calendar, $student, $user): void {
            $connection->table('s1m4k_krs')->insert([
                'id_krs' => (string) Str::uuid(),
                'id_perguruan_tinggi' => $schedule->id_perguruan_tinggi,
                'id_fakultas' => $schedule->id_fakultas,
                'id_prodi' => $schedule->id_prodi,
                'id_kalender' => $schedule->id_kalender,
                'tahun' => $calendar->tahun,
                'id_mahasiswa' => $student,
                'id_jadwal' => $schedule->id_jadwal,
                'id_matkul' => $schedule->id_matkul,
                'status_krs' => 'B',
                'tgl_update' => now()->toDateString(),
                'nilai_huruf' => null,
                'krs_reguler' => 'Y',
                'krs_mbkm' => 'N',
                'isi_edom' => 'N',
                'user' => (string) $user->legacy_id,
            ]);
        });

        return response()->json([
            'status' => 'inserted',
            'schedule_id' => $scheduleId,
            'student_id' => $student,
            'student_name' => trim((string) ($studentRow->nama_mahasiswa ?? '')),
            'calendar_id' => (string) $schedule->id_kalender,
            'message' => 'Mahasiswa berhasil ditambahkan ke kelas SP dan dicatat pada KRS SP.',
        ]);
    }

    public function shortSemesterParticipants(Request $request, string $scheduleId): JsonResponse
    {
        $user = $request->user();
        $this->authorizeWrite($user);
        $schedule = $this->shortSemesterScheduleForWrite($user, $scheduleId);

        $participants = DB::connection('simak_main')->table('s1m4k_krs as enrollment')
            ->leftJoin('s1m4k_mastermhs as student', 'enrollment.id_mahasiswa', '=', 'student.id_mahasiswa')
            ->where('enrollment.id_jadwal', $scheduleId)
            ->orderBy('student.nama_mahasiswa')
            ->get([
                'enrollment.id_mahasiswa',
                'student.nim',
                'student.nama_mahasiswa',
                'student.id_status_mahasiswa',
                'student.nama_status_mahasiswa',
                'student.id_registrasi_mahasiswa',
            ]);

        return response()->json([
            'schedule_id' => $scheduleId,
            'participants' => $participants->map(fn (object $participant): array => [
                'id_mahasiswa' => (string) $participant->id_mahasiswa,
                'nim' => trim((string) ($participant->nim ?? '')),
                'nama_mahasiswa' => trim((string) ($participant->nama_mahasiswa ?? '')),
                'id_status_mahasiswa' => trim((string) ($participant->id_status_mahasiswa ?? '')),
                'nama_status_mahasiswa' => trim((string) ($participant->nama_status_mahasiswa ?? '')),
                'id_registrasi_mahasiswa' => trim((string) ($participant->id_registrasi_mahasiswa ?? '')),
            ])->values()->all(),
        ]);
    }

    public function destroyShortSemesterStudent(
        Request $request,
        string $scheduleId,
        string $student,
        ShortSemesterScheduleFeederService $feeder
    ): JsonResponse {
        $user = $request->user();
        $this->authorizeWrite($user);
        $schedule = $this->shortSemesterScheduleForWrite($user, $scheduleId);
        $classId = trim((string) ($schedule->id_kelas_kuliah ?? ''));
        abort_if($classId === '', 422, 'ID kelas Feeder belum tersedia; peserta tidak dapat dihapus.');

        $connection = DB::connection('simak_main');
        $enrollment = $connection->table('s1m4k_krs')
            ->where('id_jadwal', $scheduleId)
            ->where('id_mahasiswa', $student)
            ->first();
        abort_if($enrollment === null, 404, 'Peserta tidak ditemukan pada KRS kelas SP ini.');

        $studentRow = $connection->table('s1m4k_mastermhs')
            ->where('id_mahasiswa', $student)
            ->where('id_prodi', (string) $schedule->id_prodi)
            ->first(['id_registrasi_mahasiswa', 'nama_mahasiswa']);
        $registrationId = trim((string) ($studentRow->id_registrasi_mahasiswa ?? ''));
        abort_if($registrationId === '', 422, 'ID registrasi Feeder mahasiswa tidak tersedia; peserta tidak dapat dihapus.');

        try {
            $feeder->deleteParticipant($classId, $registrationId);
        } catch (\RuntimeException $exception) {
            report($exception);

            return response()->json([
                'status' => 'failed',
                'student_id' => $student,
                'message' => $exception->getMessage(),
            ], 422);
        }

        $connection->transaction(function () use ($connection, $scheduleId, $student): void {
            $connection->table('s1m4k_krs')
                ->where('id_jadwal', $scheduleId)
                ->where('id_mahasiswa', $student)
                ->delete();
        });

        return response()->json([
            'status' => 'deleted',
            'schedule_id' => $scheduleId,
            'student_id' => $student,
            'student_name' => trim((string) ($studentRow->nama_mahasiswa ?? '')),
            'message' => 'Peserta berhasil dihapus dari Feeder dan KRS SP.',
        ]);
    }

    public function edit(Request $request, string $scheduleId): View
    {
        $user = $request->user();
        $this->authorizeWrite($user);
        $schedule = $this->scheduleForWrite($user, $scheduleId, true);
        $calendar = DB::connection('simak_main')->table('s1m4k_kalender')
            ->where('id_kalender', $schedule->id_kalender)
            ->first(['id_kalender', 'tahun', 'namatahun']);
        abort_if($calendar === null, 404);
        $hasKrs = DB::connection('simak_main')->table('s1m4k_krs')
            ->where('id_jadwal', $scheduleId)
            ->where('id_prodi', $schedule->id_prodi)
            ->exists();

        return view('class-schedules.form', [
            ...$this->formOptions((string) $schedule->id_prodi, $calendar),
            'schedule' => $schedule,
            'hasKrs' => $hasKrs,
            'canChooseProgram' => false,
            'programs' => [],
            'programLabel' => $this->availablePrograms($user)[(string) $schedule->id_prodi] ?? (string) $schedule->id_prodi,
            'classNameMaxLength' => 50,
        ]);
    }

    public function update(Request $request, string $scheduleId, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeWrite($user);
        $schedule = $this->scheduleForWrite($user, $scheduleId, true);
        $calendar = DB::connection('simak_main')->table('s1m4k_kalender')
            ->where('id_kalender', $schedule->id_kalender)
            ->first(['id_kalender', 'tahun']);
        abort_if($calendar === null, 404);
        $hasKrs = DB::connection('simak_main')->table('s1m4k_krs')
            ->where('id_jadwal', $scheduleId)
            ->where('id_prodi', $schedule->id_prodi)
            ->exists();
        $validated = $this->validateSchedule($request, (string) $schedule->id_prodi, $calendar, $hasKrs, $user->role);
        $courseId = $hasKrs ? (string) $schedule->id_matkul : $validated['id_matkul'];
        $className = $validated['nama_kelas'];
        $this->assertUniqueClass((string) $schedule->id_prodi, (string) $schedule->id_kalender, $courseId, $className, $scheduleId);

        $editableFields = $hasKrs
            ? [...self::TEACHER_FIELDS, 'nama_kelas', 'kampus_merdeka', 'lingkup_km', 'mode_km', 'tgl_tutup_daftar_km']
            : [...self::TEACHER_FIELDS, 'id_matkul', 'id_ruang', 'nama_kelas', 'hari', 'jammulai', 'jamselesai', 'kampus_merdeka', 'lingkup_km', 'mode_km', 'tgl_tutup_daftar_km'];
        $updates = array_intersect_key($validated, array_flip($editableFields));

        $scheduleIdentity = $this->scheduleIdentity(array_merge((array) $schedule, $updates), (string) $schedule->id_prodi);
        DB::connection('simak_main')->transaction(function () use ($schedule, $updates, $activityLogger, $user, $scheduleId, $scheduleIdentity): void {
            $connection = DB::connection('simak_main');
            $connection->table('s1m4k_jadwal')
                ->where('id_jadwal', $schedule->id_jadwal)
                ->where('id_prodi', $schedule->id_prodi)
                ->update($updates);
            $activityLogger->record(
                $user,
                'Jadwal Kuliah',
                'Ubah jadwal',
                $activityLogger->diff(array_intersect_key((array) $schedule, $updates), $updates),
                $scheduleId,
                $scheduleIdentity
            );
        });

        Log::info('SIMAK class schedule updated.', [
            'actor_id' => (string) $user->legacy_id,
            'schedule_id' => $scheduleId,
            'program_id' => (string) $schedule->id_prodi,
        ]);

        return $this->backToSchedule((string) $schedule->id_prodi, (string) $schedule->id_kalender)
            ->with('status', $hasKrs
                ? 'Jadwal terprogram KRS; hanya pengampu, kelas, dan metadata Kampus Merdeka yang diperbarui.'
                : 'Jadwal kuliah berhasil diperbarui.');
    }

    public function destroy(Request $request, string $scheduleId, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeWrite($user);
        $schedule = $this->scheduleForWrite($user, $scheduleId, true);
        $connection = DB::connection('simak_main');

        $deleted = $connection->transaction(function () use ($connection, $schedule, $scheduleId, $activityLogger, $user): bool {
            $hasKrs = $connection->table('s1m4k_krs')
                ->where('id_jadwal', $scheduleId)
                ->where('id_prodi', $schedule->id_prodi)
                ->exists();
            if ($hasKrs) {
                return false;
            }

            $deleted = $connection->table('s1m4k_jadwal')
                ->where('id_jadwal', $scheduleId)
                ->where('id_prodi', $schedule->id_prodi)
                ->delete() > 0;
            if ($deleted) {
                $activityLogger->record($user, 'Jadwal Kuliah', 'Hapus jadwal', [
                    'data_lama' => (array) $schedule,
                ], $scheduleId, $this->scheduleIdentity($schedule, (string) $schedule->id_prodi));
            }

            return $deleted;
        });

        if (! $deleted) {
            return $this->backToSchedule((string) $schedule->id_prodi, (string) $schedule->id_kalender)
                ->withErrors(['schedule' => 'Jadwal tidak dapat dihapus karena sudah dipakai pada KRS.']);
        }

        Log::info('SIMAK class schedule deleted.', [
            'actor_id' => (string) $user->legacy_id,
            'schedule_id' => $scheduleId,
            'program_id' => (string) $schedule->id_prodi,
        ]);

        return $this->backToSchedule((string) $schedule->id_prodi, (string) $schedule->id_kalender)
            ->with('status', 'Jadwal kuliah berhasil dihapus.');
    }

    public function copy(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeWrite($user);
        $validated = $request->validate([
            'id_prodi' => ['nullable', 'string', 'max:50'],
            'source_calendar' => ['required', 'string', 'max:50'],
            'target_calendar' => ['required', 'string', 'max:50', 'different:source_calendar'],
        ]);
        $programs = $this->availablePrograms($user);
        $programId = $this->requestedProgram($request, $user, $programs, true);
        $calendars = AcademicCalendarScope::excludeShortSemesters(
            DB::connection('simak_main')->table('s1m4k_kalender')
        )
            ->where('id_prodi', $programId)
            ->whereIn('id_kalender', [$validated['source_calendar'], $validated['target_calendar']])
            ->where(function ($query) use ($validated): void {
                $query->where('id_kalender', $validated['source_calendar'])
                    ->orWhere(function ($target) use ($validated): void {
                        $target->where('id_kalender', $validated['target_calendar'])
                            ->where('status', 'Y');
                    });
            })
            ->get(['id_kalender']);
        abort_unless($calendars->count() === 2, 404);
        $sourceCalendarId = (string) $validated['source_calendar'];
        $targetCalendarId = (string) $validated['target_calendar'];

        $connection = DB::connection('simak_main');
        $copied = $connection->transaction(function () use ($connection, $sourceCalendarId, $targetCalendarId, $programId, $activityLogger, $user): int {
            $sourceSchedules = $connection->table('s1m4k_jadwal')
                ->where('id_kalender', $sourceCalendarId)
                ->where('id_prodi', $programId)
                ->get();
            $count = 0;
            $copiedRecords = [];
            foreach ($sourceSchedules as $schedule) {
                $copy = array_intersect_key((array) $schedule, array_flip(self::COPY_FIELDS));
                $copy['id_jadwal'] = (string) Str::uuid();
                $copy['id_kalender'] = $targetCalendarId;
                $copy['jml_mahasiswa'] = 0;
                $copy['input_nilai'] = 'N';
                $connection->table('s1m4k_jadwal')->insert($copy);
                $copiedRecords[] = [
                    'id_jadwal' => $copy['id_jadwal'],
                    ...$copy,
                ];
                $count++;
            }
            if ($count > 0) {
                $activityLogger->record($user, 'Jadwal Kuliah', 'Salin jadwal', [
                    'id_kalender_sumber' => $sourceCalendarId,
                    'id_kalender_tujuan' => $targetCalendarId,
                    'jumlah' => $count,
                    'data_jadwal_baru' => $copiedRecords,
                ], null, [
                    'jenis' => 'Jadwal Kuliah',
                    'nama' => 'Salin '.$count.' jadwal untuk program studi '.$programId,
                    'jumlah_jadwal' => $count,
                ]);
            }

            return $count;
        });

        Log::info('SIMAK class schedules copied.', [
            'actor_id' => (string) $user->legacy_id,
            'program_id' => $programId,
            'source_calendar_id' => $sourceCalendarId,
            'target_calendar_id' => $targetCalendarId,
            'count' => $copied,
        ]);

        return $this->backToSchedule($programId, $targetCalendarId)
            ->with('status', "Berhasil menyalin {$copied} jadwal ke tahun akademik tujuan.");
    }

    public function print(Request $request): View
    {
        $user = $request->user();
        abort_unless(in_array($user->role, ['admin', 'userprodi', 'usertu'], true), 403);

        $programs = $this->availablePrograms($user);
        $programId = $this->requestedProgram($request, $user, $programs, true);
        $calendars = $this->calendarOptions($programId);
        $calendarId = trim((string) $request->query('id_kalender', ''));
        if ($calendarId === '') {
            $calendarId = (string) ($calendars->firstWhere('status', 'Y')?->id_kalender ?? '');
        }
        abort_if($calendarId === '', 404);
        $calendar = $calendars->firstWhere('id_kalender', $calendarId);
        abort_if($calendar === null, 404);
        $programName = DB::connection('simak_main')->table('s1m4k_masterprodi')
            ->where('id_prodi', $programId)
            ->value('nama_program_studi');

        return view('class-schedules.print', [
            'programName' => $programName ?: $programs[$programId],
            'calendar' => $calendar,
            'schedules' => $this->scheduleRows($user, $programId, $calendarId),
        ]);
    }

    private function scheduleRows(
        object $user,
        string $programId,
        string $calendarId,
        bool $paginate = false
    ): Collection|LengthAwarePaginator {
        $connection = DB::connection('simak_main');
        if ($user->role === 'mahasiswa') {
            $query = $connection->table('s1m4k_krs as enrollment')
                ->join('s1m4k_jadwal as schedule', 'enrollment.id_jadwal', '=', 'schedule.id_jadwal')
                ->where('enrollment.id_mahasiswa', (string) $user->legacy_id)
                ->where('enrollment.id_kalender', $calendarId)
                ->where('schedule.id_kalender', $calendarId)
                ->where('schedule.id_prodi', $programId);
        } else {
            $query = $connection->table('s1m4k_jadwal as schedule')
                ->where('schedule.id_kalender', $calendarId)
                ->where('schedule.id_prodi', $programId);
            if (in_array($user->role, ['dosen', 'asdos'], true)) {
                $this->whereAssignedTeacher($query, (string) $user->legacy_id, self::TEACHER_SCHEDULE_FIELDS);
            }
        }

        $dayOrderExpression = "CASE LOWER(schedule.hari) WHEN 'senin' THEN 0 WHEN 'selasa' THEN 1 WHEN 'rabu' THEN 2 WHEN 'kamis' THEN 3 WHEN 'jumat' THEN 4 WHEN 'sabtu' THEN 5 WHEN 'ahad' THEN 6 ELSE 99 END";

        $rows = $query
            ->leftJoin('s1m4k_mastermk as course', 'schedule.id_matkul', '=', 'course.id_matkul')
            ->leftJoin('s1m4k_masterruang as room', 'schedule.id_ruang', '=', 'room.id_ruang')
            ->leftJoin('s1m4k_masterprodi as program', 'schedule.id_prodi', '=', 'program.id_prodi')
            ->leftJoin('s1m4k_kalender as calendar', 'schedule.id_kalender', '=', 'calendar.id_kalender')
            ->select([
                'schedule.id_jadwal', 'schedule.id_prodi', 'schedule.id_kalender', 'schedule.id_matkul',
                'schedule.id_ruang', 'schedule.dosen1', 'schedule.dosen2', 'schedule.dosen3', 'schedule.dosen4',
                'schedule.dosen5', 'schedule.dosen6', 'schedule.dosen7', 'schedule.dosen8', 'schedule.dosen9',
                'schedule.dosen10', 'schedule.dosen11', 'schedule.dosen12', 'schedule.dosen13', 'schedule.dosen14',
                'schedule.nama_kelas', 'schedule.hari', 'schedule.jammulai', 'schedule.jamselesai',
                'schedule.kampus_merdeka', 'schedule.lingkup_km', 'schedule.mode_km', 'schedule.tgl_tutup_daftar_km',
                'course.kode_mata_kuliah', 'course.nama_mata_kuliah', 'course.semester', 'room.nama_ruang',
                'program.nama_program_studi', 'program.nama_jenjang_pendidikan',
                'calendar.namatahun', 'calendar.tahun',
                DB::raw($dayOrderExpression . ' as day_order'),
            ])
            ->distinct()
            ->orderBy('day_order')
            ->orderBy('schedule.jammulai');

        $rows = $paginate
            ? $query->paginate(25)->withQueryString()
            : $query->limit(1000)->get();
        $rowCollection = $rows instanceof LengthAwarePaginator ? $rows->getCollection() : $rows;
        $teacherLabels = $this->teacherLabels($rowCollection);
        $scheduleIds = $rowCollection->pluck('id_jadwal');
        $krsScheduleIds = $scheduleIds->isEmpty()
            ? collect()
            : $connection->table('s1m4k_krs')->whereIn('id_jadwal', $scheduleIds)
                ->distinct()->pluck('id_jadwal')->flip();

        $decoratedRows = $rowCollection->map(function (object $row) use ($teacherLabels, $krsScheduleIds): object {
            $row->teacher_names = collect(self::TEACHER_FIELDS)
                ->map(fn (string $field): ?string => $teacherLabels[(string) ($row->{$field} ?? '')] ?? null)
                ->filter()->unique()->implode(', ');
            $row->has_krs = $krsScheduleIds->has($row->id_jadwal);

            return $row;
        });

        if ($rows instanceof LengthAwarePaginator) {
            return $rows->setCollection($decoratedRows);
        }

        return $decoratedRows;
    }

    private function availablePrograms(object $user): array
    {
        $query = DB::connection('simak_main')->table('s1m4k_masterprodi as program')
            ->select(['program.id_prodi', 'program.nama_program_studi', 'program.nama_jenjang_pendidikan'])
            ->whereNotNull('program.nama_program_studi')
            ->whereRaw("TRIM(program.nama_program_studi) <> ''");

        if ($user->role === 'pimfak') {
            $facultyId = trim((string) data_get($user, 'id_fakultas'));
            if ($facultyId === '') {
                return [];
            }
            $query->where('program.id_fakultas', $facultyId);
        } elseif (in_array($user->role, ['userprodi', 'usertu', 'mahasiswa'], true)) {
            $programId = trim((string) data_get($user, 'id_prodi'));
            if ($programId === '') {
                return [];
            }
            $query->where('program.id_prodi', $programId);
        } elseif (in_array($user->role, ['dosen', 'asdos'], true)) {
            $teacherId = (string) $user->legacy_id;
            $query->whereIn('program.id_prodi', function ($subquery) use ($teacherId): void {
                $subquery->select('id_prodi')->from('s1m4k_jadwal')
                    ->where(function ($assignment) use ($teacherId): void {
                        foreach (self::TEACHER_PROGRAM_FIELDS as $field) {
                            $assignment->orWhere($field, $teacherId);
                        }
                    });
            });
        }

        return $query->orderBy('program.nama_program_studi')->get()
            ->mapWithKeys(static fn (object $program): array => [
                (string) $program->id_prodi => trim($program->nama_program_studi.' '.($program->nama_jenjang_pendidikan ?? '')),
            ])->all();
    }

    private function requestedProgram(Request $request, object $user, array $programs, bool $required = false): ?string
    {
        $programId = in_array($user->role, ['userprodi', 'usertu', 'mahasiswa'], true)
            ? trim((string) data_get($user, 'id_prodi'))
            : trim((string) $request->input('id_prodi', $request->query('id_prodi', '')));

        if ($programId === '') {
            if ($required) {
                throw ValidationException::withMessages(['id_prodi' => 'Pilih program studi terlebih dahulu.']);
            }

            return null;
        }
        abort_unless(array_key_exists($programId, $programs), 404);

        return $programId;
    }

    private function calendarOptions(string $programId): Collection
    {
        return AcademicCalendarScope::excludeShortSemesters(
            DB::connection('simak_main')->table('s1m4k_kalender')
        )
            ->where('id_prodi', $programId)
            ->orderByDesc('tahun')
            ->get(['id_kalender', 'tahun', 'namatahun', 'status']);
    }

    private function shortSemesterCalendarOptions(string $programId): Collection
    {
        return $this->shortSemesterCalendarQuery($programId)
            ->orderByDesc('tahun')
            ->get([
                'id_kalender', 'tahun', 'namatahun', 'status_sp as status',
            ]);
    }

    private function requestedCalendar(Request $request, ?string $programId, Collection $calendars, object $user): ?string
    {
        if ($programId === null) {
            return null;
        }

        $requested = trim((string) $request->query('id_kalender', ''));
        if ($requested !== '') {
            abort_unless($calendars->contains('id_kalender', $requested), 404);

            return $requested;
        }

        return in_array($user->role, ['dosen', 'asdos', 'mahasiswa'], true)
            ? $calendars->first()?->id_kalender
            : $calendars->firstWhere('status', 'Y')?->id_kalender;
    }

    private function hasScheduleData(object $user, string $programId, string $calendarId): bool
    {
        $connection = DB::connection('simak_main');
        if ($user->role === 'mahasiswa') {
            return $connection->table('s1m4k_krs as enrollment')
                ->join('s1m4k_jadwal as schedule', 'enrollment.id_jadwal', '=', 'schedule.id_jadwal')
                ->where('enrollment.id_mahasiswa', (string) $user->legacy_id)
                ->where('enrollment.id_kalender', $calendarId)
                ->where('schedule.id_kalender', $calendarId)
                ->where('schedule.id_prodi', $programId)
                ->exists();
        }

        $query = $connection->table('s1m4k_jadwal as schedule')
            ->where('schedule.id_kalender', $calendarId)
            ->where('schedule.id_prodi', $programId);
        $this->whereAssignedTeacher($query, (string) $user->legacy_id, self::TEACHER_SCHEDULE_FIELDS);

        return $query->exists();
    }

    private function scheduleForWrite(object $user, string $scheduleId, bool $requireActiveCalendar = false): object
    {
        $query = DB::connection('simak_main')->table('s1m4k_jadwal')->where('id_jadwal', $scheduleId);
        if (in_array($user->role, ['userprodi', 'usertu'], true)) {
            $programId = trim((string) data_get($user, 'id_prodi'));
            abort_unless($programId !== '', 403);
            $query->where('id_prodi', $programId);
        }

        $schedule = $query->first();
        abort_if($schedule === null, 404);
        if ($requireActiveCalendar) {
            $calendarIsActive = AcademicCalendarScope::excludeShortSemesters(
                DB::connection('simak_main')->table('s1m4k_kalender')
            )->where('id_kalender', $schedule->id_kalender)->where('status', 'Y')->exists();
            abort_unless($calendarIsActive, 403, 'Jadwal pada kalender tidak aktif hanya dapat dilihat.');
        }

        return $schedule;
    }

    private function shortSemesterScheduleForWrite(object $user, string $scheduleId): object
    {
        $schedule = $this->scheduleForWrite($user, $scheduleId);
        $programId = (string) $schedule->id_prodi;
        abort_if($this->shortSemesterCalendar((string) $schedule->id_kalender, $programId) === null, 404);
        abort_unless(
            (string) ($this->activeShortSemesterCalendar($programId)?->id_kalender ?? '') === (string) $schedule->id_kalender,
            403,
            'Jadwal hanya dapat diubah pada kalender SP aktif.'
        );

        return $schedule;
    }

    private function formOptions(string $programId, object $calendar, bool $shortSemester = false): array
    {
        $connection = DB::connection('simak_main');
        $semesterLastDigit = substr((string) $calendar->tahun, -1);
        $semesterOptions = match ($semesterLastDigit) {
            '1' => ['1', '3', '5', '7', '01', '03', '05', '07'],
            '2' => ['2', '4', '6', '8', '02', '04', '06', '08'],
            default => [],
        };
        $courseQuery = $connection->table('s1m4k_mastermk')
            ->where('id_prodi', $programId)
            ->where('status', 'A');
        if (! $shortSemester) {
            $courseQuery->whereIn('semester', $semesterOptions);
        }
        $courses = $courseQuery->orderBy('nama_mata_kuliah')
            ->get(['id_matkul', 'kode_mata_kuliah', 'nama_mata_kuliah', 'semester']);
        $rooms = $connection->table('s1m4k_masterruang')
            ->where('id_prodi', $programId)->orderBy('nama_ruang')
            ->get(['id_ruang', 'nama_ruang']);
        $lecturerQuery = $connection->table('s1m4k_masterdosen')
            ->whereNotNull('nama_dosen')->whereRaw("TRIM(nama_dosen) <> ''")
            ->orderBy('nama_dosen');
        if ($shortSemester) {
            $lecturerQuery->whereNotNull('nidn')->whereRaw("TRIM(nidn) <> ''");
        }
        $lecturers = $lecturerQuery->get(['id_dosen', 'nama_dosen', 'gelar_depan', 'gelar_belakang', 'nidn'])
            ->mapWithKeys(fn (object $lecturer): array => [
                (string) $lecturer->id_dosen => $this->personName([
                    $lecturer->gelar_depan ?? '', $lecturer->nama_dosen, $lecturer->gelar_belakang ?? '',
                ]).' ('.$lecturer->nidn.')',
            ])->all();
        $assistants = $connection->table('s1m4k_masterasdos')
            ->where('id_prodi', $programId)->whereRaw("LOWER(status) = 'aktif'")
            ->orderBy('nama')->get(['id_asdos', 'nama', 'gelard', 'gelarb'])
            ->mapWithKeys(fn (object $assistant): array => [
                (string) $assistant->id_asdos => $this->personName([
                    $assistant->gelard ?? '', $assistant->nama, $assistant->gelarb ?? '',
                ]).' (Asisten)',
            ])->all();

        return [
            'programId' => $programId,
            'calendar' => $calendar,
            'courses' => $courses->mapWithKeys(static fn (object $course): array => [
                (string) $course->id_matkul => trim($course->kode_mata_kuliah.' - '.$course->nama_mata_kuliah.' (SMS '.$course->semester.')'),
            ])->all(),
            'rooms' => $rooms->pluck('nama_ruang', 'id_ruang')->all(),
            'lecturers' => $lecturers,
            'teachers' => $shortSemester ? $lecturers : $lecturers + $assistants,
            'days' => array_combine(self::DAYS, array_map('ucfirst', self::DAYS)),
            'teacherFields' => self::TEACHER_FIELDS,
            'kmScopes' => ['1' => 'Internal', '2' => 'Eksternal', '3' => 'Campuran'],
            'kmModes' => ['O' => 'Online', 'F' => 'Offline', 'M' => 'Campuran'],
        ];
    }

    private function shortSemesterCalendarQuery(string $programId)
    {
        return DB::connection('simak_main')->table('s1m4k_kalender as calendar')
            ->join('s1m4k_kurikulum as curriculum', function ($join): void {
                $join->on('curriculum.id_kurikulum', '=', 'calendar.id_kalender')
                    ->on('curriculum.id_prodi', '=', 'calendar.id_prodi')
                    ->on('curriculum.id_semester', '=', 'calendar.tahun');
            })
            ->where('calendar.id_prodi', $programId)
            ->where(function ($query): void {
                $query->where('calendar.periode', 'like', '% SP')
                    ->orWhere('calendar.periode', 'like', '%Pendek')
                    ->orWhere('calendar.namatahun', 'like', 'SP %')
                    ->orWhere('curriculum.semester_mulai_berlaku', 'like', '%Pendek');
            });
    }

    private function activeShortSemesterCalendar(string $programId): ?object
    {
        return $this->shortSemesterCalendarQuery($programId)
            ->where('calendar.status_sp', 'Y')
            ->orderByDesc('calendar.tahun')
            ->first([
                'calendar.id_kalender', 'calendar.tahun', 'calendar.namatahun', 'calendar.periode',
                'calendar.tgl_mulai_krs', 'calendar.tgl_akhir_isinilai',
            ]);
    }

    private function shortSemesterCalendar(string $calendarId, string $programId): ?object
    {
        return $this->shortSemesterCalendarQuery($programId)
            ->where('calendar.id_kalender', $calendarId)
            ->first(['calendar.id_kalender', 'calendar.tahun', 'calendar.namatahun', 'calendar.periode']);
    }

    private function validateSchedule(
        Request $request,
        string $programId,
        object $calendar,
        bool $hasKrs,
        string $role,
        bool $shortSemester = false
    ): array {
        $editableDuringKrs = [...self::TEACHER_FIELDS, 'nama_kelas', 'kampus_merdeka', 'lingkup_km', 'mode_km', 'tgl_tutup_daftar_km'];
        $allFields = [...self::TEACHER_FIELDS, 'id_matkul', 'id_ruang', 'nama_kelas', 'hari', 'jammulai', 'jamselesai', 'kampus_merdeka', 'lingkup_km', 'mode_km', 'tgl_tutup_daftar_km'];
        $allowed = $hasKrs ? $editableDuringKrs : $allFields;
        $rules = [
            'nama_kelas' => ['required', 'string', 'max:'.($shortSemester || (in_array($role, ['userprodi', 'usertu'], true) && $request->routeIs('class-schedules.store')) ? 5 : 50)],
            'kampus_merdeka' => ['required', 'in:0,1'],
            'lingkup_km' => ['nullable', 'in:1,2,3'],
            'mode_km' => ['nullable', 'in:O,F,M'],
            'tgl_tutup_daftar_km' => ['nullable', 'date_format:Y-m-d'],
        ];
        if (! $hasKrs) {
            $rules += [
                'id_matkul' => ['required', 'string', 'max:50'],
                'id_ruang' => ['required', 'string', 'max:50'],
                'hari' => ['required', 'in:'.implode(',', self::DAYS)],
                'jammulai' => ['required', 'date_format:H:i'],
                'jamselesai' => ['required', 'date_format:H:i', 'after:jammulai'],
            ];
        }
        foreach (self::TEACHER_FIELDS as $field) {
            $rules[$field] = $field === 'dosen1'
                ? ['required', 'string', 'max:100']
                : ['nullable', 'string', 'max:100'];
        }

        $validated = $request->validate(array_intersect_key($rules, array_flip($allowed)));
        if (($validated['kampus_merdeka'] ?? '0') === '1' && empty($validated['tgl_tutup_daftar_km'])) {
            throw ValidationException::withMessages([
                'tgl_tutup_daftar_km' => 'Tanggal tutup wajib diisi untuk kelas Kampus Merdeka.',
            ]);
        }

        $connection = DB::connection('simak_main');
        if (! $hasKrs) {
            $courseQuery = $connection->table('s1m4k_mastermk')
                ->where('id_matkul', $validated['id_matkul'])
                ->where('id_prodi', $programId)
                ->where('status', 'A');
            if (! $shortSemester) {
                $courseQuery->whereIn('semester', $this->semesterOptions((string) $calendar->tahun));
            }
            $courseExists = $courseQuery->exists();
            if (! $courseExists) {
                throw ValidationException::withMessages(['id_matkul' => 'Mata kuliah tidak sesuai prodi atau semester kalender.']);
            }
            $roomExists = $connection->table('s1m4k_masterruang')
                ->where('id_ruang', $validated['id_ruang'])->where('id_prodi', $programId)->exists();
            if (! $roomExists) {
                throw ValidationException::withMessages(['id_ruang' => 'Ruang tidak tersedia untuk prodi ini.']);
            }
        }

        foreach (self::TEACHER_FIELDS as $index => $field) {
            $teacherId = trim((string) ($validated[$field] ?? ''));
            if ($teacherId === '') {
                continue;
            }
            $isLecturer = $connection->table('s1m4k_masterdosen')->where('id_dosen', $teacherId)->exists();
            $isAssistant = ! $shortSemester && $index > 0 && $connection->table('s1m4k_masterasdos')
                ->where('id_asdos', $teacherId)->where('id_prodi', $programId)
                ->whereRaw("LOWER(status) = 'aktif'")->exists();
            if (! $isLecturer && ! $isAssistant) {
                throw ValidationException::withMessages([$field => 'Dosen/asisten tidak ditemukan atau tidak aktif di prodi ini.']);
            }
        }
        if ($shortSemester) {
            $selectedTeachers = collect(self::TEACHER_FIELDS)
                ->map(fn (string $field): string => trim((string) ($validated[$field] ?? '')))
                ->filter();
            if ($selectedTeachers->count() !== $selectedTeachers->unique()->count()) {
                throw ValidationException::withMessages(['dosen1' => 'Dosen pengampu yang sama tidak boleh dipilih lebih dari satu kali.']);
            }
        }

        return array_replace(array_fill_keys(self::TEACHER_FIELDS, ''), [
            'id_matkul' => $validated['id_matkul'] ?? null,
            'id_ruang' => $validated['id_ruang'] ?? null,
            'nama_kelas' => $validated['nama_kelas'],
            'hari' => $validated['hari'] ?? null,
            'jammulai' => $validated['jammulai'] ?? null,
            'jamselesai' => $validated['jamselesai'] ?? null,
            'kampus_merdeka' => $validated['kampus_merdeka'],
            'lingkup_km' => $validated['lingkup_km'] ?? '',
            'mode_km' => $validated['mode_km'] ?? '',
            'tgl_tutup_daftar_km' => $validated['tgl_tutup_daftar_km'] ?? '',
        ], array_intersect_key($validated, array_flip(self::TEACHER_FIELDS)));
    }

    private function assertUniqueClass(string $programId, string $calendarId, string $courseId, string $className, ?string $exceptSchedule = null): void
    {
        $query = DB::connection('simak_main')->table('s1m4k_jadwal')
            ->where('id_prodi', $programId)->where('id_kalender', $calendarId)
            ->where('id_matkul', $courseId)->where('nama_kelas', $className);
        if ($exceptSchedule !== null) {
            $query->where('id_jadwal', '!=', $exceptSchedule);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages(['nama_kelas' => 'Nama kelas sudah digunakan untuk mata kuliah pada kalender ini.']);
        }
    }

    private function availableTeachers(string $programId): array
    {
        return $this->formOptions($programId, $this->activeCalendar($programId) ?? (object) ['tahun' => '20261'])['teachers'];
    }

    private function teacherLabels(Collection $schedules): array
    {
        $ids = $schedules->flatMap(static fn (object $schedule): array => array_map(
            static fn (string $field): string => trim((string) ($schedule->{$field} ?? '')),
            self::TEACHER_FIELDS
        ))->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $connection = DB::connection('simak_main');
        $labels = $connection->table('s1m4k_masterdosen')->whereIn('id_dosen', $ids)
            ->get(['id_dosen', 'nama_dosen', 'gelar_depan', 'gelar_belakang'])
            ->mapWithKeys(fn (object $lecturer): array => [
                (string) $lecturer->id_dosen => $this->personName([
                    $lecturer->gelar_depan ?? '', $lecturer->nama_dosen, $lecturer->gelar_belakang ?? '',
                ]),
            ])->all();
        $assistantLabels = $connection->table('s1m4k_masterasdos')->whereIn('id_asdos', $ids)
            ->get(['id_asdos', 'nama', 'gelard', 'gelarb'])
            ->mapWithKeys(fn (object $assistant): array => [
                (string) $assistant->id_asdos => $this->personName([
                    $assistant->gelard ?? '', $assistant->nama, $assistant->gelarb ?? '',
                ]).' (Asisten)',
            ])->all();

        return $assistantLabels + $labels;
    }

    private function personName(array $parts): string
    {
        return LecturerName::format(
            isset($parts[1]) ? (string) $parts[1] : null,
            isset($parts[0]) ? (string) $parts[0] : null,
            isset($parts[2]) ? (string) $parts[2] : null,
        );
    }

    private function whereAssignedTeacher($query, string $teacherId, array $fields): void
    {
        $query->where(function ($assignment) use ($teacherId, $fields): void {
            foreach ($fields as $field) {
                $assignment->orWhere('schedule.'.$field, $teacherId);
            }
        });
    }

    private function program(string $programId): object
    {
        $program = DB::connection('simak_main')->table('s1m4k_masterprodi')
            ->where('id_prodi', $programId)
            ->first(['id_prodi', 'id_perguruan_tinggi', 'id_fakultas']);
        abort_if($program === null, 404);

        return $program;
    }

    private function teacherSyncFailure(
        Request $request,
        object $schedule,
        string $teacherId,
        string $message
    ): RedirectResponse|JsonResponse {
        Log::warning('Feeder short-semester lecturer synchronization failed.', [
            'schedule_id' => (string) $schedule->id_jadwal,
            'teacher_id' => $teacherId,
            'message' => $message,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'failed',
                'teacher_id' => $teacherId,
                'message' => $message,
            ], 422);
        }

        return redirect()->route('short-semester-schedules.index', [
            'id_prodi' => $schedule->id_prodi,
            'id_kalender' => $schedule->id_kalender,
        ])->withErrors(['feeder' => $message]);
    }

    private function feederDate(mixed $date): ?string
    {
        $date = trim((string) $date);

        return $date === '' || $date === '0000-00-00' ? null : $date;
    }

    private function activeCalendar(string $programId): ?object
    {
        return AcademicCalendarScope::excludeShortSemesters(
            DB::connection('simak_main')->table('s1m4k_kalender')
        )
            ->where('id_prodi', $programId)->where('status', 'Y')
            ->orderByDesc('tahun')->first(['id_kalender', 'tahun', 'namatahun', 'periode']);
    }

    private function semesterOptions(string $year): array
    {
        return match (substr($year, -1)) {
            '1' => ['1', '3', '5', '7', '01', '03', '05', '07'],
            '2' => ['2', '4', '6', '8', '02', '04', '06', '08'],
            default => [],
        };
    }

    private function authorizeWrite(object $user): void
    {
        abort_unless(in_array($user->role, self::WRITE_ROLES, true), 403);
    }

    private function scheduleIdentity(array|object $schedule, string $programId, bool $shortSemester = false): array
    {
        $schedule = (array) $schedule;
        $course = DB::connection('simak_main')->table('s1m4k_mastermk')
            ->where('id_matkul', $schedule['id_matkul'] ?? '')
            ->first(['kode_mata_kuliah', 'nama_mata_kuliah']);
        $programName = DB::connection('simak_main')->table('s1m4k_masterprodi')
            ->where('id_prodi', $programId)
            ->value('nama_program_studi');

        return array_filter([
            'jenis' => $shortSemester ? 'Jadwal SP' : 'Jadwal Kuliah',
            'nama' => $course->nama_mata_kuliah ?? 'Jadwal mata kuliah',
            'kode_mata_kuliah' => $course->kode_mata_kuliah ?? null,
            'kelas' => $schedule['nama_kelas'] ?? null,
            'program_studi' => $programName,
        ], static fn (mixed $value): bool => is_string($value) && trim($value) !== '');
    }

    private function backToSchedule(string $programId, string $calendarId): RedirectResponse
    {
        return redirect()->route('class-schedules.index', [
            'id_prodi' => $programId,
            'id_kalender' => $calendarId,
        ]);
    }
}
