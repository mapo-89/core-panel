<?php

declare(strict_types=1);

namespace CorePanel\Support\Administration\SystemUpdates;

use CorePanel\Contracts\SystemUpdateSettingsAccess;
use CorePanel\Domain\SystemUpdate\Enums\SystemUpdateInterval;
use CorePanel\Domain\SystemUpdate\Enums\SystemUpdateMode;
use CorePanel\Domain\SystemUpdate\Enums\SystemUpdateWeekday;
use CorePanel\Support\Permissions\PermissionService;
use Illuminate\Contracts\Auth\Authenticatable;

final readonly class SystemUpdateSettingsPayload
{
    public function __construct(
        private SystemUpdateSettingsAccess $access,
        private PermissionService $permissions,
        private SystemUpdateSettings $settings,
    ) {}

    /** @return array<string, mixed>|null */
    public function forUser(Authenticatable $user): ?array
    {
        if (! $this->access->allows()) {
            return null;
        }

        $settings = $this->settings->toArray();

        return [
            'canUpdate' => $this->permissions->userHas($user, 'system-updates.update'),
            'enabled' => $settings['automatic_enabled'],
            'interval' => $settings['interval'],
            'intervalOptions' => collect(SystemUpdateInterval::cases())->map(
                static fn (SystemUpdateInterval $interval): array => [
                    'label' => __("system_updates.interval_{$interval->value}"),
                    'value' => $interval->value,
                ],
            )->all(),
            'lastAutomaticRunAt' => $settings['last_automatic_run_at'],
            'maintenanceWindowEnabled' => $settings['maintenance_window_enabled'],
            'mode' => $settings['mode'],
            'modeOptions' => collect(SystemUpdateMode::cases())->map(
                static fn (SystemUpdateMode $mode): array => [
                    'label' => __("system_updates.mode_{$mode->value}"),
                    'value' => $mode->value,
                ],
            )->all(),
            'time' => $settings['time'],
            'timezone' => $settings['timezone'],
            'weekday' => $settings['weekday'],
            'weekdayOptions' => collect(SystemUpdateWeekday::cases())->map(
                static fn (SystemUpdateWeekday $weekday): array => [
                    'label' => __("system_updates.weekday_{$weekday->value}"),
                    'value' => $weekday->value,
                ],
            )->all(),
            'windowEnd' => $settings['window_end'],
            'windowStart' => $settings['window_start'],
        ];
    }
}
