<?php

declare(strict_types=1);

namespace CorePanel\Support\Administration\SystemUpdates;

use CorePanel\Domain\SystemUpdate\Enums\SystemUpdateInterval;
use CorePanel\Domain\SystemUpdate\Enums\SystemUpdateMode;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

final readonly class RunAutomaticSystemUpdateAction
{
    private const SCHEDULER_INTERVAL_MINUTES = 1;

    public function __construct(
        private SystemUpdaterClient $updater,
        private SystemUpdateSettings $settings,
    ) {}

    /** @return array{message:string,status:string} */
    public function execute(): array
    {
        if (! $this->updater->enabled()) {
            return $this->skipped('disabled');
        }

        $settings = $this->settings->toArray();

        if (! $settings['automatic_enabled']) {
            return $this->skipped('automatic updates disabled');
        }

        $now = now($settings['timezone']);
        $dueSlot = $this->dueSlot($settings, $now);
        $pendingAttempt = $this->settings->pendingAutomaticAttempt();
        $retryableSlot = $this->settings->retryableAutomaticSlot();

        if ($dueSlot === null && $pendingAttempt === null && $retryableSlot === null) {
            return $this->skipped('not due');
        }

        $lock = Cache::lock('core-panel:system-updates:auto', 900);

        if (! $lock->get()) {
            return $this->skipped('automatic update already being coordinated');
        }

        try {
            if ($pendingAttempt !== null) {
                $pendingResult = $this->reconcilePendingAttempt($pendingAttempt);

                if ($pendingResult !== null) {
                    return $pendingResult;
                }

                $retryableSlot = $pendingAttempt['slot'];
            }

            $executionSlot = $retryableSlot !== null
                ? ['key' => $retryableSlot, 'scheduled_at' => $now]
                : $dueSlot;

            if ($executionSlot === null) {
                return $this->skipped('not due');
            }

            if ($settings['mode'] === SystemUpdateMode::Install->value
                && $settings['maintenance_window_enabled']
                && ! $this->isWithinMaintenanceWindow($settings, $executionSlot['scheduled_at'])) {
                return $this->skipped('outside maintenance window');
            }

            if ($this->hasRecentUserActivity()) {
                return $this->skipped('recent user activity detected');
            }

            if ($this->slotAlreadyHandled($executionSlot['key'])) {
                return $this->skipped('scheduled run already handled');
            }

            $status = $this->updater->status();

            if ((bool) data_get($status, 'update_running', false)) {
                return $this->skipped('update already running');
            }

            $check = $this->updater->check();

            if ($settings['mode'] === SystemUpdateMode::Install->value && (bool) data_get($check, 'update_available', false)) {
                $attemptId = (string) Str::uuid();
                $update = $this->updater->update($attemptId);
                Log::info('Automatic system update started.', [
                    'attempt_id' => $attemptId,
                    'images' => data_get($update, 'images', []),
                ]);
                $this->settings->recordAutomaticAttempt($executionSlot['key'], $attemptId);
                $result = ['message' => 'update started', 'status' => 'updated'];
            } else {
                Log::info('Automatic system update check completed.', [
                    'images' => data_get($check, 'images', []),
                    'update_available' => data_get($check, 'update_available'),
                ]);
                $result = [
                    'message' => (bool) data_get($check, 'update_available', false) ? 'update available' : 'no update available',
                    'status' => 'checked',
                ];

                $this->completeSlot($executionSlot['key']);
            }

            return $result;
        } finally {
            $this->release($lock);
        }
    }

    /**
     * @param  array{attempt_id:string,slot:string}  $pendingAttempt
     * @return array{message:string,status:string}|null
     */
    private function reconcilePendingAttempt(array $pendingAttempt): ?array
    {
        $status = $this->updater->status($pendingAttempt['attempt_id']);
        $statusAttemptId = data_get($status, 'update_attempt_id');

        if (! is_string($statusAttemptId) || $statusAttemptId !== $pendingAttempt['attempt_id']) {
            if ((bool) data_get($status, 'update_running', false)) {
                return $this->skipped('update already running');
            }

            $this->markAttemptForRetry($pendingAttempt, 'Automatic system update attempt is no longer known by the updater and remains eligible for retry.');

            return null;
        }

        if ((bool) data_get($status, 'update_running', false)) {
            return $this->skipped('update already running');
        }

        $state = data_get($status, 'last_update_state');

        if ($state === 'success') {
            $this->completeSlot($pendingAttempt['slot']);

            Log::info('Automatic system update completed.', [
                'attempt_id' => $pendingAttempt['attempt_id'],
            ]);

            return $this->skipped('scheduled run already handled');
        }

        if ($state !== 'failed') {
            return $this->skipped('automatic update outcome pending');
        }

        $this->markAttemptForRetry($pendingAttempt, 'Automatic system update failed and remains eligible for retry.');

        return null;
    }

    /** @param array{attempt_id:string,slot:string} $pendingAttempt */
    private function markAttemptForRetry(array $pendingAttempt, string $message): void
    {
        $this->settings->markAutomaticAttemptForRetry($pendingAttempt['attempt_id'], $pendingAttempt['slot']);
        Cache::forget($this->slotCacheKey($pendingAttempt['slot']));
        Log::warning($message, [
            'attempt_id' => $pendingAttempt['attempt_id'],
        ]);
    }

    private function completeSlot(string $slot): void
    {
        Cache::put($this->slotCacheKey($slot), true, now()->addDays(8));
        $this->settings->recordAutomaticRun($slot, now());
    }

    /**
     * @param  array{interval:string,time:string,weekday:?string}  $settings
     * @return array{key:string,scheduled_at:Carbon}|null
     */
    private function dueSlot(array $settings, Carbon $now): ?array
    {
        $graceMinutes = max(1, (int) $this->automaticUpdateConfig('grace_minutes', 15))
            + $this->inactiveMinutes()
            + self::SCHEDULER_INTERVAL_MINUTES;
        $scheduledToday = $this->timeToday($settings['time'], $now);

        foreach ([$scheduledToday, $scheduledToday->copy()->subDay()] as $scheduledAt) {
            if ($settings['interval'] === SystemUpdateInterval::Weekly->value
                && strtolower($scheduledAt->englishDayOfWeek) !== $settings['weekday']) {
                continue;
            }

            if ($now->lessThan($scheduledAt) || $now->greaterThan($scheduledAt->copy()->addMinutes($graceMinutes))) {
                continue;
            }

            return [
                'key' => implode('|', [
                    $settings['interval'],
                    $scheduledAt->toDateString(),
                    $settings['time'],
                    $now->getTimezone()->getName(),
                ]),
                'scheduled_at' => $scheduledAt,
            ];
        }

        return null;
    }

    private function slotAlreadyHandled(string $slot): bool
    {
        return $this->settings->lastAutomaticSlot() === $slot || Cache::has($this->slotCacheKey($slot));
    }

    private function slotCacheKey(string $slot): string
    {
        return 'core-panel:system-updates:auto:slot:'.hash('sha256', $slot);
    }

    /** @param array{window_end:string,window_start:string} $settings */
    private function isWithinMaintenanceWindow(array $settings, Carbon $now): bool
    {
        $start = $this->timeToday($settings['window_start'], $now);
        $end = $this->timeToday($settings['window_end'], $now);

        if ($end->lessThanOrEqualTo($start)) {
            return $now->greaterThanOrEqualTo($start) || $now->lessThan($end);
        }

        return $now->greaterThanOrEqualTo($start) && $now->lessThan($end);
    }

    private function timeToday(string $time, Carbon $now): Carbon
    {
        [$hour, $minute] = array_map('intval', explode(':', $time, 2));

        return $now->copy()->setTime($hour, $minute);
    }

    private function hasRecentUserActivity(): bool
    {
        $inactiveMinutes = $this->inactiveMinutes();

        if ($inactiveMinutes === 0) {
            return false;
        }

        $cutoffTimestamp = now()->subMinutes($inactiveMinutes)->timestamp;

        return $this->hasRecentDatabaseSessionActivity($cutoffTimestamp)
            || $this->hasRecentPresenceActivity($cutoffTimestamp);
    }

    private function inactiveMinutes(): int
    {
        return max(0, (int) $this->automaticUpdateConfig('inactive_minutes', 15));
    }

    private function hasRecentDatabaseSessionActivity(int $cutoffTimestamp): bool
    {
        if (config('session.driver') !== 'database') {
            return false;
        }

        $sessionsTable = (string) config('session.table', 'sessions');

        if (! Schema::hasTable($sessionsTable)) {
            return false;
        }

        return DB::table($sessionsTable)->whereNotNull('user_id')->where('last_activity', '>=', $cutoffTimestamp)->exists();
    }

    private function hasRecentPresenceActivity(int $cutoffTimestamp): bool
    {
        $userModelClass = config('core-panel.user_model');

        if (! is_string($userModelClass) || ! class_exists($userModelClass)) {
            return false;
        }

        $userModel = new $userModelClass;

        if (! $userModel instanceof Model) {
            return false;
        }

        /** @var iterable<object> $users */
        $users = $userModel->newQuery()->select($userModel->getKeyName())->cursor();

        foreach ($users as $user) {
            if (! method_exists($user, 'corePanelPresenceLastSeenAt')) {
                continue;
            }

            $lastSeenAt = $user->corePanelPresenceLastSeenAt();

            if ((is_int($lastSeenAt) || ctype_digit((string) $lastSeenAt)) && (int) $lastSeenAt >= $cutoffTimestamp) {
                return true;
            }
        }

        return false;
    }

    private function automaticUpdateConfig(string $key, string|int|null $default): string|int|null
    {
        return config("system-updates.automatic.{$key}", config("core-panel.administration.system_updates.automatic.{$key}", $default));
    }

    /** @return array{message:string,status:string} */
    private function skipped(string $message): array
    {
        return ['message' => $message, 'status' => 'skipped'];
    }

    private function release(Lock $lock): void
    {
        try {
            $lock->release();
        } catch (Throwable $throwable) {
            report($throwable);
        }
    }
}
