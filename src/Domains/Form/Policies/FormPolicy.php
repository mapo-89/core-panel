<?php

declare(strict_types=1);

namespace CorePanel\Domains\Form\Policies;

use CorePanel\Models\Form;
use CorePanel\Support\Permissions\PermissionService;
use Illuminate\Contracts\Auth\Authenticatable;

final readonly class FormPolicy
{
    public function __construct(private PermissionService $permissions) {}

    public function create(Authenticatable $user): bool
    {
        return $this->permissions->userHas($user, 'forms.create');
    }

    public function delete(Authenticatable $user, Form $form): bool
    {
        return $this->permissions->userHas($user, 'forms.delete');
    }

    public function update(Authenticatable $user, Form $form): bool
    {
        return $this->permissions->userHas($user, 'forms.update');
    }

    public function view(Authenticatable $user, Form $form): bool
    {
        return $this->permissions->userHas($user, 'forms.view');
    }

    public function viewAny(Authenticatable $user): bool
    {
        return $this->view($user);
    }
}
