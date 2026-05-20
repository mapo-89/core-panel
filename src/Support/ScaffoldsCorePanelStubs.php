<?php

declare(strict_types=1);

namespace CorePanel\Support;

use CorePanel\Support\Install\BackupManager;
use Illuminate\Filesystem\Filesystem;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final readonly class ScaffoldsCorePanelStubs
{
    public function __construct(private Filesystem $files, private BackupManager $backups) {}

    /**
     * @return list<string>
     */
    public static function paths(): array
    {
        $stubRoot = realpath(__DIR__.'/../../stubs');
        $packageRoutesRoot = realpath(__DIR__.'/../../routes/web');
        $packageResourcesRoot = realpath(__DIR__.'/../../resources/js');
        $packageLanguageRoot = realpath(__DIR__.'/../../resources/lang');
        $workspaceAiRoot = realpath(__DIR__.'/../../../../.ai');
        $workspaceAgentsRoot = realpath(__DIR__.'/../../../../.agents');
        $workspaceClaudeRoot = realpath(__DIR__.'/../../../../.claude');
        $workspaceAgentsFile = realpath(__DIR__.'/../../../../AGENTS.md');

        if ($stubRoot === false) {
            return [];
        }

        $paths = [];

        self::appendPathsFromRoot($paths, $stubRoot);

        if ($packageRoutesRoot !== false) {
            self::appendPathsFromRoot($paths, $packageRoutesRoot, 'routes/web');
        }

        if ($packageResourcesRoot !== false) {
            self::appendPathsFromRoot($paths, $packageResourcesRoot, 'resources/js');
        }

        if ($packageLanguageRoot !== false) {
            self::appendPathsFromRoot($paths, $packageLanguageRoot, 'lang');
        }

        if ($workspaceAiRoot !== false) {
            self::appendPathsFromRoot($paths, $workspaceAiRoot, '.ai');
        }

        if ($workspaceAgentsRoot !== false) {
            self::appendPathsFromRoot($paths, $workspaceAgentsRoot, '.agents');
        }

        if ($workspaceClaudeRoot !== false) {
            self::appendPathsFromRoot($paths, $workspaceClaudeRoot, '.claude');
        }

        if ($workspaceAgentsFile !== false) {
            $paths[] = 'AGENTS.md';
        }

        sort($paths);

        return array_values(array_unique($paths));
    }

    public function scaffold(bool $force = false, ?string $basePath = null): void
    {
        $root = $basePath ?? base_path();

        $this->deleteConflictingFiles($root);

        foreach (self::paths() as $relativePath) {
            $sourcePath = $this->sourcePath($relativePath);
            $destinationPath = $root.'/'.$relativePath;

            if ($relativePath === 'package.json' && $this->files->exists($destinationPath)) {
                $this->mergePackageJson($sourcePath, $destinationPath, $root);

                continue;
            }

            if (! $force && $this->files->exists($destinationPath)) {
                continue;
            }

            if ($force && $this->files->exists($destinationPath)) {
                $this->backups->backupPaths([$sourcePath => $destinationPath], $root);
            }

            $this->files->ensureDirectoryExists(dirname($destinationPath));
            $this->files->copy($sourcePath, $destinationPath);
        }
    }

    private static function appendPathsFromRoot(array &$paths, string $root, string $prefix = ''): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $relativePath = ltrim(str_replace($root, '', $file->getPathname()), DIRECTORY_SEPARATOR);
            $prefixedPath = $prefix === '' ? $relativePath : $prefix.'/'.$relativePath;

            if (self::shouldSkipPath($prefixedPath)) {
                continue;
            }

            $paths[] = $prefixedPath;
        }
    }

    private function sourcePath(string $relativePath): string
    {
        $stubSourcePath = __DIR__.'/../../stubs/'.$relativePath;

        if ($this->files->exists($stubSourcePath)) {
            return $stubSourcePath;
        }

        if (str_starts_with($relativePath, 'routes/web/')) {
            return __DIR__.'/../../'.$relativePath;
        }

        if (str_starts_with($relativePath, 'resources/js/')) {
            return __DIR__.'/../../'.$relativePath;
        }

        if (str_starts_with($relativePath, 'lang/')) {
            return __DIR__.'/../../resources/'.$relativePath;
        }

        if (str_starts_with($relativePath, '.ai/')) {
            return __DIR__.'/../../../../'.$relativePath;
        }

        if ($relativePath === 'AGENTS.md') {
            return __DIR__.'/../../../../AGENTS.md';
        }

        if (str_starts_with($relativePath, '.agents/')) {
            return __DIR__.'/../../../../'.$relativePath;
        }

        if (str_starts_with($relativePath, '.claude/')) {
            return __DIR__.'/../../../../'.$relativePath;
        }

        return $stubSourcePath;
    }

    private function mergePackageJson(string $sourcePath, string $destinationPath, string $root): void
    {
        $scaffoldPackage = json_decode((string) $this->files->get($sourcePath), true);
        $hostPackage = json_decode((string) $this->files->get($destinationPath), true);

        if (! is_array($scaffoldPackage) || ! is_array($hostPackage)) {
            return;
        }

        $mergedPackage = $hostPackage;

        foreach ($scaffoldPackage as $key => $value) {
            if (in_array($key, ['scripts', 'dependencies', 'devDependencies'], true)) {
                $hostSection = $hostPackage[$key] ?? [];
                $scaffoldSection = is_array($value) ? $value : [];

                $mergedPackage[$key] = [
                    ...is_array($hostSection) ? $hostSection : [],
                    ...$scaffoldSection,
                ];

                continue;
            }

            if (! array_key_exists($key, $mergedPackage)) {
                $mergedPackage[$key] = $value;
            }
        }

        $this->removeObsoleteManagedPackageJsonEntries($mergedPackage);

        $encodedPackage = json_encode(
            $mergedPackage,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
        );

        if (! is_string($encodedPackage)) {
            return;
        }

        $encodedPackage .= PHP_EOL;

        if ($encodedPackage === $this->files->get($destinationPath)) {
            return;
        }

        $this->backups->backupPaths([$sourcePath => $destinationPath], $root);
        $this->files->put($destinationPath, $encodedPackage);
    }

    /**
     * @param  array<string, mixed>  $package
     */
    private function removeObsoleteManagedPackageJsonEntries(array &$package): void
    {
        if (isset($package['devDependencies']) && is_array($package['devDependencies'])) {
            unset($package['devDependencies']['sass']);
        }
    }

    private function deleteConflictingFiles(string $root): void
    {
        foreach ([
            'vite.config.js',
            'bootstrap/app.php',
            'bootstrap/providers.php',
            'app/Models/User.php',
            'database/migrations/0001_01_01_000000_create_users_table.php',
            'database/migrations/0001_01_01_000001_create_cache_table.php',
            'database/migrations/0001_01_01_000002_create_jobs_table.php',
            'database/factories/UserFactory.php',
            'database/seeders/DatabaseSeeder.php',
            'routes/console.php',
            'routes/web.php',
            'resources/css/app.css',
            'resources/css/app.scss',
            'resources/css/theme/theme.scss',
            'resources/views/welcome.blade.php',
            'tests/Feature/ExampleTest.php',
            'tests/Unit/ExampleTest.php',
            'tests/Pest.php',
            'resources/js/routes/_wayfinder.ts',
            'resources/js/routes/locale.ts',
            'resources/js/routes/core-panel/forms/public.ts',
        ] as $relativePath) {
            $path = $root.'/'.$relativePath;

            if ($this->files->exists($path)) {
                $this->files->delete($path);
            }
        }

        foreach ($this->files->glob($root.'/resources/css/theme/*.scss') as $scssThemeFile) {
            $this->files->delete($scssThemeFile);
        }
    }

    private static function shouldSkipPath(string $relativePath): bool
    {
        if ($relativePath === '.env') {
            return true;
        }

        if ($relativePath === 'app/Providers/AppServiceProvider.php') {
            return true;
        }

        if (str_ends_with($relativePath, '.scss')) {
            return true;
        }

        foreach ([
            'core-panel/',
            'node_modules/',
            'public/build/',
        ] as $prefix) {
            if (str_starts_with($relativePath, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
