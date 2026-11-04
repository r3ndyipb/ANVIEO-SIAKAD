<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SimakUserManagementController extends Controller
{
    private const TYPES = [
        'useradmin' => 'Administrator',
        'userpim' => 'Pimpinan',
        'userprodi' => 'Pengelola Program Studi',
        'usertu' => 'Operator Prodi',
        'userregulang' => 'Registrasi Ulang',
        'userkeu' => 'Keuangan',
        'userwisuda' => 'Wisuda',
        'uservalidasi' => 'Validasi',
        'userlppm' => 'LPPM',
    ];

    private const FIXED_LEVELS = [
        'useradmin' => 'admin',
        'userprodi' => 'userprodi',
        'usertu' => 'usertu',
        'userregulang' => 'regulang',
        'userkeu' => 'keuangan',
        'userwisuda' => 'wisuda',
        'uservalidasi' => 'validasi',
        'userlppm' => 'lppm',
    ];

    public function index(Request $request): View
    {
        $this->authorizeAdmin($request->user());
        $type = $request->query('type', 'useradmin');
        $this->assertType($type);

        return view('simak-users.index', [
            'type' => $type,
            'types' => self::TYPES,
            'createUrl' => route('simak-users.create', ['type' => $type, 'modal' => 1]),
            'dataUrl' => route('simak-users.data', ['type' => $type]),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request->user());
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:'.implode(',', array_keys(self::TYPES))],
            'draw' => ['nullable', 'integer', 'min:0'],
            'start' => ['nullable', 'integer', 'min:0'],
            'length' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search.value' => ['nullable', 'string', 'max:100'],
            'order.0.column' => ['nullable', 'integer', 'min:0', 'max:5'],
            'order.0.dir' => ['nullable', 'in:asc,desc'],
        ]);
        $type = $validated['type'];
        $query = $this->accountQuery($type);
        $recordsTotal = (clone $query)->count();
        $search = trim((string) $request->input('search.value', ''));
        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $like = '%'.$search.'%';
                $builder->where('account.username', 'like', $like)
                    ->orWhere('account.nama_lengkap', 'like', $like)
                    ->orWhere('account.email', 'like', $like)
                    ->orWhere('account.no_telp', 'like', $like)
                    ->orWhere('program.nama_program_studi', 'like', $like)
                    ->orWhere('faculty.nama_fakultas', 'like', $like);
            });
        }
        $recordsFiltered = (clone $query)->count();
        $sortFields = ['account.username', 'account.nama_lengkap', 'account.email', 'account.no_telp', 'program.nama_program_studi', 'account.blokir'];
        $sortIndex = (int) $request->input('order.0.column', 0);
        $sortDirection = $request->input('order.0.dir') === 'desc' ? 'desc' : 'asc';
        $start = max(0, (int) $request->input('start', 0));
        $length = min(100, max(10, (int) $request->input('length', 25)));
        $records = $query->orderBy($sortFields[$sortIndex], $sortDirection)
            ->offset($start)->limit($length)->get();

        return response()->json([
            'draw' => max(0, (int) $request->input('draw', 0)),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $records->map(fn (object $account): array => [
                'username' => $account->username,
                'nama_lengkap' => $account->nama_lengkap,
                'email' => $account->email,
                'no_telp' => $account->no_telp,
                'scope' => $account->level === 'pimfak'
                    ? ($account->nama_fakultas ?: 'Fakultas belum ditetapkan')
                    : (in_array($account->level, ['userprodi', 'usertu'], true)
                        ? ($account->nama_program_studi ? $account->nama_program_studi.' ('.$account->nama_jenjang_pendidikan.')' : 'Program studi belum ditetapkan')
                        : 'Seluruh SIMAK'),
                'blokir' => $account->blokir,
                'edit_url' => route('simak-users.edit', ['type' => $type, 'account' => $account->id_admin, 'modal' => 1]),
            ]),
        ]);
    }

    public function create(Request $request, string $type): View
    {
        $this->authorizeAdmin($request->user());
        $this->assertType($type);

        $viewData = [
            'type' => $type,
            'typeLabel' => self::TYPES[$type],
            'account' => null,
            'faculties' => $this->faculties(),
            'programs' => $this->programs(),
            'formAction' => route('simak-users.store', ['type' => $type]),
            'formMethod' => 'POST',
            'modal' => $request->boolean('modal'),
        ];

        return $request->boolean('modal')
            ? view('simak-users.fields', $viewData)
            : view('simak-users.form', $viewData);
    }

    public function store(Request $request, string $type, ActivityLogger $activityLogger): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $this->authorizeAdmin($user);
        $this->assertType($type);
        $validated = $this->validateAccount($request, $type, true);
        $this->assertUsernameAvailable($validated['username']);
        $scope = $this->organizationScope($type, $validated);
        $level = $this->level($type, $validated);
        $values = [
            'username' => trim($validated['username']),
            'password' => Hash::make($validated['password']),
            'nama_lengkap' => trim($validated['nama_lengkap']),
            'level' => $level,
            'id_prodi' => $scope['id_prodi'],
            'id_fakultas' => $scope['id_fakultas'],
            'no_telp' => trim((string) ($validated['no_telp'] ?? '')),
            'email' => trim($validated['email']),
            'blokir' => 'N',
            'created_at' => now(),
            'updated_at' => now(),
        ];
        $connection = DB::connection('simak_main');
        $id = $connection->table('s1m4k_admin')->insertGetId($values);
        $activityLogger->record($user, 'Pengguna SIMAK', 'Menambahkan akun '.$level, [
            'data_baru' => $this->safeAccount($values),
        ], (string) $id, ['nama' => $values['nama_lengkap'], 'username' => $values['username'], 'level' => $level]);

        $message = 'Akun '.self::TYPES[$type].' berhasil dibuat.';
        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return redirect()->route('simak-users.index', ['type' => $type])->with('status', $message);
    }

    public function edit(Request $request, string $type, string $accountId): View
    {
        $this->authorizeAdmin($request->user());
        $this->assertType($type);
        $account = $this->findAccount($type, $accountId);

        $viewData = [
            'type' => $type,
            'typeLabel' => self::TYPES[$type],
            'account' => $account,
            'faculties' => $this->faculties(),
            'programs' => $this->programs(),
            'formAction' => route('simak-users.update', ['type' => $type, 'account' => $account->id_admin]),
            'formMethod' => 'PUT',
            'modal' => $request->boolean('modal'),
        ];

        return $request->boolean('modal')
            ? view('simak-users.fields', $viewData)
            : view('simak-users.form', $viewData);
    }

    public function update(Request $request, string $type, string $accountId, ActivityLogger $activityLogger): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $this->authorizeAdmin($user);
        $this->assertType($type);
        $account = $this->findAccount($type, $accountId);
        $validated = $this->validateAccount($request, $type, false);
        $scope = $this->organizationScope($type, $validated);
        $level = $this->level($type, $validated);
        $values = [
            'nama_lengkap' => trim($validated['nama_lengkap']),
            'email' => trim($validated['email']),
            'no_telp' => trim((string) ($validated['no_telp'] ?? '')),
            'blokir' => $validated['blokir'],
            'level' => $level,
            'id_prodi' => $scope['id_prodi'],
            'id_fakultas' => $scope['id_fakultas'],
            'updated_at' => now(),
        ];
        if (filled($validated['password'] ?? null)) {
            $values['password'] = Hash::make($validated['password']);
        }
        DB::connection('simak_main')->table('s1m4k_admin')->where('id_admin', $account->id_admin)->update($values);
        $after = DB::connection('simak_main')->table('s1m4k_admin')->where('id_admin', $account->id_admin)->first();
        $activityLogger->record($user, 'Pengguna SIMAK', 'Memperbarui akun '.$level, [
            'data_sebelumnya' => $this->safeAccount((array) $account),
            'data_baru' => $this->safeAccount((array) $after),
            'password_diubah' => filled($validated['password'] ?? null),
        ], (string) $account->id_admin, [
            'nama' => $after->nama_lengkap,
            'username' => $after->username,
            'level' => $level,
        ]);

        $message = 'Data akun berhasil diperbarui.';
        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return redirect()->route('simak-users.index', ['type' => $type])->with('status', $message);
    }

    private function validateAccount(Request $request, string $type, bool $creating): array
    {
        $rules = [
            'nama_lengkap' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:50'],
            'no_telp' => ['nullable', 'string', 'max:20'],
            'password' => $creating ? ['required', 'string', 'min:8', 'max:100'] : ['nullable', 'string', 'min:8', 'max:100'],
        ];
        if ($creating) {
            $rules['username'] = ['required', 'string', 'max:100', 'not_regex:/\s/'];
        } else {
            $rules['blokir'] = ['required', 'in:Y,N'];
        }
        if ($type === 'userpim') {
            $rules['level'] = ['required', 'in:pimuniv,pimfak'];
            $rules['id_fakultas'] = ['nullable', 'string', 'max:100'];
        } elseif (in_array($type, ['userprodi', 'usertu'], true)) {
            $rules['id_prodi'] = ['required', 'string', 'max:100'];
        }
        $validated = $request->validate($rules);
        if ($creating) {
            $this->assertUsernameAvailable($validated['username']);
        }

        return $validated;
    }

    private function accountQuery(string $type)
    {
        $query = DB::connection('simak_main')->table('s1m4k_admin as account')
            ->leftJoin('s1m4k_masterprodi as program', 'account.id_prodi', '=', 'program.id_prodi')
            ->leftJoin('s1m4k_masterfakultas as faculty', 'account.id_fakultas', '=', 'faculty.id_fakultas')
            ->select([
                'account.id_admin', 'account.username', 'account.nama_lengkap', 'account.email',
                'account.no_telp', 'account.blokir', 'account.level', 'account.id_prodi',
                'account.id_fakultas', 'program.nama_program_studi', 'program.nama_jenjang_pendidikan',
                'faculty.nama_fakultas',
            ]);
        if ($type === 'userpim') {
            return $query->whereIn('account.level', ['pimuniv', 'pimfak']);
        }

        return $query->where('account.level', self::FIXED_LEVELS[$type]);
    }

    private function assertUsernameAvailable(string $username, ?string $ignoreId = null): void
    {
        $query = DB::connection('simak_main')->table('s1m4k_admin')->where('username', trim($username));
        if ($ignoreId !== null) {
            $query->where('id_admin', '<>', $ignoreId);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages([
                'username' => 'Username sudah digunakan oleh akun SIMAK lain.',
            ]);
        }
    }

    private function level(string $type, array $validated): string
    {
        if ($type === 'userpim') {
            return $validated['level'];
        }
        $levels = [
            'useradmin' => 'admin', 'userprodi' => 'userprodi', 'usertu' => 'usertu',
            'userregulang' => 'regulang', 'userkeu' => 'keuangan', 'userwisuda' => 'wisuda',
            'uservalidasi' => 'validasi', 'userlppm' => 'lppm',
        ];

        return $levels[$type];
    }

    private function organizationScope(string $type, array $validated): array
    {
        if (in_array($type, ['userprodi', 'usertu'], true)) {
            $program = DB::connection('simak_main')->table('s1m4k_masterprodi')
                ->where('id_prodi', $validated['id_prodi'])
                ->first(['id_prodi', 'id_fakultas']);
            abort_if($program === null, 422, 'Program studi tidak ditemukan.');

            return ['id_prodi' => $program->id_prodi, 'id_fakultas' => $program->id_fakultas];
        }
        if ($type === 'userpim' && $validated['level'] === 'pimfak') {
            $facultyId = trim((string) ($validated['id_fakultas'] ?? ''));
            abort_unless($facultyId !== '' && DB::connection('simak_main')->table('s1m4k_masterfakultas')
                ->where('id_fakultas', $facultyId)->exists(), 422, 'Fakultas pimpinan tidak ditemukan.');

            return ['id_prodi' => null, 'id_fakultas' => $facultyId];
        }

        return ['id_prodi' => null, 'id_fakultas' => null];
    }

    private function findAccount(string $type, string $accountId): object
    {
        $query = DB::connection('simak_main')->table('s1m4k_admin')->where('id_admin', $accountId);
        if ($type === 'userpim') {
            $query->whereIn('level', ['pimuniv', 'pimfak']);
        } else {
            $query->where('level', [
                'useradmin' => 'admin', 'userprodi' => 'userprodi', 'usertu' => 'usertu',
                'userregulang' => 'regulang', 'userkeu' => 'keuangan', 'userwisuda' => 'wisuda',
                'uservalidasi' => 'validasi', 'userlppm' => 'lppm',
            ][$type]);
        }
        $account = $query->first();
        abort_if($account === null, 404);

        return $account;
    }

    private function faculties()
    {
        return DB::connection('simak_main')->table('s1m4k_masterfakultas')
            ->orderBy('nama_fakultas')->get(['id_fakultas', 'nama_fakultas']);
    }

    private function programs()
    {
        return DB::connection('simak_main')->table('s1m4k_masterprodi as program')
            ->leftJoin('s1m4k_masterfakultas as faculty', 'program.id_fakultas', '=', 'faculty.id_fakultas')
            ->orderBy('program.nama_program_studi')
            ->get(['program.id_prodi', 'program.id_fakultas', 'program.nama_program_studi', 'program.nama_jenjang_pendidikan', 'faculty.nama_fakultas']);
    }

    private function safeAccount(array $account): array
    {
        unset($account['password'], $account['passwordb'], $account['password_baru'], $account['id_session']);

        return $account;
    }

    private function assertType(string $type): void
    {
        abort_unless(array_key_exists($type, self::TYPES), 404);
    }

    private function authorizeAdmin(?object $user): void
    {
        abort_unless($user?->role === 'admin', 403);
    }
}
