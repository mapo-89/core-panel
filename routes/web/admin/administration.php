<?php

declare(strict_types=1);

use CorePanel\Http\Controllers\Administration\AdministrationController;
use Illuminate\Support\Facades\Route;

Route::get('/system/administration', AdministrationController::class)->name('administration.index');
