<?php

declare(strict_types=1);

namespace CorePanel\Support\Settings;

use CorePanel\Contracts\SettingsLogoUrlGenerator;
use Illuminate\Support\Facades\Storage;

final class AssetSettingsLogoUrlGenerator implements SettingsLogoUrlGenerator
{
    public function generate(string $path): string
    {
        $disk = (string) config('core-panel.files.logo.disk', config('core-panel.files.disk', 'public'));

        if (! in_array($disk, ['local', 'public'], true)) {
            return Storage::disk($disk)->url($path);
        }

        return asset('storage/'.$path);
    }
}
