<?php

declare(strict_types=1);

namespace CorePanel\Support\Administration\SystemUpdates;

use CorePanel\Contracts\SystemUpdateSettingsAccess;

final class AllowSystemUpdateSettingsAccess implements SystemUpdateSettingsAccess
{
    public function allows(): bool
    {
        return true;
    }
}
