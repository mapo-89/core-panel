<?php

declare(strict_types=1);

namespace CorePanel\Support\Migrations;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class MigrationPathResolver
{
    /**
     * @return list<string>
     */
    public static function central(?string $basePath = null): array
    {
        return self::host($basePath);
    }

    /**
     * @return list<string>
     */
    public static function host(?string $basePath = null): array
    {
        $root = $basePath ?? base_path();

        return self::sort([
            ...self::files($root.'/database/migrations', excludeTenantMigrations: true),
            ...self::hostPackage(),
        ]);
    }

    /**
     * @return list<string>
     */
    public static function tenant(?string $basePath = null): array
    {
        $root = $basePath ?? base_path();
        $tenantMigrationsPath = $root.'/database/migrations/tenant';
        $files = self::uniqueByBasename([
            ...self::files($tenantMigrationsPath),
            ...self::tenantPackage(),
        ]);

        return $files === [] ? [$tenantMigrationsPath] : $files;
    }

    /** @return list<string> */
    public static function corePackage(): array
    {
        return self::files(dirname(__DIR__, 3).'/database/migrations');
    }

    /** @return list<string> */
    public static function hostPackage(): array
    {
        return self::sort([
            ...self::corePackage(),
            ...self::configuredPackageMigrations('host_paths'),
        ]);
    }

    /** @return list<string> */
    public static function tenantPackage(): array
    {
        $packageMigrations = [];

        foreach (self::corePackage() as $migration) {
            $packageMigrations[basename($migration)] = $migration;
        }

        foreach (self::configuredPackageMigrations('tenant_paths') as $migration) {
            $packageMigrations[basename($migration)] = $migration;
        }

        return self::sort(array_values($packageMigrations));
    }

    /** @return list<string> */
    private static function configuredPackageMigrations(string $key): array
    {
        /** @var mixed $configuredPaths */
        $configuredPaths = config("core-panel.migrations.{$key}", []);

        if (! is_array($configuredPaths)) {
            return [];
        }

        $migrations = [];

        foreach ($configuredPaths as $configuredPath) {
            if (is_string($configuredPath) && $configuredPath !== '') {
                $migrations = [...$migrations, ...self::files($configuredPath)];
            }
        }

        return self::sort($migrations);
    }

    /**
     * @return list<string>
     */
    private static function files(string $migrationsRoot, bool $excludeTenantMigrations = false): array
    {
        if (! is_dir($migrationsRoot)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($migrationsRoot, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getRealPath() ?: $file->getPathname();

            if ($excludeTenantMigrations && str_contains($path, DIRECTORY_SEPARATOR.'migrations'.DIRECTORY_SEPARATOR.'tenant'.DIRECTORY_SEPARATOR)) {
                continue;
            }

            $files[] = $path;
        }

        return self::sort($files);
    }

    /**
     * @param  list<string>  $files
     * @return list<string>
     */
    private static function sort(array $files): array
    {
        $files = array_values(array_unique($files));

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

    /**
     * Keep the first migration for each basename so host tenant migrations
     * override package defaults without relying on Laravel's path ordering.
     *
     * @param  list<string>  $files
     * @return list<string>
     */
    private static function uniqueByBasename(array $files): array
    {
        $unique = [];

        foreach ($files as $file) {
            $unique[basename($file)] ??= $file;
        }

        return self::sort(array_values($unique));
    }
}
