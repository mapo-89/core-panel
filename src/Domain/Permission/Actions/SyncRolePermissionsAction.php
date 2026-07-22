<?php

declare(strict_types=1);

namespace CorePanel\Domain\Permission\Actions;

use CorePanel\Domain\Permission\DTOs\RoleData;
use CorePanel\Support\Permissions\PermissionService;
use Illuminate\Database\Eloquent\Model;

final readonly class SyncRolePermissionsAction
{
    public function __construct(private PermissionService $permissions) {}

    /**
     * @param  list<string>  $permissionNames
     */
    public function execute(Model $role, array $permissionNames): RoleData
    {
        return RoleData::fromModel(
            $this->permissions->syncRolePermissions($role, $permissionNames)
        );
    }
}
