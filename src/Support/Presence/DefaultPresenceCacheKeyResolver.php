<?php

declare(strict_types=1);

namespace CorePanel\Support\Presence;

use CorePanel\Contracts\PresenceCacheKeyResolver;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

final class DefaultPresenceCacheKeyResolver implements PresenceCacheKeyResolver
{
    public function resolve(Model|Authenticatable|string|int $user): string
    {
        if (($user instanceof Model || $user instanceof Authenticatable) && method_exists($user, 'presenceCacheKey')) {
            return $user->presenceCacheKey();
        }

        return 'user-presence:'.$this->userId($user);
    }

    public function scope(string $key): string
    {
        return $key;
    }

    private function userId(Model|Authenticatable|string|int $user): string
    {
        if ($user instanceof Model) {
            return (string) $user->getKey();
        }

        if ($user instanceof Authenticatable) {
            return (string) $user->getAuthIdentifier();
        }

        return (string) $user;
    }
}
