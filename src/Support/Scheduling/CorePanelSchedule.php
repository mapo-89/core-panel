<?php

declare(strict_types=1);

namespace CorePanel\Support\Scheduling;

use Illuminate\Console\Scheduling\Schedule;

final class CorePanelSchedule
{
    /** @var \WeakMap<Schedule, true> */
    private \WeakMap $registered;

    public function __construct()
    {
        $this->registered = new \WeakMap;
    }

    public function register(Schedule $schedule): void
    {
        if (isset($this->registered[$schedule])) {
            return;
        }

        $this->registered[$schedule] = true;

        if ((bool) config('core-panel.horizon.enabled', true) && app()->bound('command.horizon.snapshot')) {
            $schedule->command('horizon:snapshot')->everyFiveMinutes();
        }

        if ((bool) config('database-backups.enabled', config('core-panel.administration.database_backups.enabled', true))) {
            $schedule->command('database-backups:auto')->everyMinute()->withoutOverlapping(60);
        }

        if ((bool) config('core-panel.administration.system_updates.enabled', true)) {
            $schedule->command('system-updates:auto')->everyMinute()->withoutOverlapping(20)->onOneServer();
        }
    }
}
