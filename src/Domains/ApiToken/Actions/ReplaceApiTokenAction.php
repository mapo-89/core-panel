<?php

declare(strict_types=1);

namespace CorePanel\Domains\ApiToken\Actions;

use CorePanel\Models\ApiToken;
use Illuminate\Contracts\Auth\Authenticatable;
use RuntimeException;

final readonly class ReplaceApiTokenAction
{
    public function __construct(
        private CreateApiTokenAction $createApiToken,
        private DeleteApiTokenAction $deleteApiToken,
    ) {}

    /**
     * @return array{plainTextToken:string,token:ApiToken}
     */
    public function execute(Authenticatable $user, string $tokenId): array
    {
        $token = $this->deleteApiToken->findOwnedToken($user, $tokenId);

        if ($token === null) {
            throw new RuntimeException('API token not found.');
        }

        /** @var list<string> $abilities */
        $abilities = array_values(array_map(
            static fn (mixed $value): string => (string) $value,
            (array) ($token->getAttribute('scopes') ?? [])
        ));

        $name = (string) $token->getAttribute('name');

        $this->deleteApiToken->execute($user, $tokenId);

        return $this->createApiToken->execute($user, $name, $abilities);
    }
}
