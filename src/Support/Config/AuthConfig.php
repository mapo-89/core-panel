<?php

declare(strict_types=1);

namespace CorePanel\Support\Config;

final readonly class AuthConfig
{
    public function __construct(
        public bool $registrationEnabled,
    ) {}

    public function usesPassport(): bool
    {
        return true;
    }
}
