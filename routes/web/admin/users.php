<?php

declare(strict_types=1);

use CorePanel\Http\Controllers\Permissions\AssignUserRoleController;
use CorePanel\Http\Controllers\Users\ForceDeleteUserController;
use CorePanel\Http\Controllers\Users\ResetUserPasswordController;
use CorePanel\Http\Controllers\Users\RestoreUserController;
use CorePanel\Http\Controllers\Users\SendUserInvitationController;
use CorePanel\Http\Controllers\Users\SendUserPasswordResetLinkController;
use CorePanel\Http\Controllers\Users\UserAvatarController;
use CorePanel\Http\Controllers\Users\UserController;
use CorePanel\Http\Controllers\Users\UserProfileController;
use CorePanel\Http\Controllers\Users\UserSessionsController;
use Illuminate\Support\Facades\Route;

Route::post('/users/{user}/roles', [AssignUserRoleController::class, 'store'])->name('users.roles.assign');
Route::get('/users', [UserController::class, 'index'])->name('users.index');
Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
Route::post('/users', [UserController::class, 'store'])->name('users.store');
Route::get('/users/{user}', [UserProfileController::class, 'show'])->name('users.show');
Route::get('/users/{user}/edit', [UserProfileController::class, 'edit'])->name('users.edit');
Route::put('/users/{user}', [UserProfileController::class, 'update'])->name('users.update');
Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
Route::post('/users/{user}/restore', RestoreUserController::class)->name('users.restore');
Route::delete('/users/{user}/force', ForceDeleteUserController::class)->name('users.force-delete');
Route::post('/users/{user}/avatar', [UserAvatarController::class, 'store'])->name('users.avatar.store');
Route::delete('/users/{user}/avatar', [UserAvatarController::class, 'destroy'])->name('users.avatar.destroy');
Route::post('/users/{user}/reinvite', SendUserInvitationController::class)->name('users.reinvite');
Route::post('/users/{user}/password/reset-link', SendUserPasswordResetLinkController::class)->name('users.password.reset-link');
Route::put('/users/{user}/password', ResetUserPasswordController::class)->name('users.password.update');
Route::get('/users/{user}/sessions', [UserSessionsController::class, 'index'])->name('users.sessions.index');
Route::delete('/users/{user}/sessions/{session}', [UserSessionsController::class, 'destroy'])->name('users.sessions.destroy');
