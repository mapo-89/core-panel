<?php

declare(strict_types=1);

namespace CorePanel\Support;

use CorePanel\Support\Install\BackupManager;
use Illuminate\Filesystem\Filesystem;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Process\Process;

final readonly class ScaffoldsCorePanelStubs
{
    /**
     * Host files that should stay absent once an application resolves them
     * directly from the package runtime.
     *
     * @var list<string>
     */
    private const VENDOR_FIRST_SCAFFOLD_PREFIXES = [
        'lang/',
        'lang/de.json',
        'lang/en.json',
        'routes/web/admin.php',
        'routes/web/admin/',
        'resources/css/theme/',
        'resources/js/pages/',
        'resources/views/app.blade.php',
    ];

    /**
     * Scaffold files that were introduced after existing applications may already
     * have installed CorePanel without a scaffold baseline manifest.
     *
     * @var list<string>
     */
    private const VERSIONED_UPDATE_SCAFFOLDS = [
        '.env.example',
        'bootstrap/app.php',
        '.docker/bin/php-entrypoint.sh',
        '.docker/bin/prepare-local-environment.sh',
        '.docker/bin/start-dev-app.sh',
        '.docker/bin/start-dev-artisan.sh',
        '.docker/nginx/default.conf',
        '.docker/php/banner.sh',
        '.docker/php/entrypoint.sh',
        '.docker/php/opcache.ini',
        '.docker/php/php.ini',
        '.docker/php-fpm/zz-docker.conf',
        '.dockerignore',
        'Dockerfile',
        'docker-compose.dev.yml',
        'docker-compose.portainer.yml',
        'docker-compose.prod.yml',
        'docker-compose.registry.yml',
        'docker-compose.yml',
        'updater/Dockerfile',
        'updater/go.mod',
        'updater/main.go',
        'lang/de/account-mail.php',
        'lang/de/administration.php',
        'lang/de/common.php',
        'lang/de/database_backups.php',
        'lang/de/navigation.php',
        'lang/de/page-log-files.php',
        'lang/de/page-users.php',
        'lang/de/page-user-groups.php',
        'lang/de/system_updates.php',
        'lang/en/account-mail.php',
        'lang/en/administration.php',
        'lang/en/common.php',
        'lang/en/database_backups.php',
        'lang/en/navigation.php',
        'lang/en/page-log-files.php',
        'lang/en/page-users.php',
        'lang/en/page-user-groups.php',
        'lang/en/system_updates.php',
        'config/pwa.php',
        'config/trustedproxy.php',
        'public/logo.png',
        'public/manifest.json',
        'public/offline.html',
        'public/sw.js',
        'resources/css/app.css',
        'resources/js/routes/core-panel/administration.ts',
        'resources/js/routes/core-panel/log-files.ts',
        'routes/console.php',
        'routes/web.php',
    ];

    public function __construct(private Filesystem $files, private BackupManager $backups) {}

    /**
     * @return list<string>
     */
    public static function paths(): array
    {
        $stubRoot = realpath(__DIR__.'/../../stubs');
        $packageRoutesRoot = realpath(__DIR__.'/../../routes/web');
        $packageLanguageRoot = realpath(__DIR__.'/../../resources/lang');
        $packageAiRoot = realpath(__DIR__.'/../../.ai');
        $packageAgentsRoot = realpath(__DIR__.'/../../.agents');
        $packageClaudeRoot = realpath(__DIR__.'/../../.claude');
        $packageAgentsFile = realpath(__DIR__.'/../../AGENTS.md');

        if ($stubRoot === false) {
            return [];
        }

        $paths = [];

        self::appendPathsFromRoot($paths, $stubRoot);

        if ($packageRoutesRoot !== false) {
            self::appendPathsFromRoot($paths, $packageRoutesRoot, 'routes/web');
        }

        if ($packageLanguageRoot !== false) {
            self::appendPathsFromRoot($paths, $packageLanguageRoot, 'lang');
        }

        if ($packageAiRoot !== false) {
            self::appendPathsFromRoot($paths, $packageAiRoot, '.ai');
        }

        if ($packageAgentsRoot !== false) {
            self::appendPathsFromRoot($paths, $packageAgentsRoot, '.agents');
        }

        if ($packageClaudeRoot !== false) {
            self::appendPathsFromRoot($paths, $packageClaudeRoot, '.claude');
        }

        if ($packageAgentsFile !== false) {
            $paths[] = 'AGENTS.md';
        }

        sort($paths);

        return array_values(array_unique($paths));
    }

    public function scaffold(
        bool $force = false,
        ?string $basePath = null,
        bool $pruneHostScaffolds = true,
        bool $mergeExisting = false,
        bool $onlyManagedChanges = false,
    ): void {
        $root = $basePath ?? base_path();

        $this->deleteConflictingFiles($root, $pruneHostScaffolds);
        $currentVersion = $this->currentPackageVersion();
        $installedVersion = $this->installedScaffoldPackageVersion($root);

        foreach (self::paths() as $relativePath) {
            $sourcePath = $this->sourcePath($relativePath);
            $destinationPath = $root.'/'.$relativePath;
            $destinationExists = $this->files->exists($destinationPath);

            if (! $destinationExists && $this->isVendorFirstScaffold($relativePath)) {
                continue;
            }

            if ($relativePath === 'package.json' && $destinationExists) {
                if ($onlyManagedChanges && ! $this->shouldUpdateExistingManagedScaffold($relativePath, $root, $currentVersion, $installedVersion)) {
                    continue;
                }

                $this->mergePackageJson($sourcePath, $destinationPath, $root);

                continue;
            }

            if ($relativePath === 'bootstrap/providers.php' && $destinationExists) {
                if ($this->mergeBootstrapProvidersScaffold($sourcePath, $destinationPath, $root)) {
                    continue;
                }
            }

            if ($destinationExists && $this->shouldNeverOverwrite($relativePath, $destinationPath)) {
                continue;
            }

            if ($onlyManagedChanges && ! $destinationExists && ! $this->shouldCreateMissingManagedScaffold($relativePath, $root, $currentVersion, $installedVersion)) {
                continue;
            }

            if ($onlyManagedChanges && $destinationExists && ! $this->shouldUpdateExistingManagedScaffold($relativePath, $root, $currentVersion, $installedVersion)) {
                continue;
            }

            if (! $force && $destinationExists && ! $this->shouldAlwaysSynchronize($relativePath)) {
                if ($onlyManagedChanges && $this->shouldSynchronizeVersionedScaffold($relativePath, $root, $currentVersion, $installedVersion)) {
                    $this->synchronizeVersionedScaffold($relativePath, $sourcePath, $destinationPath, $root);

                    continue;
                }

                if ($mergeExisting && $this->mergeExistingScaffold($relativePath, $sourcePath, $destinationPath, $root)) {
                    continue;
                }

                continue;
            }

            if ($force && $destinationExists) {
                $this->backups->backupPaths([$sourcePath => $destinationPath], $root);
            }

            $this->writeScaffoldFile($relativePath, $sourcePath, $destinationPath, $root);
        }
    }

    private function isVendorFirstScaffold(string $relativePath): bool
    {
        foreach (self::VENDOR_FIRST_SCAFFOLD_PREFIXES as $prefix) {
            if (str_starts_with($relativePath, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $relativePaths
     */
    public function synchronizeVersionedScaffolds(array $relativePaths, ?string $basePath = null): void
    {
        $root = $basePath ?? base_path();
        $currentVersion = $this->currentPackageVersion();
        $installedVersion = $this->installedScaffoldPackageVersion($root);

        foreach ($relativePaths as $relativePath) {
            if (! $this->shouldSynchronizeVersionedScaffold($relativePath, $root, $currentVersion, $installedVersion)) {
                continue;
            }

            $this->synchronizeVersionedScaffold(
                $relativePath,
                $this->sourcePath($relativePath),
                $root.'/'.$relativePath,
                $root,
            );
        }
    }

    /**
     * @param  list<string>  $relativePaths
     */
    public function refreshHostRenderedScaffolds(array $relativePaths, ?string $basePath = null): void
    {
        $root = $basePath ?? base_path();

        foreach ($relativePaths as $relativePath) {
            $sourcePath = $this->sourcePath($relativePath);
            $destinationPath = $root.'/'.$relativePath;

            if (
                ! $this->files->isFile($sourcePath)
                || ! $this->files->isFile($destinationPath)
                || ! $this->hasScaffoldBaseline($relativePath, $root)
            ) {
                continue;
            }

            $this->writeScaffoldFile($relativePath, $sourcePath, $destinationPath, $root);
        }
    }

    private function shouldAlwaysSynchronize(string $relativePath): bool
    {
        return false;
    }

    private function shouldNeverOverwrite(string $relativePath, string $destinationPath): bool
    {
        if ($relativePath !== 'config/app-version.json') {
            return false;
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode((string) $this->files->get($destinationPath), true);

        return is_array($decoded) && ($decoded['managed_by_application'] ?? false) === true;
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

        if (str_starts_with($relativePath, 'resources/css/')) {
            return __DIR__.'/../../'.$relativePath;
        }

        if (str_starts_with($relativePath, 'lang/')) {
            return __DIR__.'/../../resources/'.$relativePath;
        }

        if (str_starts_with($relativePath, '.ai/')) {
            return $this->packageSupportPath($relativePath);
        }

        if ($relativePath === 'AGENTS.md') {
            return $this->packageSupportPath($relativePath);
        }

        if (str_starts_with($relativePath, '.agents/')) {
            return $this->packageSupportPath($relativePath);
        }

        if (str_starts_with($relativePath, '.claude/')) {
            return $this->packageSupportPath($relativePath);
        }

        return $stubSourcePath;
    }

    private function packageSupportPath(string $relativePath): string
    {
        return __DIR__.'/../../'.$relativePath;
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

    private function mergeExistingScaffold(
        string $relativePath,
        string $sourcePath,
        string $destinationPath,
        string $root,
    ): bool {
        if (! $this->files->isFile($sourcePath) || ! $this->files->isFile($destinationPath)) {
            return false;
        }

        $sourceContents = (string) $this->files->get($sourcePath);
        $destinationContents = (string) $this->files->get($destinationPath);

        if ($sourceContents === $destinationContents) {
            return true;
        }

        $baseContents = $this->scaffoldBaselineContents($relativePath, $root);

        if ($baseContents === null) {
            return false;
        }

        if ($baseContents === $destinationContents) {
            $this->backups->backupPaths([$sourcePath => $destinationPath], $root);
            $this->files->put($destinationPath, $sourceContents);
            $this->storeScaffoldManifestEntry($relativePath, $sourcePath, $destinationPath, $root);

            return true;
        }

        $mergedContents = $this->mergeFileContents($baseContents, $destinationContents, $sourceContents);

        if ($mergedContents === null || $mergedContents === $destinationContents) {
            return $mergedContents !== null;
        }

        $this->backups->backupPaths([$sourcePath => $destinationPath], $root);
        $this->files->put($destinationPath, $mergedContents);
        $this->storeScaffoldManifestEntry($relativePath, $sourcePath, $destinationPath, $root);

        return true;
    }

    private function mergeBootstrapProvidersScaffold(
        string $sourcePath,
        string $destinationPath,
        string $root,
    ): bool {
        if (! $this->files->isFile($sourcePath) || ! $this->files->isFile($destinationPath)) {
            return false;
        }

        $sourceContents = (string) $this->files->get($sourcePath);
        $destinationContents = (string) $this->files->get($destinationPath);
        $mergedContents = $destinationContents;
        $hasUseBlock = preg_match('/^use\s+[^\n]+;\n/m', $mergedContents) === 1;
        $requiredProviders = $this->requiredBootstrapProviderClasses($sourceContents);

        foreach ($requiredProviders as $providerClass) {
            if ($this->bootstrapProvidersContains($mergedContents, $providerClass)) {
                continue;
            }

            if ($hasUseBlock) {
                $shortName = class_basename($providerClass);
                $useStatement = 'use '.$providerClass.';';

                if (! str_contains($mergedContents, $useStatement)) {
                    $mergedContents = preg_replace(
                        '/^(use\s+[^\n]+;\n)+/m',
                        '$0'.$useStatement."\n",
                        $mergedContents,
                        1,
                    ) ?? $mergedContents;
                }

                $providerReference = $shortName.'::class';
            } else {
                $providerReference = '\\'.$providerClass.'::class';
            }

            $mergedContents = preg_replace(
                '/^(\s*)\];\s*$/m',
                '$1    '.$providerReference.','."\n".'$0',
                $mergedContents,
                1,
            ) ?? $mergedContents;
        }

        if ($mergedContents === $destinationContents) {
            return false;
        }

        $this->backups->backupPaths([$sourcePath => $destinationPath], $root);
        $this->files->put($destinationPath, $mergedContents);
        $this->storeScaffoldManifestEntry('bootstrap/providers.php', $sourcePath, $destinationPath, $root);

        return true;
    }

    /**
     * @return list<string>
     */
    private function requiredBootstrapProviderClasses(string $sourceContents): array
    {
        preg_match_all('/^\s*use\s+([^;]+);\s*$/m', $sourceContents, $matches);

        $imports = array_values(array_filter(
            $matches[1],
            static fn (string $value): bool => str_ends_with($value, 'ServiceProvider')
                && $value !== 'App\\Providers\\AppServiceProvider',
        ));

        /** @var list<string> $imports */
        return $imports;
    }

    private function bootstrapProvidersContains(string $contents, string $providerClass): bool
    {
        $shortName = class_basename($providerClass);

        return str_contains($contents, $shortName.'::class')
            || str_contains($contents, '\\'.$providerClass.'::class');
    }

    private function scaffoldBaselineContents(string $relativePath, string $root): ?string
    {
        $manifest = $this->readScaffoldManifestFiles($root);
        $entry = $manifest[$relativePath] ?? null;

        if (! is_array($entry) || ! is_string($entry['snapshot'] ?? null)) {
            return null;
        }

        $snapshotPath = $root.'/'.$entry['snapshot'];

        if (! $this->files->isFile($snapshotPath)) {
            return null;
        }

        return (string) $this->files->get($snapshotPath);
    }

    private function storeScaffoldManifestEntry(
        string $relativePath,
        string $sourcePath,
        string $destinationPath,
        string $root,
    ): void {
        if (! $this->files->isFile($sourcePath) || ! $this->files->isFile($destinationPath)) {
            return;
        }

        $sourceContents = (string) $this->files->get($sourcePath);
        $destinationContents = (string) $this->files->get($destinationPath);
        $sourceHash = hash('sha256', $sourceContents);
        $snapshotPath = $this->scaffoldSnapshotPath($sourceHash);
        $absoluteSnapshotPath = $root.'/'.$snapshotPath;

        $this->files->ensureDirectoryExists(dirname($absoluteSnapshotPath));

        if (! $this->files->exists($absoluteSnapshotPath)) {
            $this->files->put($absoluteSnapshotPath, $sourceContents);
        }

        $manifest = $this->readScaffoldManifestFiles($root);
        $manifest[$relativePath] = [
            'destination_hash' => hash('sha256', $destinationContents),
            'package_version' => $this->currentPackageVersion() ?? '',
            'snapshot' => $snapshotPath,
            'source_hash' => $sourceHash,
        ];

        ksort($manifest);

        $this->writeScaffoldManifest($root, $manifest, $this->currentPackageVersion());
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function readScaffoldManifestFiles(string $root): array
    {
        $decoded = $this->readScaffoldManifest($root);

        if (isset($decoded['files']) && is_array($decoded['files'])) {
            /** @var array<string, array<string, string>> $files */
            $files = $decoded['files'];

            return $files;
        }

        unset($decoded['_meta']);

        $files = [];

        foreach ($decoded as $path => $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $files[$path] = array_filter(
                $entry,
                static fn (mixed $value): bool => is_string($value),
            );
        }

        return $files;
    }

    /**
     * @return array<string, mixed>
     */
    private function readScaffoldManifest(string $root): array
    {
        $manifestPath = $this->scaffoldManifestPath($root);

        if (! $this->files->isFile($manifestPath)) {
            return [];
        }

        $decoded = json_decode((string) $this->files->get($manifestPath), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, array<string, string>>  $manifest
     */
    private function writeScaffoldManifest(string $root, array $manifest, ?string $packageVersion): void
    {
        $manifestPath = $this->scaffoldManifestPath($root);
        $this->files->ensureDirectoryExists(dirname($manifestPath));

        $encoded = json_encode([
            '_meta' => [
                'package_version' => $packageVersion,
            ],
            'files' => $manifest,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (! is_string($encoded)) {
            return;
        }

        $this->files->put($manifestPath, $encoded.PHP_EOL);
    }

    private function hasScaffoldBaseline(string $relativePath, string $root): bool
    {
        return $this->scaffoldBaselineContents($relativePath, $root) !== null;
    }

    private function shouldCreateMissingManagedScaffold(
        string $relativePath,
        string $root,
        ?string $currentVersion,
        ?string $installedVersion,
    ): bool {
        if ($this->shouldSynchronizeVersionedScaffold($relativePath, $root, $currentVersion, $installedVersion)) {
            return true;
        }

        $manifestFiles = $this->readScaffoldManifestFiles($root);
        $entry = $manifestFiles[$relativePath] ?? null;

        if (! is_array($entry) || ! is_string($entry['snapshot'] ?? null)) {
            return false;
        }

        return $this->files->isFile($root.'/'.$entry['snapshot'])
            && is_string($currentVersion)
            && $currentVersion !== '';
    }

    private function shouldUpdateExistingManagedScaffold(
        string $relativePath,
        string $root,
        ?string $currentVersion,
        ?string $installedVersion,
    ): bool {
        return $this->hasScaffoldBaseline($relativePath, $root)
            || $this->shouldSynchronizeVersionedScaffold($relativePath, $root, $currentVersion, $installedVersion);
    }

    private function shouldSynchronizeVersionedScaffold(
        string $relativePath,
        string $root,
        ?string $currentVersion,
        ?string $installedVersion,
    ): bool {
        if (! in_array($relativePath, self::VERSIONED_UPDATE_SCAFFOLDS, true)) {
            return false;
        }

        if (
            $this->isCreateOnlyPwaScaffold($relativePath)
            && $this->files->exists($root.'/'.$relativePath)
        ) {
            return false;
        }

        if (! is_string($currentVersion) || $currentVersion === '') {
            return false;
        }

        if ($installedVersion === null || $installedVersion === '' || $installedVersion !== $currentVersion) {
            return true;
        }

        $manifestEntry = $this->readScaffoldManifestFiles($root)[$relativePath] ?? null;

        return ! is_array($manifestEntry) || ($manifestEntry['package_version'] ?? null) !== $currentVersion;
    }

    private function isCreateOnlyPwaScaffold(string $relativePath): bool
    {
        return in_array($relativePath, [
            'config/pwa.php',
            'public/logo.png',
            'public/manifest.json',
            'public/offline.html',
            'public/sw.js',
        ], true);
    }

    private function installedScaffoldPackageVersion(string $root): ?string
    {
        $manifest = $this->readScaffoldManifest($root);
        $meta = $manifest['_meta'] ?? null;

        if (is_array($meta) && is_string($meta['package_version'] ?? null)) {
            return $meta['package_version'];
        }

        foreach ($this->readScaffoldManifestFiles($root) as $entry) {
            if (is_string($entry['package_version'] ?? null)) {
                return $entry['package_version'];
            }
        }

        return null;
    }

    private function synchronizeVersionedScaffold(
        string $relativePath,
        string $sourcePath,
        string $destinationPath,
        string $root,
    ): void {
        if (! $this->files->isFile($sourcePath)) {
            return;
        }

        $renderedContents = $this->scaffoldContentsForHost($relativePath, $sourcePath, $root);

        if (
            $this->files->isFile($destinationPath)
            && $renderedContents !== (string) $this->files->get($destinationPath)
        ) {
            $this->backups->backupPaths([$sourcePath => $destinationPath], $root);
        }

        $this->writeScaffoldFile($relativePath, $sourcePath, $destinationPath, $root);
    }

    private function writeScaffoldFile(
        string $relativePath,
        string $sourcePath,
        string $destinationPath,
        string $root,
    ): void {
        $this->files->ensureDirectoryExists(dirname($destinationPath));
        $this->files->put($destinationPath, $this->scaffoldContentsForHost($relativePath, $sourcePath, $root));
        $this->storeScaffoldManifestEntry($relativePath, $sourcePath, $destinationPath, $root);
    }

    private function scaffoldContentsForHost(string $relativePath, string $sourcePath, string $root): string
    {
        if ($relativePath === 'resources/css/app.css') {
            return $this->appCssContentsForHost($sourcePath, $root);
        }

        if ($relativePath === 'public/manifest.json') {
            return $this->manifestContentsForHost($sourcePath, $root);
        }

        return (string) $this->files->get($sourcePath);
    }

    private function manifestContentsForHost(string $sourcePath, string $root): string
    {
        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode((string) $this->files->get($sourcePath), true);

        if (! is_array($decoded)) {
            return (string) $this->files->get($sourcePath);
        }

        $appName = $this->hostAppName($root);
        $decoded['name'] = $appName;
        $decoded['short_name'] = $appName;

        $encoded = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (! is_string($encoded)) {
            return (string) $this->files->get($sourcePath);
        }

        return $encoded.PHP_EOL;
    }

    private function hostAppName(string $root): string
    {
        $environmentPath = $root.'/.env';

        if ($this->files->isFile($environmentPath)) {
            foreach (preg_split('/\r\n|\r|\n/', (string) $this->files->get($environmentPath)) ?: [] as $line) {
                if (! str_starts_with($line, 'APP_NAME=')) {
                    continue;
                }

                [, $value] = explode('=', $line, 2);
                $trimmed = trim($value);
                $unquoted = trim($trimmed, "\"'");

                if ($unquoted !== '') {
                    return $unquoted;
                }
            }
        }

        return 'CorePanel';
    }

    private function appCssContentsForHost(string $sourcePath, string $root): string
    {
        $sourceContents = (string) $this->files->get($sourcePath);
        $imports = $this->legacyThemeImports($root);

        if ($imports === []) {
            return $sourceContents;
        }

        return rtrim($sourceContents).PHP_EOL.PHP_EOL.implode(PHP_EOL, $imports).PHP_EOL;
    }

    /**
     * @return list<string>
     */
    private function legacyThemeImports(string $root): array
    {
        $themeRoot = $root.'/resources/css/theme';

        if (! $this->files->isDirectory($themeRoot)) {
            return [];
        }

        if ($this->files->isFile($themeRoot.'/theme.css')) {
            return ["@import './theme/theme.css';"];
        }

        $imports = collect($this->files->files($themeRoot))
            ->map(static fn (\SplFileInfo $file): ?string => $file->getExtension() === 'css'
                ? $file->getFilename()
                : null)
            ->filter()
            ->sort()
            ->values()
            ->map(static fn (string $filename): string => "@import './theme/{$filename}';")
            ->all();

        /** @var list<string> $imports */
        return $imports;
    }

    private function currentPackageVersion(): ?string
    {
        $versionPath = __DIR__.'/../../config/app-version.json';

        if (! $this->files->isFile($versionPath)) {
            return null;
        }

        $decoded = json_decode((string) $this->files->get($versionPath), true);

        if (! is_array($decoded) || ! is_string($decoded['release_version'] ?? null)) {
            return null;
        }

        return $decoded['release_version'];
    }

    private function scaffoldManifestPath(string $root): string
    {
        return $root.'/storage/app/core-panel/scaffolds.json';
    }

    private function scaffoldSnapshotPath(string $sourceHash): string
    {
        return 'storage/app/core-panel/scaffolds/'.$sourceHash;
    }

    private function mergeFileContents(string $baseContents, string $destinationContents, string $sourceContents): ?string
    {
        $temporaryDirectory = sys_get_temp_dir().'/core-panel-merge-'.bin2hex(random_bytes(8));

        $this->files->ensureDirectoryExists($temporaryDirectory);

        $basePath = $temporaryDirectory.'/base';
        $destinationPath = $temporaryDirectory.'/destination';
        $sourcePath = $temporaryDirectory.'/source';

        $this->files->put($basePath, $baseContents);
        $this->files->put($destinationPath, $destinationContents);
        $this->files->put($sourcePath, $sourceContents);

        $process = new Process(['git', 'merge-file', '-p', $destinationPath, $basePath, $sourcePath]);
        $process->run();

        $this->files->deleteDirectory($temporaryDirectory);

        if ($process->getExitCode() !== 0) {
            return null;
        }

        return $process->getOutput();
    }

    private function deleteConflictingFiles(string $root, bool $deleteHostScaffolds): void
    {
        if (! $deleteHostScaffolds) {
            return;
        }

        $conflictingFiles = [
            'resources/js/routes/_wayfinder.ts',
            'resources/js/routes/locale.ts',
            'resources/js/routes/core-panel/forms/public.ts',
        ];

        $conflictingFiles = [
            ...$conflictingFiles,
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
            'resources/js/app.js',
            'resources/css/app.css',
            'resources/css/app.scss',
            'resources/css/theme/theme.scss',
            'resources/views/welcome.blade.php',
            'tests/Feature/ExampleTest.php',
            'tests/Unit/ExampleTest.php',
            'tests/Pest.php',
        ];

        foreach ($conflictingFiles as $relativePath) {
            $path = $root.'/'.$relativePath;

            if ($this->files->exists($path)) {
                $this->files->delete($path);
            }
        }

        foreach (self::paths() as $relativePath) {
            if (! str_starts_with($relativePath, 'database/migrations/')) {
                continue;
            }

            $segments = explode('/', $relativePath);

            if (count($segments) <= 3) {
                continue;
            }

            $legacyPath = $root.'/database/migrations/';

            if ($segments[2] === 'tenant') {
                $legacyPath .= 'tenant/'.basename($relativePath);
            } else {
                $legacyPath .= basename($relativePath);
            }

            if ($this->files->exists($legacyPath)) {
                $this->files->delete($legacyPath);
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
            'merge/',
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
