<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Support\LecturerName;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class KrsTransferController extends Controller
{
    private const ROLES = ['admin', 'userprodi', 'usertu'];

    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $this->authorizeRole($user);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        $students = null;
        if (filled($filters['search'] ?? null)) {
            $search = trim($filters['search']);
            if (ctype_digit($search)) {
                $student = $this->studentQuery($user)
                    ->where('student.nim', $search)
                    ->first();
                if ($student === null) {
                    return redirect()->route('krs-transfer.index')
                        ->withInput(['search' => $search])
                        ->withErrors(['search' => 'Data mahasiswa transfer dengan NPM tersebut tidak ditemukan.']);
                }

                return redirect()->route('krs-transfer.show', ['student' => $student->id_mahasiswa]);
            }

            $students = $this->studentQuery($user)
                ->where(function ($query) use ($filters): void {
                    $search = trim($filters['search']);
                    $query->where('student.nim', 'like', '%'.$search.'%')
                        ->orWhere('student.nama_mahasiswa', 'like', '%'.$search.'%');
                })
                ->orderBy('student.nama_mahasiswa')
                ->paginate(25)
                ->withQueryString();
        }

        return view('krs-transfer.index', [
            'students' => $students,
            'search' => $filters['search'] ?? '',
            'canManage' => in_array($user->role, ['admin', 'userprodi'], true),
        ]);
    }

    public function show(Request $request, string $studentId): View
    {
        $user = $request->user();
        $this->authorizeRole($user);
        $student = $this->findStudent($user, $studentId);
        $courses = $this->transferCourses($studentId);

        return view('krs-transfer.show', [
            'student' => $student,
            'courses' => $courses,
            'creditTotal' => $courses->sum(fn (object $course): float => (float) $course->sks_mata_kuliah),
            'canManage' => in_array($user->role, ['admin', 'userprodi'], true),
        ]);
    }

    public function editCourses(Request $request, string $studentId): View
    {
        $user = $request->user();
        $this->authorizeManage($user);
        $student = $this->findStudent($user, $studentId);
        $courses = DB::connection('simak_main')->table('s1m4k_mastermk')
            ->where('id_prodi', $student->id_prodi)
            ->where('status', 'A')
            ->orderBy('semester')
            ->orderBy('nama_mata_kuliah')
            ->get(['id_matkul', 'kode_mata_kuliah', 'nama_mata_kuliah', 'semester', 'sks_mata_kuliah']);
        $selectedIds = DB::connection('simak_main')->table('s1m4k_krs')
            ->where('id_mahasiswa', $studentId)
            ->where('krs_reguler', 'N')
            ->pluck('id_matkul')
            ->map(fn ($id): string => (string) $id)
            ->all();

        return view('krs-transfer.courses', [
            'student' => $student,
            'courses' => $courses,
            'selectedIds' => $selectedIds,
            'maximumCredits' => (float) $student->sks_diakui,
        ]);
    }

    public function saveCourses(Request $request, string $studentId, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeManage($user);
        $student = $this->findStudent($user, $studentId);
        $validated = $request->validate([
            'course_ids' => ['required', 'array', 'min:1'],
            'course_ids.*' => ['required', 'string', 'distinct', 'max:100'],
        ]);

        $connection = DB::connection('simak_main');
        $courses = $connection->table('s1m4k_mastermk')
            ->where('id_prodi', $student->id_prodi)
            ->where('status', 'A')
            ->whereIn('id_matkul', $validated['course_ids'])
            ->get(['id_matkul', 'kode_mata_kuliah', 'nama_mata_kuliah', 'sks_mata_kuliah']);
        if ($courses->count() !== count($validated['course_ids'])) {
            throw ValidationException::withMessages([
                'course_ids' => 'Pilih hanya mata kuliah aktif dari program studi mahasiswa.',
            ]);
        }

        $credits = $courses->sum(fn (object $course): float => (float) $course->sks_mata_kuliah);
        $maximumCredits = (float) $student->sks_diakui;
        if ($credits > $maximumCredits) {
            throw ValidationException::withMessages([
                'course_ids' => "Total SKS ({$credits}) melebihi batas SKS diakui ({$maximumCredits}).",
            ]);
        }

        $before = $this->transferCourses($studentId)->map(fn (object $course): array => [
            'id_matkul' => $course->id_matkul,
            'kode' => $course->kode_mata_kuliah,
            'nama' => $course->nama_mata_kuliah,
            'sks' => $course->sks_mata_kuliah,
        ])->values()->all();
        $after = $courses->map(fn (object $course): array => [
            'id_matkul' => $course->id_matkul,
            'kode' => $course->kode_mata_kuliah,
            'nama' => $course->nama_mata_kuliah,
            'sks' => $course->sks_mata_kuliah,
        ])->values()->all();

        $connection->transaction(function () use ($connection, $student, $studentId, $courses, $activityLogger, $user, $before, $after): void {
            $connection->table('s1m4k_krs')
                ->where('id_mahasiswa', $studentId)
                ->where('krs_reguler', 'N')
                ->delete();

            foreach ($courses as $course) {
                $connection->table('s1m4k_krs')->insert([
                    'id_krs' => (string) Str::uuid(),
                    'id_perguruan_tinggi' => $student->id_perguruan_tinggi,
                    'id_fakultas' => $student->id_fakultas,
                    'id_prodi' => $student->id_prodi,
                    'id_kalender' => '00000',
                    'id_mahasiswa' => $studentId,
                    'id_jadwal' => '-',
                    'id_matkul' => $course->id_matkul,
                    'tgl_update' => now()->toDateString(),
                    'krs_reguler' => 'N',
                    'nilai_huruf' => 'E',
                    'input_nilai' => 'N',
                    'krs_mbkm' => 'N',
                    'isi_edom' => 'N',
                    'user' => (string) $user->legacy_id,
                ]);
            }

            $activityLogger->record($user, 'KRS Transfer', 'Perbarui mata kuliah transfer', [
                'data_sebelum' => $before,
                'data_sesudah' => $after,
            ], $studentId, $this->studentIdentity($student));
        });

        return redirect()->route('krs-transfer.show', $studentId)
            ->with('status', 'Mata kuliah transfer berhasil disimpan.');
    }

    public function editGrades(Request $request, string $studentId): View
    {
        $user = $request->user();
        $this->authorizeManage($user);
        $student = $this->findStudent($user, $studentId);
        $courses = $this->transferCourses($studentId);
        abort_if($courses->isEmpty(), 404);
        $gradeScales = DB::connection('simak_main')->table('s1m4k_masterbobotnilai')
            ->orderBy('nilai_huruf')
            ->get(['nilai_huruf', 'bobot_minimum', 'bobot_maksimum']);

        return view('krs-transfer.grades', compact('student', 'courses', 'gradeScales'));
    }

    public function saveGrades(Request $request, string $studentId, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeManage($user);
        $student = $this->findStudent($user, $studentId);
        $submitted = $request->input('grades');
        if (! is_array($submitted) || $submitted === []) {
            throw ValidationException::withMessages(['grades' => 'Tidak ada nilai transfer untuk disimpan.']);
        }

        $existingCourses = $this->transferCourses($studentId)->keyBy('id_krs');
        if (count($submitted) !== $existingCourses->count() || array_diff(array_keys($submitted), $existingCourses->keys()->all()) !== []) {
            throw ValidationException::withMessages(['grades' => 'Daftar mata kuliah tidak sesuai dengan data KRS transfer mahasiswa.']);
        }

        $normalized = [];
        foreach ($submitted as $krsId => $grade) {
            if (! is_array($grade)) {
                throw ValidationException::withMessages(['grades' => 'Data nilai transfer tidak valid.']);
            }
            $score = str_replace(',', '.', trim((string) ($grade['nilai'] ?? '')));
            $credit = trim((string) ($grade['sks_asal'] ?? ''));
            $letter = mb_strtoupper(trim((string) ($grade['nilai_huruf_asal'] ?? '')));
            if (! is_numeric($score) || (float) $score < 0 || (float) $score > 100) {
                throw ValidationException::withMessages(["grades.{$krsId}.nilai" => 'Nilai angka harus berada di antara 0 sampai 100.']);
            }
            if ($credit === '' || ! is_numeric($credit) || (float) $credit < 0 || (float) $credit > 99 || (float) $credit !== (float) (int) $credit) {
                throw ValidationException::withMessages(["grades.{$krsId}.sks_asal" => 'SKS asal harus berupa bilangan bulat antara 0 sampai 99.']);
            }
            if (mb_strlen($letter) > 4) {
                throw ValidationException::withMessages(["grades.{$krsId}.nilai_huruf_asal" => 'Nilai huruf asal maksimal 4 karakter.']);
            }
            $normalized[$krsId] = [
                'sks_asal' => $credit,
                'nilai_huruf_asal' => $letter,
                'nilai_angka' => (int) round((float) $score, 0, PHP_ROUND_HALF_UP),
            ];
        }

        $scales = DB::connection('simak_main')->table('s1m4k_masterbobotnilai')
            ->orderBy('nilai_huruf')
            ->get(['nilai_huruf', 'nilai_indeks', 'bobot_minimum', 'bobot_maksimum']);
        $updates = [];
        foreach ($normalized as $krsId => $grade) {
            $matchedScale = $scales->first(fn (object $scale): bool => is_numeric($scale->bobot_minimum)
                && is_numeric($scale->bobot_maksimum)
                && $grade['nilai_angka'] >= (float) $scale->bobot_minimum
                && $grade['nilai_angka'] <= (float) $scale->bobot_maksimum
            );
            if ($matchedScale === null) {
                throw ValidationException::withMessages([
                    "grades.{$krsId}.nilai" => "Nilai angka {$grade['nilai_angka']} tidak termasuk dalam rentang bobot nilai yang tersedia.",
                ]);
            }
            $updates[$krsId] = [
                ...$grade,
                'nilai_huruf' => $matchedScale->nilai_huruf,
                'nilai_bobot' => $matchedScale->nilai_indeks,
            ];
        }

        $before = $existingCourses->mapWithKeys(fn (object $course): array => [
            $course->id_krs => [
                'sks_asal' => $course->sks_asal,
                'nilai_huruf_asal' => $course->nilai_huruf_asal,
                'nilai_angka' => $course->nilai_angka,
                'nilai_bobot' => $course->nilai_bobot,
                'nilai_huruf' => $course->nilai_huruf,
            ],
        ])->all();
        $connection = DB::connection('simak_main');
        $connection->transaction(function () use ($connection, $updates, $studentId, $user, $activityLogger, $student, $before): void {
            foreach ($updates as $krsId => $values) {
                $connection->table('s1m4k_krs')
                    ->where('id_krs', $krsId)
                    ->where('id_mahasiswa', $studentId)
                    ->where('krs_reguler', 'N')
                    ->update([
                        ...$values,
                        'tgl_update' => now()->toDateString(),
                        'user' => (string) $user->legacy_id,
                        'input_nilai' => 'Y',
                    ]);
            }
            $activityLogger->record($user, 'KRS Transfer', 'Simpan nilai transfer', [
                'data_sebelum' => $before,
                'data_sesudah' => $updates,
            ], $studentId, $this->studentIdentity($student));
        });

        return redirect()->route('krs-transfer.show', $studentId)
            ->with('status', 'Nilai transfer berhasil disimpan.');
    }

    public function print(Request $request, string $studentId): Response
    {
        $user = $request->user();
        $this->authorizeRole($user);
        $student = $this->findStudent($user, $studentId);
        $courses = $this->transferCourses($studentId);
        abort_if($courses->isEmpty(), 404);
        $program = DB::connection('simak_main')->table('s1m4k_masterprodi as program')
            ->leftJoin('s1m4k_masterdosen as chair', 'program.ketua_prodi', '=', 'chair.id_dosen')
            ->where('program.id_prodi', $student->id_prodi)
            ->first([
                'program.nama_program_studi',
                'chair.gelar_depan as chair_gelar_depan',
                'chair.nama_dosen as chair_nama',
                'chair.gelar_belakang as chair_gelar_belakang',
                'chair.nip as chair_nip',
            ]);

        $pdf = new \FPDF('P', 'cm', 'A4');
        $pdf->AddPage();
        foreach ([
            [public_path('images/kkn/dikbud.jpg'), 2.5],
            [app(\App\Services\InstitutionSettings::class)->logoFilePath(), 17],
        ] as [$image, $x]) {
            if (is_file($image)) {
                $pdf->Image($image, $x, 0.5, 2, 2);
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
        $pdf->Cell(19.5, 0.5, $text('Nilai Mahasiswa Transfer/Pindahan'), 0, 1, 'C');
        $pdf->Line(1, 3.3, 20, 3.3);
        $pdf->Ln();
        $pdf->SetFont('Times', '', 10);
        $pdf->Cell(4, 0.5, 'NPM', 0, 0);
        $pdf->Cell(0.3, 0.5, ':', 0, 0, 'C');
        $pdf->Cell(7, 0.5, $text((string) $student->nim), 0, 0);
        $pdf->Cell(2, 0.5, '', 0, 0);
        $pdf->Cell(4, 0.5, 'SKS Diakui', 0, 0);
        $pdf->Cell(0.3, 0.5, ':', 0, 0, 'C');
        $pdf->Cell(3, 0.5, $text((string) $student->sks_diakui).' SKS', 0, 1);
        $pdf->Cell(4, 0.5, 'Nama', 0, 0);
        $pdf->Cell(0.3, 0.5, ':', 0, 0, 'C');
        $pdf->Cell(7, 0.5, $text(ucwords(mb_strtolower((string) $student->nama_mahasiswa))), 0, 1);
        $pdf->Ln();
        $pdf->SetFont('Times', 'B', 10);
        foreach ([[1.5, 'No'], [2.5, 'Kode MK'], [12.5, 'Nama Mata Kuliah'], [1, 'Nilai'], [2, 'SKS']] as [$width, $label]) {
            $pdf->Cell($width, 0.5, $text($label), 'LRTB', 0, 'C');
        }
        $pdf->Ln();
        $pdf->SetFont('Times', '', 10);
        foreach ($courses as $index => $course) {
            $pdf->Cell(1.5, 0.5, (string) ($index + 1), 'LRTB', 0, 'C');
            $pdf->Cell(2.5, 0.5, $text((string) $course->kode_mata_kuliah), 'LRTB', 0, 'C');
            $pdf->Cell(12.5, 0.5, $text(ucwords(mb_strtolower((string) $course->nama_mata_kuliah))), 'LRTB', 0);
            $pdf->Cell(1, 0.5, $text((string) ($course->nilai_huruf ?? 'E')), 'LRTB', 0, 'C');
            $pdf->Cell(2, 0.5, $text((string) (int) $course->sks_mata_kuliah), 'LRTB', 0, 'C');
            $pdf->Ln();
        }
        $pdf->SetFont('Times', 'B', 10);
        $pdf->Cell(17.5, 0.5, $text('Jumlah SKS'), 'LRTB', 0);
        $pdf->Cell(2, 0.5, (string) (int) $courses->sum(fn (object $course): float => (float) $course->sks_mata_kuliah), 'LRTB', 0, 'C');
        $pdf->Ln(1);
        $pdf->SetFont('Times', '', 10);
        $pdf->Cell(19, 0.5, $text('Ternate, '.now()->locale('id')->translatedFormat('d F Y')), 0, 1, 'R');
        $pdf->Ln(0.5);
        $chairName = LecturerName::format(
            $program?->chair_nama ? ucwords(mb_strtolower($program->chair_nama)) : null,
            $program?->chair_gelar_depan,
            $program?->chair_gelar_belakang,
        );
        $pdf->Cell(9.5, 0.5, $text('Ketua Program Studi,'), 0, 0, 'C');
        $pdf->Cell(9.5, 0.5, $text('Mahasiswa,'), 0, 1, 'C');
        $pdf->Ln(2);
        $pdf->Cell(9.5, 0.5, $text($chairName), 0, 0, 'C');
        $pdf->Cell(9.5, 0.5, $text(ucwords(mb_strtolower((string) $student->nama_mahasiswa))), 0, 1, 'C');
        $pdf->Cell(9.5, 0.5, $text('NIP. '.(string) ($program?->chair_nip ?? '')), 0, 0, 'C');
        $pdf->Cell(9.5, 0.5, $text((string) $student->nim), 0, 0, 'C');

        return response($pdf->Output('S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Nilai-Transfer-'.$student->nim.'.pdf"',
        ]);
    }

    private function authorizeRole(object $user): void
    {
        abort_unless(in_array($user->role, self::ROLES, true), 403);
    }

    private function authorizeManage(object $user): void
    {
        $this->authorizeRole($user);
        abort_unless(in_array($user->role, ['admin', 'userprodi'], true), 403);
    }

    private function studentQuery(object $user)
    {
        $query = DB::connection('simak_main')->table('s1m4k_mastermhs as student')
            ->leftJoin('s1m4k_masterprodi as program', 'student.id_prodi', '=', 'program.id_prodi')
            ->where('student.id_jenis_daftar', '2')
            ->whereRaw("TRIM(COALESCE(student.id_mahasiswa, '')) <> ''")
            ->select([
                'student.id_mahasiswa', 'student.nim', 'student.nama_mahasiswa',
                'student.id_prodi', 'program.id_fakultas', 'program.id_perguruan_tinggi',
                'student.sks_diakui', 'program.nama_program_studi',
            ]);
        if ($user->role === 'userprodi' || $user->role === 'usertu') {
            $programId = trim((string) data_get($user, 'id_prodi'));
            abort_unless($programId !== '', 403);
            $query->where('student.id_prodi', $programId);
        }

        return $query;
    }

    private function findStudent(object $user, string $studentId): object
    {
        $student = $this->studentQuery($user)->where('student.id_mahasiswa', $studentId)->first();
        abort_if($student === null, 404);

        return $student;
    }

    private function transferCourses(string $studentId)
    {
        return DB::connection('simak_main')->table('s1m4k_krs as krs')
            ->leftJoin('s1m4k_mastermk as course', 'krs.id_matkul', '=', 'course.id_matkul')
            ->where('krs.id_mahasiswa', $studentId)
            ->where('krs.krs_reguler', 'N')
            ->orderBy('course.semester')
            ->orderBy('course.nama_mata_kuliah')
            ->get([
                'krs.id_krs', 'krs.id_matkul', 'krs.sks_asal', 'krs.nilai_huruf_asal',
                'krs.nilai_angka', 'krs.nilai_bobot', 'krs.nilai_huruf', 'krs.input_nilai',
                'course.kode_mata_kuliah', 'course.nama_mata_kuliah', 'course.semester',
                'course.sks_mata_kuliah',
            ]);
    }

    private function studentIdentity(object $student): array
    {
        return [
            'nama' => $student->nama_mahasiswa,
            'nomor_identitas' => $student->nim,
            'program_studi' => $student->nama_program_studi,
        ];
    }
}
