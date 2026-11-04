<?php

namespace App\Http\Controllers;

use App\Support\AcademicCalendarScope;
use App\Support\LecturerName;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class GradeValidationController extends Controller
{
    private const ROLES = ['admin', 'validasi', 'lppm'];

    private const STUDENT_STATUSES = ['A', 'C', 'G', 'N', 'Z', 'M'];

    public function index(Request $request): View
    {
        $this->authorizeRole($request);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:120']]);
        $search = trim((string) ($filters['search'] ?? ''));
        $students = $this->studentQuery()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($builder) use ($search): void {
                    $builder->where('student.nim', 'like', '%'.$search.'%')
                        ->orWhere('student.nama_mahasiswa', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('student.id_prodi')->orderBy('student.nim')
            ->paginate(25)->withQueryString();

        return view('grade-validation.index', compact('students', 'search'));
    }

    public function khs(Request $request, string $student): View
    {
        $this->authorizeRole($request);
        $record = $this->findStudent($student);
        $calendars = AcademicCalendarScope::excludeShortSemesters(
            DB::connection('simak_main')->table('s1m4k_krs as krs')
                ->join('s1m4k_kalender as calendar', 'krs.id_kalender', '=', 'calendar.id_kalender')
                ->where('krs.id_mahasiswa', $record->id_mahasiswa)
                ->where('calendar.id_prodi', $record->id_prodi),
            'calendar.tahun'
        )
            ->select('calendar.id_kalender', 'calendar.tahun', 'calendar.namatahun', 'calendar.status')
            ->distinct()->orderByDesc('calendar.tahun')->get();
        $calendarId = trim((string) $request->query('calendar', ''));
        $calendar = $calendarId === ''
            ? ($calendars->firstWhere('status', 'Y') ?? $calendars->first())
            : $calendars->firstWhere('id_kalender', $calendarId);
        abort_if($calendarId !== '' && $calendar === null, 404, 'Kalender akademik mahasiswa tidak ditemukan.');
        $report = $calendar ? $this->semesterReport($record, $calendar) : null;

        return view('grade-validation.khs', compact('record', 'calendars', 'calendar', 'report'));
    }

    public function transcript(Request $request, string $student): View
    {
        $this->authorizeRole($request);
        $record = $this->findStudent($student);
        $report = $this->transcriptReport($record);

        return view('grade-validation.transcript', compact('record', 'report'));
    }

    private function studentQuery()
    {
        return DB::connection('simak_main')->table('s1m4k_mastermhs as student')
            ->leftJoin('s1m4k_masterprodi as program', 'student.id_prodi', '=', 'program.id_prodi')
            ->leftJoin('s1m4k_masterfakultas as faculty', 'student.id_fakultas', '=', 'faculty.id_fakultas')
            ->whereIn('student.id_status_mahasiswa', self::STUDENT_STATUSES)
            ->whereRaw("TRIM(COALESCE(student.nama_mahasiswa, '')) <> ''")
            ->whereRaw("TRIM(COALESCE(student.id_mahasiswa, '')) <> ''")
            ->select([
                'student.id_mahasiswa', 'student.nim', 'student.nama_mahasiswa', 'student.id_prodi',
                'student.id_status_mahasiswa', 'student.nama_status_mahasiswa', 'student.dosen_pa',
                'program.nama_program_studi', 'faculty.nama_fakultas',
            ]);
    }

    private function findStudent(string $studentId): object
    {
        $student = $this->studentQuery()->where('student.id_mahasiswa', $studentId)->first();
        abort_if($student === null, 404, 'Mahasiswa tidak ditemukan dalam cakupan validasi nilai.');

        return $student;
    }

    private function semesterReport(object $student, object $calendar): array
    {
        $courses = DB::connection('simak_main')->table('s1m4k_krs as krs')
            ->leftJoin('s1m4k_mastermk as course', 'krs.id_matkul', '=', 'course.id_matkul')
            ->where('krs.id_mahasiswa', $student->id_mahasiswa)
            ->where('krs.id_kalender', $calendar->id_kalender)
            ->orderBy('course.semester')->orderBy('course.nama_mata_kuliah')
            ->get([
                'krs.id_matkul', 'krs.nilai_huruf', 'krs.nilai_bobot', 'course.kode_mata_kuliah',
                'course.nama_mata_kuliah', 'course.semester', 'course.sks_mata_kuliah',
            ])
            ->groupBy('id_matkul')
            ->map(fn (Collection $attempts): object => $attempts->first())
            ->values();
        foreach ($courses as $course) {
            $course->nilai_bobot = (float) ($course->nilai_bobot ?? 0);
            $course->sks_mata_kuliah = (float) ($course->sks_mata_kuliah ?? 0);
            $course->mutu = $course->nilai_bobot * $course->sks_mata_kuliah;
        }
        $sksSemester = (float) $courses->sum(fn (object $course): float => $course->sks_mata_kuliah);
        $qualityPoints = (float) $courses->sum(fn (object $course): float => $course->mutu);
        $ips = $sksSemester > 0 ? $qualityPoints / $sksSemester : 0.0;
        $history = DB::connection('simak_main')->table('s1m4k_krs as krs')
            ->leftJoin('s1m4k_mastermk as course', 'krs.id_matkul', '=', 'course.id_matkul')
            ->where('krs.id_mahasiswa', $student->id_mahasiswa)
            ->where('krs.tahun', '<=', $calendar->tahun)
            ->orderBy('krs.tahun')
            ->get(['krs.id_matkul', 'krs.nilai_bobot', 'course.sks_mata_kuliah'])
            ->groupBy('id_matkul')
            ->map(fn (Collection $attempts): object => $attempts->sortByDesc(fn (object $attempt): float => (float) ($attempt->nilai_bobot ?? 0))->first());
        $sksProgram = (float) $history->sum(fn (object $course): float => (float) ($course->sks_mata_kuliah ?? 0));
        $cumulativeQuality = (float) $history->sum(fn (object $course): float => (float) ($course->nilai_bobot ?? 0) * (float) ($course->sks_mata_kuliah ?? 0));
        $sksPassed = (float) $history->sum(fn (object $course): float => (float) ($course->nilai_bobot ?? 0) * (float) ($course->sks_mata_kuliah ?? 0) > 0
            ? (float) ($course->sks_mata_kuliah ?? 0)
            : 0.0);
        $advisor = DB::connection('simak_main')->table('s1m4k_masterdosen')
            ->where('id_dosen', $student->dosen_pa ?? null)->first(['gelar_depan', 'nama_dosen', 'gelar_belakang']);
        $advisorName = LecturerName::format($advisor?->nama_dosen, $advisor?->gelar_depan, $advisor?->gelar_belakang);

        return [
            'courses' => $courses,
            'sks_semester' => $sksSemester,
            'quality_points' => $qualityPoints,
            'ips' => $ips,
            'ipk' => $sksProgram > 0 ? $cumulativeQuality / $sksProgram : 0.0,
            'sks_program' => $sksProgram,
            'sks_lulus' => $sksPassed,
            'maximum_credits' => match (true) {
                $ips >= 3.0 => 24, $ips >= 2.0 => 19, default => 14
            },
            'advisor' => $advisorName,
        ];
    }

    private function transcriptReport(object $student): array
    {
        $attempts = DB::connection('simak_main')->table('s1m4k_krs as krs')
            ->leftJoin('s1m4k_mastermk as course', 'krs.id_matkul', '=', 'course.id_matkul')
            ->where('krs.id_mahasiswa', $student->id_mahasiswa)
            ->orderBy('course.semester')->orderBy('course.nama_mata_kuliah')
            ->get([
                'krs.id_matkul', 'krs.nilai_huruf', 'krs.nilai_bobot', 'course.kode_mata_kuliah',
                'course.nama_mata_kuliah', 'course.semester', 'course.sks_mata_kuliah',
            ]);
        $courses = $attempts->groupBy('id_matkul')->map(function (Collection $records): object {
            $best = $records->sortByDesc(fn (object $record): float => (float) ($record->nilai_bobot ?? 0))->first();
            $best->nilai_bobot = (float) ($best->nilai_bobot ?? 0);
            $best->nilai_huruf = $this->letterGrade($best->nilai_bobot, $best->nilai_huruf);
            $best->sks_mata_kuliah = (float) ($best->sks_mata_kuliah ?? 0);
            $best->mutu = $best->nilai_bobot * $best->sks_mata_kuliah;

            return $best;
        })->sortBy(fn (object $course): string => str_pad((string) $course->semester, 8, '0', STR_PAD_LEFT).(string) $course->nama_mata_kuliah)->values();
        $totalSks = (float) $courses->sum(fn (object $course): float => $course->sks_mata_kuliah);
        $totalQuality = (float) $courses->sum(fn (object $course): float => $course->mutu);

        return [
            'courses' => $courses,
            'total_sks' => $totalSks,
            'total_quality' => $totalQuality,
            'ipk' => $totalSks > 0 ? $totalQuality / $totalSks : 0.0,
        ];
    }

    private function letterGrade(float $weight, ?string $original): string
    {
        return match (number_format($weight, 2, '.', '')) {
            '4.00' => 'A', '3.50' => 'AB', '3.00' => 'B', '2.50' => 'BC',
            '2.00' => 'C', '1.00' => 'D', default => $original ?: 'E',
        };
    }

    private function authorizeRole(Request $request): void
    {
        abort_unless(in_array($request->user()?->role, self::ROLES, true), 403);
    }
}
