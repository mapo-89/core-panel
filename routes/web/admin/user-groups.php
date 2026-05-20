<?php

declare(strict_types=1);

use CorePanel\Http\Controllers\Users\UserGroupController;
use Illuminate\Support\Facades\Route;

Route::get('/user-groups', [UserGroupController::class, 'index'])->name('user-groups.index');
Route::post('/user-groups', [UserGroupController::class, 'store'])->name('user-groups.store');
Route::post('/user-groups/preview', [UserGroupController::class, 'preview'])->name('user-groups.preview');
Route::post('/user-groups/import', [UserGroupController::class, 'import'])->name('user-groups.import');
Route::put('/user-groups/{user_group}', [UserGroupController::class, 'update'])->name('user-groups.update');
Route::delete('/user-groups/{user_group}', [UserGroupController::class, 'destroy'])->name('user-groups.destroy');
