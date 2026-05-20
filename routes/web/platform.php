<?php

declare(strict_types=1);

use CorePanel\Http\Controllers\Auth\SocialiteCallbackController;
use CorePanel\Http\Controllers\Auth\SocialiteProviderController;
use CorePanel\Http\Controllers\Auth\SocialiteRedirectController;
use CorePanel\Http\Controllers\SetLocaleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web'])->group(function (): void {
    Route::post('/locale', SetLocaleController::class)->name('locale.set');
    Route::get('/auth/{provider}/callback', SocialiteCallbackController::class)->name('socialite.callback');
    Route::get('/auth/{provider}/conflict', [SocialiteCallbackController::class, 'showConflict'])->name('socialite.conflict');
    Route::get('/auth/{provider}/redirect', SocialiteRedirectController::class)->name('socialite.redirect');
    Route::post('/auth/{provider}/resolve-conflict', [SocialiteCallbackController::class, 'resolveConflict'])->name('socialite.resolve-conflict');
    Route::get('/auth/providers', SocialiteProviderController::class)->name('socialite.providers');
});
