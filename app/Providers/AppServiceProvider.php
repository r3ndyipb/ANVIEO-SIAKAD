<?php

namespace App\Providers;

use App\Auth\LegacyUserProvider;
use App\Services\InstitutionSettings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer([
            'layouts.simak',
            'public.landing',
            'api-docs.index',
            'auth.login',
            'auth.forgot-password',
            'auth.activate-password',
            'theses.document-verification',
            'graduate-theses.document-verification',
            'khs.verification',
            'transcripts.verification',
            'system-settings.index',
            'dashboard',
            'finance.dashboard',
            'ekurikulum.profile',
            'lms.meeting-verification',
            'class-schedules.print',
            'finance.billing-print',
            'finance.manual-billing-print',
            'ekurikulum.rps-print',
            'krs.verification',
            'api-docs.guide-pdf',
            'mail.thesis-notification',
            'mail.graduate-thesis-notification',
            'theses.exam-print',
            'graduate-theses.exam-print',
        ], function ($view): void {
            $view->with('siteConfiguration', app(InstitutionSettings::class)->forDisplay());
        });

        Auth::provider('simak-legacy', function ($app, array $config) {
            return new LegacyUserProvider($config['connection']);
        });

        \Illuminate\Database\Query\Builder::macro('whereLike', function ($column, $value) {
            /** @var \Illuminate\Database\Query\Builder $this */
            $driver = $this->getConnection()->getDriverName();
            if ($driver === 'pgsql') {
                return $this->where($column, 'ilike', $value);
            }
            return $this->where($column, 'like', $value);
        });

        \Illuminate\Database\Query\Builder::macro('orWhereLike', function ($column, $value) {
            /** @var \Illuminate\Database\Query\Builder $this */
            $driver = $this->getConnection()->getDriverName();
            if ($driver === 'pgsql') {
                return $this->orWhere($column, 'ilike', $value);
            }
            return $this->orWhere($column, 'like', $value);
        });

        RateLimiter::for('login', function (Request $request): array {
            $identifier = mb_strtolower(trim((string) $request->input('login', '')));
            $retryMessage = static function (Request $request, array $headers) {
                $retryAfter = max(1, (int) ($headers['Retry-After'] ?? 60));

                return redirect()->route('login')
                    ->withInput($request->only('login'))
                    ->withErrors(['login' => 'Terlalu banyak percobaan login. Silakan coba lagi dalam '.$retryAfter.' detik.'])
                    ->with('auth_notification', [
                        'icon' => 'error',
                        'title' => 'Login ditunda',
                        'text' => 'Terlalu banyak percobaan login. Silakan coba lagi dalam '.$retryAfter.' detik.',
                    ]);
            };

            return [
                Limit::perMinute(5)
                    ->by('login:'.hash('sha256', $identifier))
                    ->response($retryMessage),
                Limit::perMinute(120)
                    ->by('ip:'.hash('sha256', (string) $request->ip()))
                    ->response($retryMessage),
            ];
        });
    }
}
