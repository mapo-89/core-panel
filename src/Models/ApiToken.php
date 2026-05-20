<?php

declare(strict_types=1);

namespace CorePanel\Models;

use Illuminate\Support\Carbon;
use Laravel\Passport\Token as PassportToken;

/**
 * @property Carbon|null $created_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $last_used_at
 * @property array<int, string>|null $scopes
 */
class ApiToken extends PassportToken
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked' => 'bool',
            'scopes' => 'array',
        ];
    }
}
