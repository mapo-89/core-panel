<?php

declare(strict_types=1);

namespace CorePanel\Support\Permissions;

final class CorePanelPermissions
{
    /**
     * @return list<string>
     */
    public static function defaults(): array
    {
        return app(CorePanelAccess::class)->managedPermissions();
    }
}
