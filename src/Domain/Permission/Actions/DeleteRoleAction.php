<?php

declare(strict_types=1);

namespace CorePanel\Domain\Permission\Actions;

use CorePanel\Support\Permissions\PermissionService;
use Illuminate\Database\Eloquent\Model;

final readonly class DeleteRoleAction
{
    public function __construct(private PermissionService $permissions) {}

    public function execute(Model $role): void
    {
        $this->permissions->deleteRole($role);
    }
}
