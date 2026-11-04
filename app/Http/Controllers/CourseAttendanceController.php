<?php

namespace App\Http\Controllers;

use App\Support\AcademicCalendarScope;
use App\Support\LecturerName;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;

class CourseAttendanceController extends Controller
{
    private const ROLES = ['admin', 'userprodi', 'usertu', 'dosen', 'asdos'];

    private const TEACHER_SLOTS = ['dosen1', 'dosen2', 'dosen3', 'dosen4', 'dosen5', 'dosen6', 'dosen7'];

    public function index(Request $request): View
    {
        $user = $request->user();
        $this->authorizeRole($user);
        $filters = $request->validate([
            'program' => ['nullable', 'string', 'max:100'],
            'calendar' => ['nullable', 'string', 'max:100'],
        ]);
        $programs = $this->programs($user);
        $selectedProgram = match ($user->role) {
            'admin' => (string) ($filters['program'] ?? ''),
            'userprodi', 'usertu' => trim((string) data_get($user, 'id_prodi')),
            default => (string) ($filters['program'] ?? ''),
        };
        if (in_array($user->role, ['userprodi', 'usertu'], true)) {
            abort_unless($selectedProgram !== '', 403);
        }
        if ($selectedProgram !== '') {
            abort_unless($programs->contains('id_prodi', $selectedProgram), 404);
        }

        $calendars = $selectedProgram !== '' ? $this->availableCalendars($user, $selectedProgram) : collect();
        $activeCalendar = $selectedProgram !== '' ? $this->activeCalendar($selectedProgram) : null;
        if (filled($filters['calendar'] ?? null)) {
            $calendar = $calendars->firstWhere('id_kalender', $filters['calendar']);
            abort_if($calendar === null, 404, 'Kalender tidak tersedia untuk program studi atau penugasan akun ini.');
        } else {
            $calendar = $activeCalendar === null
                ? null
                : $calendars->firstWhere('id_kalender', $activeCalendar->id_kalender);
        }
        $schedules = collect();
        if ($calendar !== null) {
            $query = $this->scheduleQuery()->where('schedule.id_prodi', $selectedProgram)
                ->where('schedule.id_kalender', $calendar->id_kalender);
            if (in_array($user->role, ['dosen', 'asdos'], true)) {
                $this->applyTeacherScope($query, $user);
            }
            $schedules = $query->orderBy('course.nama_mata_kuliah')
                ->orderBy('schedule.nama_kelas')
                ->get();
            foreach ($schedules as $schedule) {
                $students = DB::connection('simak_main')->table('s1m4k_krs as krs')
                    ->join('s1m4k_mastermhs as student', 'krs.id_mahasiswa', '=', 'student.id_mahasiswa')
                    ->where('krs.id_jadwal', $schedule->id_jadwal)
                    ->where('krs.id_kalender', $schedule->id_kalender);
                $schedule->student_count = (clone $students)->distinct()->count('student.id_mahasiswa');
                $schedule->approved_student_count = (clone $students)
                    ->whereExists(function ($subquery) use ($schedule): void {
                        $subquery->selectRaw('1')
                            ->from('s1m4k_pengesahankrs as approval')
                            ->whereColumn('approval.id_mahasiswa', 'student.id_mahasiswa')
                            ->where('approval.id_kalender', $schedule->id_kalender)
                            ->whereRaw("TRIM(COALESCE(approval.nama_kpro, '')) <> ''");
                    })
                    ->distinct()->count('student.id_mahasiswa');
                $schedule->pending_approval_count = $schedule->student_count - $schedule->approved_student_count;
            }
        }

        return view('course-attendance.index', compact(
            'user', 'programs', 'selectedProgram', 'calendars', 'calendar', 'schedules'
        ));
    }

    public function print(Request $request, string $scheduleId, string $document): Response
    {
        $user = $request->user();
        $this->authorizeRole($user);
        abort_unless(in_array($document, ['attendance', 'class-record', 'roster', 'midterm', 'final'], true), 404);
        $schedule = $this->accessibleSchedule($user, $scheduleId);
        $students = $this->studentsForSchedule($schedule, $document !== 'roster');

        if ($document === 'roster') {
            return $this->rosterExcel($schedule, $students);
        }

        abort_unless(class_exists(\FPDF::class), 500, 'Library cetak absen tidak tersedia.');

        $pdf = match ($document) {
            'attendance' => $this->attendancePdf($schedule, $students),
            'class-record' => $this->classRecordPdf($schedule),
            'midterm', 'final' => $this->examPdf($schedule, $students, $document),
        };

        return response($pdf->Output('S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$this->filename($document, $schedule).'.pdf"',
        ]);
    }

    public function pendingApprovals(Request $request, string $scheduleId): JsonResponse
    {
        $user = $request->user();
        abort_unless(in_array($user->role, ['admin', 'userprodi', 'usertu'], true), 403);
        $schedule = $this->accessibleSchedule($user, $scheduleId);
        $students = DB::connection('simak_main')->table('s1m4k_krs as krs')
            ->join('s1m4k_mastermhs as student', 'krs.id_mahasiswa', '=', 'student.id_mahasiswa')
            ->where('krs.id_jadwal', $schedule->id_jadwal)
            ->where('krs.id_kalender', $schedule->id_kalender)
            ->whereNotExists(function ($query) use ($schedule): void {
                $query->selectRaw('1')
                    ->from('s1m4k_pengesahankrs as approval')
                    ->whereColumn('approval.id_mahasiswa', 'student.id_mahasiswa')
                    ->where('approval.id_kalender', $schedule->id_kalender)
                    ->whereRaw("TRIM(COALESCE(approval.nama_kpro, '')) <> ''");
            })
            ->distinct()
            ->orderBy('student.nim')
            ->get(['student.nim', 'student.nama_mahasiswa']);

        return response()->json(['students' => $students]);
    }

    private function programs(object $user): Collection
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

        $teacherId = $this->teacherId($user);
        $query = $connection->table('s1m4k_jadwal as schedule')
            ->join('s1m4k_kalender as calendar', 'schedule.id_kalender', '=', 'calendar.id_kalender')
            ->join('s1m4k_masterprodi as program', 'schedule.id_prodi', '=', 'program.id_prodi')
            ->where(function ($builder) use ($teacherId): void {
                foreach (self::TEACHER_SLOTS as $slot) {
                    $builder->orWhere('schedule.'.$slot, $teacherId);
                }
            });

        return $query->distinct()->orderBy('program.nama_program_studi')
            ->get(['program.id_prodi', 'program.nama_program_studi', 'program.nama_jenjang_pendidikan']);
    }

    private function scheduleQuery()
    {
        return DB::connection('simak_main')->table('s1m4k_jadwal as schedule')
            ->join('s1m4k_masterprodi as program', 'schedule.id_prodi', '=', 'program.id_prodi')
            ->join('s1m4k_kalender as calendar', 'schedule.id_kalender', '=', 'calendar.id_kalender')
            ->leftJoin('s1m4k_mastermk as course', 'schedule.id_matkul', '=', 'course.id_matkul')
            ->leftJoin('s1m4k_masterruang as room', 'schedule.id_ruang', '=', 'room.id_ruang')
            ->leftJoin('s1m4k_masterdosen as lecturer', 'schedule.dosen1', '=', 'lecturer.id_dosen')
            ->select([
                'schedule.id_jadwal', 'schedule.id_prodi', 'schedule.id_kalender', 'schedule.id_matkul',
                'schedule.dosen1', 'schedule.dosen2', 'schedule.hari', 'schedule.jammulai',
                'schedule.jamselesai', 'schedule.nama_kelas', 'program.nama_program_studi',
                'program.nama_jenjang_pendidikan', 'program.ketua_prodi', 'calendar.namatahun',
                'course.kode_mata_kuliah', 'course.nama_mata_kuliah', 'room.nama_ruang',
                'lecturer.gelar_depan', 'lecturer.nama_dosen', 'lecturer.gelar_belakang', 'lecturer.nip',
            ]);
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

    private function availableCalendars(object $user, string $programId): Collection
    {
        $query = AcademicCalendarScope::excludeShortSemesters(
            DB::connection('simak_main')->table('s1m4k_kalender as calendar'),
            'calendar.tahun'
        )
            ->where('calendar.id_prodi', $programId);
        if (in_array($user->role, ['dosen', 'asdos'], true)) {
            $teacherId = $this->teacherId($user);
            $query->whereExists(function ($subquery) use ($teacherId): void {
                $subquery->selectRaw('1')
                    ->from('s1m4k_jadwal as schedule')
                    ->whereColumn('schedule.id_kalender', 'calendar.id_kalender')
                    ->where(function ($builder) use ($teacherId): void {
                        foreach (self::TEACHER_SLOTS as $slot) {
                            $builder->orWhere('schedule.'.$slot, $teacherId);
                        }
                    });
            });
        }

        return $query->orderByDesc('calendar.tahun')
            ->get(['calendar.id_kalender', 'calendar.id_prodi', 'calendar.tahun', 'calendar.namatahun', 'calendar.status']);
    }

    private function accessibleSchedule(object $user, string $scheduleId): object
    {
        $query = $this->scheduleQuery()
            ->where('schedule.id_jadwal', $scheduleId);

        if (in_array($user->role, ['userprodi', 'usertu'], true)) {
            $programId = trim((string) data_get($user, 'id_prodi'));
            abort_unless($programId !== '', 403);
            $query->where('schedule.id_prodi', $programId);
        } elseif (in_array($user->role, ['dosen', 'asdos'], true)) {
            $this->applyTeacherScope($query, $user);
        }

        $schedule = $query->first();
        abort_if($schedule === null, 404);

        return $schedule;
    }

    private function applyTeacherScope($query, object $user): void
    {
        $teacherId = $this->teacherId($user);
        $query->where(function ($builder) use ($teacherId): void {
            foreach (self::TEACHER_SLOTS as $slot) {
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

    private function studentsForSchedule(object $schedule, bool $approvedOnly): Collection
    {
        $query = DB::connection('simak_main')->table('s1m4k_krs as krs')
            ->join('s1m4k_mastermhs as student', 'krs.id_mahasiswa', '=', 'student.id_mahasiswa')
            ->where('krs.id_jadwal', $schedule->id_jadwal)
            ->where('krs.id_kalender', $schedule->id_kalender)
            ->when($approvedOnly, function ($builder) use ($schedule): void {
                $builder->whereExists(function ($subquery) use ($schedule): void {
                    $subquery->selectRaw('1')
                        ->from('s1m4k_pengesahankrs as approval')
                        ->whereColumn('approval.id_mahasiswa', 'student.id_mahasiswa')
                        ->where('approval.id_kalender', $schedule->id_kalender)
                        ->whereRaw("TRIM(COALESCE(approval.yang_mengesahkan, '')) <> ''")
                        ->whereRaw("TRIM(COALESCE(approval.nama_kpro, '')) <> ''");
                });
            })
            ->orderBy('student.nim')
            ->distinct();

        return $query->get(['student.id_mahasiswa', 'student.nim', 'student.nama_mahasiswa']);
    }

    private function attendancePdf(object $schedule, Collection $students): \FPDF
    {
        $pdf = new \FPDF('L', 'cm', 'Legal');
        $pdf->AddPage();
        $text = fn (string $value): string => $this->pdfText($value);
        $this->attendanceHeading($pdf, $schedule, $text, 'DAFTAR HADIR PERKULIAHAN');
        $this->attendanceTableHead($pdf, $text);
        $pdf->SetFont('Times', '', 8);
        foreach ($students as $index => $student) {
            if ($pdf->GetY() > 16.2) {
                $pdf->AddPage();
                $this->attendanceHeading($pdf, $schedule, $text, 'DAFTAR HADIR PERKULIAHAN (LANJUTAN)');
                $this->attendanceTableHead($pdf, $text);
                $pdf->SetFont('Times', '', 8);
            }
            $pdf->Cell(1.2, 0.65, (string) ($index + 1), 'LRTB', 0, 'C');
            $pdf->Cell(2.8, 0.65, $text((string) $student->nim), 'LRTB', 0, 'L');
            $pdf->Cell(5.2, 0.65, $text(ucwords(mb_strtolower((string) $student->nama_mahasiswa))), 'LRTB', 0, 'L');
            for ($meeting = 1; $meeting <= 16; $meeting++) {
                $pdf->Cell(1.53125, 0.65, '', 'LRTB', 0, 'C');
            }
            $pdf->Ln();
        }
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(9.2, 1.1, $text('Tanggal Perkuliahan'), 'LRTB', 0, 'R');
        for ($meeting = 1; $meeting <= 16; $meeting++) {
            $pdf->Cell(1.53125, 1.1, '', 'LRTB', 0, 'C');
        }
        $pdf->Ln();
        $pdf->Cell(9.2, 1.1, $text('Paraf Dosen'), 'LRTB', 0, 'R');
        for ($meeting = 1; $meeting <= 16; $meeting++) {
            $pdf->Cell(1.53125, 1.1, '', 'LRTB', 0, 'C');
        }

        return $pdf;
    }

    private function attendanceTableHead(\FPDF $pdf, callable $text): void
    {
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(1.2, 1.1, $text('No'), 'LRTB', 0, 'C');
        $pdf->Cell(2.8, 1.1, $text('NPM'), 'LRTB', 0, 'C');
        $pdf->Cell(5.2, 1.1, $text('Nama Mahasiswa'), 'LRTB', 0, 'C');
        $pdf->Cell(24.5, 0.55, $text('Pertemuan Ke'), 'LRTB', 0, 'C');
        $pdf->Ln();
        $pdf->Cell(9.2, 0.55, '', 'LRB', 0, 'C');
        for ($meeting = 1; $meeting <= 16; $meeting++) {
            $pdf->Cell(1.53125, 0.55, (string) $meeting, 'LRTB', 0, 'C');
        }
        $pdf->Ln();
    }

    private function classRecordPdf(object $schedule): \FPDF
    {
        $pdf = new \FPDF('L', 'cm', 'Legal');
        $pdf->AddPage();
        $text = fn (string $value): string => $this->pdfText($value);
        $this->attendanceHeading($pdf, $schedule, $text, 'BERITA ACARA PERKULIAHAN');
        $pdf->SetFont('Arial', 'B', 8);
        foreach ([[5, 'Pertemuan / Tanggal'], [8, 'Pokok Bahasan'], [8, 'Sub Pokok Bahasan'], [4.5, 'Tanda Tangan Dosen'], [4.5, 'Ketua Tingkat/Kelas'], [3, 'Ket']] as [$width, $label]) {
            $pdf->Cell($width, 1.2, $text($label), 'LRTB', 0, 'C');
        }
        $pdf->Ln();
        $pdf->SetFont('Times', '', 8);
        for ($meeting = 1; $meeting <= 16; $meeting++) {
            $pdf->Cell(5, 1.05, $text($meeting.'. ____________'), 'LRTB', 0, 'C');
            foreach ([8, 8, 4.5, 4.5, 3] as $width) {
                $pdf->Cell($width, 1.05, '', 'LRTB', 0, 'C');
            }
            $pdf->Ln();
        }

        return $pdf;
    }

    private function examPdf(object $schedule, Collection $students, string $document): \FPDF
    {
        $pdf = new \FPDF('P', 'cm', 'Letter');
        $pdf->AddPage();
        $text = fn (string $value): string => $this->pdfText($value);
        $logoPath = app(\App\Services\InstitutionSettings::class)->logoFilePath();
        if (is_file($logoPath)) {
            $pdf->Image($logoPath, 9.8, 0.3, 2, 2);
            $pdf->SetY(2.5);
        }
        $title = $document === 'midterm'
            ? 'PELAKSANAAN UJIAN TENGAH SEMESTER (UTS)'
            : 'PELAKSANAAN UJIAN AKHIR SEMESTER (UAS)';
        $pdf->SetFont('Courier', 'B', 9);
        $pdf->Cell(19, 0.5, $text('DAFTAR HADIR'), 0, 1, 'C');
        $pdf->Cell(19, 0.5, $text($title), 0, 1, 'C');
        $pdf->Cell(19, 0.5, $text('MATA KULIAH: '.mb_strtoupper($schedule->nama_mata_kuliah).' / '.mb_strtoupper($schedule->nama_kelas)), 0, 1, 'C');
        $pdf->Ln(0.5);
        $pdf->SetFont('Times', '', 9);
        $pdf->Cell(19, 0.5, $text('Tahun akademik: '.$schedule->namatahun), 0, 1, 'L');
        $pdf->Cell(19, 0.5, $text('Hari/jam: '.ucfirst(mb_strtolower((string) $schedule->hari)).' / '.$schedule->jammulai.' - '.$schedule->jamselesai), 0, 1, 'L');
        $pdf->Cell(19, 0.5, $text('Ruang: '.($schedule->nama_ruang ?: '-')), 0, 1, 'L');
        $pdf->Ln(0.5);
        $pdf->SetFont('Courier', 'B', 8);
        foreach ([[0.9, 'No'], [2.5, 'NPM'], [6, 'Nama Mahasiswa'], [6.3, 'Tanda Tangan'], [3.3, 'Keterangan']] as [$width, $label]) {
            $pdf->Cell($width, 0.65, $text($label), 'LRTB', 0, 'C');
        }
        $pdf->Ln();
        $pdf->SetFont('Courier', '', 8);
        foreach ($students as $index => $student) {
            if ($pdf->GetY() > 22) {
                $pdf->AddPage();
            }
            $pdf->Cell(0.9, 0.9, (string) ($index + 1), 'LRTB', 0, 'C');
            $pdf->Cell(2.5, 0.9, $text((string) $student->nim), 'LRTB', 0, 'L');
            $pdf->Cell(6, 0.9, $text(ucwords(mb_strtolower((string) $student->nama_mahasiswa))), 'LRTB', 0, 'L');
            $pdf->Cell(6.3, 0.9, '', 'LRTB', 0, 'C');
            $pdf->Cell(3.3, 0.9, '', 'LRTB', 0, 'C');
            $pdf->Ln();
        }
        $pdf->Ln(0.5);
        $pdf->MultiCell(19, 0.5, $text('Catatan: Mahasiswa yang namanya tidak tercantum pada daftar hadir tidak diperkenankan mengikuti ujian.'), 0, 'L');
        $pdf->Ln(0.4);
        $teacherName = LecturerName::format(
            ucwords(mb_strtolower((string) $schedule->nama_dosen)),
            $schedule->gelar_depan,
            $schedule->gelar_belakang,
        );
        $pdf->Cell(10, 0.6, $text('Pengawas: ______________________________'), 0, 0, 'L');
        $pdf->Cell(9, 0.6, $text('Dosen Pengasuh Mata Kuliah'), 0, 1, 'C');
        $pdf->Ln(1.4);
        $pdf->Cell(10, 0.6, $text('Nama / Tanda Tangan: ____________________'), 0, 0, 'L');
        $pdf->Cell(9, 0.6, $text($teacherName), 'B', 1, 'C');
        $pdf->Cell(10, 0.6, '', 0, 0, 'L');
        $pdf->Cell(9, 0.6, $text('NIP. '.($schedule->nip ?: '-')), 0, 1, 'C');
        $pdf->Ln(0.6);
        $pdf->Cell(19, 0.6, $text('Mengetahui, Ketua Program Studi '.ucwords(mb_strtolower($schedule->nama_program_studi))), 0, 1, 'C');
        $pdf->Ln(1.5);
        $pdf->Cell(19, 0.6, $text('_______________________________'), 0, 1, 'C');
        $pdf->Cell(19, 0.6, $text('Ketua Program Studi'), 0, 1, 'C');

        return $pdf;
    }

    private function rosterExcel(object $schedule, Collection $students): Response
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $this->setRosterCell($sheet, 'A1', 'Daftar Mahasiswa (Matakuliah '.ucwords(mb_strtolower((string) $schedule->nama_mata_kuliah)).' Kelas '.ucwords(mb_strtolower((string) $schedule->nama_kelas)).')');
        $this->setRosterCell($sheet, 'A2', 'NO');
        $this->setRosterCell($sheet, 'B2', 'NPM');
        $this->setRosterCell($sheet, 'C2', 'NAMA MAHASISWA');
        foreach ($students as $index => $student) {
            $row = $index + 3;
            $this->setRosterCell($sheet, 'A'.$row, (string) ($index + 1));
            $this->setRosterCell($sheet, 'B'.$row, (string) $student->nim);
            $this->setRosterCell($sheet, 'C'.$row, ucwords(mb_strtolower((string) $student->nama_mahasiswa)));
        }

        ob_start();
        try {
            (new Xls($spreadsheet))->save('php://output');
            $content = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        abort_if($content === false, 500, 'File roster Excel tidak dapat dibuat.');

        return response($content, 200, [
            'Content-Type' => 'application/vnd.ms-excel',
            'Content-Disposition' => 'attachment; filename="'.$this->rosterFilename($schedule).'"',
        ]);
    }

    private function setRosterCell(Worksheet $sheet, string $coordinate, string $value): void
    {
        $sheet->setCellValueExplicit($coordinate, $value, DataType::TYPE_STRING);
    }

    private function rosterFilename(object $schedule): string
    {
        $course = str_replace(' ', '', ucwords(mb_strtolower((string) $schedule->nama_mata_kuliah)));
        $class = str_replace(' ', '', ucwords(mb_strtolower((string) $schedule->nama_kelas)));

        return 'Absen-Matakuliah-'.$course.'(Kelas-'.$class.').xls';
    }

    private function attendanceHeading(\FPDF $pdf, object $schedule, callable $text, string $title): void
    {
        $imageDirectory = public_path('images/kkn');
        foreach ([[$imageDirectory.'/dikbud.jpg', 6], [app(\App\Services\InstitutionSettings::class)->logoFilePath(), 27]] as [$image, $x]) {
            if (is_file($image)) {
                $pdf->Image($image, $x, 0.5, 2, 2);
            }
        }
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(33, 0.5, $text($title.' '.mb_strtoupper((string) $schedule->namatahun)), 0, 1, 'C');
        $pdf->Cell(33, 0.5, $text('PROGRAM STUDI '.mb_strtoupper((string) $schedule->nama_program_studi)), 0, 1, 'C');
        $pdf->Cell(33, 0.5, $text(mb_strtoupper(app(\App\Services\InstitutionSettings::class)->institutionName())), 0, 1, 'C');
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
            ['Ruangan', (string) ($schedule->nama_ruang ?: '-')],
            ['Hari / Jam', ucfirst(mb_strtolower((string) $schedule->hari)).' / '.$schedule->jammulai.' s/d '.$schedule->jamselesai],
        ] as [$label, $value]) {
            $pdf->Cell(3.2, 0.42, $text($label), 0, 0, 'L');
            $pdf->Cell(0.3, 0.42, ':', 0, 0, 'C');
            $pdf->Cell(15, 0.42, $text($value), 0, 1, 'L');
        }
        $pdf->Ln(0.15);
    }

    private function pdfText(string $value): string
    {
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $value) ?: '';
    }

    private function filename(string $document, object $schedule): string
    {
        $name = Str::slug($schedule->nama_mata_kuliah.'-'.$schedule->nama_kelas) ?: 'mata-kuliah';
        $prefix = match ($document) {
            'attendance' => 'Absen',
            'class-record' => 'Berita-Acara-Perkuliahan',
            'midterm' => 'Berita-Acara-UTS',
            'final' => 'Berita-Acara-UAS',
            default => 'Daftar-Mahasiswa',
        };

        return $prefix.'-'.$name;
    }

    private function authorizeRole(object $user): void
    {
        abort_unless(in_array($user->role, self::ROLES, true), 403);
    }
}
