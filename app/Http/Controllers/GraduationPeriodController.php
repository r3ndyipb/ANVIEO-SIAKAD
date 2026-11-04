<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class GraduationPeriodController extends Controller
{
    private const PROPOSALS = ['admin', 'userprodi', 'usertu', 'wisuda'];

    private const GRADUATION_CANDIDATE_STATUSES = ['A', 'C', 'N', 'M'];

    public function index(Request $request): View
    {
        $user = $this->authorizeRole($request, ['admin', 'wisuda']);
        $canManage = $user->role === 'wisuda';

        return view('graduation-periods.index', compact('canManage'));
    }

    public function graduationProposals(Request $request): View
    {
        $user = $request->user();
        $this->authorizeRole($request, self::PROPOSALS);
        $period = $this->selectedGraduationPeriod($request->query('periode'), $request->query('tahun')) ?? $this->activeGraduationPeriod();
        $canPropose = $period !== null && $this->canUseActiveGraduationPeriod($period);
        $rows = $period === null ? null : $this->graduationProposalRows($period)
            ->when(in_array($user->role, ['userprodi', 'usertu'], true), fn ($query) => $query->where('student.id_prodi', $this->requiredProgramId($user)))
            ->paginate(25)->withQueryString();
        $search = trim((string) $request->query('search', ''));
        $students = $canPropose ? $this->graduationProposalCandidates($user, $period, $search) : null;

        return view('graduation-participants.index', [
            'periods' => $this->graduationPeriodsQuery()->get(),
            'period' => $period,
            'rows' => $rows,
            'canPropose' => $canPropose,
            'students' => $students,
            'search' => $search,
            'openCreate' => $request->boolean('create') && $canPropose,
        ]);
    }

    public function createGraduationProposals(Request $request): RedirectResponse
    {
        $this->authorizeRole($request, self::PROPOSALS);
        $period = $this->activeGraduationPeriod();
        $query = $period === null ? [] : ['periode' => $period->id_periode, 'create' => 1];
        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query['search'] = $search;
        }

        return redirect()->route('graduation-participants.index', $query);
    }

    public function storeGraduationProposals(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeRole($request, self::PROPOSALS);
        $period = $this->activeGraduationPeriod();
        if ($period === null || ! $this->canUseActiveGraduationPeriod($period)) {
            return redirect()->route('graduation-participants.index', $period === null ? [] : ['periode' => $period->id_periode])
                ->with('error', 'Periode wisuda aktif belum dibuka atau sudah berakhir. Pengusulan peserta wisuda ditolak.');
        }
        $validated = $request->validate([
            'students' => ['required', 'array', 'min:1', 'max:500'],
            'students.*' => ['required', 'string', 'max:50', 'distinct'],
        ]);
        $connection = DB::connection('simak_main');
        $created = [];
        $connection->transaction(function () use ($activityLogger, $connection, $period, $user, $validated, &$created): void {
            foreach ($validated['students'] as $studentId) {
                $studentQuery = $connection->table('s1m4k_mastermhs')
                    ->where('id_mahasiswa', $studentId)
                    ->whereIn('id_status_mahasiswa', self::GRADUATION_CANDIDATE_STATUSES);
                if (in_array($user->role, ['userprodi', 'usertu'], true)) {
                    $studentQuery->where('id_prodi', $this->requiredProgramId($user));
                }
                $student = $studentQuery->lockForUpdate()->first([
                    'id_mahasiswa', 'id_fakultas', 'id_prodi', 'nim', 'nama_mahasiswa',
                ]);
                abort_if($student === null, 404, 'Mahasiswa tidak tersedia dalam cakupan pengusulan.');
                $exists = $connection->table('s1m4k_wisuda')
                    ->where('id_mahasiswa', $studentId)
                    ->where('id_periode', $period->id_periode)
                    ->exists();
                if ($exists) {
                    continue;
                }
                $values = [
                    'id_peserta' => (string) Str::uuid(),
                    'id_periode' => (string) $period->id_periode,
                    'id_fakultas' => (string) $student->id_fakultas,
                    'id_prodi' => (string) $student->id_prodi,
                    'id_mahasiswa' => (string) $student->id_mahasiswa,
                    'status' => 0,
                    'tgl_daftar' => today()->toDateString(),
                    'petugas' => (string) $user->name,
                ];
                $connection->table('s1m4k_wisuda')->insert($values);
                $created[] = [
                    'values' => $values,
                    'identity' => [
                        'jenis' => 'Mahasiswa',
                        'nama' => $student->nama_mahasiswa,
                        'nomor_identitas' => $student->nim,
                    ],
                ];
            }
            foreach ($created as $item) {
                $activityLogger->record($user, 'Wisuda', 'Mengusulkan peserta wisuda', $item['values'], $item['values']['id_peserta'], $item['identity']);
            }
        });

        return redirect()->route('graduation-participants.index', ['periode' => $period->id_periode])
            ->with('status', count($created).' mahasiswa berhasil diusulkan sebagai peserta wisuda.');
    }

    public function data(Request $request): JsonResponse
    {
        $user = $this->authorizeRole($request, ['admin', 'wisuda']);
        $canManage = $user->role === 'wisuda';
        $request->validate([
            'draw' => ['nullable', 'integer', 'min:0'],
            'start' => ['nullable', 'integer', 'min:0'],
            'length' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search.value' => ['nullable', 'string', 'max:100'],
            'order.0.column' => ['nullable', 'integer', 'min:0', 'max:4'],
            'order.0.dir' => ['nullable', 'in:asc,desc'],
        ]);

        $query = DB::connection('simak_main')->table('s1m4k_periode')
            ->where('jenis', 'wisuda');
        $recordsTotal = (clone $query)->count();
        $search = trim((string) $request->input('search.value', ''));
        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $like = '%'.$search.'%';
                $builder->where('nama_periode', 'like', $like)
                    ->orWhere('tanggal_mulai', 'like', $like)
                    ->orWhere('tanggal_akhir', 'like', $like)
                    ->orWhere('kuota_wisuda', 'like', $like)
                    ->orWhere('aktif', 'like', $like);
            });
        }
        $recordsFiltered = (clone $query)->count();
        $sortFields = ['nama_periode', 'tanggal_mulai', 'tanggal_akhir', 'kuota_wisuda', 'aktif'];
        $sortIndex = (int) $request->input('order.0.column', 1);
        $sortDirection = $request->input('order.0.dir') === 'asc' ? 'asc' : 'desc';
        $start = max(0, (int) $request->input('start', 0));
        $length = min(100, max(10, (int) $request->input('length', 25)));
        $records = $query->orderBy($sortFields[$sortIndex], $sortDirection)
            ->offset($start)->limit($length)
            ->get(['id_periode', 'nama_periode', 'tanggal_mulai', 'tanggal_akhir', 'kuota_wisuda', 'aktif']);

        return response()->json([
            'draw' => max(0, (int) $request->input('draw', 0)),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $records->map(fn (object $record): array => [
                'id_periode' => $record->id_periode,
                'nama_periode' => $record->nama_periode,
                'tanggal_mulai' => $record->tanggal_mulai,
                'tanggal_akhir' => $record->tanggal_akhir,
                'kuota_wisuda' => $record->kuota_wisuda,
                'aktif' => $record->aktif,
                'update_url' => $canManage ? route('graduation-periods.update', $record->id_periode) : null,
                'activate_url' => $canManage ? route('graduation-periods.activate', $record->id_periode) : null,
            ]),
            'can_manage' => $canManage,
        ]);
    }

    public function store(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $this->authorizePeriodManager($request);
        $validated = $this->validatePeriod($request);
        $record = [
            'id_periode' => (string) Str::uuid(),
            'nama_periode' => trim($validated['nama_periode']),
            'tanggal_mulai' => $validated['tanggal_mulai'],
            'tanggal_akhir' => $validated['tanggal_akhir'],
            'kuota_wisuda' => $validated['kuota_wisuda'],
            'jenis' => 'wisuda',
            'aktif' => 'N',
        ];
        DB::connection('simak_main')->table('s1m4k_periode')->insert($record);
        $activityLogger->record($user, 'Periode Wisuda', 'Menambahkan periode wisuda', [
            'data_baru' => $record,
        ], $record['id_periode'], ['nama' => $record['nama_periode']]);

        return redirect()->route('graduation-periods.index')->with('status', 'Periode wisuda berhasil ditambahkan.');
    }

    public function update(Request $request, string $period, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $this->authorizePeriodManager($request);
        $validated = $this->validatePeriod($request);
        $query = DB::connection('simak_main')->table('s1m4k_periode')
            ->where('id_periode', $period)
            ->where('jenis', 'wisuda');
        $record = $query->first();
        abort_if($record === null, 404);
        $values = [
            'nama_periode' => trim($validated['nama_periode']),
            'tanggal_mulai' => $validated['tanggal_mulai'],
            'tanggal_akhir' => $validated['tanggal_akhir'],
            'kuota_wisuda' => $validated['kuota_wisuda'],
        ];
        $query->update($values);
        $activityLogger->record($user, 'Periode Wisuda', 'Mengubah periode wisuda', [
            'data_sebelumnya' => (array) $record,
            'data_baru' => $values,
        ], $period, ['nama' => $values['nama_periode']]);

        return redirect()->route('graduation-periods.index')->with('status', 'Periode wisuda berhasil diperbarui.');
    }

    public function activate(Request $request, string $period, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $this->authorizePeriodManager($request);
        $connection = DB::connection('simak_main');
        $connection->transaction(function () use ($connection, $period, $activityLogger, $user): void {
            $record = $connection->table('s1m4k_periode')
                ->where('id_periode', $period)
                ->where('jenis', 'wisuda')
                ->lockForUpdate()
                ->first();
            abort_if($record === null, 404);
            $previous = $connection->table('s1m4k_periode')
                ->where('jenis', 'wisuda')->where('aktif', 'Y')->pluck('id_periode')->all();
            $connection->table('s1m4k_periode')->where('jenis', 'wisuda')->update(['aktif' => 'N']);
            $connection->table('s1m4k_periode')
                ->where('id_periode', $period)->where('jenis', 'wisuda')->update(['aktif' => 'Y']);
            $activityLogger->record($user, 'Periode Wisuda', 'Mengaktifkan periode wisuda', [
                'sebelumnya' => $previous,
                'aktif' => $period,
            ], $period, ['nama' => $record->nama_periode]);
        });

        return redirect()->route('graduation-periods.index')->with('status', 'Periode wisuda berhasil diaktifkan.');
    }

    private function graduationProposalRows(object $period)
    {
        return DB::connection('simak_main')->table('s1m4k_wisuda as participant')
            ->join('s1m4k_mastermhs as student', 'participant.id_mahasiswa', '=', 'student.id_mahasiswa')
            ->leftJoin('s1m4k_masterprodi as program', 'participant.id_prodi', '=', 'program.id_prodi')
            ->where('participant.id_periode', $period->id_periode)
            ->select([
                'participant.id_peserta', 'participant.id_mahasiswa', 'participant.status', 'participant.tgl_daftar',
                'participant.petugas', 'participant.komentar', 'student.nim', 'student.nama_mahasiswa',
                'program.nama_program_studi',
            ])
            ->orderBy('program.nama_program_studi')->orderBy('student.nim');
    }

    private function graduationProposalCandidates(object $user, object $period, string $search)
    {
        return DB::connection('simak_main')->table('s1m4k_mastermhs as student')
            ->leftJoin('s1m4k_masterprodi as program', 'student.id_prodi', '=', 'program.id_prodi')
            ->whereIn('student.id_status_mahasiswa', self::GRADUATION_CANDIDATE_STATUSES)
            ->when(in_array($user->role, ['userprodi', 'usertu'], true), fn ($query) => $query->where('student.id_prodi', $this->requiredProgramId($user)))
            ->whereRaw("TRIM(COALESCE(student.nim, '')) <> ''")
            ->whereRaw("TRIM(COALESCE(student.nama_mahasiswa, '')) <> ''")
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($match) use ($search): void {
                    $match->where('student.nim', 'like', '%'.$search.'%')
                        ->orWhere('student.nama_mahasiswa', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('program.nama_program_studi')->orderBy('student.nim')
            ->select([
                'student.id_mahasiswa', 'student.id_fakultas', 'student.id_prodi',
                'student.nim', 'student.nama_mahasiswa', 'student.id_periode_masuk',
                'student.nama_status_mahasiswa', 'program.nama_program_studi',
            ])
            ->selectSub(function ($query) use ($period): void {
                $query->selectRaw('1')->from('s1m4k_wisuda as existing')
                    ->whereColumn('existing.id_mahasiswa', 'student.id_mahasiswa')
                    ->where('existing.id_periode', $period->id_periode)
                    ->limit(1);
            }, 'already_registered')
            ->paginate(25, ['*'], 'students_page')->withQueryString()->appends(['create' => 1]);
    }

    private function graduationPeriodsQuery()
    {
        return DB::connection('simak_main')->table('s1m4k_periode')->where('jenis', 'wisuda');
    }

    private function activeGraduationPeriod(): ?object
    {
        return $this->graduationPeriodsQuery()->where('aktif', 'Y')->first();
    }

    private function selectedGraduationPeriod(mixed $periodId, mixed $year): ?object
    {
        if (filled($periodId)) {
            abort_unless(is_string($periodId) && strlen($periodId) <= 100, 404);
            $period = $this->graduationPeriodsQuery()->where('id_periode', $periodId)->first();
            abort_if($period === null, 404, 'Periode wisuda tidak ditemukan.');

            return $period;
        }

        if (! filled($year)) {
            return $this->activeGraduationPeriod();
        }
        abort_unless(is_string($year) && preg_match('/^\d{5}$/', $year) === 1, 404);
        $period = $this->graduationPeriodsQuery()->where('tahun', $year)->first();
        abort_if($period === null, 404, 'Periode wisuda tidak ditemukan.');

        return $period;
    }

    private function canUseActiveGraduationPeriod(?object $period): bool
    {
        if ($period === null) {
            return false;
        }
        $today = today()->toDateString();

        return $period->aktif === 'Y'
            && $today >= (string) $period->tanggal_mulai
            && $today <= (string) $period->tanggal_akhir;
    }

    private function requiredProgramId(object $user): string
    {
        $programId = trim((string) data_get($user, 'id_prodi'));
        abort_unless($programId !== '', 403, 'Akun belum memiliki cakupan program studi.');

        return $programId;
    }

    private function validatePeriod(Request $request): array
    {
        return $request->validate([
            'nama_periode' => ['required', 'string', 'max:100'],
            'tanggal_mulai' => ['required', 'date_format:Y-m-d'],
            'tanggal_akhir' => ['required', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
            'kuota_wisuda' => ['required', 'integer', 'min:1', 'max:9999'],
        ]);
    }

    private function authorizeRole(Request $request, array $roles): object
    {
        $user = $request->user();
        abort_unless($user !== null && in_array($user->role, $roles, true), 403);

        return $user;
    }

    private function authorizePeriodManager(Request $request): object
    {
        return $this->authorizeRole($request, ['wisuda']);
    }
}
