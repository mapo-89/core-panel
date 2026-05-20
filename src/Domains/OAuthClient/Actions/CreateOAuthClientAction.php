<?php

declare(strict_types=1);

namespace CorePanel\Domains\OAuthClient\Actions;

use CorePanel\Domains\OAuthClient\DTOs\OAuthClientData;
use CorePanel\Models\OAuthClient;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final readonly class CreateOAuthClientAction
{
    public function __construct(
        private OAuthClient $clients,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(array $attributes, ?Authenticatable $actor = null): array
    {
        $plainTextSecret = empty($attributes['confidential']) ? null : Str::random(40);
        $client = $this->clients->newInstance();
        $client->forceFill([
            'name' => (string) $attributes['name'],
            'provider' => $attributes['provider'] ?? null,
            'redirect' => (string) ($attributes['redirect'] ?? ''),
            'secret' => $plainTextSecret !== null ? Hash::make($plainTextSecret) : null,
            'scopes_json' => array_values((array) ($attributes['scopes'] ?? [])),
            'personal_access_client' => (bool) ($attributes['personal_access_client'] ?? false),
            'password_client' => false,
            'revoked' => false,
            'user_id' => $actor?->getAuthIdentifier(),
        ]);
        $client->save();

        return OAuthClientData::fromModel($client, $plainTextSecret)->toArray();
    }
}
