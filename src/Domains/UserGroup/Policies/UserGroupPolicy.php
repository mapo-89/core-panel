<?php

declare(strict_types=1);

namespace CorePanel\Domains\UserGroup\Policies;

use CorePanel\Support\Permissions\PermissionService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

final readonly class UserGroupPolicy
{
    public function __construct(private PermissionService $permissions) {}

    public function viewAny(Authenticatable $user): bool
    {
        return $this->permissions->userHas($user, 'user-groups.view');
    }

    public function view(Authenticatable $user, Model $userGroup): bool
    {
        return $this->permissions->userHas($user, 'user-groups.view');
    }

    public function create(Authenticatable $user): bool
    {
        return $this->permissions->userHas($user, 'user-groups.create');
    }

    public function import(Authenticatable $user): bool
    {
        return $this->permissions->userHas($user, 'user-groups.import');
    }

    public function update(Authenticatable $user, Model $userGroup): bool
    {
        return $this->permissions->userHas($user, 'user-groups.update');
    }

    public function delete(Authenticatable $user, Model $userGroup): bool
    {
        return $this->permissions->userHas($user, 'user-groups.delete');
    }
}
