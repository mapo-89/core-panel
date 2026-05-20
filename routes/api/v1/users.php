<?php

declare(strict_types=1);

use CorePanel\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/users', [UserController::class, 'index'])->name('users.index');
Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
