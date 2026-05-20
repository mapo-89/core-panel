<?php

declare(strict_types=1);

namespace CorePanel\Models;

use Laravel\Passport\Client as PassportClient;

class OAuthClient extends PassportClient
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'provider',
        'redirect',
        'secret',
        'scopes_json',
        'personal_access_client',
        'password_client',
        'revoked',
        'user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password_client' => 'bool',
            'personal_access_client' => 'bool',
            'revoked' => 'bool',
            'scopes_json' => 'array',
        ];
    }
}
