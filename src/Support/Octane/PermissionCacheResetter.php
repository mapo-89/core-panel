<?php

declare(strict_types=1);

namespace CorePanel\Support\Octane;

use Spatie\Permission\PermissionRegistrar;

final readonly class PermissionCacheResetter
{
    public function __construct(private PermissionRegistrar $registrar) {}

    public function reset(): void
    {
        $this->registrar->setPermissionsTeamId(null);
        $this->registrar->forgetCachedPermissions();
        $this->registrar->clearPermissionsCollection();
    }
}
