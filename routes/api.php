<?php

use App\Http\Controllers\Api\V1\ApiDocumentationController;
use App\Http\Controllers\Api\V1\ApiGatewayController;
use App\Http\Controllers\Api\V1\ApiTokenController;
use App\Http\Controllers\MidtransNotificationController;
use Illuminate\Support\Facades\Route;

Route::post('/payments/midtrans/notification', MidtransNotificationController::class)
    ->middleware('throttle:60,1')
    ->name('payments.midtrans.notification');

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('/', [ApiDocumentationController::class, 'index'])->name('docs');
    Route::get('/operations', [ApiDocumentationController::class, 'operations'])->name('operations');
    Route::get('/openapi.yaml', [ApiDocumentationController::class, 'openApi'])->name('openapi');
    Route::get('/guide.pdf', [ApiDocumentationController::class, 'guide'])->name('guide');
    Route::post('/sandbox/execute', [ApiDocumentationController::class, 'sandbox'])
        ->middleware('throttle:30,1')->name('sandbox.execute');
    Route::post('/auth/token', [ApiTokenController::class, 'issue'])
        ->middleware('throttle:5,1')
        ->name('auth.token');
    Route::post('/auth/sso', [ApiTokenController::class, 'sso'])
        ->middleware('throttle:5,1')
        ->name('auth.sso');
    Route::match(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], '/{path}', [ApiGatewayController::class, 'dispatch'])
        ->where('path', '.*')
        ->middleware(['admin-ws.token', 'throttle:60,1'])
        ->name('gateway');
});
