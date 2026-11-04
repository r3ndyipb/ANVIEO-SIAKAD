<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;

class KknController extends Controller
{
    private const ADMIN = ['admin'];

    private const LOCATIONS = ['admin', 'lppm'];

    private const PROPOSALS = ['admin', 'validasi', 'usertu'];

    private const VALIDATORS = ['admin', 'validasi'];

    private const PARTICIPANTS = ['admin', 'lppm'];

    public function periods(Request $request): View
    {
        $this->authorizeRole($request, self::ADMIN);
        $connection = DB::connection('simak_main');
        $periods = $connection->table('s1m4k_periode')
            ->where('jenis', 'kubermas')
            ->orderByDesc('tahun')
            ->paginate(25)
            ->withQueryString();
        $years = $connection->table('s1m4k_kalender')
            ->whereNotNull('tahun')
            ->select('tahun', 'namatahun')
            ->distinct()
            ->orderByDesc('tahun')
            ->get();

        return view('kkn.periods', compact('periods', 'years'));
    }

    public function storePeriod(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeRole($request, self::ADMIN);
        $validated = $request->validate([
            'tahun' => ['required', 'regex:/^\d{5}$/'],
            'tanggal_mulai' => ['required', 'date_format:Y-m-d'],
            'tanggal_akhir' => ['required', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
        ]);
        $calendar = DB::connection('simak_main')->table('s1m4k_kalender')
            ->where('tahun', $validated['tahun'])
            ->orderBy('id_prodi')
            ->first(['tahun', 'namatahun']);
        abort_if($calendar === null, 422, 'Tahun akademik tidak ditemukan.');

        $values = [
            'id_periode' => (string) Str::uuid(),
            'nama_periode' => (string) $calendar->namatahun,
            'tahun' => (string) $calendar->tahun,
            'tanggal_mulai' => $validated['tanggal_mulai'],
            'tanggal_akhir' => $validated['tanggal_akhir'],
            'jenis' => 'kubermas',
            'aktif' => 'N',
        ];
        $connection = DB::connection('simak_main');
        $connection->table('s1m4k_periode')->insert($values);
        $activityLogger->record($request->user(), 'KKN', 'Menambahkan periode KKN', $values, $values['id_periode']);

        return redirect()->route('kkn.periods.index')->with('status', 'Periode KKN berhasil ditambahkan.');
    }

    public function updatePeriod(Request $request, string $period, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeRole($request, self::ADMIN);
        $validated = $request->validate([
            'tanggal_mulai' => ['required', 'date_format:Y-m-d'],
            'tanggal_akhir' => ['required', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
        ]);
        $connection = DB::connection('simak_main');
        $record = $connection->table('s1m4k_periode')->where('jenis', 'kubermas')->where('id_periode', $period)->first();
        abort_if($record === null, 404);
        $before = ['tanggal_mulai' => $record->tanggal_mulai, 'tanggal_akhir' => $record->tanggal_akhir];
        $connection->table('s1m4k_periode')->where('id_periode', $period)->update($validated);
        $activityLogger->record($request->user(), 'KKN', 'Mengubah periode KKN', [
            'sebelum' => $before,
            'sesudah' => $validated,
        ], $period);

        return redirect()->route('kkn.periods.index')->with('status', 'Tanggal periode KKN berhasil diperbarui.');
    }

    public function activatePeriod(Request $request, string $period, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeRole($request, self::ADMIN);
        $connection = DB::connection('simak_main');
        $connection->transaction(function () use ($activityLogger, $connection, $period, $request): void {
            $record = $connection->table('s1m4k_periode')
                ->where('jenis', 'kubermas')->where('id_periode', $period)
                ->lockForUpdate()->first();
            abort_if($record === null, 404);
            $previous = $connection->table('s1m4k_periode')->where('jenis', 'kubermas')->where('aktif', 'Y')
                ->get(['id_periode', 'aktif']);
            $connection->table('s1m4k_periode')->where('jenis', 'kubermas')->update(['aktif' => 'N']);
            $connection->table('s1m4k_periode')->where('id_periode', $period)->update(['aktif' => 'Y']);
            $activityLogger->record($request->user(), 'KKN', 'Mengaktifkan periode KKN', [
                'periode_sebelumnya' => $previous->map(fn (object $item): array => (array) $item)->all(),
                'periode_aktif' => ['id_periode' => $period, 'tahun' => $record->tahun],
            ], $period);
        });

        return redirect()->route('kkn.periods.index')->with('status', 'Periode KKN berhasil diaktifkan.');
    }

    public function locations(Request $request): View
    {
        $this->authorizeRole($request, self::LOCATIONS);
        $connection = DB::connection('simak_main');
        $locations = $connection->table('s1m4k_lokasi_kubermas as location')
            ->select('location.*')
            ->selectSub($connection->table('s1m4k_kubermas as participant')
                ->selectRaw('COUNT(*)')
                ->whereColumn('participant.id_lokasi', 'location.id_lokasi'), 'occupied_count')
            ->orderBy('location.nama_lokasi')
            ->paginate(25)
            ->withQueryString();

        return view('kkn.locations', compact('locations'));
    }

    public function storeLocation(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeRole($request, self::LOCATIONS);
        $validated = $request->validate([
            'nama_lokasi' => ['required', 'string', 'max:200'],
            'kuota' => ['required', 'integer', 'min:1', 'max:2147483647'],
        ]);
        $connection = DB::connection('simak_main');
        $this->ensureUniqueLocationName($connection, $validated['nama_lokasi']);
        $values = [
            'id_lokasi' => (string) Str::uuid(),
            'nama_lokasi' => trim($validated['nama_lokasi']),
            'kuota' => (int) $validated['kuota'],
            'aktif' => 'Y',
        ];
        $connection->table('s1m4k_lokasi_kubermas')->insert($values);
        $activityLogger->record($request->user(), 'KKN', 'Menambahkan lokasi KKN', $values, $values['id_lokasi']);

        return redirect()->route('kkn.locations.index')->with('status', 'Lokasi KKN berhasil ditambahkan.');
    }

    public function updateLocation(Request $request, string $location, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeRole($request, self::LOCATIONS);
        $validated = $request->validate([
            'nama_lokasi' => ['required', 'string', 'max:200'],
            'kuota' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'aktif' => ['required', 'in:Y,N'],
        ]);
        $connection = DB::connection('simak_main');
        $record = $connection->table('s1m4k_lokasi_kubermas')->where('id_lokasi', $location)->first();
        abort_if($record === null, 404);
        $this->ensureUniqueLocationName($connection, $validated['nama_lokasi'], $location);
        $before = ['nama_lokasi' => $record->nama_lokasi, 'kuota' => $record->kuota, 'aktif' => $record->aktif];
        $values = [
            'nama_lokasi' => trim($validated['nama_lokasi']),
            'kuota' => (int) $validated['kuota'],
            'aktif' => $validated['aktif'],
        ];
        $connection->table('s1m4k_lokasi_kubermas')->where('id_lokasi', $location)->update($values);
        $activityLogger->record($request->user(), 'KKN', 'Mengubah lokasi KKN', [
            'sebelum' => $before,
            'sesudah' => $values,
        ], $location);

        return redirect()->route('kkn.locations.index')->with('status', 'Lokasi KKN berhasil diperbarui.');
    }

    public function proposals(Request $request): View
    {
        $user = $request->user();
        $this->authorizeRole($request, self::PROPOSALS);
        $period = $this->selectedPeriod($request->query('tahun'));
        $activePeriod = $this->activePeriod();
        $canPropose = $period !== null
            && $activePeriod !== null
            && (string) $period->id_periode === (string) $activePeriod->id_periode
            && $this->canUseActivePeriod($period);
        $rows = null;
        if ($period !== null) {
            $rows = $this->participantQuery()
                ->where('participant.tahun', $period->tahun)
                ->when($user->role === 'usertu', fn ($query) => $query->where('participant.id_prodi', $this->requiredProgramId($user)))
                ->orderBy('program.nama_program_studi')->orderBy('student.nim')
                ->paginate(25)->withQueryString();
        }
        $search = trim((string) $request->query('search', ''));
        $students = $canPropose ? $this->proposalCandidates($user, $period, $search) : null;

        return view('kkn.proposals', [
            'periods' => $this->periodsQuery()->get(),
            'period' => $period,
            'rows' => $rows,
            'canPropose' => $canPropose,
            'students' => $students,
            'search' => $search,
            'openCreate' => $request->boolean('create') && $canPropose,
        ]);
    }

    public function createProposals(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeRole($request, self::PROPOSALS);
        $period = $this->activePeriod();
        $query = $period === null ? [] : ['tahun' => $period->tahun, 'create' => 1];
        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query['search'] = $search;
        }

        return redirect()->route('kkn.proposals.index', $query);
    }

    public function storeProposals(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeRole($request, self::PROPOSALS);
        $period = $this->activePeriod();
        if ($period === null || ! $this->canUseActivePeriod($period)) {
            return redirect()->route('kkn.proposals.index', ['tahun' => $request->query('tahun', $period?->tahun ?? null)])
                ->with('error', 'Masa pengusulan KKN ditutup. Pengusulan hanya dapat dilakukan saat periode KKN aktif dan masih dalam tanggal pendaftaran.');
        }
        $validated = $request->validate([
            'students' => ['required', 'array', 'min:1', 'max:500'],
            'students.*' => ['required', 'string', 'max:50', 'distinct'],
        ]);
        $connection = DB::connection('simak_main');
        $created = [];
        $connection->transaction(function () use ($activityLogger, $connection, &$created, $period, $request, $user, $validated): void {
            foreach ($validated['students'] as $studentId) {
                $studentQuery = $connection->table('s1m4k_mastermhs')
                    ->where('id_mahasiswa', $studentId)
                    ->whereIn('id_status_mahasiswa', ['A', 'N', 'G']);
                if ($user->role === 'usertu') {
                    $studentQuery->where('id_prodi', $this->requiredProgramId($user));
                }
                $student = $studentQuery->lockForUpdate()->first([
                    'id_mahasiswa', 'id_fakultas', 'id_prodi', 'nim', 'nama_mahasiswa',
                ]);
                abort_if($student === null, 404, 'Mahasiswa tidak tersedia dalam cakupan pengusulan.');
                $exists = $connection->table('s1m4k_kubermas')
                    ->where('id_mahasiswa', $studentId)->where('tahun', $period->tahun)->exists();
                if ($exists) {
                    continue;
                }
                $values = [
                    'id_peserta' => (string) Str::uuid(),
                    'id_fakultas' => (string) $student->id_fakultas,
                    'id_prodi' => (string) $student->id_prodi,
                    'id_mahasiswa' => (string) $student->id_mahasiswa,
                    'tahun' => (int) $period->tahun,
                    'tgl_daftar' => today()->toDateString(),
                    'status' => 0,
                    'petugas_usul' => (string) $request->user()->name,
                ];
                $connection->table('s1m4k_kubermas')->insert($values);
                $created[] = [
                    'values' => $values,
                    'identity' => ['jenis' => 'Mahasiswa', 'nama' => $student->nama_mahasiswa, 'nomor_identitas' => $student->nim],
                ];
            }
            foreach ($created as $item) {
                $activityLogger->record($user, 'KKN', 'Mengusulkan peserta KKN', $item['values'], $item['values']['id_peserta'], $item['identity']);
            }
        });

        return redirect()->route('kkn.proposals.index', ['tahun' => $period->tahun])
            ->with('status', count($created).' mahasiswa berhasil diusulkan sebagai peserta KKN.');
    }

    public function validations(Request $request): View
    {
        $this->authorizeRole($request, self::VALIDATORS);
        $period = $this->selectedPeriod($request->query('tahun')) ?? $this->activePeriod();
        $rows = $period === null ? null : $this->participantQuery()
            ->where('participant.tahun', $period->tahun)
            ->orderBy('program.nama_program_studi')->orderBy('student.nim')
            ->paginate(25)->withQueryString();

        return view('kkn.validations', [
            'periods' => $this->periodsQuery()->get(),
            'period' => $period,
            'rows' => $rows,
        ]);
    }

    public function validationDetail(Request $request, string $participant): View
    {
        $this->authorizeRole($request, self::VALIDATORS);
        $record = $this->participantDetail($participant);
        $calendars = DB::connection('simak_main')->table('s1m4k_krs as krs')
            ->join('s1m4k_kalender as calendar', 'krs.id_kalender', '=', 'calendar.id_kalender')
            ->where('krs.id_mahasiswa', $record->id_mahasiswa)
            ->select('calendar.id_kalender', 'calendar.tahun', 'calendar.namatahun')
            ->distinct()->orderBy('calendar.tahun')->get();

        return view('kkn.validation-detail', compact('record', 'calendars'));
    }

    public function saveValidation(Request $request, string $participant, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeRole($request, self::VALIDATORS);
        $validated = $request->validate([
            'status' => ['required', 'in:1,2'],
            'keterangan' => ['required', 'string', 'max:2000'],
        ]);
        $connection = DB::connection('simak_main');
        $connection->transaction(function () use ($activityLogger, $connection, $participant, $request, $user, $validated): void {
            $record = $connection->table('s1m4k_kubermas')
                ->where('id_peserta', $participant)->lockForUpdate()->first();
            abort_if($record === null, 404);
            $before = ['status' => $record->status, 'keterangan' => $record->keterangan, 'petugas' => $record->petugas];
            $after = [
                'status' => (int) $validated['status'],
                'keterangan' => trim($validated['keterangan']),
                'petugas' => (string) $request->user()->name,
            ];
            $connection->table('s1m4k_kubermas')->where('id_peserta', $participant)->update($after);
            $student = $connection->table('s1m4k_mastermhs')->where('id_mahasiswa', $record->id_mahasiswa)
                ->first(['nim', 'nama_mahasiswa']);
            $activityLogger->record($user, 'KKN', 'Memverifikasi peserta KKN', [
                'sebelum' => $before,
                'sesudah' => $after,
            ], $participant, [
                'jenis' => 'Mahasiswa',
                'nama' => $student?->nama_mahasiswa,
                'nomor_identitas' => $student?->nim,
            ]);
        });

        return redirect()->route('kkn.validations.show', $participant)->with('status', 'Hasil verifikasi KKN berhasil disimpan.');
    }

    public function participants(Request $request): View
    {
        $user = $request->user();
        $this->authorizeRole($request, self::PARTICIPANTS);
        $period = $this->selectedPeriod($request->query('tahun')) ?? $this->activePeriod();
        $rows = $period === null ? null : $this->participantQuery()
            ->leftJoin('s1m4k_lokasi_kubermas as location', 'participant.id_lokasi', '=', 'location.id_lokasi')
            ->where('participant.tahun', $period->tahun)
            ->where('participant.status', 2)
            ->orderBy('program.nama_program_studi')->orderBy('student.nim')
            ->select('participant.*', 'student.nim', 'student.nama_mahasiswa', 'student.jenis_kelamin',
                'program.nama_program_studi', 'location.nama_lokasi')
            ->paginate(25)->withQueryString();
        $locations = DB::connection('simak_main')->table('s1m4k_lokasi_kubermas')
            ->where('aktif', 'Y')->orderBy('nama_lokasi')->get();

        return view('kkn.participants', [
            'user' => $user,
            'periods' => $this->periodsQuery()->get(),
            'period' => $period,
            'rows' => $rows,
            'locations' => $locations,
        ]);
    }

    public function assignLocation(Request $request, string $participant, ActivityLogger $activityLogger): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $this->authorizeRole($request, self::LOCATIONS);
        $validated = $request->validate(['id_lokasi' => ['required', 'string', 'max:100']]);
        $connection = DB::connection('simak_main');
        $connection->transaction(function () use ($activityLogger, $connection, $participant, $user, $validated): void {
            $record = $connection->table('s1m4k_kubermas')->where('id_peserta', $participant)->lockForUpdate()->first();
            abort_if($record === null || (int) $record->status !== 2, 404);
            $location = $connection->table('s1m4k_lokasi_kubermas')
                ->where('id_lokasi', $validated['id_lokasi'])->where('aktif', 'Y')->lockForUpdate()->first();
            abort_if($location === null, 404, 'Lokasi aktif tidak ditemukan.');
            $occupied = $connection->table('s1m4k_kubermas')->where('id_lokasi', $location->id_lokasi)->count();
            if ((string) $record->id_lokasi !== (string) $location->id_lokasi && $occupied >= (int) $location->kuota) {
                throw ValidationException::withMessages(['id_lokasi' => 'Kuota lokasi KKN tersebut sudah penuh.']);
            }
            $connection->table('s1m4k_kubermas')->where('id_peserta', $participant)->update(['id_lokasi' => $location->id_lokasi]);
            $student = $connection->table('s1m4k_mastermhs')->where('id_mahasiswa', $record->id_mahasiswa)
                ->first(['nim', 'nama_mahasiswa']);
            $activityLogger->record($user, 'KKN', 'Menetapkan lokasi peserta KKN', [
                'id_lokasi' => ['sebelum' => $record->id_lokasi, 'sesudah' => $location->id_lokasi],
                'nama_lokasi' => $location->nama_lokasi,
            ], $participant, ['jenis' => 'Mahasiswa', 'nama' => $student?->nama_mahasiswa, 'nomor_identitas' => $student?->nim]);
        });

        $message = 'Lokasi peserta berhasil diperbarui.';
        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return redirect()->route('kkn.participants.index', ['tahun' => $request->input('tahun')])
            ->with('status', $message);
    }

    public function randomPlot(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeRole($request, ['lppm']);
        $validated = $request->validate(['tahun' => ['required', 'regex:/^\d{5}$/']]);
        $connection = DB::connection('simak_main');
        $periodExists = $this->periodsQuery()->where('tahun', $validated['tahun'])->exists();
        abort_unless($periodExists, 404);
        $summary = $connection->transaction(function () use ($activityLogger, $connection, $user, $validated): array {
            $locations = $connection->table('s1m4k_lokasi_kubermas')->where('aktif', 'Y')
                ->orderBy('nama_lokasi')->lockForUpdate()->get(['id_lokasi', 'nama_lokasi', 'kuota']);
            $capacity = [];
            $totalCapacity = 0;
            foreach ($locations as $location) {
                $occupied = $connection->table('s1m4k_kubermas')->where('id_lokasi', $location->id_lokasi)->count();
                $remaining = max(0, (int) $location->kuota - $occupied);
                $capacity[] = ['location' => $location, 'remaining' => $remaining];
                $totalCapacity += $remaining;
            }
            $students = $connection->table('s1m4k_kubermas as participant')
                ->leftJoin('s1m4k_mastermhs as student', 'participant.id_mahasiswa', '=', 'student.id_mahasiswa')
                ->where('participant.tahun', $validated['tahun'])->where('participant.status', 2)
                ->where(fn ($query) => $query->whereNull('participant.id_lokasi')->orWhere('participant.id_lokasi', ''))
                ->lockForUpdate()->get(['participant.id_peserta', 'student.jenis_kelamin']);
            if ($students->isEmpty()) {
                throw ValidationException::withMessages(['tahun' => 'Tidak ada peserta lolos verifikasi yang perlu diplotkan.']);
            }
            if ($students->count() > $totalCapacity) {
                throw ValidationException::withMessages(['tahun' => 'Sisa kuota lokasi aktif belum cukup untuk seluruh peserta.']);
            }

            $men = [];
            $women = [];
            foreach ($students as $student) {
                if ($student->jenis_kelamin === 'L') {
                    $men[] = (string) $student->id_peserta;
                } else {
                    $women[] = (string) $student->id_peserta;
                }
            }
            shuffle($men);
            shuffle($women);
            $queue = [];
            for ($index = 0, $count = max(count($men), count($women)); $index < $count; $index++) {
                if (isset($men[$index])) {
                    $queue[] = $men[$index];
                }
                if (isset($women[$index])) {
                    $queue[] = $women[$index];
                }
            }

            $assignments = [];
            $cursor = 0;
            foreach ($capacity as $slot) {
                for ($index = 0; $index < $slot['remaining'] && isset($queue[$cursor]); $index++, $cursor++) {
                    $participantId = $queue[$cursor];
                    $connection->table('s1m4k_kubermas')->where('id_peserta', $participantId)->update([
                        'id_lokasi' => $slot['location']->id_lokasi,
                    ]);
                    $assignments[] = ['id_peserta' => $participantId, 'id_lokasi' => $slot['location']->id_lokasi];
                }
            }
            $activityLogger->record($user, 'KKN', 'Melakukan ploting acak peserta KKN', [
                'tahun' => $validated['tahun'],
                'jumlah_peserta' => count($assignments),
                'ploting' => $assignments,
            ]);

            return ['count' => count($assignments)];
        });

        return redirect()->route('kkn.participants.index', ['tahun' => $validated['tahun']])
            ->with('status', $summary['count'].' peserta KKN berhasil diplotkan secara acak.');
    }

    public function exportPdf(Request $request): Response
    {
        $this->authorizeRole($request, self::PARTICIPANTS);
        $year = $this->validatedExportYear($request);
        $pdf = new \FPDF('L', 'mm', 'A4');
        $pdf->SetAutoPageBreak(true, 12);
        $pdf->AddPage();
        $text = fn (string $value): string => iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $value) ?: '';
        foreach ([
            [public_path('images/kkn/dikbud.jpg'), 24],
            [app(\App\Services\InstitutionSettings::class)->logoFilePath(), 253],
        ] as [$imagePath, $x]) {
            if (is_file($imagePath)) {
                $pdf->Image($imagePath, $x, 8, 20, 20);
            }
        }
        $pdf->SetY(8);
        $pdf->SetFont('Arial', 'B', 14);
        foreach (app(\App\Services\InstitutionSettings::class)->ministryNameLines() as $ministryLine) {
            $pdf->Cell(277, 7, $text($ministryLine), 0, 1, 'C');
        }
        $pdf->Cell(277, 7, $text(mb_strtoupper(app(\App\Services\InstitutionSettings::class)->institutionName())), 0, 1, 'C');
        $pdf->Ln(2);
        $pdf->Cell(277, 8, $text('DAFTAR PESERTA KULIAH KERJA NYATA (KKN)'), 0, 1, 'C');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(277, 7, $text('Tahun akademik '.$year), 0, 1, 'C');
        $pdf->Ln(4);
        $pdf->SetFont('Arial', 'B', 8);
        foreach ([[10, 'No'], [25, 'NPM'], [62, 'Nama Mahasiswa'], [55, 'Program Studi'], [22, 'Jenis Kelamin'], [85, 'Lokasi']] as [$width, $label]) {
            $pdf->Cell($width, 8, $text($label), 1, 0, 'C');
        }
        $pdf->Ln();
        $pdf->SetFont('Arial', '', 8);
        foreach ($this->acceptedParticipantRows($year) as $index => $row) {
            if ($pdf->GetY() > 185) {
                $pdf->AddPage();
                $pdf->SetFont('Arial', 'B', 8);
                foreach ([[10, 'No'], [25, 'NPM'], [62, 'Nama Mahasiswa'], [55, 'Program Studi'], [22, 'Jenis Kelamin'], [85, 'Lokasi']] as [$width, $label]) {
                    $pdf->Cell($width, 8, $text($label), 1, 0, 'C');
                }
                $pdf->Ln();
                $pdf->SetFont('Arial', '', 8);
            }
            $gender = $row->jenis_kelamin === 'P' ? 'Perempuan' : ($row->jenis_kelamin === 'L' ? 'Laki-laki' : '-');
            foreach ([[10, (string) ($index + 1)], [25, (string) $row->nim], [62, (string) $row->nama_mahasiswa],
                [55, (string) $row->nama_program_studi], [22, $gender], [85, (string) ($row->nama_lokasi ?? '-')]] as [$width, $value]) {
                $pdf->Cell($width, 7, $text($value), 1, 0, $width <= 25 ? 'C' : 'L');
            }
            $pdf->Ln();
        }

        return response($pdf->Output('S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="daftar-peserta-kkn-'.$year.'.pdf"',
        ]);
    }

    public function exportExcel(Request $request): Response
    {
        $this->authorizeRole($request, self::PARTICIPANTS);
        $year = $this->validatedExportYear($request);
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Peserta KKN');
        $sheet->fromArray(['No', 'NPM', 'Nama Mahasiswa', 'Jenis Kelamin', 'Agama', 'Tempat, Tanggal Lahir', 'Alamat', 'Program Studi', 'Lokasi KKN'], null, 'A1');
        foreach ($this->acceptedParticipantRows($year) as $index => $row) {
            $gender = $row->jenis_kelamin === 'P' ? 'Perempuan' : ($row->jenis_kelamin === 'L' ? 'Laki-laki' : '');
            $birthDate = $row->tanggal_lahir ? date('d-m-Y', strtotime((string) $row->tanggal_lahir)) : '';
            $sheet->fromArray([
                $index + 1,
                $row->nim,
                ucwords(mb_strtolower((string) $row->nama_mahasiswa)),
                $gender,
                $row->agama,
                trim((string) $row->tempat_lahir.($birthDate !== '' ? ', '.$birthDate : '')),
                trim(implode(', ', array_filter([(string) $row->kelurahan, (string) $row->kecamatan]))),
                ucwords(mb_strtolower((string) $row->nama_program_studi)),
                ucwords(mb_strtolower((string) ($row->nama_lokasi ?? ''))),
            ], null, 'A'.($index + 2));
        }
        $writer = new Xls($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $contents = (string) ob_get_clean();
        $spreadsheet->disconnectWorksheets();

        return response($contents, 200, [
            'Content-Type' => 'application/vnd.ms-excel',
            'Content-Disposition' => 'attachment; filename="daftar-peserta-kkn-'.$year.'.xls"',
        ]);
    }

    public function studentIndex(Request $request): View
    {
        $this->authorizeRole($request, ['mahasiswa']);
        $connection = DB::connection('simak_main');
        $student = $connection->table('s1m4k_mastermhs as student')
            ->leftJoin('s1m4k_masterprodi as program', 'student.id_prodi', '=', 'program.id_prodi')
            ->leftJoin('s1m4k_masterfakultas as faculty', 'student.id_fakultas', '=', 'faculty.id_fakultas')
            ->where('student.id_mahasiswa', $request->user()->legacy_id)
            ->first([
                'student.id_mahasiswa', 'student.nim', 'student.nama_mahasiswa', 'student.id_prodi',
                'student.id_fakultas', 'student.id_status_mahasiswa', 'program.nama_program_studi', 'faculty.nama_fakultas',
            ]);
        abort_if($student === null, 404);
        $period = $this->activePeriod();
        $eligibility = $period === null ? null : $this->studentEligibility($student, $period);
        $participant = $period === null ? null : $connection->table('s1m4k_kubermas as participant')
            ->leftJoin('s1m4k_lokasi_kubermas as location', 'participant.id_lokasi', '=', 'location.id_lokasi')
            ->where('participant.id_mahasiswa', $student->id_mahasiswa)
            ->where('participant.tahun', $period->tahun)
            ->first(['participant.*', 'location.nama_lokasi']);

        return view('kkn.student', compact('student', 'period', 'eligibility', 'participant'));
    }

    public function registerStudent(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeRole($request, ['mahasiswa']);
        $connection = DB::connection('simak_main');
        $student = $connection->table('s1m4k_mastermhs')
            ->where('id_mahasiswa', $user->legacy_id)
            ->first(['id_mahasiswa', 'nim', 'nama_mahasiswa', 'id_prodi', 'id_fakultas']);
        abort_if($student === null, 404);
        $period = $this->activePeriod();
        if ($period === null || ! $this->canUseActivePeriod($period)) {
            return redirect()->route('kkn.student.index')
                ->with('error', 'Pendaftaran KKN sedang ditutup. Periode KKN belum aktif atau sudah melewati batas tanggal pendaftaran.');
        }

        $connection->transaction(function () use ($activityLogger, $connection, $period, $student, $user): void {
            $lockedStudent = $connection->table('s1m4k_mastermhs')->where('id_mahasiswa', $student->id_mahasiswa)
                ->lockForUpdate()->first(['id_mahasiswa', 'id_prodi', 'id_fakultas']);
            abort_if($lockedStudent === null, 404);
            $eligibility = $this->studentEligibility($lockedStudent, $period);
            if (! $eligibility['eligible']) {
                throw ValidationException::withMessages(['kkn' => 'Persyaratan KKN belum terpenuhi. Periksa SKS lulus, registrasi ulang, dan mata kuliah KKN pada kalender aktif.']);
            }
            $exists = $connection->table('s1m4k_kubermas')->where('id_mahasiswa', $student->id_mahasiswa)
                ->where('tahun', $period->tahun)->exists();
            if ($exists) {
                throw ValidationException::withMessages(['kkn' => 'Anda sudah terdaftar pada periode KKN ini.']);
            }
            $values = [
                'id_peserta' => (string) Str::uuid(),
                'id_fakultas' => (string) $lockedStudent->id_fakultas,
                'id_prodi' => (string) $lockedStudent->id_prodi,
                'id_mahasiswa' => (string) $lockedStudent->id_mahasiswa,
                'tahun' => (int) $period->tahun,
                'tgl_daftar' => today()->toDateString(),
                'status' => 0,
            ];
            $connection->table('s1m4k_kubermas')->insert($values);
            $activityLogger->record($user, 'KKN', 'Mendaftar Kuliah Kerja Nyata', $values, $values['id_peserta'], [
                'jenis' => 'Mahasiswa',
                'nama' => $student->nama_mahasiswa,
                'nomor_identitas' => $student->nim,
            ]);
        });

        return redirect()->route('kkn.student.index')->with('status', 'Pendaftaran KKN berhasil dikirim untuk diverifikasi.');
    }

    private function studentEligibility(object $student, object $period): array
    {
        $connection = DB::connection('simak_main');
        $passedCredits = (float) ($connection->table('s1m4k_akm')
            ->where('id_mahasiswa', $student->id_mahasiswa)
            ->max(DB::raw('CAST(sks_lulus AS SIGNED)')) ?? 0);

        return [
            'passed_credits' => $passedCredits,
            'minimum_credits' => (int) config('simak.kkn.minimum_passed_credits', 100),
            'eligible' => $passedCredits >= (int) config('simak.kkn.minimum_passed_credits', 100),
        ];
    }

    private function acceptedParticipantRows(string $year)
    {
        return $this->participantQuery()
            ->leftJoin('s1m4k_lokasi_kubermas as location', 'participant.id_lokasi', '=', 'location.id_lokasi')
            ->leftJoin('s1m4k_kodereferensi as religion', function ($join): void {
                $join->on('student.id_agama', '=', 'religion.kode_referensi')
                    ->where('religion.keterangan', '=', 'GetAgama');
            })
            ->leftJoin('s1m4k_kodereferensi as region', function ($join): void {
                $join->on('student.id_wilayah', '=', 'region.kode_referensi')
                    ->where('region.keterangan', '=', 'GetWilayah');
            })
            ->where('participant.tahun', $year)->where('participant.status', 2)
            ->orderBy('program.nama_program_studi')->orderBy('student.nim')
            ->addSelect([
                'participant.id_peserta', 'participant.id_lokasi', 'student.nim', 'student.nama_mahasiswa',
                'student.jenis_kelamin', 'student.tempat_lahir', 'student.tanggal_lahir', 'student.kelurahan',
                'program.nama_program_studi', 'location.nama_lokasi', 'religion.ket_referensi as agama',
                'region.ket_referensi2 as kecamatan',
            ])
            ->get();
    }

    private function participantQuery()
    {
        return DB::connection('simak_main')->table('s1m4k_kubermas as participant')
            ->leftJoin('s1m4k_mastermhs as student', 'participant.id_mahasiswa', '=', 'student.id_mahasiswa')
            ->leftJoin('s1m4k_masterprodi as program', 'participant.id_prodi', '=', 'program.id_prodi')
            ->select([
                'participant.id_peserta', 'participant.id_fakultas', 'participant.id_prodi',
                'participant.id_mahasiswa', 'participant.id_lokasi', 'participant.keterangan',
                'participant.tahun', 'participant.status', 'participant.tgl_daftar',
                'participant.petugas', 'participant.petugas_usul', 'student.nim',
                'student.nama_mahasiswa', 'student.jenis_kelamin', 'program.nama_program_studi',
            ]);
    }

    private function proposalCandidates(object $user, object $period, string $search)
    {
        return DB::connection('simak_main')->table('s1m4k_mastermhs as student')
            ->leftJoin('s1m4k_masterprodi as program', 'student.id_prodi', '=', 'program.id_prodi')
            ->whereIn('student.id_status_mahasiswa', ['A', 'N', 'G'])
            ->when($user->role === 'usertu', fn ($query) => $query->where('student.id_prodi', $this->requiredProgramId($user)))
            ->whereRaw("TRIM(COALESCE(student.nim, '')) <> ''")
            ->whereRaw("TRIM(COALESCE(student.nama_mahasiswa, '')) <> ''")
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($match) use ($search): void {
                    $match->where('student.nim', 'like', '%'.$search.'%')
                        ->orWhere('student.nama_mahasiswa', 'like', '%'.$search.'%');
                });
            })
            ->whereNotExists(function ($query) use ($period): void {
                $query->selectRaw('1')->from('s1m4k_kubermas as existing')
                    ->whereColumn('existing.id_mahasiswa', 'student.id_mahasiswa')
                    ->where('existing.tahun', $period->tahun);
            })
            ->whereRaw("TRIM(COALESCE(student.id_mahasiswa, '')) <> ''")
            ->orderBy('program.nama_program_studi')->orderBy('student.nim')
            ->select([
                'student.id_mahasiswa', 'student.id_fakultas', 'student.id_prodi',
                'student.nim', 'student.nama_mahasiswa', 'student.id_periode_masuk',
                'student.nama_status_mahasiswa', 'program.nama_program_studi',
            ])
            ->paginate(25, ['*'], 'students_page')->withQueryString()->appends(['create' => 1]);
    }

    private function participantDetail(string $participant): object
    {
        $record = $this->participantQuery()
            ->leftJoin('s1m4k_masterfakultas as faculty', 'participant.id_fakultas', '=', 'faculty.id_fakultas')
            ->where('participant.id_peserta', $participant)
            ->addSelect([
                'student.email', 'student.handphone', 'student.nama_ibu', 'student.jalan',
                'student.kelurahan', 'faculty.nama_fakultas',
            ])
            ->first();
        abort_if($record === null, 404);

        return $record;
    }

    private function periodsQuery()
    {
        return DB::connection('simak_main')->table('s1m4k_periode')->where('jenis', 'kubermas');
    }

    private function activePeriod(): ?object
    {
        return $this->periodsQuery()->where('aktif', 'Y')->first();
    }

    private function selectedPeriod(mixed $year): ?object
    {
        if (! filled($year)) {
            return $this->activePeriod();
        }
        abort_unless(is_string($year) && preg_match('/^\d{5}$/', $year) === 1, 404);
        $period = $this->periodsQuery()->where('tahun', $year)->first();
        abort_if($period === null, 404, 'Periode KKN tidak ditemukan.');

        return $period;
    }

    private function canUseActivePeriod(?object $period): bool
    {
        if ($period === null) {
            return false;
        }
        $today = today()->toDateString();

        return $today >= (string) $period->tanggal_mulai && $today <= (string) $period->tanggal_akhir;
    }

    private function validatedExportYear(Request $request): string
    {
        $validated = $request->validate(['tahun' => ['required', 'regex:/^\d{5}$/']]);
        abort_unless($this->periodsQuery()->where('tahun', $validated['tahun'])->exists(), 404);

        return $validated['tahun'];
    }

    private function requiredProgramId(object $user): string
    {
        $programId = trim((string) data_get($user, 'id_prodi'));
        abort_unless($programId !== '', 403, 'Akun belum memiliki cakupan program studi.');

        return $programId;
    }

    private function ensureUniqueLocationName(mixed $connection, string $name, ?string $exceptId = null): void
    {
        $exists = $connection->table('s1m4k_lokasi_kubermas')
            ->whereRaw('LOWER(nama_lokasi) = ?', [mb_strtolower(trim($name))])
            ->when($exceptId !== null, fn ($query) => $query->where('id_lokasi', '<>', $exceptId))
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages(['nama_lokasi' => 'Nama lokasi KKN sudah digunakan.']);
        }
    }

    private function authorizeRole(Request $request, array $roles): void
    {
        abort_unless(in_array($request->user()->role, $roles, true), 403);
    }
}
