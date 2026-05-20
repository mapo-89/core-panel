<?php

declare(strict_types=1);

namespace CorePanel\Support\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

final class RevokeBrowserSession
{
    public function __construct(
        private readonly AuthenticationLogRecorder $authenticationLogs,
    ) {}

    public function execute(Authenticatable $user, string $sessionId): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        $actor = request()->user();
        $this->authenticationLogs->recordRevokedBrowserSession(
            $user,
            $sessionId,
            $actor instanceof Authenticatable ? $actor : null,
        );

        DB::table((string) config('session.table', 'sessions'))
            ->where('id', $sessionId)
            ->where('user_id', $user->getAuthIdentifier())
            ->delete();
    }
}
