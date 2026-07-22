<?php

declare(strict_types=1);

namespace CorePanel\Domain\Setting\Actions;

use CorePanel\Support\Settings\SettingsRepository;

final readonly class GetSettingAction
{
    public function __construct(private SettingsRepository $settings) {}

    public function execute(string $group, string $key, mixed $default = null, ?string $locale = null): mixed
    {
        return $this->settings->get($group, $key, $default, $locale);
    }
}
