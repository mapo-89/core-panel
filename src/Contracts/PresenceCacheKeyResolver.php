<?php

declare(strict_types=1);

namespace CorePanel\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

interface PresenceCacheKeyResolver
{
    public function resolve(Model|Authenticatable|string|int $user): string;

    public function scope(string $key): string;
}
