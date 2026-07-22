<?php

declare(strict_types=1);

namespace CorePanel\Domain\User\Policies;

use CorePanel\Support\Permissions\PermissionService;
use CorePanel\Support\Users\UserModelManager;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

final readonly class UserPolicy
{
    public function __construct(
        private PermissionService $permissions,
        private UserModelManager $users,
    ) {}

    public function viewAny(Authenticatable $user): bool
    {
        return $this->permissions->userHas($user, 'users.view');
    }

    public function view(Authenticatable $user, Model $target): bool
    {
        return $this->permissions->userHas($user, 'users.view');
    }

    public function create(Authenticatable $user): bool
    {
        return $this->permissions->userHas($user, 'users.create');
    }

    public function update(Authenticatable $user, Model $target): bool
    {
        return $this->permissions->userHas($user, 'users.update');
    }

    public function delete(Authenticatable $user, Model $target): bool
    {
        return $this->permissions->userHas($user, 'users.delete')
            && $this->users->canDeleteManagedUser($user, $target);
    }

    public function restore(Authenticatable $user, Model $target): bool
    {
        return $this->permissions->userHas($user, 'users.update');
    }

    public function forceDelete(Authenticatable $user, Model $target): bool
    {
        return $this->permissions->userHas($user, 'users.delete')
            && $this->users->canDeleteManagedUser($user, $target);
    }

    public function viewApiTokens(Authenticatable $user, Model $target): bool
    {
        return $this->isSelf($user, $target)
            || $this->permissions->userHas($user, 'api.tokens.view');
    }

    public function createApiTokens(Authenticatable $user, Model $target): bool
    {
        return $this->isSelf($user, $target)
            || $this->permissions->userHas($user, 'api.tokens.create');
    }

    public function deleteApiTokens(Authenticatable $user, Model $target): bool
    {
        return $this->isSelf($user, $target)
            || $this->permissions->userHas($user, 'api.tokens.delete');
    }

    public function viewOAuthClients(Authenticatable $user, Model $target): bool
    {
        return $this->permissions->userHas($user, 'oauth.clients.view');
    }

    public function createOAuthClients(Authenticatable $user, Model $target): bool
    {
        return $this->permissions->userHas($user, 'oauth.clients.create');
    }

    public function updateOAuthClients(Authenticatable $user, Model $target): bool
    {
        return $this->permissions->userHas($user, 'oauth.clients.update');
    }

    public function deleteOAuthClients(Authenticatable $user, Model $target): bool
    {
        return $this->permissions->userHas($user, 'oauth.clients.delete');
    }

    private function isSelf(Authenticatable $user, Model $target): bool
    {
        return (string) $user->getAuthIdentifier() === (string) $target->getKey();
    }
}
