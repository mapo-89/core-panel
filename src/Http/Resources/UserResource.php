<?php

declare(strict_types=1);

namespace CorePanel\Http\Resources;

use CorePanel\Domains\User\DTOs\UserData;
use CorePanel\Support\Permissions\PermissionService;
use CorePanel\Support\Users\UserModelManager;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

final class UserResource extends JsonResource
{
    /**
     * @return array{
     *     id:string,
     *     firstName:string,
     *     lastName:string,
     *     name:string,
     *     email:string,
     *     locale:?string,
     *     avatarUrl:?string,
     *     presenceLastSeenAt:?int,
     *     presenceStatus:string,
     *     roles:list<string>,
     *     userGroups:list<array{id:string,color:string,name:string}>,
     *     twoFactorEnabled:bool,
     *     canDelete:bool,
     *     canForceDelete:bool,
     *     emailVerifiedAt:?string,
     *     deletedAt:?string
     * }
     */
    public function toArray(Request $request): array
    {
        $data = UserData::fromModel($this->resource, app(UserModelManager::class))->toArray();
        $actor = $request->user();
        $visibleRoleNames = array_flip(app(PermissionService::class)->visibleRoleNamesFor($actor));

        return [
            ...$data,
            'roles' => array_values(array_filter(
                $data['roles'],
                static fn (string $roleName): bool => isset($visibleRoleNames[$roleName])
            )),
            'canDelete' => $actor !== null
                && Gate::forUser($actor)->allows('delete', $this->resource),
            'canForceDelete' => $actor !== null
                && Gate::forUser($actor)->allows('forceDelete', $this->resource),
        ];
    }
}
