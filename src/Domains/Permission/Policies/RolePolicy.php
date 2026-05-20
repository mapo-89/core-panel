<?php

declare(strict_types=1);

namespace CorePanel\Domains\Permission\Policies;

use CorePanel\Support\Permissions\PermissionService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

final readonly class RolePolicy
{
    public function __construct(private PermissionService $permissions) {}

    public function viewAny(Authenticatable $user): bool
    {
        return $this->permissions->userHas($user, 'roles.view');
    }

    public function view(Authenticatable $user, Model $role): bool
    {
        return $this->permissions->userHas($user, 'roles.view')
            && $this->permissions->canManageRole($user, $role);
    }

    public function create(Authenticatable $user): bool
    {
        return $this->permissions->userHas($user, 'roles.create');
    }

    public function update(Authenticatable $user, Model $role): bool
    {
        return $this->permissions->userHas($user, 'roles.update')
            && $this->permissions->canManageRole($user, $role);
    }

    public function delete(Authenticatable $user, Model $role): bool
    {
        return $this->permissions->userHas($user, 'roles.delete')
            && $this->permissions->canManageRole($user, $role);
    }
}
