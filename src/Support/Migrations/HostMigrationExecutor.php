<?php

declare(strict_types=1);

namespace CorePanel\Support\Migrations;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class HostMigrationExecutor
{
    public function __construct(
        private Kernel $artisan,
        private Filesystem $files,
    ) {}

    /**
     * @return array{executed_migrations: list<string>, output: string}
     */
    public function execute(string $database, bool $force, ?string $basePath = null): array
    {
        $migrationFiles = $this->migrationFiles($basePath);
        $knownMigrationNames = $this->migrationNamesFromPaths($migrationFiles);
        $before = $this->appliedMigrationNames($database);

        $exitCode = $this->artisan->call('migrate', [
            '--database' => $database,
            '--force' => $force,
            '--path' => $migrationFiles,
            '--realpath' => true,
        ]);

        if ($exitCode !== 0) {
            throw new RuntimeException('Database migrations could not be executed.');
        }

        $after = $this->appliedMigrationNames($database);

        return [
            'executed_migrations' => array_values(array_intersect(
                array_diff($after, $before),
                $knownMigrationNames,
            )),
            'output' => trim($this->artisan->output()),
        ];
    }

    /**
     * @return list<string>
     */
    public function migrationFiles(?string $basePath = null): array
    {
        $root = $basePath ?? base_path();
        $migrationsRoot = $root.'/database/migrations';

        if (! $this->files->isDirectory($migrationsRoot)) {
            return [];
        }

        $migrationFiles = MigrationPathResolver::host($root);

        if ($migrationFiles === []) {
            return [];
        }

        $this->assertUniqueMigrationBasenames($migrationFiles);

        return $migrationFiles;
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

    /**
     * @return list<string>
     */
    private function appliedMigrationNames(string $database): array
    {
        if (! DB::connection($database)->getSchemaBuilder()->hasTable('migrations')) {
            return [];
        }

        return DB::connection($database)
            ->table('migrations')
            ->orderBy('batch')
            ->orderBy('migration')
            ->pluck('migration')
            ->map(static fn (mixed $migration): string => (string) $migration)
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    private function migrationNamesFromPaths(array $paths): array
    {
        return array_values(array_unique(array_map(
            static fn (string $path): string => pathinfo($path, PATHINFO_FILENAME),
            $paths,
        )));
    }
}
