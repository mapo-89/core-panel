<?php

declare(strict_types=1);

use CorePanel\Http\Controllers\Logs\ActivityLogDetailController;
use CorePanel\Http\Controllers\Logs\AuthenticationLogDetailController;
use CorePanel\Http\Controllers\Logs\LogController;
use CorePanel\Http\Controllers\Logs\LogFileController;
use CorePanel\Http\Controllers\Logs\LogFileEntriesController;
use Illuminate\Support\Facades\Route;

Route::get('/logs', LogController::class)->name('logs.index');
Route::get('/activity', static function () {
    return redirect()->route('core-panel.logs.index', ['tab' => 'activity']);
})->name('activity.index');
Route::get('/activity/{activity}', [ActivityLogDetailController::class, 'show'])->name('activity.show');
Route::get('/authentication-logs', static function () {
    return redirect()->route('core-panel.logs.index', ['tab' => 'authentication']);
})->name('authentication-logs.index');
Route::get('/authentication-logs/{authenticationLog}', [AuthenticationLogDetailController::class, 'show'])->name('authentication-logs.show');
Route::get('/log-files', static function () {
    return redirect()->route('core-panel.logs.index', ['tab' => 'logs']);
})->name('log-files.index');
Route::get('/log-files/{filename}', LogFileController::class)->name('log-files.show');
Route::get('/log-files/{filename}/entries', LogFileEntriesController::class)->name('log-files.entries');
