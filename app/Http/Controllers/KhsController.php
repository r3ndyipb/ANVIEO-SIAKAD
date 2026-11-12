<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Services\InstitutionSettings;
use App\Services\QrCodeService;
use App\Services\SimakNotificationService;
use App\Support\AcademicCalendarScope;
use App\Support\LecturerName;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class KhsController extends Controller
{
    private const ROLES = ['admin', 'regulang', 'pimuniv', 'pimfak', 'userprodi', 'usertu', 'dosen', 'mahasiswa', 'validasi', 'wisuda'];

    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $this->authorizeRole($user);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'npm' => ['nullable', 'string', 'max:24'],
            'student' => ['nullable', 'string', 'max:100'],
            'calendar' => ['nullable', 'string', 'max:100'],
            'kkn_year' => [$user->role === 'validasi' ? 'required' : 'nullable', 'regex:/^\d{5}$/'],
        ]);
        $kknYear = $user->role === 'validasi' ? $filters['kkn_year'] : null;

        $student = null;
        $students = collect();
        $calendars = collect();
        $selectedCalendar = null;
        $report = null;
        $isStudent = $user->role === 'mahasiswa';

        if ($isStudent) {
            $student = $this->findStudentForUser($user);
            abort_if(
                filled($filters['student'] ?? null)
                    && ! hash_equals((string) $student->id_mahasiswa, (string) $filters['student']),
                404
            );
            $calendars = $this->studentCalendars((string) $student->id_mahasiswa);
            if (filled($filters['calendar'] ?? null)) {
                $selectedCalendar = $calendars->firstWhere('id_kalender', $filters['calendar']);
                abort_if($selectedCalendar === null, 404);
                $report = $this->report($student, $selectedCalendar, true);
            }
        } elseif (in_array($user->role, ['admin', 'userprodi', 'usertu', 'wisuda'], true) && filled($filters['npm'] ?? null)) {
            $student = $this->studentQuery($user)
                ->where('student.nim', trim($filters['npm']))
                ->first();
            if ($student === null) {
                return redirect()->route('khs.index')
                    ->withInput(['npm' => $filters['npm']])
                    ->withErrors(['npm' => 'Data mahasiswa dengan NPM tersebut tidak ditemukan.']);
            }

            $calendars = $this->studentCalendars((string) $student->id_mahasiswa);
            $selectedCalendar = filled($filters['calendar'] ?? null)
                ? $calendars->firstWhere('id_kalender', $filters['calendar'])
                : ($calendars->firstWhere('status', 'Y') ?? $calendars->sortByDesc('tahun')->first());
            abort_if(filled($filters['calendar'] ?? null) && $selectedCalendar === null, 404);
            if ($selectedCalendar !== null) {
                $report = $this->report($student, $selectedCalendar, false);
            }
        } elseif (filled($filters['search'] ?? null)) {
            $search = trim($filters['search']);
            if (in_array($user->role, ['userprodi', 'usertu'], true) && ctype_digit($search)) {
                $student = $this->studentQuery($user, $kknYear)
                    ->where('student.nim', $search)
                    ->first();
                if ($student === null) {
                    return redirect()->route('khs.index')
                        ->withInput(['search' => $search])
                        ->withErrors(['search' => 'Data mahasiswa dengan NPM tersebut tidak ditemukan.']);
                }

                $calendars = $this->studentCalendars((string) $student->id_mahasiswa, $kknYear);
                $selectedCalendar = $calendars->firstWhere('status', 'Y') ?? $calendars->sortByDesc('tahun')->first();
                if ($selectedCalendar !== null) {
                    $report = $this->report($student, $selectedCalendar, false);
                }
            } else {
                $students = $this->studentQuery($user, $kknYear)
                    ->where(function ($query) use ($filters): void {
                        $search = trim($filters['search']);
                        $query->where('student.nim', 'like', '%'.$search.'%')
                            ->orWhere('student.nama_mahasiswa', 'like', '%'.$search.'%');
                    })
                    ->orderBy('student.nama_mahasiswa')
                    ->paginate(25)
                    ->withQueryString();

                if (filled($filters['student'] ?? null)) {
                    $student = $this->findStudent($user, $filters['student'], $kknYear);
                    $calendars = $this->studentCalendars((string) $student->id_mahasiswa, $kknYear);
                    if (filled($filters['calendar'] ?? null)) {
                        $selectedCalendar = $calendars->firstWhere('id_kalender', $filters['calendar']);
                        abort_if($selectedCalendar === null, 404, 'Kalender akademik mahasiswa tidak ditemukan.');
                        $report = $this->report($student, $selectedCalendar, false);
                    }
                }
            }
        }

        return view('khs.index', compact(
            'user',
            'filters',
            'isStudent',
            'student',
            'students',
            'calendars',
            'selectedCalendar',
            'report'
        ));
    }

    public function saveSurvey(
        Request $request,
        string $krsId,
        ActivityLogger $activityLogger,
        SimakNotificationService $notifications
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user->role === 'mahasiswa', 403);
        $student = $this->findStudentForUser($user);
        $course = DB::connection('simak_main')->table('s1m4k_krs as krs')
            ->join('s1m4k_kalender as calendar', 'krs.id_kalender', '=', 'calendar.id_kalender')
            ->leftJoin('s1m4k_mastermk as course', 'krs.id_matkul', '=', 'course.id_matkul')
            ->where('krs.id_krs', $krsId)
            ->where('krs.id_mahasiswa', $student->id_mahasiswa)
            ->select([
                'krs.id_krs', 'krs.id_jadwal', 'krs.id_kalender', 'krs.tahun',
                'calendar.tahun as calendar_year', 'calendar.namatahun', 'calendar.id_prodi',
                'course.nama_mata_kuliah',
            ])->first();
        abort_if($course === null, 404);

        $questions = DB::connection('simak_main')->table('s1m4k_survey_edom')
            ->orderBy('id_survey')
            ->get(['id_survey', 'pertanyaan']);
        abort_if($questions->isEmpty(), 422, 'Instrumen survey EDOM belum tersedia.');

        $validated = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*' => ['required', 'integer', 'between:1,5'],
        ]);
        $answerIds = array_map('strval', array_keys($validated['answers']));
        $questionIds = $questions->pluck('id_survey')->map(fn ($id): string => (string) $id)->all();
        sort($answerIds);
        sort($questionIds);
        if ($answerIds !== $questionIds) {
            throw ValidationException::withMessages([
                'answers' => 'Jawab seluruh pertanyaan survey EDOM yang tersedia.',
            ]);
        }

        $connection = DB::connection('simak_main');
        $connection->transaction(function () use ($connection, $course, $questions, $validated, $user, $student, $activityLogger, $notifications): void {
            $existingAnswers = $connection->table('s1m4k_edom')
                ->where('id_krs', $course->id_krs)
                ->pluck('id_survey')
                ->map(fn ($id): string => (string) $id)
                ->unique()
                ->all();
            $questionIds = $questions->pluck('id_survey')->map(fn ($id): string => (string) $id)->all();
            if (count(array_intersect($existingAnswers, $questionIds)) === $questions->count()) {
                abort(409, 'Survey EDOM untuk mata kuliah ini sudah pernah diisi.');
            }

            $records = [];
            foreach ($questions as $question) {
                if (in_array((string) $question->id_survey, $existingAnswers, true)) {
                    continue;
                }
                $records[] = [
                    'id_edom' => (string) Str::uuid(),
                    'id_krs' => $course->id_krs,
                    'id_jadwal' => $course->id_jadwal,
                    'id_kalender' => $course->id_kalender,
                    'id_survey' => (string) $question->id_survey,
                    'nilai' => (int) $validated['answers'][$question->id_survey],
                ];
            }
            $connection->table('s1m4k_edom')->insert($records);
            $activityLogger->record($user, 'KHS Mahasiswa', 'Mengisi survey EDOM', [
                'jawaban' => collect($records)->map(fn (array $record): array => [
                    'id_survey' => $record['id_survey'],
                    'nilai' => $record['nilai'],
                ])->all(),
            ], (string) $course->id_krs, $this->studentIdentity($student));
            $message = sprintf(
                'Survey EDOM untuk mata kuliah %s (%s) telah diisi. Identitas dan nilai jawaban tidak disertakan.',
                (string) ($course->nama_mata_kuliah ?? 'mata kuliah'),
                (string) ($course->namatahun ?? $course->calendar_year),
            );
            $notifications->sendScheduleLecturers(
                (string) $course->id_jadwal,
                'edom-submitted',
                'Survey EDOM Diisi',
                $message,
                route('survey-edom.results.index'),
            );
            $notifications->sendProgram(
                (string) $course->id_prodi,
                'edom-submitted',
                'Survey EDOM Diisi',
                $message,
                route('survey-edom.results.index'),
            );
        });

        return redirect()->route('khs.index', ['calendar' => request()->input('calendar')])
            ->with('status', 'Survey EDOM berhasil disimpan. Nilai mata kuliah kini dapat dilihat.');
    }

    public function print(Request $request, string $studentId, string $calendarId, QrCodeService $qrCodes): Response
    {
        $user = $request->user();
        $this->authorizeRole($user);
        $student = $this->findStudent($user, $studentId, $user->role === 'validasi' ? (string) $request->query('kkn_year') : null);
        $calendar = DB::connection('simak_main')->table('s1m4k_kalender')
            ->where('id_kalender', $calendarId)
            ->where('id_prodi', $student->id_prodi)
            ->first();
        abort_if($calendar === null, 404);
        $report = $this->report($student, $calendar, $user->role === 'mahasiswa');

        abort_unless(class_exists(\FPDF::class), 500, 'Library cetak KHS tidak tersedia.');
        $validationCode = $this->khsValidationCode($studentId, (string) $calendar->id_kalender);
        $verificationUrl = route('khs.documents.verify', [
            'code' => $validationCode,
            'student' => $studentId,
            'calendar' => $calendar->id_kalender,
        ], true);
        $viceDean = DB::connection('simak_main')->table('s1m4k_masterfakultas as faculty')
            ->leftJoin('s1m4k_masterdosen as lecturer', 'faculty.pd1', '=', 'lecturer.id_dosen')
            ->where('faculty.id_fakultas', $student->id_fakultas)
            ->first([
                'lecturer.gelar_depan', 'lecturer.nama_dosen',
                'lecturer.gelar_belakang', 'lecturer.nip',
            ]);
        $viceDeanName = $this->lecturerFullName($viceDean);
        $viceDeanName = $viceDeanName !== '' ? $viceDeanName : '-';
        $viceDeanNip = trim((string) ($viceDean?->nip ?? ''));

        $pdf = new \FPDF('P', 'cm', 'A4');
        $pdf->AddPage();
        $logoPath = app(InstitutionSettings::class)->logoFilePath();
        if (is_file($logoPath)) {
            $pdf->Image($logoPath, 2, 0.5, 2, 2);
        }
        $text = fn (string $value): string => iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $value) ?: '';
        $pdf->SetFont('Arial', 'B', 10);
        foreach ([
            ...app(InstitutionSettings::class)->ministryNameLines(),
            mb_strtoupper(app(InstitutionSettings::class)->institutionName()),
            'Kartu Hasil Studi (KHS)',
            'PROGRAM STUDI '.mb_strtoupper((string) $student->nama_program_studi),
            (string) $calendar->namatahun,
        ] as $line) {
            $pdf->Cell(19, 0.5, $text($line), 0, 1, 'C');
        }
        $pdf->Line(1, 3.8, 20, 3.8);
        $pdf->Ln();
        $pdf->SetFont('Arial', '', 8);
        $this->pdfPair($pdf, $text, 'NPM', (string) $student->nim, 'Total SKS Lulus', number_format($report['sks_lulus'], 0).' SKS');
        $this->pdfPair($pdf, $text, 'Nama', ucwords(mb_strtolower((string) $student->nama_mahasiswa)), 'SKS Semester Ini', number_format($report['sks_semester'], 0).' SKS');
        $this->pdfPair($pdf, $text, 'Penasehat Akademik', $report['pa_name'], 'Total SKS Diprogram', number_format($report['sks_program'], 0).' SKS');
        $this->pdfPair($pdf, $text, 'Indeks Prestasi Kumulatif (IPK)', number_format($report['ipk'], 2), 'Maksimum SKS Berikut', $report['maximum_credits'].' SKS');
        $this->pdfPair($pdf, $text, 'Tahun Akademik', (string) $calendar->namatahun, 'Fakultas', (string) ($student->nama_fakultas ?? '-'));
        $pdf->Ln();
        $pdf->SetFont('Arial', 'B', 9);
        foreach ([[1, 'No'], [3, 'Kode MK'], [9, 'Mata Kuliah'], [1.5, 'Nilai'], [1.5, 'Bobot'], [1.5, 'SKS'], [1.5, 'Mutu']] as [$width, $label]) {
            $pdf->Cell($width, 0.7, $text($label), 'LRTB', 0, 'C');
        }
        $pdf->Ln();
        $pdf->SetFont('Arial', '', 8);
        foreach ($report['courses'] as $index => $course) {
            $pdf->Cell(1, 0.7, (string) ($index + 1), 'LBTR', 0, 'C');
            $pdf->Cell(3, 0.7, $text((string) $course->kode_mata_kuliah), 'LBTR', 0, 'C');
            $pdf->Cell(9, 0.7, $text(ucwords(mb_strtolower((string) $course->nama_mata_kuliah))), 'LBTR', 0);
            $visible = $user->role !== 'mahasiswa' || $course->edom_complete;
            $pdf->Cell(1.5, 0.7, $text($visible ? (string) ($course->nilai_huruf ?? '-') : 'Hidden'), 'LBTR', 0, 'C');
            $pdf->Cell(1.5, 0.7, $text($visible ? (string) ($course->nilai_bobot ?? '-') : 'Hidden'), 'LBTR', 0, 'C');
            $pdf->Cell(1.5, 0.7, $text($visible ? number_format((float) $course->sks_mata_kuliah, 2) : 'Hidden'), 'LBTR', 0, 'C');
            $pdf->Cell(1.5, 0.7, $text($visible ? number_format((float) $course->mutu, 2) : 'Hidden'), 'LBTR', 0, 'C');
            $pdf->Ln();
        }
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(13, 0.7, '', 0, 0, 'R');
        $pdf->Cell(1.5, 0.7, 'Jumlah', 'LBT', 0, 'L');
        $pdf->Cell(1.5, 0.7, '', 'TBR', 0, 'C');
        $pdf->Cell(1.5, 0.7, number_format($report['sks_semester'], 0).' SKS', 'LBTR', 0, 'C');
        $pdf->Cell(1.5, 0.7, number_format($report['quality_points'], 2), 'LBTR', 0, 'C');
        $pdf->Ln();
        $pdf->Cell(13, 0.7, '', 0, 0, 'R');
        $pdf->Cell(3, 0.7, 'Indeks Prestasi Semester (IPS)', 'LBT', 0, 'L');
        $pdf->Cell(1.5, 0.7, '', 'TBR', 0, 'C');
        $pdf->Cell(1.5, 0.7, number_format($report['ips'], 2), 'LBTR', 1, 'C');
        $pdf->Ln(1.0);
        if ($pdf->GetY() > 24.0) {
            $pdf->AddPage();
        }
        $pdf->SetFont('Times', '', 9);
        $signatureX = 6.5;
        $signatureWidth = 8.0;
        $cityName = app(InstitutionSettings::class)->cityName();
        $pdf->SetX($signatureX);
        $pdf->Cell($signatureWidth, 0.5, $text($cityName.', '.now()->locale('id')->translatedFormat('d F Y')), 0, 1, 'C');
        $pdf->SetX($signatureX);
        $pdf->Cell($signatureWidth, 0.5, $text('Wakil Dekan I Bidang Akademik'), 0, 1, 'C');
        $pdf->Ln(0.15);
        $qrY = $pdf->GetY();
        $qrPngFile = $qrCodes->generate($verificationUrl, 'simak-khs-qr-');
        try {
            $pdf->Image($qrPngFile, $signatureX + (($signatureWidth - 1.8) / 2), $qrY, 1.8, 1.8);
        } finally {
            if (is_file($qrPngFile)) {
                unlink($qrPngFile);
            }
        }
        $pdf->SetY($qrY + 1.85);
        $pdf->SetX($signatureX);
        $pdf->Cell($signatureWidth, 0.45, $text($viceDeanName), 0, 1, 'C');
        $pdf->SetX($signatureX);
        $pdf->Cell($signatureWidth, 0.45, $text('NIP. '.($viceDeanNip !== '' ? $viceDeanNip : '-')), 0, 1, 'C');

        return response($pdf->Output('S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="KHS-'.$student->nim.'-'.$calendar->tahun.'.pdf"',
        ]);
    }

    public function verifyPrint(string $code, string $studentId, string $calendarId): Response
    {
        $documentReference = hash('sha256', $studentId."\0".$calendarId);
        $validation = DB::connection('simak_main')->table('validasi_dok')
            ->where('id', $code)
            ->where('id_dokumen', $documentReference)
            ->where('jenis_dokumen', 'KHS')
            ->first();
        $student = null;
        $calendar = null;

        if ($validation !== null) {
            $student = DB::connection('simak_main')->table('s1m4k_mastermhs as student')
                ->leftJoin('s1m4k_masterprodi as program', 'student.id_prodi', '=', 'program.id_prodi')
                ->leftJoin('s1m4k_masterfakultas as faculty', 'student.id_fakultas', '=', 'faculty.id_fakultas')
                ->leftJoin('s1m4k_masterdosen as lecturer', 'faculty.pd1', '=', 'lecturer.id_dosen')
                ->where('student.id_mahasiswa', $studentId)
                ->first([
                    'student.id_mahasiswa', 'student.id_prodi', 'student.nim',
                    'student.nama_mahasiswa', 'program.nama_program_studi',
                    'lecturer.gelar_depan', 'lecturer.nama_dosen',
                    'lecturer.gelar_belakang', 'lecturer.nip as vice_dean_nip',
                ]);
            if ($student !== null) {
                $student->vice_dean_full_name = $this->lecturerFullName($student);
            }
            if ($student !== null) {
                $calendar = DB::connection('simak_main')->table('s1m4k_kalender')
                    ->where('id_kalender', $calendarId)
                    ->where('id_prodi', $student->id_prodi)
                    ->first(['namatahun', 'tahun']);
            }
        }

        $valid = $validation !== null && $student !== null && $calendar !== null;

        return response()->view('khs.verification', compact('valid', 'student', 'calendar', 'validation'), $valid ? 200 : 404);
    }

    private function khsValidationCode(string $studentId, string $calendarId): string
    {
        $connection = DB::connection('simak_main');
        $documentReference = hash('sha256', $studentId."\0".$calendarId);
        $existing = $connection->table('validasi_dok')
            ->where('id_dokumen', $documentReference)
            ->where('jenis_dokumen', 'KHS')
            ->orderBy('created_at')
            ->first(['id']);
        if ($existing !== null) {
            return (string) $existing->id;
        }

        $code = (string) Str::uuid();
        $connection->table('validasi_dok')->insert([
            'id' => $code,
            'id_dokumen' => $documentReference,
            'jenis_dokumen' => 'KHS',
            'created_at' => now(),
        ]);

        return $code;
    }

    private function lecturerFullName(?object $lecturer): string
    {
        if ($lecturer === null) {
            return '';
        }

        return LecturerName::format(
            filled($lecturer->nama_dosen ?? null) ? ucwords(mb_strtolower((string) $lecturer->nama_dosen)) : null,
            $lecturer->gelar_depan ?? null,
            $lecturer->gelar_belakang ?? null,
        );
    }

    private function report(object $student, object $calendar, bool $hideGrades): array
    {
        $connection = DB::connection('simak_main');
        $questions = $connection->table('s1m4k_survey_edom')
            ->orderBy('id_survey')
            ->get(['id_survey', 'pertanyaan']);
        $courses = $connection->table('s1m4k_krs as krs')
            ->leftJoin('s1m4k_mastermk as course', 'krs.id_matkul', '=', 'course.id_matkul')
            ->leftJoin('s1m4k_jadwal as schedule', 'krs.id_jadwal', '=', 'schedule.id_jadwal')
            ->where('krs.id_mahasiswa', $student->id_mahasiswa)
            ->where('krs.id_kalender', $calendar->id_kalender)
            ->orderBy('course.semester')
            ->orderBy('course.nama_mata_kuliah')
            ->get([
                'krs.id_krs', 'krs.id_matkul', 'krs.id_jadwal', 'krs.id_kalender',
                'krs.nilai_huruf', 'krs.nilai_bobot',
                'course.kode_mata_kuliah', 'course.nama_mata_kuliah', 'course.semester',
                'course.sks_mata_kuliah', 'schedule.nama_kelas',
            ])
            ->groupBy('id_matkul')
            ->map(fn (Collection $attempts): object => $attempts->first())
            ->values();

        foreach ($courses as $course) {
            $answerIds = $connection->table('s1m4k_edom')
                ->where('id_krs', $course->id_krs)
                ->pluck('id_survey')
                ->map(fn ($id): string => (string) $id)
                ->unique();
            $course->edom_complete = $questions->isNotEmpty()
                && $answerIds->count() === $questions->count()
                && $questions->every(fn (object $question): bool => $answerIds->contains((string) $question->id_survey));
            $course->edom_answers = $connection->table('s1m4k_edom')
                ->where('id_krs', $course->id_krs)
                ->pluck('nilai', 'id_survey');
            $course->survey_questions = $questions;
            $course->nilai_bobot = (float) ($course->nilai_bobot ?? 0);
            $course->sks_mata_kuliah = (float) ($course->sks_mata_kuliah ?? 0);
            $course->mutu = $course->nilai_bobot * $course->sks_mata_kuliah;
            $course->show_grade = ! $hideGrades || $course->edom_complete;
        }

        $sksSemester = (float) $courses->sum(fn (object $course): float => $course->sks_mata_kuliah);
        $qualityPoints = (float) $courses->sum(fn (object $course): float => $course->mutu);
        $ips = $sksSemester > 0 ? $qualityPoints / $sksSemester : 0.0;
        $maximumCreditsIps = $this->isShortSemester($calendar)
            ? $this->lastRegularIpsBefore($student, $calendar)
            : $ips;
        $maximumCredits = $this->maximumCreditsForIps($maximumCreditsIps);

        $history = $connection->table('s1m4k_krs as krs')
            ->leftJoin('s1m4k_mastermk as course', 'krs.id_matkul', '=', 'course.id_matkul')
            ->where('krs.id_mahasiswa', $student->id_mahasiswa)
            ->where('krs.tahun', '<=', $calendar->tahun)
            ->orderBy('krs.tahun')
            ->get(['krs.id_matkul', 'krs.nilai_bobot', 'course.sks_mata_kuliah'])
            ->groupBy('id_matkul')
            ->map(fn (Collection $attempts): object => $attempts->sortByDesc(fn (object $item): float => (float) $item->nilai_bobot)->first());
        $sksProgram = (float) $history->sum(fn (object $item): float => (float) ($item->sks_mata_kuliah ?? 0));
        $cumulativeQuality = (float) $history->sum(fn (object $item): float => (float) ($item->nilai_bobot ?? 0) * (float) ($item->sks_mata_kuliah ?? 0));
        $sksPassed = (float) $history->sum(fn (object $item): float => (float) ($item->nilai_bobot ?? 0) * (float) ($item->sks_mata_kuliah ?? 0) > 0
                ? (float) ($item->sks_mata_kuliah ?? 0)
                : 0.0
        );
        $pa = $connection->table('s1m4k_masterdosen')
            ->where('id_dosen', $student->dosen_pa)
            ->first(['gelar_depan', 'nama_dosen', 'gelar_belakang']);
        $paName = LecturerName::format($pa?->nama_dosen, $pa?->gelar_depan, $pa?->gelar_belakang);

        return [
            'courses' => $courses,
            'questions' => $questions,
            'ips' => $ips,
            'ipk' => $sksProgram > 0 ? $cumulativeQuality / $sksProgram : 0,
            'sks_semester' => $sksSemester,
            'sks_program' => $sksProgram,
            'sks_lulus' => $sksPassed,
            'maximum_credits' => $maximumCredits,
            'quality_points' => $qualityPoints,
            'pa_name' => $paName,
        ];
    }

    private function studentCalendars(string $studentId, ?string $kknYear = null): Collection
    {
        return DB::connection('simak_main')->table('s1m4k_krs as krs')
            ->join('s1m4k_kalender as calendar', 'krs.id_kalender', '=', 'calendar.id_kalender')
            ->where('krs.id_mahasiswa', $studentId)
            ->when($kknYear !== null, fn ($query) => $query->whereExists(function ($participant) use ($kknYear): void {
                $participant->selectRaw('1')->from('s1m4k_kubermas as kkn')
                    ->whereColumn('kkn.id_mahasiswa', 'krs.id_mahasiswa')
                    ->where('kkn.tahun', $kknYear);
            }))
            ->select('calendar.id_kalender', 'calendar.tahun', 'calendar.namatahun', 'calendar.status')
            ->distinct()
            ->orderBy('calendar.tahun')
            ->get();
    }

    private function isShortSemester(object $calendar): bool
    {
        $year = trim((string) $calendar->tahun);

        return strlen($year) === 5 && str_ends_with($year, '3');
    }

    private function lastRegularIpsBefore(object $student, object $calendar): float
    {
        $termQuery = DB::connection('simak_main')->table('s1m4k_krs as krs')
            ->join('s1m4k_kalender as term', 'krs.id_kalender', '=', 'term.id_kalender');
        $term = AcademicCalendarScope::excludeShortSemesters($termQuery, 'term.tahun')
            ->where('krs.id_mahasiswa', $student->id_mahasiswa)
            ->where('term.id_prodi', $student->id_prodi)
            ->where('term.tahun', '<', $calendar->tahun)
            ->orderByDesc('term.tahun')
            ->first(['term.id_kalender']);
        if ($term === null) {
            return 0.0;
        }

        $courses = DB::connection('simak_main')->table('s1m4k_krs as krs')
            ->join('s1m4k_mastermk as course', 'krs.id_matkul', '=', 'course.id_matkul')
            ->where('krs.id_mahasiswa', $student->id_mahasiswa)
            ->where('krs.id_kalender', $term->id_kalender)
            ->where('krs.krs_reguler', 'Y')
            ->get(['krs.nilai_bobot', 'course.sks_mata_kuliah']);
        $credits = (float) $courses->sum(fn (object $row): float => (float) $row->sks_mata_kuliah);

        return $credits > 0
            ? (float) $courses->sum(fn (object $row): float => (float) $row->nilai_bobot * (float) $row->sks_mata_kuliah) / $credits
            : 0.0;
    }

    private function maximumCreditsForIps(float $ips): int
    {
        return match (true) {
            $ips >= 3.0 => 24,
            $ips >= 2.0 => 19,
            default => 14,
        };
    }

    private function studentQuery(object $user, ?string $kknYear = null)
    {
        $query = DB::connection('simak_main')->table('s1m4k_mastermhs as student')
            ->leftJoin('s1m4k_masterprodi as program', 'student.id_prodi', '=', 'program.id_prodi')
            ->leftJoin('s1m4k_masterfakultas as faculty', 'student.id_fakultas', '=', 'faculty.id_fakultas')
            ->select([
                'student.id_mahasiswa', 'student.nim', 'student.nama_mahasiswa',
                'student.id_prodi', 'student.id_fakultas', 'student.dosen_pa',
                'program.nama_program_studi', 'program.id_fakultas as program_faculty_id',
                'faculty.nama_fakultas', 'faculty.pd1',
            ])
            ->whereRaw("TRIM(COALESCE(student.id_mahasiswa, '')) <> ''");

        if (in_array($user->role, ['userprodi', 'usertu'], true)) {
            $programId = trim((string) data_get($user, 'id_prodi'));
            abort_unless($programId !== '', 403);
            $query->where('student.id_prodi', $programId);
        } elseif ($user->role === 'pimfak') {
            $facultyId = trim((string) data_get($user, 'id_fakultas'));
            abort_unless($facultyId !== '', 403);
            $query->where('program.id_fakultas', $facultyId);
        } elseif ($user->role === 'dosen') {
            $lecturerId = DB::connection('simak_main')->table('s1m4k_masterdosen')
                ->where('nidn', data_get($user, 'login'))
                ->value('id_dosen');
            abort_if($lecturerId === null, 403);
            $query->where('student.dosen_pa', $lecturerId)
                ->whereIn('student.id_status_mahasiswa', ['A', 'C', 'G', 'N', 'Z', 'M']);
        } elseif ($user->role === 'mahasiswa') {
            $query->where('student.id_mahasiswa', data_get($user, 'legacy_id'));
        } elseif ($user->role === 'validasi') {
            abort_unless($kknYear !== null && preg_match('/^\d{5}$/', $kknYear) === 1, 403);
            $query->whereExists(function ($participant) use ($kknYear): void {
                $participant->selectRaw('1')->from('s1m4k_kubermas as kkn')
                    ->whereColumn('kkn.id_mahasiswa', 'student.id_mahasiswa')
                    ->where('kkn.tahun', $kknYear);
            });
        }

        return $query;
    }

    private function findStudent(object $user, string $studentId, ?string $kknYear = null): object
    {
        $student = $this->studentQuery($user, $kknYear)->where('student.id_mahasiswa', $studentId)->first();
        abort_if($student === null, 404);

        return $student;
    }

    private function findStudentForUser(object $user): object
    {
        $student = $this->studentQuery($user)
            ->where('student.id_mahasiswa', data_get($user, 'legacy_id'))
            ->first();
        abort_if($student === null, 404);

        return $student;
    }

    private function authorizeRole(object $user): void
    {
        abort_unless(in_array($user->role, self::ROLES, true), 403);
    }

    private function studentIdentity(object $student): array
    {
        return [
            'nama' => $student->nama_mahasiswa,
            'nomor_identitas' => $student->nim,
            'program_studi' => $student->nama_program_studi,
        ];
    }

    private function pdfPair(\FPDF $pdf, callable $text, string $leftLabel, string $leftValue, string $rightLabel, string $rightValue): void
    {
        $pdf->Cell(3.7, 0.5, $text($leftLabel), 0, 0, 'L');
        $pdf->Cell(0.3, 0.5, ':', 0, 0, 'C');
        $pdf->Cell(6.5, 0.5, $text($leftValue), 0, 0, 'L');
        $pdf->Cell(4.4, 0.5, $text($rightLabel), 0, 0, 'L');
        $pdf->Cell(0.3, 0.5, ':', 0, 0, 'C');
        $pdf->Cell(4, 0.5, $text($rightValue), 0, 1, 'L');
    }
}
