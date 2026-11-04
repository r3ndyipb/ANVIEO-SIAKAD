<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LmsDashboardController extends Controller
{
    private const ROLES = ['admin', 'pimuniv', 'pimfak', 'userprodi', 'usertu', 'dosen', 'mahasiswa'];

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless(in_array($user->role, self::ROLES, true), 403);
        $filters = $request->validate(['calendar' => ['nullable', 'string', 'max:5']]);
        $teacherId = $user->role === 'dosen'
            ? DB::connection('simak_main')->table('s1m4k_masterdosen')->where('nidn', data_get($user, 'login'))->value('id_dosen')
            : null;
        abort_if($user->role === 'dosen' && $teacherId === null, 403, 'Data dosen tidak ditemukan.');
        $teacherId = $teacherId === null ? null : (string) $teacherId;
        $programs = $this->programsFor($user, $teacherId);
        $programIds = $programs->pluck('id_prodi')->map(static fn (mixed $id): string => (string) $id)->values();
        $studentId = $user->role === 'mahasiswa'
            ? DB::connection('simak_main')->table('s1m4k_mastermhs')->where('nim', $user->login)->value('id_mahasiswa')
            : null;
        abort_if($user->role === 'mahasiswa' && $studentId === null, 403, 'Data mahasiswa tidak ditemukan.');
        $calendarRows = $this->calendarRows($programIds, $studentId, $teacherId);
        $calendarOptions = $calendarRows->groupBy('tahun')->map(function (Collection $rows, string|int $year): array {
            $label = $rows->first(fn (object $row): bool => filled($row->namatahun))?->namatahun;

            return [
                'year' => (string) $year,
                'label' => filled($label) ? (string) $label : (string) $year,
                'active' => $rows->contains(fn (object $row): bool => $row->status === 'Y'),
            ];
        })->sortByDesc('year')->values();
        $selectedYear = (string) ($filters['calendar'] ?? '');
        if ($selectedYear !== '' && ! $calendarOptions->contains(fn (array $term): bool => $term['year'] === $selectedYear)) {
            throw ValidationException::withMessages(['calendar' => 'Tahun akademik tidak tersedia dalam cakupan akun ini.']);
        }
        if ($selectedYear === '') {
            $selectedYear = (string) ($calendarOptions->firstWhere('active', true)['year'] ?? $calendarOptions->first()['year'] ?? '');
        }
        $selectedTerm = $calendarOptions->firstWhere('year', $selectedYear);
        $calendarIds = $selectedTerm === null
            ? collect()
            : $calendarRows->where('tahun', $selectedYear)->pluck('id_kalender')->values();
        $scheduleQuery = DB::connection('simak_main')->table('s1m4k_jadwal as schedule')
            ->whereIn('schedule.id_kalender', $calendarIds)
            ->whereIn('schedule.id_prodi', $programIds);
        if ($studentId !== null) {
            $scheduleQuery->whereExists(fn ($query) => $query->selectRaw('1')
                ->from('s1m4k_krs as krs')
                ->whereColumn('krs.id_jadwal', 'schedule.id_jadwal')
                ->whereColumn('krs.id_kalender', 'schedule.id_kalender')
                ->where('krs.id_mahasiswa', (string) $studentId)
                ->whereExists(fn ($approval) => $approval->selectRaw('1')
                    ->from('s1m4k_pengesahankrs as approval')
                    ->whereColumn('approval.id_mahasiswa', 'krs.id_mahasiswa')
                    ->whereColumn('approval.id_kalender', 'krs.id_kalender')
                    ->whereRaw("TRIM(COALESCE(approval.yang_mengesahkan, '')) <> ''")
                    ->whereRaw("TRIM(COALESCE(approval.nama_kpro, '')) <> ''")));
        }
        if ($teacherId !== null) {
            $scheduleQuery->where(function ($scope) use ($teacherId): void {
                foreach (['dosen1', 'dosen2', 'dosen3', 'dosen4', 'dosen5', 'dosen6', 'dosen7', 'dosen8', 'dosen9', 'dosen10', 'dosen11', 'dosen12', 'dosen13', 'dosen14'] as $slot) {
                    $scope->orWhere('schedule.'.$slot, $teacherId);
                }
            });
        }
        $schedules = $scheduleQuery->get([
            'schedule.id_jadwal', 'schedule.id_matkul', 'schedule.id_prodi', 'schedule.id_kalender',
        ]);
        $scheduleIds = $schedules->pluck('id_jadwal')->unique()->values();
        $courseIds = $schedules->pluck('id_matkul')->filter()->unique()->values();
        $meetingIds = DB::connection('simak_main')->table('lms_pertemuan')
            ->whereIn('id_jadwal', $scheduleIds)->pluck('id_pertemuan');
        $taskIds = DB::connection('simak_main')->table('lms_tugas')
            ->whereIn('id_jadwal', $scheduleIds)->pluck('id_tugas');
        $discussionCount = DB::connection('simak_main')->table('lms_diskusi')
            ->whereIn('id_jadwal', $scheduleIds)->count();
        $examIds = DB::connection('simak_main')->table('lms_ujian')
            ->whereIn('id_pertemuan', $meetingIds)->pluck('id_ujian');
        $attendanceQuery = DB::connection('simak_main')->table('lms_absensi')->whereIn('id_pertemuan', $meetingIds);
        $submissionQuery = DB::connection('simak_main')->table('lms_pengumpulan_tugas')->whereIn('id_tugas', $taskIds);
        $examAttemptQuery = DB::connection('simak_main')->table('lms_hasil_ujian')->whereIn('id_ujian', $examIds);
        if ($studentId !== null) {
            $attendanceQuery->where('id_mahasiswa', (string) $studentId);
            $submissionQuery->where('id_mahasiswa', (string) $studentId);
            $examAttemptQuery->where('id_mahasiswa', (string) $studentId);
        }
        $materialsQuery = DB::connection('simak_main')->table('lms_materi_kuliah')
            ->whereIn('id_matkul', $courseIds);
        if ($studentId !== null) {
            $materialsQuery->where('status_publikasi', 'terbit');
        }
        $counts = [
            'Mata kuliah' => $courseIds->count(),
            'Materi kuliah' => $materialsQuery->distinct('id_materi')->count('id_materi'),
            'Pertemuan kuliah' => $meetingIds->count(),
            'Tugas' => $taskIds->count(),
            'Bank soal' => DB::connection('simak_main')->table('lms_bank_soal')->whereIn('id_matkul', $courseIds)->count(),
            'Diskusi' => $discussionCount,
            'Ujian' => $examIds->count(),
            'Absensi' => $attendanceQuery->count(),
            'Pengumpulan tugas' => $submissionQuery->count(),
            'Pengerjaan ujian' => $examAttemptQuery->count(),
        ];
        $maxCount = max(1, ...array_values($counts));
        $activities = collect($counts)->map(fn (int $count, string $label): array => [
            'label' => $label,
            'value' => $count,
            'width' => (int) round(($count / $maxCount) * 100),
        ])->values();
        [$attendanceTrends, $trendMonths] = $this->attendanceTrends(
            $schedules, $meetingIds, $studentId, $teacherId
        );
        $scopeLabel = $this->scopeLabel($user, $programs);

        return view('lms-dashboard.index', compact(
            'activities', 'calendarOptions', 'selectedTerm', 'selectedYear', 'scopeLabel',
            'attendanceTrends', 'trendMonths'
        ))->with('userRole', $user->role);
    }

    private function programsFor(object $user, ?string $teacherId = null): Collection
    {
        $query = DB::connection('simak_main')->table('s1m4k_masterprodi as program')
            ->leftJoin('s1m4k_masterfakultas as faculty', 'program.id_fakultas', '=', 'faculty.id_fakultas')
            ->where('program.status', 'A');
        if ($teacherId !== null) {
            $query->whereExists(function ($schedules) use ($teacherId): void {
                $schedules->selectRaw('1')->from('s1m4k_jadwal as schedule')
                    ->whereColumn('schedule.id_prodi', 'program.id_prodi')
                    ->where(function ($scope) use ($teacherId): void {
                        foreach (['dosen1', 'dosen2', 'dosen3', 'dosen4', 'dosen5', 'dosen6', 'dosen7', 'dosen8', 'dosen9', 'dosen10', 'dosen11', 'dosen12', 'dosen13', 'dosen14'] as $slot) {
                            $scope->orWhere('schedule.'.$slot, $teacherId);
                        }
                    });
            });
        } elseif ($user->role === 'pimfak') {
            $facultyId = trim((string) data_get($user, 'id_fakultas'));
            $facultyId === ''
                ? $query->whereRaw('1 = 0')
                : $query->where('program.id_fakultas', $facultyId);
        } elseif (in_array($user->role, ['userprodi', 'usertu'], true)) {
            $programId = trim((string) data_get($user, 'id_prodi'));
            $programId === ''
                ? $query->whereRaw('1 = 0')
                : $query->where('program.id_prodi', $programId);
        } elseif ($user->role === 'mahasiswa') {
            $programId = DB::connection('simak_main')->table('s1m4k_mastermhs')
                ->where('nim', $user->login)->value('id_prodi');
            $programId === null
                ? $query->whereRaw('1 = 0')
                : $query->where('program.id_prodi', $programId);
        }

        return $query->orderBy('program.nama_program_studi')->get([
            'program.id_prodi', 'program.id_fakultas', 'program.nama_program_studi',
            'program.nama_jenjang_pendidikan', 'faculty.nama_fakultas',
        ]);
    }

    private function calendarRows(Collection $programIds, ?string $studentId, ?string $teacherId): Collection
    {
        $query = DB::connection('simak_main')->table('s1m4k_kalender')
            ->whereIn('id_prodi', $programIds);
        if ($teacherId !== null) {
            $query->whereIn('id_kalender', function ($schedules) use ($teacherId): void {
                $schedules->select('id_kalender')->from('s1m4k_jadwal')
                    ->where(function ($scope) use ($teacherId): void {
                        foreach (['dosen1', 'dosen2', 'dosen3', 'dosen4', 'dosen5', 'dosen6', 'dosen7', 'dosen8', 'dosen9', 'dosen10', 'dosen11', 'dosen12', 'dosen13', 'dosen14'] as $slot) {
                            $scope->orWhere($slot, $teacherId);
                        }
                    });
            });
        }
        if ($studentId !== null) {
            $registeredCalendarIds = DB::connection('simak_main')->table('s1m4k_krs')
                ->where('id_mahasiswa', $studentId)->distinct()->pluck('id_kalender');
            $query->where(function ($scope) use ($registeredCalendarIds): void {
                $scope->whereIn('id_kalender', $registeredCalendarIds)
                    ->orWhere('status', 'Y');
            });
        }

        return $query->orderByDesc('tahun')->get(['id_kalender', 'id_prodi', 'tahun', 'namatahun', 'status']);
    }

    private function attendanceTrends(
        Collection $schedules,
        Collection $meetingIds,
        ?string $studentId,
        ?string $teacherId
    ): array {
        if ($meetingIds->isEmpty()) {
            return [collect(), collect()];
        }
        $connection = DB::connection('simak_main');
        $meetings = $connection->table('lms_pertemuan as meeting')
            ->join('s1m4k_jadwal as schedule', 'schedule.id_jadwal', '=', 'meeting.id_jadwal')
            ->leftJoin('s1m4k_mastermk as course', 'course.id_matkul', '=', 'schedule.id_matkul')
            ->leftJoin('s1m4k_masterprodi as program', 'program.id_prodi', '=', 'schedule.id_prodi')
            ->whereIn('meeting.id_pertemuan', $meetingIds)
            ->get([
                'meeting.id_pertemuan', 'meeting.id_jadwal', 'meeting.status_pertemuan',
                'meeting.tanggal_kuliah', 'meeting.waktu_selesai',
                'schedule.id_kalender', 'schedule.id_matkul',
                'schedule.id_prodi', 'course.kode_mata_kuliah', 'course.nama_mata_kuliah',
                'program.nama_program_studi',
            ]);
        $enrollmentCounts = $connection->table('s1m4k_krs as krs')
            ->whereIn('krs.id_jadwal', $schedules->pluck('id_jadwal')->unique())
            ->whereIn('krs.id_kalender', $schedules->pluck('id_kalender')->unique())
            ->whereExists(fn ($query) => $query->selectRaw('1')->from('s1m4k_pengesahankrs as approval')
                ->whereColumn('approval.id_mahasiswa', 'krs.id_mahasiswa')
                ->whereColumn('approval.id_kalender', 'krs.id_kalender')
                ->whereRaw("TRIM(COALESCE(approval.yang_mengesahkan, '')) <> ''")
                ->whereRaw("TRIM(COALESCE(approval.nama_kpro, '')) <> ''"))
            ->when($studentId !== null, fn ($query) => $query->where('krs.id_mahasiswa', $studentId))
            ->selectRaw('krs.id_jadwal, krs.id_kalender, COUNT(DISTINCT krs.id_mahasiswa) as jumlah_mahasiswa')
            ->groupBy('krs.id_jadwal', 'krs.id_kalender')
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                $row->id_jadwal.'|'.$row->id_kalender => (int) $row->jumlah_mahasiswa,
            ]);
        $attendance = $connection->table('lms_absensi')
            ->whereIn('id_pertemuan', $meetingIds)
            ->when($studentId !== null, fn ($query) => $query->where('id_mahasiswa', $studentId))
            ->get(['id_pertemuan', 'id_mahasiswa', 'status_kehadiran'])
            ->groupBy('id_pertemuan');
        $groups = [];
        foreach ($meetings as $meeting) {
            $meetingEnd = Carbon::parse(
                $meeting->tanggal_kuliah.' '.($meeting->waktu_selesai ?: '23:59:59'),
                'Asia/Jayapura'
            );
            $attendanceWindowEnded = ! $meetingEnd->isFuture();
            $monthKey = $meetingEnd->format('Y-m');
            $programId = (string) $meeting->id_prodi;
            $courseKey = (string) ($meeting->id_matkul ?: 'schedule-'.$meeting->id_jadwal);
            $courseLabel = trim(($meeting->kode_mata_kuliah ? $meeting->kode_mata_kuliah.' — ' : '')
                .($meeting->nama_mata_kuliah ?: 'Mata kuliah tidak diketahui'));
            $groups[$programId]['label'] = (string) ($meeting->nama_program_studi ?: 'Program studi tidak diketahui');
            $groups[$programId]['courses'][$courseKey]['label'] = $courseLabel;
            $groups[$programId]['courses'][$courseKey]['meeting_count'] =
                ($groups[$programId]['courses'][$courseKey]['meeting_count'] ?? 0) + 1;
            $groups[$programId]['courses'][$courseKey]['months'][$monthKey] ??= [
                'label' => $meetingEnd->copy()->locale('id')->translatedFormat('M Y'),
                'student_present' => 0,
                'student_expected' => 0,
                'lecturer_completed' => 0,
                'lecturer_meetings' => 0,
            ];
            $bucket = &$groups[$programId]['courses'][$courseKey]['months'][$monthKey];
            $enrolled = $enrollmentCounts->get($meeting->id_jadwal.'|'.$meeting->id_kalender, 0);
            $expectedStudents = $studentId === null ? $enrolled : (int) ($enrolled > 0);
            if ($attendanceWindowEnded && $expectedStudents > 0) {
                $presentCount = $attendance->get($meeting->id_pertemuan, collect())
                    ->where('status_kehadiran', 'hadir')->count();
                $bucket['student_expected'] += $expectedStudents;
                $bucket['student_present'] += min($presentCount, $expectedStudents);
            }
            $bucket['lecturer_meetings']++;
            if ($attendanceWindowEnded) {
                $bucket['lecturer_completed']++;
            }
            unset($bucket);
        }
        $trends = collect($groups)->map(function (array $program): array {
            $courses = collect($program['courses'])->map(function (array $course): array {
                ksort($course['months']);
                $points = collect($course['months'])->map(fn (array $values): array => [
                    'month' => $values['label'],
                    'meeting_count' => $values['lecturer_meetings'],
                    'student_attendance' => $values['student_expected'] > 0
                        ? round($values['student_present'] / $values['student_expected'] * 100, 1)
                        : null,
                    'lecturer_attendance' => $values['lecturer_meetings'] > 0
                        ? round($values['lecturer_completed'] / $values['lecturer_meetings'] * 100, 1)
                        : null,
                ])->values();

                return [
                    'label' => $course['label'],
                    'meeting_count' => $course['meeting_count'],
                    'points' => $points,
                ];
            })->sortBy('label')->values();

            return ['label' => $program['label'], 'courses' => $courses];
        })->sortBy('label')->values();

        return [$trends, collect()];
    }

    private function scopeLabel(object $user, Collection $programs): string
    {
        if (in_array($user->role, ['admin', 'pimuniv'], true)) {
            return 'Seluruh universitas';
        }
        if ($user->role === 'pimfak') {
            return $programs->pluck('nama_fakultas')->filter()->unique()->implode(', ')
                ?: 'Cakupan fakultas belum ditetapkan';
        }
        if ($user->role === 'dosen') {
            return 'Mata kuliah yang diampu';
        }
        $program = $programs->first();

        return $program !== null
            ? 'Program studi '.trim($program->nama_program_studi.' '.($program->nama_jenjang_pendidikan ?? ''))
            : 'Cakupan program studi belum ditetapkan';
    }
}
