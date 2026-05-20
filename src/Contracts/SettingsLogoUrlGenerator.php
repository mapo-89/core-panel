<?php

declare(strict_types=1);

namespace CorePanel\Contracts;

interface SettingsLogoUrlGenerator
{
    public function generate(string $path): string;
}
