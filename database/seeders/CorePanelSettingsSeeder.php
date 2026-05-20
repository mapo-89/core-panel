<?php

declare(strict_types=1);

namespace CorePanel\Database\Seeders;

use CorePanel\Support\Settings\SettingsRepository;
use CorePanel\Support\Settings\SettingsSchema;
use Illuminate\Database\Seeder;

final class CorePanelSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = app(SettingsRepository::class);

        foreach (SettingsSchema::definitions() as $group => $definition) {
            foreach ((array) $definition['fields'] as $key => $field) {
                $settings->set(
                    $group,
                    $key,
                    $field['default'] ?? null,
                    (string) ($field['type'] ?? 'string'),
                    (bool) ($field['is_public'] ?? false),
                    (bool) ($field['is_localized'] ?? false),
                );
            }
        }
    }
}
