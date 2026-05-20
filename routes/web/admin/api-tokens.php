<?php

declare(strict_types=1);

use CorePanel\Http\Controllers\ApiTokenController;
use Illuminate\Support\Facades\Route;

Route::get('/api-tokens', [ApiTokenController::class, 'index'])->name('api-tokens.index');
Route::post('/api-tokens/{token}/replace', [ApiTokenController::class, 'replace'])->name('api-tokens.replace');
Route::post('/api-tokens', [ApiTokenController::class, 'store'])->name('api-tokens.store');
Route::delete('/api-tokens/{token}', [ApiTokenController::class, 'destroy'])->name('api-tokens.destroy');
