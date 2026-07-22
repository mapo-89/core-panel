<?php

declare(strict_types=1);

namespace CorePanel\Domain\ApiToken\Actions;

use CorePanel\Models\ApiToken;
use Illuminate\Contracts\Auth\Authenticatable;

final class DeleteApiTokenAction
{
    public function execute(Authenticatable $user, string $tokenId): void
    {
        $token = $this->findOwnedToken($user, $tokenId);

        if (! $token instanceof ApiToken) {
            abort(404);
        }

        $token->revoke();
    }

    public function findOwnedToken(Authenticatable $user, string $tokenId): ?ApiToken
    {
        if (! method_exists($user, 'tokens')) {
            return null;
        }

        $token = $user->tokens()
            ->whereKey($tokenId)
            ->where('revoked', false)
            ->first();

        return $token instanceof ApiToken ? $token : null;
    }
}
