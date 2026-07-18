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

        return self::files($root.'/database/migrations', excludeTenantMigrations: true);
    }

    /**
     * @return list<string>
     */
    public static function tenant(?string $basePath = null): array
    {
        $root = $basePath ?? base_path();
        $tenantMigrationsPath = $root.'/database/migrations/tenant';
        $files = self::files($tenantMigrationsPath);

        return $files === [] ? [$tenantMigrationsPath] : $files;
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

        usort($files, static function (string $left, string $right): int {
            $leftName = basename($left);
            $rightName = basename($right);
            $nameComparison = strcmp($leftName, $rightName);

            if ($nameComparison !== 0) {
                return $nameComparison;
            }

            return strcmp($left, $right);
        });

        return array_values(array_unique($files));
    }
}
