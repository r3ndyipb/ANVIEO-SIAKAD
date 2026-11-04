<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Services\QrCodeService;
use App\Services\SimakNotificationService;
use App\Support\AcademicCalendarScope;
use App\Support\LecturerName;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class KrsController extends Controller
{
    private const ROLES = ['admin', 'pimuniv', 'pimfak', 'userprodi', 'usertu', 'dosen', 'mahasiswa', 'validasi', 'wisuda', 'regulang'];

    private const ACTIVE_STUDENT_STATUSES = ['A', 'C', 'G', 'N', 'Z', 'M'];

    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        abort_unless(in_array($user->role, self::ROLES, true), 403);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'calendar' => ['nullable', 'string', 'max:100'],
        ]);

        if (in_array($user->role, ['admin', 'userprodi', 'usertu', 'validasi', 'wisuda', 'regulang'], true)
            && filled($filters['search'] ?? null)
            && ctype_digit(trim($filters['search']))) {
            $student = $this->findStudent($user, trim($filters['search']));
            if ($student === null) {
                return redirect()->route('krs.index')
                    ->withInput(['search' => $filters['search']])
                    ->withErrors(['search' => 'Data mahasiswa dengan NPM tersebut tidak ditemukan.']);
            }

            return redirect()->route('krs.edit', ['student' => $student->id_mahasiswa]);
        }

        $studentQuery = $this->studentListQuery($user, $filters['search'] ?? null);
        if (in_array($user->role, ['admin', 'userprodi', 'usertu', 'pimuniv', 'pimfak', 'validasi', 'wisuda', 'regulang'], true)
            && blank($filters['search'] ?? null)) {
            $studentQuery->whereRaw('1 = 0');
        }
        $studentQuery
            ->orderBy('student.nama_mahasiswa')
            ->orderBy('student.nim');
        $students = $user->role === 'dosen'
            ? $this->decorateStudents($studentQuery->get())
            : $studentQuery->paginate(25)->withQueryString();
        if ($students instanceof LengthAwarePaginator) {
            $students->setCollection($this->decorateStudents($students->getCollection()));
        }
        $activeCalendar = null;
        if (in_array($user->role, ['userprodi', 'usertu'], true)) {
            $programId = $this->requiredProgramId($user);
            $activeCalendar = DB::connection('simak_main')->table('s1m4k_kalender')
                ->where('id_prodi', $programId)
                ->where('status', 'Y')
                ->orderByDesc('tahun')
                ->first(['id_kalender']);
        }

        $student = $user->role === 'mahasiswa'
            ? $this->findStudent($user, (string) $user->login)
            : null;

        return $this->renderIndex($user, $filters, $students, $activeCalendar, $student, false);
    }

    public function edit(Request $request, string $studentId): View
    {
        $user = $request->user();
        abort_unless(in_array($user->role, self::ROLES, true) && $user->role !== 'mahasiswa', 403);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'calendar' => ['nullable', 'string', 'max:100'],
        ]);
        $student = $this->findStudentById($user, $studentId);
        abort_unless($this->canManageStudent($user, $student), 403);

        return $this->renderIndex($user, $filters, collect(), null, $student, true);
    }

    public function print(Request $request, string $studentId, QrCodeService $qrCodes): Response
    {
        $user = $request->user();
        abort_unless(in_array($user->role, self::ROLES, true), 403);
        $filters = $request->validate([
            'calendar' => ['nullable', 'string', 'max:100'],
        ]);
        $student = $this->findStudentById($user, $studentId);
        abort_unless($this->canManageStudent($user, $student), 403);
        $calendar = filled($filters['calendar'] ?? null)
            ? DB::connection('simak_main')->table('s1m4k_kalender')
                ->where('id_kalender', $filters['calendar'])
                ->where('id_prodi', $student->id_prodi)
                ->first()
            : $this->activeCalendar($student);
        abort_if($calendar === null, 404);

        $approval = DB::connection('simak_main')->table('s1m4k_pengesahankrs')
            ->where('id_mahasiswa', $student->id_mahasiswa)
            ->where('id_kalender', $calendar->id_kalender)
            ->first();
        abort_unless(filled($approval?->yang_mengesahkan) && filled($approval?->nama_kpro), 403, 'KRS belum disahkan PA dan program studi.');

        $connection = DB::connection('simak_main');
        $details = $connection->table('s1m4k_mastermhs as student')
            ->leftJoin('s1m4k_masterprodi as program', 'student.id_prodi', '=', 'program.id_prodi')
            ->leftJoin('s1m4k_masterdosen as pa', 'student.dosen_pa', '=', 'pa.id_dosen')
            ->leftJoin('s1m4k_masterdosen as chair', 'program.ketua_prodi', '=', 'chair.id_dosen')
            ->where('student.id_mahasiswa', $studentId)
            ->select([
                'student.id_mahasiswa', 'student.id_prodi', 'student.nim', 'student.nama_mahasiswa',
                'program.nama_program_studi', 'program.ketua_prodi',
                'pa.gelar_depan as pa_gelar_depan', 'pa.nama_dosen as pa_nama', 'pa.gelar_belakang as pa_gelar_belakang', 'pa.nip as pa_nip',
                'chair.gelar_depan as chair_gelar_depan', 'chair.nama_dosen as chair_nama', 'chair.gelar_belakang as chair_gelar_belakang', 'chair.nip as chair_nip',
            ])->first();
        abort_if($details === null, 404);

        $lastActiveTerm = $this->lastActiveTerm($student, $calendar);
        $ips = $this->ipsForLastActiveTerm($student, $calendar, $lastActiveTerm);
        $maximumCredits = $this->maximumCredits($student, $calendar);

        $ipkCourses = $connection->table('s1m4k_krs as krs')
            ->join('s1m4k_mastermk as course', 'krs.id_matkul', '=', 'course.id_matkul')
            ->where('krs.id_mahasiswa', $studentId)
            ->where('krs.tahun', '<', $calendar->tahun)
            ->select('krs.id_matkul', 'krs.nilai_bobot', 'course.sks_mata_kuliah')
            ->get()
            ->groupBy('id_matkul')
            ->map(fn ($attempts): object => $attempts->sortByDesc('nilai_bobot')->first());
        $ipkCredits = (float) $ipkCourses->sum(fn (object $row): float => (float) $row->sks_mata_kuliah);
        $ipk = $ipkCredits > 0
            ? (float) $ipkCourses->sum(fn (object $row): float => (float) $row->nilai_bobot * (float) $row->sks_mata_kuliah) / $ipkCredits
            : 0.0;
        $passedCredits = (float) $ipkCourses
            ->filter(fn (object $row): bool => (float) $row->nilai_bobot > 0)
            ->sum(fn (object $row): float => (float) $row->sks_mata_kuliah);

        $courses = $connection->table('s1m4k_krs as krs')
            ->leftJoin('s1m4k_mastermk as course', 'krs.id_matkul', '=', 'course.id_matkul')
            ->leftJoin('s1m4k_jadwal as schedule', 'krs.id_jadwal', '=', 'schedule.id_jadwal')
            ->where('krs.id_mahasiswa', $studentId)
            ->where('krs.id_kalender', $calendar->id_kalender)
            ->select([
                'krs.status_krs', 'krs.id_matkul', 'course.kode_mata_kuliah',
                'course.nama_mata_kuliah', 'course.sks_mata_kuliah', 'schedule.nama_kelas',
            ])
            ->orderBy('course.semester')
            ->orderBy('course.nama_mata_kuliah')
            ->get()
            ->unique('id_matkul')
            ->values();
        abort_if($courses->isEmpty(), 422, 'Mahasiswa belum memprogramkan mata kuliah.');

        $hasKrs = $connection->table('s1m4k_krs')
            ->where('id_mahasiswa', $studentId)
            ->where('id_kalender', $calendar->id_kalender)
            ->exists();
        abort_unless($hasKrs, 404, 'KRS mahasiswa tidak ditemukan pada tahun akademik yang dipilih.');

        abort_unless(class_exists(\FPDF::class), 500, 'Library cetak KRS tidak tersedia.');

        $verificationUrl = route('document-verification.show', ['code' => $approval->id_pengesahan], true);
        $qrPngFile = $qrCodes->generate($verificationUrl, 'simak-krs-qr-');

        try {
            $pdf = new \FPDF('P', 'cm', 'A4');
            $pdf->AddPage();
            $imageDir = public_path('images/kkn');
            foreach ([
                [$imageDir.'/dikbud.jpg', 2.5],
                [app(\App\Services\InstitutionSettings::class)->logoFilePath(), 17],
            ] as [$imagePath, $x]) {
                if (is_file($imagePath)) {
                    $pdf->Image($imagePath, $x, 0.5, 2, 2);
                }
            }

            $text = fn (string $value): string => iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $value) ?: '';
            $pdf->SetFont('Arial', 'B', 11);
            foreach ([
                ...app(\App\Services\InstitutionSettings::class)->ministryNameLines(),
                mb_strtoupper(app(\App\Services\InstitutionSettings::class)->institutionName()),
            ] as $line) {
                $pdf->Cell(19.5, 0.5, $text($line), 0, 1, 'C');
            }
            $pdf->Ln();
            $pdf->Cell(19.5, 0.5, $text('Kartu Rencana Studi (KRS)'), 0, 1, 'C');
            $pdf->Cell(19.5, 0.5, $text((string) ($calendar->namatahun ?: $calendar->tahun)), 0, 1, 'C');
            $pdf->Line(1, 3.8, 20, 3.8);
            $pdf->Ln();

            $pdf->SetFont('Times', '', 10);
            $this->pdfPair($pdf, $text, 'NPM', (string) $details->nim, 'IP Semester Lalu', number_format($ips, 2));
            $this->pdfPair($pdf, $text, 'Nama', ucwords(strtolower((string) $details->nama_mahasiswa)), 'Maksimum Kredit SKS', $maximumCredits.' SKS');
            $paName = LecturerName::format(
                $details->pa_nama ? ucwords(strtolower((string) $details->pa_nama)) : null,
                $details->pa_gelar_depan,
                $details->pa_gelar_belakang,
            );
            $chairName = LecturerName::format(
                $details->chair_nama ? ucwords(strtolower((string) $details->chair_nama)) : null,
                $details->chair_gelar_depan,
                $details->chair_gelar_belakang,
            );
            $this->pdfPair($pdf, $text, 'Penasehat Akademik', $paName, 'SKS Direncanakan', $courses->sum(fn (object $row): float => (float) $row->sks_mata_kuliah).' SKS');
            $this->pdfPair($pdf, $text, 'IPK', number_format($ipk, 2), 'SKS Lulus', number_format($passedCredits, 0).' SKS');
            $pdf->Ln();

            $pdf->SetFont('Times', 'B', 10);
            foreach ([[1.5, 'No'], [2.5, 'Kode Mk'], [12.5, 'Nama Matakuliah'], [1, 'B/U'], [2, 'SKS']] as [$width, $label]) {
                $pdf->Cell($width, 0.5, $text($label), 'LRTB', 0, 'C');
            }
            $pdf->Ln();
            $pdf->SetFont('Times', '', 10);
            foreach ($courses as $index => $course) {
                $pdf->Cell(1.5, 0.5, (string) ($index + 1), 'LRTB', 0, 'C');
                $pdf->Cell(2.5, 0.5, $text((string) $course->kode_mata_kuliah), 'LRTB', 0, 'C');
                $pdf->Cell(12.5, 0.5, $text(ucwords(strtolower((string) $course->nama_mata_kuliah))), 'LRTB', 0);
                $pdf->Cell(1, 0.5, $text((string) $course->status_krs), 'LRTB', 0, 'C');
                $pdf->Cell(2, 0.5, $text((string) (int) $course->sks_mata_kuliah), 'LRTB', 0, 'C');
                $pdf->Ln();
            }
            $pdf->SetFont('Times', 'B', 10);
            $pdf->Cell(17.5, 0.5, $text('Jumlah SKS'), 'LRTB', 0, 'L');
            $pdf->Cell(2, 0.5, (string) (int) $courses->sum(fn (object $row): float => (float) $row->sks_mata_kuliah), 'LRTB', 0, 'C');
            $pdf->Ln();
            $pdf->SetFont('Times', '', 10);
            $pdf->Cell(19, 0.5, $text('Keterangan : B = Program Baru, U = Program Ulang'), 0, 1, 'L');
            $pdf->Ln(2);

            $pdf->Cell(19, 0.5, $text('Ternate, '.now()->locale('id')->translatedFormat('d F Y')), 0, 1, 'R');
            $pdf->Ln(0.5);
            $pdf->SetX(1);
            $pdf->Cell(6.5, 0.5, $text('Ketua Program Studi,'), 0, 0, 'C');
            $pdf->Cell(6.5, 0.5, $text('Penasehat Akademik,'), 0, 0, 'C');
            $pdf->Cell(6.5, 0.5, $text('Mahasiswa,'), 0, 1, 'C');
            $qrY = $pdf->GetY();
            $pdf->Image($qrPngFile, 3.25, $qrY, 2, 2);
            $pdf->Image($qrPngFile, 9.75, $qrY, 2, 2);
            $pdf->Image($qrPngFile, 16.25, $qrY, 2, 2);
            $pdf->SetY($qrY + 2.1);
            $pdf->SetX(1);
            $pdf->Cell(6.5, 0.5, $text($chairName), 0, 0, 'C');
            $pdf->Cell(6.5, 0.5, $text($paName), 0, 0, 'C');
            $pdf->Cell(6.5, 0.5, $text(ucwords(strtolower((string) $details->nama_mahasiswa))), 0, 1, 'C');
            $pdf->SetX(1);
            $pdf->Cell(6.5, 0.5, $text('NIP. '.(string) $details->chair_nip), 0, 0, 'C');
            $pdf->Cell(6.5, 0.5, $text('NIP. '.(string) $details->pa_nip), 0, 0, 'C');
            $pdf->Cell(6.5, 0.5, $text((string) $details->nim), 0, 0, 'C');

            return response($pdf->Output('S'), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="KRS-Mahasiswa-'.$details->nim.'.pdf"',
            ]);
        } finally {
            if (is_file($qrPngFile)) {
                unlink($qrPngFile);
            }
        }
    }

    public function verifyDocument(string $code): Response
    {
        $approval = DB::connection('simak_main')->table('s1m4k_pengesahankrs as approval')
            ->leftJoin('s1m4k_mastermhs as student', 'approval.id_mahasiswa', '=', 'student.id_mahasiswa')
            ->leftJoin('s1m4k_kalender as calendar', 'approval.id_kalender', '=', 'calendar.id_kalender')
            ->where('approval.id_pengesahan', $code)
            ->select([
                'approval.id_pengesahan', 'approval.yang_mengesahkan', 'approval.tgl_pengesahan',
                'approval.nama_kpro', 'approval.tgl_pengesahan_kpro',
                'student.nim', 'student.nama_mahasiswa', 'calendar.namatahun',
            ])->first();
        $valid = $approval !== null
            && filled($approval->yang_mengesahkan)
            && filled($approval->nama_kpro)
            && filled($approval->nim)
            && filled($approval->nama_mahasiswa);

        return response()->view('krs.verification', compact('approval', 'valid'), $valid ? 200 : 404);
    }

    private function renderIndex(
        object $user,
        array $filters,
        $students,
        ?object $activeCalendar,
        ?object $student,
        bool $isStudentForm
    ): View {
        $krs = collect();
        $offerings = collect();
        $packageClasses = collect();
        $calendar = null;
        $approval = null;
        $registered = false;
        $canEdit = false;
        $canApprovePa = false;
        $canApproveProgram = false;
        $canResetApproval = false;
        $calendars = collect();
        $isActiveCalendar = false;
        $maxCredits = null;
        $lastActiveTerm = null;
        $lastActiveIps = null;
        $creditsTaken = 0.0;
        $krsWindowOpen = false;
        $packageSemester = null;

        if ($student !== null) {
            $activeCalendar = $this->activeCalendar($student);
            $calendars = $this->availableCalendars($student, $activeCalendar);
            if (filled($filters['calendar'] ?? null)) {
                $calendar = $calendars->firstWhere('id_kalender', $filters['calendar']);
                abort_if($calendar === null, 404, 'Kalender akademik tidak memiliki KRS mahasiswa atau di luar cakupan program studi.');
            } else {
                $calendar = $activeCalendar ?? $calendars->first();
            }
            $isActiveCalendar = $calendar !== null
                && $activeCalendar !== null
                && (string) $calendar->id_kalender === (string) $activeCalendar->id_kalender;

            if ($calendar !== null) {
                $registered = DB::connection('simak_main')->table('s1m4k_registrasiulang')
                    ->where('id_mahasiswa', $student->id_mahasiswa)
                    ->where('id_kalender', $calendar->id_kalender)
                    ->exists();
                $approval = DB::connection('simak_main')->table('s1m4k_pengesahankrs')
                    ->where('id_mahasiswa', $student->id_mahasiswa)
                    ->where('id_kalender', $calendar->id_kalender)
                    ->first();
                $krs = $this->currentKrs((string) $student->id_mahasiswa, (string) $calendar->id_kalender);
                $creditsTaken = (float) $krs->sum(fn (object $row): float => (float) ($row->sks_mata_kuliah ?? 0));
                $maxCredits = $this->maximumCredits($student, $calendar, $user->role);
                $lastActiveTerm = $this->lastActiveTerm($student, $calendar);
                $lastActiveIps = $lastActiveTerm === null
                    ? null
                    : $this->ipsForLastActiveTerm($student, $calendar, $lastActiveTerm);
                $krsWindowOpen = $this->krsWindowOpen($calendar);
                $canEdit = $isActiveCalendar
                    && in_array($user->role, ['userprodi', 'dosen', 'mahasiswa'], true)
                    && $this->canManageStudent($user, $student)
                    && $registered
                    && match ($user->role) {
                        'mahasiswa' => $krsWindowOpen && $approval === null,
                        'dosen' => blank($approval?->nama_kpro),
                        'userprodi' => true,
                        default => false,
                    };
                $canApprovePa = $isActiveCalendar
                    && in_array($user->role, ['userprodi', 'dosen'], true)
                    && $this->canManageStudent($user, $student)
                    && $registered
                    && $krs->isNotEmpty()
                    && empty($approval?->yang_mengesahkan);
                $canApproveProgram = $isActiveCalendar
                    && $user->role === 'userprodi'
                    && $this->canManageStudent($user, $student)
                    && $krs->isNotEmpty()
                    && filled($approval?->yang_mengesahkan)
                    && blank($approval?->nama_kpro);
                $canResetApproval = $isActiveCalendar
                    && $user->role === 'userprodi'
                    && $this->canManageStudent($user, $student)
                    && filled($approval?->yang_mengesahkan)
                    && blank($approval?->nama_kpro);

                if ($canEdit) {
                    $offerings = $this->offeredSchedules($student, $calendar);
                    if (in_array($user->role, ['mahasiswa', 'dosen'], true)
                        && $this->canProgramPackage($user, $student)) {
                        $packageSemester = substr((string) $calendar->tahun, 4, 1) === '1' ? 1 : 2;
                        $packageClasses = $this->packageClasses($student, $calendar, $packageSemester);
                    }
                }
            }
        }

        return view('krs.index', compact(
            'user',
            'filters',
            'students',
            'activeCalendar',
            'student',
            'isStudentForm',
            'calendars',
            'calendar',
            'registered',
            'approval',
            'krs',
            'offerings',
            'packageClasses',
            'canEdit',
            'canApprovePa',
            'canApproveProgram',
            'canResetApproval',
            'maxCredits',
            'lastActiveTerm',
            'lastActiveIps',
            'creditsTaken',
            'krsWindowOpen',
            'packageSemester',
        ));
    }

    public function save(
        Request $request,
        string $studentId,
        ActivityLogger $activityLogger,
        SimakNotificationService $notifications
    ): RedirectResponse {
        $user = $request->user();
        abort_unless(in_array($user->role, ['userprodi', 'dosen', 'mahasiswa'], true), 403);
        $student = $this->findStudentById($user, $studentId);
        abort_unless($this->canManageStudent($user, $student), 403);

        $validated = $request->validate([
            'schedule_ids' => ['required', 'array', 'min:1'],
            'schedule_ids.*' => ['required', 'string', 'max:50', 'distinct'],
        ]);
        $calendar = $this->activeCalendar($student);
        abort_if($calendar === null, 422, 'Kalender akademik aktif tidak tersedia.');

        $connection = DB::connection('simak_main');
        $connection->transaction(function () use ($connection, $user, $student, $calendar, $validated, $activityLogger): void {
            $lockedStudent = $connection->table('s1m4k_mastermhs')
                ->where('id_mahasiswa', $student->id_mahasiswa)
                ->lockForUpdate()
                ->first();
            abort_if($lockedStudent === null, 404);
            $this->assertKrsCanBeChanged($user, $lockedStudent, $calendar);

            $scheduleIds = $validated['schedule_ids'];
            $offerings = $connection->table('s1m4k_jadwal as schedule')
                ->join('s1m4k_mastermk as course', 'schedule.id_matkul', '=', 'course.id_matkul')
                ->leftJoin('s1m4k_masterruang as room', 'schedule.id_ruang', '=', 'room.id_ruang')
                ->where('schedule.id_prodi', $student->id_prodi)
                ->where('schedule.id_kalender', $calendar->id_kalender)
                ->whereIn('schedule.id_jadwal', $scheduleIds)
                ->select([
                    'schedule.id_jadwal', 'schedule.id_prodi', 'schedule.id_kalender',
                    'schedule.id_matkul', 'schedule.id_fakultas', 'schedule.id_perguruan_tinggi',
                    'schedule.nama_kelas', 'schedule.hari', 'schedule.jammulai', 'schedule.jamselesai',
                    'course.kode_mata_kuliah', 'course.nama_mata_kuliah', 'course.sks_mata_kuliah',
                    'room.kapasitas',
                ])->get();

            if ($offerings->count() !== count($scheduleIds)) {
                throw ValidationException::withMessages([
                    'schedule_ids' => 'Satu atau lebih jadwal tidak tersedia untuk program studi dan kalender akademik mahasiswa.',
                ]);
            }
            if ($offerings->pluck('id_matkul')->unique()->count() !== $offerings->count()) {
                throw ValidationException::withMessages([
                    'schedule_ids' => 'Setiap mata kuliah hanya dapat dipilih satu kali. Pilih satu kelas untuk tiap mata kuliah.',
                ]);
            }
            $oldScheduleIds = $connection->table('s1m4k_krs')
                ->where('id_mahasiswa', $student->id_mahasiswa)
                ->where('id_kalender', $calendar->id_kalender)
                ->pluck('id_jadwal');
            $this->assertScheduleCapacity($connection, $offerings, $oldScheduleIds);

            $creditSum = (float) $offerings->sum(fn (object $row): float => (float) $row->sks_mata_kuliah);
            $maxCredits = $this->maximumCredits($student, $calendar, $user->role);
            if ($creditSum > $maxCredits) {
                throw ValidationException::withMessages([
                    'schedule_ids' => "Jumlah SKS yang dipilih ({$creditSum}) melebihi batas {$maxCredits} SKS.",
                ]);
            }

            $oldRows = $connection->table('s1m4k_krs')
                ->where('id_mahasiswa', $student->id_mahasiswa)
                ->where('id_kalender', $calendar->id_kalender)
                ->get();
            $oldApproval = $connection->table('s1m4k_pengesahankrs')
                ->where('id_mahasiswa', $student->id_mahasiswa)
                ->where('id_kalender', $calendar->id_kalender)
                ->first();

            $connection->table('s1m4k_krs')
                ->where('id_mahasiswa', $student->id_mahasiswa)
                ->where('id_kalender', $calendar->id_kalender)
                ->delete();
            $isProgramStaff = $user->role === 'userprodi';
            if (! $isProgramStaff) {
                $connection->table('s1m4k_pengesahankrs')
                    ->where('id_mahasiswa', $student->id_mahasiswa)
                    ->where('id_kalender', $calendar->id_kalender)
                    ->delete();
            }

            $newRows = [];
            foreach ($offerings as $offering) {
                $priorAttempts = $connection->table('s1m4k_krs')
                    ->where('id_mahasiswa', $student->id_mahasiswa)
                    ->where('id_matkul', $offering->id_matkul)
                    ->count();
                $newRows[] = [
                    'id_krs' => (string) Str::uuid(),
                    'id_perguruan_tinggi' => $offering->id_perguruan_tinggi,
                    'id_fakultas' => $offering->id_fakultas,
                    'id_prodi' => $offering->id_prodi,
                    'id_kalender' => $offering->id_kalender,
                    'tahun' => $calendar->tahun,
                    'id_mahasiswa' => $student->id_mahasiswa,
                    'id_jadwal' => $offering->id_jadwal,
                    'id_matkul' => $offering->id_matkul,
                    'status_krs' => $priorAttempts <= 1 ? 'B' : 'U',
                    'tgl_update' => now()->toDateString(),
                    'nilai_huruf' => null,
                    'krs_reguler' => 'Y',
                    'krs_mbkm' => 'N',
                    'isi_edom' => 'N',
                    'user' => (string) $user->legacy_id,
                ];
            }
            $connection->table('s1m4k_krs')->insert($newRows);

            if (! in_array($user->role, ['mahasiswa', 'userprodi'], true)) {
                $this->insertPaApproval($connection, $student, $calendar, $user);
            }

            $newApproval = $connection->table('s1m4k_pengesahankrs')
                ->where('id_mahasiswa', $student->id_mahasiswa)
                ->where('id_kalender', $calendar->id_kalender)
                ->first();
            $changes = [
                'data_lama' => $oldRows->map(fn (object $row): array => (array) $row)->all(),
                'data_baru' => $newRows,
                'pengesahan_sebelumnya' => $oldApproval === null ? null : (array) $oldApproval,
                'pengesahan_baru' => $newApproval === null ? null : (array) $newApproval,
            ];
            $activityLogger->record(
                $user,
                'KRS Mahasiswa',
                $oldRows->isEmpty() ? 'Program KRS' : 'Ubah KRS',
                $changes,
                (string) $student->id_mahasiswa,
                $this->studentIdentity($student)
            );
        });
        if ($user->role === 'mahasiswa') {
            $message = sprintf(
                '%s (%s) telah mengisi atau memperbarui KRS untuk %s.',
                (string) $student->nama_mahasiswa,
                (string) $student->nim,
                (string) ($calendar->namatahun ?? $calendar->tahun),
            );
            $url = route('krs.edit', ['student' => $student->id_mahasiswa]);
            if (filled($student->dosen_pa)) {
                $notifications->send('dosen', (string) $student->dosen_pa, 'krs-submitted', 'Pengisian KRS Mahasiswa', $message, $url);
            }
            $notifications->sendProgram((string) $student->id_prodi, 'krs-submitted', 'Pengisian KRS Mahasiswa', $message, $url);
        }

        $redirectParameters = $user->role === 'mahasiswa' ? [] : array_filter([
            'student' => $student->id_mahasiswa,
            'search' => $request->query('search'),
        ]);

        return redirect()->route($user->role === 'mahasiswa' ? 'krs.index' : 'krs.edit', $redirectParameters)
            ->with('status', 'KRS mahasiswa berhasil disimpan.');
    }

    public function savePackage(
        Request $request,
        string $studentId,
        ActivityLogger $activityLogger,
        SimakNotificationService $notifications
    ): RedirectResponse {
        $user = $request->user();
        abort_unless(in_array($user->role, ['mahasiswa', 'dosen'], true), 403);
        $student = $this->findStudentById($user, $studentId);
        abort_unless($this->canManageStudent($user, $student), 403);
        abort_unless($this->canProgramPackage($user, $student), 403, 'KRS paket hanya tersedia untuk mahasiswa dengan paling banyak dua semester terprogram.');
        $class = $request->validate([
            'kelas' => ['required', 'string', 'max:100'],
        ])['kelas'];
        $calendar = $this->activeCalendar($student);
        abort_if($calendar === null, 422, 'Kalender akademik aktif tidak tersedia.');
        $semester = substr((string) $calendar->tahun, 4, 1) === '1' ? 1 : 2;
        $connection = DB::connection('simak_main');

        $connection->transaction(function () use ($connection, $user, $student, $calendar, $semester, $class, $activityLogger): void {
            $lockedStudent = $connection->table('s1m4k_mastermhs')
                ->where('id_mahasiswa', $student->id_mahasiswa)
                ->lockForUpdate()
                ->first();
            abort_if($lockedStudent === null, 404);
            $this->assertKrsCanBeChanged($user, $lockedStudent, $calendar);

            $offerings = $connection->table('s1m4k_jadwal as schedule')
                ->join('s1m4k_mastermk as course', 'schedule.id_matkul', '=', 'course.id_matkul')
                ->leftJoin('s1m4k_masterruang as room', 'schedule.id_ruang', '=', 'room.id_ruang')
                ->where('schedule.id_prodi', $student->id_prodi)
                ->where('schedule.id_kalender', $calendar->id_kalender)
                ->where('schedule.nama_kelas', $class)
                ->where('course.semester', '<=', $semester)
                ->select([
                    'schedule.id_jadwal', 'schedule.id_prodi', 'schedule.id_kalender',
                    'schedule.id_matkul', 'schedule.id_fakultas', 'schedule.id_perguruan_tinggi',
                    'schedule.nama_kelas', 'course.nama_mata_kuliah', 'course.sks_mata_kuliah',
                    'room.kapasitas',
                ])->get();

            if ($offerings->isEmpty()) {
                throw ValidationException::withMessages([
                    'kelas' => 'Kelas paket tidak memiliki jadwal yang dapat diprogram pada kalender aktif.',
                ]);
            }
            if ($offerings->pluck('id_matkul')->unique()->count() !== $offerings->count()) {
                throw ValidationException::withMessages([
                    'kelas' => 'Kelas paket memiliki lebih dari satu jadwal untuk mata kuliah yang sama. Periksa data jadwal paket.',
                ]);
            }
            $oldScheduleIds = $connection->table('s1m4k_krs')
                ->where('id_mahasiswa', $student->id_mahasiswa)
                ->where('id_kalender', $calendar->id_kalender)
                ->pluck('id_jadwal');
            $this->assertScheduleCapacity($connection, $offerings, $oldScheduleIds, 'kelas');

            $creditSum = (float) $offerings->sum(fn (object $row): float => (float) $row->sks_mata_kuliah);
            $maxCredits = $this->maximumCredits($student, $calendar, $user->role);
            if ($creditSum > $maxCredits) {
                throw ValidationException::withMessages([
                    'kelas' => "Jumlah SKS paket ({$creditSum}) melebihi batas {$maxCredits} SKS.",
                ]);
            }

            $oldRows = $connection->table('s1m4k_krs')
                ->where('id_mahasiswa', $student->id_mahasiswa)
                ->where('id_kalender', $calendar->id_kalender)
                ->get();
            $oldApproval = $connection->table('s1m4k_pengesahankrs')
                ->where('id_mahasiswa', $student->id_mahasiswa)
                ->where('id_kalender', $calendar->id_kalender)
                ->first();
            $connection->table('s1m4k_krs')
                ->where('id_mahasiswa', $student->id_mahasiswa)
                ->where('id_kalender', $calendar->id_kalender)
                ->delete();
            $connection->table('s1m4k_pengesahankrs')
                ->where('id_mahasiswa', $student->id_mahasiswa)
                ->where('id_kalender', $calendar->id_kalender)
                ->delete();

            $newRows = [];
            foreach ($offerings as $offering) {
                $priorAttempts = $connection->table('s1m4k_krs')
                    ->where('id_mahasiswa', $student->id_mahasiswa)
                    ->where('id_matkul', $offering->id_matkul)
                    ->count();
                $newRows[] = [
                    'id_krs' => (string) Str::uuid(),
                    'id_perguruan_tinggi' => $offering->id_perguruan_tinggi,
                    'id_fakultas' => $offering->id_fakultas,
                    'id_prodi' => $offering->id_prodi,
                    'id_kalender' => $offering->id_kalender,
                    'tahun' => $calendar->tahun,
                    'id_mahasiswa' => $student->id_mahasiswa,
                    'id_jadwal' => $offering->id_jadwal,
                    'id_matkul' => $offering->id_matkul,
                    'status_krs' => $priorAttempts <= 1 ? 'B' : 'U',
                    'tgl_update' => now()->toDateString(),
                    'nilai_huruf' => null,
                    'krs_reguler' => 'Y',
                    'krs_mbkm' => 'N',
                    'isi_edom' => 'N',
                    'user' => (string) $user->legacy_id,
                ];
            }
            $connection->table('s1m4k_krs')->insert($newRows);
            $activityLogger->record(
                $user,
                'KRS Mahasiswa',
                'Program KRS Paket',
                [
                    'kelas_paket' => $class,
                    'semester_maksimum' => $semester,
                    'sks' => $creditSum,
                    'data_lama' => $oldRows->map(fn (object $row): array => (array) $row)->all(),
                    'data_baru' => $newRows,
                    'pengesahan_sebelumnya' => $oldApproval === null ? null : (array) $oldApproval,
                ],
                (string) $student->id_mahasiswa,
                $this->studentIdentity($student)
            );
        });
        if ($user->role === 'mahasiswa') {
            $message = sprintf(
                '%s (%s) telah mengisi KRS paket kelas %s untuk %s.',
                (string) $student->nama_mahasiswa,
                (string) $student->nim,
                $class,
                (string) ($calendar->namatahun ?? $calendar->tahun),
            );
            $url = route('krs.edit', ['student' => $student->id_mahasiswa]);
            if (filled($student->dosen_pa)) {
                $notifications->send('dosen', (string) $student->dosen_pa, 'krs-submitted', 'Pengisian KRS Mahasiswa', $message, $url);
            }
            $notifications->sendProgram((string) $student->id_prodi, 'krs-submitted', 'Pengisian KRS Mahasiswa', $message, $url);
        }

        $redirectParameters = $user->role === 'mahasiswa' ? [] : ['student' => $student->id_mahasiswa];

        return redirect()->route($user->role === 'mahasiswa' ? 'krs.index' : 'krs.edit', $redirectParameters)
            ->with('status', 'KRS paket berhasil diprogramkan.');
    }

    public function approvePa(
        Request $request,
        string $studentId,
        ActivityLogger $activityLogger,
        SimakNotificationService $notifications
    ): RedirectResponse {
        $user = $request->user();
        abort_unless(in_array($user->role, ['userprodi', 'dosen'], true), 403);
        $student = $this->findStudentById($user, $studentId);
        abort_unless($this->canManageStudent($user, $student), 403);
        $calendar = $this->activeCalendar($student);
        abort_if($calendar === null, 404);

        DB::connection('simak_main')->transaction(function () use ($user, $student, $calendar, $activityLogger): void {
            $this->assertKrsExists($student, $calendar);
            $approvalQuery = DB::connection('simak_main')->table('s1m4k_pengesahankrs')
                ->where('id_mahasiswa', $student->id_mahasiswa)
                ->where('id_kalender', $calendar->id_kalender);
            $before = (clone $approvalQuery)->lockForUpdate()->first();
            abort_if(filled($before?->yang_mengesahkan), 409, 'Pengesahan PA sudah tercatat.');

            if ($before === null) {
                $this->insertPaApproval(DB::connection('simak_main'), $student, $calendar, $user);
            } else {
                $approvalQuery->update([
                    'yang_mengesahkan' => (string) $user->name,
                    'tgl_pengesahan' => now()->toDateString(),
                ]);
            }
            $after = DB::connection('simak_main')->table('s1m4k_pengesahankrs')
                ->where('id_mahasiswa', $student->id_mahasiswa)
                ->where('id_kalender', $calendar->id_kalender)
                ->first();
            $activityLogger->record(
                $user,
                'KRS Mahasiswa',
                'Sahkan KRS oleh PA',
                ['pengesahan_sebelum' => $before === null ? null : (array) $before, 'pengesahan_sesudah' => (array) $after],
                (string) $student->id_mahasiswa,
                $this->studentIdentity($student)
            );
        });
        $notifications->send(
            'mahasiswa',
            (string) $student->id_mahasiswa,
            'krs-approved-pa',
            'Pengesahan KRS oleh Dosen PA',
            'KRS Anda untuk '.(string) ($calendar->namatahun ?? $calendar->tahun).' telah disahkan oleh dosen PA.',
            route('krs.index'),
        );

        return back()->with('status', 'KRS berhasil disahkan oleh dosen PA.');
    }

    public function approveProgram(
        Request $request,
        string $studentId,
        ActivityLogger $activityLogger,
        SimakNotificationService $notifications
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user->role === 'userprodi', 403);
        $student = $this->findStudentById($user, $studentId);
        abort_unless($this->canManageStudent($user, $student), 403);
        $calendar = $this->activeCalendar($student);
        abort_if($calendar === null, 404);

        DB::connection('simak_main')->transaction(function () use ($user, $student, $calendar, $activityLogger): void {
            $this->assertKrsExists($student, $calendar);
            $approvalQuery = DB::connection('simak_main')->table('s1m4k_pengesahankrs')
                ->where('id_mahasiswa', $student->id_mahasiswa)
                ->where('id_kalender', $calendar->id_kalender);
            $before = $approvalQuery->first();
            abort_unless(filled($before?->yang_mengesahkan), 409, 'KRS harus disahkan PA terlebih dahulu.');
            abort_if(filled($before?->nama_kpro), 409, 'KRS sudah disahkan program studi.');
            $approvalQuery->update([
                'tgl_pengesahan_kpro' => now()->toDateString(),
                'nama_kpro' => (string) $user->name,
            ]);
            $after = DB::connection('simak_main')->table('s1m4k_pengesahankrs')
                ->where('id_mahasiswa', $student->id_mahasiswa)
                ->where('id_kalender', $calendar->id_kalender)
                ->first();
            $activityLogger->record(
                $user,
                'KRS Mahasiswa',
                'Sahkan KRS oleh program studi',
                ['pengesahan_sebelum' => (array) $before, 'pengesahan_sesudah' => (array) $after],
                (string) $student->id_mahasiswa,
                $this->studentIdentity($student)
            );
        });
        $notifications->send(
            'mahasiswa',
            (string) $student->id_mahasiswa,
            'krs-approved-program',
            'Pengesahan KRS oleh Program Studi',
            'KRS Anda untuk '.(string) ($calendar->namatahun ?? $calendar->tahun).' telah disahkan oleh program studi.',
            route('krs.index'),
        );

        return back()->with('status', 'KRS berhasil disahkan oleh program studi.');
    }

    public function resetApproval(Request $request, string $studentId, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->role === 'userprodi', 403);
        $student = $this->findStudentById($user, $studentId);
        abort_unless($this->canManageStudent($user, $student), 403);
        $calendar = $this->activeCalendar($student);
        abort_if($calendar === null, 404);
        DB::connection('simak_main')->transaction(function () use ($user, $student, $calendar, $activityLogger): void {
            $approvalQuery = DB::connection('simak_main')->table('s1m4k_pengesahankrs')
                ->where('id_mahasiswa', $student->id_mahasiswa)
                ->where('id_kalender', $calendar->id_kalender);
            $approval = (clone $approvalQuery)->lockForUpdate()->first();

            if (filled($approval?->yang_mengesahkan)) {
                $approvalQuery->update([
                    'yang_mengesahkan' => null,
                    'tgl_pengesahan' => null,
                ]);
                $after = DB::connection('simak_main')->table('s1m4k_pengesahankrs')
                    ->where('id_mahasiswa', $student->id_mahasiswa)
                    ->where('id_kalender', $calendar->id_kalender)
                    ->first();
                $activityLogger->record(
                    $user,
                    'KRS Mahasiswa',
                    'Reset pengesahan dosen PA',
                    [
                        'pengesahan_pa_sebelum' => [
                            'yang_mengesahkan' => $approval->yang_mengesahkan,
                            'tgl_pengesahan' => $approval->tgl_pengesahan,
                        ],
                        'pengesahan_pa_sesudah' => [
                            'yang_mengesahkan' => $after->yang_mengesahkan,
                            'tgl_pengesahan' => $after->tgl_pengesahan,
                        ],
                        'pengesahan_prodi_dipertahankan' => [
                            'nama_kpro' => $after->nama_kpro,
                            'tgl_pengesahan_kpro' => $after->tgl_pengesahan_kpro,
                        ],
                    ],
                    (string) $student->id_mahasiswa,
                    $this->studentIdentity($student)
                );
            }
        });

        return back()->with('status', 'Pengesahan dosen PA berhasil direset. Data KRS dan pengesahan program studi tidak diubah.');
    }

    public function approveAllProgram(
        Request $request,
        ActivityLogger $activityLogger,
        SimakNotificationService $notifications
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user->role === 'userprodi', 403);
        $calendarId = $request->validate([
            'id_kalender' => ['required', 'string', 'max:50'],
        ])['id_kalender'];
        $programId = (string) data_get($user, 'id_prodi');
        $calendar = DB::connection('simak_main')->table('s1m4k_kalender')
            ->where('id_kalender', $calendarId)
            ->where('id_prodi', $programId)
            ->where('status', 'Y')
            ->first();
        abort_if($calendar === null, 404);

        $approvedStudents = DB::connection('simak_main')->transaction(function () use ($user, $calendarId, $programId, $activityLogger): array {
            $approvals = DB::connection('simak_main')->table('s1m4k_pengesahankrs as approval')
                ->join('s1m4k_mastermhs as student', 'approval.id_mahasiswa', '=', 'student.id_mahasiswa')
                ->where('approval.id_kalender', $calendarId)
                ->where('student.id_prodi', $programId)
                ->whereNotNull('approval.yang_mengesahkan')
                ->where('approval.yang_mengesahkan', '<>', '')
                ->where(function ($query): void {
                    $query->whereNull('approval.nama_kpro')->orWhere('approval.nama_kpro', '');
                })
                ->select(['student.id_mahasiswa', 'student.nim', 'student.nama_mahasiswa', 'student.id_prodi', 'approval.id_pengesahan'])
                ->get();
            $approvedStudents = [];

            foreach ($approvals as $item) {
                if (! DB::connection('simak_main')->table('s1m4k_krs')
                    ->where('id_mahasiswa', $item->id_mahasiswa)
                    ->where('id_kalender', $calendarId)
                    ->exists()) {
                    continue;
                }
                DB::connection('simak_main')->table('s1m4k_pengesahankrs')
                    ->where('id_pengesahan', $item->id_pengesahan)
                    ->update([
                        'tgl_pengesahan_kpro' => now()->toDateString(),
                        'nama_kpro' => (string) $user->name,
                    ]);
                $activityLogger->record(
                    $user,
                    'KRS Mahasiswa',
                    'Sahkan KRS massal oleh program studi',
                    [
                        'tgl_pengesahan_kpro' => ['sebelum' => null, 'sesudah' => now()->toDateString()],
                        'nama_kpro' => ['sebelum' => null, 'sesudah' => (string) $user->name],
                    ],
                    (string) $item->id_mahasiswa,
                    $this->studentIdentity($item)
                );
                $approvedStudents[] = (string) $item->id_mahasiswa;
            }

            return $approvedStudents;
        });
        foreach ($approvedStudents as $approvedStudentId) {
            $notifications->send(
                'mahasiswa',
                $approvedStudentId,
                'krs-approved-program',
                'Pengesahan KRS oleh Program Studi',
                'KRS Anda untuk '.(string) ($calendar->namatahun ?? $calendar->tahun).' telah disahkan oleh program studi.',
                route('krs.index'),
            );
        }

        return back()->with('status', count($approvedStudents).' KRS berhasil disahkan oleh program studi.');
    }

    private function studentListQuery(object $user, ?string $search)
    {
        $query = DB::connection('simak_main')->table('s1m4k_mastermhs as student')
            ->leftJoin('s1m4k_masterprodi as program', 'student.id_prodi', '=', 'program.id_prodi')
            ->leftJoin('s1m4k_masterdosen as pa', 'student.dosen_pa', '=', 'pa.id_dosen')
            ->whereRaw("TRIM(COALESCE(student.id_mahasiswa, '')) <> ''")
            ->select([
                'student.id_mahasiswa', 'student.nim', 'student.nama_mahasiswa',
                'student.id_prodi', 'student.dosen_pa', 'program.id_fakultas', 'program.nama_program_studi',
                'pa.nama_dosen as nama_dosen_pa', 'pa.gelar_depan as pa_gelar_depan',
                'pa.gelar_belakang as pa_gelar_belakang',
            ]);

        if ($user->role === 'mahasiswa') {
            $query->where('student.id_mahasiswa', $user->legacy_id);
        } elseif (in_array($user->role, ['userprodi', 'usertu'], true)) {
            $query->where('student.id_prodi', $this->requiredProgramId($user));
        } elseif ($user->role === 'pimfak') {
            $facultyId = data_get($user, 'id_fakultas');
            if (blank($facultyId)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('program.id_fakultas', $facultyId);
            }
        } elseif ($user->role === 'dosen') {
            $lecturerId = DB::connection('simak_main')->table('s1m4k_masterdosen')
                ->where('nidn', $user->login)
                ->value('id_dosen');
            if ($lecturerId === null) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('student.dosen_pa', $lecturerId)
                    ->whereIn('student.id_status_mahasiswa', self::ACTIVE_STUDENT_STATUSES);
            }
        }

        if (filled($search)) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('student.nim', 'like', '%'.trim($search).'%')
                    ->orWhere('student.nama_mahasiswa', 'like', '%'.trim($search).'%');
            });
        }

        return $query;
    }

    private function decorateStudents(Collection $students): Collection
    {
        $calendarIds = DB::connection('simak_main')->table('s1m4k_kalender')
            ->whereIn('id_prodi', $students->pluck('id_prodi')->filter()->unique())
            ->where('status', 'Y')
            ->orderByDesc('tahun')
            ->get(['id_prodi', 'id_kalender'])
            ->unique('id_prodi')
            ->keyBy('id_prodi');

        foreach ($students as $student) {
            $calendarId = $calendarIds->get($student->id_prodi)?->id_kalender;
            $student->active_calendar_id = $calendarId;
            $student->is_registered = $calendarId !== null && DB::connection('simak_main')->table('s1m4k_registrasiulang')
                ->where('id_mahasiswa', $student->id_mahasiswa)
                ->where('id_kalender', $calendarId)
                ->exists();
            $student->has_krs = $calendarId !== null && DB::connection('simak_main')->table('s1m4k_krs')
                ->where('id_mahasiswa', $student->id_mahasiswa)
                ->where('id_kalender', $calendarId)
                ->exists();
            $student->pa_approved = $calendarId !== null && DB::connection('simak_main')->table('s1m4k_pengesahankrs')
                ->where('id_mahasiswa', $student->id_mahasiswa)
                ->where('id_kalender', $calendarId)
                ->whereNotNull('yang_mengesahkan')
                ->where('yang_mengesahkan', '<>', '')
                ->exists();
        }

        return $students;
    }

    private function pdfPair(\FPDF $pdf, callable $text, string $leftLabel, string $leftValue, string $rightLabel, string $rightValue): void
    {
        $pdf->Cell(4, 0.5, $text($leftLabel), 0, 0, 'L');
        $pdf->Cell(0.3, 0.5, ':', 0, 0, 'C');
        $pdf->Cell(7, 0.5, $text($leftValue), 0, 0, 'L');
        $pdf->Cell(2, 0.5, '', 0, 0, 'C');
        $pdf->Cell(4, 0.5, $text($rightLabel), 0, 0, 'L');
        $pdf->Cell(0.3, 0.5, ':', 0, 0, 'C');
        $pdf->Cell(3, 0.5, $text($rightValue), 0, 1, 'L');
    }

    private function findStudent(object $user, string $nim): ?object
    {
        $query = $this->studentListQuery($user, null)->where('student.nim', $nim);

        return $query->first();
    }

    private function findStudentById(object $user, string $studentId): object
    {
        $student = $this->studentListQuery($user, null)
            ->where('student.id_mahasiswa', $studentId)
            ->first();
        abort_if($student === null, 404);

        return $student;
    }

    private function canManageStudent(object $user, object $student): bool
    {
        $lecturerId = $user->role === 'dosen'
            ? DB::connection('simak_main')->table('s1m4k_masterdosen')
                ->where('nidn', $user->login)
                ->value('id_dosen')
            : null;

        return match ($user->role) {
            'admin', 'pimuniv', 'validasi', 'wisuda', 'regulang' => true,
            'pimfak' => filled(data_get($user, 'id_fakultas'))
                && (string) $student->id_fakultas === (string) data_get($user, 'id_fakultas'),
            'userprodi', 'usertu' => (string) $student->id_prodi === $this->requiredProgramId($user),
            'dosen' => $lecturerId !== null && (string) $student->dosen_pa === (string) $lecturerId,
            'mahasiswa' => (string) $student->id_mahasiswa === (string) $user->legacy_id,
            default => false,
        };
    }

    private function requiredProgramId(object $user): string
    {
        $programId = trim((string) data_get($user, 'id_prodi'));
        abort_unless($programId !== '', 403, 'Akun belum memiliki cakupan program studi.');

        return $programId;
    }

    private function activeCalendar(object $student): ?object
    {
        return AcademicCalendarScope::excludeShortSemesters(
            DB::connection('simak_main')->table('s1m4k_kalender')
        )
            ->where('id_prodi', $student->id_prodi)
            ->where('status', 'Y')
            ->orderByDesc('tahun')
            ->first();
    }

    private function availableCalendars(object $student, ?object $activeCalendar): Collection
    {
        return AcademicCalendarScope::excludeShortSemesters(
            DB::connection('simak_main')->table('s1m4k_kalender as calendar'),
            'calendar.tahun'
        )
            ->where('calendar.id_prodi', $student->id_prodi)
            ->where(function ($query) use ($student, $activeCalendar): void {
                if ($activeCalendar !== null) {
                    $query->where('calendar.id_kalender', $activeCalendar->id_kalender);
                }
                $query->orWhereExists(function ($subquery) use ($student): void {
                    $subquery->selectRaw('1')
                        ->from('s1m4k_krs as krs')
                        ->whereColumn('krs.id_kalender', 'calendar.id_kalender')
                        ->where('krs.id_mahasiswa', $student->id_mahasiswa);
                });
            })
            ->orderByDesc('calendar.tahun')
            ->get(['calendar.*']);
    }

    private function currentKrs(string $studentId, string $calendarId)
    {
        return DB::connection('simak_main')->table('s1m4k_krs as krs')
            ->leftJoin('s1m4k_mastermk as course', 'krs.id_matkul', '=', 'course.id_matkul')
            ->leftJoin('s1m4k_jadwal as schedule', 'krs.id_jadwal', '=', 'schedule.id_jadwal')
            ->where('krs.id_mahasiswa', $studentId)
            ->where('krs.id_kalender', $calendarId)
            ->orderBy('course.semester')
            ->orderBy('course.nama_mata_kuliah')
            ->select([
                'krs.*', 'course.kode_mata_kuliah', 'course.nama_mata_kuliah',
                'course.semester', 'course.sks_mata_kuliah', 'schedule.nama_kelas',
                'schedule.hari', 'schedule.jammulai', 'schedule.jamselesai',
            ])->get();
    }

    private function offeredSchedules(object $student, object $calendar)
    {
        $offerings = DB::connection('simak_main')->table('s1m4k_jadwal as schedule')
            ->join('s1m4k_mastermk as course', 'schedule.id_matkul', '=', 'course.id_matkul')
            ->leftJoin('s1m4k_masterruang as room', 'schedule.id_ruang', '=', 'room.id_ruang')
            ->where('schedule.id_prodi', $student->id_prodi)
            ->where('schedule.id_kalender', $calendar->id_kalender)
            ->select([
                'schedule.id_jadwal', 'schedule.id_matkul', 'schedule.nama_kelas',
                'schedule.hari', 'schedule.jammulai', 'schedule.jamselesai',
                'course.kode_mata_kuliah', 'course.nama_mata_kuliah', 'room.kapasitas',
                'course.semester', 'course.sks_mata_kuliah', 'room.nama_ruang',
            ])
            ->orderBy('course.semester')
            ->orderBy('course.nama_mata_kuliah')
            ->get();

        $counts = DB::connection('simak_main')->table('s1m4k_krs')
            ->whereIn('id_jadwal', $offerings->pluck('id_jadwal'))
            ->select('id_jadwal')
            ->selectRaw('COUNT(*) as enrolled_count')
            ->groupBy('id_jadwal')
            ->pluck('enrolled_count', 'id_jadwal');
        foreach ($offerings as $offering) {
            $offering->enrolled_count = (int) $counts->get($offering->id_jadwal, 0);
            $offering->is_full = $offering->enrolled_count >= (int) ($offering->kapasitas ?? 0);
        }

        return $offerings;
    }

    private function assertScheduleCapacity($connection, Collection $offerings, Collection $existingScheduleIds, string $errorKey = 'schedule_ids'): void
    {
        $scheduleIds = $offerings->pluck('id_jadwal');
        $connection->table('s1m4k_jadwal')
            ->whereIn('id_jadwal', $scheduleIds)
            ->orderBy('id_jadwal')
            ->lockForUpdate()
            ->get(['id_jadwal']);
        $counts = $connection->table('s1m4k_krs')
            ->whereIn('id_jadwal', $scheduleIds)
            ->select('id_jadwal')
            ->selectRaw('COUNT(*) as enrolled_count')
            ->groupBy('id_jadwal')
            ->pluck('enrolled_count', 'id_jadwal');

        foreach ($offerings as $offering) {
            $enrolled = (int) $counts->get($offering->id_jadwal, 0);
            if ($enrolled >= (int) ($offering->kapasitas ?? 0)
                && ! $existingScheduleIds->contains($offering->id_jadwal)) {
                throw ValidationException::withMessages([
                    $errorKey => "Kelas {$offering->nama_kelas} untuk {$offering->nama_mata_kuliah} sudah penuh. Pilih kelas lain.",
                ]);
            }
        }
    }

    private function maximumCredits(object $student, object $calendar, ?string $role = null): int
    {
        if ((string) $student->id_prodi === (string) config('simak.professional_program_id')) {
            return (int) config('simak.krs.professional_max_credits', 50);
        }

        if (in_array($role, ['userprodi', 'usertu'], true)) {
            return (int) config('simak.krs.max_credits_high_ips', 24);
        }

        $lastActivity = $this->lastActiveTerm($student, $calendar);
        if ($lastActivity === null) {
            return (int) config('simak.krs.max_credits_new_student', 24);
        }

        $ips = $this->ipsForLastActiveTerm($student, $calendar, $lastActivity);

        return match (true) {
            $ips < 2.0 => (int) config('simak.krs.max_credits_low_ips', 14),
            $ips < 3.0 => (int) config('simak.krs.max_credits_mid_ips', 19),
            default => (int) config('simak.krs.max_credits_high_ips', 24),
        };
    }

    private function lastActiveTerm(object $student, object $calendar): ?object
    {
        $query = DB::connection('simak_main')->table('s1m4k_krs as krs')
            ->join('s1m4k_kalender as term', 'krs.id_kalender', '=', 'term.id_kalender');

        return AcademicCalendarScope::excludeShortSemesters($query, 'term.tahun')
            ->where('krs.id_mahasiswa', $student->id_mahasiswa)
            ->where('term.id_prodi', $student->id_prodi)
            ->where('term.tahun', '<', $calendar->tahun)
            ->orderByDesc('term.tahun')
            ->first(['term.id_kalender', 'term.tahun', 'term.namatahun']);
    }

    private function ipsForLastActiveTerm(object $student, object $calendar, ?object $term = null): float
    {
        $term ??= $this->lastActiveTerm($student, $calendar);
        if ($term === null) {
            return 0.0;
        }

        $query = DB::connection('simak_main')->table('s1m4k_krs as krs')
            ->join('s1m4k_mastermk as course', 'krs.id_matkul', '=', 'course.id_matkul')
            ->join('s1m4k_kalender as term', 'krs.id_kalender', '=', 'term.id_kalender');

        $courses = AcademicCalendarScope::excludeShortSemesters($query, 'term.tahun')
            ->where('krs.id_mahasiswa', $student->id_mahasiswa)
            ->where('krs.id_kalender', $term->id_kalender)
            ->where('krs.krs_reguler', 'Y')
            ->select(['krs.nilai_bobot', 'course.sks_mata_kuliah'])
            ->get();
        $credits = (float) $courses->sum(fn (object $row): float => (float) $row->sks_mata_kuliah);

        return $credits > 0
            ? (float) $courses->sum(fn (object $row): float => (float) $row->nilai_bobot * (float) $row->sks_mata_kuliah) / $credits
            : 0.0;
    }

    private function krsWindowOpen(object $calendar): bool
    {
        $today = now()->toDateString();

        return (! filled($calendar->tgl_mulai_krs) || $today >= $calendar->tgl_mulai_krs)
            && (! filled($calendar->tgl_akhir_krs) || $today <= $calendar->tgl_akhir_krs);
    }

    private function assertKrsCanBeChanged(object $user, object $student, object $calendar): void
    {
        abort_unless($this->canManageStudent($user, $student), 403);
        $registered = DB::connection('simak_main')->table('s1m4k_registrasiulang')
            ->where('id_mahasiswa', $student->id_mahasiswa)
            ->where('id_kalender', $calendar->id_kalender)
            ->exists();
        if (! $registered) {
            throw ValidationException::withMessages([
                'registration' => 'Pengisian KRS ditolak. Mahasiswa belum melakukan registrasi ulang pada kalender aktif.',
            ]);
        }
        if ($user->role === 'mahasiswa') {
            abort_unless($this->krsWindowOpen($calendar), 403, 'Masa pengisian KRS belum dibuka atau sudah berakhir.');
            abort_unless(! DB::connection('simak_main')->table('s1m4k_pengesahankrs')
                ->where('id_mahasiswa', $student->id_mahasiswa)
                ->where('id_kalender', $calendar->id_kalender)
                ->exists(), 403, 'KRS yang sudah disahkan belum dapat diubah.');
        }
        if ($user->role === 'dosen') {
            $programApproval = DB::connection('simak_main')->table('s1m4k_pengesahankrs')
                ->where('id_mahasiswa', $student->id_mahasiswa)
                ->where('id_kalender', $calendar->id_kalender)
                ->lockForUpdate()
                ->first(['nama_kpro']);
            abort_unless(blank($programApproval?->nama_kpro), 403, 'KRS yang sudah disahkan program studi tidak dapat diubah oleh dosen PA.');
        }
    }

    private function assertKrsExists(object $student, object $calendar): void
    {
        abort_unless(DB::connection('simak_main')->table('s1m4k_krs')
            ->where('id_mahasiswa', $student->id_mahasiswa)
            ->where('id_kalender', $calendar->id_kalender)
            ->exists(), 422, 'Mahasiswa belum memprogramkan mata kuliah.');
    }

    private function insertPaApproval($connection, object $student, object $calendar, object $user): void
    {
        $connection->table('s1m4k_pengesahankrs')->insert([
            'id_pengesahan' => (string) Str::uuid(),
            'id_mahasiswa' => $student->id_mahasiswa,
            'id_kalender' => $calendar->id_kalender,
            'tgl_pengesahan' => now()->toDateString(),
            'yang_mengesahkan' => (string) $user->name,
        ]);
    }

    private function studentIdentity(object $student): array
    {
        return [
            'jenis' => 'Mahasiswa',
            'nama' => (string) $student->nama_mahasiswa,
            'nomor_identitas' => (string) $student->nim,
            'program_studi' => (string) ($student->nama_program_studi ?? ''),
        ];
    }

    private function canProgramPackage(object $user, object $student): bool
    {
        if ($user->role === 'dosen') {
            return true;
        }

        if ($user->role !== 'mahasiswa' || (string) $student->id_mahasiswa !== (string) $user->legacy_id) {
            return false;
        }

        return DB::connection('simak_main')->table('s1m4k_krs')
            ->where('id_mahasiswa', $student->id_mahasiswa)
            ->distinct()
            ->count('id_kalender') <= 2;
    }

    private function packageClasses(object $student, object $calendar, int $semester)
    {
        $schedules = DB::connection('simak_main')->table('s1m4k_jadwal as schedule')
            ->join('s1m4k_mastermk as course', 'schedule.id_matkul', '=', 'course.id_matkul')
            ->leftJoin('s1m4k_masterruang as room', 'schedule.id_ruang', '=', 'room.id_ruang')
            ->where('schedule.id_prodi', $student->id_prodi)
            ->where('schedule.id_kalender', $calendar->id_kalender)
            ->where('course.semester', '<=', $semester)
            ->whereNotNull('schedule.nama_kelas')
            ->where('schedule.nama_kelas', '<>', '')
            ->get([
                'schedule.id_jadwal',
                'schedule.nama_kelas',
                'room.kapasitas',
            ]);
        $enrolledCounts = DB::connection('simak_main')->table('s1m4k_krs')
            ->whereIn('id_jadwal', $schedules->pluck('id_jadwal'))
            ->select('id_jadwal')
            ->selectRaw('COUNT(*) as enrolled_count')
            ->groupBy('id_jadwal')
            ->pluck('enrolled_count', 'id_jadwal');
        $currentStudentScheduleIds = DB::connection('simak_main')->table('s1m4k_krs')
            ->where('id_mahasiswa', $student->id_mahasiswa)
            ->where('id_kalender', $calendar->id_kalender)
            ->pluck('id_jadwal');

        return $schedules
            ->groupBy('nama_kelas')
            ->map(function (Collection $classSchedules, string $className) use ($enrolledCounts, $currentStudentScheduleIds): object {
                $isFull = $classSchedules->contains(function (object $schedule) use ($enrolledCounts, $currentStudentScheduleIds): bool {
                    return (int) $enrolledCounts->get($schedule->id_jadwal, 0) >= (int) ($schedule->kapasitas ?? 0)
                        && ! $currentStudentScheduleIds->contains($schedule->id_jadwal);
                });

                return (object) ['nama_kelas' => $className, 'is_full' => $isFull];
            })
            ->sortBy('nama_kelas')
            ->values();
    }
}
