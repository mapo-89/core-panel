<?php

declare(strict_types=1);

namespace CorePanel\Support\Administration\SystemUpdates;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

final class SystemUpdateJobStatus
{
    private const CACHE_KEY = 'core-panel:system-updates:job-status';

    private const ATTEMPT_CACHE_KEY_PREFIX = 'core-panel:system-updates:job-status:attempt:';

    public function begin(?string $attemptId = null): string
    {
        $resolvedAttemptId = is_string($attemptId) && trim($attemptId) !== ''
            ? trim($attemptId)
            : (string) Str::uuid();

        try {
            $cache = $this->cache();
            $marker = [
                'attempt_id' => $resolvedAttemptId,
                'attempted_at' => now()->toISOString(),
            ];

            $cache->put($this->attemptCacheKey($resolvedAttemptId), $marker, now()->addHours(12));
            $cache->put(self::CACHE_KEY, $resolvedAttemptId, now()->addHours(12));
        } catch (Throwable $exception) {
            report($exception);
        }

        return $resolvedAttemptId;
    }

    public function accept(string $attemptId): void
    {
        try {
            $cache = $this->cache();
            $current = $this->marker($cache, $attemptId);

            if (! is_array($current) || ($current['attempt_id'] ?? null) !== $attemptId) {
                return;
            }

            $cache->put($this->attemptCacheKey($attemptId), [
                ...$current,
                'accepted_at' => now()->toISOString(),
            ], now()->addHours(12));

            if ($this->currentAttemptId($cache->get(self::CACHE_KEY)) === $attemptId) {
                $cache->put(self::CACHE_KEY, $attemptId, now()->addHours(12));
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function clear(?string $attemptId = null): void
    {
        try {
            $cache = $this->cache();
            $resolvedAttemptId = is_string($attemptId) && trim($attemptId) !== ''
                ? trim($attemptId)
                : null;

            if ($resolvedAttemptId === null) {
                $currentAttemptId = $this->currentAttemptId($cache->get(self::CACHE_KEY));

                if ($currentAttemptId !== null) {
                    $cache->forget($this->attemptCacheKey($currentAttemptId));
                }

                $cache->forget(self::CACHE_KEY);

                return;
            }

            $cache->forget($this->attemptCacheKey($resolvedAttemptId));

            $current = $cache->get(self::CACHE_KEY);
            $currentAttemptId = $this->currentAttemptId($current);

            if ($currentAttemptId === $resolvedAttemptId) {
                $cache->forget(self::CACHE_KEY);
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function reject(string $attemptId): void
    {
        $resolvedAttemptId = trim($attemptId);

        if ($resolvedAttemptId === '') {
            return;
        }

        $this->recordFailure($resolvedAttemptId, 'system_updates.update_conflict');
    }

    public function fail(?string $attemptId = null): void
    {
        $this->recordFailure($attemptId, 'system_updates.action_failed');
    }

    private function recordFailure(?string $attemptId, string $errorKey): void
    {
        try {
            $cache = $this->cache();
            $failure = $this->marker($cache, $attemptId);
            $failedAt = now()->toISOString();
            $resolvedAttemptId = is_string($attemptId) && trim($attemptId) !== ''
                ? trim($attemptId)
                : (is_array($failure) && is_string($failure['attempt_id'] ?? null)
                    ? $failure['attempt_id']
                    : (string) Str::uuid());

            $cache->put($this->attemptCacheKey($resolvedAttemptId), [
                'attempt_id' => $resolvedAttemptId,
                'attempted_at' => is_array($failure) && is_string($failure['attempted_at'] ?? null)
                    ? $failure['attempted_at']
                    : $failedAt,
                'error_key' => $errorKey,
                'failed_at' => $failedAt,
            ], now()->addHours(12));

            $currentAttemptId = $this->currentAttemptId($cache->get(self::CACHE_KEY));

            if ($currentAttemptId === null || $currentAttemptId === $resolvedAttemptId) {
                $cache->put(self::CACHE_KEY, $resolvedAttemptId, now()->addHours(12));
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * @param  array<string, mixed>  $status
     * @return array<string, mixed>
     */
    public function apply(array $status, ?string $attemptId = null): array
    {
        $resolvedAttemptId = is_string($attemptId) && trim($attemptId) !== ''
            ? trim($attemptId)
            : null;

        try {
            $cache = $this->cache();
            $failure = $this->marker($cache, $resolvedAttemptId);
        } catch (Throwable) {
            // This auxiliary overlay is polled frequently. Degrade silently when
            // its store is unavailable so one outage cannot flood error reporting.
            return $status;
        }

        if (! is_array($failure)) {
            return $status;
        }

        $markerAttemptId = is_string($failure['attempt_id'] ?? null)
            ? $failure['attempt_id']
            : null;
        $updaterAttemptId = is_string($status['update_attempt_id'] ?? null)
            ? $status['update_attempt_id']
            : null;
        $updaterState = $status['last_update_state'] ?? null;
        $terminalUpdaterState = in_array($updaterState, ['failed', 'success'], true)
            && ($status['update_running'] ?? false) !== true;

        if (
            $markerAttemptId !== null
            && is_string($failure['accepted_at'] ?? null)
        ) {
            if ($updaterAttemptId === null) {
                $status['update_attempt_id'] = $markerAttemptId;
                $updaterAttemptId = $markerAttemptId;
            }

            if ($terminalUpdaterState && $updaterAttemptId === $markerAttemptId) {
                $this->clear($markerAttemptId);
            }
        }

        if (! is_string($failure['failed_at'] ?? null)) {
            return $status;
        }

        $failedAt = $failure['failed_at'];
        $errorKey = ($failure['error_key'] ?? null) === 'system_updates.update_conflict'
            ? 'system_updates.update_conflict'
            : 'system_updates.action_failed';

        if (($status['update_running'] ?? false) === true) {
            if ($markerAttemptId === null || $markerAttemptId === $updaterAttemptId) {
                $this->clear($markerAttemptId);

                return $status;
            }

            if ($resolvedAttemptId === null) {
                if ($updaterAttemptId === null) {
                    $this->clear($markerAttemptId);
                }

                return $status;
            }
        }

        $attemptedAt = is_string($failure['attempted_at'] ?? null)
            ? $failure['attempted_at']
            : $failedAt;

        if (is_string($status['last_update_at'] ?? null) && in_array($updaterState, ['failed', 'success'], true)) {
            if ($markerAttemptId !== null && $markerAttemptId === $updaterAttemptId) {
                $this->clear($markerAttemptId);

                return $status;
            }

            try {
                if (
                    ($updaterAttemptId === null || $resolvedAttemptId === null)
                    && Carbon::parse($status['last_update_at'])->greaterThanOrEqualTo(Carbon::parse($attemptedAt))
                ) {
                    if (
                        $resolvedAttemptId === null
                        && $updaterAttemptId !== null
                        && $updaterAttemptId !== $markerAttemptId
                    ) {
                        $this->clearCurrentAttemptPointer($markerAttemptId);
                    } else {
                        $this->clear($markerAttemptId);
                    }

                    return $status;
                }
            } catch (Throwable) {
                // Invalid updater timestamps must not suppress a recorded transport failure.
            }
        }

        if ($errorKey === 'system_updates.update_conflict') {
            return [
                ...$status,
                'error' => __($errorKey),
                'last_update_at' => $failedAt,
                'last_update_state' => 'failed',
                'update_attempt_id' => $markerAttemptId,
                'update_running' => false,
            ];
        }

        return [
            ...$status,
            'error' => __($errorKey),
            'last_update_at' => $failedAt,
            'last_update_state' => 'failed',
            'update_attempt_id' => $markerAttemptId,
            'update_running' => false,
        ];
    }

    private function cache(): Repository
    {
        return Cache::store((string) config(
            'core-panel.administration.system_updates.status_store',
            config('cache.default'),
        ));
    }

    private function attemptCacheKey(string $attemptId): string
    {
        return self::ATTEMPT_CACHE_KEY_PREFIX.$attemptId;
    }

    /** @return array<string, mixed>|null */
    private function marker(Repository $cache, ?string $attemptId): ?array
    {
        $current = $cache->get(self::CACHE_KEY);

        if (is_string($attemptId) && trim($attemptId) !== '') {
            $resolvedAttemptId = trim($attemptId);
            $marker = $cache->get($this->attemptCacheKey($resolvedAttemptId));

            if (is_array($marker)) {
                return $marker;
            }

            return is_array($current) && ($current['attempt_id'] ?? null) === $resolvedAttemptId
                ? $current
                : null;
        }

        if (is_array($current)) {
            return $current;
        }

        if (! is_string($current) || trim($current) === '') {
            return null;
        }

        $marker = $cache->get($this->attemptCacheKey(trim($current)));

        return is_array($marker) ? $marker : null;
    }

    private function currentAttemptId(mixed $current): ?string
    {
        if (is_string($current) && trim($current) !== '') {
            return trim($current);
        }

        return is_array($current) && is_string($current['attempt_id'] ?? null)
            ? $current['attempt_id']
            : null;
    }

    private function clearCurrentAttemptPointer(?string $attemptId): void
    {
        try {
            $cache = $this->cache();

            if ($attemptId === null || $this->currentAttemptId($cache->get(self::CACHE_KEY)) === $attemptId) {
                $cache->forget(self::CACHE_KEY);
            }
        } catch (Throwable) {
            // The pointer is auxiliary; retain the scoped marker and degrade silently.
        }
    }
}
