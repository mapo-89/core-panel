<?php

declare(strict_types=1);

namespace CorePanel\Domains\Permission\Actions;

use CorePanel\Domains\Permission\DTOs\RoleData;
use CorePanel\Support\Permissions\PermissionService;
use Illuminate\Database\Eloquent\Model;

final readonly class UpdateRoleAction
{
    public function __construct(private PermissionService $permissions) {}

    /**
     * @param  array{name:string,guard_name?:string}  $attributes
     */
    public function execute(Model $role, array $attributes): RoleData
    {
        return RoleData::fromModel(
            $this->permissions->updateRole($role, $attributes)->load('permissions')
        );
    }
}
