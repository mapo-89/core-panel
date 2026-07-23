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
        $configuredModel = config('core-panel.user_group_model', UserGroup::class);

        if (! is_string($configuredModel) || ! is_a($configuredModel, Model::class, true)) {
            return UserGroup::class;
        }

        return $configuredModel;
    }

    public function newModel(): Model
    {
        $modelClass = $this->modelClass();

        return new $modelClass;
    }
}
