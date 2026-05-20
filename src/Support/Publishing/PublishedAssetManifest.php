<?php

declare(strict_types=1);

namespace CorePanel\Support\Publishing;

use Illuminate\Filesystem\Filesystem;

final readonly class PublishedAssetManifest
{
    public function __construct(private Filesystem $files) {}

    /**
     * @return array{
     *     files: array<string, array{
     *         tag:string,
     *         source:string,
     *         source_hash:string,
     *         destination_hash:string,
     *         published_at:string
     *     }>
     * }
     */
    public function read(string $basePath): array
    {
        $path = $this->path($basePath);

        if (! $this->files->exists($path)) {
            return ['files' => []];
        }

        /** @var array{files?:array<string, array{tag:string,source:string,source_hash:string,destination_hash:string,published_at:string}>} $decoded */
        $decoded = json_decode((string) $this->files->get($path), true, flags: JSON_THROW_ON_ERROR);

        return [
            'files' => $decoded['files'] ?? [],
        ];
    }

    /**
     * @param  array{
     *     files: array<string, array{
     *         tag:string,
     *         source:string,
     *         source_hash:string,
     *         destination_hash:string,
     *         published_at:string
     *     }>
     * }  $manifest
     */
    public function write(string $basePath, array $manifest): void
    {
        $path = $this->path($basePath);

        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n");
    }

    public function path(string $basePath): string
    {
        return rtrim($basePath, '/').'/storage/app/core-panel/published.json';
    }
}
