<?php

declare(strict_types=1);

namespace CorePanel\Domain\User\Actions;

use CorePanel\Support\Media\MediaService;
use CorePanel\Support\Users\UserModelManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final readonly class CreateUserAction
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
     *     avatar?:?UploadedFile,
     *     locale?:?string,
     *     remove_avatar?:bool,
     *     role_names?:list<string>,
     *     status?:string,
     *     user_group_ids?:list<int|string>
     * }  $attributes
     */
    public function execute(array $attributes): Model
    {
        return DB::transaction(function () use ($attributes): Model {
            $user = $this->users->newModel();

            $payload = [
                'first_name' => $attributes['first_name'],
                'last_name' => $attributes['last_name'],
                'email' => $attributes['email'],
                'password' => Hash::make(Str::password(32)),
            ];

            $user->forceFill($payload);

            if ($this->users->hasColumn('requires_password_setup')) {
                $user->setAttribute('requires_password_setup', true);
            }

            if ($this->users->hasColumn('invited_at')) {
                $user->setAttribute('invited_at', now());
            }

            if ($this->users->hasColumn('invitation_accepted_at')) {
                $user->setAttribute('invitation_accepted_at', null);
            }

            if ($this->users->supportsLocale() && array_key_exists('locale', $attributes)) {
                $user->setAttribute('locale', $attributes['locale']);
            }

            if ($this->users->supportsStatus() && array_key_exists('status', $attributes)) {
                $user->setAttribute('status', $attributes['status']);
            }

            $user->save();

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
