<?php

declare(strict_types=1);

namespace CorePanel\Domains\ApiToken\Actions;

use CorePanel\Domains\ApiToken\DTOs\ApiTokenData;
use CorePanel\Models\ApiToken;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

final class ListApiTokensAction
{
    /**
     * @return list<array{
     *     id:string,
     *     name:string,
     *     abilities:list<string>,
     *     lastUsedAt:?string,
     *     createdAt:?string
     * }>
     */
    public function execute(Authenticatable $user): array
    {
        if (! method_exists($user, 'tokens')) {
            return [];
        }

        /** @var Collection<int, ApiToken> $tokens */
        $tokens = $user->tokens()
            ->orderByDesc('created_at')
            ->where('revoked', false)
            ->get()
            ->map(static fn (mixed $token): ApiToken => $token);

        return $tokens
            ->map(static fn (ApiToken $token): array => ApiTokenData::fromModel($token)->toArray())
            ->values()
            ->all();
    }
}
