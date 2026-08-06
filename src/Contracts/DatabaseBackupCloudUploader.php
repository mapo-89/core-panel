<?php

declare(strict_types=1);

namespace CorePanel\Contracts;

interface DatabaseBackupCloudUploader
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
    public function status(): array;

    /**
     * @param  resource  $stream
     */
    public function upload($stream, string $name): bool;
}
