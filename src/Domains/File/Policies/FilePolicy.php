<?php

declare(strict_types=1);

namespace CorePanel\Domains\File\Policies;

use CorePanel\Models\ManagedFile;
use CorePanel\Support\Permissions\PermissionService;
use Illuminate\Contracts\Auth\Authenticatable;

final readonly class FilePolicy
{
    public function __construct(
        private PermissionService $permissions,
    ) {}

    public function delete(Authenticatable $user, ManagedFile $file): bool
    {
        return $this->permissions->userHas($user, 'files.delete');
    }

    public function upload(Authenticatable $user): bool
    {
        return $this->permissions->userHas($user, 'files.upload');
    }

    public function view(Authenticatable $user, ManagedFile $file): bool
    {
        return $this->permissions->userHas($user, 'files.view');
    }

    public function viewAny(Authenticatable $user): bool
    {
        return $this->permissions->userHas($user, 'files.view');
    }
}
