<?php

declare(strict_types=1);

use CorePanel\Http\Controllers\Files\FileController;
use CorePanel\Http\Controllers\Files\FileDeleteController;
use CorePanel\Http\Controllers\Files\FileDownloadController;
use CorePanel\Http\Controllers\Files\FilePreviewController;
use CorePanel\Http\Controllers\Files\FileUploadController;
use Illuminate\Support\Facades\Route;

Route::get('/files', [FileController::class, 'index'])->name('files.index');
Route::post('/files', [FileUploadController::class, 'store'])->name('files.store');
Route::delete('/files/{file}', [FileDeleteController::class, 'destroy'])->name('files.destroy');
Route::get('/files/{file}/download', [FileDownloadController::class, 'show'])->name('files.download');
Route::get('/files/{file}/preview', [FilePreviewController::class, 'show'])->name('files.preview');
