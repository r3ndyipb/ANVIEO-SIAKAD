<?php

namespace App\Http\Middleware;

use App\Services\DatabaseMailSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyDatabaseMailSettings
{
    public function handle(Request $request, Closure $next): Response
    {
        app(DatabaseMailSettings::class)->configure();

        return $next($request);
    }
}
