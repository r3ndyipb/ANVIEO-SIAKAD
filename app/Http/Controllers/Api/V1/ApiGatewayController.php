<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use App\Services\ApiOperationCatalog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\ControllerDispatcher;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class ApiGatewayController extends Controller
{
    public function dispatch(Request $request, string $path, ApiOperationCatalog $catalog): JsonResponse
    {
        $uri = ApiOperationCatalog::internalPath('/'.ltrim($path, '/'));
        $innerRequest = Request::create(
            $uri.($request->getQueryString() ? '?'.$request->getQueryString() : ''),
            $request->method(),
            $request->request->all(),
            $request->cookies->all(),
            $request->allFiles(),
            $request->server->all(),
            $request->getContent(),
        );
        $innerRequest->headers->replace($request->headers->all());
        $innerRequest->headers->set('Accept', 'application/json');
        $apiUser = $request->attributes->get('admin_ws_user');
        abort_unless($apiUser instanceof GenericUser, 401);
        $businessUser = new GenericUser([
            'id' => $apiUser->getAuthIdentifier(),
            'legacy_id' => (string) $apiUser->legacy_id,
            'legacy_type' => 'admin_ws',
            'login' => (string) $apiUser->login,
            'name' => (string) $apiUser->name,
            'email' => (string) ($apiUser->email ?? ''),
            'role' => 'admin',
            'audit_role' => 'admin_ws',
        ]);
        $innerRequest->setUserResolver(static fn () => $businessUser);

        try {
            $targetRoute = app('router')->getRoutes()->match($innerRequest);
        } catch (HttpExceptionInterface) {
            return $this->translatedJson(['pesan' => 'Operasi API tidak ditemukan.'], 404);
        }

        if ($catalog->find((string) $targetRoute->getName(), $innerRequest->method()) === null) {
            return $this->translatedJson(['pesan' => 'Operasi ini tidak tersedia melalui API.'], 403);
        }
        if (str_starts_with((string) $targetRoute->getName(), 'lms.') && $request->allFiles() !== []) {
            return $this->translatedJson(['pesan' => 'Unggah dan unduh berkas LMS tidak tersedia melalui API JSON.'], 406);
        }
        if ($targetRoute->getName() === 'simak-users.update' && filled($innerRequest->input('password'))) {
            return $this->translatedJson(['pesan' => 'Perubahan password akun tidak tersedia melalui API.'], 403);
        }
        $throttled = $this->enforceRouteThrottle($targetRoute, $apiUser);
        if ($throttled !== null) {
            return $throttled;
        }

        $targetRoute->bind($innerRequest);
        $innerRequest->setRouteResolver(static fn () => $targetRoute);

        $container = app();
        $previousRequest = $container->make('request');
        $previousRoute = $container->bound(RoutingRoute::class) ? $container->make(RoutingRoute::class) : null;
        $previousUser = Auth::user();
        $arraySession = app('session')->driver('array');
        $arraySession->start();
        $innerRequest->setLaravelSession($arraySession);
        $targetRoute->setContainer($container);
        $container->instance('request', $innerRequest);
        $container->instance(RoutingRoute::class, $targetRoute);
        Auth::setUser($businessUser);

        try {
            $controller = $targetRoute->getController();
            $action = $targetRoute->getActionMethod();
            $result = app(ControllerDispatcher::class)->dispatch($targetRoute, $controller, $action);
            $statusCode = $result instanceof Response ? $result->getStatusCode() : 200;
            if (in_array($innerRequest->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)
                && $statusCode < 400
                && ! $innerRequest->attributes->get('activity_log_recorded', false)) {
                app(ActivityLogger::class)->record(
                    $businessUser,
                    'API WS',
                    'API '.$innerRequest->method().' '.$targetRoute->getName(),
                    ['route' => $targetRoute->getName(), 'method' => $innerRequest->method()],
                );
            }

            return $this->normalizeResponse($result, $innerRequest);
        } catch (ValidationException $exception) {
            return $this->translatedJson([
                'pesan' => 'Data request tidak valid.',
                'kesalahan' => $this->localizeKeys($exception->errors()),
            ], 422);
        } catch (AuthorizationException) {
            return $this->translatedJson(['pesan' => 'Aksi tidak diizinkan.'], 403);
        } catch (ModelNotFoundException) {
            return $this->translatedJson(['pesan' => 'Data tidak ditemukan.'], 404);
        } catch (HttpExceptionInterface $exception) {
            return $this->translatedJson([
                'pesan' => $exception->getStatusCode() === 403 ? 'Aksi tidak diizinkan.' : 'Data atau operasi tidak ditemukan.',
            ], $exception->getStatusCode());
        } catch (Throwable $exception) {
            report($exception);

            return $this->translatedJson(['pesan' => 'Operasi API gagal diproses.'], 500);
        } finally {
            if ($previousUser instanceof Authenticatable) {
                Auth::setUser($previousUser);
            } else {
                Auth::forgetGuards();
            }
            $container->instance('request', $previousRequest);
            if ($previousRoute instanceof RoutingRoute) {
                $container->instance(RoutingRoute::class, $previousRoute);
            }
        }
    }

    private function enforceRouteThrottle(RoutingRoute $route, GenericUser $user): ?JsonResponse
    {
        foreach ($route->gatherMiddleware() as $middleware) {
            if (! is_string($middleware) || ! str_starts_with($middleware, 'throttle:')) {
                continue;
            }
            [$maximum, $decayMinutes] = array_pad(explode(',', substr($middleware, 9), 2), 2, '1');
            if (! ctype_digit($maximum) || ! ctype_digit($decayMinutes)) {
                continue;
            }
            $key = 'simak-api-route:'.hash('sha256', $user->legacy_id.'|'.$route->getName());
            if (RateLimiter::tooManyAttempts($key, (int) $maximum)) {
                return $this->translatedJson(['pesan' => 'Rate limit route terlampaui.'], 429)
                    ->header('Retry-After', (string) RateLimiter::availableIn($key))
                    ->header('Cache-Control', 'no-store');
            }
            RateLimiter::hit($key, (int) $decayMinutes * 60);
        }

        return null;
    }

    private function normalizeResponse(mixed $result, Request $request): JsonResponse
    {
        if ($result instanceof JsonResponse) {
            return $this->translatedJson($this->redact($result->getData(true)), $result->getStatusCode());
        }
        if ($result instanceof View) {
            return $this->translatedJson(['data' => $this->redact($result->getData())]);
        }
        if ($result instanceof RedirectResponse) {
            $message = $request->session()->get('status');

            return $this->translatedJson([
                'pesan' => is_string($message) ? $message : 'Operasi berhasil.',
                'berhasil' => true,
            ]);
        }
        if ($result instanceof Response) {
            $contentType = (string) $result->headers->get('Content-Type', '');
            if (str_contains($contentType, 'application/json')) {
                $decoded = json_decode((string) $result->getContent(), true);

                return $this->translatedJson($this->redact($decoded ?? []), $result->getStatusCode());
            }
            if (str_contains($contentType, 'text/html')) {
                return $this->translatedJson(['html' => (string) $result->getContent()], $result->getStatusCode());
            }

            return $this->translatedJson(['pesan' => 'Response file tidak tersedia melalui API JSON.'], 406);
        }

        return $this->translatedJson(['data' => $this->redact($result)]);
    }

    private function translatedJson(mixed $payload, int $status = 200): JsonResponse
    {
        return response()->json($this->localizeKeys($payload), $status);
    }

    private function localizeKeys(mixed $value): mixed
    {
        if ($value instanceof Arrayable) {
            $value = $value->toArray();
        } elseif ($value instanceof Collection) {
            $value = $value->all();
        } elseif (is_object($value)) {
            $value = get_object_vars($value);
        }

        if (! is_array($value)) {
            return $value;
        }

        $translated = [];
        foreach ($value as $key => $item) {
            if (is_int($key) || is_float($key)) {
                $translated[$key] = $this->localizeKeys($item);

                continue;
            }

            $translated[$this->translateJsonKey((string) $key)] = $this->localizeKeys($item);
        }

        return $translated;
    }

    private function translateJsonKey(string $key): string
    {
        $normalized = preg_replace('/(?<!^)[A-Z]/', '_$0', $key) ?? $key;
        $segments = array_values(array_filter(preg_split('/[_\-]+/', strtolower($normalized)) ?: [], static fn (string $segment): bool => $segment !== ''));

        if ($segments === []) {
            return $key;
        }

        $translations = [
            'id' => 'id',
            'message' => 'pesan',
            'messages' => 'pesan',
            'error' => 'kesalahan',
            'errors' => 'kesalahan',
            'success' => 'berhasil',
            'record' => 'rekaman',
            'records' => 'rekaman',
            'data' => 'data',
            'values' => 'nilai',
            'value' => 'nilai',
            'name' => 'nama',
            'names' => 'nama',
            'code' => 'kode',
            'codes' => 'kode',
            'title' => 'judul',
            'titles' => 'judul',
            'description' => 'deskripsi',
            'descriptions' => 'deskripsi',
            'type' => 'tipe',
            'types' => 'tipe',
            'status' => 'status',
            'statuses' => 'status',
            'username' => 'username',
            'password' => 'password',
            'email' => 'email',
            'phone' => 'telepon',
            'address' => 'alamat',
            'student' => 'mahasiswa',
            'students' => 'mahasiswa',
            'teacher' => 'dosen',
            'teachers' => 'dosen',
            'lecturer' => 'dosen',
            'lecturers' => 'dosen',
            'faculty' => 'fakultas',
            'faculties' => 'fakultas',
            'program' => 'program',
            'programs' => 'program',
            'study' => 'studi',
            'course' => 'mata_kuliah',
            'courses' => 'mata_kuliah',
            'semester' => 'semester',
            'period' => 'periode',
            'periods' => 'periode',
            'year' => 'tahun',
            'date' => 'tanggal',
            'dates' => 'tanggal',
            'created' => 'dibuat',
            'updated' => 'diperbarui',
            'deleted' => 'dihapus',
            'at' => 'pada',
            'count' => 'jumlah',
            'total' => 'total',
            'amount' => 'nominal',
            'number' => 'nomor',
            'account' => 'akun',
            'accounts' => 'akun',
            'user' => 'pengguna',
            'users' => 'pengguna',
            'billing' => 'tagihan',
            'payment' => 'pembayaran',
            'payments' => 'pembayaran',
            'category' => 'kategori',
            'categories' => 'kategori',
            'role' => 'peran',
            'roles' => 'peran',
            'region' => 'wilayah',
            'regions' => 'wilayah',
            'origin' => 'asal',
            'origins' => 'asal',
            'class' => 'kelas',
            'classes' => 'kelas',
            'row' => 'baris',
            'rows' => 'baris',
            'column' => 'kolom',
            'columns' => 'kolom',
            'meta' => 'meta',
            'html' => 'html',
            'token' => 'token',
            'tokens' => 'token',
            'access' => 'akses',
            'expires' => 'kedaluwarsa',
            'expired' => 'kedaluwarsa',
            'in' => 'dalam',
            'of' => 'dari',
        ];

        $localized = [];
        foreach ($segments as $segment) {
            $localized[] = $translations[$segment] ?? $segment;
        }

        return implode('_', $localized);
    }

    private function redact(mixed $value): mixed
    {
        if ($value instanceof Arrayable) {
            $value = $value->toArray();
        } elseif ($value instanceof Collection) {
            $value = $value->all();
        } elseif (is_object($value)) {
            $value = get_object_vars($value);
        }

        if (! is_array($value)) {
            return $value;
        }

        $safe = [];
        foreach ($value as $key => $item) {
            if (is_string($key) && preg_match('/password|token|secret|api[_-]?key|authorization|session|path_berkas/i', $key) === 1) {
                continue;
            }
            $safe[$key] = $this->redact($item);
        }

        return $safe;
    }
}
