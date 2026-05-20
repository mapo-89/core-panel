<?php

declare(strict_types=1);

use CorePanel\Http\Controllers\Permissions\RoleController;
use Illuminate\Support\Facades\Route;

Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
Route::get('/roles/matrix', [RoleController::class, 'matrix'])->name('roles.matrix');
Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
Route::post('/roles/resync', [RoleController::class, 'resync'])->name('roles.resync');
Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
Route::post('/roles/{role}/permissions', [RoleController::class, 'syncPermissions'])->name('roles.permissions.sync');
