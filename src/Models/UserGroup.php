<?php

declare(strict_types=1);

namespace CorePanel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class UserGroup extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'color',
    ];

    public function users(): BelongsToMany
    {
        /** @var class-string<Model> $userModel */
        $userModel = (string) config('core-panel.user_model');

        return $this->belongsToMany($userModel, 'user_group_user')
            ->withTimestamps()
            ->orderBy('last_name')
            ->orderBy('first_name');
    }
}
