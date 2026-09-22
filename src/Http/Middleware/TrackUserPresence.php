<?php

declare(strict_types=1);

namespace CorePanel\Http\Middleware;

use Closure;
use CorePanel\Support\Presence\PresenceManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class TrackUserPresence
{
    public function __construct(private PresenceManager $presence) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $user = $request->user();

        if (! (bool) config('core-panel.presence.enabled', true) || ! $user instanceof Model) {
            return $response;
        }

        if (
            method_exists($user, 'supportsCorePanelStatus')
            && $user->supportsCorePanelStatus()
            && method_exists($user, 'corePanelUserStatus')
            && $user->corePanelUserStatus() !== 'active'
        ) {
            return $response;
        }

        $this->presence->touch($user);

        return $response;
    }
}
