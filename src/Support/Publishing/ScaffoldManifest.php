<?php

declare(strict_types=1);

namespace CorePanel\Support\Publishing;

use Illuminate\Filesystem\Filesystem;

final readonly class ScaffoldManifest
{
    public function __construct(private Filesystem $files) {}

    /**
     * @return array{
     *     package_version:?string,
     *     files: array<string, array{
     *         destination_hash:string,
     *         package_version:string,
     *         snapshot:string,
     *         source_hash:string
     *     }>
     * }
     */
    public function read(string $basePath): array
    {
        $path = $this->path($basePath);

        if (! $this->files->exists($path)) {
            return [
                'package_version' => null,
                'files' => [],
            ];
        }

        /** @var array{
         *     _meta?: array{package_version?: string|null},
         *     files?: array<string, array{
         *         destination_hash:string,
         *         package_version:string,
         *         snapshot:string,
         *         source_hash:string
         *     }>
         * } $decoded
         */
        $decoded = json_decode((string) $this->files->get($path), true, flags: JSON_THROW_ON_ERROR);

        return [
            'package_version' => $decoded['_meta']['package_version'] ?? null,
            'files' => $decoded['files'] ?? [],
        ];
    }

    /**
     * @param  array{
     *     package_version:?string,
     *     files: array<string, array{
     *         destination_hash:string,
     *         package_version:string,
     *         snapshot:string,
     *         source_hash:string
     *     }>
     * }  $manifest
     */
    public function write(string $basePath, array $manifest): void
    {
        $path = $this->path($basePath);

        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, json_encode([
            '_meta' => [
                'package_version' => $manifest['package_version'],
            ],
            'files' => $manifest['files'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
    }

    public function path(string $basePath): string
    {
        return rtrim($basePath, '/').'/storage/app/core-panel/scaffolds.json';
    }
}
