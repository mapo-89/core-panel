<?php

declare(strict_types=1);

namespace CorePanel\Domains\ApiToken\DTOs;

use CorePanel\Models\ApiToken;

final readonly class ApiTokenData
{
    /**
     * @param  list<string>  $abilities
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $abilities,
        public ?string $lastUsedAt,
        public ?string $createdAt,
    ) {}

    public static function fromModel(ApiToken $token): self
    {
        /** @var list<string> $abilities */
        $abilities = array_values(array_map(
            static fn (mixed $value): string => (string) $value,
            (array) ($token->getAttribute('scopes') ?? [])
        ));

        return new self(
            id: (string) $token->getKey(),
            name: (string) $token->getAttribute('name'),
            abilities: $abilities,
            lastUsedAt: $token->getAttribute('last_used_at')?->toIso8601String(),
            createdAt: $token->getAttribute('created_at')?->toIso8601String(),
        );
    }

    /**
     * @return array{
     *     id:string,
     *     name:string,
     *     abilities:list<string>,
     *     lastUsedAt:?string,
     *     createdAt:?string
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'abilities' => $this->abilities,
            'lastUsedAt' => $this->lastUsedAt,
            'createdAt' => $this->createdAt,
        ];
    }
}
