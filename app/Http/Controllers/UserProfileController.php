<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Services\LegacyFeederService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class UserProfileController extends Controller
{
    private const PROFILE_ROLES = [
        'userprodi', 'usertu', 'regulang', 'keuangan', 'pimuniv', 'pimfak',
        'wisuda', 'validasi', 'lppm', 'timkur', 'dosen', 'asdos', 'mahasiswa',
    ];

    public function edit(Request $request): View
    {
        $profile = $this->profileQuery($request)->first();
        abort_if($profile === null, 404);

        return view('user-profile.edit', [
            'profile' => $profile,
            'fields' => $this->fields((string) $request->user()->legacy_type),
        ]);
    }

    public function update(
        Request $request,
        LegacyFeederService $feeder,
        ActivityLogger $activityLogger
    ): RedirectResponse|JsonResponse {
        $user = $request->user();
        abort_unless(in_array($user->role, self::PROFILE_ROLES, true), 403);
        $type = (string) $user->legacy_type;
        $fields = $this->fields($type);
        $rules = [];
        foreach ($fields as $field => $rule) {
            $rules[$field] = $rule;
        }
        $rules['password'] = ['nullable', 'string', 'min:8', 'max:100'];
        $validated = $request->validate($rules);
        $profileQuery = $this->profileQuery($request);
        $profile = $profileQuery->first();
        abort_if($profile === null, 404);

        $data = array_intersect_key($validated, $fields);
        if (isset($validated['password']) && $validated['password'] !== '') {
            $data['password'] = Hash::make($validated['password']);
        }

        if ($type === 'mahasiswa') {
            try {
                $feederRecord = array_diff_key($data, ['password' => true]);
                $feederRecord['kewarganegaraan'] = $data['id_negara'] ?? '';
                $feeder->updateStudentBiodata((string) $user->legacy_id, $feederRecord);
            } catch (RuntimeException $exception) {
                report($exception);
                throw ValidationException::withMessages([
                    'profile' => 'Profil belum disimpan karena Feeder gagal: '.$exception->getMessage(),
                ]);
            }
        }

        DB::connection('simak_main')->transaction(function () use ($profileQuery, $data, $profile, $activityLogger, $user, $type): void {
            $profileQuery->update($data);
            $before = array_intersect_key((array) $profile, $data);
            $changes = $activityLogger->diff($before, $data);
            if ($changes !== []) {
                $nameColumn = match ($type) {
                    'admin' => 'nama_lengkap',
                    'dosen' => 'nama_dosen',
                    'asdos' => 'nama',
                    'mahasiswa' => 'nama_mahasiswa',
                };
                $loginColumn = match ($type) {
                    'admin' => 'username',
                    'dosen' => 'nidn',
                    'asdos' => 'kdasisten',
                    'mahasiswa' => 'nim',
                };
                $activityLogger->record($user, 'Profil Pengguna', 'Ubah profil', $changes, (string) $user->legacy_id, [
                    'jenis' => ucfirst($type),
                    'nama' => (string) ($data[$nameColumn] ?? $profile->{$nameColumn} ?? ''),
                    'nomor_identitas' => (string) ($profile->{$loginColumn} ?? ''),
                ]);
            }
        });

        if ($request->expectsJson()) {
            $message = 'Profil akun berhasil diperbarui.';
            $request->session()->flash('status', $message);

            return response()->json(['message' => $message]);
        }

        return back()->with('status', 'Profil akun berhasil diperbarui.');
    }

    private function profileQuery(Request $request): Builder
    {
        $user = $request->user();
        abort_unless(in_array($user->role, self::PROFILE_ROLES, true), 403);
        $type = (string) $user->legacy_type;
        [$table, $key] = match ($type) {
            'admin' => ['s1m4k_admin', 'id_admin'],
            'dosen' => ['s1m4k_masterdosen', 'id_dosen'],
            'asdos' => ['s1m4k_masterasdos', 'id_asdos'],
            'mahasiswa' => ['s1m4k_mastermhs', 'id_mahasiswa'],
            default => abort(403),
        };

        return DB::connection('simak_main')->table($table)->where($key, $user->legacy_id);
    }

    private function fields(string $type): array
    {
        return match ($type) {
            'admin' => [
                'nama_lengkap' => ['required', 'string', 'max:100'],
                'email' => ['required', 'email', 'max:50'],
                'no_telp' => ['nullable', 'string', 'max:20'],
            ],
            'dosen' => [
                'nama_dosen' => ['required', 'string', 'max:100'],
                'email' => ['required', 'email', 'max:50'],
                'nip' => ['nullable', 'string', 'max:20'],
            ],
            'asdos' => [
                'nama' => ['required', 'string', 'max:100'],
                'email' => ['required', 'email', 'max:50'],
                'notlp' => ['nullable', 'string', 'max:20'],
            ],
            'mahasiswa' => [
                'email' => ['required', 'email', 'max:50'],
                'nik' => ['nullable', 'string', 'max:16'],
                'id_negara' => ['nullable', 'string', 'max:10'],
                'jalan' => ['nullable', 'string', 'max:80'],
                'rt' => ['nullable', 'string', 'max:2'],
                'rw' => ['nullable', 'string', 'max:2'],
                'dusun' => ['nullable', 'string', 'max:60'],
                'kelurahan' => ['nullable', 'string', 'max:60'],
                'kode_pos' => ['nullable', 'string', 'max:5'],
                'id_wilayah' => ['nullable', 'string', 'max:10'],
                'id_jenis_tinggal' => ['nullable', 'string', 'max:10'],
                'id_alat_transportasi' => ['nullable', 'string', 'max:10'],
                'handphone' => ['nullable', 'string', 'max:20'],
                'telepon' => ['nullable', 'string', 'max:20'],
            ],
            default => abort(403),
        };
    }
}
