<?php

declare(strict_types=1);

namespace CorePanel\Support\Administration\DatabaseBackups;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class RunAutomaticDatabaseBackupAction
{
    public function __construct(
        private DatabaseBackupService $backups,
        private DatabaseBackupSettings $settings,
    ) {}

    /**
     * @return array{message:string,status:string}
     */
    public function execute(): array
    {
        if (! $this->backups->enabled()) {
            return $this->skipped('disabled');
        }

        $settings = $this->settings->toArray();

        if (! $settings['automatic_enabled']) {
            return $this->skipped('automatic backups disabled');
        }

        $slot = $this->currentScheduledSlot($settings);

        if (! $slot instanceof Carbon) {
            return $this->skipped('not scheduled backup time');
        }

        $slotKey = $this->slotCacheKey($slot, $settings['timezone']);

        return Cache::lock('core-panel:database-backups:auto', 3600)->block(1, function () use ($slotKey): array {
            if (Cache::has($slotKey)) {
                return $this->skipped('backup already created for scheduled slot');
            }

            $backup = $this->backups->create('automatic');

            Cache::put($slotKey, $backup->name, now()->addDays(8));

            Log::info('Automatic database backup created.', [
                'name' => $backup->name,
            ]);

            return [
                'message' => $backup->name,
                'status' => 'created',
            ];
        });
    }

    /**
     * @param  array{
     *     automatic_enabled: bool,
     *     cloud_backup_enabled: bool,
     *     cloud_backup_path: string,
     *     encryption_code: string,
     *     encryption_enabled: bool,
     *     retention_count: int,
     *     retention_days: int,
     *     retention_mode: string,
     *     schedule_mode: string,
     *     system_time: string,
     *     time: string,
     *     time_mode: string,
     *     timezone: string,
     *     weekdays: list<string>
     * }  $settings
     */
    private function currentScheduledSlot(array $settings): ?Carbon
    {
        $timezone = $settings['timezone'];
        $now = now($timezone);
        $scheduledTime = $this->settings->scheduledTime();
        $slot = $this->timeToday($scheduledTime, $now);

        if ($slot->format('H:i') !== $now->format('H:i')) {
            return null;
        }

        if ($settings['schedule_mode'] === 'custom' && ! in_array(strtolower($now->englishDayOfWeek), $settings['weekdays'], true)) {
            return null;
        }

        return $slot;
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

    private function slotCacheKey(Carbon $slot, string $timezone): string
    {
        return sprintf(
            'core-panel:database-backups:auto:%s:%s',
            $timezone,
            $slot->format('Y-m-d-H-i'),
        );
    }

    private function timeToday(string $time, Carbon $now): Carbon
    {
        try {
            [$hour, $minute] = array_map('intval', explode(':', $time, 2));

            return $now->copy()->setTime($hour, $minute, 0);
        } catch (Throwable) {
            return $now->copy()->setTime(2, 0, 0);
        }
    }
}
