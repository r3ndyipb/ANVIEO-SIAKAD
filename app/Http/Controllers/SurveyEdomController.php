<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Support\AcademicCalendarScope;
use App\Support\LecturerName;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SurveyEdomController extends Controller
{
    private const QUESTION_ROLES = ['admin', 'pimuniv', 'pimfak'];

    private const RESULT_ROLES = ['admin', 'pimuniv', 'pimfak', 'userprodi', 'usertu', 'dosen', 'asdos'];

    private const TEACHER_SLOTS = [
        'dosen1', 'dosen2', 'dosen3', 'dosen4', 'dosen5', 'dosen6', 'dosen7', 'dosen8', 'dosen9',
    ];

    private const ASPECTS = [
        1 => ['label' => 'Aspek Rencana', 'color' => '#32754d'],
        2 => ['label' => 'Aspek Capaian', 'color' => '#bd5d84'],
        3 => ['label' => 'Aspek Metode', 'color' => '#557eac'],
        4 => ['label' => 'Aspek Sikap', 'color' => '#c48b2e'],
        5 => ['label' => 'Aspek Transparan', 'color' => '#278f89'],
        6 => ['label' => 'Aspek Kesesuaian', 'color' => '#86649b'],
    ];

    public function questions(Request $request): View
    {
        $user = $request->user();
        abort_unless(in_array($user->role, self::QUESTION_ROLES, true), 403);

        return view('survey-edom.questions', [
            'questions' => DB::connection('simak_main')->table('s1m4k_survey_edom')
                ->orderBy('id_survey')->get(['id_survey', 'pertanyaan']),
            'canEdit' => $user->role === 'admin',
        ]);
    }

    public function createQuestion(Request $request): View
    {
        abort_unless($request->user()->role === 'admin', 403);

        return view('survey-edom.question-form', ['question' => null]);
    }

    public function storeQuestion(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->role === 'admin', 403);
        $validated = $request->validate([
            'pertanyaan' => ['required', 'string', 'max:2000'],
        ]);
        $id = DB::connection('simak_main')->table('s1m4k_survey_edom')->insertGetId([
            'pertanyaan' => trim($validated['pertanyaan']),
        ]);
        $activityLogger->record($user, 'Survey EDOM', 'Menambah pertanyaan', [
            'data_baru' => ['pertanyaan' => trim($validated['pertanyaan'])],
        ], (string) $id);

        return redirect()->route('survey-edom.questions.index')->with('status', 'Pertanyaan EDOM berhasil ditambahkan.');
    }

    public function editQuestion(Request $request, string $questionId): View
    {
        abort_unless($request->user()->role === 'admin', 403);
        $question = DB::connection('simak_main')->table('s1m4k_survey_edom')
            ->where('id_survey', $questionId)->first(['id_survey', 'pertanyaan']);
        abort_if($question === null, 404);

        return view('survey-edom.question-form', compact('question'));
    }

    public function updateQuestion(Request $request, string $questionId, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->role === 'admin', 403);
        $validated = $request->validate([
            'pertanyaan' => ['required', 'string', 'max:2000'],
        ]);
        $connection = DB::connection('simak_main');
        $question = $connection->table('s1m4k_survey_edom')->where('id_survey', $questionId)->first();
        abort_if($question === null, 404);
        $value = trim($validated['pertanyaan']);
        $connection->table('s1m4k_survey_edom')->where('id_survey', $questionId)->update(['pertanyaan' => $value]);
        if ((string) $question->pertanyaan !== $value) {
            $activityLogger->record($user, 'Survey EDOM', 'Mengubah pertanyaan', [
                'data_sebelumnya' => ['pertanyaan' => $question->pertanyaan],
                'data_baru' => ['pertanyaan' => $value],
            ], $questionId);
        }

        return redirect()->route('survey-edom.questions.index')->with('status', 'Pertanyaan EDOM berhasil diperbarui.');
    }

    public function results(Request $request): View
    {
        $user = $request->user();
        abort_unless(in_array($user->role, self::RESULT_ROLES, true), 403);
        $filters = $request->validate([
            'program' => ['nullable', 'string', 'max:100'],
            'calendar' => ['nullable', 'string', 'max:100'],
        ]);
        $teacherId = in_array($user->role, ['dosen', 'asdos'], true) ? $this->teacherId($user) : null;
        $programs = $this->programsForResults($user, $teacherId);
        $programId = $this->resolveProgram($user, $programs, $filters['program'] ?? null);
        $programIds = $programs->pluck('id_prodi')->values();
        $allCalendars = $programIds->isEmpty()
            ? collect()
            : AcademicCalendarScope::excludeShortSemesters(
                DB::connection('simak_main')->table('s1m4k_kalender')
            )
                ->whereIn('id_prodi', $programIds)->orderByDesc('tahun')
                ->get(['id_kalender', 'id_prodi', 'tahun', 'namatahun', 'status']);
        $calendarOptionsByProgram = $allCalendars->groupBy('id_prodi')->map(fn (Collection $rows): array => $rows
            ->map(fn (object $calendar): array => [
                'id' => (string) $calendar->id_kalender,
                'year' => (string) $calendar->tahun,
                'label' => $calendar->namatahun ?: $calendar->tahun,
                'active' => $calendar->status === 'Y',
            ])->values()->all())->all();
        $calendars = $allCalendars->where('id_prodi', $programId)->values();
        $requestedCalendar = (string) ($filters['calendar'] ?? '');
        $calendarId = $requestedCalendar !== '' && $calendars->contains(
            fn (object $calendar): bool => (string) $calendar->id_kalender === $requestedCalendar
        ) ? $requestedCalendar : '';

        return view('survey-edom.results', [
            'user' => $user,
            'programs' => $programs,
            'calendarOptionsByProgram' => $calendarOptionsByProgram,
            'selectedProgram' => $programId,
            'selectedCalendar' => $calendarId,
            'showProgramFilter' => in_array($user->role, ['admin', 'pimuniv', 'pimfak'], true),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(in_array($user->role, self::RESULT_ROLES, true), 403);
        $filters = $request->validate([
            'program' => ['required', 'string', 'max:100'],
            'calendar' => ['required', 'string', 'max:100'],
        ]);
        $teacherId = in_array($user->role, ['dosen', 'asdos'], true) ? $this->teacherId($user) : null;
        $programs = $this->programsForResults($user, $teacherId);
        $programId = $this->resolveProgram($user, $programs, $filters['program']);
        abort_unless(AcademicCalendarScope::excludeShortSemesters(
            DB::connection('simak_main')->table('s1m4k_kalender')
        )
            ->where('id_kalender', $filters['calendar'])->where('id_prodi', $programId)->exists(), 404);

        $questions = DB::connection('simak_main')->table('s1m4k_survey_edom')
            ->orderBy('id_survey')->get(['id_survey', 'pertanyaan']);
        $lecturers = $this->lecturersForResults($user, $programId, $teacherId);
        $results = $this->aggregateResults($programId, $filters['calendar'], $lecturers);

        return response()->json([
            'program' => $programs->firstWhere('id_prodi', $programId)?->nama_program_studi,
            'calendar' => DB::connection('simak_main')->table('s1m4k_kalender')
                ->where('id_kalender', $filters['calendar'])->value('namatahun'),
            'question_count' => $questions->count(),
            'results' => $results,
        ]);
    }

    private function resolveProgram(object $user, Collection $programs, ?string $requestedProgram): string
    {
        if (in_array($user->role, ['userprodi', 'usertu', 'dosen', 'asdos'], true)) {
            $programId = trim((string) data_get($user, 'id_prodi'));
            if ($programId === '' && in_array($user->role, ['dosen', 'asdos'], true)) {
                $programId = (string) ($programs->first()->id_prodi ?? '');
            }
            abort_unless($programId !== '' && $programs->contains(
                fn (object $program): bool => (string) $program->id_prodi === $programId
            ), 403);
            abort_if(filled($requestedProgram) && (string) $requestedProgram !== $programId, 404);

            return $programId;
        }

        $programId = trim((string) $requestedProgram);
        if ($programId === '') {
            return '';
        }
        abort_unless($programs->contains(fn (object $program): bool => (string) $program->id_prodi === $programId), 404);

        return $programId;
    }

    public function export(Request $request, string $programId, string $calendarId, string $lecturerId): StreamedResponse
    {
        $user = $request->user();
        abort_unless(in_array($user->role, self::RESULT_ROLES, true), 403);
        $teacherId = in_array($user->role, ['dosen', 'asdos'], true) ? $this->teacherId($user) : null;
        $programs = $this->programsForResults($user, $teacherId);
        abort_unless($programs->contains(fn (object $program): bool => (string) $program->id_prodi === $programId), 404);
        abort_unless(DB::connection('simak_main')->table('s1m4k_kalender')
            ->where('id_kalender', $calendarId)->where('id_prodi', $programId)->exists(), 404);

        $lecturers = $this->lecturersForResults($user, $programId, $teacherId);
        $lecturer = $lecturers->firstWhere('id', $lecturerId);
        abort_if($lecturer === null, 404);
        $rows = $this->responseRows($programId, $calendarId, $lecturerId)->sortBy([
            ['nama_mata_kuliah', 'asc'], ['nama_kelas', 'asc'], ['id_mahasiswa', 'asc'], ['id_survey', 'asc'],
        ])->values();
        $questions = DB::connection('simak_main')->table('s1m4k_survey_edom')
            ->pluck('pertanyaan', 'id_survey');
        $fileName = 'EDOM-anonim-'.Str::slug($lecturer['name']).'-'.Str::slug($calendarId).'.csv';

        return response()->streamDownload(function () use ($rows, $questions): void {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['No', 'Kode Responden', 'Mata Kuliah', 'Kelas', 'Aspek Survey', 'Nilai']);
            foreach ($rows as $index => $row) {
                $rawId = (string) $row->id_mahasiswa;
                $respondent = mb_strlen($rawId) > 6
                    ? mb_substr($rawId, 0, 3).'****'.mb_substr($rawId, -3)
                    : 'RESPONDEN-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
                fputcsv($stream, [
                    $index + 1,
                    $respondent,
                    $row->nama_mata_kuliah,
                    $row->nama_kelas ?: '-',
                    $questions[$row->id_survey] ?? 'Aspek '.$row->id_survey,
                    (int) $row->nilai,
                ]);
            }
            fclose($stream);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function programsForResults(object $user, ?string $teacherId): Collection
    {
        $query = DB::connection('simak_main')->table('s1m4k_masterprodi as program')
            ->leftJoin('s1m4k_masterfakultas as faculty', 'program.id_fakultas', '=', 'faculty.id_fakultas');

        if ($user->role === 'pimfak') {
            $facultyId = trim((string) data_get($user, 'id_fakultas'));
            $facultyId === '' ? $query->whereRaw('1 = 0') : $query->where('program.id_fakultas', $facultyId);
        } elseif (in_array($user->role, ['userprodi', 'usertu'], true)) {
            $programId = trim((string) data_get($user, 'id_prodi'));
            $programId === '' ? $query->whereRaw('1 = 0') : $query->where('program.id_prodi', $programId);
        } elseif (in_array($user->role, ['dosen', 'asdos'], true)) {
            $query->whereIn('program.id_prodi', function ($subquery) use ($teacherId): void {
                $subquery->from('s1m4k_jadwal as schedule')->select('schedule.id_prodi')->distinct()
                    ->where(function ($builder) use ($teacherId): void {
                        foreach (self::TEACHER_SLOTS as $slot) {
                            $builder->orWhere('schedule.'.$slot, $teacherId);
                        }
                    });
            });
        }

        return $query->orderBy('program.nama_program_studi')->get([
            'program.id_prodi', 'program.id_fakultas', 'program.nama_program_studi',
            'program.nama_jenjang_pendidikan', 'faculty.nama_fakultas',
        ]);
    }

    private function lecturersForResults(object $user, string $programId, ?string $teacherId): Collection
    {
        $connection = DB::connection('simak_main');
        if ($user->role === 'asdos') {
            $assistant = $connection->table('s1m4k_masterasdos')->where('id_asdos', $teacherId)->first([
                'id_asdos as id', 'nama', 'gelard as gelar_depan', 'gelarb as gelar_belakang',
            ]);
            $rows = $assistant === null ? collect() : collect([$assistant]);
        } else {
            $query = $connection->table('s1m4k_masterdosen')->where('id_prodi', $programId);
            if ($user->role === 'dosen') {
                $query->where('id_dosen', $teacherId);
            }
            $rows = $query->orderBy('nama_dosen')->get([
                'id_dosen as id', 'nama_dosen as nama', 'gelar_depan', 'gelar_belakang',
            ]);
        }

        return $rows->map(fn (object $row): array => [
            'id' => (string) $row->id,
            'name' => LecturerName::format(
                $row->nama ? ucwords(mb_strtolower((string) $row->nama)) : null,
                $row->gelar_depan,
                $row->gelar_belakang,
            ),
        ])->values();
    }

    private function aggregateResults(string $programId, string $calendarId, Collection $lecturers): Collection
    {
        $stats = [];
        foreach ($lecturers as $lecturer) {
            $stats[$lecturer['id']] = [
                'courses' => [], 'classes' => [], 'respondents' => [],
                'aspects' => array_fill_keys(array_keys(self::ASPECTS), ['sum' => 0, 'count' => 0]),
            ];
        }

        $rows = $this->responseRows($programId, $calendarId);
        foreach ($rows as $row) {
            for ($slot = 1; $slot <= 9; $slot++) {
                $lecturerId = trim((string) ($row->{'dosen'.$slot} ?? ''));
                if ($lecturerId === '' || ! isset($stats[$lecturerId])) {
                    continue;
                }
                $stats[$lecturerId]['courses'][(string) $row->id_matkul] = true;
                $stats[$lecturerId]['classes'][(string) ($row->nama_kelas ?: 'Reguler')] = true;
                $stats[$lecturerId]['respondents'][(string) $row->id_mahasiswa.'|'.(string) $row->id_matkul] = true;
                $aspectId = (int) $row->id_survey;
                if (isset($stats[$lecturerId]['aspects'][$aspectId])) {
                    $stats[$lecturerId]['aspects'][$aspectId]['sum'] += (int) $row->nilai;
                    $stats[$lecturerId]['aspects'][$aspectId]['count']++;
                }
            }
        }

        return $lecturers->map(function (array $lecturer) use ($stats, $programId, $calendarId): array {
            $item = $stats[$lecturer['id']];
            $aspects = [];
            foreach (self::ASPECTS as $id => $aspect) {
                $sum = $item['aspects'][$id]['sum'];
                $count = $item['aspects'][$id]['count'];
                $aspects[] = [
                    'id' => $id,
                    'label' => $aspect['label'],
                    'color' => $aspect['color'],
                    'responses' => $count,
                    'percentage' => $count > 0 ? round(($sum / ($count * 5)) * 100, 1) : 0,
                ];
            }

            return [
                ...$lecturer,
                'course_count' => count($item['courses']),
                'class_count' => count($item['classes']),
                'respondent_count' => count($item['respondents']),
                'aspects' => $aspects,
                'export_url' => route('survey-edom.results.export', [
                    'program' => $programId,
                    'calendar' => $calendarId,
                    'lecturer' => $lecturer['id'],
                ]),
            ];
        })->values();
    }

    private function responseRows(string $programId, string $calendarId, ?string $lecturerId = null): Collection
    {
        $query = DB::connection('simak_main')->table('s1m4k_edom as response')
            ->join('s1m4k_krs as registration', 'response.id_krs', '=', 'registration.id_krs')
            ->join('s1m4k_jadwal as schedule', 'response.id_jadwal', '=', 'schedule.id_jadwal')
            ->leftJoin('s1m4k_mastermk as course', 'schedule.id_matkul', '=', 'course.id_matkul')
            ->where('schedule.id_prodi', $programId)
            ->where('response.id_kalender', $calendarId);
        if ($lecturerId !== null) {
            $query->where(function ($builder) use ($lecturerId): void {
                foreach (self::TEACHER_SLOTS as $slot) {
                    $builder->orWhere('schedule.'.$slot, $lecturerId);
                }
            });
        }

        return $query->get([
            'response.id_survey', 'response.nilai', 'registration.id_mahasiswa',
            'schedule.id_matkul', 'schedule.nama_kelas', 'course.nama_mata_kuliah',
            ...self::TEACHER_SLOTS,
        ]);
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
}
