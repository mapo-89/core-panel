<?php

declare(strict_types=1);

namespace CorePanel\Domain\SystemUpdate\DTOs;

use CorePanel\Domain\SystemUpdate\Enums\SystemUpdateInterval;
use CorePanel\Domain\SystemUpdate\Enums\SystemUpdateMode;
use CorePanel\Domain\SystemUpdate\Enums\SystemUpdateWeekday;

final readonly class AutomaticSystemUpdateSettingsData
{
    public function __construct(
        public bool $enabled,
        public SystemUpdateInterval $interval,
        public bool $maintenanceWindowEnabled,
        public SystemUpdateMode $mode,
        public string $time,
        public ?SystemUpdateWeekday $weekday,
        public string $windowEnd,
        public string $windowStart,
    ) {}

    /**
     * @param  array<string, mixed>  $values
     */
    public static function fromArray(array $values): self
    {
        return new self(
            enabled: (bool) $values['automatic_enabled'],
            interval: SystemUpdateInterval::from((string) $values['interval']),
            maintenanceWindowEnabled: (bool) $values['maintenance_window_enabled'],
            mode: SystemUpdateMode::from((string) $values['mode']),
            time: (string) $values['time'],
            weekday: filled($values['weekday'] ?? null)
                ? SystemUpdateWeekday::from((string) $values['weekday'])
                : null,
            windowEnd: (string) ($values['window_end'] ?? '04:00'),
            windowStart: (string) ($values['window_start'] ?? '02:00'),
        );
    }
}
