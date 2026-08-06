<?php

declare(strict_types=1);

namespace CorePanel\Support\Administration\DatabaseBackups;

use CorePanel\Contracts\DatabaseBackupCloudUploader;

final class DatabaseBackupCloudBackupService
{
    public function __construct(private readonly DatabaseBackupCloudUploader $uploader) {}

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
        return $this->uploader->status();
    }

    public function uploadIfEnabled(string $path, string $name): bool
    {
        $status = $this->status();

        if (! $status['enabled'] || ! $status['available']) {
            return false;
        }

        $stream = fopen($path, 'rb');

        if ($stream === false) {
            throw new \RuntimeException('Database backup file could not be opened for cloud upload.');
        }

        try {
            return $this->uploader->upload($stream, $name);
        } finally {
            fclose($stream);
        }
    }
}
