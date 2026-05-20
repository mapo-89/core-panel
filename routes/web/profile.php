<?php

declare(strict_types=1);

use CorePanel\Http\Controllers\Auth\AuthPageController;
use CorePanel\Http\Controllers\Auth\LinkSocialAccountController;
use CorePanel\Http\Controllers\Auth\LogoutOtherBrowserSessionsController;
use CorePanel\Http\Controllers\Auth\SocialiteCallbackController;
use CorePanel\Http\Controllers\Auth\UnlinkSocialAccountController;
use CorePanel\Http\Controllers\Presence\HeartbeatController;
use CorePanel\Http\Controllers\Presence\PollUserPresenceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'core-panel.verified'])->group(function (): void {
    Route::post('/presence/heartbeat', HeartbeatController::class)->name('presence.heartbeat');
    Route::get('/presence/updates', PollUserPresenceController::class)->name('presence.updates');
    Route::get('/profile', [AuthPageController::class, 'profile'])->name('profile.show');
    Route::get('/profile/security', [AuthPageController::class, 'security'])->name('profile.security');
    Route::post('/profile/sessions/logout-others', LogoutOtherBrowserSessionsController::class)->name('profile.sessions.destroy-others');

    Route::post('/profile/security/social/{provider}/link', LinkSocialAccountController::class)->name('socialite.link');
    Route::post('/profile/security/social/{provider}/avatar-sync', [SocialiteCallbackController::class, 'resolveAvatarSync'])->name('socialite.resolve-avatar-sync');
    Route::post('/profile/security/social/{provider}/test-mail', [SocialiteCallbackController::class, 'sendTestMail'])->name('socialite.test-mail');
    Route::delete('/profile/security/social/{provider}', UnlinkSocialAccountController::class)->name('socialite.unlink');
});
