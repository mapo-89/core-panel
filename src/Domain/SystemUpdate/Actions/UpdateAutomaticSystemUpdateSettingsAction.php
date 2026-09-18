<?php

declare(strict_types=1);

namespace CorePanel\Domain\SystemUpdate\Actions;

use CorePanel\Domain\SystemUpdate\DTOs\AutomaticSystemUpdateSettingsData;
use CorePanel\Support\Administration\SystemUpdates\SystemUpdateSettings;

final readonly class UpdateAutomaticSystemUpdateSettingsAction
{
    public function __construct(private SystemUpdateSettings $settings) {}

    public function execute(AutomaticSystemUpdateSettingsData $data): void
    {
        $this->settings->update($data);
    }
}
