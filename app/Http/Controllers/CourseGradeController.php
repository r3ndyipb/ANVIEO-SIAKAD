<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Services\LegacyFeederService;
use App\Services\ShortSemesterScheduleFeederService;
use App\Services\SimakNotificationService;
use App\Support\AcademicCalendarScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class CourseGradeController extends Controller
{
    private const ROLES = ['admin', 'pimuniv', 'pimfak', 'userprodi', 'usertu', 'dosen', 'asdos'];

    private const INPUT_ROLES = ['userprodi', 'dosen', 'asdos'];

    private const UNLOCK_ROLES = ['userprodi'];

    private const TEACHER_LIST_SLOTS = ['dosen1', 'dosen2', 'dosen3', 'dosen4', 'dosen5', 'dosen6', 'dosen7', 'dosen8', 'dosen9'];

    private const TEACHER_GRADE_SLOTS = ['dosen1', 'dosen2', 'dosen3', 'dosen4', 'dosen5', 'dosen6', 'dosen7'];

    private const COMPONENT_FIELDS = [
        'nilai_partisipatif', 'nilai_proyek', 'nilai_quiz', 'nilai_tugas', 'nilai_uts', 'nilai_uas',
    ];

    public function index(Request $request): View
    {
        $user = $request->user();
        $this->authorizeRole($user);
        $shortSemester = $request->routeIs('short-semester-grades.*');
        $gradeRoutePrefix = $shortSemester ? 'short-semester-grades' : 'grade-management';
        $filters = $request->validate([
            'program' => ['nullable', 'string', 'max:100'],
            'calendar' => ['nullable', 'string', 'max:100'],
        ]);
        $programs = $this->programs($user, $shortSemester);
        $showProgramFilter = in_array($user->role, ['admin', 'pimuniv', 'pimfak', 'dosen', 'asdos'], true);
        $programId = in_array($user->role, ['userprodi', 'usertu'], true)
            ? trim((string) data_get($user, 'id_prodi'))
            : (string) ($filters['program'] ?? '');
        if (in_array($user->role, ['userprodi', 'usertu'], true)) {
            abort_unless($programId !== '', 403);
        }
        if ($programId !== '') {
            abort_unless($programs->contains('id_prodi', $programId), 404);
        }

        $calendars = $programId === '' ? collect() : $this->gradeCalendars($programId, $shortSemester);
        $calendar = null;
        if ($programId !== '') {
            if ($shortSemester && filled($filters['calendar'] ?? null)) {
                $calendar = $calendars->firstWhere('id_kalender', (string) $filters['calendar']);
                abort_if($calendar === null, 404, 'Kalender SP tidak ditemukan pada program studi ini.');
            } else {
                $calendar = $calendars->firstWhere($shortSemester ? 'status_sp' : 'status', 'Y')
                    ?? ($shortSemester ? $calendars->first() : null);
            }
        }
        $schedules = collect();
        $inputWindowOpen = $this->isInputWindowOpen($calendar);
        $calendarIsActive = $calendar !== null
            && (! $shortSemester || (string) ($calendar->status_sp ?? '') === 'Y');
        if ($calendar !== null) {
            $query = $this->scheduleQuery($shortSemester)
                ->where('schedule.id_prodi', $programId)
                ->where('schedule.id_kalender', $calendar->id_kalender);
            if (in_array($user->role, ['dosen', 'asdos'], true)) {
                $this->applyTeacherScope($query, $user);
            }
            $schedules = $query->orderBy('course.nama_mata_kuliah')
                ->orderBy('schedule.nama_kelas')->get();

            foreach ($schedules as $schedule) {
                $grades = DB::connection('simak_main')->table('s1m4k_krs')
                    ->where('id_jadwal', $schedule->id_jadwal)
                    ->where('id_kalender', $schedule->id_kalender)
                    ->get(['id_krs', 'input_nilai']);
                $schedule->enrolled_count = $grades->count();
                $schedule->entered_count = $grades->where('input_nilai', 'Y')->count();
                $schedule->is_locked = $schedule->enrolled_count > 0
                    && $schedule->entered_count === $schedule->enrolled_count;
                $schedule->calendar_is_active = ! $shortSemester || (string) $schedule->status_sp === 'Y';
                $schedule->can_open_entry = $schedule->calendar_is_active && ($user->role === 'userprodi'
                    || (in_array($user->role, ['dosen', 'asdos'], true) && $inputWindowOpen));
                $schedule->can_unlock = $schedule->calendar_is_active && in_array($user->role, self::UNLOCK_ROLES, true)
                    && $schedule->entered_count > 0;
            }
        }

        return view('course-grades.index', compact(
            'user', 'filters', 'programs', 'programId', 'calendar', 'calendars', 'schedules',
            'showProgramFilter', 'inputWindowOpen', 'shortSemester', 'gradeRoutePrefix', 'calendarIsActive'
        ));
    }

    public function edit(Request $request, string $scheduleId, LegacyFeederService $feeder): View|RedirectResponse
    {
        $user = $request->user();
        $this->authorizeRole($user);
        abort_unless(in_array($user->role, self::INPUT_ROLES, true), 403);
        $shortSemester = $request->routeIs('short-semester-grades.*');
        $gradeRoutePrefix = $shortSemester ? 'short-semester-grades' : 'grade-management';
        $schedule = $this->accessibleSchedule($user, $scheduleId, $request->query('program'), $shortSemester);
        $calendar = $shortSemester
            ? $this->scheduleCalendar($schedule)
            : $this->activeCalendar((string) $schedule->id_prodi);
        $inputWindowOpen = $this->isInputWindowOpen($calendar);
        $calendarIsActive = ! $shortSemester || (string) ($calendar->status_sp ?? '') === 'Y';

        if ($calendarIsActive && in_array($user->role, ['dosen', 'asdos'], true) && ! $inputWindowOpen) {
            return redirect()->route($gradeRoutePrefix.'.index', ['program' => $schedule->id_prodi])
                ->withErrors(['grade_window' => $this->inputWindowMessage($calendar)]);
        }
        $unlockOnly = $user->role === 'userprodi' && $request->query('focus') === 'unlock';
        if ($shortSemester && ! $calendarIsActive && $unlockOnly) {
            return redirect()->route($gradeRoutePrefix.'.index', [
                'program' => $schedule->id_prodi,
                'calendar' => $schedule->id_kalender,
            ])->withErrors(['calendar' => 'Kalender SP tidak aktif; data hanya dapat dilihat.']);
        }

        $grades = DB::connection('simak_main')->table('s1m4k_krs as krs')
            ->join('s1m4k_mastermhs as student', 'krs.id_mahasiswa', '=', 'student.id_mahasiswa')
            ->where('krs.id_jadwal', $schedule->id_jadwal)
            ->where('krs.id_kalender', $schedule->id_kalender)
            ->orderBy('student.nim')
            ->get([
                'krs.id_krs', 'krs.nilai_angka', 'krs.nilai_huruf', 'krs.input_nilai',
                'krs.nilai_partisipatif', 'krs.nilai_proyek', 'krs.nilai_quiz',
                'krs.nilai_tugas', 'krs.nilai_uts', 'krs.nilai_uas',
                'student.id_mahasiswa', 'student.nim', 'student.nama_mahasiswa',
            ]);
        $gradeRows = $grades->map(fn (object $grade): array => [
            'id_krs' => (string) $grade->id_krs,
            'nim' => (string) $grade->nim,
            'nama_mahasiswa' => (string) $grade->nama_mahasiswa,
            'nilai_partisipatif' => (string) ($grade->nilai_partisipatif ?? ''),
            'nilai_proyek' => (string) ($grade->nilai_proyek ?? ''),
            'nilai_quiz' => (string) ($grade->nilai_quiz ?? ''),
            'nilai_tugas' => (string) ($grade->nilai_tugas ?? ''),
            'nilai_uts' => (string) ($grade->nilai_uts ?? ''),
            'nilai_uas' => (string) ($grade->nilai_uas ?? ''),
            'nilai_angka' => (string) ($grade->nilai_angka ?? ''),
            'nilai_huruf' => (string) ($grade->nilai_huruf ?? ''),
            'input_nilai' => (string) ($grade->input_nilai ?? 'N'),
        ])->values();
        if ($unlockOnly) {
            $gradeRows = collect();
        }
        $isLocked = $grades->isNotEmpty()
            && $grades->every(fn (object $grade): bool => $grade->input_nilai === 'Y');
        $canEdit = $calendarIsActive && ! $unlockOnly && ($user->role === 'userprodi' || ! $isLocked);
        $gradeRanges = DB::connection('simak_main')->table('s1m4k_masterbobotnilai')
            ->orderBy('nilai_huruf')->get(['nilai_huruf', 'bobot_minimum', 'bobot_maksimum']);
        $evaluationComponents = collect();
        if ($canEdit) {
            try {
                $evaluationComponents = collect($feeder->courseEvaluations((string) $schedule->id_matkul));
            } catch (RuntimeException $exception) {
                report($exception);

                return redirect()->route($gradeRoutePrefix.'.index', ['program' => $schedule->id_prodi])
                    ->withErrors(['feeder' => $exception->getMessage()]);
            }
            if ($evaluationComponents->isEmpty()) {
                return redirect()->route($gradeRoutePrefix.'.index', ['program' => $schedule->id_prodi])
                    ->withErrors(['feeder' => 'Komponen evaluasi belum tersedia di Feeder untuk mata kuliah ini.']);
            }
        }
        $componentWeights = $this->componentWeights($evaluationComponents);
        $enabledComponents = array_map(fn (float $weight): bool => $weight > 0, $componentWeights);
        $oldGrades = $request->old('grades', []);
        if (is_array($oldGrades)) {
            $gradeRows = $gradeRows->map(function (array $row) use ($oldGrades, $enabledComponents): array {
                $oldRow = $oldGrades[$row['id_krs']] ?? [];
                if (! is_array($oldRow)) {
                    return $row;
                }
                foreach (array_keys(array_filter($enabledComponents)) as $field) {
                    if (array_key_exists($field, $oldRow) && is_scalar($oldRow[$field])) {
                        $row[$field] = (string) $oldRow[$field];
                    }
                }

                return $row;
            });
        }

        return view('course-grades.edit', compact(
            'user', 'schedule', 'grades', 'gradeRows', 'isLocked', 'canEdit', 'inputWindowOpen',
            'gradeRanges', 'enabledComponents', 'componentWeights', 'evaluationComponents', 'unlockOnly',
            'shortSemester', 'gradeRoutePrefix'
        ));
    }

    public function update(
        Request $request,
        string $scheduleId,
        LegacyFeederService $feeder,
        ActivityLogger $activityLogger,
        SimakNotificationService $notifications
    ): RedirectResponse {
        $user = $request->user();
        $this->authorizeRole($user);
        abort_unless(in_array($user->role, self::INPUT_ROLES, true), 403);
        $shortSemester = $request->routeIs('short-semester-grades.*');
        $gradeRoutePrefix = $shortSemester ? 'short-semester-grades' : 'grade-management';
        $schedule = $this->accessibleSchedule($user, $scheduleId, (string) $request->input('program', ''), $shortSemester);
        $this->assertTeacherCanEnter($user, $schedule, $shortSemester);

        try {
            $evaluationComponents = collect($feeder->courseEvaluations((string) $schedule->id_matkul));
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->withInput()->withErrors(['feeder' => $exception->getMessage()]);
        }
        $componentWeights = $this->componentWeights($evaluationComponents);
        $enabledComponents = array_map(fn (float $weight): bool => $weight > 0, $componentWeights);
        abort_if(! in_array(true, $enabledComponents, true), 422, 'Komponen evaluasi belum tersedia untuk mata kuliah ini.');

        $submittedGrades = $request->input('grades', []);
        if (is_array($submittedGrades)) {
            foreach ($submittedGrades as $krsId => $components) {
                if (! is_array($components)) {
                    continue;
                }
                foreach ($components as $field => $score) {
                    if (is_string($score)) {
                        $components[$field] = str_replace(',', '.', trim($score));
                    }
                }
                $submittedGrades[$krsId] = $components;
            }
            $request->merge(['grades' => $submittedGrades]);
        }
        $rules = [
            'program' => ['required', 'string', 'max:100'],
            'grades' => ['required', 'array', 'min:1'],
            'grades.*' => ['required', 'array'],
        ];
        foreach (self::COMPONENT_FIELDS as $field) {
            if ($enabledComponents[$field] ?? false) {
                $rules['grades.*.'.$field] = ['nullable', 'numeric', 'between:0,100'];
            } else {
                $rules['grades.*.'.$field] = ['prohibited'];
            }
        }
        $validated = $request->validate($rules);
        $connection = DB::connection('simak_main');
        $gradeScale = $connection->table('s1m4k_masterbobotnilai')
            ->orderBy('nilai_huruf')
            ->get(['nilai_huruf', 'nilai_indeks', 'bobot_minimum', 'bobot_maksimum']);

        $connection->transaction(function () use (
            $connection, $user, $schedule, $validated, $gradeScale, $activityLogger, $enabledComponents,
            $componentWeights, $notifications, $shortSemester
        ): void {
            $roster = $connection->table('s1m4k_krs as krs')
                ->join('s1m4k_mastermhs as student', 'krs.id_mahasiswa', '=', 'student.id_mahasiswa')
                ->where('krs.id_jadwal', $schedule->id_jadwal)
                ->where('krs.id_kalender', $schedule->id_kalender)
                ->orderBy('student.nim')
                ->lockForUpdate()
                ->get(['krs.*', 'student.nim', 'student.nama_mahasiswa'])
                ->keyBy('id_krs');
            $submittedIds = array_map('strval', array_keys($validated['grades']));
            $rosterIds = $roster->keys()->map('strval')->all();
            sort($submittedIds);
            sort($rosterIds);
            if ($submittedIds !== $rosterIds) {
                throw ValidationException::withMessages([
                    'grades' => 'Daftar nilai berubah. Muat ulang halaman sebelum menyimpan.',
                ]);
            }
            abort_if($roster->isEmpty(), 422, 'Belum ada mahasiswa yang memilih jadwal ini.');
            if (in_array($user->role, ['dosen', 'asdos'], true)) {
                abort_if($roster->every(fn (object $grade): bool => $grade->input_nilai === 'Y'), 422,
                    'Nilai mata kuliah ini sudah disimpan dan hanya dapat dibuka kembali oleh pengelola.');
            }

            foreach ($validated['grades'] as $krsId => $components) {
                [$componentValues, $score, $matchingGrade] = $this->calculateGrade(
                    $components,
                    $enabledComponents,
                    $componentWeights,
                    $gradeScale,
                    'grades.'.$krsId
                );
                $before = $roster->get($krsId);
                $connection->table('s1m4k_krs')->where('id_krs', $krsId)->update([
                    ...$componentValues,
                    'nilai_angka' => (string) $score,
                    'nilai_huruf' => $matchingGrade->nilai_huruf,
                    'nilai_bobot' => $matchingGrade->nilai_indeks,
                    'tgl_update' => now()->toDateString(),
                    'user' => (string) data_get($user, 'legacy_id', ''),
                    'input_nilai' => 'Y',
                ]);
                if (in_array($user->role, ['dosen', 'asdos'], true)) {
                    $connection->table('s1m4k_akm')
                        ->where('id_mahasiswa', $before->id_mahasiswa)
                        ->where('id_kalender', $schedule->id_kalender)
                        ->increment('update_akm');
                }
                $after = $connection->table('s1m4k_krs')->where('id_krs', $krsId)->first();
                $moduleName = $shortSemester ? 'Nilai SP' : 'Nilai Mata Kuliah';
                $activityLogger->record($user, $moduleName, 'Menyimpan nilai mahasiswa', [
                    'data_sebelumnya' => (array) $before,
                    'data_baru' => (array) $after,
                ], (string) $krsId, [
                    'nama' => $before->nama_mahasiswa,
                    'nomor_identitas' => $before->nim,
                    'mata_kuliah' => $schedule->nama_mata_kuliah,
                    'kelas' => $schedule->nama_kelas,
                ]);
                if (in_array($user->role, ['userprodi', 'dosen'], true) && $before->input_nilai !== 'Y') {
                    $notifications->send(
                        'mahasiswa',
                        (string) $before->id_mahasiswa,
                        'course-grade-entered',
                        $shortSemester ? 'Nilai SP Diinput' : 'Nilai Mata Kuliah Diinput',
                        ($shortSemester ? 'Nilai SP ' : 'Nilai mata kuliah ').(string) $schedule->nama_mata_kuliah.' telah diinput dan dapat dilihat setelah persyaratan akademik terpenuhi.',
                        route('khs.index'),
                    );
                }
            }
        });

        return redirect()->route($gradeRoutePrefix.'.index', ['program' => $schedule->id_prodi])
            ->with('status', 'Nilai mahasiswa berhasil disimpan.');
    }

    public function updateShortSemesterGrade(
        Request $request,
        string $scheduleId,
        string $krsId,
        LegacyFeederService $feeder,
        ShortSemesterScheduleFeederService $shortSemesterFeeder,
        ActivityLogger $activityLogger,
        SimakNotificationService $notifications
    ): JsonResponse {
        $user = $request->user();
        $this->authorizeRole($user);
        abort_unless(in_array($user->role, self::INPUT_ROLES, true), 403);
        $schedule = $this->accessibleSchedule(
            $user,
            $scheduleId,
            (string) $request->input('program', ''),
            true
        );
        $this->assertShortSemesterCalendarActive($schedule);
        $this->assertTeacherCanEnter($user, $schedule, true);
        $classId = trim((string) ($schedule->id_kelas_kuliah ?? ''));
        abort_if($classId === '', 422, 'ID kelas Feeder belum tersedia; nilai SP tidak dapat disinkronkan.');

        $connection = DB::connection('simak_main');
        $grade = $connection->table('s1m4k_krs as krs')
            ->join('s1m4k_mastermhs as student', 'krs.id_mahasiswa', '=', 'student.id_mahasiswa')
            ->where('krs.id_krs', $krsId)
            ->where('krs.id_jadwal', $schedule->id_jadwal)
            ->where('krs.id_kalender', $schedule->id_kalender)
            ->where('student.id_prodi', $schedule->id_prodi)
            ->first(['krs.*', 'student.nim', 'student.nama_mahasiswa', 'student.id_registrasi_mahasiswa']);
        abort_if($grade === null, 404, 'KRS mahasiswa tidak ditemukan pada kelas SP ini.');
        $registrationId = trim((string) ($grade->id_registrasi_mahasiswa ?? ''));
        abort_if($registrationId === '', 422, 'ID registrasi Feeder mahasiswa tidak tersedia; nilai SP tidak dapat disinkronkan.');

        try {
            $evaluationComponents = collect($feeder->courseEvaluations((string) $schedule->id_matkul));
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json(['status' => 'failed', 'message' => $exception->getMessage()], 422);
        }
        $componentWeights = $this->componentWeights($evaluationComponents);
        $enabledComponents = array_map(fn (float $weight): bool => $weight > 0, $componentWeights);
        abort_if(! in_array(true, $enabledComponents, true), 422, 'Komponen evaluasi belum tersedia untuk mata kuliah ini.');

        $components = $request->input('components', []);
        if (is_array($components)) {
            foreach ($components as $field => $score) {
                if (is_string($score)) {
                    $components[$field] = str_replace(',', '.', trim($score));
                }
            }
            $request->merge(['components' => $components]);
        }
        $rules = [
            'program' => ['required', 'string', 'max:100'],
            'components' => ['required', 'array'],
        ];
        foreach (self::COMPONENT_FIELDS as $field) {
            $rules['components.'.$field] = ($enabledComponents[$field] ?? false)
                ? ['nullable', 'numeric', 'between:0,100']
                : ['prohibited'];
        }
        $validated = $request->validate($rules);
        $gradeScale = $connection->table('s1m4k_masterbobotnilai')
            ->orderBy('nilai_huruf')
            ->get(['nilai_huruf', 'nilai_indeks', 'bobot_minimum', 'bobot_maksimum']);
        [$componentValues, $score, $matchingGrade] = $this->calculateGrade(
            $validated['components'],
            $enabledComponents,
            $componentWeights,
            $gradeScale,
            'components'
        );

        try {
            $shortSemesterFeeder->updateParticipantGrade($classId, $registrationId, [
                'nilai_angka' => number_format((float) $score, 1, '.', ''),
                'nilai_indeks' => (string) $matchingGrade->nilai_indeks,
                'nilai_huruf' => (string) $matchingGrade->nilai_huruf,
            ]);
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json([
                'status' => 'failed',
                'krs_id' => $krsId,
                'student_name' => (string) $grade->nama_mahasiswa,
                'message' => $exception->getMessage(),
            ], 422);
        }

        $connection->transaction(function () use (
            $connection, $user, $schedule, $grade, $krsId, $componentValues, $score,
            $matchingGrade, $activityLogger, $notifications
        ): void {
            $before = $connection->table('s1m4k_krs')
                ->where('id_krs', $krsId)
                ->where('id_jadwal', $schedule->id_jadwal)
                ->where('id_kalender', $schedule->id_kalender)
                ->lockForUpdate()
                ->first();
            abort_if($before === null, 404, 'KRS mahasiswa tidak ditemukan pada kelas SP ini.');
            $connection->table('s1m4k_krs')->where('id_krs', $krsId)->update([
                ...$componentValues,
                'nilai_angka' => (string) $score,
                'nilai_huruf' => $matchingGrade->nilai_huruf,
                'nilai_bobot' => $matchingGrade->nilai_indeks,
                'tgl_update' => now()->toDateString(),
                'user' => (string) data_get($user, 'legacy_id', ''),
                'input_nilai' => 'Y',
            ]);
            if (in_array($user->role, ['dosen', 'asdos'], true)) {
                $connection->table('s1m4k_akm')
                    ->where('id_mahasiswa', $grade->id_mahasiswa)
                    ->where('id_kalender', $schedule->id_kalender)
                    ->increment('update_akm');
            }
            $after = $connection->table('s1m4k_krs')->where('id_krs', $krsId)->first();
            $activityLogger->record($user, 'Nilai SP', 'Menyimpan nilai mahasiswa', [
                'data_sebelumnya' => (array) $before,
                'data_baru' => (array) $after,
            ], $krsId, [
                'nama' => $grade->nama_mahasiswa,
                'nomor_identitas' => $grade->nim,
                'mata_kuliah' => $schedule->nama_mata_kuliah,
                'kelas' => $schedule->nama_kelas,
            ]);
            if (in_array($user->role, ['userprodi', 'dosen'], true) && $before->input_nilai !== 'Y') {
                $notifications->send(
                    'mahasiswa',
                    (string) $grade->id_mahasiswa,
                    'course-grade-entered',
                    'Nilai SP Diinput',
                    'Nilai SP '.(string) $schedule->nama_mata_kuliah.' telah diinput dan dapat dilihat pada KHS SP.',
                    route('khs.index'),
                );
            }
        });

        return response()->json([
            'status' => 'updated',
            'krs_id' => $krsId,
            'student_name' => (string) $grade->nama_mahasiswa,
            'message' => 'Nilai mahasiswa berhasil diperbarui di Feeder dan SIMAK.',
        ]);
    }

    public function unlock(Request $request, string $scheduleId, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeRole($user);
        abort_unless(in_array($user->role, self::UNLOCK_ROLES, true), 403);
        $shortSemester = $request->routeIs('short-semester-grades.*');
        $gradeRoutePrefix = $shortSemester ? 'short-semester-grades' : 'grade-management';
        $validated = $request->validate(['program' => ['required', 'string', 'max:100']]);
        $schedule = $this->accessibleSchedule($user, $scheduleId, $validated['program'], $shortSemester);
        if ($shortSemester) {
            $this->assertShortSemesterCalendarActive($schedule);
        }
        $connection = DB::connection('simak_main');
        $unlockedCount = $connection->transaction(function () use ($connection, $schedule, $user, $activityLogger, $shortSemester): int {
            $grades = $connection->table('s1m4k_krs as krs')
                ->join('s1m4k_mastermhs as student', 'krs.id_mahasiswa', '=', 'student.id_mahasiswa')
                ->where('krs.id_jadwal', $schedule->id_jadwal)
                ->where('krs.id_kalender', $schedule->id_kalender)
                ->where('krs.id_matkul', $schedule->id_matkul)
                ->where('krs.input_nilai', 'Y')
                ->lockForUpdate()
                ->get(['krs.id_krs', 'krs.input_nilai', 'student.nim', 'student.nama_mahasiswa']);
            if ($grades->isEmpty()) {
                return 0;
            }

            $connection->table('s1m4k_krs')
                ->where('id_jadwal', $schedule->id_jadwal)
                ->where('id_kalender', $schedule->id_kalender)
                ->where('id_matkul', $schedule->id_matkul)
                ->where('input_nilai', 'Y')
                ->update(['input_nilai' => 'N']);
            foreach ($grades as $grade) {
                $activityLogger->record($user, $shortSemester ? 'Nilai SP' : 'Nilai Mata Kuliah', 'Membuka kembali input nilai per kelas', [
                    'data_sebelumnya' => ['input_nilai' => $grade->input_nilai],
                    'data_baru' => ['input_nilai' => 'N'],
                ], (string) $grade->id_krs, [
                    'nama' => $grade->nama_mahasiswa,
                    'nomor_identitas' => $grade->nim,
                    'mata_kuliah' => $schedule->nama_mata_kuliah,
                    'kelas' => $schedule->nama_kelas,
                ]);
            }

            return $grades->count();
        });

        return redirect()->route($gradeRoutePrefix.'.index', ['program' => $schedule->id_prodi])
            ->with('status', $unlockedCount > 0
                ? "Input nilai untuk {$unlockedCount} mahasiswa pada kelas berhasil dibuka kembali."
                : 'Tidak ada input nilai terkunci pada kelas ini.');
    }

    public function print(Request $request, string $scheduleId): Response
    {
        $user = $request->user();
        $this->authorizeRole($user);
        $shortSemester = $request->routeIs('short-semester-grades.*');
        $schedule = $this->accessibleSchedule($user, $scheduleId, $request->query('program'), $shortSemester);
        abort_unless(class_exists(\FPDF::class), 500, 'Library cetak nilai tidak tersedia.');
        $students = DB::connection('simak_main')->table('s1m4k_krs as krs')
            ->leftJoin('s1m4k_mastermhs as student', 'krs.id_mahasiswa', '=', 'student.id_mahasiswa')
            ->where('krs.id_jadwal', $schedule->id_jadwal)
            ->where('krs.id_kalender', $schedule->id_kalender)
            ->orderBy('student.nim')
            ->get(['krs.nilai_angka', 'krs.nilai_huruf', 'student.nim', 'student.nama_mahasiswa']);
        $pdf = new \FPDF('P', 'cm', 'A4');
        $pdf->AddPage();
        $text = fn (string $value): string => $this->pdfText($value);
        $imageDirectory = public_path('images/kkn');
        foreach ([
            [$imageDirectory.'/dikbud.jpg', 2.5],
            [app(\App\Services\InstitutionSettings::class)->logoFilePath(), 17],
        ] as [$image, $x]) {
            if (is_file($image)) {
                $pdf->Image($image, $x, 0.5, 2, 2);
            }
        }
        $pdf->SetFont('Arial', 'B', 11);
        foreach ([
            ...app(\App\Services\InstitutionSettings::class)->ministryNameLines(),
            mb_strtoupper(app(\App\Services\InstitutionSettings::class)->institutionName()),
            $shortSemester ? 'Nilai Semester Pendek' : 'Nilai Mata Kuliah',
            ucwords(mb_strtolower((string) $schedule->nama_mata_kuliah)),
        ] as $line) {
            $pdf->Cell(19.5, 0.5, $text($line), 0, 1, 'C');
        }
        $pdf->Cell(19.5, 0.5, $text($schedule->nama_program_studi.' - Kelas '.$schedule->nama_kelas.' - '.($schedule->namatahun ?: $schedule->tahun)), 0, 1, 'C');
        $pdf->Line(1, 3, 20, 3);
        $pdf->Ln();
        $pdf->SetFont('Times', 'B', 10);
        foreach ([[1.5, 'No'], [2.5, 'NPM'], [11.5, 'Nama Mahasiswa'], [2, 'Nilai Angka'], [2, 'Nilai Huruf']] as [$width, $label]) {
            $pdf->Cell($width, 0.5, $text($label), 'LRTB', 0, 'C');
        }
        $pdf->Ln();
        $pdf->SetFont('Times', '', 10);
        foreach ($students as $index => $student) {
            if ($pdf->GetY() > 25) {
                $pdf->AddPage();
            }
            $pdf->Cell(1.5, 0.5, (string) ($index + 1), 'LRTB', 0, 'C');
            $pdf->Cell(2.5, 0.5, $text((string) $student->nim), 'LRTB', 0, 'C');
            $pdf->Cell(11.5, 0.5, $text(ucwords(mb_strtolower((string) $student->nama_mahasiswa))), 'LRTB', 0);
            $pdf->Cell(2, 0.5, (string) $student->nilai_angka, 'LRTB', 0, 'C');
            $pdf->Cell(2, 0.5, $text((string) $student->nilai_huruf), 'LRTB', 0, 'C');
            $pdf->Ln();
        }

        return response($pdf->Output('S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.($shortSemester ? 'Nilai-SP-' : 'Nilai-Matakuliah-').$schedule->id_jadwal.'.pdf"',
        ]);
    }

    private function programs(object $user, bool $shortSemester = false): Collection
    {
        $connection = DB::connection('simak_main');
        if ($user->role === 'admin') {
            return $connection->table('s1m4k_masterprodi')->orderBy('nama_program_studi')
                ->get(['id_prodi', 'nama_program_studi', 'nama_jenjang_pendidikan']);
        }
        if (in_array($user->role, ['userprodi', 'usertu'], true)) {
            $programId = trim((string) data_get($user, 'id_prodi'));
            abort_unless($programId !== '', 403);

            return $connection->table('s1m4k_masterprodi')->where('id_prodi', $programId)
                ->get(['id_prodi', 'nama_program_studi', 'nama_jenjang_pendidikan']);
        }
        if ($user->role === 'pimfak') {
            $facultyId = trim((string) data_get($user, 'id_fakultas'));
            abort_unless($facultyId !== '', 403);

            return $connection->table('s1m4k_masterprodi')->where('id_fakultas', $facultyId)
                ->orderBy('nama_program_studi')
                ->get(['id_prodi', 'nama_program_studi', 'nama_jenjang_pendidikan']);
        }
        if (in_array($user->role, ['admin', 'pimuniv'], true)) {
            return $connection->table('s1m4k_masterprodi')->orderBy('nama_program_studi')
                ->get(['id_prodi', 'nama_program_studi', 'nama_jenjang_pendidikan']);
        }

        $teacherId = $this->teacherId($user);
        $query = $this->scheduleQuery($shortSemester)
            ->where(function ($builder) use ($teacherId): void {
                foreach (self::TEACHER_LIST_SLOTS as $slot) {
                    $builder->orWhere('schedule.'.$slot, $teacherId);
                }
            });

        return $query->distinct()->orderBy('program.nama_program_studi')
            ->get(['program.id_prodi', 'program.nama_program_studi', 'program.nama_jenjang_pendidikan']);
    }

    private function scheduleQuery(bool $shortSemester = false)
    {
        $query = DB::connection('simak_main')->table('s1m4k_jadwal as schedule')
            ->join('s1m4k_kalender as calendar', 'schedule.id_kalender', '=', 'calendar.id_kalender')
            ->join('s1m4k_masterprodi as program', 'schedule.id_prodi', '=', 'program.id_prodi')
            ->leftJoin('s1m4k_mastermk as course', 'schedule.id_matkul', '=', 'course.id_matkul')
            ->leftJoin('s1m4k_masterdosen as lecturer', 'schedule.dosen1', '=', 'lecturer.id_dosen')
            ->select([
                'schedule.id_jadwal', 'schedule.id_prodi', 'schedule.id_kalender', 'schedule.id_kelas_kuliah', 'schedule.id_matkul', 'schedule.dosen1', 'schedule.dosen2',
                'schedule.nama_kelas', 'calendar.tahun', 'calendar.namatahun', 'calendar.status_sp', 'calendar.tgl_mulai_isinilai',
                'calendar.tgl_akhir_isinilai', 'program.nama_program_studi', 'program.nama_jenjang_pendidikan',
                'course.kode_mata_kuliah', 'course.nama_mata_kuliah', 'lecturer.gelar_depan',
                'lecturer.nama_dosen', 'lecturer.gelar_belakang',
            ]);

        if ($shortSemester) {
            return $query->addSelect('calendar.status_sp')
                ->whereColumn('course.id_prodi', 'schedule.id_prodi')
                ->whereExists(function ($curriculum): void {
                    $curriculum->selectRaw('1')
                        ->from('s1m4k_kurikulum as curriculum')
                        ->whereColumn('curriculum.id_kurikulum', 'calendar.id_kalender')
                        ->whereColumn('curriculum.id_prodi', 'calendar.id_prodi')
                        ->whereColumn('curriculum.id_semester', 'calendar.tahun')
                        ->where(function ($shortSemesterLabel): void {
                            $shortSemesterLabel->where('calendar.periode', 'like', '% SP')
                                ->orWhere('calendar.periode', 'like', '%Pendek')
                                ->orWhere('calendar.namatahun', 'like', 'SP %')
                                ->orWhere('curriculum.semester_mulai_berlaku', 'like', '%Pendek');
                        });
                });
        }

        return $query->where('calendar.status', 'Y');
    }

    private function activeCalendar(string $programId, bool $shortSemester = false): ?object
    {
        if ($shortSemester) {
            return DB::connection('simak_main')->table('s1m4k_kalender as calendar')
                ->join('s1m4k_kurikulum as curriculum', function ($join): void {
                    $join->on('curriculum.id_kurikulum', '=', 'calendar.id_kalender')
                        ->on('curriculum.id_prodi', '=', 'calendar.id_prodi')
                        ->on('curriculum.id_semester', '=', 'calendar.tahun');
                })
                ->where('calendar.id_prodi', $programId)
                ->where('calendar.status_sp', 'Y')
                ->where(function ($shortSemesterLabel): void {
                    $shortSemesterLabel->where('calendar.periode', 'like', '% SP')
                        ->orWhere('calendar.periode', 'like', '%Pendek')
                        ->orWhere('calendar.namatahun', 'like', 'SP %')
                        ->orWhere('curriculum.semester_mulai_berlaku', 'like', '%Pendek');
                })
                ->orderByDesc('calendar.tahun')
                ->first([
                    'calendar.id_kalender', 'calendar.id_prodi', 'calendar.tahun', 'calendar.namatahun',
                    'calendar.tgl_mulai_isinilai', 'calendar.tgl_akhir_isinilai',
                ]);
        }

        return AcademicCalendarScope::excludeShortSemesters(
            DB::connection('simak_main')->table('s1m4k_kalender')
        )
            ->where('id_prodi', $programId)->where('status', 'Y')->orderByDesc('tahun')->first();
    }

    private function gradeCalendars(string $programId, bool $shortSemester): Collection
    {
        if (! $shortSemester) {
            $calendar = $this->activeCalendar($programId);

            return $calendar === null ? collect() : collect([$calendar]);
        }

        return DB::connection('simak_main')->table('s1m4k_kalender as calendar')
            ->join('s1m4k_kurikulum as curriculum', function ($join): void {
                $join->on('curriculum.id_kurikulum', '=', 'calendar.id_kalender')
                    ->on('curriculum.id_prodi', '=', 'calendar.id_prodi')
                    ->on('curriculum.id_semester', '=', 'calendar.tahun');
            })
            ->where('calendar.id_prodi', $programId)
            ->where(function ($shortSemesterLabel): void {
                $shortSemesterLabel->where('calendar.periode', 'like', '% SP')
                    ->orWhere('calendar.periode', 'like', '%Pendek')
                    ->orWhere('calendar.namatahun', 'like', 'SP %')
                    ->orWhere('curriculum.semester_mulai_berlaku', 'like', '%Pendek');
            })
            ->orderByDesc('calendar.tahun')
            ->get([
                'calendar.id_kalender', 'calendar.id_prodi', 'calendar.tahun', 'calendar.namatahun',
                'calendar.status_sp', 'calendar.tgl_mulai_isinilai', 'calendar.tgl_akhir_isinilai',
            ]);
    }

    private function scheduleCalendar(object $schedule): object
    {
        return (object) [
            'id_kalender' => $schedule->id_kalender,
            'id_prodi' => $schedule->id_prodi,
            'tahun' => $schedule->tahun,
            'namatahun' => $schedule->namatahun,
            'status_sp' => $schedule->status_sp,
            'tgl_mulai_isinilai' => $schedule->tgl_mulai_isinilai,
            'tgl_akhir_isinilai' => $schedule->tgl_akhir_isinilai,
        ];
    }

    private function assertShortSemesterCalendarActive(object $schedule): void
    {
        abort_unless((string) ($schedule->status_sp ?? '') === 'Y', 403, 'Kalender SP tidak aktif; data hanya dapat dilihat.');
    }

    private function accessibleSchedule(
        object $user,
        string $scheduleId,
        ?string $requestedProgram = null,
        bool $shortSemester = false
    ): object {
        $query = $this->scheduleQuery($shortSemester)->where('schedule.id_jadwal', $scheduleId);
        if (in_array($user->role, ['userprodi', 'usertu'], true)) {
            $programId = trim((string) data_get($user, 'id_prodi'));
            abort_unless($programId !== '', 403);
            $query->where('schedule.id_prodi', $programId);
        } elseif (in_array($user->role, ['pimuniv', 'pimfak'], true)) {
            $programs = $this->programs($user, $shortSemester);
            $programId = filled($requestedProgram) ? (string) $requestedProgram : null;
            if ($programId !== null) {
                abort_unless($programs->contains('id_prodi', $programId), 404);
                $query->where('schedule.id_prodi', $programId);
            } else {
                $query->whereIn('schedule.id_prodi', $programs->pluck('id_prodi'));
            }
        } elseif (in_array($user->role, ['dosen', 'asdos'], true)) {
            $this->applyTeacherScope($query, $user);
        }
        if (filled($requestedProgram)) {
            $query->where('schedule.id_prodi', $requestedProgram);
        }
        $schedule = $query->first();
        abort_if($schedule === null, 404);

        return $schedule;
    }

    private function applyTeacherScope($query, object $user): void
    {
        $teacherId = $this->teacherId($user);
        $query->where(function ($builder) use ($teacherId): void {
            foreach (self::TEACHER_GRADE_SLOTS as $slot) {
                $builder->orWhere('schedule.'.$slot, $teacherId);
            }
        });
    }

    private function teacherId(object $user): string
    {
        $connection = DB::connection('simak_main');
        $id = $user->role === 'dosen'
            ? $connection->table('s1m4k_masterdosen')->where('nidn', data_get($user, 'login'))->value('id_dosen')
            : $connection->table('s1m4k_masterasdos')->where('kdasisten', data_get($user, 'login'))->value('id_asdos');
        abort_if($id === null, 403);

        return (string) $id;
    }

    private function evaluationField(array $evaluation): ?string
    {
        $kind = trim((string) ($evaluation['jenis_evaluasi'] ?? ''));
        $name = strtoupper(trim((string) ($evaluation['nama_evaluasi'] ?? '')));

        return match (true) {
            $kind === 'Aktivitas Partisipatif' => 'nilai_partisipatif',
            $kind === 'Hasil Proyek' => 'nilai_proyek',
            $kind === 'Kognitif/ Pengetahuan' && $name === 'QIZ' => 'nilai_quiz',
            $kind === 'Kognitif/ Pengetahuan' && $name === 'TGS' => 'nilai_tugas',
            $kind === 'Kognitif/ Pengetahuan' && $name === 'UTS' => 'nilai_uts',
            $kind === 'Kognitif/ Pengetahuan' && $name === 'UAS' => 'nilai_uas',
            default => null,
        };
    }

    private function componentWeights(Collection $evaluationComponents): array
    {
        $weights = array_fill_keys(self::COMPONENT_FIELDS, 0.0);
        foreach ($evaluationComponents as $evaluation) {
            $field = $this->evaluationField($evaluation);
            $weight = (float) ($evaluation['bobot_evaluasi'] ?? 0);
            if ($field !== null && $weight > 0) {
                $weights[$field] += $weight;
            }
        }

        return $weights;
    }

    private function calculateGrade(
        array $components,
        array $enabledComponents,
        array $componentWeights,
        Collection $gradeScale,
        string $errorKey
    ): array {
        $componentValues = [];
        $total = 0.0;
        foreach (self::COMPONENT_FIELDS as $field) {
            $componentValue = ($enabledComponents[$field] ?? false) ? (float) ($components[$field] ?? 0) : 0.0;
            $componentValues[$field] = number_format($componentValue, 2, '.', '');
            $total += $componentValue * ($componentWeights[$field] / 100);
        }
        if ($total > 100) {
            throw ValidationException::withMessages([
                $errorKey => 'Akumulasi nilai komponen tidak boleh melebihi 100.',
            ]);
        }

        $score = round($total);
        $matchingGrade = $gradeScale->filter(fn (object $grade): bool => is_numeric($grade->bobot_minimum)
            && is_numeric($grade->bobot_maksimum)
            && $score >= (float) $grade->bobot_minimum
            && $score <= (float) $grade->bobot_maksimum)->last();
        if ($matchingGrade === null) {
            throw ValidationException::withMessages([
                $errorKey => 'Nilai angka tidak termasuk rentang bobot nilai yang tersedia.',
            ]);
        }

        return [$componentValues, $score, $matchingGrade];
    }

    private function isInputWindowOpen(?object $calendar): bool
    {
        if (! $calendar || ! $calendar->tgl_mulai_isinilai || ! $calendar->tgl_akhir_isinilai) {
            return false;
        }
        $today = now()->toDateString();

        return $today >= $calendar->tgl_mulai_isinilai && $today <= $calendar->tgl_akhir_isinilai;
    }

    private function inputWindowMessage(?object $calendar): string
    {
        if (! $calendar || ! $calendar->tgl_mulai_isinilai || ! $calendar->tgl_akhir_isinilai) {
            return 'Jadwal pengisian nilai belum ditetapkan pada kalender aktif.';
        }
        if (now()->toDateString() < $calendar->tgl_mulai_isinilai) {
            return 'Waktu pengisian nilai belum dimulai.';
        }

        return 'Waktu pengisian nilai telah berakhir.';
    }

    private function assertTeacherCanEnter(object $user, object $schedule, bool $shortSemester = false): void
    {
        if (! in_array($user->role, ['dosen', 'asdos'], true)) {
            return;
        }
        $calendar = $this->activeCalendar((string) $schedule->id_prodi, $shortSemester);
        abort_unless($this->isInputWindowOpen($calendar), 422, $this->inputWindowMessage($calendar));
        $locked = DB::connection('simak_main')->table('s1m4k_krs')
            ->where('id_jadwal', $schedule->id_jadwal)
            ->where('id_kalender', $schedule->id_kalender)
            ->where('input_nilai', '!=', 'Y')->doesntExist();
        abort_if($locked, 422, 'Nilai mata kuliah ini sudah disimpan dan hanya dapat dibuka kembali oleh pengelola.');
    }

    private function pdfText(string $value): string
    {
        $converted = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $value);

        return $converted === false ? $value : $converted;
    }

    private function authorizeRole(object $user): void
    {
        abort_unless(in_array($user->role, self::ROLES, true), 403);
    }
}
