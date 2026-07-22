<?php

declare(strict_types=1);

namespace CorePanel\Domain\User\Actions;

use Illuminate\Database\Eloquent\Model;

final class DeleteUserAction
{
    public function execute(Model $user): void
    {
        $user->delete();
    }
}
