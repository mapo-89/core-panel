<?php

declare(strict_types=1);

namespace CorePanel\Support\Presence;

use BadMethodCallException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

final class PresenceManager
{
    public const STATUS_ONLINE = 'online';

    public const STATUS_AWAY = 'away';

    public const STATUS_OFFLINE = 'offline';

    private const CACHE_TTL_MINUTES = 30;

    private const ONLINE_WINDOW_MINUTES = 2;

    private const AWAY_WINDOW_MINUTES = 10;

    private const PUBLISH_COOLDOWN_SECONDS = 30;

    private const CURSOR_CACHE_KEY = 'core-panel:presence:cursor';

    private const EVENT_CACHE_KEY_PREFIX = 'core-panel:presence:event:';

    /**
     * @return array{cursor:int,status:string,timestamp:int,userId:string}
     */
    public function touch(Model|Authenticatable|string|int $user): array
    {
        $userId = $this->resolveUserId($user);
        $previousTimestamp = $this->lastSeenTimestamp($user);
        $timestamp = now()->timestamp;

        try {
            Cache::put(
                $this->cacheKey($user),
                $timestamp,
                now()->addMinutes(self::CACHE_TTL_MINUTES),
            );
        } catch (BadMethodCallException) {
            return [
                'cursor' => $this->latestCursor(),
                'status' => $this->statusFromTimestamp($timestamp),
                'timestamp' => $timestamp,
                'userId' => $userId,
            ];
        }

        $cursor = $this->latestCursor();

        if ($this->shouldPublishUpdate($previousTimestamp, $timestamp)) {
            $cursor = $this->publishUpdate($userId, $timestamp);
        }

        return [
            'cursor' => $cursor,
            'status' => $this->statusFromTimestamp($timestamp),
            'timestamp' => $timestamp,
            'userId' => $userId,
        ];
    }

    /**
     * @param  list<string>  $trackedUserIds
     * @return array{cursor:int,events:list<array{lastSeenAt:int,status:string,userId:string}>}
     */
    public function eventsAfter(int $cursor, array $trackedUserIds = []): array
    {
        $latestCursor = $this->latestCursor();

        if ($latestCursor <= $cursor) {
            return [
                'cursor' => $latestCursor,
                'events' => [],
            ];
        }

        $events = [];

        for ($currentCursor = $cursor + 1; $currentCursor <= $latestCursor; $currentCursor++) {
            $event = $this->eventPayload($currentCursor);

            if ($event === null) {
                continue;
            }

            if ($trackedUserIds !== [] && ! in_array($event['userId'], $trackedUserIds, true)) {
                continue;
            }

            $events[] = $event;
        }

        return [
            'cursor' => $latestCursor,
            'events' => $events,
        ];
    }

    public function latestCursor(): int
    {
        try {
            $cursor = Cache::get(self::CURSOR_CACHE_KEY, 0);
        } catch (BadMethodCallException) {
            return 0;
        }

        if (! is_int($cursor) && ! ctype_digit((string) $cursor)) {
            return 0;
        }

        return (int) $cursor;
    }

    public function lastSeenTimestamp(Model|Authenticatable|string|int $user): ?int
    {
        try {
            $timestamp = Cache::get($this->cacheKey($user));
        } catch (BadMethodCallException) {
            return null;
        }

        if (! is_int($timestamp) && ! ctype_digit((string) $timestamp)) {
            return null;
        }

        return (int) $timestamp;
    }

    public function statusFor(Model|Authenticatable|string|int $user): string
    {
        return $this->statusFromTimestamp($this->lastSeenTimestamp($user));
    }

    public function statusFromTimestamp(?int $timestamp): string
    {
        if ($timestamp === null) {
            return self::STATUS_OFFLINE;
        }

        $minutesSinceLastSeen = max(
            0,
            Carbon::createFromTimestamp($timestamp)->diffInMinutes(now()),
        );

        if ($minutesSinceLastSeen <= self::ONLINE_WINDOW_MINUTES) {
            return self::STATUS_ONLINE;
        }

        if ($minutesSinceLastSeen <= self::AWAY_WINDOW_MINUTES) {
            return self::STATUS_AWAY;
        }

        return self::STATUS_OFFLINE;
    }

    private function cacheKey(Model|Authenticatable|string|int $user): string
    {
        if (($user instanceof Model || $user instanceof Authenticatable) && method_exists($user, 'presenceCacheKey')) {
            return $user->presenceCacheKey();
        }

        return 'user-presence:'.$this->resolveUserId($user);
    }

    /**
     * @return array{lastSeenAt:int,status:string,userId:string}|null
     */
    private function eventPayload(int $cursor): ?array
    {
        try {
            $payload = Cache::get($this->eventCacheKey($cursor));
        } catch (BadMethodCallException) {
            return null;
        }

        if (! is_array($payload)) {
            return null;
        }

        $userId = isset($payload['userId']) ? (string) $payload['userId'] : '';
        $lastSeenAt = isset($payload['lastSeenAt']) && (is_int($payload['lastSeenAt']) || ctype_digit((string) $payload['lastSeenAt']))
            ? (int) $payload['lastSeenAt']
            : null;

        if ($userId === '' || $lastSeenAt === null) {
            return null;
        }

        return [
            'lastSeenAt' => $lastSeenAt,
            'status' => $this->statusFromTimestamp($lastSeenAt),
            'userId' => $userId,
        ];
    }

    private function eventCacheKey(int $cursor): string
    {
        return self::EVENT_CACHE_KEY_PREFIX.$cursor;
    }

    private function incrementCursor(): int
    {
        try {
            return (int) Cache::increment(self::CURSOR_CACHE_KEY);
        } catch (BadMethodCallException) {
            $cursor = $this->latestCursor() + 1;

            Cache::put(self::CURSOR_CACHE_KEY, $cursor, now()->addMinutes(self::CACHE_TTL_MINUTES));

            return $cursor;
        }
    }

    private function publishUpdate(string $userId, int $timestamp): int
    {
        $cursor = $this->incrementCursor();

        try {
            Cache::put(
                $this->eventCacheKey($cursor),
                [
                    'lastSeenAt' => $timestamp,
                    'userId' => $userId,
                ],
                now()->addMinutes(2),
            );
        } catch (BadMethodCallException) {
            // Ignore event fan-out when the cache store cannot persist it.
        }

        return $cursor;
    }

    private function resolveUserId(Model|Authenticatable|string|int $user): string
    {
        if ($user instanceof Model) {
            return (string) $user->getKey();
        }

        if ($user instanceof Authenticatable) {
            return (string) $user->getAuthIdentifier();
        }

        return (string) $user;
    }

    private function shouldPublishUpdate(?int $previousTimestamp, int $timestamp): bool
    {
        if ($previousTimestamp === null) {
            return true;
        }

        if ($this->statusFromTimestamp($previousTimestamp) !== self::STATUS_ONLINE) {
            return true;
        }

        return ($timestamp - $previousTimestamp) >= self::PUBLISH_COOLDOWN_SECONDS;
    }
}
