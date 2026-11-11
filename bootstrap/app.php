<?php

use App\Http\Middleware\ApplyDatabaseMailSettings;
use App\Http\Middleware\AuthenticateAdminWsToken;
use App\Http\Middleware\LogUntrackedActivity;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [SetLocale::class, ApplyDatabaseMailSettings::class, LogUntrackedActivity::class]);
        if (defined('PHPUNIT_RUNNING') || class_exists(\PHPUnit\Framework\TestCase::class, false) || env('APP_ENV') === 'testing' || getenv('APP_ENV') === 'testing') {
            $middleware->validateCsrfTokens(except: ['*']);
        }
        $middleware->alias([
            'admin-ws.token' => AuthenticateAdminWsToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
