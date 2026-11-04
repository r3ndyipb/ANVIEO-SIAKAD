<?php

namespace App\Http\Controllers;

use App\Mail\GraduateThesisNotificationMail;
use App\Services\ActivityLogger;
use App\Services\InstitutionSettings;
use App\Services\QrCodeService;
use App\Support\ChatMessageText;
use App\Support\LecturerName;
use App\Support\Utf8Text;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Throwable;

class GraduateThesisController extends Controller
{
    private const ALLOWED_DEGREES = ['S2', 'S3'];

    private const READ_ROLES = ['admin', 'pimuniv', 'pimfak', 'userprodi', 'usertu', 'dosen', 'mahasiswa'];

    private const DASHBOARD_ROLES = ['admin', 'pimuniv', 'pimfak', 'userprodi', 'usertu', 'dosen'];

    private const EXAM_ROLES = ['admin', 'pimuniv', 'pimfak', 'userprodi', 'usertu', 'dosen'];

    private const STATUS_LABELS = [
        0 => 'Menunggu Penugasan',
        1 => 'Membimbing Proposal',
        2 => 'Ujian Proposal',
        3 => 'Membimbing Hasil',
        4 => 'Ujian Hasil',
        5 => 'Ujian Tesis & Disertasi',
        6 => 'Selesai Bimbingan',
    ];

    private const ASSIGNMENT_FIELDS = [
        'pembimbing1' => 'Pembimbing 1',
        'pembimbing2' => 'Pembimbing 2',
        'penguji1' => 'Penguji 1',
        'penguji2' => 'Penguji 2',
        'penguji3' => 'Penguji 3',
    ];

    public function dashboard(Request $request): View
    {
        $user = $request->user();
        abort_unless(in_array($user->role, self::DASHBOARD_ROLES, true), 403);
        $programs = $this->programsFor($user);
        $programId = $this->selectedProgram($user, $programs, $request->query('program'));
        $data = $user->role === 'dosen' || $programId !== ''
            ? $this->dashboardDataFor($user, $programId)
            : null;

        return view('graduate-theses.dashboard', [
            'user' => $user,
            'programs' => $programs,
            'programId' => $programId,
            'showProgramFilter' => in_array($user->role, ['admin', 'pimuniv', 'pimfak'], true),
            'data' => $data,
        ]);
    }

    public function titleProposalIndex(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->role === 'mahasiswa', 403);
        $student = DB::connection('simak_main')->table('s1m4k_mastermhs as student')
            ->leftJoin('s1m4k_masterprodi as program', 'student.id_prodi', '=', 'program.id_prodi')
            ->leftJoin('s1m4k_masterfakultas as faculty', 'student.id_fakultas', '=', 'faculty.id_fakultas')
            ->where('student.id_mahasiswa', (string) data_get($user, 'legacy_id'))
            ->where('student.id_status_mahasiswa', '<>', 'Z')
            ->whereIn('program.nama_jenjang_pendidikan', self::ALLOWED_DEGREES)
            ->first([
                'student.id_mahasiswa', 'student.nim', 'student.nama_mahasiswa', 'student.email',
                'student.id_prodi', 'student.id_fakultas', 'program.nama_program_studi', 'faculty.nama_fakultas',
            ]);
        abort_if($student === null, 404);

        $proposals = DB::connection('simak_main')->table('etesis_judul')
            ->where('id_mahasiswa', $student->id_mahasiswa)
            ->orderBy('tanggal_usul')->orderBy('id')
            ->get()
            ->map(function (object $proposal): object {
                $proposal->status_label = $this->titleStatusLabel((int) $proposal->status);
                $endDate = (int) $proposal->status === 0 || blank($proposal->tanggal_verifikasi)
                    ? now()->startOfDay()
                    : Carbon::parse($proposal->tanggal_verifikasi)->startOfDay();
                $proposal->elapsed_days = Carbon::parse($proposal->tanggal_usul)->startOfDay()->diffInDays($endDate);

                return $proposal;
            });

        return view('graduate-theses.title-proposals', [
            'student' => $student,
            'proposals' => $proposals,
            'canPropose' => filled($student->email) && ! $proposals->contains(fn (object $proposal): bool => (int) $proposal->status !== 2),
        ]);
    }

    public function storeTitleProposal(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->role === 'mahasiswa', 403);
        $validated = $request->validate([
            'judul' => ['required', 'string', 'max:2000'],
            'deskripsi' => ['required', 'string', 'max:20000'],
        ]);

        $connection = DB::connection('simak_main');
        $proposal = $connection->transaction(function () use ($connection, $user, $validated, $activityLogger): array {
            $student = $connection->table('s1m4k_mastermhs as student')
                ->join('s1m4k_masterprodi as program', 'student.id_prodi', '=', 'program.id_prodi')
                ->where('student.id_mahasiswa', (string) data_get($user, 'legacy_id'))
                ->where('student.id_status_mahasiswa', '<>', 'Z')
                ->whereIn('program.nama_jenjang_pendidikan', self::ALLOWED_DEGREES)
                ->lockForUpdate()->first([
                    'student.id_mahasiswa', 'student.nim', 'student.nama_mahasiswa',
                    'student.email', 'student.id_prodi', 'student.id_fakultas',
                ]);
            abort_if($student === null, 404);
            if (! filter_var(trim((string) $student->email), FILTER_VALIDATE_EMAIL)) {
                throw ValidationException::withMessages([
                    'email' => 'Perbarui email aktif pada profil sebelum mengajukan judul.',
                ]);
            }
            if ($connection->table('etesis_judul')->where('id_mahasiswa', $student->id_mahasiswa)
                ->where('status', '<>', 2)->exists()) {
                throw ValidationException::withMessages([
                    'judul' => 'Usulan sebelumnya masih diverifikasi atau sudah disetujui.',
                ]);
            }

            $id = (string) Str::uuid();
            $connection->table('etesis_judul')->insert([
                'id' => $id,
                'id_fakultas' => $student->id_fakultas,
                'id_prodi' => $student->id_prodi,
                'id_mahasiswa' => $student->id_mahasiswa,
                'judul' => trim($validated['judul']),
                'deskripsi' => trim($validated['deskripsi']),
                'status' => 0,
                'tanggal_usul' => now()->toDateString(),
            ]);
            $activityLogger->record($user, 'Usulan Judul Tesis & Disertasi', 'Mengajukan usulan judul', [
                'judul' => trim($validated['judul']),
            ], $id, ['npm' => $student->nim, 'nama' => $student->nama_mahasiswa]);

            return ['id' => $id, 'student' => $student];
        });

        $this->sendThesisNotification(
            trim((string) $proposal['student']->email),
            'Bukti Usulan Proposal Tesis & Disertasi',
            (string) $proposal['student']->nama_mahasiswa,
            'Usulan judul proposal tesis & disertasi Anda telah berhasil direkam dan sedang diverifikasi.',
            [
                'NIM' => $proposal['student']->nim,
                'Judul' => trim($validated['judul']),
                'Tanggal ajuan' => now()->format('d-m-Y'),
            ],
            null,
            'title-proposal',
            'mahasiswa',
            (string) $proposal['student']->id_mahasiswa,
            route('graduate_theses.titles.index')
        );
        $this->notifyProgramOfTitleProposal($proposal['student'], trim($validated['judul']));

        return redirect()->route('graduate_theses.titles.index')->with('status', 'Usulan judul berhasil dikirim dan sedang diverifikasi.');
    }

    public function titleValidationIndex(Request $request): View
    {
        $user = $request->user();
        abort_unless(in_array($user->role, ['admin', 'userprodi', 'usertu', 'pimuniv', 'pimfak'], true), 403);
        $programs = $this->programsFor($user);
        $programId = $this->selectedProgram($user, $programs, $request->query('program'));
        $proposals = collect();

        if ($programId !== '') {
            $proposals = DB::connection('simak_main')->table('etesis_judul as proposal')
                ->join('s1m4k_mastermhs as student', 'proposal.id_mahasiswa', '=', 'student.id_mahasiswa')
                ->leftJoin('s1m4k_masterprodi as program', 'proposal.id_prodi', '=', 'program.id_prodi')
                ->where('proposal.id_prodi', $programId)
                ->where('student.id_status_mahasiswa', '<>', 'Z')
                ->orderBy('proposal.status')->orderBy('proposal.tanggal_usul')->orderBy('proposal.id')
                ->get([
                    'proposal.id', 'proposal.id_mahasiswa', 'proposal.judul', 'proposal.deskripsi',
                    'proposal.komentar', 'proposal.status', 'proposal.tanggal_usul', 'proposal.tanggal_verifikasi',
                    'student.nim', 'student.nama_mahasiswa', 'program.nama_program_studi',
                ])
                ->map(function (object $proposal): object {
                    $proposal->status_label = $this->titleStatusLabel((int) $proposal->status);

                    return $proposal;
                });
        }

        return view('graduate-theses.title-validation-index', [
            'user' => $user,
            'programs' => $programs,
            'programId' => $programId,
            'proposals' => $proposals,
            'showProgramFilter' => in_array($user->role, ['admin', 'pimuniv', 'pimfak'], true),
            'canVerify' => in_array($user->role, ['admin', 'userprodi'], true),
        ]);
    }

    public function titleValidationShow(Request $request, string $proposalId): View
    {
        $user = $request->user();
        abort_unless(in_array($user->role, ['admin', 'userprodi', 'usertu', 'pimuniv', 'pimfak'], true), 403);
        $proposal = $this->scopedTitleProposal($user, $proposalId);
        $assignments = DB::connection('simak_main')->table('etesis_skripsi')
            ->where('id_proposal', $proposalId)
            ->orderByDesc('created_at')
            ->first(array_keys(self::ASSIGNMENT_FIELDS));

        return view('graduate-theses.title-validation-show', [
            'proposal' => $proposal,
            'assignments' => $assignments,
            'canVerify' => in_array($user->role, ['admin', 'userprodi'], true),
            'lecturers' => DB::connection('simak_main')->table('s1m4k_masterdosen')
                ->whereNotNull('nama_dosen')->where('nama_dosen', '<>', '')
                ->orderBy('nama_dosen')
                ->get(['id_dosen', 'nama_dosen', 'gelar_depan', 'gelar_belakang']),
        ]);
    }

    public function saveTitleValidation(Request $request, string $proposalId, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();
        abort_unless(in_array($user->role, ['admin', 'userprodi'], true), 403);
        $this->scopedTitleProposal($user, $proposalId);
        $validated = $request->validate([
            'status' => ['required', 'integer', 'in:1,2'],
            'komentar' => ['required', 'string', 'max:10000'],
            'pembimbing1' => ['required_if:status,1', 'nullable', 'string', 'max:100'],
            'pembimbing2' => ['nullable', 'string', 'max:100'],
            'penguji1' => ['nullable', 'string', 'max:100'],
            'penguji2' => ['nullable', 'string', 'max:100'],
            'penguji3' => ['nullable', 'string', 'max:100'],
        ]);

        $connection = DB::connection('simak_main');
        $verified = $connection->transaction(function () use ($connection, $user, $proposalId, $validated, $activityLogger): array {
            $proposal = $connection->table('etesis_judul')
                ->where('id', $proposalId)->lockForUpdate()->first();
            abort_if($proposal === null, 404);
            $this->assertProgramFacultyScope($user, $proposal);
            $student = $connection->table('s1m4k_mastermhs')
                ->where('id_mahasiswa', $proposal->id_mahasiswa)
                ->where('id_status_mahasiswa', '<>', 'Z')
                ->first(['id_mahasiswa', 'nim', 'nama_mahasiswa', 'email', 'id_prodi', 'id_fakultas']);
            abort_if($student === null, 404);

            $hasActiveThesisForProposal = $connection->table('etesis_skripsi')
                ->where('id_proposal', $proposalId)->where('status', '>', 0)->exists();
            if ($hasActiveThesisForProposal) {
                throw ValidationException::withMessages([
                    'status' => 'Usulan ini sudah memiliki proses bimbingan sehingga tidak dapat diverifikasi ulang.',
                ]);
            }
            if ((int) $validated['status'] === 1 && $connection->table('etesis_skripsi')
                ->where('id_mahasiswa', $student->id_mahasiswa)->exists()) {
                throw ValidationException::withMessages([
                    'status' => 'Mahasiswa ini sudah terdaftar pada data tesis & disertasi.',
                ]);
            }

            $assignments = [];
            if ((int) $validated['status'] === 1) {
                foreach (array_keys(self::ASSIGNMENT_FIELDS) as $field) {
                    $lecturerId = trim((string) ($validated[$field] ?? ''));
                    if ($lecturerId !== '' && ! $connection->table('s1m4k_masterdosen')->where('id_dosen', $lecturerId)->exists()) {
                        throw ValidationException::withMessages([
                            $field => 'Dosen yang dipilih tidak ditemukan.',
                        ]);
                    }
                    $assignments[$field] = $lecturerId === '' ? null : $lecturerId;
                }
            }

            $connection->table('etesis_judul')->where('id', $proposalId)->update([
                'status' => (int) $validated['status'],
                'komentar' => trim($validated['komentar']),
                'tanggal_verifikasi' => now()->toDateString(),
            ]);
            if ((int) $validated['status'] === 1) {
                $thesisId = (string) Str::uuid();
                $connection->table('etesis_skripsi')->insert([
                    'id' => $thesisId,
                    'id_fakultas' => $student->id_fakultas,
                    'id_prodi' => $student->id_prodi,
                    'id_mahasiswa' => $student->id_mahasiswa,
                    'id_proposal' => $proposalId,
                    'judul' => $proposal->judul,
                    ...$assignments,
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $thesisId = null;
                $connection->table('etesis_skripsi')->where('id_proposal', $proposalId)->delete();
            }
            $activityLogger->record($user, 'Validasi Usulan Judul', 'Menyimpan hasil verifikasi usulan judul', [
                'status' => (int) $validated['status'],
                'komentar' => trim($validated['komentar']),
                'penugasan_dosen' => $assignments,
            ], $proposalId, ['npm' => $student->nim, 'nama' => $student->nama_mahasiswa]);

            $lecturers = $connection->table('s1m4k_masterdosen')
                ->whereIn('id_dosen', array_values(array_filter($assignments)))
                ->get(['id_dosen', 'nama_dosen', 'gelar_depan', 'gelar_belakang'])->keyBy('id_dosen');
            $assignmentNames = [];
            foreach ($assignments as $field => $lecturerId) {
                $assignmentNames[self::ASSIGNMENT_FIELDS[$field]] = $lecturerId === null
                    ? '—'
                    : $this->lecturerName($lecturers->get($lecturerId));
            }

            return [
                'student' => $student,
                'title' => $proposal->judul,
                'status' => (int) $validated['status'],
                'assignments' => $assignmentNames,
                'assignment_ids' => $assignments,
                'thesis_id' => $thesisId,
            ];
        });

        $statusText = $verified['status'] === 1 ? 'DITERIMA' : 'DITOLAK / REVISI';
        $additionalMessage = $verified['status'] === 1
            ? 'Usulan judul telah disetujui dan dosen pembimbing/penguji telah ditetapkan.'
            : 'Usulan judul belum disetujui. Mahasiswa dapat memperbaiki usulan berdasarkan komentar dan mengajukannya kembali.';
        $email = trim((string) $verified['student']->email);
        $this->sendThesisNotification(
            $email,
            'Hasil Verifikasi Usulan Judul Tesis & Disertasi',
            (string) $verified['student']->nama_mahasiswa,
            'Hasil verifikasi usulan judul tesis & disertasi Anda: '.$statusText.'. '.$additionalMessage,
            [
                'NIM' => $verified['student']->nim,
                'Judul' => $verified['title'],
                'Tanggal verifikasi' => now()->format('d-m-Y'),
                'Komentar / catatan prodi' => trim($validated['komentar']),
                ...$verified['assignments'],
            ],
            trim($validated['komentar']),
            'title-validation',
            'mahasiswa',
            (string) $verified['student']->id_mahasiswa,
            route('graduate_theses.titles.index')
        );
        if ($verified['status'] === 1 && $verified['thesis_id'] !== null) {
            $this->notifyAssignedLecturers(
                $verified['student'],
                $verified['title'],
                $verified['assignment_ids'],
                [],
                (string) $verified['thesis_id'],
            );
        }

        return redirect()->route('graduate_theses.titles.validation.show', $proposalId)
            ->with('status', 'Hasil verifikasi usulan judul berhasil disimpan.');
    }

    public function lecturerAssignmentIndex(Request $request): View
    {
        $user = $request->user();
        abort_unless(in_array($user->role, ['admin', 'userprodi', 'usertu', 'pimuniv', 'pimfak'], true), 403);
        $programs = $this->programsFor($user);
        $programId = $this->selectedProgram($user, $programs, $request->query('program'));
        $theses = collect();

        if ($programId !== '') {
            $theses = $this->scopedTheses($user, $programId)
                ->orderByDesc('thesis.created_at')->orderBy('thesis.status')->get();
            $theses = $this->appendAssignmentNames($theses);
            $theses->each(function (object $thesis): void {
                $thesis->status_label = self::STATUS_LABELS[(int) $thesis->status] ?? 'Status tidak diketahui';
            });
        }

        return view('graduate-theses.lecturer-assignments-index', [
            'user' => $user,
            'programs' => $programs,
            'programId' => $programId,
            'theses' => $theses,
            'showProgramFilter' => in_array($user->role, ['admin', 'pimuniv', 'pimfak'], true),
            'canEdit' => in_array($user->role, ['admin', 'userprodi'], true),
        ]);
    }

    public function lecturerAssignmentShow(Request $request, string $skripsi): View
    {
        $user = $request->user();
        abort_unless(in_array($user->role, ['admin', 'userprodi', 'usertu', 'pimuniv', 'pimfak'], true), 403);
        $programs = $this->programsFor($user);
        $programId = $this->selectedProgram($user, $programs, $request->query('program'));
        $thesis = $this->scopedTheses($user, $programId)
            ->where('thesis.id', $skripsi)->first();
        abort_if($thesis === null, 404);
        $thesisCollection = $this->appendAssignmentNames(collect([$thesis]));
        $thesis = $thesisCollection->first();
        $lecturers = DB::connection('simak_main')->table('s1m4k_masterdosen')
            ->whereNotNull('nama_dosen')->where('nama_dosen', '<>', '')
            ->orderBy('nama_dosen')->get(['id_dosen', 'nama_dosen', 'gelar_depan', 'gelar_belakang']);
        $assignedLecturers = DB::connection('simak_main')->table('s1m4k_masterdosen')
            ->whereIn('id_dosen', array_filter([
                $thesis->pembimbing1, $thesis->pembimbing2, $thesis->penguji1, $thesis->penguji2, $thesis->penguji3,
            ]))->get(['id_dosen', 'nama_dosen', 'gelar_depan', 'gelar_belakang'])->keyBy('id_dosen');
        $assignmentNames = [];
        foreach (array_keys(self::ASSIGNMENT_FIELDS) as $field) {
            $lecturerId = trim((string) ($thesis->{$field} ?? ''));
            $assignmentNames[$field] = $lecturerId === '' || $lecturerId === '-'
                ? '—'
                : ($this->lecturerName($assignedLecturers->get($lecturerId)) ?: 'Nama dosen tidak tersedia');
        }

        return view('graduate-theses.lecturer-assignments-show', [
            'thesis' => $thesis,
            'lecturers' => $lecturers,
            'assignmentNames' => $assignmentNames,
            'canEdit' => in_array($user->role, ['admin', 'userprodi'], true),
            'statusLabel' => self::STATUS_LABELS[(int) $thesis->status] ?? 'Status tidak diketahui',
        ]);
    }

    public function saveLecturerAssignment(Request $request, string $skripsi, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();
        abort_unless(in_array($user->role, ['admin', 'userprodi'], true), 403);
        $validated = $request->validate([
            'pembimbing1' => ['required', 'string', 'max:100'],
            'pembimbing2' => ['nullable', 'string', 'max:100'],
            'penguji1' => ['nullable', 'string', 'max:100'],
            'penguji2' => ['nullable', 'string', 'max:100'],
            'penguji3' => ['nullable', 'string', 'max:100'],
        ]);

        $connection = DB::connection('simak_main');
        $notification = $connection->transaction(function () use ($connection, $user, $skripsi, $validated, $activityLogger): array {
            $thesis = $connection->table('etesis_skripsi')
                ->where('id', $skripsi)->lockForUpdate()->first();
            abort_if($thesis === null, 404);
            $this->assertProgramFacultyScope($user, (object) [
                'id_prodi' => $thesis->id_prodi,
                'id_fakultas' => $thesis->id_fakultas,
            ]);

            $assignments = [];
            foreach (array_keys(self::ASSIGNMENT_FIELDS) as $field) {
                $lecturerId = trim((string) ($validated[$field] ?? ''));
                if ($lecturerId !== '' && ! $connection->table('s1m4k_masterdosen')->where('id_dosen', $lecturerId)->exists()) {
                    throw ValidationException::withMessages([
                        $field => 'Dosen yang dipilih tidak ditemukan.',
                    ]);
                }
                $assignments[$field] = $lecturerId === '' ? null : $lecturerId;
            }
            if ($assignments['pembimbing1'] === null) {
                throw ValidationException::withMessages([
                    'pembimbing1' => 'Pembimbing 1 wajib dipilih.',
                ]);
            }

            $before = array_intersect_key((array) $thesis, $assignments);
            $connection->table('etesis_skripsi')->where('id', $skripsi)->update([
                ...$assignments,
                'status' => (int) $thesis->status === 0 ? 1 : $thesis->status,
                'updated_at' => now(),
            ]);
            $student = $connection->table('s1m4k_mastermhs')
                ->where('id_mahasiswa', $thesis->id_mahasiswa)
                ->where('id_status_mahasiswa', '<>', 'Z')
                ->first(['id_mahasiswa', 'nim', 'nama_mahasiswa', 'email']);
            abort_if($student === null, 404);
            $lecturers = $connection->table('s1m4k_masterdosen')
                ->whereIn('id_dosen', array_values(array_filter($assignments)))
                ->get(['id_dosen', 'nama_dosen', 'gelar_depan', 'gelar_belakang'])->keyBy('id_dosen');
            $assignmentNames = [];
            foreach ($assignments as $field => $lecturerId) {
                $assignmentNames[self::ASSIGNMENT_FIELDS[$field]] = $lecturerId === null
                    ? '—'
                    : $this->lecturerName($lecturers->get($lecturerId));
            }
            $activityLogger->record($user, 'Penugasan Dosen Tesis & Disertasi', 'Menyimpan penugasan dosen', [
                'sebelum' => $before,
                'sesudah' => $assignments,
            ], $skripsi, ['npm' => $student->nim, 'nama' => $student->nama_mahasiswa]);

            return [
                'student' => $student,
                'title' => $thesis->judul,
                'assignments' => $assignmentNames,
                'assignment_ids' => $assignments,
                'previous_assignment_ids' => $before,
                'thesis_id' => $skripsi,
                'program_id' => $thesis->id_prodi,
            ];
        });

        $this->sendThesisNotification(
            trim((string) $notification['student']->email),
            'Penetapan Dosen Pembimbing dan Penguji Tesis & Disertasi',
            (string) $notification['student']->nama_mahasiswa,
            'Program studi telah menetapkan dosen pembimbing dan penguji untuk tesis & disertasi Anda.',
            [
                'NIM' => $notification['student']->nim,
                'Judul tesis & disertasi' => $notification['title'],
                ...$notification['assignments'],
            ],
            null,
            'lecturer-assignment',
            'mahasiswa',
            (string) $notification['student']->id_mahasiswa,
            route('graduate_theses.bimbingan.index')
        );
        $this->notifyAssignedLecturers(
            $notification['student'],
            (string) $notification['title'],
            $notification['assignment_ids'],
            $notification['previous_assignment_ids'],
            (string) $notification['thesis_id'],
        );

        return redirect()->route('graduate_theses.assignments.show', ['skripsi' => $skripsi, 'program' => $notification['program_id']])
            ->with('status', 'Penugasan dosen berhasil disimpan.');
    }

    public function updateAssignedThesisTitle(Request $request, string $skripsi, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();
        abort_unless(in_array($user->role, ['admin', 'userprodi'], true), 403);
        $validated = $request->validate([
            'judul' => ['required', 'string', 'max:2000'],
        ]);

        $connection = DB::connection('simak_main');
        $updated = $connection->transaction(function () use ($connection, $user, $skripsi, $validated, $activityLogger): array {
            $thesis = $connection->table('etesis_skripsi')
                ->where('id', $skripsi)->lockForUpdate()->first();
            abort_if($thesis === null, 404);
            $this->assertProgramFacultyScope($user, (object) [
                'id_prodi' => $thesis->id_prodi,
                'id_fakultas' => $thesis->id_fakultas,
            ]);
            $title = trim($validated['judul']);
            $connection->table('etesis_skripsi')->where('id', $skripsi)->update([
                'judul' => $title,
                'updated_at' => now(),
            ]);
            $connection->table('etesis_judul')->where('id', $thesis->id_proposal)->update(['judul' => $title]);
            $student = $connection->table('s1m4k_mastermhs')->where('id_mahasiswa', $thesis->id_mahasiswa)
                ->first(['id_mahasiswa', 'nim', 'nama_mahasiswa', 'email']);
            $assignedLecturers = $this->assignedIds($thesis)->all();
            $activityLogger->record($user, 'Penugasan Dosen Tesis & Disertasi', 'Memperbarui judul tesis & disertasi', [
                'judul' => ['sebelum' => $thesis->judul, 'sesudah' => $title],
            ], $skripsi, ['npm' => $student->nim ?? '', 'nama' => $student->nama_mahasiswa ?? '']);

            return [
                'program_id' => (string) $thesis->id_prodi,
                'thesis_id' => $skripsi,
                'title' => $title,
                'student' => $student,
                'lecturer_ids' => $assignedLecturers,
            ];
        });

        $this->notifyThesisTitleChanged($updated);

        return redirect()->route('graduate_theses.assignments.show', ['skripsi' => $skripsi, 'program' => $updated['program_id']])
            ->with('status', 'Judul tesis & disertasi berhasil diperbarui.');
    }

    public function bimbinganIndex(Request $request): View
    {
        $user = $request->user();
        abort_unless(in_array($user->role, self::READ_ROLES, true), 403);
        $programs = $this->programsFor($user);
        $programId = $this->selectedProgram($user, $programs, $request->query('program'));
        $theses = collect();
        $supervisorTheses = collect();
        $examinerTheses = collect();

        if ($user->role === 'dosen') {
            $teacherId = $this->teacherId($user);
            $supervisorTheses = $this->scopedTheses($user, $programId)
                ->whereBetween('thesis.status', [1, 5])
                ->where(function (Builder $builder) use ($teacherId): void {
                    $builder->where('thesis.pembimbing1', $teacherId)->orWhere('thesis.pembimbing2', $teacherId);
                })
                ->orderByDesc('thesis.created_at')->get();
            $examinerTheses = $this->scopedTheses($user, $programId)
                ->whereBetween('thesis.status', [1, 5])
                ->where(function (Builder $builder) use ($teacherId): void {
                    $builder->where('thesis.penguji1', $teacherId)->orWhere('thesis.penguji2', $teacherId)->orWhere('thesis.penguji3', $teacherId);
                })
                ->orderByDesc('thesis.created_at')->get();
            $supervisorTheses = $this->appendLastMessage($supervisorTheses, $user);
            $examinerTheses = $this->appendLastMessage($examinerTheses, $user);
            $supervisorTheses = $this->appendAssignmentNames($supervisorTheses);
            $examinerTheses = $this->appendAssignmentNames($examinerTheses);
        } elseif (! in_array($user->role, ['admin', 'pimuniv', 'pimfak'], true) || $programId !== '') {
            $query = $this->scopedTheses($user, $programId);
            if ($user->role === 'mahasiswa') {
                $query->where('thesis.status', '>', 0);
            } else {
                $query->whereBetween('thesis.status', [1, 5]);
            }
            $theses = $query->orderByDesc('thesis.created_at')->get();
            $theses = $this->appendLastMessage($theses, $user);
            $theses = $this->appendAssignmentNames($theses);
        }

        return view('graduate-theses.bimbingan-index', [
            'user' => $user,
            'programs' => $programs,
            'programId' => $programId,
            'showProgramFilter' => in_array($user->role, ['admin', 'pimuniv', 'pimfak'], true),
            'theses' => $theses,
            'supervisorTheses' => $supervisorTheses,
            'examinerTheses' => $examinerTheses,
            'statusLabels' => self::STATUS_LABELS,
        ]);
    }

    public function bimbinganShow(Request $request, string $skripsi): View
    {
        $user = $request->user();
        abort_unless(in_array($user->role, self::READ_ROLES, true), 403);
        $thesis = $this->authorizedThesis($user, $skripsi);
        abort_unless((int) $thesis->status > 0, 404);
        $contacts = $this->contactsFor($user, $thesis);
        $teacherId = $user->role === 'dosen' ? $this->teacherId($user) : '';

        return view('graduate-theses.bimbingan-chat', [
            'user' => $user,
            'thesis' => $thesis,
            'contacts' => $contacts,
            'teacherId' => $teacherId,
            'statusLabel' => self::STATUS_LABELS[(int) $thesis->status] ?? 'Status tidak diketahui',
        ]);
    }

    public function messages(Request $request, string $skripsi): JsonResponse
    {
        $user = $request->user();
        $thesis = $this->authorizedThesis($user, $skripsi);
        abort_unless((int) $thesis->status > 0, 404);
        $contactId = trim((string) $request->query('contact', ''));
        abort_unless($contactId !== '' && $this->assignedIds($thesis)->contains($contactId), 404);
        $messages = DB::connection('simak_main')->table('etesis_bimbingan')
            ->where('id_skripsi', $thesis->id)
            ->where('id_dosen_tujuan', $contactId)
            ->orderByDesc('waktu_kirim')->orderByDesc('id_bimbingan')->limit(200)->get()
            ->reverse()->values();
        $lecturer = DB::connection('simak_main')->table('s1m4k_masterdosen')
            ->where('id_dosen', $contactId)->first(['nama_dosen', 'gelar_depan', 'gelar_belakang']);

        return response()->json([
            'contact' => $contactId,
            'status' => (int) $thesis->status,
            'messages' => $messages->map(fn (object $message): array => [
                'id' => (string) $message->id_bimbingan,
                'sender' => (string) $message->pengirim,
                'sender_name' => $message->pengirim === 'mahasiswa'
                    ? (string) $thesis->nama_mahasiswa
                    : $this->lecturerName($lecturer),
                'content' => ChatMessageText::decodeFromStorage(
                    Utf8Text::repairMojibake((string) $message->pesan)
                ),
                'file_url' => $message->url_berkas ? (string) $message->url_berkas : null,
                'sent_at' => (string) $message->waktu_kirim,
            ])->all(),
        ]);
    }

    public function sendMessage(Request $request, string $skripsi, ActivityLogger $activityLogger): JsonResponse
    {
        $user = $request->user();
        abort_unless(in_array($user->role, ['mahasiswa', 'dosen'], true), 403);
        $validated = $request->validate([
            'contact' => ['required', 'string', 'max:100'],
            'message' => ['required', 'string', 'max:5000'],
            'type' => ['nullable', 'string', 'max:80'],
            'file_url' => ['nullable', 'url', 'max:2000'],
        ]);
        $thesis = $this->authorizedThesis($user, $skripsi);
        abort_unless((int) $thesis->status > 0, 422);
        $contactId = trim($validated['contact']);
        abort_unless($this->assignedIds($thesis)->contains($contactId), 404);
        $type = $user->role === 'dosen' ? trim((string) ($validated['type'] ?? 'biasa')) : 'biasa';
        if ($user->role === 'dosen') {
            abort_unless($contactId === $this->teacherId($user), 403);
            if ($type !== 'biasa') {
                $this->validateApprovalType($thesis, $type, $contactId);
            }
        }

        $messageText = Utf8Text::repairMojibake(trim($validated['message']));
        $storedMessage = $type === 'biasa'
            ? $messageText
            : $type.' | '.$messageText;
        $storageMessage = ChatMessageText::encodeForStorage($storedMessage);
        $connection = DB::connection('simak_main');
        $messageId = (string) Str::uuid();
        $connection->transaction(function () use (
            $connection, $thesis, $contactId, $user, $storageMessage, $validated, $type, $activityLogger, $messageId
        ): void {
            $lockedThesis = $connection->table('etesis_skripsi')->where('id', $thesis->id)->lockForUpdate()->first();
            abort_if($lockedThesis === null, 404);
            abort_unless((int) $lockedThesis->status > 0, 422);
            abort_unless($this->assignedIds($lockedThesis)->contains($contactId), 404);
            if ($type !== 'biasa') {
                abort_unless((int) $lockedThesis->status < 6, 422);
                $this->validateApprovalType($lockedThesis, $type, $this->teacherId($user));
            }
            $connection->table('etesis_bimbingan')->insert([
                'id_bimbingan' => $messageId,
                'id_skripsi' => $thesis->id,
                'id_dosen_tujuan' => $contactId,
                'pengirim' => $user->role === 'mahasiswa' ? 'mahasiswa' : 'dosen',
                'pesan' => $storageMessage,
                'url_berkas' => filled($validated['file_url'] ?? null) ? $validated['file_url'] : null,
                'waktu_kirim' => now(),
            ]);

            if ($type !== 'biasa') {
                $this->applyApprovalTransition($connection, $lockedThesis, $type, $contactId);
            }
            $activityLogger->record($user, 'Bimbingan Tesis & Disertasi', 'Mengirim pesan bimbingan', [
                'jenis' => $type === 'biasa' ? 'pesan' : 'persetujuan',
                'penerima' => $contactId,
            ], $messageId, [
                'nama' => $thesis->nama_mahasiswa,
                'skripsi' => $thesis->id,
            ]);
        });
        $this->notifyMessageRecipient($user, $thesis, $contactId, $storedMessage, $type);

        return response()->json(['message' => 'Pesan berhasil dikirim.', 'id' => $messageId], 201);
    }

    public function examsIndex(Request $request): View
    {
        $user = $request->user();
        abort_unless(in_array($user->role, self::EXAM_ROLES, true), 403);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
        ]);
        $programs = $this->programsFor($user);
        $programId = $this->selectedProgram($user, $programs, $request->query('program'));
        $search = trim((string) ($filters['search'] ?? ''));
        $exams = collect();
        $query = DB::connection('simak_main')->table('etesis_ujian as exam')
            ->join('etesis_skripsi as thesis', 'exam.id_skripsi', '=', 'thesis.id')
            ->join('s1m4k_mastermhs as student', 'thesis.id_mahasiswa', '=', 'student.id_mahasiswa')
            ->leftJoin('s1m4k_masterprodi as program', 'thesis.id_prodi', '=', 'program.id_prodi')
            ->whereIn('program.nama_jenjang_pendidikan', self::ALLOWED_DEGREES)
            ->whereBetween('thesis.status', [2, 6]);
        $this->applyRoleScope($query, $user, $programId);
        if ($user->role === 'dosen') {
            $teacherId = $this->teacherId($user);
            $this->applyAssignedLecturerScope($query, 'thesis', $teacherId);
            $query->whereNotNull('exam.waktu_ujian')->whereNotNull('exam.ruang_ujian')
                ->where('exam.ruang_ujian', '<>', '');
        }
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $term = '%'.$search.'%';
                $builder->where('student.nim', 'like', $term)
                    ->orWhere('student.nama_mahasiswa', 'like', $term)
                    ->orWhere('thesis.judul', 'like', $term)
                    ->orWhere('exam.jenis_ujian', 'like', $term)
                    ->orWhere('exam.ruang_ujian', 'like', $term)
                    ->orWhere('program.nama_program_studi', 'like', $term);
            });
        }
        $exams = $query->select([
            'exam.id_ujian', 'exam.id_skripsi', 'exam.jenis_ujian', 'exam.waktu_ujian', 'exam.ruang_ujian',
            'exam.nilaipembimbing1', 'exam.nilaipembimbing2', 'exam.nilaipenguji1', 'exam.nilaipenguji2', 'exam.nilaipenguji3',
            'thesis.judul', 'thesis.status', 'thesis.id_prodi', 'thesis.id_mahasiswa',
            'student.nim', 'student.nama_mahasiswa', 'program.nama_program_studi',
            'thesis.pembimbing1', 'thesis.pembimbing2', 'thesis.penguji1', 'thesis.penguji2', 'thesis.penguji3',
        ])->orderByDesc('exam.waktu_ujian')->orderBy('student.nama_mahasiswa')->get();
        $exams = $this->appendAssignmentNames($exams);
        $exams->transform(function (object $exam) use ($user): object {
            $scores = array_filter([
                $exam->nilaipembimbing1, $exam->nilaipembimbing2, $exam->nilaipenguji1,
                $exam->nilaipenguji2, $exam->nilaipenguji3,
            ], fn (mixed $score): bool => $this->hasEnteredExamScore($score));
            $exam->has_scores = $scores !== [];
            $teacherId = $user->role === 'dosen' ? $this->teacherId($user) : null;
            $scoreField = $teacherId !== null ? $this->scoreField($exam, $teacherId) : null;
            $exam->lecturer_has_score = $scoreField !== null
                && $this->hasEnteredExamScore($exam->{$scoreField});

            return $exam;
        });

        return view('graduate-theses.exams-index', [
            'user' => $user,
            'programs' => $programs,
            'programId' => $programId,
            'showProgramFilter' => in_array($user->role, ['admin', 'pimuniv', 'pimfak'], true),
            'exams' => $exams,
            'search' => $search,
            'statusLabels' => self::STATUS_LABELS,
        ]);
    }

    public function examShow(Request $request, string $examId): View
    {
        $user = $request->user();
        abort_unless(in_array($user->role, self::EXAM_ROLES, true), 403);
        $exam = $this->authorizedExam($user, $examId);
        $teacherId = $user->role === 'dosen' ? $this->teacherId($user) : null;
        $scoreField = $teacherId !== null ? $this->scoreField($exam, $teacherId) : null;

        return view('graduate-theses.exam-show', [
            'user' => $user,
            'exam' => $exam,
            'canSchedule' => $user->role === 'userprodi',
            'canGrade' => $scoreField !== null,
            'currentScore' => $scoreField !== null ? $exam->{$scoreField} : null,
            'currentNote' => $scoreField !== null ? $exam->{$this->noteField($scoreField)} : null,
            'statusLabel' => self::STATUS_LABELS[(int) $exam->status] ?? 'Status tidak diketahui',
        ]);
    }

    public function scheduleExam(Request $request, string $examId, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->role === 'userprodi', 403);
        $validated = $request->validate([
            'waktu_ujian' => ['required', 'date'],
            'ruang_ujian' => ['required', 'string', 'max:100'],
        ]);
        $exam = $this->authorizedExam($user, $examId);
        $before = ['waktu_ujian' => $exam->waktu_ujian, 'ruang_ujian' => $exam->ruang_ujian];
        $after = [
            'waktu_ujian' => date('Y-m-d H:i:s', strtotime($validated['waktu_ujian'])),
            'ruang_ujian' => trim($validated['ruang_ujian']),
        ];
        DB::connection('simak_main')->table('etesis_ujian')->where('id_ujian', $examId)->update([
            ...$after,
            'update_at' => now(),
        ]);
        $activityLogger->record($user, 'Ujian Tesis & Disertasi', 'Menjadwalkan ujian', [
            'sebelum' => $before,
            'sesudah' => $after,
        ], $examId, ['nama' => $exam->nama_mahasiswa, 'skripsi' => $exam->id_skripsi]);
        $this->notifyExamParticipants($exam, $after['waktu_ujian'], $after['ruang_ujian']);

        return back()->with('status', 'Jadwal ujian berhasil disimpan.');
    }

    public function saveGrade(Request $request, string $examId, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->role === 'dosen', 403);
        $validated = $request->validate([
            'nilai' => ['required', 'integer', 'between:0,100'],
            'catatan' => ['required', 'string', 'max:5000'],
        ]);
        $exam = $this->authorizedExam($user, $examId);
        $teacherId = $this->teacherId($user);
        $scoreField = $this->scoreField($exam, $teacherId);
        abort_if($scoreField === null, 403);
        $noteField = $this->noteField($scoreField);
        $revisionText = Utf8Text::repairMojibake(trim($validated['catatan']));
        $connection = DB::connection('simak_main');
        $before = [$scoreField => $exam->{$scoreField}, $noteField => $exam->{$noteField}];
        $connection->transaction(function () use ($connection, $exam, $scoreField, $noteField, $validated, $revisionText, $user, $teacherId, $activityLogger): void {
            $connection->table('etesis_ujian')->where('id_ujian', $exam->id_ujian)->update([
                $scoreField => (int) $validated['nilai'],
                $noteField => $revisionText,
                'update_at' => now(),
            ]);
            $revision = '[REVISI UJIAN '.strtoupper((string) $exam->jenis_ujian).'] '.$revisionText;
            $existing = $connection->table('etesis_bimbingan')
                ->where('id_skripsi', $exam->id_skripsi)
                ->where('id_dosen_tujuan', $teacherId)
                ->where('pesan', 'like', '[REVISI UJIAN '.strtoupper((string) $exam->jenis_ujian).']%')
                ->first();
            if ($existing) {
                $connection->table('etesis_bimbingan')->where('id_bimbingan', $existing->id_bimbingan)
                    ->update(['pesan' => $revision]);
            } else {
                $connection->table('etesis_bimbingan')->insert([
                    'id_bimbingan' => (string) Str::uuid(),
                    'id_skripsi' => $exam->id_skripsi,
                    'id_dosen_tujuan' => $teacherId,
                    'pengirim' => 'dosen',
                    'pesan' => $revision,
                    'waktu_kirim' => now(),
                ]);
            }
            $activityLogger->record($user, 'Ujian Tesis & Disertasi', 'Menyimpan nilai dan revisi ujian', [
                'nilai' => ['sebelum' => $exam->{$scoreField}, 'sesudah' => (int) $validated['nilai']],
                'catatan_revisi' => true,
            ], (string) $exam->id_ujian, ['nama' => $exam->nama_mahasiswa, 'skripsi' => $exam->id_skripsi]);
        });
        $this->notifyGradeAndRevision($exam, $teacherId, $revisionText);

        return back()->with('status', 'Nilai disimpan dan catatan revisi dikirim ke ruang bimbingan.');
    }

    public function printExam(Request $request, string $examId, QrCodeService $qrCodes): Response
    {
        $user = $request->user();
        abort_unless(in_array($user->role, self::EXAM_ROLES, true), 403);
        $exam = $this->authorizedExam($user, $examId);
        if ($user->role === 'dosen') {
            $scoreField = $this->scoreField($exam, $this->teacherId($user));
            abort_unless($scoreField !== null && $this->hasEnteredExamScore($exam->{$scoreField}), 403);
        }
        $scores = array_filter([
            $exam->nilaipembimbing1, $exam->nilaipembimbing2, $exam->nilaipenguji1,
            $exam->nilaipenguji2, $exam->nilaipenguji3,
        ], fn (mixed $score): bool => $this->hasEnteredExamScore($score));
        abort_if($scores === [], 404);

        $context = $this->examPrintContext($exam);
        $validationCode = $this->examValidationCode($exam, 'NILAI_REVISI_UJIAN');
        $verificationUrl = route('graduate_theses.documents.verify', $validationCode, true);
        $qrPath = $this->createQrCode($verificationUrl, $qrCodes);
        $pdf = new \FPDF('P', 'cm', 'A4');
        $pdf->SetMargins(1, 1.5, 1);
        $pdf->SetAutoPageBreak(true, 1.5);
        $text = fn (string $value): string => iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $value) ?: '';
        $studentName = mb_strtoupper((string) $exam->nama_mahasiswa);
        $examDate = filled($exam->waktu_ujian) ? strtotime((string) $exam->waktu_ujian) : time();
        $englishDay = date('l', $examDate);
        $dayNames = [
            'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu',
        ];
        $dateLine = $dayNames[$englishDay].', '.now()->setTimestamp($examDate)->locale('id')->translatedFormat('d F Y');
        $lecturerRows = $this->examLecturerRows($exam, $context['lecturers']);
        if ($user->role === 'dosen') {
            $teacherId = $this->teacherId($user);
            $lecturerRows = array_values(array_filter($lecturerRows, fn (array $row): bool => $row['id'] === $teacherId));
        }
        abort_if($lecturerRows === [], 404);

        try {
            foreach ($lecturerRows as $row) {
                $pdf->AddPage();
                $this->printThesisLetterhead($pdf, $text, $context['faculty'], $context['program'], $context['logo']);
                $pdf->SetFont('Arial', 'B', 12);
                $pdf->Cell(0, 0.6, $text('NILAI DOSEN '.$row['role']), 0, 1, 'C');
                $pdf->Cell(0, 0.6, $text('UJIAN '.mb_strtoupper((string) $exam->jenis_ujian)), 0, 1, 'C');
                $pdf->SetFont('Arial', '', 10);
                $pdf->Cell(0, 0.6, $text('Ternate, '.$dateLine.' Jam '.date('H:i', $examDate)), 0, 1, 'C');
                $pdf->Ln(0.6);
                $this->pdfLabelValue($pdf, $text, 'Nama', $studentName);
                $this->pdfLabelValue($pdf, $text, 'NPM', (string) $exam->nim);
                $this->pdfLabelValue($pdf, $text, 'Judul', mb_strtoupper((string) $exam->judul), true);
                $pdf->Ln(0.8);
                $score = (int) $row['score'];
                $hasScore = $this->hasEnteredExamScore($row['score']);
                $report = $hasScore ? round($score * 0.4, 2) : '..........';
                $material = $hasScore ? round($score * 0.35, 2) : '..........';
                $presentation = $hasScore ? round($score * 0.25, 2) : '..........';
                foreach ([
                    ['Nilai Penulisan Laporan', $report, '40%'],
                    ['Nilai Penguasaan Materi', $material, '35%'],
                    ['Nilai Presentasi', $presentation, '25%'],
                ] as [$label, $value, $weight]) {
                    $pdf->Cell(5.8, 0.8, $text($label), 0);
                    $pdf->Cell(0.5, 0.8, ':', 0, 0, 'C');
                    $pdf->Cell(4, 0.8, $text((string) $value.' x '.$weight), 0, 0, 'C');
                    $pdf->Cell(1, 0.8, '=', 0, 0, 'C');
                    $pdf->Cell(3, 0.8, $text((string) $value), 'B', 1, 'C');
                }
                $pdf->SetFont('Arial', 'B', 10);
                $pdf->Cell(6.3, 1.2, $text('NILAI TOTAL'), 0);
                $pdf->Cell(3, 1.2, $text($hasScore ? (string) $score : ''), 1, 1, 'C');
                $pdf->Ln(0.5);
                $pdf->SetFont('Arial', '', 10);
                $pdf->Cell(10, 0.6, '', 0, 0);
                $pdf->Cell(6, 0.6, $text($row['role'].','), 0, 1);
                $qrY = $pdf->GetY() + 0.1;
                if ($hasScore) {
                    $pdf->Image($qrPath, 12, $qrY, 2, 2);
                }
                $pdf->Ln(2.2);
                $pdf->Cell(10, 0.5, '', 0, 0);
                $pdf->Cell(6, 0.5, $text($row['name']), 'B', 1);
                $pdf->Cell(10, 0.5, '', 0, 0);
                $pdf->Cell(6, 0.5, $text('NIP. '.($row['nip'] ?: '-')), 0, 1);

                $pdf->AddPage();
                $this->printThesisLetterhead($pdf, $text, $context['faculty'], $context['program'], $context['logo'], true);
                $pdf->SetFont('Arial', 'B', 12);
                $pdf->Cell(0, 0.6, $text('DAFTAR PERBAIKAN UJIAN '.mb_strtoupper((string) $exam->jenis_ujian)), 0, 1, 'C');
                $pdf->Ln(0.8);
                $pdf->SetFont('Arial', '', 10);
                $pdf->Cell(0, 0.6, $text('Dengan ini dinyatakan bahwa pada'), 0, 1);
                $this->pdfLabelValue($pdf, $text, 'Hari / tanggal', mb_strtoupper($dateLine));
                $this->pdfLabelValue($pdf, $text, 'Pukul', date('H:i', $examDate));
                $this->pdfLabelValue($pdf, $text, 'Tempat', mb_strtoupper((string) ($exam->ruang_ujian ?: 'RUANG UJIAN')));
                $pdf->Cell(0, 0.6, $text('telah berlangsung Ujian '.mb_strtoupper((string) $exam->jenis_ujian).' dengan Peserta:'), 0, 1);
                $this->pdfLabelValue($pdf, $text, 'Nama Mahasiswa', $studentName);
                $this->pdfLabelValue($pdf, $text, 'NPM', (string) $exam->nim);
                $this->pdfLabelValue($pdf, $text, 'Judul', mb_strtoupper((string) $exam->judul), true);
                $pdf->Ln(0.5);
                $pdf->Cell(0, 0.6, $text('Dinyatakan HARUS menyelesaikan perbaikan, yaitu:'), 0, 1);
                $note = trim(strip_tags((string) $row['note']));
                if ($note !== '') {
                    $pdf->SetFont('Arial', '', 11);
                    $pdf->MultiCell(0, 0.7, $text($note), 0, 'L');
                } else {
                    $pdf->SetDrawColor(140, 140, 140);
                    for ($line = 0; $line < 5; $line++) {
                        $pdf->Cell(0, 0.8, '', 'B', 1);
                        $pdf->Ln(0.2);
                    }
                }
                $pdf->Ln(0.8);
                $pdf->Cell(10, 0.6, '', 0, 0);
                $pdf->Cell(6, 0.6, $text('Dosen '.$row['role'].','), 0, 1);
                $qrY = $pdf->GetY() + 0.1;
                if ($hasScore || $note !== '') {
                    $pdf->Image($qrPath, 12, $qrY, 2, 2);
                }
                $pdf->Ln(2.2);
                $pdf->Cell(10, 0.5, '', 0, 0);
                $pdf->Cell(6, 0.5, $text($row['name']), 'B', 1);
                $pdf->Cell(10, 0.5, '', 0, 0);
                $pdf->Cell(6, 0.5, $text('NIP. '.($row['nip'] ?: '-')), 0, 1);
            }

            return response($pdf->Output('S'), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="Nilai-Revisi-'.$exam->nim.'.pdf"',
            ]);
        } finally {
            if (is_file($qrPath)) {
                unlink($qrPath);
            }
        }
    }

    public function printMinutes(Request $request, string $examId, QrCodeService $qrCodes): Response
    {
        $user = $request->user();
        abort_unless(in_array($user->role, self::EXAM_ROLES, true), 403);
        $exam = $this->authorizedExam($user, $examId);
        if ($user->role === 'dosen') {
            $scoreField = $this->scoreField($exam, $this->teacherId($user));
            abort_unless($scoreField !== null && $this->hasEnteredExamScore($exam->{$scoreField}), 403);
        }
        abort_unless(filled($exam->waktu_ujian) && filled($exam->ruang_ujian), 404);
        $context = $this->examPrintContext($exam);
        $validationCode = $this->examValidationCode($exam);
        $qrPath = $this->createQrCode(route('graduate_theses.documents.verify', $validationCode, true), $qrCodes);
        $pdf = new \FPDF('P', 'cm', 'Legal');
        $pdf->SetLeftMargin(2);
        $pdf->SetRightMargin(2);
        $text = fn (string $value): string => iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $value) ?: '';
        $studentName = ucwords(mb_strtolower((string) $exam->nama_mahasiswa));
        $examDate = strtotime((string) $exam->waktu_ujian);
        $scores = array_filter([
            $exam->nilaipembimbing1, $exam->nilaipembimbing2, $exam->nilaipenguji1,
            $exam->nilaipenguji2, $exam->nilaipenguji3,
        ], fn (mixed $score): bool => $this->hasEnteredExamScore($score));
        $average = round(array_sum(array_map(
            fn (mixed $score): int => max(0, (int) $score),
            $scores
        )) / max(1, count($scores)), 2);
        $letterGrade = match (true) {
            $average >= 81 => 'A', $average >= 71 => 'AB', $average >= 66 => 'B',
            $average >= 61 => 'BC', $average >= 56 => 'C', $average >= 41 => 'D', default => 'E',
        };
        $result = $average >= 56 ? 'LULUS' : 'TIDAK LULUS';

        try {
            $pdf->AddPage();
            $this->printThesisLetterhead($pdf, $text, $context['faculty'], $context['program'], $context['logo']);
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(0, 0.5, $text('BERITA ACARA UJIAN '.mb_strtoupper((string) $exam->jenis_ujian)), 0, 1, 'C');
            $pdf->Ln(0.5);
            $pdf->SetFont('Arial', '', 10);
            $dateText = now()->setTimestamp($examDate)->locale('id')->translatedFormat('d F Y');
            $pdf->MultiCell(0, 0.6, $text('Pada hari ini, tanggal '.$dateText.', telah diselenggarakan Ujian '.mb_strtoupper((string) $exam->jenis_ujian).' bagi mahasiswa/i '.app(InstitutionSettings::class)->institutionName().':'), 0, 'J');
            $pdf->Ln(0.3);
            $this->pdfLabelValue($pdf, $text, 'Nama Mahasiswa', $studentName);
            $this->pdfLabelValue($pdf, $text, 'N P M', (string) $exam->nim);
            $this->pdfLabelValue($pdf, $text, 'Program Studi', (string) $exam->nama_program_studi);
            $this->pdfLabelValue($pdf, $text, 'Judul Tesis & Disertasi', (string) $exam->judul, true);
            $pdf->Ln(0.5);
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->MultiCell(0, 0.6, $text("Setelah mempertimbangkan hasil evaluasi dan penilaian dari Tim Penguji, mahasiswa tersebut dinyatakan:\n\n".$result.' dengan Nilai Rata-rata: '.$average.' ('.$letterGrade.')'), 0, 'L');
            $pdf->Ln(0.8);
            $pdf->Cell(0, 0.6, $text('SUSUNAN TIM PENGUJI'), 0, 1, 'C');
            foreach ([[1, 'No'], [7, 'Nama Dosen'], [4.5, 'Jabatan / Peran'], [5, 'Tanda Tangan Validasi']] as [$width, $label]) {
                $pdf->Cell($width, 0.8, $text($label), 1, 0, 'C');
            }
            $pdf->Ln();
            $pdf->SetFont('Arial', '', 9);
            $rows = $this->examLecturerRows($exam, $context['lecturers']);
            foreach ($rows as $index => $row) {
                $startY = $pdf->GetY();
                $pdf->Cell(1, 2.5, (string) ($index + 1), 0, 0, 'C');
                $pdf->SetXY(3.2, $startY + 0.5);
                $pdf->MultiCell(6.6, 0.5, $text($row['name']."\nNIP. ".($row['nip'] ?: '-')), 0, 'L');
                $pdf->SetXY(10, $startY);
                $pdf->Cell(4.5, 2.5, $text($row['role']), 0, 0, 'C');
                if ($this->hasEnteredExamScore($row['score'])) {
                    $pdf->Image($qrPath, 15.5, $startY + 0.25, 2, 2);
                } else {
                    $pdf->SetXY(14.5, $startY + 1);
                    $pdf->SetTextColor(150, 150, 150);
                    $pdf->Cell(5, 0.5, $text('(Belum Memberikan Nilai)'), 0, 0, 'C');
                    $pdf->SetTextColor(0, 0, 0);
                }
                $pdf->SetY($startY + 2.5);
            }
            $pdf->Ln(1);
            $pdf->Cell(10, 0.5, '', 0, 0);
            $pdf->Cell(5, 0.5, $text('Ternate, '.$dateText), 0, 1);
            $pdf->Cell(10, 0.5, '', 0, 0);
            $pdf->Cell(5, 0.5, $text('Koordinator Program Studi,'), 0, 1);
            $pdf->Image($qrPath, 13, $pdf->GetY() + 0.2, 2, 2);
            $pdf->Ln(2.5);
            $pdf->Cell(10, 0.5, '', 0, 0);
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(7, 0.5, $text($context['chair_name']), 0, 1);
            $pdf->Cell(10, 0.5, '', 0, 0);
            $pdf->SetFont('Arial', '', 10);
            $pdf->Cell(7, 0.5, $text('NIP. '.$context['chair_nip']), 0, 1);

            return response($pdf->Output('S'), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="BA-Ujian-'.$exam->nim.'.pdf"',
            ]);
        } finally {
            if (is_file($qrPath)) {
                unlink($qrPath);
            }
        }
    }

    public function verifyExamDocument(string $code): Response
    {
        $document = DB::connection('simak_main')->table('validasi_dok as validation')
            ->join('etesis_skripsi as thesis', 'validation.id_dokumen', '=', 'thesis.id')
            ->join('s1m4k_mastermhs as student', 'thesis.id_mahasiswa', '=', 'student.id_mahasiswa')
            ->join('s1m4k_masterprodi as program', 'thesis.id_prodi', '=', 'program.id_prodi')
            ->leftJoin('etesis_ujian as exam', function (Builder $join): void {
                $join->on('thesis.id', '=', 'exam.id_skripsi')
                    ->whereRaw("UPPER(exam.jenis_ujian) = CASE
                        WHEN validation.jenis_dokumen LIKE 'BERITA_ACARA_UJIAN_%'
                        THEN REPLACE(validation.jenis_dokumen, 'BERITA_ACARA_UJIAN_', '')
                        ELSE REPLACE(validation.jenis_dokumen, 'NILAI_REVISI_UJIAN_', '')
                    END");
            })
            ->where('validation.id', $code)
            ->whereIn('program.nama_jenjang_pendidikan', self::ALLOWED_DEGREES)
            ->where(function (Builder $query): void {
                $query->where('validation.jenis_dokumen', 'like', 'BERITA_ACARA_UJIAN_%')
                    ->orWhere('validation.jenis_dokumen', 'like', 'NILAI_REVISI_UJIAN_%');
            })
            ->select([
                'validation.id', 'validation.jenis_dokumen', 'validation.created_at',
                'thesis.judul', 'student.nim', 'student.nama_mahasiswa',
                'program.nama_program_studi', 'exam.waktu_ujian', 'exam.jenis_ujian',
            ])->first();
        $valid = $document !== null && $document->jenis_ujian !== null;

        return response()->view('graduate-theses.document-verification', compact('document', 'valid'), $valid ? 200 : 404);
    }

    private function createQrCode(string $url, QrCodeService $qrCodes): string
    {
        return $qrCodes->generate($url, 'simak-thesis-qr-');
    }

    private function examValidationCode(object $exam, string $documentPrefix = 'BERITA_ACARA_UJIAN'): string
    {
        $connection = DB::connection('simak_main');
        $documentType = $documentPrefix.'_'.mb_strtoupper((string) $exam->jenis_ujian);
        $existing = $connection->table('validasi_dok')
            ->where('id_dokumen', $exam->id_skripsi)
            ->where('jenis_dokumen', $documentType)
            ->orderBy('created_at')
            ->first(['id']);
        if ($existing !== null) {
            return (string) $existing->id;
        }

        $code = (string) Str::uuid();
        $connection->table('validasi_dok')->insert([
            'id' => $code,
            'id_dokumen' => (string) $exam->id_skripsi,
            'jenis_dokumen' => $documentType,
            'created_at' => now(),
        ]);

        return $code;
    }

    private function examPrintContext(object $exam): array
    {
        $program = DB::connection('simak_main')->table('s1m4k_masterprodi as program')
            ->leftJoin('s1m4k_masterfakultas as faculty', 'program.id_fakultas', '=', 'faculty.id_fakultas')
            ->leftJoin('s1m4k_masterdosen as chair', 'program.ketua_prodi', '=', 'chair.id_dosen')
            ->where('program.id_prodi', $exam->id_prodi)
            ->first([
                'program.nama_program_studi', 'program.id_fakultas', 'faculty.nama_fakultas',
                'chair.nama_dosen as chair_name', 'chair.gelar_depan as chair_title',
                'chair.gelar_belakang as chair_suffix', 'chair.nip as chair_nip',
            ]);
        abort_if($program === null, 404, 'Data program studi tidak ditemukan untuk dokumen ujian.');
        $assigned = $this->assignedIds($exam);
        $lecturers = $assigned->isEmpty()
            ? collect()
            : DB::connection('simak_main')->table('s1m4k_masterdosen')
                ->whereIn('id_dosen', $assigned)
                ->get(['id_dosen', 'nama_dosen', 'gelar_depan', 'gelar_belakang', 'nip'])
                ->keyBy('id_dosen');
        $chairName = LecturerName::format(
            filled($program->chair_name) ? ucwords(mb_strtolower($program->chair_name)) : null,
            $program->chair_title,
            $program->chair_suffix,
        );

        return [
            'program' => (string) $program->nama_program_studi,
            'faculty' => (string) ($program->nama_fakultas ?? ''),
            'lecturers' => $lecturers,
            'chair_name' => $chairName !== '' ? $chairName : 'Ketua Program Studi',
            'chair_nip' => filled($program->chair_nip) ? (string) $program->chair_nip : '-',
            'logo' => app(InstitutionSettings::class)->logoFilePath(),
        ];
    }

    private function examLecturerRows(object $exam, Collection $lecturers): array
    {
        $rows = [];
        foreach (self::ASSIGNMENT_FIELDS as $field => $label) {
            $id = trim((string) $exam->{$field});
            if ($id === '' || $id === '-') {
                continue;
            }
            $lecturer = $lecturers->get($id);
            $name = $lecturer === null ? '' : $this->lecturerName($lecturer);
            $suffix = match ($field) {
                'pembimbing1' => 'I', 'pembimbing2' => 'II', 'penguji1' => 'I',
                'penguji2' => 'II', 'penguji3' => 'III',
            };
            $role = str_starts_with($field, 'pembimbing') ? 'Pembimbing '.$suffix : 'Penguji '.$suffix;
            $scoreField = match ($field) {
                'pembimbing1' => 'nilaipembimbing1', 'pembimbing2' => 'nilaipembimbing2',
                'penguji1' => 'nilaipenguji1', 'penguji2' => 'nilaipenguji2', 'penguji3' => 'nilaipenguji3',
            };
            $noteField = $this->noteField($scoreField);
            $rows[] = [
                'id' => $id,
                'name' => $name !== '' ? $name : 'Dosen belum tersedia',
                'nip' => (string) ($lecturer->nip ?? ''),
                'role' => $role,
                'score' => $exam->{$scoreField},
                'note' => $exam->{$noteField},
            ];
        }

        return $rows;
    }

    private function printThesisLetterhead(
        \FPDF $pdf,
        callable $text,
        string $faculty,
        string $program,
        string $logoPath,
        bool $minutes = false
    ): void {
        if (is_file($logoPath)) {
            $pdf->Image($logoPath, $minutes ? 2.5 : 2, $minutes ? 0.8 : 1, 2.5, 2.5);
        }
        if ($minutes) {
            $pdf->SetFont('Arial', 'B', 11);
            $pdf->Cell(3.5);
            foreach (app(InstitutionSettings::class)->ministryNameLines() as $ministryLine) {
                $pdf->Cell(14.5, 0.6, $text($ministryLine), 0, 1, 'C');
            }
            $pdf->SetFont('Arial', 'B', 13);
        } else {
            $pdf->SetFont('Arial', 'B', 12);
        }
        $pdf->Cell(3.5);
        $pdf->Cell(14.5, 0.6, $text(mb_strtoupper(app(InstitutionSettings::class)->institutionName())), 0, 1, 'C');
        $pdf->SetFont('Arial', 'B', $minutes ? 11 : 12);
        $pdf->Cell(3.5);
        $pdf->Cell(14.5, 0.6, $text(mb_strtoupper($faculty)), 0, 1, 'C');
        if (! $minutes && $program !== '') {
            $pdf->Cell(3.5);
            $pdf->Cell(14.5, 0.6, $text('PROGRAM STUDI '.mb_strtoupper($program)), 0, 1, 'C');
        }
        if ($minutes) {
            $pdf->SetLineWidth(0.05);
            $pdf->Line(2, 3.8, 19.5, 3.8);
            $pdf->SetLineWidth(0.01);
            $pdf->Line(2, 3.9, 19.5, 3.9);
        }
        $pdf->Ln($minutes ? 1.2 : 1.5);
    }

    private function pdfLabelValue(\FPDF $pdf, callable $text, string $label, string $value, bool $multiline = false): void
    {
        $pdf->Cell(4, 0.6, $text($label), 0, 0, 'L');
        $pdf->Cell(0.5, 0.6, ':', 0, 0, 'C');
        if ($multiline) {
            $pdf->MultiCell(13, 0.6, $text($value), 0, 'J');

            return;
        }
        $pdf->Cell(10, 0.6, $text($value), 0, 1, 'L');
    }

    public function uploadIndex(Request $request): View
    {
        abort_unless($request->user()->role === 'admin', 403);

        return view('graduate-theses.upload');
    }

    public function downloadTemplate(Request $request)
    {
        abort_unless($request->user()->role === 'admin', 403);
        $path = resource_path('templates/format_etesis.xlsx');
        abort_unless(is_file($path), 404, 'Template E-Tesis & Disertasi tidak tersedia.');

        return response()->download($path, 'format_etesis.xlsx');
    }

    public function upload(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->role === 'admin', 403);
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx', 'max:5120'],
        ]);
        try {
            $reader = new Xlsx;
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($validated['file']->getRealPath());
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['file' => 'File Excel tidak dapat dibaca. Periksa format template.']);
        }

        $connection = DB::connection('simak_main');
        $processed = 0;
        $skipped = 0;
        $connection->transaction(function () use ($spreadsheet, $connection, &$processed, &$skipped): void {
            foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
                for ($rowNumber = 3; $rowNumber <= $sheet->getHighestDataRow(); $rowNumber++) {
                    $cell = fn (string $column): string => trim((string) $sheet->getCell($column.$rowNumber)->getFormattedValue());
                    $npm = preg_replace('/\s+/', '', $cell('B')) ?? '';
                    $title = $cell('C');
                    if ($npm === '' || $title === '') {
                        continue;
                    }
                    $student = $connection->table('s1m4k_mastermhs')->where('nim', $npm)
                        ->first(['id_mahasiswa', 'id_prodi', 'id_fakultas']);
                    $status = filter_var($cell('I'), FILTER_VALIDATE_INT);
                    if ($student === null || $status === false || $status < 0 || $status > 6) {
                        $skipped++;

                        continue;
                    }

                    $titleId = $connection->table('etesis_judul')
                        ->where('id_mahasiswa', $student->id_mahasiswa)
                        ->where('id_prodi', $student->id_prodi)->value('id');
                    if (! $titleId) {
                        $titleId = (string) Str::uuid();
                        $connection->table('etesis_judul')->insert([
                            'id' => $titleId,
                            'id_fakultas' => $student->id_fakultas,
                            'id_prodi' => $student->id_prodi,
                            'id_mahasiswa' => $student->id_mahasiswa,
                            'judul' => $title,
                            'deskripsi' => $title,
                            'tanggal_usul' => now()->toDateString(),
                            'status' => 1,
                        ]);
                    }

                    $lecturerIds = [];
                    foreach (['D', 'E', 'F', 'G', 'H'] as $column) {
                        $nidn = $cell($column);
                        $lecturerIds[] = $nidn === '' || $nidn === '-' || $nidn === '0'
                            ? null
                            : $connection->table('s1m4k_masterdosen')->where('nidn', $nidn)->value('id_dosen');
                    }
                    $values = [
                        'judul' => $title,
                        'pembimbing1' => $lecturerIds[0],
                        'pembimbing2' => $lecturerIds[1],
                        'penguji1' => $lecturerIds[2],
                        'penguji2' => $lecturerIds[3],
                        'penguji3' => $lecturerIds[4],
                        'status' => $status,
                        'updated_at' => now(),
                    ];
                    $thesis = $connection->table('etesis_skripsi')
                        ->where('id_mahasiswa', $student->id_mahasiswa)
                        ->where('id_prodi', $student->id_prodi)->first(['id']);
                    if ($thesis) {
                        $thesisId = (string) $thesis->id;
                        $connection->table('etesis_skripsi')->where('id', $thesisId)->update($values);
                    } else {
                        $thesisId = (string) Str::uuid();
                        $connection->table('etesis_skripsi')->insert([
                            'id' => $thesisId,
                            'id_fakultas' => $student->id_fakultas,
                            'id_prodi' => $student->id_prodi,
                            'id_mahasiswa' => $student->id_mahasiswa,
                            'id_proposal' => $titleId,
                            ...$values,
                            'created_at' => now(),
                        ]);
                    }
                    $examType = match ((int) $status) {
                        2 => 'proposal',
                        4 => 'hasil',
                        5 => 'tesis & disertasi',
                        default => null,
                    };
                    if ($examType !== null && ! $connection->table('etesis_ujian')
                        ->where('id_skripsi', $thesisId)->where('jenis_ujian', $examType)->exists()) {
                        $connection->table('etesis_ujian')->insert([
                            'id_ujian' => (string) Str::uuid(),
                            'id_skripsi' => $thesisId,
                            'jenis_ujian' => $examType,
                            'accpembimbing1' => 0,
                            'accpembimbing2' => 0,
                            'accpenguji1' => 0,
                            'accpenguji2' => 0,
                            'accpenguji3' => 0,
                            'create_at' => now(),
                            'update_at' => now(),
                        ]);
                    }
                    $processed++;
                }
            }
        });
        $activityLogger->record($user, 'Upload E-Tesis & Disertasi', 'Mengimpor data tesis & disertasi', [
            'diproses' => $processed,
            'dilewati' => $skipped,
        ]);

        return back()->with('status', "Import selesai: {$processed} baris diproses, {$skipped} baris dilewati.");
    }

    private function dashboardDataFor(object $user, string $programId): array
    {
        $query = $this->scopedTheses($user, $programId);
        $theses = $query->orderByDesc('thesis.created_at')->get();
        $statusCounts = array_fill_keys(array_keys(self::STATUS_LABELS), 0);
        foreach ($theses as $thesis) {
            $statusCounts[(int) $thesis->status] = ($statusCounts[(int) $thesis->status] ?? 0) + 1;
        }
        $ids = $theses->where('status', '<', 6)->pluck('id')->all();
        $lastMessages = $ids === [] ? collect() : DB::connection('simak_main')->table('etesis_bimbingan')
            ->whereIn('id_skripsi', $ids)
            ->when($user->role === 'dosen', fn (Builder $builder) => $builder->where('id_dosen_tujuan', $this->teacherId($user)))
            ->orderByDesc('waktu_kirim')->orderByDesc('id_bimbingan')->get()->unique('id_skripsi')->keyBy('id_skripsi');
        $stagnant = $theses->filter(function (object $thesis) use ($lastMessages): bool {
            if ((int) $thesis->status < 1 || (int) $thesis->status > 5) {
                return false;
            }
            $last = $lastMessages->get($thesis->id);
            $lastActivity = Carbon::parse($last?->waktu_kirim ?? $thesis->created_at);

            return $lastActivity->lt(now()->subDays(7));
        })->sortBy(fn (object $thesis): Carbon => Carbon::parse(
            $lastMessages->get($thesis->id)?->waktu_kirim ?? $thesis->created_at
        ))->map(function (object $thesis) use ($lastMessages): array {
            $last = $lastMessages->get($thesis->id);
            $lastActivity = Carbon::parse($last?->waktu_kirim ?? $thesis->created_at);

            return [
                'id' => (string) $thesis->id,
                'student' => $thesis->nama_mahasiswa,
                'nim' => $thesis->nim,
                'title' => $thesis->judul,
                'status' => self::STATUS_LABELS[(int) $thesis->status] ?? 'Status tidak diketahui',
                'last_message_at' => $last?->waktu_kirim,
                'stagnation_level' => $lastActivity->lt(now()->subMonth()) ? 'month' : 'week',
            ];
        })->values()->all();

        $titleProgramIds = $programId !== '' ? [$programId] : $this->programIdsFor($user);
        $titleStatuses = [0 => 'Belum diverifikasi', 1 => 'Diterima', 2 => 'Ditolak'];
        $titleProposals = $user->role === 'dosen' || $titleProgramIds === []
            ? collect()
            : DB::connection('simak_main')->table('etesis_judul as proposal')
                ->join('s1m4k_mastermhs as student', 'proposal.id_mahasiswa', '=', 'student.id_mahasiswa')
                ->leftJoin('s1m4k_masterprodi as program', 'proposal.id_prodi', '=', 'program.id_prodi')
                ->whereIn('proposal.id_prodi', $titleProgramIds)
                ->orderByDesc('proposal.tanggal_usul')
                ->get([
                    'proposal.id', 'proposal.id_prodi', 'proposal.judul', 'proposal.status', 'proposal.tanggal_usul',
                    'student.nim', 'student.nama_mahasiswa', 'program.nama_program_studi',
                ])
                ->map(function (object $proposal) use ($titleStatuses): object {
                    $proposal->status_label = $titleStatuses[(int) $proposal->status] ?? 'Status tidak diketahui';

                    return $proposal;
                });

        return [
            'total' => $theses->count(),
            'judul' => DB::connection('simak_main')->table('etesis_judul')
                ->whereIn('id_prodi', $titleProgramIds)
                ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status')->all(),
            'title_proposals' => $titleProposals,
            'status_labels' => self::STATUS_LABELS,
            'status_counts' => $statusCounts,
            'stagnant' => $stagnant,
            'workload' => $this->lecturerWorkload($theses->where('status', '<', 6), $user->role === 'dosen' ? $this->teacherId($user) : null),
        ];
    }

    private function lecturerWorkload(Collection $theses, ?string $onlyLecturerId = null): array
    {
        $ids = $theses->pluck('id')->all();
        if ($ids === []) {
            return [];
        }
        $assignments = DB::connection('simak_main')->table('etesis_skripsi')
            ->whereIn('id', $ids)->get(['pembimbing1', 'pembimbing2', 'penguji1', 'penguji2', 'penguji3']);
        $workload = [];
        foreach ($assignments as $assignment) {
            foreach (self::ASSIGNMENT_FIELDS as $field => $label) {
                $id = trim((string) $assignment->{$field});
                if ($id === '' || $id === '-') {
                    continue;
                }
                if ($onlyLecturerId !== null && $id !== $onlyLecturerId) {
                    continue;
                }
                $workload[$id][$label] = ($workload[$id][$label] ?? 0) + 1;
            }
        }
        $lecturers = DB::connection('simak_main')->table('s1m4k_masterdosen')
            ->whereIn('id_dosen', array_keys($workload))
            ->get(['id_dosen', 'nama_dosen', 'gelar_depan', 'gelar_belakang'])->keyBy('id_dosen');

        return collect($workload)->map(fn (array $counts, string $id): array => [
            'id' => $id,
            'name' => $this->lecturerName($lecturers->get($id)),
            'supervisor' => ($counts['Pembimbing 1'] ?? 0) + ($counts['Pembimbing 2'] ?? 0),
            'examiner' => ($counts['Penguji 1'] ?? 0) + ($counts['Penguji 2'] ?? 0) + ($counts['Penguji 3'] ?? 0),
        ])->sortByDesc(fn (array $row): int => $row['supervisor'] + $row['examiner'])->values()->all();
    }

    private function appendLastMessage(Collection $theses, object $user): Collection
    {
        $ids = $theses->pluck('id')->all();
        if ($ids === []) {
            return $theses;
        }
        $latest = DB::connection('simak_main')->table('etesis_bimbingan')
            ->whereIn('id_skripsi', $ids)
            ->when($user->role === 'dosen', fn (Builder $builder) => $builder->where('id_dosen_tujuan', $this->teacherId($user)))
            ->orderByDesc('waktu_kirim')->orderByDesc('id_bimbingan')->get()->unique('id_skripsi')->keyBy('id_skripsi');

        $theses->transform(function (object $thesis) use ($latest, $user): object {
            $last = $latest->get($thesis->id);
            $thesis->last_message_at = $last?->waktu_kirim;
            $thesis->unread = $last !== null && (($user->role === 'mahasiswa' && $last->pengirim === 'dosen')
                || ($user->role === 'dosen' && $last->pengirim === 'mahasiswa'));

            return $thesis;
        });

        return $theses;
    }

    private function appendAssignmentNames(Collection $theses): Collection
    {
        $lecturerIds = $theses->flatMap(function (object $thesis): array {
            return array_map(
                static fn (string $field): string => trim((string) ($thesis->{$field} ?? '')),
                array_keys(self::ASSIGNMENT_FIELDS)
            );
        })->filter(fn (mixed $id): bool => (string) $id !== '' && (string) $id !== '-')->unique()->values()->all();
        $lecturers = $lecturerIds === [] ? collect() : DB::connection('simak_main')->table('s1m4k_masterdosen')
            ->whereIn('id_dosen', $lecturerIds)
            ->get(['id_dosen', 'nama_dosen', 'gelar_depan', 'gelar_belakang'])->keyBy('id_dosen');

        return $theses->transform(function (object $thesis) use ($lecturers): object {
            $namesFor = function (array $fields) use ($thesis, $lecturers): string {
                $names = collect($fields)->map(function (string $field) use ($thesis, $lecturers): ?string {
                    $lecturerId = trim((string) ($thesis->{$field} ?? ''));

                    return $lecturerId === '' || $lecturerId === '-'
                        ? null
                        : $this->lecturerName($lecturers->get($lecturerId));
                })->filter()->unique()->values();

                return $names->isEmpty() ? '—' : $names->implode(', ');
            };
            $thesis->pembimbing_names = $namesFor(['pembimbing1', 'pembimbing2']);
            $thesis->penguji_names = $namesFor(['penguji1', 'penguji2', 'penguji3']);

            return $thesis;
        });
    }

    private function programsFor(object $user): Collection
    {
        $query = DB::connection('simak_main')->table('s1m4k_masterprodi as program')
            ->leftJoin('s1m4k_masterfakultas as faculty', 'program.id_fakultas', '=', 'faculty.id_fakultas')
            ->whereIn('program.nama_jenjang_pendidikan', self::ALLOWED_DEGREES);
        if ($user->role === 'pimfak') {
            $facultyId = trim((string) data_get($user, 'id_fakultas'));
            $facultyId === '' ? $query->whereRaw('1 = 0') : $query->where('program.id_fakultas', $facultyId);
        } elseif (in_array($user->role, ['userprodi', 'usertu'], true)) {
            $programId = trim((string) data_get($user, 'id_prodi'));
            $programId === '' ? $query->whereRaw('1 = 0') : $query->where('program.id_prodi', $programId);
        } elseif ($user->role === 'dosen') {
            $teacherId = $this->teacherId($user);
            $query->whereIn('program.id_prodi', function ($subquery) use ($teacherId): void {
                $subquery->from('etesis_skripsi as thesis')->select('thesis.id_prodi')->distinct()
                    ->where(function (Builder $builder) use ($teacherId): void {
                        $this->applyAssignedLecturerScope($builder, 'thesis', $teacherId);
                    });
            });
        }

        return $query->orderBy('program.nama_program_studi')->get([
            'program.id_prodi', 'program.id_fakultas', 'program.nama_program_studi',
            'program.nama_jenjang_pendidikan', 'faculty.nama_fakultas',
        ]);
    }

    private function selectedProgram(object $user, Collection $programs, ?string $requested): string
    {
        if (in_array($user->role, ['userprodi', 'usertu'], true)) {
            $programId = trim((string) data_get($user, 'id_prodi'));
            abort_unless($programId !== '' && $programs->contains('id_prodi', $programId), 403);

            return $programId;
        }
        $programId = trim((string) $requested);
        if ($programId !== '') {
            abort_unless($programs->contains('id_prodi', $programId), 404);
        }

        return $programId;
    }

    private function scopedTitleProposal(object $user, string $proposalId): object
    {
        $proposal = DB::connection('simak_main')->table('etesis_judul as proposal')
            ->join('s1m4k_mastermhs as student', 'proposal.id_mahasiswa', '=', 'student.id_mahasiswa')
            ->leftJoin('s1m4k_masterprodi as program', 'proposal.id_prodi', '=', 'program.id_prodi')
            ->leftJoin('s1m4k_masterfakultas as faculty', 'proposal.id_fakultas', '=', 'faculty.id_fakultas')
            ->where('proposal.id', $proposalId)
            ->whereIn('program.nama_jenjang_pendidikan', self::ALLOWED_DEGREES)
            ->where('student.id_status_mahasiswa', '<>', 'Z')
            ->first([
                'proposal.*', 'student.nim', 'student.nama_mahasiswa', 'student.email',
                'program.nama_program_studi', 'faculty.nama_fakultas',
            ]);
        abort_if($proposal === null, 404);
        $this->assertProgramFacultyScope($user, $proposal);
        $proposal->status_label = $this->titleStatusLabel((int) $proposal->status);

        return $proposal;
    }

    private function assertProgramFacultyScope(object $user, object $proposal): void
    {
        if (in_array($user->role, ['userprodi', 'usertu'], true)) {
            abort_unless((string) $proposal->id_prodi === trim((string) data_get($user, 'id_prodi')), 404);
        } elseif ($user->role === 'pimfak') {
            abort_unless((string) $proposal->id_fakultas === trim((string) data_get($user, 'id_fakultas')), 404);
        } else {
            abort_unless(in_array($user->role, ['admin', 'pimuniv'], true), 403);
        }
    }

    private function titleStatusLabel(int $status): string
    {
        return [
            0 => 'Sedang diverifikasi',
            1 => 'Disetujui',
            2 => 'Ditolak / revisi',
        ][$status] ?? 'Status tidak diketahui';
    }

    private function programIdsFor(object $user): array
    {
        return $this->programsFor($user)->pluck('id_prodi')->map(fn (mixed $id): string => (string) $id)->all();
    }

    private function scopedTheses(object $user, string $programId = ''): Builder
    {
        $query = DB::connection('simak_main')->table('etesis_skripsi as thesis')
            ->join('s1m4k_mastermhs as student', 'thesis.id_mahasiswa', '=', 'student.id_mahasiswa')
            ->leftJoin('s1m4k_masterprodi as program', 'thesis.id_prodi', '=', 'program.id_prodi')
            ->leftJoin('s1m4k_masterfakultas as faculty', 'program.id_fakultas', '=', 'faculty.id_fakultas')
            ->whereIn('program.nama_jenjang_pendidikan', self::ALLOWED_DEGREES)
            ->select([
                'thesis.*', 'student.nim', 'student.nama_mahasiswa', 'student.id_status_mahasiswa',
                'program.nama_program_studi', 'program.nama_jenjang_pendidikan', 'program.id_fakultas', 'faculty.nama_fakultas',
            ]);
        $this->applyRoleScope($query, $user, $programId);

        return $query;
    }

    private function applyRoleScope(Builder $query, object $user, string $programId = ''): void
    {
        if ($user->role === 'mahasiswa') {
            $query->where('thesis.id_mahasiswa', (string) data_get($user, 'legacy_id'));
        } elseif ($user->role === 'dosen') {
            $this->applyAssignedLecturerScope($query, 'thesis', $this->teacherId($user));
        } elseif (in_array($user->role, ['userprodi', 'usertu'], true)) {
            $scopeId = trim((string) data_get($user, 'id_prodi'));
            abort_unless($scopeId !== '', 403);
            $query->where('thesis.id_prodi', $scopeId);
        } elseif ($user->role === 'pimfak') {
            $facultyId = trim((string) data_get($user, 'id_fakultas'));
            abort_unless($facultyId !== '', 403);
            $query->where('program.id_fakultas', $facultyId);
        } elseif (! in_array($user->role, ['admin', 'pimuniv'], true)) {
            abort(403);
        }
        if ($programId !== '') {
            $query->where('thesis.id_prodi', $programId);
        }
    }

    private function authorizedThesis(object $user, string $skripsi): object
    {
        $thesis = $this->scopedTheses($user)->where('thesis.id', $skripsi)->first();
        abort_if($thesis === null, 404);

        return $thesis;
    }

    private function authorizedExam(object $user, string $examId): object
    {
        $exam = DB::connection('simak_main')->table('etesis_ujian as exam')
            ->join('etesis_skripsi as thesis', 'exam.id_skripsi', '=', 'thesis.id')
            ->join('s1m4k_mastermhs as student', 'thesis.id_mahasiswa', '=', 'student.id_mahasiswa')
            ->leftJoin('s1m4k_masterprodi as program', 'thesis.id_prodi', '=', 'program.id_prodi')
            ->where('exam.id_ujian', $examId)
            ->whereIn('program.nama_jenjang_pendidikan', self::ALLOWED_DEGREES)
            ->select(['exam.*', 'thesis.id as id_skripsi', 'thesis.id_prodi', 'thesis.id_mahasiswa', 'thesis.judul', 'thesis.status', 'thesis.pembimbing1', 'thesis.pembimbing2', 'thesis.penguji1', 'thesis.penguji2', 'thesis.penguji3', 'student.nim', 'student.nama_mahasiswa', 'student.email as email_mahasiswa', 'program.nama_program_studi', 'program.id_fakultas'])
            ->first();
        abort_if($exam === null, 404);
        abort_unless((int) $exam->status >= 2 && (int) $exam->status <= 6, 404);
        $this->applyRoleScopeToExam($user, $exam);

        return $exam;
    }

    private function applyRoleScopeToExam(object $user, object $exam): void
    {
        if ($user->role === 'mahasiswa') {
            abort_unless($exam->id_mahasiswa === (string) data_get($user, 'legacy_id'), 404);
        } elseif ($user->role === 'dosen') {
            $this->scoreField($exam, $this->teacherId($user)) !== null || abort(404);
            abort_unless(filled($exam->waktu_ujian) && filled($exam->ruang_ujian), 404);
        } elseif (in_array($user->role, ['userprodi', 'usertu'], true)) {
            abort_unless($exam->id_prodi === (string) data_get($user, 'id_prodi'), 404);
        } elseif ($user->role === 'pimfak') {
            abort_unless($exam->id_fakultas === (string) data_get($user, 'id_fakultas'), 404);
        } else {
            abort_unless(in_array($user->role, ['admin', 'pimuniv'], true), 403);
        }
    }

    private function contactsFor(object $user, object $thesis): array
    {
        $assigned = $this->assignedIds($thesis);
        $lecturers = $assigned->isEmpty() ? collect() : DB::connection('simak_main')->table('s1m4k_masterdosen')
            ->whereIn('id_dosen', $assigned)->get(['id_dosen', 'nama_dosen', 'gelar_depan', 'gelar_belakang'])->keyBy('id_dosen');
        $contacts = [];
        foreach (self::ASSIGNMENT_FIELDS as $field => $label) {
            $id = trim((string) $thesis->{$field});
            if ($id === '' || $id === '-') {
                continue;
            }
            $lecturer = $lecturers->get($id);
            $contacts[] = [
                'key' => $field,
                'id' => $id,
                'label' => $label,
                'name' => $this->lecturerName($lecturer) ?: 'Dosen belum tersedia',
                'kind' => str_starts_with($field, 'pembimbing') ? 'pembimbing' : 'penguji',
            ];
        }

        return $contacts;
    }

    private function assignedIds(object $thesis): Collection
    {
        return collect(array_values(array_filter(array_map(
            fn (string $field): string => trim((string) $thesis->{$field}),
            array_keys(self::ASSIGNMENT_FIELDS)
        ), fn (string $id): bool => $id !== '' && $id !== '-')))->unique()->values();
    }

    private function validateApprovalType(object $thesis, string $type, string $teacherId): void
    {
        $rules = [
            '[ACC] ACC Ujian Proposal' => ['roles' => ['pembimbing'], 'statuses' => [1]],
            '[ACC] ACC Revisi Proposal' => ['roles' => ['pembimbing', 'penguji'], 'statuses' => [2]],
            '[ACC] ACC Ujian Hasil' => ['roles' => ['pembimbing'], 'statuses' => [2, 3]],
            '[ACC] ACC Revisi Hasil' => ['roles' => ['pembimbing', 'penguji'], 'statuses' => [4]],
            '[ACC] ACC Revisi Tutup' => ['roles' => ['pembimbing', 'penguji'], 'statuses' => [4, 5]],
        ];
        abort_unless(isset($rules[$type]), 422, 'Jenis persetujuan tidak valid.');
        $field = $this->teacherAssignmentField($thesis, $teacherId);
        $kind = $field !== null && str_starts_with($field, 'pembimbing') ? 'pembimbing' : 'penguji';
        abort_unless(in_array($kind, $rules[$type]['roles'], true), 403);
        abort_unless(in_array((int) $thesis->status, $rules[$type]['statuses'], true), 422, 'Persetujuan tidak tersedia pada tahap tesis & disertasi ini.');
    }

    private function applyApprovalTransition(object $connection, object $thesis, string $type, string $teacherId): void
    {
        $transitions = [
            '[ACC] ACC Ujian Proposal' => [2, 'proposal', 'supervisors'],
            '[ACC] ACC Revisi Proposal' => [3, null, 'all'],
            '[ACC] ACC Ujian Hasil' => [4, 'hasil', 'supervisors'],
            '[ACC] ACC Revisi Hasil' => [5, 'tesis & disertasi', 'all'],
            '[ACC] ACC Revisi Tutup' => [6, null, 'all'],
        ];
        [$nextStatus, $examType, $thresholdKind] = $transitions[$type];
        $supervisors = collect([$thesis->pembimbing1, $thesis->pembimbing2])
            ->map(fn (mixed $id): string => trim((string) $id))->filter(fn (string $id): bool => $id !== '' && $id !== '-')->unique();
        $allAssigned = $this->assignedIds($thesis);
        $threshold = max(1, $thresholdKind === 'supervisors' ? $supervisors->count() : $allAssigned->count());
        $approved = DB::connection('simak_main')->table('etesis_bimbingan')
            ->where('id_skripsi', $thesis->id)->where('pesan', 'like', $type.'%')
            ->distinct('id_dosen_tujuan')->count('id_dosen_tujuan');
        if ($approved < $threshold || $nextStatus <= (int) $thesis->status) {
            return;
        }
        $connection->table('etesis_skripsi')->where('id', $thesis->id)->where('status', '<', $nextStatus)
            ->update(['status' => $nextStatus, 'updated_at' => now()]);
        if ($examType !== null && ! $connection->table('etesis_ujian')->where('id_skripsi', $thesis->id)
            ->where('jenis_ujian', $examType)->exists()) {
            $connection->table('etesis_ujian')->insert([
                'id_ujian' => (string) Str::uuid(),
                'id_skripsi' => $thesis->id,
                'jenis_ujian' => $examType,
                'accpembimbing1' => 0,
                'accpembimbing2' => 0,
                'accpenguji1' => 0,
                'accpenguji2' => 0,
                'accpenguji3' => 0,
                'create_at' => now(),
                'update_at' => now(),
            ]);
        }
    }

    private function scoreField(object $exam, string $teacherId): ?string
    {
        foreach (self::ASSIGNMENT_FIELDS as $field => $_label) {
            if ((string) $exam->{$field} === $teacherId) {
                return match ($field) {
                    'pembimbing1' => 'nilaipembimbing1',
                    'pembimbing2' => 'nilaipembimbing2',
                    'penguji1' => 'nilaipenguji1',
                    'penguji2' => 'nilaipenguji2',
                    'penguji3' => 'nilaipenguji3',
                };
            }
        }

        return null;
    }

    private function hasEnteredExamScore(mixed $score): bool
    {
        return $score !== null && trim((string) $score) !== '';
    }

    private function noteField(string $scoreField): string
    {
        return str_replace('nilai', 'catatan', $scoreField);
    }

    private function teacherAssignmentField(object $thesis, string $teacherId): ?string
    {
        foreach (self::ASSIGNMENT_FIELDS as $field => $_label) {
            if ((string) $thesis->{$field} === $teacherId) {
                return $field;
            }
        }

        return null;
    }

    private function teacherId(object $user): string
    {
        $id = DB::connection('simak_main')->table('s1m4k_masterdosen')
            ->where('nidn', data_get($user, 'login'))->value('id_dosen');
        abort_if($id === null, 403);

        return (string) $id;
    }

    private function applyAssignedLecturerScope(Builder $query, string $alias, string $teacherId): void
    {
        $query->where(function (Builder $builder) use ($alias, $teacherId): void {
            foreach (array_keys(self::ASSIGNMENT_FIELDS) as $field) {
                $builder->orWhere($alias.'.'.$field, $teacherId);
            }
        });
    }

    private function lecturerName(?object $lecturer): string
    {
        if ($lecturer === null) {
            return '';
        }

        return LecturerName::format(
            filled($lecturer->nama_dosen ?? null) ? ucwords(mb_strtolower($lecturer->nama_dosen)) : null,
            $lecturer->gelar_depan ?? null,
            $lecturer->gelar_belakang ?? null,
        );
    }

    private function notifyMessageRecipient(object $user, object $thesis, string $contactId, string $message, string $type): void
    {
        if ($user->role === 'mahasiswa') {
            $recipient = DB::connection('simak_main')->table('s1m4k_masterdosen')
                ->where('id_dosen', $contactId)->first(['email', 'nama_dosen', 'gelar_depan', 'gelar_belakang']);
            $email = trim((string) ($recipient->email ?? ''));
            $name = $this->lecturerName($recipient);
            $subject = 'Pesan Bimbingan Baru: '.$thesis->nama_mahasiswa;
            $intro = 'Mahasiswa mengirimkan pesan bimbingan atau revisi baru.';
        } else {
            $recipient = DB::connection('simak_main')->table('s1m4k_mastermhs')
                ->where('id_mahasiswa', $thesis->id_mahasiswa)->first(['email', 'nama_mahasiswa']);
            $email = trim((string) ($recipient->email ?? ''));
            $name = (string) ($recipient->nama_mahasiswa ?? 'Mahasiswa');
            $subject = $type === 'biasa' ? 'Balasan Bimbingan Tesis & Disertasi' : 'Status ACC Tesis & Disertasi';
            $intro = 'Dosen pembimbing/penguji mengirimkan respons terkait tesis & disertasi Anda.';
        }
        $recipientRole = $user->role === 'mahasiswa' ? 'dosen' : 'mahasiswa';
        $recipientId = $user->role === 'mahasiswa' ? $contactId : (string) $thesis->id_mahasiswa;
        $this->sendThesisNotification($email, $subject, $name, $intro, [
            'NIM' => $thesis->nim,
            'Judul tesis & disertasi' => $thesis->judul,
        ], $message, 'message', $recipientRole, $recipientId, route(
            'graduate_theses.bimbingan.show',
            (string) data_get($thesis, 'id', data_get($thesis, 'id_skripsi'))
        ));
    }

    private function notifyExamParticipants(object $exam, string $time, string $room): void
    {
        $details = [
            'Jenis ujian' => ucfirst((string) $exam->jenis_ujian),
            'NIM' => $exam->nim,
            'Nama mahasiswa' => $exam->nama_mahasiswa,
            'Program studi' => $exam->nama_program_studi,
            'Judul tesis & disertasi' => $exam->judul,
            'Jadwal' => Carbon::parse($time)->format('d-m-Y H:i').' WIT',
            'Ruang' => $room,
        ];
        $subject = 'Undangan Ujian Tesis & Disertasi: '.$exam->nama_mahasiswa;
        $intro = 'Jadwal ujian tesis & disertasi telah ditetapkan atau diperbarui. Harap hadir sesuai waktu dan ruang berikut.';

        $this->sendThesisNotification(
            trim((string) ($exam->email_mahasiswa ?? '')),
            $subject,
            (string) $exam->nama_mahasiswa,
            $intro,
            $details,
            null,
            'schedule',
            'mahasiswa',
            (string) $exam->id_mahasiswa,
            route('graduate_theses.bimbingan.show', $exam->id_skripsi),
        );

        $lecturers = DB::connection('simak_main')->table('s1m4k_masterdosen')
            ->whereIn('id_dosen', $this->assignedIds($exam))
            ->get(['id_dosen', 'email', 'nama_dosen', 'gelar_depan', 'gelar_belakang']);
        foreach ($lecturers as $lecturer) {
            $email = trim((string) $lecturer->email);
            $this->sendThesisNotification(
                $email,
                $subject,
                $this->lecturerName($lecturer),
                'Anda ditugaskan menghadiri ujian tesis & disertasi berikut. '.$intro,
                $details,
                null,
                'schedule',
                'dosen',
                (string) $lecturer->id_dosen,
                route('graduate_theses.exams.show', $exam->id_ujian));
        }
    }

    private function notifyGradeAndRevision(object $exam, string $teacherId, string $revisionText): void
    {
        $student = DB::connection('simak_main')->table('s1m4k_mastermhs')
            ->where('id_mahasiswa', $exam->id_mahasiswa)
            ->first(['email', 'nama_mahasiswa']);
        $lecturer = DB::connection('simak_main')->table('s1m4k_masterdosen')
            ->where('id_dosen', $teacherId)
            ->first(['nama_dosen', 'gelar_depan', 'gelar_belakang']);
        $lecturerName = $this->lecturerName($lecturer) ?: 'Dosen';
        $examType = ucfirst((string) $exam->jenis_ujian);
        $title = 'Nilai dan Revisi Ujian Telah Diberikan';
        $intro = $lecturerName.' telah menginput nilai dan memberikan revisi untuk Ujian '
            .$examType.' tesis & disertasi Anda.';

        $this->sendThesisNotification(
            trim((string) ($student->email ?? '')),
            $title,
            (string) ($student->nama_mahasiswa ?? $exam->nama_mahasiswa),
            $intro,
            [
                'Dosen' => $lecturerName,
                'Jenis ujian' => $examType,
                'Judul tesis & disertasi' => $exam->judul,
            ],
            $intro.' Catatan revisi: '.$revisionText,
            'message',
            'mahasiswa',
            (string) $exam->id_mahasiswa,
            route('graduate_theses.bimbingan.show', $exam->id_skripsi),
        );
    }

    private function notifyAssignedLecturers(
        object $student,
        string $title,
        array $assignments,
        array $previousAssignments,
        string $thesisId
    ): void {
        $changedAssignments = [];
        foreach ($assignments as $field => $lecturerId) {
            $lecturerId = trim((string) ($lecturerId ?? ''));
            if ($lecturerId === '' || $lecturerId === trim((string) ($previousAssignments[$field] ?? ''))) {
                continue;
            }

            $changedAssignments[$lecturerId][] = self::ASSIGNMENT_FIELDS[$field] ?? 'Dosen tesis & disertasi';
        }

        foreach ($changedAssignments as $lecturerId => $positions) {
            $lecturer = DB::connection('simak_main')->table('s1m4k_masterdosen')
                ->where('id_dosen', $lecturerId)
                ->first(['id_dosen', 'email', 'nama_dosen', 'gelar_depan', 'gelar_belakang']);
            if ($lecturer === null) {
                continue;
            }

            $position = implode(', ', $positions);
            $this->sendThesisNotification(
                trim((string) $lecturer->email),
                'Penugasan Tesis & Disertasi Baru',
                $this->lecturerName($lecturer),
                'Program studi menugaskan Anda sebagai '.$position.' untuk tesis & disertasi berikut.',
                [
                    'NIM' => $student->nim,
                    'Nama mahasiswa' => $student->nama_mahasiswa,
                    'Judul tesis & disertasi' => $title,
                    'Peran' => $position,
                ],
                null,
                'lecturer-assignment',
                'dosen',
                (string) $lecturer->id_dosen,
                route('graduate_theses.bimbingan.show', $thesisId),
            );
        }
    }

    private function notifyThesisTitleChanged(array $updated): void
    {
        $student = $updated['student'];
        $details = [
            'NIM' => (string) ($student->nim ?? ''),
            'Judul tesis & disertasi' => $updated['title'],
        ];
        $url = route('graduate_theses.bimbingan.show', $updated['thesis_id']);
        $this->sendThesisNotification(
            trim((string) ($student->email ?? '')),
            'Judul Tesis & Disertasi Diperbarui',
            (string) ($student->nama_mahasiswa ?? 'Mahasiswa'),
            'Program studi telah memperbarui judul tesis & disertasi Anda.',
            $details,
            null,
            'title-update',
            'mahasiswa',
            (string) ($student->id_mahasiswa ?? ''),
            $url,
        );

        if ($updated['lecturer_ids'] === []) {
            return;
        }

        $lecturers = DB::connection('simak_main')->table('s1m4k_masterdosen')
            ->whereIn('id_dosen', $updated['lecturer_ids'])
            ->get(['id_dosen', 'email', 'nama_dosen', 'gelar_depan', 'gelar_belakang']);
        foreach ($lecturers as $lecturer) {
            $this->sendThesisNotification(
                trim((string) $lecturer->email),
                'Judul Tesis & Disertasi Mahasiswa Diperbarui',
                $this->lecturerName($lecturer),
                'Judul tesis & disertasi mahasiswa bimbingan/penguji Anda telah diperbarui.',
                $details + ['Nama mahasiswa' => (string) ($student->nama_mahasiswa ?? '')],
                null,
                'title-update',
                'dosen',
                (string) $lecturer->id_dosen,
                $url,
            );
        }
    }

    private function sendThesisNotification(
        string $email,
        string $subject,
        string $name,
        string $intro,
        array $details,
        ?string $message,
        string $category,
        string $recipientRole,
        string $recipientId,
        string $url
    ): void {
        $messageBody = trim($message ?? '') !== '' ? trim($message) : $intro;
        if ($details !== []) {
            $messageBody .= ' '.implode(' · ', array_map(
                static fn (string $label, mixed $value): string => $label.': '.(string) $value,
                array_keys($details),
                array_values($details)
            ));
        }
        $this->storeThesisNotification(
            $recipientRole,
            $recipientId,
            $category,
            $subject,
            mb_substr($messageBody, 0, 2000),
            $url,
        );

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }

        try {
            if (app()->environment('production') && in_array(config('mail.default'), ['array', 'log'], true)) {
                throw new \RuntimeException('The configured mailer does not deliver email in production.');
            }
            Mail::to($email)->send(new GraduateThesisNotificationMail($subject, $name, $intro, $details, $message));
        } catch (Throwable $exception) {
            Log::error('E-Tesis & Disertasi email notification could not be sent.', [
                'category' => $category,
                'mailer' => config('mail.default'),
                'exception' => $exception::class,
                'exception_message' => substr(
                    preg_replace('/[\w.%+-]+@[\w.-]+\.[A-Z]{2,}/i', '[redacted-email]', $exception->getMessage()) ?? '',
                    0,
                    500,
                ),
                'recipient_domain' => substr(strrchr($email, '@') ?: '', 1),
            ]);
        }
    }

    private function notifyProgramOfTitleProposal(object $student, string $title): void
    {
        $connection = DB::connection('simak_main');
        if (! Schema::connection('simak_main')->hasTable('s1m4k_admin')) {
            Log::warning('E-Tesis & Disertasi title-proposal notification was not sent because program accounts are unavailable.', [
                'program_id' => (string) $student->id_prodi,
            ]);

            return;
        }

        $accounts = $connection->table('s1m4k_admin')
            ->where('level', 'userprodi')
            ->where('id_prodi', (string) $student->id_prodi)
            ->where('blokir', 'N')
            ->get(['id_admin']);
        $url = route('graduate_theses.titles.validation.index', ['program' => $student->id_prodi]);
        $message = sprintf(
            'Usulan judul baru dari %s (%s): %s',
            (string) $student->nama_mahasiswa,
            (string) $student->nim,
            $title,
        );

        foreach ($accounts as $account) {
            $this->storeThesisNotification(
                'userprodi',
                (string) $account->id_admin,
                'title-proposal-created',
                'Usulan Judul Baru',
                mb_substr($message, 0, 2000),
                $url,
            );
        }
    }

    private function storeThesisNotification(
        string $recipientRole,
        string $recipientId,
        string $category,
        string $title,
        string $message,
        string $url
    ): void {
        if (! Schema::connection('simak_main')->hasTable('etesis_notifikasi')) {
            Log::warning('E-Tesis & Disertasi in-app notification was not stored because its table is missing.', [
                'category' => $category,
                'recipient_role' => $recipientRole,
            ]);

            return;
        }

        DB::connection('simak_main')->table('etesis_notifikasi')->insert([
            'id' => (string) Str::uuid(),
            'recipient_role' => $recipientRole,
            'recipient_id' => $recipientId,
            'category' => $category,
            'title' => $title,
            'message' => $message,
            'url' => $url,
            'is_read' => 0,
            'created_at' => now(),
            'read_at' => null,
        ]);
    }
}
