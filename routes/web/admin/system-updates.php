<?php

declare(strict_types=1);

use CorePanel\Http\Controllers\Administration\SystemUpdateController;
use Illuminate\Support\Facades\Route;

Route::get('/system/updates/status', [SystemUpdateController::class, 'status'])->name('system-updates.status');
Route::post('/system/updates/check', [SystemUpdateController::class, 'check'])->name('system-updates.check');
Route::post('/system/updates/install', [SystemUpdateController::class, 'update'])->name('system-updates.update');
