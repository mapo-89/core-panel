<?php

declare(strict_types=1);

namespace CorePanel\Models\Concerns;

use CorePanel\Models\UserGroup;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait HasCorePanelGroups
{
    /** @return BelongsToMany<UserGroup, $this> */
    public function userGroups(): BelongsToMany
    {
        /** @var class-string<UserGroup> $model */
        $model = (string) config('core-panel.user_group_model', UserGroup::class);

        return $this->belongsToMany($model, 'user_group_user')->withTimestamps()->orderBy('name');
    }
}
