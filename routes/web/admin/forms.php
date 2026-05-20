<?php

declare(strict_types=1);

use CorePanel\Http\Controllers\Forms\FormController;
use CorePanel\Http\Controllers\Forms\FormSubmissionController;
use CorePanel\Http\Controllers\Forms\FormVersionController;
use Illuminate\Support\Facades\Route;

Route::get('/forms', [FormController::class, 'index'])->name('forms.index');
Route::get('/forms/create', [FormController::class, 'create'])->name('forms.create');
Route::post('/forms', [FormController::class, 'store'])->name('forms.store');
Route::get('/forms/{form}/edit', [FormController::class, 'edit'])->name('forms.edit');
Route::put('/forms/{form}', [FormController::class, 'update'])->name('forms.update');
Route::delete('/forms/{form}', [FormController::class, 'destroy'])->name('forms.destroy');
Route::get('/forms/{form}/preview', [FormController::class, 'preview'])->name('forms.preview');
Route::get('/forms/{form}/versions', [FormVersionController::class, 'index'])->name('forms.versions.index');
Route::post('/forms/{form}/publish', [FormSubmissionController::class, 'publish'])->name('forms.publish');
Route::get('/forms/{form}/submissions', [FormSubmissionController::class, 'index'])->name('forms.submissions.index');
Route::get('/forms/{form}/submissions/export', [FormSubmissionController::class, 'export'])->name('forms.submissions.export');
