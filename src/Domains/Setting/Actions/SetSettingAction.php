<?php

declare(strict_types=1);

namespace CorePanel\Domains\Setting\Actions;

use CorePanel\Domains\Setting\DTOs\SettingData;
use CorePanel\Support\Settings\SettingsRepository;

final readonly class SetSettingAction
{
    public function __construct(private SettingsRepository $settings) {}

    public function execute(
        string $group,
        string $key,
        mixed $value,
        string $type = 'string',
        bool $isPublic = false,
        bool $isLocalized = false,
    ): SettingData {
        return SettingData::fromModel(
            $this->settings->set($group, $key, $value, $type, $isPublic, $isLocalized),
        );
    }
}
