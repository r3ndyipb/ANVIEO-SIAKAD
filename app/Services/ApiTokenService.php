<?php

namespace App\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use JsonException;

class ApiTokenService
{
    public const LIFETIME_SECONDS = 3600;

    public function issue(string $accountId): string
    {
        return Crypt::encryptString(json_encode([
            'aud' => 'simak-api-v1',
            'sub' => $accountId,
            'jti' => (string) Str::uuid(),
            'iat' => time(),
            'exp' => time() + self::LIFETIME_SECONDS,
        ], JSON_THROW_ON_ERROR));
    }

    /** @return array{aud: string, sub: string, jti: string, iat: int, exp: int}|null */
    public function decode(string $token): ?array
    {
        try {
            $claims = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            return null;
        }

        if (! is_array($claims)
            || ($claims['aud'] ?? null) !== 'simak-api-v1'
            || ! is_string($claims['sub'] ?? null)
            || ! is_string($claims['jti'] ?? null)
            || ! is_int($claims['iat'] ?? null)
            || ! is_int($claims['exp'] ?? null)
            || $claims['exp'] <= time()
            || $claims['iat'] > time() + 60
            || $claims['exp'] - $claims['iat'] > self::LIFETIME_SECONDS) {
            return null;
        }

        return $claims;
    }
}