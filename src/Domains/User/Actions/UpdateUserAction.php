<?php

declare(strict_types=1);

namespace CorePanel\Domains\User\Actions;

use CorePanel\Support\Media\MediaService;
use CorePanel\Support\Users\UserModelManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final readonly class UpdateUserAction
{
    public function __construct(
        private UserModelManager $users,
        private AssignUserRolesAction $assignRoles,
        private AssignUserGroupsAction $assignUserGroups,
        private MediaService $media,
    ) {}

    /**
     * @param  array{
     *     first_name:string,
     *     last_name:string,
     *     email:string,
     *     password?:?string,
     *     avatar?:?UploadedFile,
     *     locale?:?string,
     *     remove_avatar?:bool,
     *     role_names?:list<string>,
     *     status?:string,
     *     user_group_ids?:list<int|string>
     * }  $attributes
     */
    public function execute(Model $user, array $attributes): Model
    {
        return DB::transaction(function () use ($user, $attributes): Model {
            $payload = [
                'first_name' => $attributes['first_name'],
                'last_name' => $attributes['last_name'],
                'email' => $attributes['email'],
            ];

            $user->forceFill($payload);

            if (($attributes['password'] ?? null) !== null && $attributes['password'] !== '') {
                $user->setAttribute('password', Hash::make($attributes['password']));
            }

            if ($this->users->supportsLocale() && array_key_exists('locale', $attributes)) {
                $user->setAttribute('locale', $attributes['locale']);
            }

            if ($this->users->supportsStatus() && array_key_exists('status', $attributes)) {
                $user->setAttribute('status', $attributes['status']);
            }

            $user->save();

            if (
                $this->users->supportsMedia() &&
                method_exists($user, 'clearMediaCollection') &&
                ($attributes['remove_avatar'] ?? false) === true
            ) {
                $user->clearMediaCollection('avatars');
            }

            if ($this->users->supportsMedia() && ($attributes['avatar'] ?? null) instanceof UploadedFile) {
                if (method_exists($user, 'clearMediaCollection')) {
                    $user->clearMediaCollection('avatars');
                }

                $this->media->upload($user, $attributes['avatar'], 'avatars');
            }

            $this->assignRoles->execute(
                $user,
                $attributes['role_names'] ?? [],
            );

            $this->assignUserGroups->execute(
                $user,
                $attributes['user_group_ids'] ?? [],
            );

            return $user->refresh()->load($this->users->relations());
        });
    }
}
