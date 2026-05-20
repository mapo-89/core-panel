<?php

declare(strict_types=1);

use CorePanel\Http\Controllers\Permissions\PermissionController;
use Illuminate\Support\Facades\Route;

Route::post('/permissions', [PermissionController::class, 'store'])->name('permissions.store');
Route::put('/permissions/{permission}', [PermissionController::class, 'update'])->name('permissions.update');
Route::delete('/permissions/{permission}', [PermissionController::class, 'destroy'])->name('permissions.destroy');
