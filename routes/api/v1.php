<?php

declare(strict_types=1);

use CorePanel\Http\Controllers\ApiTokenController;
use CorePanel\Http\Controllers\Auth\SocialiteProviderController;
use CorePanel\Http\Middleware\InitializeTenant;
use CorePanel\Support\Api\ApiResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Middleware\CheckToken;

$tenantApiMiddleware = class_exists(InitializeTenant::class)
    ? ['core-panel.initialize-tenant', 'core-panel.authorize-tenant', 'core-panel.clear-tenant']
    : [];

Route::prefix('v1')
    ->middleware(['api', ...$tenantApiMiddleware])
    ->name('v1.')
    ->group(function (): void {
        Route::get('/ping', static fn (): JsonResponse => app(ApiResponseFactory::class)->success([
            'package' => 'core-panel',
            'status' => 'ok',
        ]))->name('ping');

        Route::get('/auth/providers', SocialiteProviderController::class)->name('auth.providers');

        Route::middleware(['auth:api', CheckToken::using('read'), 'core-panel.verified'])
            ->group(function (): void {
                Route::get('/me', [ApiTokenController::class, 'me'])->name('me');

                require __DIR__.'/v1/users.php';
            });
    });
