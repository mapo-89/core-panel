<?php

declare(strict_types=1);

use CorePanel\Http\Controllers\Forms\PublicFormController;
use Illuminate\Support\Facades\Route;

Route::prefix('forms')
    ->name('core-panel.forms.public.')
    ->group(function (): void {
        Route::get('/{slug}', [PublicFormController::class, 'show'])->name('show');
        Route::post('/{slug}', [PublicFormController::class, 'store'])->name('store');
    });
