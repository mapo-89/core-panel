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
     *         published_at:string,
     *         snapshot?:string
     *     }>
     * }
     */
    public function read(string $basePath): array
    {
        $path = $this->path($basePath);

        if (! $this->files->exists($path)) {
            return ['files' => []];
        }

        /** @var array{files?:array<string, mixed>} $decoded */
        $decoded = json_decode((string) $this->files->get($path), true, flags: JSON_THROW_ON_ERROR);

        /** @var array<string, array{tag:string,source:string,source_hash:string,destination_hash:string,published_at:string,snapshot?:string}> $files */
        $files = [];

        foreach ($decoded['files'] ?? [] as $destination => $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $normalizedEntry = [
                'tag' => (string) ($entry['tag'] ?? ''),
                'source' => (string) ($entry['source'] ?? ''),
                'source_hash' => (string) ($entry['source_hash'] ?? ''),
                'destination_hash' => (string) ($entry['destination_hash'] ?? ''),
                'published_at' => (string) ($entry['published_at'] ?? ''),
            ];

            if (is_string($entry['snapshot'] ?? null) && $entry['snapshot'] !== '') {
                $normalizedEntry['snapshot'] = $entry['snapshot'];
            }

            $files[$destination] = $normalizedEntry;
        }

        return [
            'files' => $files,
        ];
    }

    /**
     * @param  array{
     *     files: array<string, array{
     *         tag:string,
     *         source:string,
     *         source_hash:string,
     *         destination_hash:string,
     *         published_at:string,
     *         snapshot?:string
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
