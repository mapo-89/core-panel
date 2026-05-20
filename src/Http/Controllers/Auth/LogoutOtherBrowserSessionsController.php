<?php

declare(strict_types=1);

namespace CorePanel\Http\Controllers\Auth;

use CorePanel\Support\Auth\LogoutOtherBrowserSessions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class LogoutOtherBrowserSessionsController
{
    public function __invoke(
        Request $request,
        LogoutOtherBrowserSessions $logoutOtherBrowserSessions
    ): RedirectResponse {
        $validated = $request->validate([
            'password' => ['required', 'string', 'current_password:web'],
        ]);

        $user = $request->user();
        abort_unless($user !== null, 403);

        $logoutOtherBrowserSessions->execute(
            $user,
            $validated['password'],
            (string) $request->session()->getId(),
        );

        return back()->with(
            'status',
            __('page-users.users.other_sessions_revoked'),
        );
    }
}
