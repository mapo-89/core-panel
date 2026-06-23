<?php

declare(strict_types=1);

namespace CorePanel\Support\Migrations;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;

final readonly class HostMigrationRunner
{
    public function __construct(private Filesystem $files) {}

    public function run(Command $command, ?string $basePath = null): void
    {
        $root = $basePath ?? base_path();
        $migrationsRoot = $root.'/database/migrations';

        if (! $this->files->isDirectory($migrationsRoot)) {
            return;
        }

        $migrationFiles = $this->migrationFiles($migrationsRoot);

        if ($migrationFiles === []) {
            return;
        }

        $this->assertUniqueMigrationBasenames($migrationFiles);

        $command->call('migrate', [
            '--force' => true,
            '--path' => $migrationFiles,
            '--realpath' => true,
        ]);
    }

    /**
     * @return list<string>
     */
    private function migrationFiles(string $migrationsRoot): array
    {
        return MigrationPathResolver::host(dirname($migrationsRoot, 2));
    }

    /**
     * @param  list<string>  $migrationFiles
     */
    private function assertUniqueMigrationBasenames(array $migrationFiles): void
    {
        $groupedByBasename = [];

        foreach ($migrationFiles as $migrationFile) {
            $groupedByBasename[basename($migrationFile)][] = $migrationFile;
        }

        $duplicates = array_filter(
            $groupedByBasename,
            static fn (array $paths): bool => count($paths) > 1,
        );

        if ($duplicates === []) {
            return;
        }

        $details = collect($duplicates)
            ->map(static function (array $paths, string $basename): string {
                return sprintf("%s\n- %s", $basename, implode("\n- ", $paths));
            })
            ->implode("\n\n");

        throw new RuntimeException(
            "Duplicate host migration basenames detected. Laravel de-duplicates migrations by basename within a single migrate run, so these files must be renamed to stay globally unique:\n\n{$details}",
        );
    }
}
