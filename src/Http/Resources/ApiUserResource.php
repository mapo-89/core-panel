<?php

declare(strict_types=1);

namespace CorePanel\Http\Resources;

use CorePanel\Support\Users\UserModelManager;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ApiUserResource extends JsonResource
{
    /**
     * @return array{
     *     id:string,
     *     name:?string,
     *     email:?string,
     *     locale:?string,
     *     presenceLastSeenAt:?int,
     *     presenceStatus:string,
     *     tokenAbilities:list<string>
     * }
     */
    public function toArray(Request $request): array
    {
        $token = $request->user()?->currentAccessToken();
        $abilities = is_object($token) && property_exists($token, 'abilities')
            ? (array) $token->abilities
            : ['*'];
        $users = app(UserModelManager::class);

        return [
            'id' => (string) $this->resource->getAuthIdentifier(),
            'name' => $users->composeDisplayName(
                is_string($this->resource->first_name ?? null) ? $this->resource->first_name : null,
                is_string($this->resource->last_name ?? null) ? $this->resource->last_name : null,
            ),
            'email' => $this->resource->email,
            'locale' => $this->resource->locale ?? null,
            'presenceLastSeenAt' => $users->presenceLastSeenAt($this->resource),
            'presenceStatus' => $users->presenceStatus($this->resource),
            'tokenAbilities' => array_values(array_map(static fn (mixed $value): string => (string) $value, (array) $abilities)),
        ];
    }
}
