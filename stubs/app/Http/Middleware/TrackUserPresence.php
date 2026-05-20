<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use CorePanel\Support\Presence\PresenceManager;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class TrackUserPresence
{
    public function __construct(
        private PresenceManager $presence,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $user = $request->user();

        if ($user !== null) {
            $this->presence->touch($user);
        }

        return $response;
    }
}
