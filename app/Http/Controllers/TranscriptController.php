<?php

namespace App\Http\Controllers;

use App\Services\InstitutionSettings;
use App\Services\QrCodeService;
use App\Support\LecturerName;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TranscriptController extends Controller
{
    private const ROLES = ['admin', 'userprodi', 'usertu', 'dosen', 'mahasiswa', 'validasi', 'wisuda'];

    private const ACTIVE_STUDENT_STATUSES = ['A', 'C', 'G', 'N', 'Z', 'M'];

    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $this->authorizeRole($user);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'student' => ['nullable', 'string', 'max:100'],
            'kkn_year' => [$user->role === 'validasi' ? 'required' : 'nullable', 'regex:/^\d{5}$/'],
        ]);
        $kknYear = $user->role === 'validasi' ? $filters['kkn_year'] : null;
        $students = collect();
        $student = null;
        $report = null;

        if ($user->role === 'mahasiswa') {
            $student = $this->studentQuery($user)->first();
            abort_if($student === null, 404);
            $report = $this->transcriptReport($student);
        } elseif (in_array($user->role, ['admin', 'userprodi'], true)
            && filled($filters['search'] ?? null)
            && ctype_digit(trim($filters['search']))) {
            $student = $this->studentQuery($user, $kknYear)
                ->where('student.nim', trim($filters['search']))
                ->first();
            if ($student === null) {
                return redirect()->route('transcripts.index')
                    ->withInput(['search' => $filters['search']])
                    ->withErrors(['search' => 'Data mahasiswa dengan NPM tersebut tidak ditemukan.']);
            }
            $report = $this->transcriptReport($student);
        } elseif ($user->role === 'dosen') {
            $students = $this->studentQuery($user, $kknYear)
                ->when(filled($filters['search'] ?? null), fn ($query) => $this->applySearch($query, $filters['search']))
                ->orderBy('student.id_prodi')
                ->orderBy('student.nim')
                ->paginate(25)
                ->withQueryString();

            if (filled($filters['student'] ?? null)) {
                $student = $this->findStudent($user, $filters['student'], $kknYear);
                $report = $this->transcriptReport($student);
            }
        } else {
            if (filled($filters['search'] ?? null)) {
                $students = $this->studentQuery($user, $kknYear)
                    ->where(function ($query) use ($filters): void {
                        $this->applySearch($query, $filters['search']);
                    })
                    ->orderBy('student.nama_mahasiswa')
                    ->paginate(25)
                    ->withQueryString();
            }
            if (filled($filters['student'] ?? null)) {
                $student = $this->findStudent($user, $filters['student'], $kknYear);
                $report = $this->transcriptReport($student);
            }
        }

        return view('transcripts.index', compact('user', 'filters', 'students', 'student', 'report'));
    }

    public function print(Request $request, string $studentId, QrCodeService $qrCodes): Response
    {
        $user = $request->user();
        $this->authorizeRole($user);
        $student = $this->findStudent($user, $studentId, $user->role === 'validasi' ? (string) $request->query('kkn_year') : null);
        $report = $this->transcriptReport($student);
        $pdf = $this->newPdf();
        $text = fn (string $value): string => $this->pdfText($value);
        $logoPath = app(InstitutionSettings::class)->logoFilePath();
        if (is_file($logoPath)) {
            $pdf->Image($logoPath, 2, 0.5, 2, 2);
        }

        $pdf->SetFont('Arial', 'B', 10);
        foreach ([
            ...app(InstitutionSettings::class)->ministryNameLines(),
            mb_strtoupper(app(InstitutionSettings::class)->institutionName()),
            'Transkrip Nilai Sementara',
        ] as $line) {
            $pdf->Cell(19, 0.5, $text($line), 0, 1, 'C');
        }
        $pdf->Line(1, 3, 20, 3);
        $pdf->Ln();
        $pdf->SetFont('Arial', '', 9);
        foreach ([
            ['NPM', $student->nim],
            ['Nama', ucwords(mb_strtolower((string) $student->nama_mahasiswa))],
            ['Program Studi', ucwords(mb_strtolower((string) ($student->nama_program_studi ?? '-')))],
        ] as [$label, $value]) {
            $pdf->Cell(2.5, 0.5, $text($label), 0);
            $pdf->Cell(0.4, 0.5, ':', 0, 0, 'C');
            $pdf->Cell(12, 0.5, $text((string) $value), 0, 1);
        }
        $pdf->Ln();
        $pdf->SetFont('Arial', 'B', 8);
        foreach ([[1, 'No'], [2.5, 'Kode MK'], [7.5, 'Mata Kuliah'], [1.5, 'SMS'], [1.5, 'Nilai'], [1.5, 'Bobot'], [1.5, 'SKS'], [1.5, 'Mutu']] as [$width, $label]) {
            $pdf->Cell($width, 0.7, $text($label), 'LRTB', 0, 'C');
        }
        $pdf->Ln();
        $pdf->SetFont('Arial', '', 8);
        foreach ($report['courses'] as $index => $course) {
            $pdf->Cell(1, 0.7, (string) ($index + 1), 'LBTR', 0, 'C');
            $pdf->Cell(2.5, 0.7, $text((string) ($course->kode_mata_kuliah ?? '-')), 'LBTR', 0, 'C');
            $pdf->Cell(7.5, 0.7, $text(ucwords(mb_strtolower((string) ($course->nama_mata_kuliah ?? '-')))), 'LBTR', 0);
            $pdf->Cell(1.5, 0.7, $text((string) $course->semester), 'LBTR', 0, 'C');
            $pdf->Cell(1.5, 0.7, $text((string) $course->nilai_huruf), 'LBTR', 0, 'C');
            $pdf->Cell(1.5, 0.7, number_format($course->nilai_bobot, 2), 'LBTR', 0, 'C');
            $pdf->Cell(1.5, 0.7, number_format($course->sks_mata_kuliah, 0), 'LBTR', 0, 'C');
            $pdf->Cell(1.5, 0.7, number_format($course->mutu, 2), 'LBTR', 0, 'C');
            $pdf->Ln();
        }
        $pdf->Ln();
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(13, 0.6, $text('Jumlah SKS'), 0, 0, 'R');
        $pdf->Cell(2, 0.6, number_format($report['total_sks'], 0).' SKS', 0, 1, 'R');
        $pdf->Cell(13, 0.6, $text('Indeks Prestasi Kumulatif (IPK)'), 0, 0, 'R');
        $pdf->Cell(2, 0.6, number_format($report['ipk'], 2), 0, 1, 'R');
        $pdf->Ln(0.5);
        $pdf->SetFont('Arial', '', 9);
        $cityName = app(InstitutionSettings::class)->cityName();
        $pdf->Cell(19, 0.5, $text($cityName.', '.now()->format('d/m/Y')), 0, 1, 'R');
        $pdf->Cell(8, 0.5, $text('Dekan'), 0, 0, 'C');
        $pdf->Cell(3, 0.5, '', 0, 0);
        $pdf->Cell(8, 0.5, $text('Ketua Program Studi'), 0, 1, 'C');
        $pdf->Cell(8, 0.5, $text((string) ($student->nama_fakultas ?? '-')), 0, 0, 'C');
        $pdf->Cell(3, 0.5, '', 0, 0);
        $pdf->Cell(8, 0.5, $text((string) ($student->nama_program_studi ?? '-')), 0, 1, 'C');
        $pdf->Ln(0.25);
        $deanName = LecturerName::format(
            $student->dean_name ?? null,
            $student->dean_prefix ?? null,
            $student->dean_suffix ?? null,
        );
        $programHeadName = LecturerName::format(
            $student->program_head_name ?? null,
            $student->program_head_prefix ?? null,
            $student->program_head_suffix ?? null,
        );
        $deanQrFile = null;
        $programHeadQrFile = null;

        try {
            if (filled($student->dean_id)) {
                $deanCode = $this->transcriptValidationCode($studentId, 'TRANSKRIP_DEKAN', (string) $student->dean_id);
                $deanQrFile = $qrCodes->generate(route('transcripts.documents.verify', [
                    'code' => $deanCode,
                    'student' => $studentId,
                    'signatory' => 'dekan',
                ], true), 'simak-transcript-dean-qr-');
            }
            if (filled($student->program_head_id)) {
                $programHeadCode = $this->transcriptValidationCode($studentId, 'TRANSKRIP_KETUA_PRODI', (string) $student->program_head_id);
                $programHeadQrFile = $qrCodes->generate(route('transcripts.documents.verify', [
                    'code' => $programHeadCode,
                    'student' => $studentId,
                    'signatory' => 'ketua-prodi',
                ], true), 'simak-transcript-head-qr-');
            }

            if ($pdf->GetY() + 2.6 > 28.2) {
                $pdf->AddPage();
            }
            $qrY = $pdf->GetY();
            if ($deanQrFile !== null) {
                $pdf->Image($deanQrFile, 4.25, $qrY, 1.5, 1.5);
            }
            if ($programHeadQrFile !== null) {
                $pdf->Image($programHeadQrFile, 15.25, $qrY, 1.5, 1.5);
            }
            $pdf->SetY($qrY + 1.55);
            $pdf->Cell(8, 0.5, $text($deanName), 'B', 0, 'C');
            $pdf->Cell(3, 0.5, '', 0, 0);
            $pdf->Cell(8, 0.5, $text($programHeadName), 'B', 1, 'C');
            $pdf->Cell(8, 0.5, $text('NIP. '.($student->dean_nip ?? '-')), 0, 0, 'C');
            $pdf->Cell(3, 0.5, '', 0, 0);
            $pdf->Cell(8, 0.5, $text('NIP. '.($student->program_head_nip ?? '-')), 0, 1, 'C');

            return response($pdf->Output('S'), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="Transkrip-'.$student->nim.'.pdf"',
            ]);
        } finally {
            foreach ([$deanQrFile, $programHeadQrFile] as $qrFile) {
                if ($qrFile !== null && is_file($qrFile)) {
                    unlink($qrFile);
                }
            }
        }
    }

    public function verifyPrint(string $code, string $studentId, string $signatory): Response
    {
        $documentTypes = [
            'dekan' => ['type' => 'TRANSKRIP_DEKAN', 'id' => 'dean_id', 'name' => 'dean_name', 'prefix' => 'dean_prefix', 'suffix' => 'dean_suffix', 'nip' => 'dean_nip', 'label' => 'Dekan'],
            'ketua-prodi' => ['type' => 'TRANSKRIP_KETUA_PRODI', 'id' => 'program_head_id', 'name' => 'program_head_name', 'prefix' => 'program_head_prefix', 'suffix' => 'program_head_suffix', 'nip' => 'program_head_nip', 'label' => 'Ketua Program Studi'],
        ];
        $document = $documentTypes[$signatory] ?? null;
        $student = $document === null ? null : $this->verificationStudent($studentId);
        $signatoryId = $student !== null ? trim((string) ($student->{$document['id']} ?? '')) : '';
        $documentReference = $signatoryId !== ''
            ? $this->transcriptDocumentReference($studentId, $document['type'], $signatoryId)
            : null;
        $validation = $documentReference === null ? null : DB::connection('simak_main')->table('validasi_dok')
            ->where('id', $code)
            ->where('id_dokumen', $documentReference)
            ->where('jenis_dokumen', $document['type'])
            ->first();
        $signatoryName = $student !== null && $document !== null
            ? LecturerName::format(
                $student->{$document['name']} ?? null,
                $student->{$document['prefix']} ?? null,
                $student->{$document['suffix']} ?? null,
            )
            : '';
        $signatoryNip = $student !== null && $document !== null
            ? trim((string) ($student->{$document['nip']} ?? ''))
            : '';
        $valid = $validation !== null && $student !== null && $signatoryName !== '';

        return response()->view('transcripts.verification', compact(
            'valid', 'student', 'document', 'signatoryName', 'signatoryNip', 'validation'
        ), $valid ? 200 : 404);
    }

    public function curriculum(Request $request, string $studentId): Response
    {
        $user = $request->user();
        $this->authorizeRole($user);
        $student = $this->findStudent($user, $studentId, $user->role === 'validasi' ? (string) $request->query('kkn_year') : null);
        $courses = DB::connection('simak_main')->table('s1m4k_mastermk as course')
            ->where('course.id_prodi', $student->id_prodi)
            ->where('course.status', 'A')
            ->orderBy('course.semester')
            ->orderBy('course.id_jenis_mata_kuliah')
            ->orderBy('course.nama_mata_kuliah')
            ->get([
                'course.id_matkul', 'course.kode_mata_kuliah', 'course.nama_mata_kuliah',
                'course.semester', 'course.id_jenis_mata_kuliah', 'course.sks_mata_kuliah',
            ]);

        foreach ($courses as $course) {
            $attempt = DB::connection('simak_main')->table('s1m4k_krs')
                ->where('id_mahasiswa', $student->id_mahasiswa)
                ->where('id_matkul', $course->id_matkul)
                ->orderBy('nilai_huruf')
                ->first(['nilai_huruf']);
            $course->nilai_huruf = $attempt?->nilai_huruf;
            $course->is_programmed = $attempt !== null;
            $course->course_type = $this->courseType((string) $course->id_jenis_mata_kuliah);
        }

        $pdf = $this->newPdf();
        $text = fn (string $value): string => $this->pdfText($value);
        $logoPath = app(InstitutionSettings::class)->logoFilePath();
        if (is_file($logoPath)) {
            $pdf->Image($logoPath, 2, 0.5, 2, 2);
        }
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(19, 0.5, $text(mb_strtoupper(app(InstitutionSettings::class)->institutionName())), 0, 1, 'C');
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(19, 0.6, $text('CEK MATA KULIAH'), 0, 1, 'C');
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(19, 0.6, $text($student->nim.' - '.$student->nama_mahasiswa), 0, 1, 'C');
        $pdf->Ln();
        $pdf->SetFont('Arial', 'B', 8);
        foreach ([[1, 'No'], [2.5, 'Kode MK'], [7.5, 'Mata Kuliah'], [1.5, 'SMS'], [1.5, 'SKS'], [2.5, 'Jenis'], [1.25, 'Nilai'], [1.25, 'Diprogram']] as [$width, $label]) {
            $pdf->Cell($width, 0.7, $text($label), 'LRTB', 0, 'C');
        }
        $pdf->Ln();
        $pdf->SetFont('Arial', '', 8);
        foreach ($courses as $index => $course) {
            foreach ([
                [1, (string) ($index + 1), 'C'],
                [2.5, (string) $course->kode_mata_kuliah, 'C'],
                [7.5, ucwords(mb_strtolower((string) $course->nama_mata_kuliah)), 'L'],
                [1.5, (string) $course->semester, 'C'],
                [1.5, number_format((float) $course->sks_mata_kuliah, 0), 'C'],
                [2.5, $course->course_type, 'C'],
                [1.25, (string) ($course->nilai_huruf ?? '-'), 'C'],
                [1.25, $course->is_programmed ? 'Ya' : 'Tidak', 'C'],
            ] as [$width, $value, $align]) {
                $pdf->Cell($width, 0.7, $text($value), 'LBTR', 0, $align);
            }
            $pdf->Ln();
        }

        return response($pdf->Output('S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Cek-Mata-Kuliah-'.$student->nim.'.pdf"',
        ]);
    }

    private function transcriptReport(object $student): array
    {
        $attempts = DB::connection('simak_main')->table('s1m4k_krs as krs')
            ->leftJoin('s1m4k_mastermk as course', 'krs.id_matkul', '=', 'course.id_matkul')
            ->where('krs.id_mahasiswa', $student->id_mahasiswa)
            ->orderBy('course.semester')
            ->orderBy('course.nama_mata_kuliah')
            ->get([
                'krs.id_matkul', 'krs.nilai_huruf', 'krs.nilai_bobot',
                'course.kode_mata_kuliah', 'course.nama_mata_kuliah',
                'course.semester', 'course.sks_mata_kuliah',
            ]);
        $courses = $attempts->groupBy('id_matkul')
            ->map(function (Collection $records): object {
                $best = $records->sortByDesc(fn (object $record): float => (float) ($record->nilai_bobot ?? 0))->first();
                $best->nilai_bobot = (float) ($best->nilai_bobot ?? 0);
                $best->nilai_huruf = $this->letterGrade($best->nilai_bobot, $best->nilai_huruf);
                $best->sks_mata_kuliah = (float) ($best->sks_mata_kuliah ?? 0);
                $best->mutu = $best->nilai_bobot * $best->sks_mata_kuliah;

                return $best;
            })
            ->sortBy(fn (object $course): string => str_pad((string) $course->semester, 8, '0', STR_PAD_LEFT).(string) $course->nama_mata_kuliah)
            ->values();
        $totalSks = (float) $courses->sum(fn (object $course): float => $course->sks_mata_kuliah);
        $totalQuality = (float) $courses->sum(fn (object $course): float => $course->mutu);

        return [
            'courses' => $courses,
            'total_sks' => $totalSks,
            'total_quality' => $totalQuality,
            'ipk' => $totalSks > 0 ? $totalQuality / $totalSks : 0.0,
        ];
    }

    private function studentQuery(object $user, ?string $kknYear = null)
    {
        $query = DB::connection('simak_main')->table('s1m4k_mastermhs as student')
            ->leftJoin('s1m4k_masterprodi as program', 'student.id_prodi', '=', 'program.id_prodi')
            ->leftJoin('s1m4k_masterfakultas as faculty', 'student.id_fakultas', '=', 'faculty.id_fakultas')
            ->leftJoin('s1m4k_masterdosen as dean', 'faculty.dekan', '=', 'dean.id_dosen')
            ->leftJoin('s1m4k_masterdosen as program_head', 'program.ketua_prodi', '=', 'program_head.id_dosen')
            ->select([
                'student.id_mahasiswa', 'student.nim', 'student.nama_mahasiswa',
                'student.id_prodi', 'student.id_fakultas', 'student.id_status_mahasiswa',
                'faculty.dekan as dean_id', 'program.ketua_prodi as program_head_id',
                'program.nama_program_studi', 'program_head.nama_dosen as program_head_name',
                'program_head.gelar_depan as program_head_prefix', 'program_head.gelar_belakang as program_head_suffix',
                'program_head.nip as program_head_nip', 'faculty.nama_fakultas',
                'dean.nama_dosen as dean_name', 'dean.gelar_depan as dean_prefix',
                'dean.gelar_belakang as dean_suffix', 'dean.nip as dean_nip',
            ])
            ->whereRaw("TRIM(COALESCE(student.id_mahasiswa, '')) <> ''");

        if ($user->role === 'userprodi' || $user->role === 'usertu') {
            abort_unless(filled(data_get($user, 'id_prodi')), 403);
            $query->where('student.id_prodi', data_get($user, 'id_prodi'));
        } elseif ($user->role === 'dosen') {
            $lecturerId = DB::connection('simak_main')->table('s1m4k_masterdosen')
                ->where('nidn', data_get($user, 'login'))
                ->value('id_dosen');
            abort_if($lecturerId === null, 403);
            $query->where('student.dosen_pa', $lecturerId)
                ->whereIn('student.id_status_mahasiswa', self::ACTIVE_STUDENT_STATUSES);
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

    private function applySearch($query, string $search): void
    {
        $search = trim($search);
        $query->where(function ($builder) use ($search): void {
            $builder->where('student.nim', 'like', '%'.$search.'%')
                ->orWhere('student.nama_mahasiswa', 'like', '%'.$search.'%');
        });
    }

    private function authorizeRole(object $user): void
    {
        abort_unless(in_array($user->role, self::ROLES, true), 403);
    }

    private function newPdf(): \FPDF
    {
        abort_unless(class_exists(\FPDF::class), 500, 'Library cetak transkrip tidak tersedia.');
        $pdf = new \FPDF('P', 'cm', 'A4');
        $pdf->SetAutoPageBreak(true, 1.5);
        $pdf->AddPage();

        return $pdf;
    }

    private function verificationStudent(string $studentId): ?object
    {
        return DB::connection('simak_main')->table('s1m4k_mastermhs as student')
            ->leftJoin('s1m4k_masterprodi as program', 'student.id_prodi', '=', 'program.id_prodi')
            ->leftJoin('s1m4k_masterfakultas as faculty', 'student.id_fakultas', '=', 'faculty.id_fakultas')
            ->leftJoin('s1m4k_masterdosen as dean', 'faculty.dekan', '=', 'dean.id_dosen')
            ->leftJoin('s1m4k_masterdosen as program_head', 'program.ketua_prodi', '=', 'program_head.id_dosen')
            ->where('student.id_mahasiswa', $studentId)
            ->first([
                'student.id_mahasiswa', 'student.nim', 'student.nama_mahasiswa',
                'program.nama_program_studi', 'faculty.dekan as dean_id',
                'faculty.nama_fakultas', 'dean.nama_dosen as dean_name',
                'dean.gelar_depan as dean_prefix', 'dean.gelar_belakang as dean_suffix',
                'dean.nip as dean_nip', 'program.ketua_prodi as program_head_id',
                'program_head.nama_dosen as program_head_name',
                'program_head.gelar_depan as program_head_prefix',
                'program_head.gelar_belakang as program_head_suffix',
                'program_head.nip as program_head_nip',
            ]);
    }

    private function transcriptValidationCode(string $studentId, string $documentType, string $signatoryId): string
    {
        $connection = DB::connection('simak_main');
        $documentReference = $this->transcriptDocumentReference($studentId, $documentType, $signatoryId);
        $existing = $connection->table('validasi_dok')
            ->where('id_dokumen', $documentReference)
            ->where('jenis_dokumen', $documentType)
            ->orderBy('created_at')
            ->first(['id']);
        if ($existing !== null) {
            return (string) $existing->id;
        }

        $code = (string) Str::uuid();
        $connection->table('validasi_dok')->insert([
            'id' => $code,
            'id_dokumen' => $documentReference,
            'jenis_dokumen' => $documentType,
            'created_at' => now(),
        ]);

        return $code;
    }

    private function transcriptDocumentReference(string $studentId, string $documentType, string $signatoryId): string
    {
        return hash('sha256', $studentId."\0".$documentType."\0".$signatoryId);
    }

    private function pdfText(string $value): string
    {
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $value) ?: '';
    }

    private function letterGrade(float $weight, ?string $original): string
    {
        return match (number_format($weight, 2, '.', '')) {
            '4.00' => 'A',
            '3.50' => 'AB',
            '3.00' => 'B',
            '2.50' => 'BC',
            '2.00' => 'C',
            '1.00' => 'D',
            default => $original ?: 'E',
        };
    }

    private function courseType(string $type): string
    {
        return match ($type) {
            'A' => 'Wajib',
            'B' => 'Pilihan',
            'C' => 'Wajib Peminatan',
            'D' => 'Pilihan Peminatan',
            'S' => 'Skripsi/Disertasi',
            default => '-',
        };
    }
}
