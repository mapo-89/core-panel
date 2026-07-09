<?php

declare(strict_types=1);

namespace CorePanel\Support\Administration\SystemUpdates;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

final readonly class RunAutomaticSystemUpdateAction
{
    public function __construct(private SystemUpdaterClient $updater) {}

    /**
     * @return array{message:string,status:string}
     */
    public function execute(): array
    {
        if (! $this->updater->enabled()) {
            return $this->skipped('disabled');
        }

        if (! (bool) config('core-panel.administration.system_updates.automatic.enabled', false)) {
            return $this->skipped('automatic updates disabled');
        }

        if (! $this->isWithinMaintenanceWindow()) {
            return $this->skipped('outside maintenance window');
        }

        if ($this->hasRecentUserActivity()) {
            return $this->skipped('recent user activity detected');
        }

        return Cache::lock('core-panel:system-updates:auto', 900)->block(1, function (): array {
            $status = $this->updater->status();

            if ((bool) data_get($status, 'update_running', false)) {
                return $this->skipped('update already running');
            }

            $check = $this->updater->check();

            if (! (bool) data_get($check, 'update_available', false)) {
                Log::info('System update check completed without available update.', [
                    'images' => data_get($check, 'images', []),
                ]);

                return [
                    'message' => 'no update available',
                    'status' => 'checked',
                ];
            }

            $update = $this->updater->update();

            Log::info('Automatic system update started.', [
                'images' => data_get($update, 'images', []),
            ]);

            return [
                'message' => 'update started',
                'status' => 'updated',
            ];
        });
    }

    /**
     * @return array{message:string,status:string}
     */
    private function skipped(string $message): array
    {
        return [
            'message' => $message,
            'status' => 'skipped',
        ];
    }

    private function isWithinMaintenanceWindow(): bool
    {
        $timezone = (string) config('core-panel.administration.system_updates.automatic.timezone', config('app.timezone'));
        $now = now($timezone);
        $start = $this->timeToday((string) config('core-panel.administration.system_updates.automatic.window_start', '02:00'), $now);
        $end = $this->timeToday((string) config('core-panel.administration.system_updates.automatic.window_end', '04:00'), $now);

        if ($end->lessThanOrEqualTo($start)) {
            return $now->greaterThanOrEqualTo($start) || $now->lessThan($end);
        }

        return $now->betweenIncluded($start, $end);
    }

    private function timeToday(string $time, Carbon $now): Carbon
    {
        try {
            [$hour, $minute] = array_map('intval', explode(':', $time, 2));

            return $now->copy()->setTime($hour, $minute);
        } catch (Throwable) {
            return $now->copy()->setTime(2, 0);
        }
    }

    private function hasRecentUserActivity(): bool
    {
        $inactiveMinutes = max(0, (int) config('core-panel.administration.system_updates.automatic.inactive_minutes', 15));

        if ($inactiveMinutes === 0) {
            return false;
        }

        $cutoffTimestamp = now()->subMinutes($inactiveMinutes)->timestamp;

        if ($this->hasRecentDatabaseSessionActivity($cutoffTimestamp)) {
            return true;
        }

        return $this->hasRecentPresenceActivity($cutoffTimestamp);
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

        return DB::table($sessionsTable)
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', $cutoffTimestamp)
            ->exists();
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
        $users = $userModel->newQuery()
            ->select($userModel->getKeyName())
            ->cursor();

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
}
