<?php

declare(strict_types=1);

namespace CorePanel\Domains\User\Actions;

use CorePanel\Support\Users\UserModelManager;
use Illuminate\Database\Eloquent\Model;

final readonly class ForceDeleteUserAction
{
    public function __construct(private UserModelManager $users) {}

    public function execute(Model $user): void
    {
        if ($this->users->supportsSoftDeletes()) {
            $user->forceDelete();

            return;
        }

        $user->delete();
    }
}
