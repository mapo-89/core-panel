<?php

declare(strict_types=1);

namespace CorePanel\Support\Auth;

use CorePanel\Models\AuthenticationLog;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

final class AuthenticationLogRecorder
{
    private const AUTH_METHOD_ATTRIBUTE = 'core_panel.authentication_method';

    private const SOCIAL_PROVIDER_ATTRIBUTE = 'core_panel.social_provider';

    private const LOGOUT_REASON_EXPIRED = 'expired';

    private const LOGOUT_REASON_LOGOUT = 'logout';

    private const LOGOUT_REASON_REVOKED = 'revoked';

    private const LOGOUT_REASON_REVOKED_OTHER_SESSIONS = 'revoked_other_sessions';

    public function recordFailedLogin(Failed $event): void
    {
        if (! Schema::hasTable('authentication_logs')) {
            return;
        }

        $request = request();
        $identifier = $this->resolveLoginIdentifier(
            $request,
            $event->credentials,
        );

        AuthenticationLog::query()->create([
            'browser' => $this->resolveBrowser($request),
            'device_name' => $this->resolveDeviceName($request),
            'device_type' => $this->resolveDeviceType($request),
            'guard' => $event->guard,
            'ip_address' => $request->ip(),
            'last_active_at' => now(),
            'login' => $identifier,
            'login_at' => now(),
            'login_successful' => false,
            'platform' => $this->resolvePlatform($request),
            'properties' => $this->authenticationProperties($request),
            'session_id' => $request->hasSession() ? $request->session()->getId() : null,
            'user_id' => null,
        ]);
    }

    public function recordLogout(Logout $event): void
    {
        if (! Schema::hasTable('authentication_logs')) {
            return;
        }

        $request = request();
        $sessionId = $request->hasSession() ? $request->session()->getId() : null;
        $log = null;

        if (is_string($sessionId) && $sessionId !== '') {
            $log = $this->openSuccessfulLoginQuery($event)
                ->where('session_id', $sessionId)
                ->orderByDesc('login_at')
                ->limit(1)
                ->first();

            if ($log instanceof AuthenticationLog) {
                $this->finishAuthenticationLog($log, self::LOGOUT_REASON_LOGOUT);

                return;
            }
        }

        $log = $this->openSuccessfulLoginQuery($event)
            ->orderByDesc('login_at')
            ->limit(1)
            ->first();

        if ($log instanceof AuthenticationLog) {
            $this->finishAuthenticationLog($log, self::LOGOUT_REASON_LOGOUT, $sessionId);
        }
    }

    public function recordSuccessfulLogin(Login $event): void
    {
        if (! Schema::hasTable('authentication_logs')) {
            return;
        }

        $request = request();

        AuthenticationLog::query()->create([
            'browser' => $this->resolveBrowser($request),
            'device_name' => $this->resolveDeviceName($request),
            'device_type' => $this->resolveDeviceType($request),
            'guard' => $event->guard,
            'ip_address' => $request->ip(),
            'last_active_at' => now(),
            'login' => $this->resolveLoginIdentifier($request),
            'login_at' => now(),
            'login_successful' => true,
            'platform' => $this->resolvePlatform($request),
            'properties' => $this->authenticationProperties($request, [
                'remember' => $event->remember,
            ]),
            'session_id' => $request->hasSession() ? $request->session()->getId() : null,
            'user_id' => (string) $event->user->getAuthIdentifier(),
        ]);
    }

    public function recordExpiredDatabaseSessions(): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        $request = request();
        $currentSessionId = $request->hasSession()
            ? $request->session()->getId()
            : null;
        $sessionsTable = (string) config('session.table', 'sessions');

        if (! Schema::hasTable('authentication_logs') || ! Schema::hasTable($sessionsTable)) {
            return;
        }

        $this->reconcileCurrentRequestSession($sessionsTable, $currentSessionId);

        AuthenticationLog::query()
            ->where('login_successful', true)
            ->whereNull('logout_at')
            ->whereNotNull('session_id')
            ->when(
                is_string($currentSessionId) && $currentSessionId !== '',
                static fn ($query) => $query->where('session_id', '!=', $currentSessionId),
            )
            ->whereNotExists(function ($query) use ($sessionsTable): void {
                $query
                    ->selectRaw('1')
                    ->from($sessionsTable)
                    ->whereColumn($sessionsTable.'.id', 'authentication_logs.session_id');
            })
            ->cursor()
            ->each(fn (AuthenticationLog $log): bool => $this->finishAuthenticationLog(
                $log,
                self::LOGOUT_REASON_EXPIRED,
            ));
    }

    private function reconcileCurrentRequestSession(
        string $sessionsTable,
        ?string $currentSessionId,
    ): void {
        if (! is_string($currentSessionId) || $currentSessionId === '') {
            return;
        }

        $user = request()->user();

        if (! $user instanceof Authenticatable) {
            return;
        }

        $userId = (string) $user->getAuthIdentifier();
        $guard = config('auth.defaults.guard');

        $currentLogExists = AuthenticationLog::query()
            ->where('login_successful', true)
            ->whereNull('logout_at')
            ->where('session_id', $currentSessionId)
            ->where('user_id', $userId)
            ->when(
                is_string($guard) && $guard !== '',
                static fn (Builder $query): Builder => $query->where('guard', $guard),
            )
            ->exists();

        if ($currentLogExists) {
            return;
        }

        $candidate = AuthenticationLog::query()
            ->where('login_successful', true)
            ->whereNull('logout_at')
            ->where('user_id', $userId)
            ->when(
                is_string($guard) && $guard !== '',
                static fn (Builder $query): Builder => $query->where('guard', $guard),
            )
            ->where('session_id', '!=', $currentSessionId)
            ->where(function (Builder $query) use ($sessionsTable): void {
                $query
                    ->whereNull('session_id')
                    ->orWhereNotExists(function ($subQuery) use ($sessionsTable): void {
                        $subQuery
                            ->selectRaw('1')
                            ->from($sessionsTable)
                            ->whereColumn($sessionsTable.'.id', 'authentication_logs.session_id');
                    });
            })
            ->latest('login_at')
            ->first();

        if (! $candidate instanceof AuthenticationLog) {
            return;
        }

        $candidate->forceFill([
            'last_active_at' => now(),
            'session_id' => $currentSessionId,
        ])->save();
    }

    public function recordRevokedBrowserSession(
        Authenticatable $user,
        string $sessionId,
        ?Authenticatable $actor = null,
    ): void {
        if (! Schema::hasTable('authentication_logs')) {
            return;
        }

        $log = AuthenticationLog::query()
            ->where('login_successful', true)
            ->whereNull('logout_at')
            ->where('session_id', $sessionId)
            ->where('user_id', (string) $user->getAuthIdentifier())
            ->latest('login_at')
            ->first();

        if ($log instanceof AuthenticationLog) {
            $this->finishAuthenticationLog($log, self::LOGOUT_REASON_REVOKED, actor: $actor);
        }
    }

    public function recordRevokedOtherBrowserSessions(
        Authenticatable $user,
        string $currentSessionId,
        ?Authenticatable $actor = null,
    ): void {
        if (! Schema::hasTable('authentication_logs')) {
            return;
        }

        AuthenticationLog::query()
            ->where('login_successful', true)
            ->whereNull('logout_at')
            ->whereNotNull('session_id')
            ->where('session_id', '!=', $currentSessionId)
            ->where('user_id', (string) $user->getAuthIdentifier())
            ->cursor()
            ->each(fn (AuthenticationLog $log): bool => $this->finishAuthenticationLog(
                $log,
                self::LOGOUT_REASON_REVOKED_OTHER_SESSIONS,
                actor: $actor,
            ));
    }

    /**
     * @return Builder<AuthenticationLog>
     */
    private function openSuccessfulLoginQuery(Logout $event): Builder
    {
        return AuthenticationLog::query()
            ->where('guard', $event->guard)
            ->where('login_successful', true)
            ->whereNull('logout_at')
            ->where('user_id', (string) $event->user->getAuthIdentifier());
    }

    private function finishAuthenticationLog(
        AuthenticationLog $log,
        string $reason,
        ?string $sessionId = null,
        ?Authenticatable $actor = null,
    ): bool {
        $properties = (array) ($log->getAttribute('properties') ?? []);
        $properties['logout_reason'] = $reason;

        if ($actor instanceof Authenticatable) {
            $properties['logout_actor_id'] = (string) $actor->getAuthIdentifier();
        }

        $log->forceFill([
            'last_active_at' => now(),
            'logout_at' => now(),
            'properties' => $properties,
            'session_id' => is_string($sessionId) && $sessionId !== ''
                ? $sessionId
                : $log->getAttribute('session_id'),
        ])->save();

        return true;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function resolveLoginIdentifier(
        Request $request,
        array $credentials = [],
    ): ?string {
        $field = (string) config('fortify.username', 'email');
        $value = $request->input($field, $credentials[$field] ?? null);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function authenticationProperties(Request $request, array $extra = []): array
    {
        $method = $request->attributes->get(self::AUTH_METHOD_ATTRIBUTE);
        $provider = $request->attributes->get(self::SOCIAL_PROVIDER_ATTRIBUTE);

        return array_filter([
            ...$extra,
            'auth_method' => is_string($method) && $method !== '' ? $method : 'form',
            'social_provider' => is_string($provider) && $provider !== '' ? $provider : null,
            'user_agent' => $request->userAgent(),
        ], static fn (mixed $value): bool => $value !== null);
    }

    private function resolveBrowser(Request $request): ?string
    {
        $userAgent = strtolower((string) $request->userAgent());

        return match (true) {
            str_contains($userAgent, 'edg/') => 'Edge',
            str_contains($userAgent, 'firefox/') => 'Firefox',
            str_contains($userAgent, 'opr/') || str_contains($userAgent, 'opera/') => 'Opera',
            str_contains($userAgent, 'chrome/') => 'Chrome',
            str_contains($userAgent, 'safari/') => 'Safari',
            default => null,
        };
    }

    private function resolveDeviceName(Request $request): ?string
    {
        $userAgent = trim((string) $request->userAgent());

        return $userAgent !== '' ? $userAgent : null;
    }

    private function resolveDeviceType(Request $request): ?string
    {
        $userAgent = strtolower((string) $request->userAgent());

        return match (true) {
            str_contains($userAgent, 'tablet') || str_contains($userAgent, 'ipad') => 'tablet',
            str_contains($userAgent, 'mobile') || str_contains($userAgent, 'iphone') || str_contains($userAgent, 'android') => 'mobile',
            $userAgent !== '' => 'desktop',
            default => null,
        };
    }

    private function resolvePlatform(Request $request): ?string
    {
        $userAgent = strtolower((string) $request->userAgent());

        return match (true) {
            str_contains($userAgent, 'windows') => 'Windows',
            str_contains($userAgent, 'mac os') || str_contains($userAgent, 'macintosh') => 'macOS',
            str_contains($userAgent, 'iphone') || str_contains($userAgent, 'ipad') => 'iOS',
            str_contains($userAgent, 'android') => 'Android',
            str_contains($userAgent, 'linux') => 'Linux',
            default => null,
        };
    }
}
