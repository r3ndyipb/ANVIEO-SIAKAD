<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Services\QrCodeService;
use App\Services\SimakNotificationService;
use App\Support\ChatMessageText;
use App\Support\LecturerName;
use App\Support\Utf8Text;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LmsController extends Controller
{
    private const MANAGERS = ['admin', 'pimuniv', 'pimfak', 'userprodi', 'usertu'];

    private const TEACHER_SLOTS = [
        'dosen1', 'dosen2', 'dosen3', 'dosen4', 'dosen5', 'dosen6', 'dosen7',
        'dosen8', 'dosen9', 'dosen10', 'dosen11', 'dosen12', 'dosen13', 'dosen14',
    ];

    private const MODULES = ['materi', 'pertemuan', 'tugas', 'bank-soal', 'diskusi'];

    public function materials(Request $request): View
    {
        return $this->index($request, 'materi');
    }

    public function meetings(Request $request): View
    {
        return $this->index($request, 'pertemuan');
    }

    public function printMeetingAttendance(Request $request, string $record, QrCodeService $qrCodeService)
    {
        $user = $request->user();
        $this->authorizeModule($user, 'pertemuan');
        abort_unless($user->role === 'dosen' || in_array($user->role, self::MANAGERS, true), 403);
        abort_unless(class_exists(\FPDF::class), 500, 'Library cetak absen tidak tersedia.');

        $meeting = $this->meetingsQuery($user)->where('meeting.id_pertemuan', $record)->first();
        abort_if($meeting === null, 404);
        $students = DB::connection('simak_main')->table('s1m4k_krs as krs')
            ->join('s1m4k_mastermhs as student', 'student.id_mahasiswa', '=', 'krs.id_mahasiswa')
            ->leftJoin('lms_absensi as attendance', function ($join) use ($record): void {
                $join->on('attendance.id_mahasiswa', '=', 'student.id_mahasiswa')
                    ->where('attendance.id_pertemuan', $record);
            })
            ->where('krs.id_jadwal', $meeting->id_jadwal)
            ->where('krs.id_kalender', $meeting->id_kalender)
            ->whereExists(fn ($query) => $query->selectRaw('1')->from('s1m4k_pengesahankrs as approval')
                ->whereColumn('approval.id_mahasiswa', 'student.id_mahasiswa')
                ->where('approval.id_kalender', $meeting->id_kalender)
                ->whereRaw("TRIM(COALESCE(approval.yang_mengesahkan, '')) <> ''")
                ->whereRaw("TRIM(COALESCE(approval.nama_kpro, '')) <> ''"))
            ->orderBy('student.nim')
            ->get(['student.nim', 'student.nama_mahasiswa', 'attendance.status_kehadiran']);
        $schedule = $this->schedule($meeting->id_jadwal);
        $calendarName = DB::connection('simak_main')->table('s1m4k_kalender')
            ->where('id_kalender', $meeting->id_kalender)->value('namatahun');
        $schedule->namatahun = $calendarName ?: '';
        $schedule->hari = $meeting->tanggal_kuliah;
        $schedule->jammulai = substr((string) $meeting->waktu_mulai, 0, 5);
        $schedule->jamselesai = substr((string) $meeting->waktu_selesai, 0, 5);
        $schedule->nama_ruang = $schedule->nama_ruang ?: '-';

        $pdf = new \FPDF('L', 'cm', 'Legal');
        $pdf->AddPage();
        $this->lmsAttendanceHeading($pdf, $schedule, 'DAFTAR HADIR PERKULIAHAN');
        $this->lmsAttendanceTableHead($pdf);
        $pdf->SetFont('Times', '', 8);
        foreach ($students as $index => $student) {
            if ($pdf->GetY() > 16.2) {
                $pdf->AddPage();
                $this->lmsAttendanceHeading($pdf, $schedule, 'DAFTAR HADIR PERKULIAHAN (LANJUTAN)');
                $this->lmsAttendanceTableHead($pdf);
                $pdf->SetFont('Times', '', 8);
            }
            $pdf->Cell(1.2, 0.65, (string) ($index + 1), 'LRTB', 0, 'C');
            $pdf->Cell(2.8, 0.65, $this->pdfText((string) $student->nim), 'LRTB', 0, 'L');
            $pdf->Cell(5.2, 0.65, $this->pdfText(ucwords(mb_strtolower((string) $student->nama_mahasiswa))), 'LRTB', 0, 'L');
            for ($number = 1; $number <= 16; $number++) {
                $pdf->Cell(1.53125, 0.65, '', 'LRTB', 0, 'C');
            }
            $pdf->Ln();
            $x = 10.2 + (((int) $meeting->pertemuan_ke - 1) * 1.53125);
            $pdf->SetDrawColor(22, 163, 74);
            $pdf->SetLineWidth(0.06);
            if ($student->status_kehadiran === 'hadir') {
                $pdf->Line($x + 0.57, $pdf->GetY() - 0.43, $x + 0.7, $pdf->GetY() - 0.3);
                $pdf->Line($x + 0.7, $pdf->GetY() - 0.3, $x + 0.98, $pdf->GetY() - 0.58);
            } else {
                $pdf->SetDrawColor(220, 38, 38);
                $pdf->Line($x + 0.57, $pdf->GetY() - 0.55, $x + 0.98, $pdf->GetY() - 0.15);
                $pdf->Line($x + 0.98, $pdf->GetY() - 0.55, $x + 0.57, $pdf->GetY() - 0.15);
            }
            $pdf->SetDrawColor(0, 0, 0);
        }
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(9.2, 1.1, $this->pdfText('Tanggal Perkuliahan'), 'LRTB', 0, 'R');
        for ($number = 1; $number <= 16; $number++) {
            $pdf->Cell(1.53125, 1.1, $number === (int) $meeting->pertemuan_ke ? $this->pdfText($meeting->tanggal_kuliah) : '', 'LRTB', 0, 'C');
        }

        $pdf->AddPage();
        $pdf->SetAutoPageBreak(false);
        $this->lmsAttendanceHeading($pdf, $schedule, 'BERITA ACARA PERKULIAHAN');
        $pdf->SetFont('Arial', 'B', 8);
        foreach ([[5, 'Pertemuan / Tanggal'], [10, 'Pokok Bahasan'], [14, 'Sub Pokok Bahasan'], [3, 'Ket']] as [$width, $label]) {
            $pdf->Cell($width, 1.2, $this->pdfText($label), 'LRTB', 0, 'C');
        }
        $pdf->Ln();
        $materials = DB::connection('simak_main')->table('lms_pertemuan_materi as link')
            ->join('lms_materi_kuliah as material', 'material.id_materi', '=', 'link.id_materi')
            ->where('link.id_pertemuan', $record)->orderBy('material.judul')->pluck('material.judul');
        if ($materials->isEmpty() && $meeting->id_materi) {
            $legacyTitle = DB::connection('simak_main')->table('lms_materi_kuliah')
                ->where('id_materi', $meeting->id_materi)->value('judul');
            if ($legacyTitle) {
                $materials->push($legacyTitle);
            }
        }
        $pdf->SetFont('Times', '', 8);
        for ($number = 1; $number <= 16; $number++) {
            $isMeeting = $number === (int) $meeting->pertemuan_ke;
            $pdf->Cell(5, 0.55, $this->pdfText($isMeeting
                ? $number.'. '.$meeting->tanggal_kuliah
                : $number.'. ____________'), 'LRTB', 0, 'C');
            $pdf->Cell(10, 0.55, $isMeeting ? $this->pdfText($meeting->judul) : '', 'LRTB', 0, 'C');
            $pdf->Cell(14, 0.55, $isMeeting ? $this->pdfText($materials->implode(', ')) : '', 'LRTB', 0, 'C');
            $pdf->Cell(3, 0.55, $isMeeting ? $this->pdfText(substr((string) $meeting->waktu_mulai, 0, 5).'-'.substr((string) $meeting->waktu_selesai, 0, 5)) : '', 'LRTB', 0, 'C');
            $pdf->Ln();
        }

        $teacherName = LecturerName::format(
            ucwords(mb_strtolower((string) $schedule->nama_dosen)),
            $schedule->gelar_depan,
            $schedule->gelar_belakang,
        );
        $printDate = Carbon::now('Asia/Jayapura')->locale('id')->translatedFormat('j F Y');
        $verificationUrl = URL::signedRoute('lms.meetings.minutes.verify', ['record' => $record]);
        $qrPath = $qrCodeService->generate($verificationUrl, 'simak-lms-attendance-');
        $pdf->Ln(0.25);
        $pdf->SetFont('Times', '', 9);
        $pdf->SetX(22);
        $cityName = app(\App\Services\InstitutionSettings::class)->cityName();
        $pdf->Cell(11, 0.45, $this->pdfText($cityName.', '.$printDate), 0, 1, 'R');
        $qrY = $pdf->GetY() + 0.1;
        try {
            $pdf->Image($qrPath, 29, $qrY, 2.4, 2.4, 'PNG');
        } finally {
            if (is_file($qrPath)) {
                unlink($qrPath);
            }
        }
        $pdf->SetY($qrY + 2.45);
        $pdf->SetX(22);
        $pdf->SetFont('Times', 'B', 9);
        $pdf->Cell(11, 0.4, $this->pdfText($teacherName ?: '-'), 0, 1, 'R');
        $pdf->SetX(22);
        $pdf->SetFont('Times', '', 9);
        $pdf->Cell(11, 0.4, $this->pdfText('NIP. '.($schedule->nip ?: '-')), 0, 1, 'R');

        $filename = Str::slug('Absen-'.$schedule->nama_mata_kuliah.'-'.$schedule->nama_kelas.'-pertemuan-'.$meeting->pertemuan_ke) ?: 'absen-pertemuan';

        return response($pdf->Output('S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'.pdf"',
        ]);
    }

    public function verifyPrintedMeeting(string $record): View
    {
        $meeting = DB::connection('simak_main')->table('lms_pertemuan as meeting')
            ->join('s1m4k_jadwal as schedule', 'schedule.id_jadwal', '=', 'meeting.id_jadwal')
            ->join('s1m4k_masterprodi as program', 'program.id_prodi', '=', 'schedule.id_prodi')
            ->join('s1m4k_kalender as calendar', 'calendar.id_kalender', '=', 'schedule.id_kalender')
            ->leftJoin('s1m4k_mastermk as course', 'course.id_matkul', '=', 'schedule.id_matkul')
            ->leftJoin('s1m4k_masterdosen as lecturer', 'lecturer.id_dosen', '=', 'schedule.dosen1')
            ->leftJoin('s1m4k_masterruang as room', 'room.id_ruang', '=', 'schedule.id_ruang')
            ->where('meeting.id_pertemuan', $record)
            ->first([
                'meeting.id_pertemuan', 'meeting.pertemuan_ke', 'meeting.judul', 'meeting.tanggal_kuliah',
                'meeting.waktu_mulai', 'meeting.waktu_selesai', 'schedule.nama_kelas',
                'program.nama_program_studi', 'calendar.namatahun', 'course.nama_mata_kuliah',
                'room.nama_ruang', 'lecturer.nama_dosen', 'lecturer.gelar_depan',
                'lecturer.gelar_belakang', 'lecturer.nip',
            ]);
        abort_if($meeting === null, 404);

        return view('lms.meeting-verification', ['meeting' => $meeting]);
    }

    public function tasks(Request $request): View
    {
        return $this->index($request, 'tugas');
    }

    public function questions(Request $request): View
    {
        return $this->index($request, 'bank-soal');
    }

    public function discussions(Request $request): View
    {
        return $this->index($request, 'diskusi');
    }

    public function index(Request $request, string $module): View
    {
        $this->authorizeModule($request->user(), $module);
        $user = $request->user();
        $selectedLecturerId = trim((string) $request->query('id_dosen', ''));
        $selectedCourseId = trim((string) $request->query('id_matkul', ''));
        $selectedClassId = trim((string) $request->query('id_jadwal', ''));
        $selectedProgramId = trim((string) $request->query('id_prodi', ''));
        $selectedQuestionType = trim((string) $request->query('jenis_soal', ''));
        $materialViewer = $module === 'materi' && $user->role !== 'dosen';
        $materialProgramFilterRequired = $materialViewer && in_array($user->role, ['admin', 'pimuniv', 'pimfak'], true);
        $materialLibrary = collect();
        $materialPrograms = $materialProgramFilterRequired ? $this->materialProgramsForViewer($user) : collect();
        $selectedMaterialCourse = null;
        if ($materialViewer) {
            if ($materialProgramFilterRequired) {
                if ($materialPrograms->doesntContain(fn (object $program): bool => (string) $program->id_prodi === $selectedProgramId)) {
                    $selectedProgramId = '';
                }
            } elseif ($user->role === 'mahasiswa') {
                $studentId = $this->studentId($user);
                $selectedProgramId = (string) DB::connection('simak_main')->table('s1m4k_mastermhs')
                    ->where('id_mahasiswa', $studentId)->value('id_prodi');
            } else {
                $selectedProgramId = trim((string) data_get($user, 'id_prodi'));
                abort_unless($selectedProgramId !== '', 403);
            }
            if (! $materialProgramFilterRequired || $selectedProgramId !== '') {
                $materialLibrary = $this->materialLibraryForViewer($user, $selectedProgramId);
            }
            $selectedLecturer = $materialLibrary->firstWhere('id_dosen', $selectedLecturerId);
            if ($selectedLecturer === null) {
                $selectedLecturerId = '';
                $selectedCourseId = '';
                if (! $materialProgramFilterRequired) {
                    $selectedProgramId = '';
                }
            } else {
                $selectedMaterialCourse = $selectedLecturer->courses->first(
                    fn (object $course): bool => (string) $course->id_matkul === $selectedCourseId
                        && (string) $course->id_prodi === $selectedProgramId
                );
                if ($selectedMaterialCourse === null) {
                    $selectedCourseId = '';
                    if (! $materialProgramFilterRequired) {
                        $selectedProgramId = '';
                    }
                }
            }
        }
        $courses = in_array($module, ['materi', 'bank-soal'], true) ? $this->coursesForTeacher($user) : collect();
        if ($module === 'bank-soal') {
            if (! in_array($selectedQuestionType, ['pilihan_ganda', 'essay'], true)) {
                $selectedQuestionType = '';
            }
        } else {
            $selectedQuestionType = '';
        }
        $studentScheduleModule = $user->role === 'mahasiswa' && in_array($module, ['pertemuan', 'tugas', 'diskusi'], true);
        $classFilterModule = in_array($module, ['pertemuan', 'tugas', 'diskusi'], true);
        $studentAcademicCalendars = $studentScheduleModule ? $this->studentAcademicCalendars($user) : collect();
        $selectedCalendarId = '';
        $selectedAcademicCalendar = null;
        $studentCourses = collect();
        $studentSchedules = collect();
        $studentActivityFiltersReady = ! $studentScheduleModule;
        $studentActivityWritable = false;
        $activityFilterModule = in_array($module, ['pertemuan', 'tugas', 'bank-soal', 'diskusi'], true);
        $activitySchedules = collect();
        $activityAcademicYears = collect();
        $activityCourses = collect();
        $activityClasses = collect();
        $selectedAcademicYear = null;
        $selectedAcademicYearCode = '';
        $activityCalendarIds = collect();
        $activityCourseIds = collect();
        $yearSchedules = collect();

        if ($studentScheduleModule) {
            $activeCalendar = $studentAcademicCalendars->firstWhere('writable', true);
            $requestedCalendarId = trim((string) $request->query('id_kalender', ''));
            $selectedAcademicCalendar = $requestedCalendarId !== ''
                ? $studentAcademicCalendars->firstWhere('id_kalender', $requestedCalendarId)
                : $activeCalendar;
            if ($selectedAcademicCalendar !== null) {
                $selectedCalendarId = (string) $selectedAcademicCalendar->id_kalender;
                $selectedAcademicCalendar->writable = $this->calendarIsWritable($selectedCalendarId);
                $studentSchedules = $this->studentSchedulesForCalendar($user, $selectedCalendarId);
                $studentCourses = $studentSchedules->unique('id_matkul')->sortBy('nama_mata_kuliah')->values();
                if ($studentCourses->doesntContain(fn (object $course): bool => (string) $course->id_matkul === $selectedCourseId)) {
                    $selectedCourseId = '';
                }
            }
            $studentActivityFiltersReady = $selectedCalendarId !== '' && $selectedCourseId !== '';
            $studentActivityWritable = (bool) ($selectedAcademicCalendar?->writable ?? false);
        }

        if ($activityFilterModule && $user->role !== 'mahasiswa') {
            $activityScheduleQuery = DB::connection('simak_main')->table('s1m4k_jadwal as schedule')
                ->join('s1m4k_kalender as calendar', 'calendar.id_kalender', '=', 'schedule.id_kalender')
                ->leftJoin('s1m4k_mastermk as course', 'course.id_matkul', '=', 'schedule.id_matkul')
                ->join('s1m4k_masterprodi as program', 'program.id_prodi', '=', 'schedule.id_prodi');
            if ($user->role === 'dosen') {
                $teacherId = $this->teacherId($user);
                $activityScheduleQuery->where(function ($scope) use ($teacherId): void {
                    foreach (self::TEACHER_SLOTS as $slot) {
                        $scope->orWhere('schedule.'.$slot, $teacherId);
                    }
                });
            } else {
                $activityScheduleQuery = $this->applyManagerScope($activityScheduleQuery, $user, 'schedule');
            }
            $activitySchedules = $activityScheduleQuery->get([
                'schedule.id_jadwal', 'schedule.id_kalender', 'schedule.id_matkul', 'schedule.nama_kelas',
                'calendar.tahun', 'calendar.namatahun', 'calendar.status as status_kalender',
                'calendar.status_sp',
                'course.kode_mata_kuliah', 'course.nama_mata_kuliah', 'program.nama_program_studi',
            ]);
            $writableCalendarIds = $this->writableCalendarIds();
            $activityAcademicYears = $activitySchedules->groupBy(fn (object $schedule): string => (string) $schedule->tahun)
                ->map(function (Collection $rows, string $year) use ($writableCalendarIds): array {
                    $label = $rows->first(fn (object $row): bool => filled($row->namatahun))?->namatahun;

                    return [
                        'year' => $year,
                        'label' => filled($label) ? (string) $label : $year,
                        'active' => $rows->contains(
                            fn (object $row): bool => $writableCalendarIds->contains((string) $row->id_kalender)
                        ),
                    ];
                })->sortByDesc('year')->values();
            $selectedAcademicYearCode = trim((string) $request->query('tahun', ''));
            if ($selectedAcademicYearCode !== ''
                && $activityAcademicYears->doesntContain(fn (array $year): bool => $year['year'] === $selectedAcademicYearCode)) {
                throw ValidationException::withMessages(['tahun' => 'Tahun akademik tidak tersedia dalam cakupan akun ini.']);
            }
            if ($selectedAcademicYearCode === '') {
                $selectedAcademicYearCode = (string) (
                    $activityAcademicYears->firstWhere('active', true)['year']
                    ?? $activityAcademicYears->first()['year']
                    ?? ''
                );
            }
            $selectedAcademicYear = $activityAcademicYears->firstWhere('year', $selectedAcademicYearCode);
            $yearSchedules = $activitySchedules->where('tahun', $selectedAcademicYearCode);
            $activityCalendarIds = $yearSchedules->pluck('id_kalender')->unique()->values();
            $activityCourses = $yearSchedules->filter(fn (object $schedule): bool => filled($schedule->id_matkul))
                ->unique('id_matkul')->sortBy('nama_mata_kuliah')->values();
            if ($selectedCourseId !== ''
                && $activityCourses->doesntContain(fn (object $course): bool => (string) $course->id_matkul === $selectedCourseId)) {
                $selectedCourseId = '';
            }
            $activityCourseIds = $selectedCourseId === ''
                ? $activityCourses->pluck('id_matkul')->unique()->values()
                : collect([$selectedCourseId]);
        }

        if ($classFilterModule) {
            $classSchedules = $studentScheduleModule ? $studentSchedules : $yearSchedules;
            if ($selectedCourseId !== '') {
                $classSchedules = $classSchedules->filter(
                    fn (object $schedule): bool => (string) $schedule->id_matkul === $selectedCourseId
                );
            } elseif ($studentScheduleModule) {
                $classSchedules = collect();
            }
            $activityClasses = $classSchedules
                ->filter(fn (object $schedule): bool => filled($schedule->id_jadwal) && filled($schedule->nama_kelas))
                ->unique('id_jadwal')
                ->sortBy(fn (object $schedule): string => mb_strtolower(
                    trim((string) ($schedule->nama_mata_kuliah ?? '')).' '.trim((string) $schedule->nama_kelas)
                ))
                ->values();
            if ($selectedClassId !== ''
                && $activityClasses->doesntContain(fn (object $schedule): bool => (string) $schedule->id_jadwal === $selectedClassId)) {
                $selectedClassId = '';
            }
        } else {
            $selectedClassId = '';
        }

        $query = match ($module) {
            'materi' => $materialViewer
                ? $this->materialsForLibrarySelection($user, $selectedLecturerId, $selectedCourseId, $selectedProgramId)->orderByDesc('materi.dibuat_pada')
                : $this->materialsQuery($user)->orderByDesc('materi.dibuat_pada'),
            'pertemuan' => $this->meetingsQuery($user)->orderByDesc('meeting.tanggal_kuliah'),
            'tugas' => $this->tasksQuery($user)->orderByDesc('task.batas_pengumpulan'),
            'bank-soal' => $this->questionsQuery($user)->orderByDesc('question.dibuat_pada'),
            'diskusi' => $this->discussionsQuery($user)->orderByDesc('discussion.dibuat_pada'),
        };
        if ($studentScheduleModule && $studentActivityFiltersReady) {
            $query->where('schedule.id_kalender', $selectedCalendarId)
                ->where('schedule.id_matkul', $selectedCourseId);
        }
        if ($classFilterModule && $selectedClassId !== '') {
            $query->where('schedule.id_jadwal', $selectedClassId);
        }
        if ($activityFilterModule && $user->role !== 'mahasiswa') {
            if ($activityCalendarIds->isEmpty()) {
                $query->whereRaw('1 = 0');
            } elseif ($module === 'bank-soal') {
                $query->whereIn('question.id_matkul', $activityCourseIds);
            } else {
                $query->whereIn('schedule.id_kalender', $activityCalendarIds);
                if ($selectedCourseId !== '') {
                    $query->where('schedule.id_matkul', $selectedCourseId);
                }
            }
        }
        if ($module === 'bank-soal') {
            if ($selectedQuestionType !== '') {
                $query->where('question.jenis_soal', $selectedQuestionType);
            }
        }
        $records = $studentScheduleModule && ! $studentActivityFiltersReady ? collect() : $query->get();
        if ($module === 'materi') {
            $usedMaterialIds = $this->materialsUsedInMeetings($records->pluck('id_materi')->all());
            foreach ($records as $material) {
                $material->used_in_meeting = isset($usedMaterialIds[$material->id_materi]);
            }
        } elseif ($module === 'pertemuan') {
            if ($user->role === 'mahasiswa') {
                $this->attachStudentMeetingAttendance($user, $records);
            } else {
                $this->attachMeetingIndexData($user, $records);
            }
        } elseif ($module === 'tugas' && $user->role === 'mahasiswa') {
            $this->attachStudentTaskSubmissions($user, $records);
        }
        $schedules = in_array($module, ['pertemuan', 'tugas', 'diskusi'], true)
            ? $this->teachingSchedules($user, true)
            : collect();
        $materials = $module === 'pertemuan' ? $this->materialsForTeacher($user) : collect();
        $canManage = $user->role === 'dosen'
            && (! $activityFilterModule || ($selectedAcademicYear['active'] ?? false));
        if ($module === 'bank-soal' && $canManage) {
            $courses = $activityCourses;
        }

        return view('lms.index', [
            'module' => $module,
            'records' => $records,
            'user' => $user,
            'canManage' => $canManage,
            'isStudent' => $user->role === 'mahasiswa',
            'schedules' => $schedules,
            'materials' => $materials,
            'courses' => $courses,
            'selectedLecturerId' => $selectedLecturerId,
            'selectedCourseId' => $selectedCourseId,
            'selectedClassId' => $selectedClassId,
            'selectedProgramId' => $selectedProgramId,
            'selectedQuestionType' => $selectedQuestionType,
            'activityFilterModule' => $activityFilterModule,
            'activityAcademicYears' => $activityAcademicYears,
            'selectedAcademicYear' => $selectedAcademicYear,
            'selectedAcademicYearCode' => $selectedAcademicYearCode,
            'isShortSemester' => $this->isShortSemesterCode($selectedAcademicYearCode)
                || ($studentScheduleModule && $this->isShortSemesterCode((string) ($selectedAcademicCalendar->tahun ?? ''))),
            'activityCourses' => $activityCourses,
            'activityClasses' => $activityClasses,
            'activityReadOnly' => $activityFilterModule
                && $user->role !== 'mahasiswa'
                && ! ($selectedAcademicYear['active'] ?? false),
            'studentAcademicCalendars' => $studentAcademicCalendars,
            'selectedCalendarId' => $selectedCalendarId,
            'selectedAcademicCalendar' => $selectedAcademicCalendar,
            'studentCourses' => $studentCourses,
            'studentActivityFiltersReady' => $studentActivityFiltersReady,
            'studentActivityWritable' => $studentActivityWritable,
            'materialViewer' => $materialViewer,
            'materialLibrary' => $materialLibrary,
            'materialPrograms' => $materialPrograms,
            'materialProgramFilterRequired' => $materialProgramFilterRequired,
            'selectedMaterialCourse' => $selectedMaterialCourse,
        ]);
    }

    public function show(Request $request, string $module, string $record): View
    {
        $this->authorizeModule($request->user(), $module);
        $user = $request->user();
        $data = match ($module) {
            'materi' => $this->materialRecord($user, $record),
            'pertemuan' => $this->meetingRecord($user, $record),
            'tugas' => $this->taskRecord($user, $record),
            'bank-soal' => $this->questionRecord($user, $record),
            'diskusi' => $this->discussionRecord($user, $record),
        };
        $studentActivityWritable = true;
        if ($user->role === 'mahasiswa' && in_array($module, ['pertemuan', 'tugas', 'diskusi'], true)) {
            $studentActivityWritable = $this->calendarIsWritable((string) $data['record']->id_kalender);
        }
        $canManage = $user->role === 'dosen';
        if ($canManage && in_array($module, ['pertemuan', 'tugas', 'diskusi'], true)) {
            $canManage = $this->calendarIsWritableForSchedule((string) $data['record']->id_jadwal);
        } elseif ($canManage && $module === 'bank-soal') {
            $requestedYear = trim((string) $request->query('tahun', ''));
            $canManage = $requestedYear === ''
                ? $this->teacherHasActiveCourse($user, (string) $data['record']->id_matkul)
                : $this->teacherCourseIsActiveInYear($user, (string) $data['record']->id_matkul, $requestedYear);
        }
        $studentActivityFilters = $user->role === 'mahasiswa'
            && in_array($module, ['pertemuan', 'tugas', 'diskusi'], true)
                ? array_filter([
                    'id_kalender' => trim((string) $request->query('id_kalender', '')),
                    'id_matkul' => trim((string) $request->query('id_matkul', '')),
                ], fn (string $value): bool => $value !== '')
                : [];
        $activityFilters = $user->role !== 'mahasiswa'
            && in_array($module, ['pertemuan', 'tugas', 'bank-soal', 'diskusi'], true)
                ? array_filter([
                    'tahun' => trim((string) $request->query('tahun', '')),
                    'id_matkul' => trim((string) $request->query('id_matkul', '')),
                    'jenis_soal' => trim((string) $request->query('jenis_soal', '')),
                ], fn (string $value): bool => $value !== '')
                : [];
        $calendarId = trim((string) ($data['record']->id_kalender ?? ''));
        if ($calendarId === '' && filled($data['record']->id_jadwal ?? null)) {
            $calendarId = (string) DB::connection('simak_main')->table('s1m4k_jadwal')
                ->where('id_jadwal', $data['record']->id_jadwal)
                ->value('id_kalender');
        }
        $calendarYear = $calendarId === ''
            ? trim((string) $request->query('tahun', ''))
            : trim((string) DB::connection('simak_main')->table('s1m4k_kalender')
                ->where('id_kalender', $calendarId)
                ->value('tahun'));

        return view('lms.show', [
            'module' => $module,
            'record' => $data['record'],
            'related' => $data['related'] ?? collect(),
            'user' => $user,
            'canManage' => $canManage,
            'canViewRoster' => $user->role === 'dosen' || in_array($user->role, self::MANAGERS, true),
            'isStudent' => $user->role === 'mahasiswa',
            'studentActivityWritable' => $studentActivityWritable,
            'isShortSemester' => $this->isShortSemesterCode($calendarYear),
            'studentActivityFilters' => $studentActivityFilters,
            'activityFilters' => $activityFilters,
            'attendanceOpenNow' => $module === 'pertemuan'
                && $user->role === 'mahasiswa'
                && (bool) $data['record']->absen_dibuka
                && now('Asia/Jayapura')->between(
                    Carbon::parse($data['record']->tanggal_kuliah.' '.$data['record']->waktu_mulai, 'Asia/Jayapura'),
                    Carbon::parse($data['record']->tanggal_kuliah.' '.$data['record']->waktu_selesai, 'Asia/Jayapura')
                ),
            'canParticipate' => $this->canParticipate($user),
        ]);
    }

    public function storeMaterial(Request $request, SimakNotificationService $notifications): RedirectResponse
    {
        $user = $request->user();
        $this->requireTeacher($user);
        $data = $request->validate([
            'id_matkul' => ['required', 'string', 'max:100'],
            'judul' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'berkas' => $this->materialUploadRules(! $this->isApiAdministrator($user)),
            'status_publikasi' => ['required', 'in:draf,terbit'],
        ]);
        $taughtCourse = $this->validateTaughtCourse($user, $data['id_matkul']);
        $id = (string) Str::uuid();
        $file = $request->file('berkas');
        $fileName = $file ? $this->safeOriginalFilename($file) : null;
        $path = $file?->store("lms/materi/{$id}", 'local');
        try {
            DB::connection('simak_main')->transaction(function () use ($data, $id, $user, $file, $fileName, $path, $taughtCourse): void {
                DB::connection('simak_main')->table('lms_materi_kuliah')->insert([
                    'id_materi' => $id,
                    'id_dosen' => $this->teacherId($user),
                    'id_matkul' => $data['id_matkul'],
                    'id_prodi' => $taughtCourse->id_prodi,
                    'judul' => $data['judul'],
                    'deskripsi' => $data['deskripsi'] ?? null,
                    'nama_berkas' => $fileName,
                    'path_berkas' => $path,
                    'tipe_berkas' => $file?->getMimeType(),
                    'ukuran_berkas' => $file?->getSize(),
                    'status_publikasi' => $data['status_publikasi'],
                ]);
            });
        } catch (\Throwable $exception) {
            if ($path !== null) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }
        if ($data['status_publikasi'] === 'terbit') {
            $notifications->sendApprovedCourseStudents(
                (string) $data['id_matkul'],
                'lms-material-published',
                'Materi LMS Baru',
                'Dosen menerbitkan materi '.$data['judul'].' pada mata kuliah Anda.',
                route('lms.show', ['module' => 'materi', 'record' => $id]),
            );
        }

        return redirect()->route('lms.materials.index')->with('success', 'Materi kuliah berhasil disimpan.');
    }

    public function updateMaterial(Request $request, string $record, SimakNotificationService $notifications): RedirectResponse
    {
        $user = $request->user();
        $this->requireTeacher($user);
        $material = $this->ownedMaterial($user, $record);
        $data = $request->validate([
            'id_matkul' => ['required', 'string', 'max:100'],
            'judul' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'berkas' => $this->materialUploadRules(),
            'status_publikasi' => ['required', 'in:draf,terbit'],
        ]);
        $taughtCourse = $this->validateTaughtCourse($user, $data['id_matkul']);
        $file = $request->file('berkas');
        $fileName = $file ? $this->safeOriginalFilename($file) : null;
        $newPath = $file?->store("lms/materi/{$record}", 'local');
        try {
            DB::connection('simak_main')->transaction(function () use ($data, $material, $record, $file, $fileName, $newPath, $taughtCourse): void {
                DB::connection('simak_main')->table('lms_materi_kuliah')->where('id_materi', $record)->update([
                    'id_matkul' => $data['id_matkul'],
                    'id_prodi' => $taughtCourse->id_prodi,
                    'judul' => $data['judul'],
                    'deskripsi' => $data['deskripsi'] ?? null,
                    'status_publikasi' => $data['status_publikasi'],
                    'nama_berkas' => $fileName ?? $material->nama_berkas,
                    'path_berkas' => $newPath ?? $material->path_berkas,
                    'tipe_berkas' => $file?->getMimeType() ?? $material->tipe_berkas,
                    'ukuran_berkas' => $file?->getSize() ?? $material->ukuran_berkas,
                ]);
            });
        } catch (\Throwable $exception) {
            if ($newPath !== null) {
                Storage::disk('local')->delete($newPath);
            }
            throw $exception;
        }
        if ($newPath !== null && $material->path_berkas) {
            Storage::disk('local')->delete($material->path_berkas);
        }
        $materialChanged = (string) $material->id_matkul !== (string) $data['id_matkul']
            || (string) $material->judul !== (string) $data['judul']
            || (string) ($material->deskripsi ?? '') !== (string) ($data['deskripsi'] ?? '')
            || (string) $material->status_publikasi !== (string) $data['status_publikasi']
            || $newPath !== null;
        $removedFromPreviousCourse = $material->status_publikasi === 'terbit'
            && ($data['status_publikasi'] !== 'terbit' || (string) $material->id_matkul !== (string) $data['id_matkul']);
        if ($materialChanged && $removedFromPreviousCourse) {
            $notifications->sendApprovedCourseStudents(
                (string) $material->id_matkul,
                'lms-material-removed',
                'Materi LMS Tidak Lagi Tersedia',
                'Materi '.$material->judul.' tidak lagi tersedia pada mata kuliah ini.',
                route('lms.materials.index'),
            );
        }
        if ($materialChanged && $data['status_publikasi'] === 'terbit') {
            $notifications->sendApprovedCourseStudents(
                (string) $data['id_matkul'],
                $material->status_publikasi === 'terbit' && (string) $material->id_matkul === (string) $data['id_matkul']
                    ? 'lms-material-updated'
                    : 'lms-material-published',
                'Materi LMS Diperbarui',
                'Materi '.$data['judul'].' telah diperbarui pada mata kuliah Anda.',
                route('lms.show', ['module' => 'materi', 'record' => $record]),
            );
        }

        return back()->with('success', 'Materi kuliah berhasil diperbarui.');
    }

    public function destroyMaterial(Request $request, string $record, SimakNotificationService $notifications): RedirectResponse
    {
        $user = $request->user();
        $this->requireTeacher($user);
        [$material, $isUsed] = DB::connection('simak_main')->transaction(function () use ($user, $record): array {
            $material = DB::connection('simak_main')->table('lms_materi_kuliah')
                ->where('id_materi', $record)
                ->where('id_dosen', $this->teacherId($user))
                ->lockForUpdate()
                ->first();
            abort_if($material === null, 404);

            if ($this->materialIsUsedInMeeting($record)) {
                return [$material, true];
            }

            DB::connection('simak_main')->table('lms_materi_kuliah')
                ->where('id_materi', $record)->delete();

            return [$material, false];
        });
        if ($isUsed) {
            return back()->with('error', 'Materi ini sudah digunakan pada pertemuan mata kuliah, hanya bisa dilakukan edit.');
        }
        if ($material->path_berkas) {
            Storage::disk('local')->delete($material->path_berkas);
        }
        if ($material->status_publikasi === 'terbit') {
            $notifications->sendApprovedCourseStudents(
                (string) $material->id_matkul,
                'lms-material-removed',
                'Materi LMS Dihapus',
                'Materi '.$material->judul.' telah dihapus dari mata kuliah.',
                route('lms.materials.index'),
            );
        }

        return redirect()->route('lms.materials.index')->with('success', 'Materi kuliah berhasil dihapus.');
    }

    public function downloadMaterial(Request $request, string $record)
    {
        $user = $request->user();
        $this->authorizeModule($user, 'materi');
        $material = $this->materialRecord($user, $record)['record'];
        abort_if(! $material->path_berkas || ! Storage::disk('local')->exists($material->path_berkas), 404, 'Berkas materi tidak ditemukan.');

        return Storage::disk('local')->download($material->path_berkas, $material->nama_berkas, [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function storeMeeting(Request $request, SimakNotificationService $notifications): RedirectResponse
    {
        $user = $request->user();
        $this->requireTeacher($user);
        $data = $request->validate([
            'id_jadwal' => ['required', 'string', 'max:100'],
            'pertemuan_ke' => ['required', 'integer', 'between:1,16'],
            'id_materi' => ['nullable', 'array'],
            'id_materi.*' => ['uuid', 'distinct'],
            'judul' => ['required', 'string', 'max:255'],
            'tanggal_kuliah' => ['required', 'date'],
            'waktu_mulai' => ['required', 'date_format:H:i'],
            'waktu_selesai' => ['required', 'date_format:H:i', 'after:waktu_mulai'],
            'absen_dibuka' => ['required', 'boolean'],
            'tautan_video' => ['nullable', 'url', 'max:2048'],
        ]);
        $schedule = $this->ownedSchedule($user, $data['id_jadwal'], true);
        if (DB::connection('simak_main')->table('lms_pertemuan')
            ->where('id_jadwal', $schedule->id_jadwal)->where('pertemuan_ke', $data['pertemuan_ke'])->exists()) {
            throw ValidationException::withMessages(['pertemuan_ke' => 'Nomor pertemuan tersebut sudah digunakan pada jadwal ini.']);
        }
        $materialIds = array_values(array_unique($data['id_materi'] ?? []));
        $meetingId = (string) Str::uuid();
        DB::connection('simak_main')->transaction(function () use ($data, $schedule, $materialIds, $meetingId, $user): void {
            $this->lockOwnedMaterials($user, $materialIds);
            DB::connection('simak_main')->table('lms_pertemuan')->insert([
                'id_pertemuan' => $meetingId,
                'id_jadwal' => $schedule->id_jadwal,
                'id_dosen' => $this->teacherId($user),
                'pertemuan_ke' => $data['pertemuan_ke'],
                'id_materi' => $materialIds[0] ?? null,
                'judul' => $data['judul'],
                'tanggal_kuliah' => $data['tanggal_kuliah'],
                'waktu_mulai' => $data['waktu_mulai'],
                'waktu_selesai' => $data['waktu_selesai'],
                'absen_dibuka' => $data['absen_dibuka'],
                'tautan_video' => $data['tautan_video'] ?? null,
                'status_pertemuan' => 'terbit',
            ]);
            $this->syncMeetingMaterials($meetingId, $materialIds);
        });
        $notifications->sendApprovedScheduleStudents(
            (string) $schedule->id_jadwal,
            'lms-meeting-published',
            'Pertemuan LMS Baru',
            'Dosen menerbitkan pertemuan '.(string) $data['pertemuan_ke'].': '.$data['judul'].'.',
            route('lms.show', ['module' => 'pertemuan', 'record' => $meetingId]),
        );

        return redirect()->route('lms.meetings.index')->with('success', 'Pertemuan kuliah berhasil dibuat.');
    }

    public function updateMeeting(Request $request, string $record, SimakNotificationService $notifications): RedirectResponse
    {
        $user = $request->user();
        $this->requireTeacher($user);
        $meeting = $this->ownedMeeting($user, $record);
        $data = $request->validate([
            'pertemuan_ke' => ['required', 'integer', 'between:1,16'],
            'id_materi' => ['nullable', 'array'],
            'id_materi.*' => ['uuid', 'distinct'],
            'judul' => ['required', 'string', 'max:255'],
            'tanggal_kuliah' => ['required', 'date'],
            'waktu_mulai' => ['required', 'date_format:H:i'],
            'waktu_selesai' => ['required', 'date_format:H:i', 'after:waktu_mulai'],
            'absen_dibuka' => ['required', 'boolean'],
            'tautan_video' => ['nullable', 'url', 'max:2048'],
            'status_pertemuan' => ['required', 'in:draf,terbit,selesai'],
        ]);
        $materialIds = array_values(array_unique($data['id_materi'] ?? []));
        $previousMaterialIds = DB::connection('simak_main')->table('lms_pertemuan_materi')
            ->where('id_pertemuan', $record)->orderBy('id_materi')->pluck('id_materi')->all();
        $newMaterialIds = $materialIds;
        sort($newMaterialIds);
        if (DB::connection('simak_main')->table('lms_pertemuan')
            ->where('id_jadwal', $meeting->id_jadwal)
            ->where('pertemuan_ke', $data['pertemuan_ke'])
            ->where('id_pertemuan', '!=', $record)
            ->exists()) {
            throw ValidationException::withMessages(['pertemuan_ke' => 'Nomor pertemuan tersebut sudah digunakan pada jadwal ini.']);
        }
        DB::connection('simak_main')->transaction(function () use ($data, $record, $materialIds, $user): void {
            $this->lockOwnedMaterials($user, $materialIds);
            DB::connection('simak_main')->table('lms_pertemuan')->where('id_pertemuan', $record)->update([
                'pertemuan_ke' => $data['pertemuan_ke'],
                'id_materi' => $materialIds[0] ?? null,
                'judul' => $data['judul'],
                'tanggal_kuliah' => $data['tanggal_kuliah'],
                'waktu_mulai' => $data['waktu_mulai'],
                'waktu_selesai' => $data['waktu_selesai'],
                'absen_dibuka' => $data['absen_dibuka'],
                'tautan_video' => $data['tautan_video'] ?? null,
                'status_pertemuan' => $data['status_pertemuan'],
            ]);
            $this->syncMeetingMaterials($record, $materialIds);
        });
        $meetingChanged = (int) $meeting->pertemuan_ke !== (int) $data['pertemuan_ke']
            || (string) $meeting->judul !== (string) $data['judul']
            || (string) $meeting->tanggal_kuliah !== (string) $data['tanggal_kuliah']
            || substr((string) $meeting->waktu_mulai, 0, 5) !== (string) $data['waktu_mulai']
            || substr((string) $meeting->waktu_selesai, 0, 5) !== (string) $data['waktu_selesai']
            || (bool) $meeting->absen_dibuka !== (bool) $data['absen_dibuka']
            || (string) $meeting->status_pertemuan !== (string) $data['status_pertemuan']
            || (string) ($meeting->tautan_video ?? '') !== (string) ($data['tautan_video'] ?? '')
            || $newMaterialIds !== $previousMaterialIds;
        if ($meetingChanged && ($meeting->status_pertemuan === 'terbit' || $data['status_pertemuan'] === 'terbit')) {
            $isPublished = $data['status_pertemuan'] === 'terbit';
            $notifications->sendApprovedScheduleStudents(
                (string) $meeting->id_jadwal,
                $isPublished ? 'lms-meeting-updated' : 'lms-meeting-unpublished',
                $isPublished ? 'Informasi Pertemuan LMS Diperbarui' : 'Pertemuan LMS Tidak Lagi Terbit',
                $isPublished
                    ? 'Informasi pertemuan '.$data['pertemuan_ke'].' telah diperbarui: '.$data['judul'].'.'
                    : 'Pertemuan '.$meeting->judul.' tidak lagi tersedia sebagai pertemuan terbit.',
                $isPublished
                    ? route('lms.show', ['module' => 'pertemuan', 'record' => $record])
                    : route('lms.meetings.index'),
            );
        }

        return back()->with('success', 'Pertemuan kuliah berhasil diperbarui.');
    }

    public function destroyMeeting(Request $request, string $record, SimakNotificationService $notifications): RedirectResponse
    {
        $user = $request->user();
        $this->requireTeacher($user);
        $meeting = $this->ownedMeeting($user, $record);
        $connection = DB::connection('simak_main');
        $blocked = $connection->transaction(function () use ($connection, $record): bool {
            $connection->table('lms_pertemuan')->where('id_pertemuan', $record)->lockForUpdate()->first();

            if ($connection->table('lms_absensi')->where('id_pertemuan', $record)->exists()
                || $connection->table('lms_ujian')->where('id_pertemuan', $record)->exists()) {
                return true;
            }

            $connection->table('lms_pertemuan')->where('id_pertemuan', $record)->delete();

            return false;
        });

        if ($blocked) {
            return back()->with('error', 'Pertemuan tidak dapat dihapus karena sudah memiliki absensi mahasiswa atau ujian.');
        }
        if ($meeting->status_pertemuan === 'terbit') {
            $notifications->sendApprovedScheduleStudents(
                (string) $meeting->id_jadwal,
                'lms-meeting-removed',
                'Pertemuan LMS Dibatalkan',
                'Pertemuan '.(string) $meeting->pertemuan_ke.' ('.$meeting->judul.') telah dihapus.',
                route('lms.meetings.index'),
            );
        }

        return redirect()->route('lms.meetings.index')->with('success', 'Pertemuan kuliah berhasil dihapus.');
    }

    public function selfAttend(Request $request, string $record, SimakNotificationService $notifications): RedirectResponse
    {
        abort_unless($request->user()->role === 'mahasiswa', 403);
        $meeting = $this->meetingRecord($request->user(), $record)['record'];
        $this->assertStudentCalendarIsActive((string) $meeting->id_kalender);
        $now = now('Asia/Jayapura');
        if (! (bool) $meeting->absen_dibuka) {
            throw ValidationException::withMessages(['absensi' => 'Absensi mandiri tidak dibuka oleh dosen.']);
        }
        if (! ($now->toDateString() === $meeting->tanggal_kuliah
            && $now->format('H:i:s') >= $meeting->waktu_mulai
            && $now->format('H:i:s') <= $meeting->waktu_selesai)) {
            throw ValidationException::withMessages(['absensi' => 'Absensi hanya tersedia pada tanggal dan jam pertemuan.']);
        }
        $studentId = $this->studentId($request->user());
        if (! $this->isApprovedInSchedule($studentId, $meeting->id_jadwal)) {
            throw ValidationException::withMessages(['absensi' => 'KRS belum disahkan untuk kelas ini.']);
        }
        DB::connection('simak_main')->table('lms_absensi')->updateOrInsert(
            ['id_pertemuan' => $record, 'id_mahasiswa' => $studentId],
            [
                'id_absensi' => (string) Str::uuid(),
                'status_kehadiran' => 'hadir',
                'metode_absen' => 'mandiri',
                'waktu_absen' => $now->toDateTimeString(),
                'id_pencatat' => $studentId,
            ]
        );
        $notifications->send(
            'dosen',
            (string) $meeting->id_dosen,
            'lms-attendance',
            'Mahasiswa Mengisi Absensi',
            'Seorang mahasiswa telah mengisi absensi untuk pertemuan '.(string) $meeting->judul.'.',
            route('lms.show', ['module' => 'pertemuan', 'record' => $record]),
        );

        return back()->with('success', 'Absensi berhasil dicatat.');
    }

    public function markAttendance(
        Request $request,
        string $record,
        string $student,
        SimakNotificationService $notifications
    ): JsonResponse|RedirectResponse {
        $user = $request->user();
        $this->requireTeacher($user);
        $meeting = $this->ownedMeeting($user, $record);
        $data = $request->validate([
            'status_kehadiran' => ['required', 'in:hadir,izin,sakit,alpa'],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ]);
        if (! $this->isApprovedInSchedule($student, $meeting->id_jadwal)) {
            throw ValidationException::withMessages(['status_kehadiran' => 'Mahasiswa tidak terdaftar dengan KRS disahkan pada kelas ini.']);
        }
        DB::connection('simak_main')->table('lms_absensi')->updateOrInsert(
            ['id_pertemuan' => $record, 'id_mahasiswa' => $student],
            [
                'id_absensi' => (string) Str::uuid(),
                'status_kehadiran' => $data['status_kehadiran'],
                'metode_absen' => 'dosen',
                'waktu_absen' => now('Asia/Jayapura')->toDateTimeString(),
                'catatan' => $data['catatan'] ?? null,
                'id_pencatat' => $this->teacherId($user),
            ]
        );
        $notifications->send(
            'mahasiswa',
            $student,
            'lms-attendance',
            'Absensi Diperbarui Dosen',
            'Status absensi pertemuan '.(string) $meeting->judul.' dicatat sebagai '.(string) $data['status_kehadiran'].'.',
            route('lms.show', ['module' => 'pertemuan', 'record' => $record]),
        );

        $message = 'Absensi mahasiswa berhasil disimpan.';

        return $request->expectsJson()
            ? response()->json(['message' => $message])
            : back()->with('success', $message);
    }

    public function cancelAttendance(
        Request $request,
        string $record,
        string $student,
        SimakNotificationService $notifications
    ): JsonResponse|RedirectResponse {
        $user = $request->user();
        $this->requireTeacher($user);
        $meeting = $this->ownedMeeting($user, $record);
        if (! $this->isApprovedInSchedule($student, $meeting->id_jadwal)) {
            throw ValidationException::withMessages(['absensi' => 'Mahasiswa tidak terdaftar dengan KRS disahkan pada kelas ini.']);
        }
        $deleted = DB::connection('simak_main')->table('lms_absensi')
            ->where('id_pertemuan', $record)
            ->where('id_mahasiswa', $student)
            ->delete();
        if ($deleted > 0) {
            $notifications->send(
                'mahasiswa',
                $student,
                'lms-attendance',
                'Absensi Dibatalkan',
                'Catatan absensi pertemuan '.(string) $meeting->judul.' telah dibatalkan oleh dosen.',
                route('lms.show', ['module' => 'pertemuan', 'record' => $record]),
            );
        }
        $message = 'Absensi mahasiswa berhasil dibatalkan.';

        return $request->expectsJson()
            ? response()->json(['message' => $message])
            : back()->with('success', $message);
    }

    public function attendanceRoster(Request $request, string $record): View
    {
        $user = $request->user();
        $this->requireTeacher($user);
        $meeting = $this->ownedMeeting($user, $record);
        $calendarId = DB::connection('simak_main')->table('s1m4k_jadwal')
            ->where('id_jadwal', $meeting->id_jadwal)->value('id_kalender');
        abort_if($calendarId === null, 404);
        $students = DB::connection('simak_main')->table('s1m4k_krs as krs')
            ->join('s1m4k_mastermhs as student', 'student.id_mahasiswa', '=', 'krs.id_mahasiswa')
            ->where('krs.id_jadwal', $meeting->id_jadwal)
            ->where('krs.id_kalender', $calendarId)
            ->whereExists(fn ($query) => $query->selectRaw('1')->from('s1m4k_pengesahankrs as approval')
                ->whereColumn('approval.id_mahasiswa', 'student.id_mahasiswa')
                ->where('approval.id_kalender', $calendarId)
                ->whereRaw("TRIM(COALESCE(approval.yang_mengesahkan, '')) <> ''")
                ->whereRaw("TRIM(COALESCE(approval.nama_kpro, '')) <> ''"))
            ->leftJoin('lms_absensi as attendance', function ($join) use ($record): void {
                $join->on('attendance.id_mahasiswa', '=', 'student.id_mahasiswa')
                    ->where('attendance.id_pertemuan', $record);
            })
            ->orderBy('student.nim')
            ->get(['student.id_mahasiswa', 'student.nim', 'student.nama_mahasiswa', 'attendance.status_kehadiran']);

        $meetingDetails = $this->meetingsQuery($user)
            ->where('meeting.id_pertemuan', $record)
            ->first();
        abort_if($meetingDetails === null, 404);

        return view('lms.attendance', [
            'meeting' => $meetingDetails,
            'students' => $students,
            'record' => $record,
        ]);
    }

    public function meetingQuestions(Request $request, string $record): JsonResponse
    {
        $user = $request->user();
        $this->requireTeacher($user);
        $meeting = $this->ownedMeeting($user, $record);
        $courseId = DB::connection('simak_main')->table('s1m4k_jadwal')
            ->where('id_jadwal', $meeting->id_jadwal)->value('id_matkul');
        abort_if($courseId === null, 404);
        $questions = DB::connection('simak_main')->table('lms_bank_soal')
            ->where('id_dosen', $this->teacherId($user))
            ->where('id_matkul', $courseId)
            ->where('aktif', true)
            ->orderBy('dibuat_pada')
            ->get(['id_soal', 'jenis_soal', 'pertanyaan']);

        return response()->json($questions);
    }

    public function createExam(Request $request, string $record): View
    {
        $user = $request->user();
        $this->requireTeacher($user);
        $meeting = $this->ownedMeeting($user, $record);
        abort_if(
            DB::connection('simak_main')->table('lms_ujian')->where('id_pertemuan', $record)->exists(),
            404
        );
        $schedule = $this->schedule($meeting->id_jadwal);
        $questions = DB::connection('simak_main')->table('lms_bank_soal')
            ->where('id_dosen', $this->teacherId($user))
            ->where('id_matkul', $schedule->id_matkul)
            ->where('aktif', true)
            ->orderBy('dibuat_pada')
            ->get(['id_soal', 'jenis_soal', 'pertanyaan']);

        return view('lms.exam-create', [
            'meeting' => $meeting,
            'schedule' => $schedule,
            'questions' => $questions,
        ]);
    }

    public function storeQuestion(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->requireTeacher($user);
        $data = $request->validate([
            'id_matkul' => ['required', 'string', 'max:100'],
            'tahun' => ['required', 'string', 'max:5'],
            'jenis_soal' => ['required', 'in:pilihan_ganda,essay'],
            'pertanyaan' => ['required', 'string'],
            'opsi_a' => ['required_if:jenis_soal,pilihan_ganda', 'nullable', 'string'],
            'opsi_b' => ['required_if:jenis_soal,pilihan_ganda', 'nullable', 'string'],
            'opsi_c' => ['required_if:jenis_soal,pilihan_ganda', 'nullable', 'string'],
            'opsi_d' => ['required_if:jenis_soal,pilihan_ganda', 'nullable', 'string'],
            'opsi_e' => ['required_if:jenis_soal,pilihan_ganda', 'nullable', 'string'],
            'kunci_jawaban' => ['required_if:jenis_soal,pilihan_ganda', 'nullable', 'in:A,B,C,D,E'],
        ]);
        $this->validateTaughtCourse($user, $data['id_matkul'], true, $data['tahun']);
        $id = (string) Str::uuid();
        $options = $data['jenis_soal'] === 'pilihan_ganda'
            ? array_intersect_key($data, array_flip(['opsi_a', 'opsi_b', 'opsi_c', 'opsi_d', 'opsi_e', 'kunci_jawaban']))
            : [];
        DB::connection('simak_main')->table('lms_bank_soal')->insert([
            'id_soal' => $id,
            'id_dosen' => $this->teacherId($user),
            'id_matkul' => $data['id_matkul'],
            'jenis_soal' => $data['jenis_soal'],
            'pertanyaan' => $data['pertanyaan'],
            'opsi_a' => $options['opsi_a'] ?? null,
            'opsi_b' => $options['opsi_b'] ?? null,
            'opsi_c' => $options['opsi_c'] ?? null,
            'opsi_d' => $options['opsi_d'] ?? null,
            'opsi_e' => $options['opsi_e'] ?? null,
            'kunci_jawaban' => $options['kunci_jawaban'] ?? null,
            'aktif' => true,
        ]);

        return redirect()->route('lms.show', ['module' => 'bank-soal', 'record' => $id])->with('success', 'Soal berhasil ditambahkan ke bank soal.');
    }

    public function downloadQuestionTemplate(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $questionsSheet = $spreadsheet->getActiveSheet();
        $questionsSheet->setTitle('Soal');
        $questionsSheet->fromArray([[
            'Jenis Soal', 'Pertanyaan', 'Opsi A', 'Opsi B', 'Opsi C', 'Opsi D', 'Opsi E', 'Kunci Jawaban',
        ]], null, 'A1');
        $questionsSheet->getStyle('A1:H1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $questionsSheet->getStyle('A1:H1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('356B45');
        $questionsSheet->freezePane('A2');
        $questionsSheet->setAutoFilter('A1:H1');
        foreach (['A' => 20, 'B' => 54, 'C' => 30, 'D' => 30, 'E' => 30, 'F' => 30, 'G' => 30, 'H' => 18] as $column => $width) {
            $questionsSheet->getColumnDimension($column)->setWidth($width);
        }

        $instructionsSheet = $spreadsheet->createSheet();
        $instructionsSheet->setTitle('Petunjuk');
        $instructionsSheet->fromArray([
            ['PETUNJUK UPLOAD BANK SOAL'],
            ['1. Isi satu soal pada setiap baris di sheet Soal. Jangan ubah nama kolom.'],
            ['2. Jenis Soal diisi pilihan_ganda atau essay.'],
            ['3. Untuk pilihan_ganda, isi Opsi A sampai Opsi E dan Kunci Jawaban dengan A, B, C, D, atau E.'],
            ['4. Untuk essay, cukup isi Jenis Soal dan Pertanyaan; kolom opsi dan kunci jawaban boleh kosong.'],
            ['5. Pilih mata kuliah yang diampu pada modal upload sebelum mengirim file.'],
            ['6. Maksimal 1.000 soal dalam satu file. Baris kosong akan dilewati.'],
        ], null, 'A1');
        $instructionsSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('356B45');
        $instructionsSheet->getColumnDimension('A')->setWidth(105);
        $instructionsSheet->getStyle('A1:A7')->getAlignment()->setWrapText(true);

        return response()->streamDownload(static function () use ($spreadsheet): void {
            (new XlsxWriter($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, 'format-bank-soal.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function importQuestions(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->requireTeacher($user);
        $data = $request->validate([
            'id_matkul' => ['required', 'string', 'max:100'],
            'file' => ['required', 'file', 'max:10240', 'mimes:xlsx', 'extensions:xlsx'],
        ]);
        $this->validateTaughtCourse($user, $data['id_matkul'], true);

        try {
            $reader = new XlsxReader;
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($data['file']->getRealPath());
        } catch (\Throwable) {
            throw ValidationException::withMessages(['file' => 'File Excel tidak dapat dibaca. Unduh dan gunakan format template Bank Soal.']);
        }

        $sheet = $spreadsheet->getSheetByName('Soal');
        if ($sheet === null) {
            $spreadsheet->disconnectWorksheets();
            throw ValidationException::withMessages(['file' => 'Sheet Soal tidak ditemukan. Gunakan template Bank Soal yang disediakan.']);
        }

        $rows = $sheet->toArray(null, true, true, false);
        $expectedHeaders = ['jenis soal', 'pertanyaan', 'opsi a', 'opsi b', 'opsi c', 'opsi d', 'opsi e', 'kunci jawaban'];
        $headers = array_map(static fn ($header): string => mb_strtolower(trim((string) $header)), array_slice($rows[0] ?? [], 0, 8));
        if ($headers !== $expectedHeaders) {
            $spreadsheet->disconnectWorksheets();
            throw ValidationException::withMessages(['file' => 'Susunan kolom tidak sesuai template Bank Soal.']);
        }

        if (count($rows) > 1001) {
            $spreadsheet->disconnectWorksheets();
            throw ValidationException::withMessages(['file' => 'File hanya dapat berisi maksimal 1.000 soal.']);
        }

        $questions = [];
        foreach (array_slice($rows, 1) as $index => $row) {
            $cells = array_pad(array_slice($row, 0, 8), 8, null);
            $cells = array_map(static fn ($cell): string => trim((string) ($cell ?? '')), $cells);
            if (count(array_filter($cells, static fn (string $cell): bool => $cell !== '')) === 0) {
                continue;
            }

            $rowNumber = $index + 2;
            $type = str_replace([' ', '-'], '_', mb_strtolower($cells[0]));
            if (! in_array($type, ['pilihan_ganda', 'essay'], true)) {
                $spreadsheet->disconnectWorksheets();
                throw ValidationException::withMessages(['file' => "Baris {$rowNumber}: Jenis Soal harus pilihan_ganda atau essay."]);
            }
            if ($cells[1] === '') {
                $spreadsheet->disconnectWorksheets();
                throw ValidationException::withMessages(['file' => "Baris {$rowNumber}: Pertanyaan wajib diisi."]);
            }

            $options = array_slice($cells, 2, 5);
            $answerKey = strtoupper($cells[7]);
            if ($type === 'pilihan_ganda'
                && (in_array('', $options, true) || ! in_array($answerKey, ['A', 'B', 'C', 'D', 'E'], true))) {
                $spreadsheet->disconnectWorksheets();
                throw ValidationException::withMessages(['file' => "Baris {$rowNumber}: Soal pilihan ganda harus memiliki Opsi A–E dan Kunci Jawaban A–E."]);
            }

            $questions[] = [
                'id_soal' => (string) Str::uuid(),
                'id_dosen' => $this->teacherId($user),
                'id_matkul' => $data['id_matkul'],
                'jenis_soal' => $type,
                'pertanyaan' => $cells[1],
                'opsi_a' => $type === 'pilihan_ganda' ? $options[0] : null,
                'opsi_b' => $type === 'pilihan_ganda' ? $options[1] : null,
                'opsi_c' => $type === 'pilihan_ganda' ? $options[2] : null,
                'opsi_d' => $type === 'pilihan_ganda' ? $options[3] : null,
                'opsi_e' => $type === 'pilihan_ganda' ? $options[4] : null,
                'kunci_jawaban' => $type === 'pilihan_ganda' ? $answerKey : null,
                'aktif' => true,
            ];
        }
        $spreadsheet->disconnectWorksheets();

        if ($questions === []) {
            throw ValidationException::withMessages(['file' => 'File belum berisi soal untuk diimpor.']);
        }

        DB::connection('simak_main')->transaction(function () use ($questions): void {
            DB::connection('simak_main')->table('lms_bank_soal')->insert($questions);
        });

        return redirect()->route('lms.questions.index')->with('success', count($questions).' soal berhasil diimpor ke bank soal.');
    }

    public function updateQuestion(Request $request, string $record): RedirectResponse
    {
        $this->requireTeacher($request->user());
        $data = $request->validate([
            'tahun' => ['required', 'string', 'max:5'],
            'jenis_soal' => ['required', 'in:pilihan_ganda,essay'],
            'pertanyaan' => ['required', 'string'],
            'opsi_a' => ['required_if:jenis_soal,pilihan_ganda', 'nullable', 'string'],
            'opsi_b' => ['required_if:jenis_soal,pilihan_ganda', 'nullable', 'string'],
            'opsi_c' => ['required_if:jenis_soal,pilihan_ganda', 'nullable', 'string'],
            'opsi_d' => ['required_if:jenis_soal,pilihan_ganda', 'nullable', 'string'],
            'opsi_e' => ['required_if:jenis_soal,pilihan_ganda', 'nullable', 'string'],
            'kunci_jawaban' => ['required_if:jenis_soal,pilihan_ganda', 'nullable', 'in:A,B,C,D,E'],
            'aktif' => ['required', 'boolean'],
        ]);
        $question = $this->ownedQuestion($request->user(), $record, $data['tahun']);
        $options = $data['jenis_soal'] === 'pilihan_ganda'
            ? array_intersect_key($data, array_flip(['opsi_a', 'opsi_b', 'opsi_c', 'opsi_d', 'opsi_e', 'kunci_jawaban']))
            : [];
        DB::connection('simak_main')->table('lms_bank_soal')->where('id_soal', $record)->update([
            'jenis_soal' => $data['jenis_soal'],
            'pertanyaan' => $data['pertanyaan'],
            'aktif' => $data['aktif'],
            'opsi_a' => $options['opsi_a'] ?? null,
            'opsi_b' => $options['opsi_b'] ?? null,
            'opsi_c' => $options['opsi_c'] ?? null,
            'opsi_d' => $options['opsi_d'] ?? null,
            'opsi_e' => $options['opsi_e'] ?? null,
            'kunci_jawaban' => $options['kunci_jawaban'] ?? null,
        ]);

        return back()->with('success', "Soal {$question->id_soal} berhasil diperbarui.");
    }

    public function destroyQuestion(Request $request, string $record): RedirectResponse
    {
        $this->requireTeacher($request->user());
        $data = $request->validate(['tahun' => ['required', 'string', 'max:5']]);
        $this->ownedQuestion($request->user(), $record, $data['tahun']);
        if (DB::connection('simak_main')->table('lms_ujian_soal')->where('id_soal', $record)->exists()) {
            throw ValidationException::withMessages(['soal' => 'Soal sudah dipakai dalam ujian dan tidak dapat dihapus.']);
        }
        DB::connection('simak_main')->table('lms_bank_soal')->where('id_soal', $record)->delete();

        return redirect()->route('lms.questions.index')->with('success', 'Soal berhasil dihapus.');
    }

    public function storeExam(Request $request, string $record, SimakNotificationService $notifications): RedirectResponse
    {
        $user = $request->user();
        $this->requireTeacher($user);
        $meeting = $this->ownedMeeting($user, $record);
        $data = $request->validate([
            'judul' => ['required', 'string', 'max:255'],
            'petunjuk' => ['nullable', 'string'],
            'jenis_ujian' => ['required', 'in:pilihan_ganda,essay'],
            'id_soal' => ['required', 'array', 'min:1'],
            'id_soal.*' => ['required', 'uuid', 'distinct'],
            'jumlah_soal_disajikan' => ['required', 'integer', 'min:1', 'max:1000'],
            'urutan_soal' => ['required', 'in:acak,berurutan'],
            'acak_pilihan_jawaban' => ['required_if:jenis_ujian,pilihan_ganda', 'nullable', 'boolean'],
            'durasi_menit' => ['required', 'integer', 'between:1,1440'],
            'batas_mulai' => ['required', 'date'],
            'batas_selesai' => ['required', 'date', 'after:batas_mulai'],
            'nilai_per_soal' => ['required_if:jenis_ujian,pilihan_ganda', 'nullable', 'numeric', 'gt:0'],
        ]);
        if ($data['jenis_ujian'] === 'essay') {
            $data['acak_pilihan_jawaban'] = false;
            $data['nilai_per_soal'] = null;
        }
        if (DB::connection('simak_main')->table('lms_ujian')->where('id_pertemuan', $record)->exists()) {
            throw ValidationException::withMessages(['judul' => 'Pertemuan ini sudah memiliki ujian.']);
        }
        $pool = DB::connection('simak_main')->table('lms_bank_soal')
            ->where('id_dosen', $this->teacherId($user))
            ->where('id_matkul', $this->schedule($meeting->id_jadwal)->id_matkul)
            ->where('jenis_soal', $data['jenis_ujian'])
            ->where('aktif', true);
        if ((clone $pool)->count() < $data['jumlah_soal_disajikan']) {
            throw ValidationException::withMessages([
                'jumlah_soal_disajikan' => 'Jumlah soal yang disajikan melebihi jumlah soal aktif pada bank soal untuk mata kuliah dan jenis ujian ini.',
            ]);
        }
        $pool->whereIn('id_soal', $data['id_soal']);
        $data['sumber_soal'] = 'pilihan';
        $questions = $pool->orderBy('dibuat_pada')->get();
        if ($questions->count() < $data['jumlah_soal_disajikan']) {
            throw ValidationException::withMessages(['id_soal' => 'Pilih minimal sejumlah soal sesuai jumlah soal yang akan disajikan.']);
        }
        $id = (string) Str::uuid();
        DB::connection('simak_main')->transaction(function () use ($data, $questions, $id, $meeting, $user): void {
            DB::connection('simak_main')->table('lms_ujian')->insert([
                'id_ujian' => $id,
                'id_pertemuan' => $meeting->id_pertemuan,
                'id_dosen' => $this->teacherId($user),
                'id_matkul' => $this->schedule($meeting->id_jadwal)->id_matkul,
                'judul' => $data['judul'],
                'petunjuk' => $data['petunjuk'] ?? null,
                'jenis_ujian' => $data['jenis_ujian'],
                'sumber_soal' => $data['sumber_soal'],
                'jumlah_soal_disajikan' => $data['jumlah_soal_disajikan'],
                'urutan_soal' => $data['urutan_soal'],
                'acak_pilihan_jawaban' => $data['acak_pilihan_jawaban'],
                'durasi_menit' => $data['durasi_menit'],
                'batas_mulai' => Carbon::parse($data['batas_mulai'], 'Asia/Jayapura')->toDateTimeString(),
                'batas_selesai' => Carbon::parse($data['batas_selesai'], 'Asia/Jayapura')->toDateTimeString(),
                'nilai_per_soal' => $data['nilai_per_soal'] ?? null,
                'status_ujian' => 'terbit',
            ]);
            foreach ($questions->values() as $position => $question) {
                DB::connection('simak_main')->table('lms_ujian_soal')->insert([
                    'id_ujian_soal' => (string) Str::uuid(),
                    'id_ujian' => $id,
                    'id_soal' => $question->id_soal,
                    'urutan_tetap' => $data['urutan_soal'] === 'berurutan' ? $position + 1 : null,
                ]);
            }
        });
        $notifications->sendApprovedScheduleStudents(
            (string) $meeting->id_jadwal,
            'lms-exam-published',
            'Ujian LMS Baru',
            'Ujian '.$data['judul'].' telah diterbitkan untuk pertemuan '.$meeting->pertemuan_ke.'.',
            route('lms.exams.show', $id),
        );

        return redirect()->route('lms.meetings.index')
            ->with('success', 'Ujian berhasil dibuat untuk pertemuan ini.');
    }

    public function showExam(
        Request $request,
        string $record,
        ActivityLogger $activityLogger,
        SimakNotificationService $notifications
    ): View {
        $user = $request->user();
        $exam = $this->examForUser($user, $record);
        $meeting = DB::connection('simak_main')->table('lms_pertemuan')
            ->where('id_pertemuan', $exam->id_pertemuan)
            ->first(['id_jadwal', 'pertemuan_ke']);
        $schedule = $this->schedule((string) $meeting->id_jadwal);
        $exam->kode_mata_kuliah = $schedule->kode_mata_kuliah;
        $exam->nama_mata_kuliah = $schedule->nama_mata_kuliah;
        $exam->nama_kelas = $schedule->nama_kelas;
        $exam->pertemuan_ke = $meeting->pertemuan_ke;
        if ($user->role === 'mahasiswa') {
            abort_unless($exam->status_ujian === 'terbit', 404);
        }
        $result = $user->role === 'mahasiswa'
            ? DB::connection('simak_main')->table('lms_hasil_ujian')->where('id_ujian', $record)->where('id_mahasiswa', $this->studentId($user))->first()
            : null;
        if ($result !== null && $result->status_hasil === 'berlangsung' && now('Asia/Jayapura')->greaterThanOrEqualTo($this->examDeadline($exam, $result))) {
            $this->finalizeExamAttempt($exam, $result);
            $this->notifyExamSubmitted($notifications, $exam, (string) $result->id_mahasiswa);
            $result = DB::connection('simak_main')->table('lms_hasil_ujian')->where('id_hasil_ujian', $result->id_hasil_ujian)->first();
            $this->logTimedOutExam($activityLogger, $user, $exam, $result);
        }
        $details = $result
            ? DB::connection('simak_main')->table('lms_detail_hasil_ujian')->where('id_hasil_ujian', $result->id_hasil_ujian)->orderBy('urutan_tampil')->get()
            : collect();
        foreach ($details as $detail) {
            $snapshot = json_decode($detail->soal_snapshot, true, 512, JSON_THROW_ON_ERROR);
            unset($snapshot['kunci_jawaban']);
            $detail->snapshot = $snapshot;
        }
        $canEditExam = $user->role === 'dosen';
        $examResults = collect();
        if ($user->role !== 'mahasiswa') {
            $examResults = DB::connection('simak_main')->table('lms_hasil_ujian as result')
                ->join('s1m4k_mastermhs as student', 'student.id_mahasiswa', '=', 'result.id_mahasiswa')
                ->where('result.id_ujian', $record)
                ->orderBy('student.nim')
                ->get(['result.*', 'student.nim', 'student.nama_mahasiswa']);
            $answersByResult = $examResults->isEmpty()
                ? collect()
                : DB::connection('simak_main')->table('lms_detail_hasil_ujian')
                    ->whereIn('id_hasil_ujian', $examResults->pluck('id_hasil_ujian'))
                    ->orderBy('urutan_tampil')
                    ->get()
                    ->each(function (object $answer): void {
                        $snapshot = json_decode($answer->soal_snapshot, true, 512, JSON_THROW_ON_ERROR);
                        unset($snapshot['kunci_jawaban']);
                        $answer->snapshot = $snapshot;
                    })
                    ->groupBy('id_hasil_ujian');
            foreach ($examResults as $attempt) {
                $attempt->answers = $answersByResult->get($attempt->id_hasil_ujian, collect());
            }
        }
        $hasExamParticipants = false;
        $examQuestions = collect();
        $selectedExamQuestionIds = [];
        if ($canEditExam) {
            $hasExamParticipants = DB::connection('simak_main')->table('lms_hasil_ujian')
                ->where('id_ujian', $record)->exists();
            $examQuestions = DB::connection('simak_main')->table('lms_bank_soal')
                ->where('id_dosen', $this->teacherId($user))
                ->where('id_matkul', $schedule->id_matkul)
                ->where('aktif', true)
                ->orderBy('jenis_soal')
                ->orderBy('dibuat_pada')
                ->get(['id_soal', 'jenis_soal', 'pertanyaan']);
            $selectedExamQuestionIds = DB::connection('simak_main')->table('lms_ujian_soal')
                ->where('id_ujian', $record)
                ->pluck('id_soal')
                ->all();
        }
        $deadline = $result !== null && $result->status_hasil === 'berlangsung'
            ? $this->examDeadline($exam, $result)
            : null;
        $serverNow = now('Asia/Jayapura');
        $serverNowAt = $serverNow->getTimestamp() * 1000;
        $examCanStart = $user->role === 'mahasiswa'
            && $result === null
            && $exam->status_ujian === 'terbit'
            && $serverNow->greaterThanOrEqualTo(Carbon::parse($exam->batas_mulai, 'Asia/Jayapura'))
            && $serverNow->lessThan(Carbon::parse($exam->batas_selesai, 'Asia/Jayapura'));

        return view('lms.exam', compact(
            'exam',
            'result',
            'details',
            'user',
            'deadline',
            'serverNowAt',
            'examCanStart',
            'examResults',
            'canEditExam',
            'hasExamParticipants',
            'examQuestions',
            'selectedExamQuestionIds'
        ));
    }

    public function updateExam(Request $request, string $record, SimakNotificationService $notifications): RedirectResponse
    {
        $user = $request->user();
        $this->requireTeacher($user);
        $exam = $this->examForUser($user, $record);
        $this->ownedMeeting($user, (string) $exam->id_pertemuan);
        $connection = DB::connection('simak_main');
        $previousQuestionIds = $connection->table('lms_ujian_soal')
            ->where('id_ujian', $record)->orderBy('id_soal')->pluck('id_soal')->all();
        $connection->transaction(function () use ($connection, $request, $record, $user): void {
            $exam = $connection->table('lms_ujian')
                ->where('id_ujian', $record)
                ->lockForUpdate()
                ->first();
            abort_if($exam === null, 404);

            $hasParticipants = $connection->table('lms_hasil_ujian')
                ->where('id_ujian', $record)
                ->exists();
            if ($hasParticipants) {
                $data = $request->validate([
                    'batas_selesai' => ['required', 'date'],
                ]);
                $newEnd = Carbon::parse($data['batas_selesai'], 'Asia/Jayapura');
                if (! $newEnd->greaterThan(Carbon::parse($exam->batas_mulai, 'Asia/Jayapura'))) {
                    throw ValidationException::withMessages([
                        'batas_selesai' => 'Waktu berakhir harus setelah waktu mulai ujian.',
                    ]);
                }
                $connection->table('lms_ujian')->where('id_ujian', $record)->update([
                    'batas_selesai' => $newEnd->toDateTimeString(),
                ]);

                return;
            }

            $data = $request->validate([
                'judul' => ['required', 'string', 'max:255'],
                'petunjuk' => ['nullable', 'string'],
                'jenis_ujian' => ['required', 'in:pilihan_ganda,essay'],
                'id_soal' => ['required', 'array', 'min:1'],
                'id_soal.*' => ['required', 'uuid', 'distinct'],
                'jumlah_soal_disajikan' => ['required', 'integer', 'min:1', 'max:1000'],
                'urutan_soal' => ['required', 'in:acak,berurutan'],
                'acak_pilihan_jawaban' => ['required_if:jenis_ujian,pilihan_ganda', 'nullable', 'boolean'],
                'durasi_menit' => ['required', 'integer', 'between:1,1440'],
                'batas_mulai' => ['required', 'date'],
                'batas_selesai' => ['required', 'date', 'after:batas_mulai'],
                'nilai_per_soal' => ['required_if:jenis_ujian,pilihan_ganda', 'nullable', 'numeric', 'gt:0'],
                'status_ujian' => ['required', 'in:draf,terbit,ditutup'],
            ]);
            if ($data['jenis_ujian'] === 'essay') {
                $data['acak_pilihan_jawaban'] = false;
                $data['nilai_per_soal'] = null;
            }
            $meetingScheduleId = $connection->table('lms_pertemuan')
                ->where('id_pertemuan', $exam->id_pertemuan)
                ->value('id_jadwal');
            abort_if($meetingScheduleId === null, 404);
            $meetingCourseId = $connection->table('s1m4k_jadwal')
                ->where('id_jadwal', $meetingScheduleId)
                ->value('id_matkul');
            abort_if($meetingCourseId === null, 404);
            $questionPool = $connection->table('lms_bank_soal')
                ->where('id_dosen', $this->teacherId($user))
                ->where('id_matkul', $meetingCourseId)
                ->where('jenis_soal', $data['jenis_ujian'])
                ->where('aktif', true);
            if ((clone $questionPool)->count() < (int) $data['jumlah_soal_disajikan']) {
                throw ValidationException::withMessages([
                    'jumlah_soal_disajikan' => 'Jumlah soal melebihi soal aktif yang tersedia untuk jenis ujian ini.',
                ]);
            }
            $questions = (clone $questionPool)
                ->whereIn('id_soal', $data['id_soal'])
                ->orderBy('dibuat_pada')
                ->get(['id_soal']);
            if ($questions->count() !== count($data['id_soal'])) {
                throw ValidationException::withMessages([
                    'id_soal' => 'Pastikan semua soal aktif, sesuai jenis ujian, dan berasal dari mata kuliah ini.',
                ]);
            }
            if ($questions->count() < (int) $data['jumlah_soal_disajikan']) {
                throw ValidationException::withMessages([
                    'id_soal' => 'Pilih minimal sejumlah soal sesuai jumlah soal yang akan disajikan.',
                ]);
            }

            $connection->table('lms_ujian')->where('id_ujian', $record)->update([
                'judul' => $data['judul'],
                'petunjuk' => $data['petunjuk'] ?? null,
                'jenis_ujian' => $data['jenis_ujian'],
                'sumber_soal' => 'pilihan',
                'jumlah_soal_disajikan' => $data['jumlah_soal_disajikan'],
                'urutan_soal' => $data['urutan_soal'],
                'acak_pilihan_jawaban' => $data['acak_pilihan_jawaban'],
                'durasi_menit' => $data['durasi_menit'],
                'batas_mulai' => Carbon::parse($data['batas_mulai'], 'Asia/Jayapura')->toDateTimeString(),
                'batas_selesai' => Carbon::parse($data['batas_selesai'], 'Asia/Jayapura')->toDateTimeString(),
                'nilai_per_soal' => $data['nilai_per_soal'] ?? null,
                'status_ujian' => $data['status_ujian'],
            ]);
            $connection->table('lms_ujian_soal')->where('id_ujian', $record)->delete();
            foreach ($questions as $position => $question) {
                $connection->table('lms_ujian_soal')->insert([
                    'id_ujian_soal' => (string) Str::uuid(),
                    'id_ujian' => $record,
                    'id_soal' => $question->id_soal,
                    'urutan_tetap' => $data['urutan_soal'] === 'berurutan' ? $position + 1 : null,
                ]);
            }
        });
        $updatedExam = $connection->table('lms_ujian')->where('id_ujian', $record)->first();
        if ($updatedExam !== null && ($exam->status_ujian === 'terbit' || $updatedExam->status_ujian === 'terbit')) {
            $currentQuestionIds = $connection->table('lms_ujian_soal')
                ->where('id_ujian', $record)->orderBy('id_soal')->pluck('id_soal')->all();
            $changed = $exam->status_ujian !== $updatedExam->status_ujian
                || $exam->judul !== $updatedExam->judul
                || $exam->petunjuk !== $updatedExam->petunjuk
                || $exam->jenis_ujian !== $updatedExam->jenis_ujian
                || $exam->jumlah_soal_disajikan != $updatedExam->jumlah_soal_disajikan
                || $exam->durasi_menit != $updatedExam->durasi_menit
                || $exam->batas_mulai !== $updatedExam->batas_mulai
                || $exam->batas_selesai !== $updatedExam->batas_selesai
                || $previousQuestionIds !== $currentQuestionIds;
            if ($changed) {
                $meeting = $connection->table('lms_pertemuan')
                    ->where('id_pertemuan', $updatedExam->id_pertemuan)
                    ->first(['id_jadwal', 'pertemuan_ke']);
                if ($meeting !== null) {
                    $isPublished = $updatedExam->status_ujian === 'terbit';
                    $notifications->sendApprovedScheduleStudents(
                        (string) $meeting->id_jadwal,
                        $isPublished ? 'lms-exam-updated' : 'lms-exam-closed',
                        $isPublished ? 'Informasi Ujian LMS Diperbarui' : 'Ujian LMS Ditutup',
                        $isPublished
                            ? 'Informasi ujian '.$updatedExam->judul.' telah diperbarui.'
                            : 'Ujian '.$updatedExam->judul.' tidak lagi tersedia.',
                        $isPublished ? route('lms.exams.show', $record) : route('lms.meetings.index'),
                    );
                }
            }
        }

        return back()->with('success', 'Pengaturan ujian berhasil diperbarui.');
    }

    public function startExam(Request $request, string $record): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->role === 'mahasiswa', 403);
        $exam = $this->examForUser($user, $record);
        $meeting = DB::connection('simak_main')->table('lms_pertemuan as meeting')
            ->join('s1m4k_jadwal as schedule', 'schedule.id_jadwal', '=', 'meeting.id_jadwal')
            ->where('meeting.id_pertemuan', $exam->id_pertemuan)
            ->first(['schedule.id_kalender']);
        abort_if($meeting === null, 404);
        $this->assertStudentCalendarIsActive((string) $meeting->id_kalender);
        $now = now('Asia/Jayapura');
        if ($exam->status_ujian !== 'terbit'
            || $now->lt(Carbon::parse($exam->batas_mulai, 'Asia/Jayapura'))
            || $now->greaterThanOrEqualTo(Carbon::parse($exam->batas_selesai, 'Asia/Jayapura'))) {
            throw ValidationException::withMessages(['ujian' => 'Ujian belum dibuka atau telah ditutup.']);
        }
        $studentId = $this->studentId($user);
        if (! $this->isApprovedInMeeting($studentId, $exam->id_pertemuan)) {
            throw ValidationException::withMessages(['ujian' => 'KRS belum disahkan untuk kelas ini.']);
        }
        $connection = DB::connection('simak_main');
        $connection->transaction(function () use ($connection, $record, $studentId): void {
            $lockedExam = $connection->table('lms_ujian')->where('id_ujian', $record)->lockForUpdate()->first();
            $now = now('Asia/Jayapura');
            if ($lockedExam === null
                || $lockedExam->status_ujian !== 'terbit'
                || $now->lt(Carbon::parse($lockedExam->batas_mulai, 'Asia/Jayapura'))
                || $now->greaterThanOrEqualTo(Carbon::parse($lockedExam->batas_selesai, 'Asia/Jayapura'))) {
                throw ValidationException::withMessages(['ujian' => 'Ujian belum dibuka atau telah ditutup.']);
            }
            $result = $connection->table('lms_hasil_ujian')
                ->where('id_ujian', $record)->where('id_mahasiswa', $studentId)->first();
            if ($result !== null) {
                if ($result->status_hasil !== 'berlangsung') {
                    throw ValidationException::withMessages(['ujian' => 'Ujian hanya dapat dikerjakan satu kali.']);
                }

                return;
            }
            $id = (string) Str::uuid();
            $connection->table('lms_hasil_ujian')->insert([
                'id_hasil_ujian' => $id,
                'id_ujian' => $record,
                'id_mahasiswa' => $studentId,
                'dimulai_pada' => now('Asia/Jayapura')->toDateTimeString(),
                'status_hasil' => 'berlangsung',
            ]);
            $pool = $connection->table('lms_ujian_soal as exam_question')
                ->join('lms_bank_soal as question', 'question.id_soal', '=', 'exam_question.id_soal')
                ->where('exam_question.id_ujian', $record)
                ->select('question.*', 'exam_question.urutan_tetap')
                ->get();
            $pool = $lockedExam->urutan_soal === 'acak' ? $pool->shuffle() : $pool->sortBy('urutan_tetap')->values();
            $pool = $pool->take($lockedExam->jumlah_soal_disajikan)->values();
            foreach ($pool as $position => $question) {
                $options = collect(['A', 'B', 'C', 'D', 'E'])->mapWithKeys(fn ($key) => [$key => $question->{'opsi_'.strtolower($key)}])->all();
                $correct = strtoupper(trim((string) $question->kunci_jawaban));
                if ($lockedExam->acak_pilihan_jawaban && $lockedExam->jenis_ujian === 'pilihan_ganda') {
                    $keys = array_keys(array_filter($options, fn ($value) => $value !== null));
                    $correctOption = $options[$correct] ?? null;
                    $values = collect($keys)->map(fn ($key) => $options[$key])->shuffle()->all();
                    $options = array_combine($keys, $values);
                    $randomizedCorrect = array_search($correctOption, $options, true);
                    if ($correctOption === null || $randomizedCorrect === false) {
                        throw new \LogicException('Kunci jawaban tidak sesuai dengan opsi soal yang tersedia.');
                    }
                    $correct = $randomizedCorrect;
                }
                $snapshot = [
                    'pertanyaan' => $question->pertanyaan,
                    'jenis_soal' => $question->jenis_soal,
                    'opsi' => $options,
                    'kunci_jawaban' => $correct,
                ];
                $connection->table('lms_detail_hasil_ujian')->insert([
                    'id_detail_hasil' => (string) Str::uuid(),
                    'id_hasil_ujian' => $id,
                    'id_soal' => $question->id_soal,
                    'urutan_tampil' => $position + 1,
                    'soal_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                ]);
            }
        });

        return redirect()->route('lms.exams.show', $record);
    }

    public function submitExam(Request $request, string $record, SimakNotificationService $notifications): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->role === 'mahasiswa', 403);
        $exam = $this->examForUser($user, $record);
        $studentId = $this->studentId($user);
        $connection = DB::connection('simak_main');
        $result = $connection->table('lms_hasil_ujian')->where('id_ujian', $record)->where('id_mahasiswa', $studentId)->first();
        if ($result === null || $result->status_hasil !== 'berlangsung') {
            throw ValidationException::withMessages(['ujian' => 'Tidak ada ujian yang sedang berlangsung.']);
        }
        if (now('Asia/Jayapura')->greaterThanOrEqualTo($this->examDeadline($exam, $result))) {
            $this->finalizeExamAttempt($exam, $result);
            $this->notifyExamSubmitted($notifications, $exam, $studentId);
            $result = $connection->table('lms_hasil_ujian')->where('id_hasil_ujian', $result->id_hasil_ujian)->first();
            $this->logTimedOutExam(app(ActivityLogger::class), $user, $exam, $result);

            return redirect()->route('lms.exams.show', $record)->with('success', 'Waktu ujian telah habis; jawaban tersimpan telah dikumpulkan.');
        }
        $answers = $request->validate([
            'jawaban' => ['nullable', 'array'],
            'jawaban.*' => ['nullable', 'string', 'max:10000'],
        ])['jawaban'] ?? [];
        $this->validateExamAnswerIds($result->id_hasil_ujian, $answers);
        $this->finalizeExamAttempt($exam, $result, $answers);
        $this->notifyExamSubmitted($notifications, $exam, $studentId);

        return redirect()->route('lms.exams.show', $record)->with('success', 'Jawaban ujian berhasil dikumpulkan.');
    }

    public function saveExamAnswers(Request $request, string $record, SimakNotificationService $notifications)
    {
        $user = $request->user();
        abort_unless($user->role === 'mahasiswa', 403);
        $exam = $this->examForUser($user, $record);
        $result = DB::connection('simak_main')->table('lms_hasil_ujian')
            ->where('id_ujian', $record)->where('id_mahasiswa', $this->studentId($user))->first();
        if ($result === null || $result->status_hasil !== 'berlangsung') {
            return response()->json(['message' => 'Ujian tidak sedang berlangsung.'], 409);
        }
        if (now('Asia/Jayapura')->greaterThanOrEqualTo($this->examDeadline($exam, $result))) {
            $this->finalizeExamAttempt($exam, $result);
            $this->notifyExamSubmitted($notifications, $exam, (string) $result->id_mahasiswa);

            return response()->json(['message' => 'Waktu ujian telah habis dan jawaban tersimpan telah dikumpulkan.'], 409);
        }
        $answers = $request->validate([
            'jawaban' => ['required', 'array'],
            'jawaban.*' => ['nullable', 'string', 'max:10000'],
        ])['jawaban'];
        $this->validateExamAnswerIds($result->id_hasil_ujian, $answers);
        $connection = DB::connection('simak_main');
        $saveState = $connection->transaction(function () use ($connection, $exam, $result, $answers): string {
            $attempt = $connection->table('lms_hasil_ujian')
                ->where('id_hasil_ujian', $result->id_hasil_ujian)
                ->lockForUpdate()
                ->first();
            if ($attempt === null || $attempt->status_hasil !== 'berlangsung') {
                return 'closed';
            }
            if (now('Asia/Jayapura')->greaterThanOrEqualTo($this->examDeadline($exam, $attempt))) {
                return 'expired';
            }

            foreach ($answers as $detailId => $answer) {
                $connection->table('lms_detail_hasil_ujian')
                    ->where('id_detail_hasil', $detailId)
                    ->where('id_hasil_ujian', $attempt->id_hasil_ujian)
                    ->update([
                        'jawaban_mahasiswa' => $answer,
                        'dijawab_pada' => now('Asia/Jayapura')->toDateTimeString(),
                    ]);
            }

            return 'saved';
        });
        if ($saveState === 'expired') {
            $this->finalizeExamAttempt($exam, $result);
            $this->notifyExamSubmitted($notifications, $exam, (string) $result->id_mahasiswa);
            $finalizedResult = $connection->table('lms_hasil_ujian')
                ->where('id_hasil_ujian', $result->id_hasil_ujian)->first();
            $this->logTimedOutExam(app(ActivityLogger::class), $user, $exam, $finalizedResult);

            return response()->json(['message' => 'Waktu ujian telah habis dan jawaban tersimpan telah dikumpulkan.'], 409);
        }
        if ($saveState === 'closed') {
            return response()->json(['message' => 'Ujian tidak sedang berlangsung.'], 409);
        }

        return response()->json(['message' => 'Jawaban tersimpan.']);
    }

    private function validateExamAnswerIds(string $resultId, array $answers): void
    {
        $validIds = DB::connection('simak_main')->table('lms_detail_hasil_ujian')
            ->where('id_hasil_ujian', $resultId)
            ->pluck('id_detail_hasil')
            ->all();
        if (array_diff(array_keys($answers), $validIds) !== []) {
            throw ValidationException::withMessages(['jawaban' => 'Ada jawaban yang tidak termasuk dalam ujian ini.']);
        }
    }

    private function examDeadline(object $exam, object $result): Carbon
    {
        $durationDeadline = Carbon::parse($result->dimulai_pada, 'Asia/Jayapura')->addMinutes((int) $exam->durasi_menit);
        $examDeadline = Carbon::parse($exam->batas_selesai, 'Asia/Jayapura');

        return $durationDeadline->lessThan($examDeadline) ? $durationDeadline : $examDeadline;
    }

    private function finalizeExamAttempt(object $exam, object $result, array $answers = []): void
    {
        $connection = DB::connection('simak_main');
        $connection->transaction(function () use ($connection, $exam, $result, $answers): void {
            $attempt = $connection->table('lms_hasil_ujian')->where('id_hasil_ujian', $result->id_hasil_ujian)
                ->lockForUpdate()->first();
            if ($attempt === null || $attempt->status_hasil !== 'berlangsung') {
                return;
            }
            $acceptedAnswers = now('Asia/Jayapura')->lessThan($this->examDeadline($exam, $attempt)) ? $answers : [];
            $score = 0.0;
            $details = $connection->table('lms_detail_hasil_ujian')
                ->where('id_hasil_ujian', $attempt->id_hasil_ujian)->get();
            foreach ($details as $detail) {
                $snapshot = json_decode($detail->soal_snapshot, true, 512, JSON_THROW_ON_ERROR);
                $answer = array_key_exists($detail->id_detail_hasil, $acceptedAnswers)
                    ? $acceptedAnswers[$detail->id_detail_hasil]
                    : $detail->jawaban_mahasiswa;
                $correctAnswer = strtoupper(trim((string) ($snapshot['kunci_jawaban'] ?? '')));
                $submittedAnswer = strtoupper(trim((string) $answer));
                $correct = $exam->jenis_ujian === 'pilihan_ganda'
                    && $submittedAnswer !== ''
                    && $submittedAnswer === $correctAnswer;
                $points = $exam->jenis_ujian === 'pilihan_ganda'
                    ? ($correct ? (float) $exam->nilai_per_soal : 0)
                    : null;
                if ($correct) {
                    $score += (float) $exam->nilai_per_soal;
                }
                $connection->table('lms_detail_hasil_ujian')->where('id_detail_hasil', $detail->id_detail_hasil)->update([
                    'jawaban_mahasiswa' => $answer,
                    'jawaban_benar' => $exam->jenis_ujian === 'pilihan_ganda' ? $correct : null,
                    'nilai_jawaban' => $points,
                    'dijawab_pada' => $answer === null ? null : ($detail->dijawab_pada ?? now('Asia/Jayapura')->toDateTimeString()),
                ]);
            }
            $maximumScore = $details->count() * (float) $exam->nilai_per_soal;
            $connection->table('lms_hasil_ujian')->where('id_hasil_ujian', $attempt->id_hasil_ujian)->update([
                'dikumpulkan_pada' => now('Asia/Jayapura')->toDateTimeString(),
                'nilai_akhir' => $exam->jenis_ujian === 'pilihan_ganda'
                    ? round($maximumScore > 0 ? ($score / $maximumScore) * 100 : 0, 2)
                    : null,
                'status_hasil' => $exam->jenis_ujian === 'pilihan_ganda' ? 'dinilai' : 'terkumpul',
            ]);
        });
    }

    private function logTimedOutExam(ActivityLogger $activityLogger, object $user, object $exam, ?object $result): void
    {
        if ($result === null) {
            return;
        }

        $activityLogger->record(
            $user,
            'LMS',
            'Mengumpulkan ujian otomatis karena waktu habis',
            [
                'id_ujian' => (string) $exam->id_ujian,
                'status_hasil' => ['sebelum' => 'berlangsung', 'sesudah' => (string) $result->status_hasil],
            ],
            (string) $result->id_hasil_ujian,
        );
    }

    private function notifyExamSubmitted(
        SimakNotificationService $notifications,
        object $exam,
        string $studentId
    ): void {
        $student = DB::connection('simak_main')->table('s1m4k_mastermhs')
            ->where('id_mahasiswa', $studentId)
            ->first(['nama_mahasiswa', 'nim']);
        $notifications->send(
            'dosen',
            (string) $exam->id_dosen,
            'lms-exam-submitted',
            'Jawaban Ujian LMS Dikumpulkan',
            sprintf(
                '%s (%s) telah mengumpulkan jawaban ujian %s.',
                (string) ($student->nama_mahasiswa ?? 'Mahasiswa'),
                (string) ($student->nim ?? ''),
                (string) $exam->judul,
            ),
            route('lms.exams.show', $exam->id_ujian),
        );
    }

    public function gradeEssay(
        Request $request,
        string $record,
        string $result,
        SimakNotificationService $notifications
    ): RedirectResponse {
        $user = $request->user();
        $this->requireTeacher($user);
        $exam = $this->examForUser($user, $record);
        $this->ownedMeeting($user, (string) $exam->id_pertemuan);
        abort_unless($exam->jenis_ujian === 'essay', 403);
        $data = $request->validate(['nilai_akhir' => ['required', 'numeric', 'between:0,100'], 'komentar_dosen' => ['nullable', 'string']]);
        $attempt = DB::connection('simak_main')->table('lms_hasil_ujian')->where('id_hasil_ujian', $result)->where('id_ujian', $record)->first();
        abort_if($attempt === null || $attempt->status_hasil !== 'terkumpul', 404);
        DB::connection('simak_main')->table('lms_hasil_ujian')->where('id_hasil_ujian', $result)->update([
            'nilai_akhir' => $data['nilai_akhir'],
            'komentar_dosen' => $data['komentar_dosen'] ?? null,
            'status_hasil' => 'dinilai',
            'dinilai_oleh' => $this->teacherId($user),
            'dinilai_pada' => now('Asia/Jayapura')->toDateTimeString(),
        ]);
        $notifications->send(
            'mahasiswa',
            (string) $attempt->id_mahasiswa,
            'lms-exam-graded',
            'Ujian LMS Telah Dinilai',
            'Ujian '.(string) $exam->judul.' telah dinilai oleh dosen.',
            route('lms.exams.show', $record),
        );

        return back()->with('success', 'Nilai essay berhasil disimpan.');
    }

    public function storeTask(Request $request, SimakNotificationService $notifications): RedirectResponse
    {
        $user = $request->user();
        $this->requireTeacher($user);
        $data = $request->validate([
            'id_jadwal' => ['required', 'string', 'max:100'],
            'judul' => ['required', 'string', 'max:255'],
            'deskripsi' => ['required', 'string'],
            'batas_pengumpulan' => ['required', 'date', 'after:now'],
            'status_tugas' => ['required', 'in:draf,terbit'],
        ]);
        $schedule = $this->ownedSchedule($user, $data['id_jadwal'], true);
        $id = (string) Str::uuid();
        DB::connection('simak_main')->table('lms_tugas')->insert([
            'id_tugas' => $id,
            'id_jadwal' => $schedule->id_jadwal,
            'id_dosen' => $this->teacherId($user),
            'judul' => $data['judul'],
            'deskripsi' => $data['deskripsi'],
            'batas_pengumpulan' => Carbon::parse($data['batas_pengumpulan'], 'Asia/Jayapura')->toDateTimeString(),
            'status_tugas' => $data['status_tugas'],
        ]);
        if ($data['status_tugas'] === 'terbit') {
            $notifications->sendApprovedScheduleStudents(
                (string) $schedule->id_jadwal,
                'lms-task-published',
                'Tugas LMS Baru',
                'Tugas '.$data['judul'].' telah diterbitkan.',
                route('lms.show', ['module' => 'tugas', 'record' => $id]),
            );
        }

        return redirect()->route('lms.show', ['module' => 'tugas', 'record' => $id])->with('success', 'Tugas berhasil dibuat.');
    }

    public function updateTask(Request $request, string $record, SimakNotificationService $notifications): RedirectResponse
    {
        $user = $request->user();
        $this->requireTeacher($user);
        $task = $this->ownedTask($user, $record);
        $data = $request->validate([
            'judul' => ['required', 'string', 'max:255'],
            'deskripsi' => ['required', 'string'],
            'batas_pengumpulan' => ['required', 'date'],
            'status_tugas' => ['required', 'in:draf,terbit,ditutup'],
        ]);
        $data['batas_pengumpulan'] = Carbon::parse($data['batas_pengumpulan'], 'Asia/Jayapura')->toDateTimeString();
        DB::connection('simak_main')->table('lms_tugas')->where('id_tugas', $record)->update($data);
        $changed = $task->judul !== $data['judul']
            || $task->deskripsi !== $data['deskripsi']
            || $task->batas_pengumpulan !== $data['batas_pengumpulan']
            || $task->status_tugas !== $data['status_tugas'];
        if ($changed && ($task->status_tugas === 'terbit' || $data['status_tugas'] === 'terbit')) {
            $notifications->sendApprovedScheduleStudents(
                (string) $task->id_jadwal,
                $data['status_tugas'] === 'terbit' ? 'lms-task-updated' : 'lms-task-closed',
                $data['status_tugas'] === 'terbit' ? 'Informasi Tugas LMS Diperbarui' : 'Tugas LMS Ditutup',
                $data['status_tugas'] === 'terbit'
                    ? 'Informasi tugas '.$data['judul'].' telah diperbarui.'
                    : 'Tugas '.$data['judul'].' tidak lagi menerima pengumpulan.',
                route('lms.show', ['module' => 'tugas', 'record' => $record]),
            );
        }

        return back()->with('success', 'Tugas berhasil diperbarui.');
    }

    public function destroyTask(Request $request, string $record, SimakNotificationService $notifications): RedirectResponse
    {
        $user = $request->user();
        $this->requireTeacher($user);
        $task = $this->ownedTask($user, $record);
        $connection = DB::connection('simak_main');
        $blocked = $connection->transaction(function () use ($connection, $record): bool {
            $connection->table('lms_tugas')->where('id_tugas', $record)->lockForUpdate()->first();

            if ($connection->table('lms_pengumpulan_tugas')->where('id_tugas', $record)->exists()) {
                return true;
            }

            $connection->table('lms_tugas')->where('id_tugas', $record)->delete();

            return false;
        });

        if ($blocked) {
            return back()->with('error', 'Tugas tidak dapat dihapus karena sudah ada mahasiswa yang mengumpulkan.');
        }
        if ($task->status_tugas === 'terbit') {
            $notifications->sendApprovedScheduleStudents(
                (string) $task->id_jadwal,
                'lms-task-removed',
                'Tugas LMS Dihapus',
                'Tugas '.$task->judul.' telah dihapus.',
                route('lms.tasks.index'),
            );
        }

        return redirect()->route('lms.tasks.index')->with('success', 'Tugas berhasil dihapus.');
    }

    public function submitTask(Request $request, string $record, SimakNotificationService $notifications): RedirectResponse
    {
        abort_unless($request->user()->role === 'mahasiswa', 403);
        $task = $this->taskRecord($request->user(), $record)['record'];
        $this->assertStudentCalendarIsActive((string) $task->id_kalender);
        if ($task->status_tugas !== 'terbit') {
            throw ValidationException::withMessages(['tugas' => 'Tugas tidak tersedia.']);
        }
        if (now('Asia/Jayapura')->gt($task->batas_pengumpulan)) {
            throw ValidationException::withMessages(['tugas' => 'Batas pengumpulan tugas telah lewat.']);
        }
        $data = $request->validate([
            'jawaban_teks' => ['nullable', 'string'],
            'tautan_berkas' => ['nullable', 'url', 'max:2048'],
        ]);
        if (blank($data['jawaban_teks'] ?? null) && blank($data['tautan_berkas'] ?? null)) {
            throw ValidationException::withMessages(['jawaban_teks' => 'Isi jawaban teks atau tautan sebelum mengumpulkan tugas.']);
        }
        $studentId = $this->studentId($request->user());
        if (! $this->isApprovedInSchedule($studentId, $task->id_jadwal)) {
            throw ValidationException::withMessages(['tugas' => 'KRS belum disahkan untuk kelas ini.']);
        }
        $connection = DB::connection('simak_main');
        $existing = $connection->table('lms_pengumpulan_tugas')->where('id_tugas', $record)->where('id_mahasiswa', $studentId)->first();
        if ($existing?->status_pengumpulan === 'dinilai') {
            throw ValidationException::withMessages(['tugas' => 'Tugas yang sudah dinilai tidak dapat diubah.']);
        }
        $connection->table('lms_pengumpulan_tugas')->updateOrInsert(
            ['id_tugas' => $record, 'id_mahasiswa' => $studentId],
            [
                'id_pengumpulan' => $existing->id_pengumpulan ?? (string) Str::uuid(),
                'jawaban_teks' => $data['jawaban_teks'] ?? null,
                'tautan_berkas' => $data['tautan_berkas'] ?? null,
                'dikumpulkan_pada' => now('Asia/Jayapura')->toDateTimeString(),
                'status_pengumpulan' => 'terkumpul',
            ]
        );
        $student = $connection->table('s1m4k_mastermhs')
            ->where('id_mahasiswa', $studentId)
            ->first(['nama_mahasiswa', 'nim']);
        $notifications->send(
            'dosen',
            (string) $task->id_dosen,
            'lms-task-submitted',
            'Pengumpulan Tugas Mahasiswa',
            sprintf(
                '%s (%s) telah mengumpulkan tugas %s.',
                (string) ($student->nama_mahasiswa ?? 'Mahasiswa'),
                (string) ($student->nim ?? ''),
                (string) $task->judul,
            ),
            route('lms.show', ['module' => 'tugas', 'record' => $record]),
        );

        return back()->with('success', 'Pengumpulan tugas berhasil disimpan.');
    }

    public function gradeTask(
        Request $request,
        string $record,
        string $submission,
        SimakNotificationService $notifications
    ): RedirectResponse {
        $user = $request->user();
        $this->requireTeacher($user);
        $this->ownedTask($user, $record);
        $data = $request->validate(['nilai' => ['required', 'numeric', 'between:0,100'], 'komentar_dosen' => ['nullable', 'string']]);
        $connection = DB::connection('simak_main');
        $studentId = $connection->table('lms_pengumpulan_tugas')
            ->where('id_pengumpulan', $submission)
            ->where('id_tugas', $record)
            ->value('id_mahasiswa');
        abort_if($studentId === null, 404);
        $updated = $connection->table('lms_pengumpulan_tugas')
            ->where('id_pengumpulan', $submission)->where('id_tugas', $record)->update([
                'nilai' => $data['nilai'],
                'komentar_dosen' => $data['komentar_dosen'] ?? null,
                'dinilai_oleh' => $this->teacherId($user),
                'dinilai_pada' => now('Asia/Jayapura')->toDateTimeString(),
                'status_pengumpulan' => 'dinilai',
            ]);
        abort_unless($updated > 0, 404);
        $task = $connection->table('lms_tugas')->where('id_tugas', $record)->first(['judul']);
        $notifications->send(
            'mahasiswa',
            (string) $studentId,
            'lms-task-graded',
            'Tugas LMS Telah Dinilai',
            'Tugas '.(string) $task->judul.' telah dinilai oleh dosen.',
            route('lms.show', ['module' => 'tugas', 'record' => $record]),
        );

        return back()->with('success', 'Nilai tugas berhasil disimpan.');
    }

    public function storeDiscussion(Request $request, SimakNotificationService $notifications): RedirectResponse
    {
        $user = $request->user();
        $this->requireTeacher($user);
        $data = $request->validate([
            'id_jadwal' => ['required', 'string', 'max:100'],
            'judul' => ['required', 'string', 'max:255'],
            'isi_diskusi' => ['required', 'string'],
        ]);
        $schedule = $this->ownedSchedule($user, $data['id_jadwal'], true);
        $id = (string) Str::uuid();
        DB::connection('simak_main')->table('lms_diskusi')->insert([
            'id_diskusi' => $id,
            'id_jadwal' => $schedule->id_jadwal,
            'id_dosen' => $this->teacherId($user),
            'judul' => $data['judul'],
            'isi_diskusi' => $data['isi_diskusi'],
            'status_diskusi' => 'terbuka',
        ]);
        $notifications->sendApprovedScheduleStudents(
            (string) $schedule->id_jadwal,
            'lms-discussion-opened',
            'Diskusi LMS Baru',
            'Diskusi '.$data['judul'].' telah dibuka untuk kelas Anda.',
            route('lms.show', ['module' => 'diskusi', 'record' => $id]),
        );

        return redirect()->route('lms.discussions.index')->with('success', 'Diskusi berhasil dibuka.');
    }

    public function updateDiscussion(Request $request, string $record, SimakNotificationService $notifications): RedirectResponse
    {
        $user = $request->user();
        $this->requireTeacher($user);
        $discussion = $this->ownedDiscussion($user, $record);
        $data = $request->validate([
            'judul' => ['required', 'string', 'max:255'],
            'isi_diskusi' => ['required', 'string'],
            'status_diskusi' => ['required', 'in:terbuka,ditutup'],
        ]);
        DB::connection('simak_main')->table('lms_diskusi')->where('id_diskusi', $record)->update($data);
        $changed = $discussion->judul !== $data['judul']
            || $discussion->isi_diskusi !== $data['isi_diskusi']
            || $discussion->status_diskusi !== $data['status_diskusi'];
        if ($changed && ($discussion->status_diskusi === 'terbuka' || $data['status_diskusi'] === 'terbuka')) {
            $isOpen = $data['status_diskusi'] === 'terbuka';
            $notifications->sendApprovedScheduleStudents(
                (string) $discussion->id_jadwal,
                $isOpen ? 'lms-discussion-updated' : 'lms-discussion-closed',
                $isOpen ? 'Informasi Diskusi LMS Diperbarui' : 'Diskusi LMS Ditutup',
                $isOpen
                    ? 'Informasi diskusi '.$data['judul'].' telah diperbarui.'
                    : 'Diskusi '.$data['judul'].' telah ditutup.',
                route('lms.show', ['module' => 'diskusi', 'record' => $record]),
            );
        }

        return back()->with('success', 'Diskusi berhasil diperbarui.');
    }

    public function destroyDiscussion(
        Request $request,
        string $record,
        SimakNotificationService $notifications
    ): RedirectResponse {
        $this->requireTeacher($request->user());
        $discussion = $this->ownedDiscussion($request->user(), $record);
        if (DB::connection('simak_main')->table('lms_pesan_diskusi')
            ->where('id_diskusi', $record)
            ->where('jenis_pengguna', 'mahasiswa')
            ->exists()) {
            return back()->with('error', 'Diskusi tidak dapat dihapus karena sudah diikuti mahasiswa.');
        }
        DB::connection('simak_main')->table('lms_diskusi')->where('id_diskusi', $record)->delete();
        if ($discussion->status_diskusi === 'terbuka') {
            $notifications->sendApprovedScheduleStudents(
                (string) $discussion->id_jadwal,
                'lms-discussion-removed',
                'Diskusi LMS Dihapus',
                'Diskusi '.$discussion->judul.' telah dihapus.',
                route('lms.discussions.index'),
            );
        }

        return redirect()->route('lms.discussions.index')->with('success', 'Diskusi berhasil dihapus.');
    }

    public function storeDiscussionMessage(
        Request $request,
        string $record,
        SimakNotificationService $notifications
    ): RedirectResponse {
        $user = $request->user();
        abort_unless(in_array($user->role, ['dosen', 'mahasiswa'], true), 403);
        $discussion = $this->discussionRecord($user, $record)['record'];
        if ($user->role === 'mahasiswa') {
            $this->assertStudentCalendarIsActive((string) $discussion->id_kalender);
        } else {
            $this->assertActiveSchedule((string) $discussion->id_jadwal);
        }
        if ($discussion->status_diskusi !== 'terbuka') {
            throw ValidationException::withMessages(['isi_pesan' => 'Diskusi telah ditutup.']);
        }
        $data = $request->validate(['isi_pesan' => ['required', 'string', 'max:20000'], 'id_pesan_induk' => ['nullable', 'uuid']]);
        $messageText = Utf8Text::repairMojibake($data['isi_pesan']);
        $storageMessage = ChatMessageText::encodeForStorage($messageText);
        if ($user->role === 'mahasiswa') {
            $studentId = $this->studentId($user);
            if (! $this->isApprovedInSchedule($studentId, $discussion->id_jadwal)) {
                throw ValidationException::withMessages(['isi_pesan' => 'KRS belum disahkan untuk kelas ini.']);
            }
            $kind = 'mahasiswa';
            $senderId = $studentId;
        } else {
            $kind = 'dosen';
            $senderId = $this->teacherId($user);
        }
        if (! empty($data['id_pesan_induk'])) {
            if (! DB::connection('simak_main')->table('lms_pesan_diskusi')
                ->where('id_pesan', $data['id_pesan_induk'])->where('id_diskusi', $record)->exists()) {
                throw ValidationException::withMessages(['id_pesan_induk' => 'Pesan induk tidak ditemukan pada diskusi ini.']);
            }
        }
        DB::connection('simak_main')->table('lms_pesan_diskusi')->insert([
            'id_pesan' => (string) Str::uuid(),
            'id_diskusi' => $record,
            'id_pesan_induk' => $data['id_pesan_induk'] ?? null,
            'jenis_pengguna' => $kind,
            'id_pengguna' => $senderId,
            'isi_pesan' => $storageMessage,
        ]);
        if ($kind === 'mahasiswa') {
            $notifications->send(
                'dosen',
                (string) $discussion->id_dosen,
                'lms-discussion-message',
                'Pesan Baru pada Diskusi LMS',
                'Seorang mahasiswa mengirim pesan pada diskusi '.(string) $discussion->judul.'.',
                route('lms.show', ['module' => 'diskusi', 'record' => $record]),
            );
        } else {
            $notifications->sendApprovedScheduleStudents(
                (string) $discussion->id_jadwal,
                'lms-discussion-message',
                'Pesan Baru pada Diskusi LMS',
                'Dosen mengirim pesan pada diskusi '.(string) $discussion->judul.'.',
                route('lms.show', ['module' => 'diskusi', 'record' => $record]),
            );
        }

        return back()->with('success', 'Pesan diskusi berhasil dikirim.');
    }

    public function destroyDiscussionMessage(Request $request, string $record, string $message): RedirectResponse
    {
        $user = $request->user();
        $discussion = $this->discussionRecord($user, $record)['record'];
        $query = DB::connection('simak_main')->table('lms_pesan_diskusi')
            ->where('id_pesan', $message)->where('id_diskusi', $record);
        if ($user->role === 'dosen') {
            $this->requireTeacher($user);
            $this->assertActiveSchedule((string) $discussion->id_jadwal);
        } else {
            abort_unless($user->role === 'mahasiswa', 403);
            $this->assertStudentCalendarIsActive((string) $discussion->id_kalender);
            $query->where('jenis_pengguna', 'mahasiswa')->where('id_pengguna', $this->studentId($user));
        }
        abort_unless($query->delete() > 0, 404);

        return back()->with('success', 'Pesan diskusi berhasil dihapus.');
    }

    private function authorizeModule(object $user, string $module): void
    {
        abort_unless(in_array($module, self::MODULES, true), 404);
        $allowed = in_array($user->role, self::MANAGERS, true)
            || $user->role === 'dosen'
            || ($user->role === 'mahasiswa' && $module !== 'bank-soal');
        abort_unless($allowed, 403);
    }

    private function canParticipate(object $user): bool
    {
        return in_array($user->role, ['dosen', 'mahasiswa'], true);
    }

    private function requireTeacher(object $user): void
    {
        abort_unless(
            $user->role === 'dosen' || $this->isApiAdministrator($user),
            403,
            'Fitur pengelolaan LMS hanya tersedia untuk dosen pengampu.'
        );
    }

    private function teacherId(object $user): string
    {
        if ($this->isApiAdministrator($user)) {
            $teacherId = request()->input('id_dosen');
            if (! is_string($teacherId) || trim($teacherId) === '' || mb_strlen($teacherId) > 100) {
                throw ValidationException::withMessages([
                    'id_dosen' => 'ID dosen wajib diisi untuk operasi LMS melalui Web Service.',
                ]);
            }
            $exists = DB::connection('simak_main')->table('s1m4k_masterdosen')
                ->where('id_dosen', trim($teacherId))->exists();
            if (! $exists) {
                throw ValidationException::withMessages(['id_dosen' => 'Dosen tidak ditemukan.']);
            }

            return trim($teacherId);
        }

        $id = DB::connection('simak_main')->table('s1m4k_masterdosen')
            ->where('nidn', data_get($user, 'login'))->value('id_dosen');
        abort_if($id === null, 403, 'Data dosen tidak ditemukan.');

        return (string) $id;
    }

    private function isApiAdministrator(object $user): bool
    {
        return $user->role === 'admin' && data_get($user, 'legacy_type') === 'admin_ws';
    }

    private function materialUploadRules(bool $required = false): array
    {
        $extensions = 'doc,docx,odt,rtf,ppt,pptx,odp,pdf';
        $requiredRule = $required ? 'required' : 'nullable';

        return [$requiredRule, 'file', 'max:51200', "mimes:{$extensions}", "extensions:{$extensions}"];
    }

    private function safeOriginalFilename(UploadedFile $file): string
    {
        $filename = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $stem = trim(pathinfo($filename, PATHINFO_FILENAME));

        if (! mb_check_encoding($filename, 'UTF-8') || preg_match('/[\x00-\x1F\x7F]/', $filename) === 1) {
            throw ValidationException::withMessages(['berkas' => 'Nama file tidak valid.']);
        }

        $maximumStemLength = 255 - ($extension === '' ? 0 : strlen($extension) + 1);
        $stem = mb_substr($stem, 0, max(1, $maximumStemLength));

        return $stem.($extension === '' ? '' : '.'.$extension);
    }

    private function studentId(object $user): string
    {
        $id = DB::connection('simak_main')->table('s1m4k_mastermhs')
            ->where('nim', data_get($user, 'login'))->value('id_mahasiswa');
        abort_if($id === null, 403, 'Data mahasiswa tidak ditemukan.');

        return (string) $id;
    }

    private function schedule(string $scheduleId): object
    {
        $schedule = DB::connection('simak_main')->table('s1m4k_jadwal as schedule')
            ->join('s1m4k_masterprodi as program', 'program.id_prodi', '=', 'schedule.id_prodi')
            ->join('s1m4k_kalender as calendar', 'calendar.id_kalender', '=', 'schedule.id_kalender')
            ->leftJoin('s1m4k_mastermk as course', 'course.id_matkul', '=', 'schedule.id_matkul')
            ->leftJoin('s1m4k_masterruang as room', 'room.id_ruang', '=', 'schedule.id_ruang')
            ->leftJoin('s1m4k_masterdosen as lecturer', 'lecturer.id_dosen', '=', 'schedule.dosen1')
            ->where('schedule.id_jadwal', $scheduleId)
            ->select([
                'schedule.id_jadwal', 'schedule.id_prodi', 'schedule.id_kalender', 'schedule.id_matkul',
                'schedule.id_ruang', 'schedule.dosen1', 'schedule.dosen2', 'schedule.dosen3', 'schedule.dosen4', 'schedule.dosen5',
                'schedule.dosen6', 'schedule.dosen7', 'schedule.dosen8', 'schedule.dosen9', 'schedule.dosen10',
                'schedule.dosen11', 'schedule.dosen12', 'schedule.dosen13', 'schedule.dosen14',
                'schedule.nama_kelas', 'calendar.status as status_kalender',
                'calendar.status_sp',
                'course.kode_mata_kuliah', 'course.nama_mata_kuliah', 'program.nama_program_studi',
                'program.id_fakultas', 'room.nama_ruang', 'lecturer.nama_dosen', 'lecturer.gelar_depan',
                'lecturer.gelar_belakang', 'lecturer.nip',
            ])->first();
        abort_if($schedule === null, 404);

        return $schedule;
    }

    private function ownedSchedule(object $user, string $scheduleId, bool $activeOnly = false): object
    {
        $schedule = $this->schedule($scheduleId);
        $teacherId = $this->teacherId($user);
        abort_unless(collect(self::TEACHER_SLOTS)->contains(fn ($slot) => (string) $schedule->{$slot} === $teacherId), 403, 'Jadwal ini bukan jadwal mengajar Anda.');
        abort_unless(! $activeOnly || $this->calendarIsWritableForSchedule($scheduleId), 403, 'Aktivitas ini hanya dapat dibuat pada kalender akademik aktif atau kalender SP aktif.');

        return $schedule;
    }

    private function assertActiveSchedule(string $scheduleId): void
    {
        abort_unless(
            $this->calendarIsWritableForSchedule($scheduleId),
            403,
            'Aktivitas LMS hanya dapat dikelola pada tahun akademik aktif atau kalender SP aktif.'
        );
    }

    private function calendarIsWritableForSchedule(string $scheduleId): bool
    {
        $calendarId = DB::connection('simak_main')->table('s1m4k_jadwal')
            ->where('id_jadwal', $scheduleId)->value('id_kalender');

        return $calendarId !== null && $this->calendarIsWritable((string) $calendarId);
    }

    private function calendarIsWritable(string $calendarId): bool
    {
        return $this->writableCalendarQuery()
            ->where('calendar.id_kalender', $calendarId)
            ->exists();
    }

    private function writableCalendarIds(?Collection $calendarIds = null)
    {
        return $this->writableCalendarQuery()
            ->when($calendarIds !== null, fn ($query) => $query->whereIn('calendar.id_kalender', $calendarIds))
            ->pluck('calendar.id_kalender')->map(
                static fn (mixed $id): string => (string) $id
            );
    }

    private function writableCalendarQuery()
    {
        return DB::connection('simak_main')->table('s1m4k_kalender as calendar')
            ->where(function ($query): void {
                $query->where('calendar.status', 'Y')
                    ->orWhere('calendar.status_sp', 'Y');
            });
    }

    private function teacherHasActiveCourse(object $user, string $courseId): bool
    {
        return $this->teacherCourseIsActiveInYear($user, $courseId, null);
    }

    private function teacherCourseIsActiveInYear(object $user, string $courseId, ?string $year): bool
    {
        $teacherId = $this->teacherId($user);
        $query = DB::connection('simak_main')->table('s1m4k_jadwal as schedule')
            ->join('s1m4k_kalender as calendar', 'calendar.id_kalender', '=', 'schedule.id_kalender')
            ->where('schedule.id_matkul', $courseId)
            ->where('calendar.status', 'Y')
            ->when($year !== null, fn ($builder) => $builder->where('calendar.tahun', $year))
            ->where(function ($builder) use ($teacherId): void {
                foreach (self::TEACHER_SLOTS as $slot) {
                    $builder->orWhere('schedule.'.$slot, $teacherId);
                }
            });

        return $query->exists();
    }

    private function assertActiveTeacherCourse(object $user, string $courseId, ?string $year = null): void
    {
        abort_unless(
            $this->teacherCourseIsActiveInYear($user, $courseId, $year),
            403,
            'Bank soal hanya dapat dikelola untuk mata kuliah pada tahun akademik aktif.'
        );
    }

    private function teachingSchedules(object $user, bool $activeOnly = false): Collection
    {
        if ($user->role !== 'dosen' && ! $this->isApiAdministrator($user)) {
            return collect();
        }
        $teacherId = $this->teacherId($user);
        $query = DB::connection('simak_main')->table('s1m4k_jadwal as schedule')
            ->join('s1m4k_kalender as calendar', 'calendar.id_kalender', '=', 'schedule.id_kalender')
            ->leftJoin('s1m4k_mastermk as course', 'course.id_matkul', '=', 'schedule.id_matkul')
            ->leftJoin('s1m4k_masterprodi as program', 'program.id_prodi', '=', 'schedule.id_prodi')
            ->where(function ($builder) use ($teacherId): void {
                foreach (self::TEACHER_SLOTS as $slot) {
                    $builder->orWhere('schedule.'.$slot, $teacherId);
                }
            })
            ->when($activeOnly, fn ($builder) => $this->constrainWritableCalendars($builder))
            ->select([
                'schedule.id_jadwal', 'schedule.id_prodi', 'schedule.id_kalender', 'schedule.id_matkul',
                'schedule.nama_kelas', 'calendar.namatahun', 'calendar.status as status_kalender',
                'calendar.status_sp',
                'course.kode_mata_kuliah', 'course.nama_mata_kuliah', 'program.nama_program_studi',
            ]);

        return $query->orderBy('course.nama_mata_kuliah')->get();
    }

    private function constrainWritableCalendars($query)
    {
        return $query->whereIn('calendar.id_kalender', $this->writableCalendarQuery()->select('calendar.id_kalender'));
    }

    private function coursesForTeacher(object $user): Collection
    {
        return $this->teachingSchedules($user)
            ->sortByDesc(fn ($schedule) => $this->academicYearSortKey((string) $schedule->namatahun))
            ->unique('id_matkul')
            ->values();
    }

    private function academicYearSortKey(string $academicYear): int
    {
        preg_match_all('/20\\d{2}/', $academicYear, $years);
        $yearMatches = $years[0];
        $year = (int) (end($yearMatches) ?: 0);
        $normalizedYear = mb_strtolower($academicYear);
        $semester = str_contains($normalizedYear, 'genap') ? 2 : 1;

        if (! str_contains($normalizedYear, 'genap')
            && ! str_contains($normalizedYear, 'ganjil')
            && preg_match('/20\\d{2}\\s*([12])(?:\\D|$)/', $academicYear, $match)) {
            $semester = (int) $match[1];
        }

        return ($year * 10) + $semester;
    }

    private function isShortSemesterCode(string $code): bool
    {
        return preg_match('/^\\d{4}3$/', trim($code)) === 1;
    }

    private function validateTaughtCourse(object $user, string $courseId, bool $activeOnly = false, ?string $year = null): object
    {
        $course = $this->coursesForTeacher($user)->firstWhere('id_matkul', $courseId);
        if ($course === null) {
            throw ValidationException::withMessages([
                'id_matkul' => 'Pilih mata kuliah yang pernah Anda ampu.',
            ]);
        }
        if ($activeOnly) {
            $this->assertActiveTeacherCourse($user, $courseId, $year);
        }

        return $course;
    }

    private function materialsForTeacher(object $user): Collection
    {
        if ($user->role !== 'dosen') {
            return collect();
        }

        return DB::connection('simak_main')->table('lms_materi_kuliah')
            ->where('id_dosen', $this->teacherId($user))->orderBy('judul')->get(['id_materi', 'judul']);
    }

    private function materialsForLibrarySelection(object $user, string $lecturerId, string $courseId, string $programId)
    {
        $query = $this->materialsQuery($user);
        if ($lecturerId === '' || $courseId === '' || $programId === '') {
            $query->whereRaw('0 = 1');
        } else {
            $query->where('materi.id_dosen', $lecturerId)
                ->where('materi.id_matkul', $courseId)
                ->where(function ($programScope) use ($programId): void {
                    $programScope->whereNull('materi.id_prodi')
                        ->orWhere('materi.id_prodi', $programId);
                });
        }

        return $query;
    }

    private function materialProgramsForViewer(object $user): Collection
    {
        $query = DB::connection('simak_main')->table('s1m4k_jadwal as schedule')
            ->join('s1m4k_masterprodi as program', 'program.id_prodi', '=', 'schedule.id_prodi')
            ->join('s1m4k_mastermk as course', 'course.id_matkul', '=', 'schedule.id_matkul');
        if ($user->role === 'pimfak') {
            $facultyId = trim((string) data_get($user, 'id_fakultas'));
            abort_unless($facultyId !== '', 403);
            $query->where('program.id_fakultas', $facultyId);
        }

        return $query
            ->where(function ($teacherQuery): void {
                foreach (self::TEACHER_SLOTS as $slot) {
                    $teacherQuery->orWhere(function ($slotQuery) use ($slot): void {
                        $slotQuery->whereNotNull('schedule.'.$slot)
                            ->where('schedule.'.$slot, '<>', '');
                    });
                }
            })
            ->select('program.id_prodi', 'program.nama_program_studi')
            ->distinct()
            ->orderBy('program.nama_program_studi')
            ->get();
    }

    private function materialLibraryForViewer(object $user, string $programId): Collection
    {
        if ($programId === '') {
            return collect();
        }

        $scheduleQuery = DB::connection('simak_main')->table('s1m4k_jadwal as schedule')
            ->join('s1m4k_mastermk as course', 'course.id_matkul', '=', 'schedule.id_matkul')
            ->join('s1m4k_masterprodi as program', 'program.id_prodi', '=', 'schedule.id_prodi')
            ->where('schedule.id_prodi', $programId);

        $assignments = null;
        foreach (self::TEACHER_SLOTS as $slot) {
            $slotAssignments = (clone $scheduleQuery)
                ->whereNotNull('schedule.'.$slot)
                ->where('schedule.'.$slot, '<>', '')
                ->select([
                    'schedule.'.$slot.' as id_dosen',
                    'schedule.id_matkul',
                    'schedule.id_prodi',
                ]);
            $assignments = $assignments === null
                ? $slotAssignments
                : $assignments->unionAll($slotAssignments);
        }

        $scheduledCourses = DB::connection('simak_main')->query()
            ->fromSub($assignments, 'assignment')
            ->join('s1m4k_masterdosen as lecturer', 'lecturer.id_dosen', '=', 'assignment.id_dosen')
            ->join('s1m4k_mastermk as course', 'course.id_matkul', '=', 'assignment.id_matkul')
            ->join('s1m4k_masterprodi as program', 'program.id_prodi', '=', 'assignment.id_prodi')
            ->select([
                'assignment.id_dosen',
                'lecturer.nama_dosen', 'lecturer.gelar_depan', 'lecturer.gelar_belakang',
                'lecturer.nidn',
                'assignment.id_matkul',
                'assignment.id_prodi',
                'course.kode_mata_kuliah',
                'course.nama_mata_kuliah',
                'program.nama_program_studi',
            ])
            ->distinct()
            ->orderBy('lecturer.nama_dosen')
            ->orderBy('course.nama_mata_kuliah')
            ->get();
        if ($scheduledCourses->isEmpty()) {
            return collect();
        }

        $materialCounts = $this->materialsQuery($user)
            ->where(function ($programScope) use ($programId): void {
                $programScope->whereNull('materi.id_prodi')->orWhere('materi.id_prodi', $programId);
            })
            ->select('materi.id_dosen', 'materi.id_matkul', 'materi.id_prodi')
            ->selectRaw('COUNT(DISTINCT materi.id_materi) as material_count')
            ->groupBy('materi.id_dosen', 'materi.id_matkul', 'materi.id_prodi')
            ->get();
        $keyFor = static fn (string $lecturerId, string $courseId, string $programId): string => $lecturerId."\0".$courseId."\0".$programId;
        $countByCourse = $materialCounts->mapWithKeys(
            fn (object $row): array => [$keyFor((string) $row->id_dosen, (string) $row->id_matkul, (string) ($row->id_prodi ?? '')) => (int) $row->material_count]
        );

        foreach ($scheduledCourses as $course) {
            $courseKey = $keyFor((string) $course->id_dosen, (string) $course->id_matkul, (string) $course->id_prodi);
            $globalCourseKey = $keyFor((string) $course->id_dosen, (string) $course->id_matkul, '');
            $course->material_count = $countByCourse->get($courseKey, 0)
                + ($courseKey === $globalCourseKey ? 0 : $countByCourse->get($globalCourseKey, 0));
        }

        return $scheduledCourses->groupBy('id_dosen')->map(function (Collection $courses): object {
            $lecturer = clone $courses->first();
            $lecturer->courses = $courses->values();
            $lecturer->material_count = $courses->sum('material_count');

            return $lecturer;
        })->values();
    }

    private function studentAcademicCalendars(object $user): Collection
    {
        $studentId = $this->studentId($user);
        $registeredCalendarIds = DB::connection('simak_main')->table('s1m4k_krs')
            ->where('id_mahasiswa', $studentId)
            ->distinct()
            ->pluck('id_kalender');
        $calendars = $registeredCalendarIds->isEmpty()
            ? collect()
            : DB::connection('simak_main')->table('s1m4k_kalender')
                ->whereIn('id_kalender', $registeredCalendarIds)
                ->get(['id_kalender', 'tahun', 'namatahun', 'status', 'status_sp'])
                ->keyBy('id_kalender');
        $studentProgramId = DB::connection('simak_main')->table('s1m4k_mastermhs')
            ->where('id_mahasiswa', $studentId)->value('id_prodi');
        $activeCalendarQuery = $this->writableCalendarQuery();
        if ($studentProgramId !== null && trim((string) $studentProgramId) !== '') {
            $activeCalendarQuery->where('calendar.id_prodi', $studentProgramId);
        }
        $activeCalendar = $activeCalendarQuery
            ->orderByDesc('calendar.tahun')
            ->first([
                'calendar.id_kalender', 'calendar.tahun', 'calendar.namatahun',
                'calendar.status', 'calendar.status_sp',
            ]);
        if ($activeCalendar !== null) {
            $calendars->put($activeCalendar->id_kalender, $activeCalendar);
        }

        $writableIds = $this->writableCalendarIds($calendars->keys());
        foreach ($calendars as $calendar) {
            $calendar->writable = $writableIds->contains((string) $calendar->id_kalender);
        }

        return $calendars->values()
            ->sortByDesc(fn (object $calendar): array => [(bool) $calendar->writable ? 1 : 0, $this->academicYearSortKey((string) $calendar->namatahun)])
            ->values();
    }

    private function studentSchedulesForCalendar(object $user, string $calendarId): Collection
    {
        if ($user->role !== 'mahasiswa' || $calendarId === '') {
            return collect();
        }
        $studentId = $this->studentId($user);

        return DB::connection('simak_main')->table('s1m4k_krs as krs')
            ->join('s1m4k_jadwal as schedule', 'schedule.id_jadwal', '=', 'krs.id_jadwal')
            ->join('s1m4k_mastermk as course', 'course.id_matkul', '=', 'schedule.id_matkul')
            ->where('krs.id_mahasiswa', $studentId)
            ->where('krs.id_kalender', $calendarId)
            ->where('schedule.id_kalender', $calendarId)
            ->whereExists(fn ($approval) => $approval->selectRaw('1')->from('s1m4k_pengesahankrs as approval')
                ->where('approval.id_mahasiswa', $studentId)
                ->whereColumn('approval.id_kalender', 'krs.id_kalender')
                ->whereRaw("TRIM(COALESCE(approval.yang_mengesahkan, '')) <> ''")
                ->whereRaw("TRIM(COALESCE(approval.nama_kpro, '')) <> ''"))
            ->select(
                'schedule.id_jadwal',
                'schedule.id_kalender',
                'schedule.id_matkul',
                'schedule.nama_kelas',
                'course.kode_mata_kuliah',
                'course.nama_mata_kuliah'
            )
            ->distinct()
            ->orderBy('course.nama_mata_kuliah')
            ->orderBy('schedule.nama_kelas')
            ->get();
    }

    private function studentCalendarIsActive(string $calendarId): bool
    {
        return $this->calendarIsWritable($calendarId);
    }

    private function assertStudentCalendarIsActive(string $calendarId): void
    {
        abort_unless(
            $this->studentCalendarIsActive($calendarId),
            403,
            'Aktivitas hanya tersedia pada tahun akademik aktif atau kalender SP aktif.'
        );
    }

    private function lockOwnedMaterials(object $user, array $materialIds): void
    {
        if ($materialIds === []) {
            return;
        }

        $ownedMaterialIds = DB::connection('simak_main')->table('lms_materi_kuliah')
            ->where('id_dosen', $this->teacherId($user))
            ->whereIn('id_materi', $materialIds)
            ->lockForUpdate()
            ->pluck('id_materi');

        if ($ownedMaterialIds->count() !== count($materialIds)) {
            throw ValidationException::withMessages([
                'id_materi' => 'Pilih materi kuliah yang Anda unggah sendiri.',
            ]);
        }
    }

    private function materialsUsedInMeetings(array $materialIds): array
    {
        $materialIds = array_values(array_unique($materialIds));
        if ($materialIds === []) {
            return [];
        }

        $connection = DB::connection('simak_main');
        $linkedIds = $connection->table('lms_pertemuan_materi')
            ->whereIn('id_materi', $materialIds)->distinct()->pluck('id_materi');
        $legacyIds = $connection->table('lms_pertemuan')
            ->whereIn('id_materi', $materialIds)->distinct()->pluck('id_materi');

        return array_fill_keys($linkedIds->merge($legacyIds)->unique()->all(), true);
    }

    private function materialIsUsedInMeeting(string $materialId): bool
    {
        $connection = DB::connection('simak_main');

        return $connection->table('lms_pertemuan_materi')->where('id_materi', $materialId)->exists()
            || $connection->table('lms_pertemuan')->where('id_materi', $materialId)->exists();
    }

    private function syncMeetingMaterials(string $meetingId, array $materialIds): void
    {
        $connection = DB::connection('simak_main');
        $connection->table('lms_pertemuan_materi')->where('id_pertemuan', $meetingId)->delete();
        foreach ($materialIds as $materialId) {
            $connection->table('lms_pertemuan_materi')->insert([
                'id_pertemuan_materi' => (string) Str::uuid(),
                'id_pertemuan' => $meetingId,
                'id_materi' => $materialId,
            ]);
        }
    }

    private function attachMeetingIndexData(object $user, Collection $records): void
    {
        if ($user->role !== 'dosen' || $records->isEmpty()) {
            return;
        }

        $connection = DB::connection('simak_main');
        $meetingIds = $records->pluck('id_pertemuan');

        $materialsByMeeting = $connection->table('lms_pertemuan_materi as link')
            ->join('lms_materi_kuliah as material', 'material.id_materi', '=', 'link.id_materi')
            ->whereIn('link.id_pertemuan', $meetingIds)
            ->orderBy('material.judul')
            ->get(['link.id_pertemuan', 'material.id_materi', 'material.judul'])
            ->groupBy('id_pertemuan');
        $legacyMaterials = $connection->table('lms_materi_kuliah')
            ->whereIn('id_materi', $records->pluck('id_materi')->filter()->unique())
            ->get(['id_materi', 'judul'])
            ->keyBy('id_materi');
        $examsByMeeting = $connection->table('lms_ujian')->whereIn('id_pertemuan', $meetingIds)->get()->keyBy('id_pertemuan');

        foreach ($records as $meeting) {
            $meeting->materials = $materialsByMeeting->get($meeting->id_pertemuan, collect());
            $legacyMaterial = $legacyMaterials->get($meeting->id_materi);
            if ($meeting->materials->isEmpty() && $legacyMaterial) {
                $meeting->materials = collect([$legacyMaterial]);
            }
            $meeting->exam = $examsByMeeting->get($meeting->id_pertemuan);
        }
    }

    private function attachStudentMeetingAttendance(object $user, Collection $records): void
    {
        if ($records->isEmpty()) {
            return;
        }

        $connection = DB::connection('simak_main');
        $meetingIds = $records->pluck('id_pertemuan');
        $attendanceByMeeting = $connection->table('lms_absensi')
            ->where('id_mahasiswa', $this->studentId($user))
            ->whereIn('id_pertemuan', $meetingIds)
            ->get(['id_pertemuan', 'status_kehadiran'])
            ->keyBy('id_pertemuan');
        $now = now('Asia/Jayapura')->toDateTimeString();
        $examsByMeeting = $connection->table('lms_ujian')
            ->whereIn('id_pertemuan', $meetingIds)
            ->where('status_ujian', 'terbit')
            ->where('batas_mulai', '<=', $now)
            ->where('batas_selesai', '>=', $now)
            ->get(['id_ujian', 'id_pertemuan'])
            ->keyBy('id_pertemuan');
        $examIds = $examsByMeeting->pluck('id_ujian');
        $examResults = $examIds->isEmpty()
            ? collect()
            : $connection->table('lms_hasil_ujian')
                ->where('id_mahasiswa', $this->studentId($user))
                ->whereIn('id_ujian', $examIds)
                ->get(['id_ujian', 'status_hasil'])
                ->keyBy('id_ujian');

        foreach ($records as $meeting) {
            $meeting->student_attendance_status = $attendanceByMeeting->get($meeting->id_pertemuan)?->status_kehadiran;
            $exam = $examsByMeeting->get($meeting->id_pertemuan);
            $result = $exam ? $examResults->get($exam->id_ujian) : null;
            $meeting->student_exam = $exam && ($result === null || $result->status_hasil === 'berlangsung')
                ? $exam
                : null;
        }
    }

    private function attachStudentTaskSubmissions(object $user, Collection $records): void
    {
        if ($records->isEmpty()) {
            return;
        }

        $submissions = DB::connection('simak_main')->table('lms_pengumpulan_tugas')
            ->where('id_mahasiswa', $this->studentId($user))
            ->whereIn('id_tugas', $records->pluck('id_tugas'))
            ->get([
                'id_tugas', 'jawaban_teks', 'tautan_berkas', 'dikumpulkan_pada',
                'status_pengumpulan', 'nilai', 'komentar_dosen',
            ])
            ->keyBy('id_tugas');

        foreach ($records as $task) {
            $task->student_submission = $submissions->get($task->id_tugas);
        }
    }

    private function materialsQuery(object $user)
    {
        $query = DB::connection('simak_main')->table('lms_materi_kuliah as materi')
            ->leftJoin('s1m4k_mastermk as course', 'course.id_matkul', '=', 'materi.id_matkul')
            ->leftJoin('s1m4k_masterdosen as lecturer', 'lecturer.id_dosen', '=', 'materi.id_dosen')
            ->leftJoin('s1m4k_masterprodi as program', function ($join): void {
                $join->whereRaw('program.id_prodi = COALESCE(materi.id_prodi, course.id_prodi)');
            })
            ->select(
                'materi.*', 'course.kode_mata_kuliah', 'course.nama_mata_kuliah',
                'lecturer.nama_dosen', 'lecturer.gelar_depan', 'lecturer.gelar_belakang',
                'program.nama_program_studi'
            );
        if ($user->role === 'mahasiswa') {
            $query->where('materi.status_publikasi', 'terbit');
        } elseif ($user->role === 'dosen') {
            $query->where('materi.id_dosen', $this->teacherId($user));
        } elseif ($user->role === 'pimfak') {
            $facultyId = trim((string) data_get($user, 'id_fakultas'));
            abort_unless($facultyId !== '', 403);
            $query->where(function ($scope) use ($facultyId): void {
                $scope->whereNull('materi.id_prodi')
                    ->orWhereExists(fn ($subquery) => $subquery->selectRaw('1')->from('s1m4k_masterprodi as program_scope')
                        ->whereColumn('program_scope.id_prodi', 'materi.id_prodi')->where('program_scope.id_fakultas', $facultyId));
            });
        } elseif (in_array($user->role, ['userprodi', 'usertu'], true)) {
            $query->where(function ($scope) use ($user): void {
                $scope->whereNull('materi.id_prodi')->orWhere('materi.id_prodi', data_get($user, 'id_prodi'));
            });
        }

        return $query;
    }

    private function meetingsQuery(object $user)
    {
        $query = DB::connection('simak_main')->table('lms_pertemuan as meeting')
            ->join('s1m4k_jadwal as schedule', 'schedule.id_jadwal', '=', 'meeting.id_jadwal')
            ->join('s1m4k_masterprodi as program', 'program.id_prodi', '=', 'schedule.id_prodi')
            ->join('s1m4k_kalender as calendar', 'calendar.id_kalender', '=', 'schedule.id_kalender')
            ->leftJoin('s1m4k_mastermk as course', 'course.id_matkul', '=', 'schedule.id_matkul')
            ->select('meeting.*', 'schedule.id_matkul', 'schedule.id_prodi', 'schedule.id_kalender', 'schedule.nama_kelas', 'calendar.namatahun', 'course.kode_mata_kuliah', 'course.nama_mata_kuliah', 'program.nama_program_studi');
        if ($user->role === 'mahasiswa') {
            $studentId = $this->studentId($user);
            $query->where('meeting.status_pertemuan', 'terbit')
                ->whereExists(fn ($subquery) => $subquery->selectRaw('1')->from('s1m4k_krs as krs')
                    ->whereColumn('krs.id_jadwal', 'meeting.id_jadwal')
                    ->whereColumn('krs.id_kalender', 'schedule.id_kalender')
                    ->where('krs.id_mahasiswa', $studentId)
                    ->whereExists(fn ($approved) => $approved->selectRaw('1')->from('s1m4k_pengesahankrs as approval')
                        ->whereColumn('approval.id_mahasiswa', 'krs.id_mahasiswa')
                        ->whereColumn('approval.id_kalender', 'krs.id_kalender')
                        ->whereRaw("TRIM(COALESCE(approval.yang_mengesahkan, '')) <> ''")
                        ->whereRaw("TRIM(COALESCE(approval.nama_kpro, '')) <> ''"))
                );
        } elseif ($user->role === 'dosen') {
            $query->where('meeting.id_dosen', $this->teacherId($user));
        } else {
            $query = $this->applyManagerScope($query, $user, 'schedule');
        }

        return $query;
    }

    private function tasksQuery(object $user)
    {
        $query = DB::connection('simak_main')->table('lms_tugas as task')
            ->join('s1m4k_jadwal as schedule', 'schedule.id_jadwal', '=', 'task.id_jadwal')
            ->leftJoin('s1m4k_mastermk as course', 'course.id_matkul', '=', 'schedule.id_matkul')
            ->join('s1m4k_masterprodi as program', 'program.id_prodi', '=', 'schedule.id_prodi')
            ->select('task.*', 'schedule.id_matkul', 'schedule.id_prodi', 'schedule.id_kalender', 'schedule.nama_kelas', 'course.kode_mata_kuliah', 'course.nama_mata_kuliah', 'program.nama_program_studi');
        if ($user->role === 'mahasiswa') {
            $studentId = $this->studentId($user);
            $query->where('task.status_tugas', 'terbit')
                ->whereExists(fn ($subquery) => $subquery->selectRaw('1')->from('s1m4k_krs as krs')
                    ->whereColumn('krs.id_jadwal', 'task.id_jadwal')
                    ->whereColumn('krs.id_kalender', 'schedule.id_kalender')
                    ->where('krs.id_mahasiswa', $studentId)
                    ->whereExists(fn ($approved) => $approved->selectRaw('1')->from('s1m4k_pengesahankrs as approval')
                        ->whereColumn('approval.id_mahasiswa', 'krs.id_mahasiswa')
                        ->whereColumn('approval.id_kalender', 'krs.id_kalender')
                        ->whereRaw("TRIM(COALESCE(approval.yang_mengesahkan, '')) <> ''")
                        ->whereRaw("TRIM(COALESCE(approval.nama_kpro, '')) <> ''"))
                );
        } elseif ($user->role === 'dosen') {
            $query->where('task.id_dosen', $this->teacherId($user));
        } else {
            $query = $this->applyManagerScope($query, $user, 'schedule');
        }

        return $query;
    }

    private function questionsQuery(object $user)
    {
        $query = DB::connection('simak_main')->table('lms_bank_soal as question')
            ->leftJoin('s1m4k_mastermk as course', 'course.id_matkul', '=', 'question.id_matkul')
            ->leftJoin('s1m4k_masterprodi as program', 'program.id_prodi', '=', 'course.id_prodi')
            ->leftJoin('s1m4k_masterdosen as lecturer', 'lecturer.id_dosen', '=', 'question.id_dosen')
            ->select(
                'question.*', 'course.kode_mata_kuliah', 'course.nama_mata_kuliah',
                'program.nama_program_studi', 'lecturer.nama_dosen',
                'lecturer.gelar_depan', 'lecturer.gelar_belakang'
            );
        if ($user->role === 'dosen') {
            $query->where('question.id_dosen', $this->teacherId($user));
        } elseif ($user->role === 'pimfak') {
            $facultyId = trim((string) data_get($user, 'id_fakultas'));
            abort_unless($facultyId !== '', 403);
            $query->whereExists(fn ($subquery) => $subquery->selectRaw('1')->from('s1m4k_jadwal as schedule')
                ->join('s1m4k_masterprodi as program', 'program.id_prodi', '=', 'schedule.id_prodi')
                ->whereColumn('schedule.id_matkul', 'question.id_matkul')->where('program.id_fakultas', $facultyId));
        } elseif (in_array($user->role, ['userprodi', 'usertu'], true)) {
            $query->whereExists(fn ($subquery) => $subquery->selectRaw('1')->from('s1m4k_jadwal as schedule')
                ->whereColumn('schedule.id_matkul', 'question.id_matkul')->where('schedule.id_prodi', data_get($user, 'id_prodi')));
        }

        return $query;
    }

    private function discussionsQuery(object $user)
    {
        $latestMessage = DB::connection('simak_main')->table('lms_pesan_diskusi')
            ->select('id_diskusi')
            ->selectRaw('MAX(dibuat_pada) as tanggal_terakhir_pesan')
            ->groupBy('id_diskusi');
        $query = DB::connection('simak_main')->table('lms_diskusi as discussion')
            ->join('s1m4k_jadwal as schedule', 'schedule.id_jadwal', '=', 'discussion.id_jadwal')
            ->leftJoin('s1m4k_mastermk as course', 'course.id_matkul', '=', 'schedule.id_matkul')
            ->join('s1m4k_masterprodi as program', 'program.id_prodi', '=', 'schedule.id_prodi')
            ->leftJoinSub($latestMessage, 'latest_message', 'latest_message.id_diskusi', '=', 'discussion.id_diskusi')
            ->select('discussion.*', 'schedule.id_matkul', 'schedule.id_prodi', 'schedule.id_kalender', 'schedule.nama_kelas', 'course.kode_mata_kuliah', 'course.nama_mata_kuliah', 'program.nama_program_studi')
            ->selectRaw('COALESCE(latest_message.tanggal_terakhir_pesan, discussion.dibuat_pada) as tanggal_terakhir_diskusi');
        if ($user->role === 'mahasiswa') {
            $studentId = $this->studentId($user);
            $query->whereExists(fn ($subquery) => $subquery->selectRaw('1')->from('s1m4k_krs as krs')
                ->whereColumn('krs.id_jadwal', 'discussion.id_jadwal')
                ->whereColumn('krs.id_kalender', 'schedule.id_kalender')
                ->where('krs.id_mahasiswa', $studentId)
                ->whereExists(fn ($approved) => $approved->selectRaw('1')->from('s1m4k_pengesahankrs as approval')
                    ->whereColumn('approval.id_mahasiswa', 'krs.id_mahasiswa')
                    ->whereColumn('approval.id_kalender', 'krs.id_kalender')
                    ->whereRaw("TRIM(COALESCE(approval.yang_mengesahkan, '')) <> ''")
                    ->whereRaw("TRIM(COALESCE(approval.nama_kpro, '')) <> ''"))
            );
        } elseif ($user->role === 'dosen') {
            $query->where('discussion.id_dosen', $this->teacherId($user));
        } else {
            $query = $this->applyManagerScope($query, $user, 'schedule');
        }

        return $query;
    }

    private function applyManagerScope($query, object $user, string $scheduleAlias)
    {
        if ($user->role === 'pimfak') {
            $facultyId = trim((string) data_get($user, 'id_fakultas'));
            abort_unless($facultyId !== '', 403);
            $query->where('program.id_fakultas', $facultyId);
        } elseif (in_array($user->role, ['userprodi', 'usertu'], true)) {
            $programId = trim((string) data_get($user, 'id_prodi'));
            abort_unless($programId !== '', 403);
            $query->where($scheduleAlias.'.id_prodi', $programId);
        }

        return $query;
    }

    private function materialRecord(object $user, string $id): array
    {
        $query = $this->materialsQuery($user)->where('materi.id_materi', $id);
        $record = $query->first();
        abort_if($record === null, 404);
        $record->used_in_meeting = $this->materialIsUsedInMeeting($id);

        return ['record' => $record];
    }

    private function meetingRecord(object $user, string $id): array
    {
        $record = $this->meetingsQuery($user)->where('meeting.id_pertemuan', $id)->first();
        abort_if($record === null, 404);
        $record->studentAttendance = $user->role === 'mahasiswa'
            ? DB::connection('simak_main')->table('lms_absensi')
                ->where('id_pertemuan', $id)
                ->where('id_mahasiswa', $this->studentId($user))
                ->first(['status_kehadiran', 'metode_absen', 'waktu_absen'])
            : null;
        $students = DB::connection('simak_main')->table('s1m4k_krs as krs')
            ->join('s1m4k_mastermhs as student', 'student.id_mahasiswa', '=', 'krs.id_mahasiswa')
            ->where('krs.id_jadwal', $record->id_jadwal)
            ->where('krs.id_kalender', $record->id_kalender)
            ->whereExists(fn ($subquery) => $subquery->selectRaw('1')->from('s1m4k_pengesahankrs as approval')
                ->whereColumn('approval.id_mahasiswa', 'student.id_mahasiswa')
                ->where('approval.id_kalender', $record->id_kalender)
                ->whereRaw("TRIM(COALESCE(approval.yang_mengesahkan, '')) <> ''")
                ->whereRaw("TRIM(COALESCE(approval.nama_kpro, '')) <> ''"))
            ->distinct()->orderBy('student.nim')
            ->leftJoin('lms_absensi as attendance', function ($join) use ($id): void {
                $join->on('attendance.id_mahasiswa', '=', 'student.id_mahasiswa')->where('attendance.id_pertemuan', $id);
            })
            ->get(['student.id_mahasiswa', 'student.nim', 'student.nama_mahasiswa', 'attendance.status_kehadiran', 'attendance.metode_absen']);
        $exam = DB::connection('simak_main')->table('lms_ujian')->where('id_pertemuan', $id)->first();
        $record->exam = $exam;
        $record->students = in_array($user->role, ['dosen', ...self::MANAGERS], true) ? $students : collect();
        $record->materials = DB::connection('simak_main')->table('lms_pertemuan_materi as link')
            ->join('lms_materi_kuliah as material', 'material.id_materi', '=', 'link.id_materi')
            ->where('link.id_pertemuan', $id)
            ->orderBy('material.judul')
            ->get(['material.id_materi', 'material.judul']);
        if ($record->materials->isEmpty() && $record->id_materi) {
            $record->materials = DB::connection('simak_main')->table('lms_materi_kuliah')
                ->where('id_materi', $record->id_materi)->get(['id_materi', 'judul']);
        }
        $record->questions = $user->role === 'dosen'
            ? DB::connection('simak_main')->table('lms_bank_soal')
                ->where('id_dosen', $this->teacherId($user))
                ->where('id_matkul', $record->id_matkul)
                ->where('aktif', true)
                ->orderBy('dibuat_pada')
                ->get(['id_soal', 'jenis_soal', 'pertanyaan'])
            : collect();

        return [
            'record' => $record,
            'related' => $user->role === 'dosen' ? $this->materialsForTeacher($user) : collect(),
        ];
    }

    private function taskRecord(object $user, string $id): array
    {
        $record = $this->tasksQuery($user)->where('task.id_tugas', $id)->first();
        abort_if($record === null, 404);
        $studentId = $user->role === 'mahasiswa' ? $this->studentId($user) : null;
        $record->submission = $studentId
            ? DB::connection('simak_main')->table('lms_pengumpulan_tugas')->where('id_tugas', $id)->where('id_mahasiswa', $studentId)->first()
            : null;
        $record->submissions = $user->role === 'dosen'
            ? DB::connection('simak_main')->table('lms_pengumpulan_tugas as submission')
                ->join('s1m4k_mastermhs as student', 'student.id_mahasiswa', '=', 'submission.id_mahasiswa')
                ->where('submission.id_tugas', $id)->orderBy('student.nim')
                ->get(['submission.*', 'student.nim', 'student.nama_mahasiswa'])
            : collect();

        return ['record' => $record];
    }

    private function questionRecord(object $user, string $id): array
    {
        $record = $this->questionsQuery($user)->where('question.id_soal', $id)->first();
        abort_if($record === null, 404);

        return ['record' => $record];
    }

    private function discussionRecord(object $user, string $id): array
    {
        $record = $this->discussionsQuery($user)->where('discussion.id_diskusi', $id)->first();
        abort_if($record === null, 404);
        $record->messages = DB::connection('simak_main')->table('lms_pesan_diskusi')
            ->where('id_diskusi', $id)->orderBy('dibuat_pada')->get();
        foreach ($record->messages as $message) {
            $message->isi_pesan = ChatMessageText::decodeFromStorage(
                Utf8Text::repairMojibake((string) $message->isi_pesan)
            );
        }
        $messagesById = $record->messages->keyBy('id_pesan');
        $senderId = match ($user->role) {
            'dosen' => $this->teacherId($user),
            'mahasiswa' => $this->studentId($user),
            default => null,
        };
        foreach ($record->messages as $message) {
            $message->can_delete = $senderId !== null
                && $message->jenis_pengguna === ($user->role === 'dosen' ? 'dosen' : 'mahasiswa')
                && (string) $message->id_pengguna === $senderId;
            $message->is_mine = $message->can_delete;
            $message->parent_message = $message->id_pesan_induk
                ? $messagesById->get($message->id_pesan_induk)
                : null;
        }

        return ['record' => $record];
    }

    private function ownedMaterial(object $user, string $id): object
    {
        $material = DB::connection('simak_main')->table('lms_materi_kuliah')
            ->where('id_materi', $id)->where('id_dosen', $this->teacherId($user))->first();
        abort_if($material === null, 404);

        return $material;
    }

    private function ownedMeeting(object $user, string $id): object
    {
        $meeting = DB::connection('simak_main')->table('lms_pertemuan')
            ->where('id_pertemuan', $id)->where('id_dosen', $this->teacherId($user))->first();
        abort_if($meeting === null, 404);
        $this->assertActiveSchedule((string) $meeting->id_jadwal);

        return $meeting;
    }

    private function ownedQuestion(object $user, string $id, ?string $year = null): object
    {
        $question = DB::connection('simak_main')->table('lms_bank_soal')
            ->where('id_soal', $id)->where('id_dosen', $this->teacherId($user))->first();
        abort_if($question === null, 404);
        $this->assertActiveTeacherCourse($user, (string) $question->id_matkul, $year);

        return $question;
    }

    private function ownedTask(object $user, string $id): object
    {
        $task = DB::connection('simak_main')->table('lms_tugas')
            ->where('id_tugas', $id)->where('id_dosen', $this->teacherId($user))->first();
        abort_if($task === null, 404);
        $this->assertActiveSchedule((string) $task->id_jadwal);

        return $task;
    }

    private function ownedDiscussion(object $user, string $id): object
    {
        $discussion = DB::connection('simak_main')->table('lms_diskusi')
            ->where('id_diskusi', $id)->where('id_dosen', $this->teacherId($user))->first();
        abort_if($discussion === null, 404);
        $this->assertActiveSchedule((string) $discussion->id_jadwal);

        return $discussion;
    }

    private function isApprovedInSchedule(string $studentId, string $scheduleId): bool
    {
        $schedule = $this->schedule($scheduleId);

        return DB::connection('simak_main')->table('s1m4k_krs as krs')
            ->where('krs.id_jadwal', $scheduleId)
            ->where('krs.id_kalender', $schedule->id_kalender)
            ->where('krs.id_mahasiswa', $studentId)
            ->whereExists(fn ($subquery) => $subquery->selectRaw('1')->from('s1m4k_pengesahankrs as approval')
                ->where('approval.id_mahasiswa', $studentId)
                ->where('approval.id_kalender', $schedule->id_kalender)
                ->whereRaw("TRIM(COALESCE(approval.yang_mengesahkan, '')) <> ''")
                ->whereRaw("TRIM(COALESCE(approval.nama_kpro, '')) <> ''"))
            ->exists();
    }

    private function lmsAttendanceHeading(\FPDF $pdf, object $schedule, string $title): void
    {
        $logoPath = app(\App\Services\InstitutionSettings::class)->logoFilePath();
        if (is_file($logoPath)) {
            $pdf->Image($logoPath, 3, 0.5, 2, 2);
        }
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(33, 0.5, $this->pdfText($title.' '.mb_strtoupper((string) $schedule->namatahun)), 0, 1, 'C');
        $pdf->Cell(33, 0.5, $this->pdfText('PROGRAM STUDI '.mb_strtoupper((string) $schedule->nama_program_studi)), 0, 1, 'C');
        $pdf->Cell(33, 0.5, $this->pdfText(mb_strtoupper(app(\App\Services\InstitutionSettings::class)->institutionName())), 0, 1, 'C');
        $pdf->Ln(0.3);
        $pdf->Line(1, $pdf->GetY(), 34, $pdf->GetY());
        $pdf->Ln(0.2);
        $pdf->SetFont('Times', '', 9);
        foreach ([
            ['Mata kuliah', ucwords(mb_strtolower((string) $schedule->nama_mata_kuliah))],
            ['Kelas', ucwords(mb_strtolower((string) $schedule->nama_kelas))],
            ['Dosen', LecturerName::format(
                ucwords(mb_strtolower((string) $schedule->nama_dosen)),
                $schedule->gelar_depan,
                $schedule->gelar_belakang,
            )],
            ['Ruangan', (string) $schedule->nama_ruang],
            ['Hari / Jam', (string) $schedule->hari.' / '.$schedule->jammulai.' s/d '.$schedule->jamselesai],
        ] as [$label, $value]) {
            $pdf->Cell(3.2, 0.42, $this->pdfText($label), 0, 0, 'L');
            $pdf->Cell(0.3, 0.42, ':', 0, 0, 'C');
            $pdf->Cell(15, 0.42, $this->pdfText($value), 0, 1, 'L');
        }
        $pdf->Ln(0.15);
    }

    private function lmsAttendanceTableHead(\FPDF $pdf): void
    {
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(1.2, 1.1, $this->pdfText('No'), 'LRTB', 0, 'C');
        $pdf->Cell(2.8, 1.1, $this->pdfText('NPM'), 'LRTB', 0, 'C');
        $pdf->Cell(5.2, 1.1, $this->pdfText('Nama Mahasiswa'), 'LRTB', 0, 'C');
        $pdf->Cell(24.5, 0.55, $this->pdfText('Pertemuan Ke'), 'LRTB', 0, 'C');
        $pdf->Ln();
        $pdf->Cell(9.2, 0.55, '', 'LRB', 0, 'C');
        for ($number = 1; $number <= 16; $number++) {
            $pdf->Cell(1.53125, 0.55, (string) $number, 'LRTB', 0, 'C');
        }
        $pdf->Ln();
    }

    private function pdfText(string $value): string
    {
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $value) ?: '';
    }

    private function isApprovedInMeeting(string $studentId, string $meetingId): bool
    {
        $meeting = DB::connection('simak_main')->table('lms_pertemuan')->where('id_pertemuan', $meetingId)->first();
        abort_if($meeting === null, 404);

        return $this->isApprovedInSchedule($studentId, $meeting->id_jadwal);
    }

    private function examForUser(object $user, string $id): object
    {
        $exam = DB::connection('simak_main')->table('lms_ujian')->where('id_ujian', $id)->first();
        abort_if($exam === null, 404);
        if ($user->role === 'dosen' || $this->isApiAdministrator($user)) {
            abort_unless($exam->id_dosen === $this->teacherId($user), 404);
        } elseif ($user->role === 'mahasiswa') {
            abort_unless($this->isApprovedInMeeting($this->studentId($user), $exam->id_pertemuan), 404);
        } else {
            abort_unless(in_array($user->role, self::MANAGERS, true), 403);
            $scheduleId = DB::connection('simak_main')->table('lms_pertemuan')
                ->where('id_pertemuan', $exam->id_pertemuan)->value('id_jadwal');
            abort_if($scheduleId === null, 404);
            $schedule = $this->schedule((string) $scheduleId);
            if ($user->role === 'pimfak') {
                abort_unless($schedule->id_fakultas === (string) data_get($user, 'id_fakultas'), 404);
            } elseif (in_array($user->role, ['userprodi', 'usertu'], true)) {
                abort_unless($schedule->id_prodi === (string) data_get($user, 'id_prodi'), 404);
            }
        }

        return $exam;
    }
}
