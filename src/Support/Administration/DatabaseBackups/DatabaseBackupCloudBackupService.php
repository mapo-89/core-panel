<?php

declare(strict_types=1);

namespace CorePanel\Support\Administration\DatabaseBackups;

final class DatabaseBackupCloudBackupService
{
    /**
     * @return array{
     *     available: bool,
     *     connected: bool,
     *     enabled: bool,
     *     missing_scopes: bool,
     *     path: string,
     *     provider_email: string|null
     * }
     */
    public function status(): array
    {
        return [
            'available' => false,
            'connected' => false,
            'enabled' => false,
            'missing_scopes' => false,
            'path' => '',
            'provider_email' => null,
        ];
    }

    public function uploadIfEnabled(string $path, string $name): bool
    {
        return false;
    }
}
