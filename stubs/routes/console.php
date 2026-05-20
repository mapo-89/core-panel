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
