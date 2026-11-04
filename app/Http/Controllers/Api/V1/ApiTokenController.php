<?php

namespace App\Http\Controllers\Api\V1;

use App\Auth\LegacyUserProvider;
use App\Http\Controllers\Controller;
use App\Services\ApiTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class ApiTokenController extends Controller
{
    private const SSO_BIODATA_FIELDS = [
        'dosen' => [
            'id_registrasi_dosen', 'nidn', 'nip', 'nama_dosen', 'gelar_depan', 'gelar_belakang',
            'jenis_kelamin', 'id_agama', 'tanggal_lahir', 'email', 'telepon', 'handphone',
            'id_status_aktif', 'nama_status_aktif', 'nomor_surat_tugas', 'tanggal_surat_tugas',
            'mulai_surat_tugas', 'id_prodi', 'id_fakultas', 'id_perguruan_tinggi',
        ],
        'asdos' => [
            'kdasisten', 'nama', 'gelard', 'gelarb', 'jkel', 'email', 'notlp',
            'status', 'id_prodi', 'id_fakultas',
        ],
        'mahasiswa' => [
            'id_registrasi_mahasiswa', 'nim', 'nama_mahasiswa', 'id_perguruan_tinggi',
            'id_fakultas', 'id_prodi', 'id_status_mahasiswa', 'nama_status_mahasiswa',
            'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'id_agama', 'nisn',
            'jalan', 'dusun', 'rt', 'rw', 'kelurahan', 'kode_pos', 'id_negara', 'id_wilayah',
            'telepon', 'handphone', 'email', 'id_periode_masuk', 'tanggal_daftar',
            'id_jenis_daftar', 'id_jalur_daftar', 'id_pembiayaan', 'dosen_pa',
        ],
    ];

    public function issue(Request $request, ApiTokenService $tokens): JsonResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $account = DB::connection('simak_main')->table('s1m4k_admin')
            ->where('username', $credentials['username'])
            ->where('level', 'admin_ws')
            ->where('blokir', 'N')
            ->first(['id_admin', 'username', 'password']);

        if ($account === null || ! Hash::check($credentials['password'], (string) $account->password)) {
            return response()->json(['pesan' => 'Kredensial API tidak valid.'], 401);
        }

        return response()->json([
            'akses_token' => $tokens->issue((string) $account->id_admin),
            'tipe_token' => 'Bearer',
            'kedaluwarsa_dalam' => ApiTokenService::LIFETIME_SECONDS,
        ])->header('Cache-Control', 'no-store');
    }

    public function sso(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'login' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $provider = Auth::createUserProvider((string) config('auth.guards.web.provider'));
        $user = $provider?->retrieveByCredentials($credentials);
        if ($provider === null || $user === null || ! $provider->validateCredentials($user, $credentials)) {
            return response()->json(['message' => 'Identitas atau kata sandi tidak valid.'], 401)
                ->header('Cache-Control', 'no-store');
        }

        $biodata = [
            'jenis_akun' => (string) $user->legacy_type,
            'identitas_login' => (string) $user->login,
            'nama' => (string) ($user->name ?? ''),
            'email' => (string) ($user->email ?? ''),
            'id_prodi' => $user->id_prodi,
            'id_fakultas' => $user->id_fakultas,
        ];
        if ($user->legacy_type !== 'admin') {
            $biodata = [...$biodata, ...$this->additionalBiodata($user)];
        }

        return response()->json([
            'success' => true,
            'user' => [
                'id' => (string) $user->legacy_id,
                'level' => (string) $user->role,
                'biodata' => $biodata,
            ],
        ])->header('Cache-Control', 'no-store');
    }

    private function additionalBiodata(object $user): array
    {
        $type = (string) $user->legacy_type;
        $account = LegacyUserProvider::ACCOUNTS[$type] ?? null;
        $fields = self::SSO_BIODATA_FIELDS[$type] ?? null;
        if ($account === null || $fields === null) {
            return [];
        }

        $table = $account['table'];
        $connection = DB::connection('simak_main');
        $columns = array_values(array_filter(
            $fields,
            static fn (string $field): bool => Schema::connection('simak_main')->hasColumn($table, $field),
        ));
        if ($columns === []) {
            return [];
        }

        $record = $connection->table($table)
            ->where($account['primary'], (string) $user->legacy_id)
            ->first($columns);
        if ($record === null) {
            return [];
        }

        $biodata = array_fill_keys($fields, null);
        foreach ($columns as $column) {
            $biodata[$column] = $record->{$column};
        }

        $programId = trim((string) ($biodata['id_prodi'] ?? ''));
        if ($programId !== ''
            && Schema::connection('simak_main')->hasTable('s1m4k_masterprodi')
            && Schema::connection('simak_main')->hasColumn('s1m4k_masterprodi', 'nama_program_studi')) {
            $programColumns = ['nama_program_studi'];
            if (Schema::connection('simak_main')->hasColumn('s1m4k_masterprodi', 'nama_jenjang_pendidikan')) {
                $programColumns[] = 'nama_jenjang_pendidikan';
            }
            $program = $connection->table('s1m4k_masterprodi')->where('id_prodi', $programId)
                ->first($programColumns);
            if ($program !== null) {
                $biodata['nama_program_studi'] = $program->nama_program_studi;
                $biodata['jenjang_pendidikan'] = $program->nama_jenjang_pendidikan ?? null;
            }
        }

        $facultyId = trim((string) ($biodata['id_fakultas'] ?? ''));
        if ($facultyId !== ''
            && Schema::connection('simak_main')->hasTable('s1m4k_masterfakultas')
            && Schema::connection('simak_main')->hasColumn('s1m4k_masterfakultas', 'nama_fakultas')) {
            $biodata['nama_fakultas'] = $connection->table('s1m4k_masterfakultas')
                ->where('id_fakultas', $facultyId)->value('nama_fakultas');
        }

        return $biodata;
    }
}
