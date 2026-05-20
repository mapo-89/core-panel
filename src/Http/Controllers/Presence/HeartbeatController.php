<?php

declare(strict_types=1);

namespace CorePanel\Http\Controllers\Presence;

use CorePanel\Support\Presence\PresenceManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class HeartbeatController
{
    public function __construct(
        private PresenceManager $presence,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_if($user === null, 401);

        $payload = $this->presence->touch($user);

        return response()->json([
            'data' => [[
                'lastSeenAt' => $payload['timestamp'],
                'status' => $payload['status'],
                'userId' => $payload['userId'],
            ]],
            'meta' => [
                'cursor' => $payload['cursor'],
            ],
        ]);
    }
}
