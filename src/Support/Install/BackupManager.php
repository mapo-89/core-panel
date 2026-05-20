<?php

declare(strict_types=1);

namespace CorePanel\Support\Install;

use Illuminate\Filesystem\Filesystem;

final readonly class BackupManager
{
    public function __construct(private Filesystem $files) {}

    /**
     * @param  array<string, string>  $paths
     */
    public function backupPaths(array $paths, string $root): void
    {
        if ($paths === []) {
            return;
        }

        $timestamp = now()->format('YmdHis');

        foreach ($paths as $sourcePath => $destinationPath) {
            if (! $this->files->exists($destinationPath)) {
                continue;
            }

            $relativePath = ltrim(str_replace(rtrim($root, '/'), '', $destinationPath), '/');
            $backupPath = $root.'/.core-panel-backups/'.$timestamp.'/'.$relativePath;

            if ($this->files->isDirectory($destinationPath)) {
                $this->files->ensureDirectoryExists(dirname($backupPath));
                $this->files->copyDirectory($destinationPath, $backupPath);

                continue;
            }

            if ($this->files->exists($sourcePath) && $this->sameContents($sourcePath, $destinationPath)) {
                continue;
            }

            $this->files->ensureDirectoryExists(dirname($backupPath));
            $this->files->copy($destinationPath, $backupPath);
        }
    }

    private function sameContents(string $sourcePath, string $destinationPath): bool
    {
        return hash_equals(
            md5((string) $this->files->get($sourcePath)),
            md5((string) $this->files->get($destinationPath)),
        );
    }
}
