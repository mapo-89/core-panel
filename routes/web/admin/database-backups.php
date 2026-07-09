<?php

declare(strict_types=1);

use CorePanel\Http\Controllers\Administration\DatabaseBackupController;
use Illuminate\Support\Facades\Route;

Route::post('/system/database-backups', [DatabaseBackupController::class, 'store'])->name('database-backups.store');
Route::post('/system/database-backups/import', [DatabaseBackupController::class, 'importBackup'])->name('database-backups.import');
Route::get('/system/database-backups/emergency-kit', [DatabaseBackupController::class, 'emergencyKit'])->name('database-backups.emergency-kit');
Route::put('/system/database-backups/settings', [DatabaseBackupController::class, 'updateSettings'])->name('database-backups.settings.update');
Route::get('/system/database-backups/{backup}', [DatabaseBackupController::class, 'download'])->name('database-backups.download');
Route::get('/system/database-backups/{backup}/sql', [DatabaseBackupController::class, 'downloadSql'])->name('database-backups.download-sql');
Route::post('/system/database-backups/{backup}/restore', [DatabaseBackupController::class, 'restore'])->name('database-backups.restore');
Route::delete('/system/database-backups/{backup}', [DatabaseBackupController::class, 'destroy'])->name('database-backups.destroy');
Route::get('/system/database-backup-restores/{restoreId}', [DatabaseBackupController::class, 'restoreStatus'])->name('database-backups.restore.status');
