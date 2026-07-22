<?php

declare(strict_types=1);

namespace CorePanel\Domain\OAuthClient\Actions;

use CorePanel\Models\OAuthClient;

final readonly class UpdateOAuthClientAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(OAuthClient $client, array $attributes): OAuthClient
    {
        $client->forceFill([
            'name' => (string) ($attributes['name'] ?? $client->getAttribute('name')),
            'provider' => $attributes['provider'] ?? $client->getAttribute('provider'),
            'redirect' => (string) ($attributes['redirect'] ?? $client->getAttribute('redirect')),
            'scopes_json' => array_values((array) ($attributes['scopes'] ?? ($client->getAttribute('scopes_json') ?? []))),
        ]);
        $client->save();

        return $client;
    }
}
