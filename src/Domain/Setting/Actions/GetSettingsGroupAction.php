<?php

declare(strict_types=1);

namespace CorePanel\Domain\Setting\Actions;

use CorePanel\Support\Settings\SettingsRepository;

final readonly class GetSettingsGroupAction
{
    public function __construct(private SettingsRepository $settings) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(string $group, ?string $locale = null): array
    {
        return $this->settings->getGroup($group, $locale);
    }
}
