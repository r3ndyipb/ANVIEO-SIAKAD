<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Services\LegacyFeederService;
use App\Support\AcademicCalendarScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class StudentStatusController extends Controller
{
    private const ROLES = ['admin', 'userprodi', 'usertu'];

    private const EXIT_STATUSES = [
        'C' => 'Cuti',
        '1' => 'Lulus',
        '2' => 'Mutasi',
        '3' => 'Dikeluarkan',
        '4' => 'Mengundurkan Diri',
        '5' => 'Putus Kuliah',
        '6' => 'Wafat',
        '7' => 'Hilang',
        'Z' => 'Lainnya',
    ];

    public function index(Request $request): View
    {
        $user = $request->user();
        $this->authorizeRole($user);
        $filters = $request->validate([
            'prodi' => ['nullable', 'string', 'max:100'],
            'tahun' => ['nullable', 'regex:/^\d{5}$/'],
        ]);
        $programs = $this->programs($user);
        $selectedProgram = $user->role === 'admin'
            ? ($filters['prodi'] ?? '')
            : (string) data_get($user, 'id_prodi');
        $calendars = $selectedProgram !== '' ? $this->calendars($selectedProgram) : collect();
        $records = null;

        if ($user->role !== 'admin') {
            abort_unless($selectedProgram !== '', 403);
        }

        if (filled($filters['tahun'] ?? null) && $selectedProgram !== '') {
            abort_if(! $calendars->contains(fn (object $calendar): bool => (string) $calendar->tahun === (string) $filters['tahun']), 404, 'Tahun akademik bukan milik program studi terpilih.');
            $records = $this->statusRecordsQuery($selectedProgram, (string) $filters['tahun'])
                ->paginate(25)
                ->withQueryString();
            foreach ($records as $record) {
                $record->status_label = $this->statusLabel((string) $record->status_mahasiswa);
            }
        }

        return view('student-status.index', compact(
            'user',
            'filters',
            'programs',
            'selectedProgram',
            'calendars',
            'records'
        ));
    }

    public function exportExcel(Request $request): Response
    {
        $user = $request->user();
        $this->authorizeRole($user);
        $validated = $request->validate([
            'prodi' => [$user->role === 'admin' ? 'required' : 'nullable', 'string', 'max:100'],
            'tahun' => ['required', 'regex:/^\d{5}$/'],
        ]);
        $programId = $user->role === 'admin'
            ? $validated['prodi']
            : trim((string) data_get($user, 'id_prodi'));
        abort_unless($programId !== '', 403);
        abort_unless($this->programs($user)->contains('id_prodi', $programId), 404);
        $calendar = $this->calendars($programId)->firstWhere('tahun', $validated['tahun']);
        abort_if($calendar === null, 404, 'Tahun akademik bukan milik program studi terpilih.');

        $records = $this->statusRecordsQuery($programId, (string) $validated['tahun'])->get();
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Lulus Cuti DO');
        $headers = ['NPM', 'Nama Mahasiswa', 'Tahun Akademik', 'Status', 'Tanggal Keluar', 'IPK', 'No SK', 'No Ijazah'];
        foreach ($headers as $index => $header) {
            $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($index + 1).'1', $header, DataType::TYPE_STRING);
        }
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);
        foreach ($records as $index => $record) {
            $values = [
                $record->nim,
                $record->nama_mahasiswa,
                $calendar->namatahun ?: $calendar->tahun,
                $this->statusLabel((string) $record->status_mahasiswa),
                $record->tgl_keluar,
                number_format((float) $record->ipk, 2),
                $record->no_sk,
                $record->no_ijazah,
            ];
            foreach ($values as $column => $value) {
                $sheet->setCellValueExplicit(
                    Coordinate::stringFromColumnIndex($column + 1).($index + 2),
                    (string) ($value ?? ''),
                    DataType::TYPE_STRING
                );
            }
        }

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $contents = (string) ob_get_clean();
        $spreadsheet->disconnectWorksheets();

        return response($contents, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="lulus-cuti-do-'.$calendar->tahun.'.xlsx"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function create(Request $request): View
    {
        $user = $request->user();
        $this->authorizeRole($user);
        $filters = $request->validate([
            'prodi' => ['nullable', 'string', 'max:100'],
            'tahun' => ['nullable', 'regex:/^\\d{5}$/'],
        ]);
        $programs = $this->programs($user);
        $selectedProgram = $user->role === 'admin'
            ? ($filters['prodi'] ?? '')
            : (string) data_get($user, 'id_prodi');
        if ($user->role !== 'admin') {
            abort_unless($selectedProgram !== '', 403);
        }
        $calendars = $selectedProgram !== '' ? $this->calendars($selectedProgram) : collect();
        $selectedCalendar = filled($filters['tahun'] ?? null)
            ? $calendars->firstWhere('tahun', $filters['tahun'])
            : null;
        abort_if(filled($filters['tahun'] ?? null) && $selectedCalendar === null, 404, 'Tahun akademik bukan milik program studi terpilih.');

        return view('student-status.form', [
            'user' => $user,
            'programs' => $programs,
            'selectedProgram' => $selectedProgram,
            'calendars' => $calendars,
            'selectedCalendar' => $selectedCalendar,
            'students' => $selectedCalendar !== null ? $this->activeStudents($selectedProgram) : collect(),
            'record' => null,
        ]);
    }

    public function store(
        Request $request,
        LegacyFeederService $feeder,
        ActivityLogger $activityLogger
    ): RedirectResponse {
        $user = $request->user();
        $this->authorizeRole($user);
        $programId = $this->programIdForUser($user, $request->input('id_prodi'));
        $validated = $this->validatedRecord($request, $programId, false);
        $student = $this->activeStudents($programId)->firstWhere('id_mahasiswa', $validated['id_mahasiswa']);
        abort_if($student === null, 404, 'Mahasiswa aktif pada program studi tersebut tidak ditemukan.');
        $calendar = $this->calendarForInput($programId, $validated['id_kalender'], $validated['tahun']);
        $statusName = self::EXIT_STATUSES[$validated['status_mahasiswa']];
        $connection = DB::connection('simak_main');
        $recordId = (string) Str::uuid();
        $values = $this->akmValues($validated, $programId, $student->id_mahasiswa, $calendar);

        try {
            $feeder->synchronizeStudentExitStatus(
                'InsertMahasiswaLulusDO',
                (string) $student->id_mahasiswa,
                $this->feederRecord($validated, $calendar),
                $user->role === 'admin'
            );
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->withInput()->withErrors(['feeder' => $exception->getMessage()]);
        }

        $connection->transaction(function () use ($activityLogger, $connection, $programId, $recordId, $statusName, $student, $user, $values): void {
            $existing = $connection->table('s1m4k_akm')
                ->where('id_mahasiswa', $student->id_mahasiswa)
                ->where('id_kalender', $values['id_kalender'])
                ->lockForUpdate()
                ->get();
            $connection->table('s1m4k_akm')
                ->where('id_mahasiswa', $student->id_mahasiswa)
                ->where('id_kalender', $values['id_kalender'])
                ->delete();
            $connection->table('s1m4k_akm')->insert(['id_ipmhs' => $recordId, ...$values]);
            $connection->table('s1m4k_mastermhs')
                ->where('id_mahasiswa', $student->id_mahasiswa)
                ->where('id_prodi', $programId)
                ->update([
                    'id_status_mahasiswa' => $values['status_mahasiswa'],
                    'nama_status_mahasiswa' => $statusName,
                    'flag_feeder' => '0',
                ]);
            $this->syncStudentStatus($connection, (string) $student->id_mahasiswa, $programId);
            $activityLogger->record($user, 'Lulus, Cuti, DO', 'Mencatat status keluar mahasiswa', [
                'data_sebelumnya' => $existing->map(fn (object $row): array => (array) $row)->all(),
                'data_baru' => ['id_ipmhs' => $recordId, ...$values],
                'status_mahasiswa_baru' => $statusName,
            ], (string) $student->id_mahasiswa, $this->studentIdentity($student));
        });

        return redirect()->route('student-status.index', [
            'prodi' => $programId,
            'tahun' => $calendar->tahun,
        ])->with('status', 'Data status mahasiswa berhasil disimpan ke Feeder dan SIMAK.');
    }

    public function edit(Request $request, string $recordId): View
    {
        $user = $request->user();
        $this->authorizeRole($user);
        $record = $this->findRecord($user, $recordId);
        $programs = $this->programs($user);

        return view('student-status.form', [
            'user' => $user,
            'programs' => $programs,
            'selectedProgram' => (string) $record->id_prodi,
            'calendars' => $this->calendars((string) $record->id_prodi),
            'students' => collect(),
            'record' => $record,
            'studentName' => $record->nama_mahasiswa,
            'studentNim' => $record->nim,
        ]);
    }

    public function update(
        Request $request,
        string $recordId,
        LegacyFeederService $feeder,
        ActivityLogger $activityLogger
    ): RedirectResponse {
        $user = $request->user();
        $this->authorizeRole($user);
        $before = $this->findRecord($user, $recordId);
        $validated = $this->validatedRecord($request, (string) $before->id_prodi, true);
        abort_unless($validated['id_mahasiswa'] === (string) $before->id_mahasiswa, 422, 'Mahasiswa pada riwayat status tidak dapat diganti.');
        $calendar = $this->calendarForInput((string) $before->id_prodi, $validated['id_kalender'], $validated['tahun']);
        $this->ensureCalendarAvailable($before, $calendar);
        $statusName = self::EXIT_STATUSES[$validated['status_mahasiswa']];
        $values = $this->akmValues($validated, (string) $before->id_prodi, (string) $before->id_mahasiswa, $calendar);
        $connection = DB::connection('simak_main');

        try {
            $feeder->synchronizeStudentExitStatus(
                'UpdateMahasiswaLulusDO',
                (string) $before->id_mahasiswa,
                $this->feederRecord($validated, $calendar),
                $user->role === 'admin'
            );
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->withInput()->withErrors(['feeder' => $exception->getMessage()]);
        }

        $connection->transaction(function () use ($activityLogger, $before, $connection, $recordId, $statusName, $user, $values): void {
            $existing = $connection->table('s1m4k_akm')
                ->where('id_mahasiswa', $before->id_mahasiswa)
                ->where('id_kalender', $values['id_kalender'])
                ->where('id_ipmhs', '<>', $recordId)
                ->lockForUpdate()
                ->exists();
            abort_if($existing, 422, 'Mahasiswa sudah memiliki riwayat pada kalender akademik tersebut.');
            $beforeData = (array) $connection->table('s1m4k_akm')->where('id_ipmhs', $recordId)->first();
            $connection->table('s1m4k_akm')->where('id_ipmhs', $recordId)->update($values);
            $after = $connection->table('s1m4k_akm')->where('id_ipmhs', $recordId)->first();
            $this->syncStudentStatus($connection, (string) $before->id_mahasiswa, (string) $before->id_prodi);
            $activityLogger->record($user, 'Lulus, Cuti, DO', 'Mengubah status keluar mahasiswa', [
                'perubahan' => $activityLogger->diff($beforeData, (array) $after),
                'status_mahasiswa' => ['sebelum' => $before->nama_status_mahasiswa ?? $before->status_mahasiswa, 'sesudah' => $statusName],
            ], (string) $before->id_mahasiswa, $this->studentIdentity($before));
        });

        return redirect()->route('student-status.index', [
            'prodi' => $before->id_prodi,
            'tahun' => $calendar->tahun,
        ])->with('status', 'Data status mahasiswa berhasil diperbarui.');
    }

    public function destroy(Request $request, string $recordId, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeRole($user);
        $record = $this->findRecord($user, $recordId);
        $connection = DB::connection('simak_main');

        $connection->transaction(function () use ($activityLogger, $connection, $record, $recordId, $user): void {
            $connection->table('s1m4k_akm')->where('id_ipmhs', $recordId)->delete();
            $statusAfterDelete = $this->syncStudentStatus($connection, (string) $record->id_mahasiswa, (string) $record->id_prodi);
            $activityLogger->record($user, 'Lulus, Cuti, DO', 'Menghapus riwayat status keluar mahasiswa', [
                'data_dihapus' => (array) $record,
                'status_setelah_hapus' => $statusAfterDelete,
            ], (string) $record->id_mahasiswa, $this->studentIdentity($record));
        });

        return redirect()->route('student-status.index', [
            'prodi' => $record->id_prodi,
            'tahun' => $record->tahun,
        ])->with('status', 'Riwayat status mahasiswa berhasil dihapus.');
    }

    private function validatedRecord(Request $request, string $programId, bool $editing): array
    {
        $rules = [
            'id_kalender' => ['required', 'string', 'max:100'],
            'tahun' => ['required', 'regex:/^\d{5}$/'],
            'status_mahasiswa' => ['required', Rule::in(array_keys(self::EXIT_STATUSES))],
            'tgl_keluar' => ['required', 'date_format:Y-m-d'],
            'ipk' => ['nullable', 'numeric', 'between:0,4'],
            'ket' => ['nullable', 'string', 'max:2000'],
            'no_sk' => ['nullable', 'string', 'max:50'],
            'tgl_sk' => ['nullable', 'date_format:Y-m-d'],
            'no_ijazah' => ['nullable', 'string', 'max:50'],
        ];
        if ($editing) {
            $rules['id_mahasiswa'] = ['required', 'string', 'max:100'];
        } else {
            $rules['id_mahasiswa'] = [
                'required',
                'string',
                'max:100',
                Rule::exists('simak_main.s1m4k_mastermhs', 'id_mahasiswa')->where('id_prodi', $programId),
            ];
        }

        return $request->validate($rules);
    }

    private function akmValues(array $validated, string $programId, string $studentId, object $calendar): array
    {
        return [
            'id_prodi' => $programId,
            'id_mahasiswa' => $studentId,
            'id_kalender' => $calendar->id_kalender,
            'tahun' => $calendar->tahun,
            'status_mahasiswa' => $validated['status_mahasiswa'],
            'no_ijazah' => $validated['no_ijazah'] ?? '',
            'ipk' => number_format((float) ($validated['ipk'] ?? 0), 2, '.', ''),
            'tgl_keluar' => $validated['tgl_keluar'],
            'ket' => $validated['ket'] ?? '',
            'no_sk' => $validated['no_sk'] ?? '',
            'tgl_sk' => $validated['tgl_sk'] ?? null,
        ];
    }

    private function feederRecord(array $validated, object $calendar): array
    {
        return [
            'id_jenis_keluar' => $validated['status_mahasiswa'],
            'tanggal_keluar' => $validated['tgl_keluar'],
            'id_periode_keluar' => $calendar->tahun,
            'keterangan' => $validated['ket'] ?? '',
            'ipk' => $validated['ipk'] ?? '',
            'nomor_ijazah' => $validated['no_ijazah'] ?? '',
            'nomor_sk_yudisium' => $validated['no_sk'] ?? '',
            'tanggal_sk_yudisium' => $validated['tgl_sk'] ?? '',
        ];
    }

    private function programs(object $user): Collection
    {
        $query = DB::connection('simak_main')->table('s1m4k_masterprodi')
            ->select(['id_prodi', 'nama_program_studi', 'nama_jenjang_pendidikan'])
            ->orderBy('nama_program_studi');
        if ($user->role !== 'admin') {
            $programId = trim((string) data_get($user, 'id_prodi'));
            abort_unless($programId !== '', 403);
            $query->where('id_prodi', $programId);
        }

        return $query->get();
    }

    private function calendars(string $programId): Collection
    {
        return AcademicCalendarScope::excludeShortSemesters(
            DB::connection('simak_main')->table('s1m4k_kalender')
        )
            ->where('id_prodi', $programId)
            ->orderByDesc('tahun')
            ->get(['id_kalender', 'id_prodi', 'tahun', 'namatahun']);
    }

    private function statusRecordsQuery(string $programId, string $year)
    {
        return DB::connection('simak_main')->table('s1m4k_akm as akm')
            ->leftJoin('s1m4k_mastermhs as student', 'akm.id_mahasiswa', '=', 'student.id_mahasiswa')
            ->where('akm.id_prodi', $programId)
            ->where('akm.tahun', $year)
            ->whereIn(DB::raw('UPPER(akm.status_mahasiswa)'), $this->exitStatusCodes())
            ->select([
                'akm.id_ipmhs', 'akm.id_mahasiswa', 'akm.id_kalender', 'akm.tahun',
                'akm.status_mahasiswa', 'akm.tgl_keluar', 'akm.ipk', 'akm.no_sk',
                'akm.no_ijazah', 'student.nim', 'student.nama_mahasiswa',
            ])
            ->orderBy('student.nama_mahasiswa');
    }

    private function activeStudents(string $programId): Collection
    {
        return DB::connection('simak_main')->table('s1m4k_mastermhs')
            ->where('id_prodi', $programId)
            ->where(function ($query): void {
                $query->where('id_status_mahasiswa', 'A')
                    ->orWhereRaw("LOWER(TRIM(COALESCE(nama_status_mahasiswa, ''))) = 'aktif'");
            })
            ->orderBy('nama_mahasiswa')
            ->get(['id_mahasiswa', 'nim', 'nama_mahasiswa', 'id_prodi']);
    }

    private function calendarForInput(string $programId, string $calendarId, string $year): object
    {
        $calendar = AcademicCalendarScope::excludeShortSemesters(
            DB::connection('simak_main')->table('s1m4k_kalender')
        )
            ->where('id_kalender', $calendarId)
            ->where('id_prodi', $programId)
            ->where('tahun', $year)
            ->first();
        abort_if($calendar === null, 404, 'Kalender akademik tidak cocok dengan program studi atau tahun yang dipilih.');

        return $calendar;
    }

    private function ensureCalendarAvailable(object $record, object $calendar): void
    {
        if ((string) $record->id_kalender === (string) $calendar->id_kalender) {
            return;
        }

        $exists = DB::connection('simak_main')->table('s1m4k_akm')
            ->where('id_mahasiswa', $record->id_mahasiswa)
            ->where('id_kalender', $calendar->id_kalender)
            ->where('id_ipmhs', '<>', $record->id_ipmhs)
            ->exists();
        abort_if($exists, 422, 'Mahasiswa sudah memiliki riwayat pada kalender akademik tersebut.');
    }

    private function syncStudentStatus(object $connection, string $studentId, string $programId): array
    {
        $latestStatus = $connection->table('s1m4k_akm')
            ->where('id_mahasiswa', $studentId)
            ->where('id_prodi', $programId)
            ->whereIn(DB::raw('UPPER(status_mahasiswa)'), $this->exitStatusCodes())
            ->orderByDesc('tahun')
            ->orderByDesc('tgl_keluar')
            ->orderByDesc('id_ipmhs')
            ->first(['status_mahasiswa']);
        $statusCode = $latestStatus?->status_mahasiswa ?? 'A';
        $statusName = $latestStatus
            ? $this->statusLabel((string) $latestStatus->status_mahasiswa)
            : 'Aktif';

        $connection->table('s1m4k_mastermhs')
            ->where('id_mahasiswa', $studentId)
            ->where('id_prodi', $programId)
            ->update([
                'id_status_mahasiswa' => $statusCode,
                'nama_status_mahasiswa' => $statusName,
                'flag_feeder' => '0',
            ]);

        return ['kode' => $statusCode, 'nama' => $statusName];
    }

    private function findRecord(object $user, string $recordId): object
    {
        $query = DB::connection('simak_main')->table('s1m4k_akm as akm')
            ->join('s1m4k_mastermhs as student', 'akm.id_mahasiswa', '=', 'student.id_mahasiswa')
            ->where('akm.id_ipmhs', $recordId)
            ->select([
                'akm.*', 'student.nim', 'student.nama_mahasiswa',
                'student.id_prodi as student_program_id',
            ]);
        if ($user->role !== 'admin') {
            $programId = trim((string) data_get($user, 'id_prodi'));
            abort_unless($programId !== '', 403);
            $query->where('akm.id_prodi', $programId);
        }
        $record = $query->first();
        abort_if($record === null, 404);
        abort_unless((string) $record->id_prodi === (string) $record->student_program_id, 404);

        return $record;
    }

    private function programIdForUser(object $user, mixed $requestedProgram): string
    {
        if ($user->role === 'admin') {
            $programId = trim((string) $requestedProgram);
            abort_unless($programId !== '', 422, 'Program studi wajib dipilih.');

            return $programId;
        }

        $programId = trim((string) data_get($user, 'id_prodi'));
        abort_unless($programId !== '', 403);

        return $programId;
    }

    private function statusLabel(string $status): string
    {
        return self::EXIT_STATUSES[strtoupper($status)] ?? 'Status tidak dikenal';
    }

    private function exitStatusCodes(): array
    {
        return array_map('strval', array_keys(self::EXIT_STATUSES));
    }

    private function studentIdentity(object $student): array
    {
        return [
            'nama' => $student->nama_mahasiswa,
            'nomor_identitas' => $student->nim,
        ];
    }

    private function authorizeRole(object $user): void
    {
        abort_unless(in_array($user->role, self::ROLES, true), 403);
    }
}
