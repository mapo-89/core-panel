<?php

declare(strict_types=1);

namespace CorePanel\Domains\Permission\Actions;

use CorePanel\Database\Seeders\CorePanelPermissionSeeder;
use CorePanel\Support\Permissions\CorePanelAccess;
use CorePanel\Support\Permissions\PermissionService;

final readonly class ResyncAccessMatrixAction
{
    public function __construct(
        private CorePanelAccess $access,
        private PermissionService $permissions,
    ) {}

    public function execute(bool $fresh = false): void
    {
        $seeder = new CorePanelPermissionSeeder;
        $seeder->fresh = $fresh;
        $seeder->run($this->permissions, $this->access);
    }
}
