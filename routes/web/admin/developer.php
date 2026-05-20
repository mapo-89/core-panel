<?php

declare(strict_types=1);

use CorePanel\Http\Controllers\Developer\DeveloperController;
use CorePanel\Http\Controllers\Developer\RegenerateApiDocsController;
use Illuminate\Support\Facades\Route;

Route::get('/developer', DeveloperController::class)->name('developer.index');
Route::post('/developer/regenerate-api-docs', RegenerateApiDocsController::class)->name('developer.regenerate-api-docs');
