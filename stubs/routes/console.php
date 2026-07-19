<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

if ((bool) config('core-panel.horizon.enabled', true) && app()->bound('command.horizon.snapshot')) {
    Schedule::command('horizon:snapshot')->everyFiveMinutes();
}

if ((bool) config('database-backups.enabled', config('core-panel.administration.database_backups.enabled', true))) {
    Schedule::command('database-backups:auto')
        ->everyMinute()
        ->withoutOverlapping(60);
}

if ((bool) config('system-updates.automatic.enabled', config('core-panel.administration.system_updates.automatic.enabled', false))) {
    Schedule::command('system-updates:auto')->everyFiveMinutes();
}
