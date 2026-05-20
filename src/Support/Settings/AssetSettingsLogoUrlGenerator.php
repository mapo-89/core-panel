<?php

declare(strict_types=1);

namespace CorePanel\Support\Settings;

use CorePanel\Contracts\SettingsLogoUrlGenerator;

final class AssetSettingsLogoUrlGenerator implements SettingsLogoUrlGenerator
{
    public function generate(string $path): string
    {
        return asset('storage/'.$path);
    }
}
