<?php

declare(strict_types=1);

namespace CorePanel\Domain\User\Actions;

use Illuminate\Database\Eloquent\Model;

final class AssignUserGroupsAction
{
    /**
     * @param  list<string|int>  $userGroupIds
     */
    public function execute(Model $user, array $userGroupIds): void
    {
        if (! method_exists($user, 'userGroups')) {
            return;
        }

        $relation = $user->userGroups();

        if (! method_exists($relation, 'sync')) {
            return;
        }

        $relation->sync($userGroupIds);
    }
}
