<?php

declare(strict_types=1);

namespace CorePanel\Support\Config;

final readonly class SecurityConfig
{
    public function __construct(
        public bool $headersEnabled,
        public string $contentSecurityPolicy,
        public bool $cspReportOnly,
        public string $strictTransportSecurity,
        public string $referrerPolicy,
        public string $permissionsPolicy,
    ) {}
}
