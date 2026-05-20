<?php

declare(strict_types=1);

namespace CorePanel\Support\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class LogoutOtherBrowserSessions
{
    public function __construct(
        private readonly AuthenticationLogRecorder $authenticationLogs,
    ) {}

    public function execute(
        Authenticatable $user,
        string $password,
        string $currentSessionId
    ): void {
        Auth::logoutOtherDevices($password);

        if (config('session.driver') !== 'database') {
            return;
        }

        $actor = request()->user();
        $this->authenticationLogs->recordRevokedOtherBrowserSessions(
            $user,
            $currentSessionId,
            $actor instanceof Authenticatable ? $actor : null,
        );

        DB::table((string) config('session.table', 'sessions'))
            ->where('user_id', $user->getAuthIdentifier())
            ->where('id', '!=', $currentSessionId)
            ->delete();
    }
}
