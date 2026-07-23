<?php

declare(strict_types=1);

namespace CorePanel\Support\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

final class ListBrowserSessions
{
    public function forUser(Authenticatable $user, string $currentSessionId): BrowserSessionCollection
    {
        if (config('session.driver') !== 'database') {
            return new BrowserSessionCollection;
        }

        $sessions = DB::table((string) config('session.table', 'sessions'))
            ->where('user_id', $user->getAuthIdentifier())
            ->orderByDesc('last_activity')
            ->get();

        $browserSessions = $sessions
            ->map(
                fn (object $session): array => $this->sessionPayload($session, $currentSessionId),
            )
            ->values();

        return new BrowserSessionCollection($browserSessions->all());
    }

    /**
     * @return array{id:string, ip_address:string|null, user_agent:string|null, last_active:int, is_current:bool}
     */
    private function sessionPayload(object $session, string $currentSessionId): array
    {
        $id = (string) $session->id;

        return [
            'id' => $id,
            'ip_address' => is_string($session->ip_address) ? $session->ip_address : null,
            'user_agent' => is_string($session->user_agent) ? $session->user_agent : null,
            'last_active' => (int) $session->last_activity,
            'is_current' => $id === $currentSessionId,
        ];
    }
}
