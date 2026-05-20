<?php

declare(strict_types=1);

use CorePanel\Http\Controllers\Auth\AuthPageController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'guest'])->group(function (): void {
    Route::get('/login', [AuthPageController::class, 'login'])->name('auth.login');
    Route::get('/register', [AuthPageController::class, 'register'])->name('auth.register');
    Route::get('/forgot-password', [AuthPageController::class, 'forgotPassword'])->name('password.request');
    Route::get('/reset-password/{token}', [AuthPageController::class, 'resetPassword'])->name('password.reset');
    Route::get('/two-factor-challenge', [AuthPageController::class, 'twoFactorChallenge'])->name('two-factor.login');
});

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('/email/verify', [AuthPageController::class, 'verifyEmail'])->name('auth.verification.notice');
});
