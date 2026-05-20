<?php

declare(strict_types=1);

namespace CorePanel\Http\Controllers\Logs;

use CorePanel\Models\AuthenticationLog;
use CorePanel\Support\Auth\AuthenticationLogRecorder;
use CorePanel\Support\Permissions\PermissionService;
use CorePanel\Support\Users\UserModelManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AuthenticationLogDetailController extends Controller
{
    public function __construct(
        private AuthenticationLogRecorder $authenticationLogs,
        private PermissionService $permissions,
        private UserModelManager $users,
    ) {}

    public function show(Request $request, AuthenticationLog $authenticationLog): JsonResponse
    {
        abort_unless($this->canViewAuthenticationLogs($request), 403);

        $this->authenticationLogs->recordExpiredDatabaseSessions();

        $authenticationLog->refresh();
        $authenticationLog->loadMissing('user');

        return response()->json([
            'data' => $this->transform($authenticationLog),
        ]);
    }

    private function canViewAuthenticationLogs(Request $request): bool
    {
        $user = $request->user();

        return $user !== null
            && $this->permissions->userHas($user, 'authentication-logs.view');
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(AuthenticationLog $log): array
    {
        $user = $log->getRelationValue('user');
        $name = $user !== null
            ? $this->users->composeDisplayName(
                $user->getAttribute('first_name'),
                $user->getAttribute('last_name'),
            )
            : null;

        return [
            'authenticationResult' => $this->authenticationResult($log),
            'browser' => $log->getAttribute('browser'),
            'deviceName' => $log->getAttribute('device_name'),
            'deviceType' => $log->getAttribute('device_type'),
            'guard' => $log->getAttribute('guard'),
            'id' => (string) $log->getKey(),
            'ipAddress' => $log->getAttribute('ip_address'),
            'lastActiveAt' => $log->getAttribute('last_active_at')?->toIso8601String(),
            'login' => $log->getAttribute('login'),
            'loginAt' => $log->getAttribute('login_at')?->toIso8601String(),
            'loginSuccessful' => (bool) $log->getAttribute('login_successful'),
            'logoutAt' => $log->getAttribute('logout_at')?->toIso8601String(),
            'platform' => $log->getAttribute('platform'),
            'properties' => $properties = (array) ($log->getAttribute('properties') ?? []),
            'authMethod' => isset($properties['auth_method']) && is_string($properties['auth_method'])
                ? $properties['auth_method']
                : 'form',
            'userAvatarUrl' => $user !== null ? $this->users->avatarUrl($user) : null,
            'userEmail' => $user?->getAttribute('email'),
            'userAgent' => isset($properties['user_agent']) && is_string($properties['user_agent'])
                ? $properties['user_agent']
                : null,
            'userId' => $log->getAttribute('user_id'),
            'userName' => $name !== '' ? $name : null,
            'socialProvider' => isset($properties['social_provider']) && is_string($properties['social_provider'])
                ? $properties['social_provider']
                : null,
            'logoutReason' => isset($properties['logout_reason']) && is_string($properties['logout_reason'])
                ? $properties['logout_reason']
                : null,
        ];
    }

    private function authenticationResult(AuthenticationLog $log): string
    {
        if (! (bool) $log->getAttribute('login_successful')) {
            return 'failed';
        }

        $properties = (array) ($log->getAttribute('properties') ?? []);
        $reason = isset($properties['logout_reason']) && is_string($properties['logout_reason'])
            ? $properties['logout_reason']
            : null;

        return match ($reason) {
            'expired' => 'expired',
            'logout' => 'logout',
            'revoked', 'revoked_other_sessions' => 'revoked',
            default => 'successful',
        };
    }
}
