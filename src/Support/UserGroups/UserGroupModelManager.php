<?php

declare(strict_types=1);

namespace CorePanel\Support\UserGroups;

use CorePanel\Models\UserGroup;
use Illuminate\Database\Eloquent\Model;

final class UserGroupModelManager
{
    /**
     * @return class-string<Model>
     */
    public function modelClass(): string
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = (string) config('core-panel.user_group_model', UserGroup::class);

        return $modelClass;
    }

    public function newModel(): Model
    {
        $modelClass = $this->modelClass();

        return new $modelClass;
    }
}
