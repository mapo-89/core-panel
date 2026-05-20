<?php

declare(strict_types=1);

namespace CorePanel\Domains\Permission\Actions;

use CorePanel\Domains\Permission\DTOs\RoleData;
use CorePanel\Support\Permissions\PermissionService;

final readonly class CreateRoleAction
{
    public function __construct(private PermissionService $permissions) {}

    /**
     * @param  array{name:string,guard_name?:string}  $attributes
     */
    public function execute(array $attributes): RoleData
    {
        return RoleData::fromModel(
            $this->permissions->createRole($attributes)->load('permissions')
        );
    }
}
