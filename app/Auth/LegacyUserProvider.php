<?php

namespace App\Auth;

use App\Support\LecturerName;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LegacyUserProvider implements UserProvider
{
    public const ACCOUNTS = [
        'admin' => [
            'table' => 's1m4k_admin',
            'login' => 'username',
            'primary' => 'id_admin',
            'pending_password' => 'passwordb',
            'name' => 'nama_lengkap',
            'role_column' => 'level',
            'prodi' => 'id_prodi',
            'fakultas' => 'id_fakultas',
        ],
        'dosen' => [
            'table' => 's1m4k_masterdosen',
            'login' => 'nidn',
            'primary' => 'id_dosen',
            'pending_password' => 'password_baru',
            'name' => 'nama_dosen',
            'role' => 'dosen',
            'prodi' => 'id_prodi',
            'fakultas' => 'id_fakultas',
        ],
        'asdos' => [
            'table' => 's1m4k_masterasdos',
            'login' => 'kdasisten',
            'primary' => 'id_asdos',
            'pending_password' => 'passwordb',
            'name' => 'nama',
            'role' => 'asdos',
            'prodi' => 'id_prodi',
            'fakultas' => 'id_fakultas',
        ],
        'mahasiswa' => [
            'table' => 's1m4k_mastermhs',
            'login' => 'nim',
            'primary' => 'id_mahasiswa',
            'pending_password' => 'password_baru',
            'name' => 'nama_mahasiswa',
            'role' => 'mahasiswa',
            'prodi' => 'id_prodi',
            'fakultas' => 'id_fakultas',
        ],
    ];

    public function __construct(private readonly string $connection) {}

    public function retrieveById($identifier): ?Authenticatable
    {
        $decoded = base64_decode((string) $identifier, true);
        $identity = $decoded === false ? null : json_decode($decoded, true);

        if (! is_array($identity) || count($identity) !== 2 || ! is_string($identity[0] ?? null) || ! is_string($identity[1] ?? null)) {
            return null;
        }

        return $this->findUser($identity[0], $identity[1]);
    }

    public function retrieveByToken($identifier, #[\SensitiveParameter] $token): ?Authenticatable
    {
        return null;
    }

    public function updateRememberToken(Authenticatable $user, #[\SensitiveParameter] $token): void {}

    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials): ?Authenticatable
    {
        $login = $credentials['login'] ?? null;

        if (! is_string($login) || $login === '') {
            return null;
        }

        if ($this->connectionIsConfigured()
            && DB::connection($this->connection)->table(self::ACCOUNTS['admin']['table'])
                ->where(self::ACCOUNTS['admin']['login'], $login)
                ->where(self::ACCOUNTS['admin']['role_column'], 'admin_ws')->exists()) {
            return null;
        }

        foreach (array_keys(self::ACCOUNTS) as $type) {
            $user = $this->findUser($type, $login, true);

            if ($user !== null) {
                return $user;
            }
        }

        return null;
    }

    public function validateCredentials(Authenticatable $user, #[\SensitiveParameter] array $credentials): bool
    {
        $password = $credentials['password'] ?? null;
        $hashedPassword = $user->getAuthPassword();

        return is_string($password)
            && is_string($hashedPassword)
            && password_verify($password, $hashedPassword);
    }

    public function rehashPasswordIfRequired(Authenticatable $user, #[\SensitiveParameter] array $credentials, bool $force = false): void {}

    private function findUser(string $type, string $identifier, bool $forLogin = false): ?Authenticatable
    {
        $account = self::ACCOUNTS[$type] ?? null;

        if ($account === null) {
            return null;
        }

        if (! $this->connectionIsConfigured()) {
            return null;
        }

        $query = DB::connection($this->connection)->table($account['table']);

        if ($forLogin) {
            $query->where($account['login'], $identifier);
        } else {
            $query->where($account['primary'], $identifier);
        }

        if ($type === 'admin') {
            $query->where('blokir', 'N');
        }

        $columns = [
            $account['primary'].' as legacy_id',
            $account['login'].' as login',
            $account['name'].' as name',
            'email',
            $account['prodi'].' as id_prodi',
            $account['fakultas'].' as id_fakultas',
            'password',
        ];

        if (isset($account['role_column'])) {
            $columns[] = $account['role_column'].' as role';
        }
        if ($type === 'dosen') {
            foreach (['gelar_depan', 'gelar_belakang'] as $titleColumn) {
                if (Schema::connection('simak_main')->hasColumn($account['table'], $titleColumn)) {
                    $columns[] = $titleColumn;
                }
            }
        }

        $record = $query->first($columns);

        if ($record === null) {
            return null;
        }

        $legacyId = (string) $record->legacy_id;
        $name = $type === 'dosen'
            ? LecturerName::format($record->name, $record->gelar_depan ?? null, $record->gelar_belakang ?? null)
            : $record->name;
        $role = $record->role ?? $account['role'] ?? null;
        if ($type === 'admin' && $role === 'admin_ws') {
            return null;
        }
        $userId = base64_encode(json_encode([$type, $legacyId], JSON_THROW_ON_ERROR));

        return new GenericUser([
            'id' => $userId,
            'legacy_type' => $type,
            'legacy_id' => $legacyId,
            'login' => $record->login,
            'role' => $role,
            'name' => $name,
            'email' => $record->email,
            'id_prodi' => $record->id_prodi,
            'id_fakultas' => $record->id_fakultas,
            'password' => $record->password,
            'remember_token' => null,
        ]);
    }

    private function connectionIsConfigured(): bool
    {
        $connection = config('database.connections.'.$this->connection, []);

        if (($connection['driver'] ?? null) !== 'mysql') {
            return true;
        }

        if (! empty($connection['url'])) {
            return true;
        }

        return ! empty($connection['database'])
            && (! empty($connection['host']) || ! empty($connection['unix_socket']));
    }
}
