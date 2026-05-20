<?php

declare(strict_types=1);

use CorePanel\Http\Controllers\OAuth\OAuthClientController;
use Illuminate\Support\Facades\Route;

Route::get('/oauth-clients', [OAuthClientController::class, 'index'])->name('oauth-clients.index');
Route::post('/oauth-clients', [OAuthClientController::class, 'store'])->name('oauth-clients.store');
Route::put('/oauth-clients/{client}', [OAuthClientController::class, 'update'])->name('oauth-clients.update');
Route::delete('/oauth-clients/{client}', [OAuthClientController::class, 'destroy'])->name('oauth-clients.destroy');
