<?php

declare(strict_types=1);

use CorePanel\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

$middleware = [
    ...config('core-panel.middleware', ['web', 'auth']),
    'core-panel.verified',
    'check.permission',
];

Route::middleware($middleware)
    ->name('core-panel.')
    ->group(function (): void {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
    });
