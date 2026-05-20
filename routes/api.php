<?php

declare(strict_types=1);

use CorePanel\Support\Api\ApiResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

Route::middleware(['api'])
    ->prefix(config('core-panel.route_prefix', 'admin'))
    ->name('core-panel.api.')
    ->group(function (): void {
        Route::get('/ping', static fn (): JsonResponse => app(ApiResponseFactory::class)->success([
            'package' => 'core-panel',
            'status' => 'ok',
        ]))->name('ping');
    });

Route::prefix('api')
    ->name('core-panel.api.')
    ->group(__DIR__.'/api/v1.php');
