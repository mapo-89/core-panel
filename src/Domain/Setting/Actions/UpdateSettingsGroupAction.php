<?php

declare(strict_types=1);

namespace CorePanel\Domain\Setting\Actions;

use CorePanel\Support\Settings\SettingsRepository;

final readonly class UpdateSettingsGroupAction
{
    public function __construct(private SettingsRepository $settings) {}

    /**
     * @param  array<string, array{type?:string,is_public?:bool,is_localized?:bool,value:mixed}>  $values
     * @return array<string, mixed>
     */
    public function execute(string $group, array $values): array
    {
        return $this->settings->updateGroup($group, $values);
    }
}
