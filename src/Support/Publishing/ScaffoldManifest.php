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

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) $this->files->get($path), true, flags: JSON_THROW_ON_ERROR);

        $meta = $decoded['_meta'] ?? null;
        $packageVersion = is_array($meta) && is_string($meta['package_version'] ?? null)
            ? $meta['package_version']
            : null;
        $nestedFiles = $decoded['files'] ?? null;

        if (is_array($nestedFiles)) {
            return [
                'package_version' => $packageVersion,
                'files' => $nestedFiles,
            ];
        }

        $files = [];

        foreach ($decoded as $relativePath => $entry) {
            if ($relativePath === '_meta' || ! is_array($entry)) {
                continue;
            }

            $normalizedEntry = array_filter(
                $entry,
                static fn (mixed $value): bool => is_string($value),
            );

            if (
                ! isset(
                    $normalizedEntry['destination_hash'],
                    $normalizedEntry['package_version'],
                    $normalizedEntry['snapshot'],
                    $normalizedEntry['source_hash'],
                )
            ) {
                continue;
            }

            $files[$relativePath] = [
                'destination_hash' => $normalizedEntry['destination_hash'],
                'package_version' => $normalizedEntry['package_version'],
                'snapshot' => $normalizedEntry['snapshot'],
                'source_hash' => $normalizedEntry['source_hash'],
            ];
        }

        return [
            'package_version' => $packageVersion,
            'files' => $files,
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
