<?php

declare(strict_types=1);

namespace CorePanel\Support\Publishing;

use CorePanel\Support\Install\BackupManager;
use CorePanel\Support\PublishTag;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;

final readonly class CorePanelPublisher
{
    public function __construct(
        private Filesystem $files,
        private BackupManager $backups,
        private PublishedAssetManifest $manifest,
    ) {}

    /**
     * @param  list<string>  $tags
     * @return array{
     *     changes:list<array{tag:string,status:string,source:string,destination:string,reason:string}>,
     *     manifestPath:string,
     *     backupsCreated:bool,
     *     themeMigrationHint:bool
     * }
     */
    public function publish(array $tags, bool $force = false, bool $dryRun = false, ?string $basePath = null): array
    {
        return $this->apply($tags, $force, $dryRun, false, $basePath);
    }

    /**
     * @param  list<string>  $tags
     * @return array{
     *     changes:list<array{tag:string,status:string,source:string,destination:string,reason:string}>,
     *     manifestPath:string,
     *     backupsCreated:bool,
     *     themeMigrationHint:bool
     * }
     */
    public function publishForProvider(
        string $provider,
        array $tags,
        bool $force = false,
        bool $dryRun = false,
        ?string $basePath = null,
    ): array {
        return $this->apply($tags, $force, $dryRun, false, $basePath, $provider);
    }

    /**
     * @param  list<string>  $tags
     * @return array{
     *     changes:list<array{tag:string,status:string,source:string,destination:string,reason:string}>,
     *     manifestPath:string,
     *     backupsCreated:bool,
     *     themeMigrationHint:bool
     * }
     */
    public function update(array $tags, bool $force = false, bool $dryRun = false, ?string $basePath = null): array
    {
        return $this->apply($tags, $force, $dryRun, true, $basePath);
    }

    /**
     * @param  list<string>  $tags
     * @return array{
     *     changes:list<array{tag:string,status:string,source:string,destination:string,reason:string}>,
     *     manifestPath:string,
     *     backupsCreated:bool,
     *     themeMigrationHint:bool
     * }
     */
    public function updateForProvider(
        string $provider,
        array $tags,
        bool $force = false,
        bool $dryRun = false,
        ?string $basePath = null,
        bool $adoptUnmanagedExisting = false,
    ): array {
        return $this->apply($tags, $force, $dryRun, true, $basePath, $provider, $adoptUnmanagedExisting);
    }

    /**
     * @param  list<string>  $tags
     * @return array{
     *     changes:list<array{tag:string,status:string,source:string,destination:string,reason:string}>,
     *     manifestPath:string,
     *     backupsCreated:bool,
     *     themeMigrationHint:bool
     * }
     */
    private function apply(
        array $tags,
        bool $force,
        bool $dryRun,
        bool $manifestAware,
        ?string $basePath,
        ?string $provider = null,
        bool $adoptUnmanagedExisting = false,
    ): array {
        $root = $basePath ?? base_path();
        $manifest = $this->manifest->read($root);
        $changes = [];
        $backupsCreated = false;
        $updatedManifest = $manifest;
        $themeMigrationHint = false;

        foreach ($tags as $tag) {
            foreach ($this->publishablePathsFor($tag, $root, $provider) as $source => $destination) {
                $sourceHash = $this->hash($source);
                $destinationExists = $this->files->exists($destination);
                $destinationHash = $destinationExists ? $this->hash($destination) : null;
                $manifestEntry = $updatedManifest['files'][$destination] ?? null;

                if ($manifestAware && ! is_array($manifestEntry)) {
                    if ($destinationExists) {
                        if (! $adoptUnmanagedExisting) {
                            $changes[] = $this->change($tag, 'skipped', $source, $destination, 'destination is not managed by the publish manifest');

                            continue;
                        }

                        if ($destinationHash === $sourceHash) {
                            $changes[] = $this->change($tag, 'unchanged', $source, $destination, 'legacy published file adopted into the publish manifest');
                            $this->storeManifestEntry($updatedManifest, $tag, $source, $destination, $sourceHash, $destinationHash);

                            continue;
                        }

                        if (! $force) {
                            $changes[] = $this->change($tag, 'conflict', $source, $destination, 'legacy published file is not managed by the publish manifest');

                            continue;
                        }

                        $status = 'overwrite';
                        $reason = 'legacy published file adopted into the publish manifest';

                        if ($dryRun) {
                            $changes[] = $this->change($tag, $status, $source, $destination, $reason);

                            continue;
                        }

                        $this->backups->backupPaths([$source => $destination], $root);
                        $backupsCreated = true;
                        $this->copyPublishable($source, $destination);
                        $destinationHash = $this->hash($destination);
                        $this->storeManifestEntry($updatedManifest, $tag, $source, $destination, $sourceHash, $destinationHash);
                        $changes[] = $this->change($tag, $status, $source, $destination, $reason);

                        if ($tag === PublishTag::Theme->value) {
                            $themeMigrationHint = true;
                        }

                        continue;
                    }

                    $changes[] = $this->change($tag, 'skipped', $source, $destination, 'tag was not previously published');

                    continue;
                }

                $status = 'create';
                $reason = 'new file';

                if ($destinationExists) {
                    if ($destinationHash === $sourceHash) {
                        $changes[] = $this->change($tag, 'unchanged', $source, $destination, 'already up to date');
                        $this->storeManifestEntry($updatedManifest, $tag, $source, $destination, $sourceHash, $destinationHash);

                        continue;
                    }

                    if ($manifestAware && is_array($manifestEntry)) {
                        if (($manifestEntry['destination_hash'] ?? null) === $destinationHash) {
                            $status = 'update';
                            $reason = 'published file changed upstream';
                        } else {
                            $status = $force ? 'overwrite' : 'conflict';
                            $reason = 'local changes detected';
                        }
                    } else {
                        $status = $force ? 'overwrite' : 'conflict';
                        $reason = 'destination exists without manifest tracking';
                    }
                }

                if ($status === 'conflict') {
                    $changes[] = $this->change($tag, $status, $source, $destination, $reason);

                    continue;
                }

                if ($dryRun) {
                    $changes[] = $this->change($tag, $status, $source, $destination, $reason);

                    continue;
                }

                if (in_array($status, ['overwrite', 'update'], true) && $destinationExists) {
                    $this->backups->backupPaths([$source => $destination], $root);
                    $backupsCreated = true;
                }

                $this->copyPublishable($source, $destination);
                $destinationHash = $this->hash($destination);
                $this->storeManifestEntry($updatedManifest, $tag, $source, $destination, $sourceHash, $destinationHash);
                $changes[] = $this->change($tag, $status, $source, $destination, $reason);

                if ($tag === PublishTag::Theme->value) {
                    $themeMigrationHint = true;
                }
            }
        }

        if (! $dryRun) {
            $this->manifest->write($root, $updatedManifest);
        }

        return [
            'changes' => $changes,
            'manifestPath' => $this->manifest->path($root),
            'backupsCreated' => $backupsCreated && ! $dryRun,
            'themeMigrationHint' => $themeMigrationHint,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function publishablePathsFor(string $tag, string $basePath, ?string $provider = null): array
    {
        /** @var array<string, string> $paths */
        $paths = ServiceProvider::pathsToPublish($provider, $tag);

        return collect($paths)
            ->mapWithKeys(static fn (string $destination, string $source): array => [
                $source => rtrim($basePath, '/').'/'.ltrim((string) str($destination)->after(base_path()), '/'),
            ])
            ->all();
    }

    private function copyPublishable(string $source, string $destination): void
    {
        if ($this->files->isDirectory($source)) {
            if ($this->files->exists($destination)) {
                $this->files->deleteDirectory($destination);
            }

            $this->files->ensureDirectoryExists(dirname($destination));
            $this->files->copyDirectory($source, $destination);

            return;
        }

        $this->files->ensureDirectoryExists(dirname($destination));
        $this->files->copy($source, $destination);
    }

    private function hash(string $path): string
    {
        if ($this->files->isDirectory($path)) {
            $entries = collect($this->files->allFiles($path))
                ->sortBy(static fn (\SplFileInfo $file): string => $file->getRealPath() ?: $file->getPathname())
                ->map(static fn (\SplFileInfo $file): string => ($file->getRealPath() ?: $file->getPathname()).':'.md5_file($file->getPathname()))
                ->implode('|');

            return md5($entries);
        }

        $contents = $this->files->get($path);

        return md5((string) $contents);
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
    private function storeManifestEntry(
        array &$manifest,
        string $tag,
        string $source,
        string $destination,
        string $sourceHash,
        string $destinationHash,
    ): void {
        $manifest['files'][$destination] = [
            'tag' => $tag,
            'source' => $source,
            'source_hash' => $sourceHash,
            'destination_hash' => $destinationHash,
            'published_at' => now()->toAtomString(),
        ];
    }

    /**
     * @return array{tag:string,status:string,source:string,destination:string,reason:string}
     */
    private function change(string $tag, string $status, string $source, string $destination, string $reason): array
    {
        return [
            'tag' => $tag,
            'status' => $status,
            'source' => $source,
            'destination' => $destination,
            'reason' => $reason,
        ];
    }
}
