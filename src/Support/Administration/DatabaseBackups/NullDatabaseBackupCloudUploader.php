<?php

declare(strict_types=1);

namespace CorePanel\Support\Administration\DatabaseBackups;

use CorePanel\Contracts\DatabaseBackupCloudUploader;

final class NullDatabaseBackupCloudUploader implements DatabaseBackupCloudUploader
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

    /**
     * @param  resource  $stream
     */
    public function upload($stream, string $name): bool
    {
        return false;
    }
}
