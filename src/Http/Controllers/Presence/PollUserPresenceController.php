<?php

declare(strict_types=1);

namespace CorePanel\Http\Controllers\Presence;

use CorePanel\Support\Presence\PresenceManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class PollUserPresenceController
{
    public function __construct(
        private PresenceManager $presence,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        abort_if($request->user() === null, 401);

        $cursor = max(0, (int) $request->integer('cursor', 0));
        /** @var list<string> $trackedUserIds */
        $trackedUserIds = collect((array) $request->input('ids', []))
            ->map(static fn (mixed $value): string => (string) $value)
            ->filter(static fn (string $value): bool => $value !== '')
            ->unique()
            ->values()
            ->all();

        $timeoutAt = microtime(true) + 20;

        do {
            $result = $this->presence->eventsAfter($cursor, $trackedUserIds);

            if ($result['cursor'] > $cursor) {
                return response()->json([
                    'data' => $result['events'],
                    'meta' => [
                        'cursor' => $result['cursor'],
                    ],
                ]);
            }

            usleep(500000);
        } while (microtime(true) < $timeoutAt && ! connection_aborted());

        return response()->json([
            'data' => [],
            'meta' => [
                'cursor' => $this->presence->latestCursor(),
            ],
        ]);
    }
}
