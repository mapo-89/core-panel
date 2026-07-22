<?php

declare(strict_types=1);

namespace CorePanel\Domain\OAuthClient\DTOs;

use CorePanel\Models\OAuthClient;

final readonly class OAuthClientData
{
    /**
     * @param  list<string>  $scopes
     */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $provider,
        public string $redirect,
        public ?string $secret,
        public bool $confidential,
        public bool $personalAccessClient,
        public bool $passwordClient,
        public bool $revoked,
        public array $scopes,
    ) {}

    public static function fromModel(OAuthClient $client, ?string $plainTextSecret = null): self
    {
        return new self(
            id: (string) $client->getKey(),
            name: (string) $client->getAttribute('name'),
            provider: is_string($client->getAttribute('provider')) ? $client->getAttribute('provider') : null,
            redirect: (string) $client->getAttribute('redirect'),
            secret: $plainTextSecret,
            confidential: filled($client->getAttribute('secret')),
            personalAccessClient: (bool) $client->getAttribute('personal_access_client'),
            passwordClient: (bool) $client->getAttribute('password_client'),
            revoked: (bool) $client->getAttribute('revoked'),
            scopes: array_values((array) ($client->getAttribute('scopes_json') ?? [])),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'confidential' => $this->confidential,
            'id' => $this->id,
            'name' => $this->name,
            'passwordClient' => $this->passwordClient,
            'personalAccessClient' => $this->personalAccessClient,
            'provider' => $this->provider,
            'redirect' => $this->redirect,
            'revoked' => $this->revoked,
            'scopes' => $this->scopes,
            'secret' => $this->secret,
        ];
    }
}
