<?php

declare(strict_types=1);

namespace CorePanel\Support\Publishing;

use CorePanel\Support\Install\BackupManager;
use Illuminate\Filesystem\Filesystem;

final readonly class VendorFirstAssetMigrator
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
    public function migrate(array $tags, bool $force = false, bool $dryRun = false, ?string $basePath = null): array
    {
        $root = $basePath ?? base_path();
        $manifest = $this->manifest->read($root);
        $updatedManifest = $manifest;
        $changes = [];
        $backupsCreated = false;
        $themeMigrationHint = false;

        foreach ($manifest['files'] as $destination => $entry) {
            if (! in_array($entry['tag'], $tags, true)) {
                continue;
            }

            $tag = $entry['tag'];
            $source = $entry['source'];

            if (! $this->files->exists($destination)) {
                unset($updatedManifest['files'][$destination]);

                $changes[] = $this->change(
                    $tag,
                    'pruned',
                    $source,
                    $destination,
                    'published override already absent; vendor asset will be used',
                );

                if ($tag === 'core-panel-theme') {
                    $themeMigrationHint = true;
                }

                continue;
            }

            $destinationHash = $this->hash($destination);
            $managedHash = $entry['destination_hash'];
            $hasLocalChanges = $managedHash !== $destinationHash;

            if ($hasLocalChanges && ! $force) {
                $changes[] = $this->change(
                    $tag,
                    'conflict',
                    $source,
                    $destination,
                    'local changes detected; keeping published override',
                );

                continue;
            }

            $status = $hasLocalChanges ? 'remove' : 'delete';
            $reason = $hasLocalChanges
                ? 'local override removed after backup; vendor asset will be used'
                : 'published override removed; vendor asset will be used';

            if ($dryRun) {
                $changes[] = $this->change($tag, $status, $source, $destination, $reason);

                if ($tag === 'core-panel-theme') {
                    $themeMigrationHint = true;
                }

                continue;
            }

            if ($hasLocalChanges) {
                $this->backups->backupPaths([$source => $destination], $root);
                $backupsCreated = true;
            }

            $this->deletePath($destination);
            unset($updatedManifest['files'][$destination]);
            $changes[] = $this->change($tag, $status, $source, $destination, $reason);

            if ($tag === 'core-panel-theme') {
                $themeMigrationHint = true;
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

    private function deletePath(string $path): void
    {
        if ($this->files->isDirectory($path)) {
            $this->files->deleteDirectory($path);

            return;
        }

        $this->files->delete($path);
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

        return md5((string) $this->files->get($path));
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
