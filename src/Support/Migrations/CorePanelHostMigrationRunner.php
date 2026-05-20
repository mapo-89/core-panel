<?php

declare(strict_types=1);

namespace CorePanel\Support\Migrations;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final readonly class CorePanelHostMigrationRunner
{
    public function __construct(private Filesystem $files) {}

    public function run(Command $command, ?string $basePath = null): void
    {
        $root = $basePath ?? base_path();
        $migrationsRoot = $root.'/database/migrations';

        if (! $this->files->isDirectory($migrationsRoot)) {
            return;
        }

        foreach ($this->migrationFiles($migrationsRoot) as $migrationFile) {
            $command->call('migrate', [
                '--force' => true,
                '--path' => $migrationFile,
                '--realpath' => true,
            ]);
        }
    }

    /**
     * @return list<string>
     */
    private function migrationFiles(string $migrationsRoot): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($migrationsRoot, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getPathname();

            if (str_contains($path, DIRECTORY_SEPARATOR.'migrations'.DIRECTORY_SEPARATOR.'tenant'.DIRECTORY_SEPARATOR)) {
                continue;
            }

            $files[] = $path;
        }

        usort($files, static function (string $left, string $right): int {
            $leftName = basename($left);
            $rightName = basename($right);
            $nameComparison = strcmp($leftName, $rightName);

            if ($nameComparison !== 0) {
                return $nameComparison;
            }

            return strcmp($left, $right);
        });

        return $files;
    }
}
