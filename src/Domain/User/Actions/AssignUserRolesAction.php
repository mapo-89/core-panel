<?php

declare(strict_types=1);

namespace CorePanel\Domain\User\Actions;

use CorePanel\Support\Permissions\PermissionService;
use CorePanel\Support\Users\UserModelManager;
use Illuminate\Contracts\Auth\Authenticatable;

final readonly class AssignUserRolesAction
{
    public function __construct(
        private PermissionService $permissions,
        private UserModelManager $users,
    ) {}

    /**
     * @param  list<string>  $roleNames
     */
    public function execute(Authenticatable $user, array $roleNames): void
    {
        if (! $this->users->supportsRoles()) {
            return;
        }

        $this->permissions->syncUserRoles($user, $roleNames);
    }
}
