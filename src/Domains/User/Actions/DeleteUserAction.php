<?php

declare(strict_types=1);

namespace CorePanel\Domains\User\Actions;

use Illuminate\Database\Eloquent\Model;

final class DeleteUserAction
{
    public function execute(Model $user): void
    {
        $user->delete();
    }
}
