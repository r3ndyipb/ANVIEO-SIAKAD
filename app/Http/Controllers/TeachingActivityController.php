<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Support\AcademicCalendarScope;
use App\Support\LecturerName;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TeachingActivityController extends Controller
{
    private const ROLES = ['admin', 'pimuniv', 'pimfak', 'userprodi', 'usertu'];

    private const WRITE_ROLES = ['admin', 'userprodi', 'usertu'];

    private const TEACHER_SLOTS = [
        'dosen1', 'dosen2', 'dosen3', 'dosen4', 'dosen5', 'dosen6', 'dosen7',
        'dosen8', 'dosen9', 'dosen10', 'dosen11', 'dosen12', 'dosen13', 'dosen14',
    ];

    public function index(Request $request): View
    {
        $user = $request->user();
        $this->authorizeRole($user);
        $filters = $request->validate(['program' => ['nullable', 'string', 'max:100']]);
        $programs = $this->programs($user);
        $selectedProgram = in_array($user->role, ['userprodi', 'usertu'], true)
            ? trim((string) data_get($user, 'id_prodi'))
            : (string) ($filters['program'] ?? '');
        if (in_array($user->role, ['userprodi', 'usertu'], true)) {
            abort_unless($selectedProgram !== '', 403);
        }
        if ($selectedProgram !== '') {
            abort_unless($programs->contains('id_prodi', $selectedProgram), 404);
        }

        $calendar = $selectedProgram !== '' ? $this->activeCalendar($selectedProgram) : null;
        $schedules = collect();
        if ($calendar !== null) {
            $schedules = DB::connection('simak_main')->table('s1m4k_jadwal as schedule')
                ->join('s1m4k_masterprodi as program', 'schedule.id_prodi', '=', 'program.id_prodi')
                ->leftJoin('s1m4k_mastermk as course', 'schedule.id_matkul', '=', 'course.id_matkul')
                ->where('schedule.id_prodi', $selectedProgram)
                ->where('schedule.id_kalender', $calendar->id_kalender)
                ->orderBy('course.nama_mata_kuliah')
                ->orderBy('schedule.nama_kelas')
                ->get([
                    'schedule.id_jadwal', 'schedule.id_prodi', 'schedule.id_kalender',
                    ...self::TEACHER_SLOTS,
                    'schedule.nama_kelas', 'program.nama_program_studi',
                    'program.nama_jenjang_pendidikan', 'course.nama_mata_kuliah',
                ]);
            $this->attachTeacherActivities($schedules, $calendar);
        }

        return view('teaching-activities.index', [
            'user' => $user,
            'programs' => $programs,
            'selectedProgram' => $selectedProgram,
            'calendar' => $calendar,
            'schedules' => $schedules,
            'canEdit' => in_array($user->role, self::WRITE_ROLES, true),
        ]);
    }

    public function update(Request $request, string $scheduleId, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();
        abort_unless(in_array($user->role, self::WRITE_ROLES, true), 403);
        $validated = $request->validate([
            'program' => ['required', 'string', 'max:100'],
            'teacher_id' => ['required', 'string', 'max:100'],
            'field' => ['required', Rule::in(['rencana', 'realisasi'])],
            'meeting_count' => ['required', 'integer', 'between:1,16'],
        ]);
        $programId = $this->programIdForUser($user, $validated['program']);
        $calendar = $this->activeCalendar($programId);
        abort_if($calendar === null, 422, 'Kalender aktif program studi belum tersedia.');
        $schedule = DB::connection('simak_main')->table('s1m4k_jadwal as schedule')
            ->leftJoin('s1m4k_mastermk as course', 'schedule.id_matkul', '=', 'course.id_matkul')
            ->where('schedule.id_jadwal', $scheduleId)
            ->where('schedule.id_prodi', $programId)
            ->where('schedule.id_kalender', $calendar->id_kalender)
            ->first(['schedule.*', 'course.nama_mata_kuliah']);
        abort_if($schedule === null, 404);

        $assigned = collect(self::TEACHER_SLOTS)->contains(
            fn (string $slot): bool => (string) ($schedule->{$slot} ?? '') === (string) $validated['teacher_id']
        );
        abort_unless($assigned, 404);

        $teacher = $this->teacher($validated['teacher_id']);
        abort_if($teacher === null, 404);
        $connection = DB::connection('simak_main');
        $existing = $connection->table('s1m4k_akd')
            ->where('id_jadwal', $scheduleId)
            ->where('id_dosen', $validated['teacher_id'])
            ->first();
        $before = $existing === null ? null : (array) $existing;
        $values = [
            $validated['field'] => (string) $validated['meeting_count'],
            'ket' => $teacher['type'],
            'id_kalender' => $calendar->id_kalender,
            'tahun' => $calendar->tahun,
            'id_prodi' => $programId,
        ];

        $connection->transaction(function () use (
            $connection, $existing, $schedule, $scheduleId, $validated, $values,
            $user, $activityLogger, $teacher, $programId, $calendar, $before
        ): void {
            if ($existing === null) {
                $connection->table('s1m4k_akd')->insert([
                    'id_akd' => (string) Str::uuid(),
                    'id_jadwal' => $scheduleId,
                    'id_dosen' => $validated['teacher_id'],
                    ...$values,
                ]);
            } else {
                $connection->table('s1m4k_akd')->where('id_akd', $existing->id_akd)->update($values);
            }
            $after = $connection->table('s1m4k_akd')
                ->where('id_jadwal', $scheduleId)
                ->where('id_dosen', $validated['teacher_id'])
                ->first();
            $activityLogger->record($user, 'Aktivitas Mengajar', 'Memperbarui jumlah tatap muka', [
                'id_jadwal' => $scheduleId,
                'mata_kuliah' => $schedule->nama_mata_kuliah,
                'pengampu' => $teacher['name'],
                'field' => $validated['field'],
                'data_sebelumnya' => $before,
                'data_baru' => (array) $after,
            ], (string) $scheduleId, [
                'mata_kuliah' => $schedule->nama_mata_kuliah,
                'program_studi' => $programId,
                'tahun_akademik' => $calendar->namatahun,
            ]);
        });

        return redirect()->route('activity-teaching.index', ['program' => $programId])
            ->with('status', 'Aktivitas mengajar berhasil diperbarui.');
    }

    private function attachTeacherActivities(Collection $schedules, object $calendar): void
    {
        $connection = DB::connection('simak_main');
        $teacherIds = $schedules->flatMap(fn (object $schedule): array => collect(self::TEACHER_SLOTS)
            ->map(fn (string $slot): ?string => filled($schedule->{$slot} ?? null) ? (string) $schedule->{$slot} : null)
            ->filter()->values()->all())->unique()->values();
        $lecturers = $connection->table('s1m4k_masterdosen')->whereIn('id_dosen', $teacherIds)
            ->get(['id_dosen', 'nama_dosen', 'gelar_depan', 'gelar_belakang'])->keyBy('id_dosen');
        $assistants = $connection->table('s1m4k_masterasdos')->whereIn('id_asdos', $teacherIds)
            ->get(['id_asdos', 'nama', 'gelard', 'gelarb'])->keyBy('id_asdos');

        foreach ($schedules as $schedule) {
            $schedule->teacher_activities = collect();
            foreach (self::TEACHER_SLOTS as $slot) {
                $teacherId = trim((string) ($schedule->{$slot} ?? ''));
                if ($teacherId === '') {
                    continue;
                }
                $lecturer = $lecturers->get($teacherId);
                $assistant = $assistants->get($teacherId);
                if ($lecturer !== null) {
                    $name = LecturerName::format(
                        $lecturer->nama_dosen,
                        $lecturer->gelar_depan,
                        $lecturer->gelar_belakang,
                    );
                    $type = 'dosen';
                } elseif ($assistant !== null) {
                    $name = trim(implode(' ', array_filter([
                        $assistant->gelard,
                        $assistant->nama,
                        $assistant->gelarb,
                    ])));
                    $type = 'asdos';
                } else {
                    $name = $teacherId;
                    $type = 'dosen';
                }
                $activity = $connection->table('s1m4k_akd')
                    ->where('id_jadwal', $schedule->id_jadwal)
                    ->where('id_dosen', $teacherId)
                    ->first(['rencana', 'realisasi']);
                $schedule->teacher_activities->push((object) [
                    'teacher_id' => $teacherId,
                    'teacher_name' => $name,
                    'teacher_type' => $type,
                    'rencana' => $activity?->rencana,
                    'realisasi' => $activity?->realisasi,
                ]);
            }
        }
    }

    private function programs(object $user): Collection
    {
        $query = DB::connection('simak_main')->table('s1m4k_masterprodi')
            ->orderBy('nama_program_studi')
            ->select(['id_prodi', 'id_fakultas', 'nama_program_studi', 'nama_jenjang_pendidikan']);
        if ($user->role === 'pimfak') {
            $facultyId = trim((string) data_get($user, 'id_fakultas'));
            abort_unless($facultyId !== '', 403);
            $query->where('id_fakultas', $facultyId);
        } elseif (in_array($user->role, ['userprodi', 'usertu'], true)) {
            $programId = trim((string) data_get($user, 'id_prodi'));
            abort_unless($programId !== '', 403);
            $query->where('id_prodi', $programId);
        }

        return $query->get();
    }

    private function programIdForUser(object $user, string $requestedProgram): string
    {
        $programId = $user->role === 'userprodi'
            ? trim((string) data_get($user, 'id_prodi'))
            : $requestedProgram;
        abort_unless($programId !== '', 403);
        abort_unless($this->programs($user)->contains('id_prodi', $programId), 404);

        return $programId;
    }

    private function activeCalendar(string $programId): ?object
    {
        return AcademicCalendarScope::excludeShortSemesters(
            DB::connection('simak_main')->table('s1m4k_kalender')
        )
            ->where('id_prodi', $programId)
            ->where('status', 'Y')
            ->orderByDesc('tahun')
            ->first();
    }

    private function teacher(string $teacherId): ?array
    {
        $connection = DB::connection('simak_main');
        $lecturer = $connection->table('s1m4k_masterdosen')->where('id_dosen', $teacherId)
            ->first(['id_dosen', 'nama_dosen', 'gelar_depan', 'gelar_belakang']);
        if ($lecturer !== null) {
            return [
                'type' => 'dosen',
                'name' => LecturerName::format($lecturer->nama_dosen, $lecturer->gelar_depan, $lecturer->gelar_belakang),
            ];
        }
        $assistant = $connection->table('s1m4k_masterasdos')->where('id_asdos', $teacherId)
            ->first(['id_asdos', 'nama', 'gelard', 'gelarb']);
        if ($assistant !== null) {
            return [
                'type' => 'asdos',
                'name' => trim(implode(' ', array_filter([$assistant->gelard, $assistant->nama, $assistant->gelarb]))),
            ];
        }

        return null;
    }

    private function authorizeRole(object $user): void
    {
        abort_unless(in_array($user->role, self::ROLES, true), 403);
    }
}
