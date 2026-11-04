<?php

namespace App\Http\Middleware;

use App\Services\ApiTokenService;
use Closure;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAdminWsToken
{
    public function __construct(private readonly ApiTokenService $tokens) {}

    public function handle(Request $request, Closure $next): Response
    {
        $claims = $request->bearerToken() ? $this->tokens->decode($request->bearerToken()) : null;
        if ($claims === null) {
            return $this->unauthorized();
        }

        $account = DB::connection('simak_main')->table('s1m4k_admin')
            ->where('id_admin', $claims['sub'])
            ->where('level', 'admin_ws')
            ->where('blokir', 'N')
            ->first(['id_admin', 'username', 'nama_lengkap']);
        if ($account === null) {
            return $this->unauthorized();
        }

        $user = new GenericUser([
            'id' => 'admin_ws:'.$account->id_admin,
            'legacy_id' => (string) $account->id_admin,
            'legacy_type' => 'admin_ws',
            'login' => (string) $account->username,
            'name' => (string) ($account->nama_lengkap ?: $account->username),
            'role' => 'admin_ws',
        ]);
        $request->attributes->set('admin_ws_user', $user);
        $request->setUserResolver(static fn () => $user);

        return $next($request);
    }

    private function unauthorized(): JsonResponse
    {
        return response()->json(['message' => 'Token API tidak valid atau sudah kedaluwarsa.'], 401)
            ->header('WWW-Authenticate', 'Bearer')
            ->header('Cache-Control', 'no-store');
    }
}