<?php

declare(strict_types=1);

namespace CorePanel\Support\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ListBrowserSessions
{
    /**
     * @return Collection<int, array{id:string, ip_address:?string, user_agent:?string, last_active:int, is_current:bool}>
     */
    public function forUser(Authenticatable $user, string $currentSessionId): Collection
    {
        if (config('session.driver') !== 'database') {
            return collect();
        }

        return DB::table((string) config('session.table', 'sessions'))
            ->where('user_id', $user->getAuthIdentifier())
            ->orderByDesc('last_activity')
            ->get()
            ->map(static function (object $session) use ($currentSessionId): array {
                return [
                    'id' => (string) $session->id,
                    'ip_address' => is_string($session->ip_address) ? $session->ip_address : null,
                    'user_agent' => is_string($session->user_agent) ? $session->user_agent : null,
                    'last_active' => (int) $session->last_activity,
                    'is_current' => $session->id === $currentSessionId,
                ];
            })
            ->values();
    }
}
