<?php

declare(strict_types=1);

namespace CorePanel\Domain\OAuthClient\Actions;

use CorePanel\Models\OAuthClient;

final readonly class DeleteOAuthClientAction
{
    public function execute(OAuthClient $client): OAuthClient
    {
        $client->forceFill([
            'revoked' => true,
        ]);
        $client->save();

        return $client;
    }
}
