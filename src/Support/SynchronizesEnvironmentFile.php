<?php

declare(strict_types=1);

namespace CorePanel\Support;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Process;

final readonly class SynchronizesEnvironmentFile
{
    private const LEGACY_UPDATER_RUNTIME_SERVICES = 'app,horizon,scheduler,nginx';

    private const UPDATER_RUNTIME_SERVICES = 'app,horizon,scheduler';

    private const AUTO_DETECTED_COMPOSE_FILE = 'docker-compose.portainer.yml';

    /** @var list<string> */
    private const OBSOLETE_KEYS = [
        'OCTANE_HOST',
        'OCTANE_HTTPS',
        'OCTANE_PORT',
        'OCTANE_SERVER',
    ];

    public function __construct(private Filesystem $files) {}

    public function configuredValue(string $key, ?string $basePath = null): ?string
    {
        $runtimeValue = $this->usesCurrentApplicationBasePath($basePath)
            ? getenv($key)
            : false;

        if (is_string($runtimeValue)) {
            return $this->normalizedEnvironmentValue($runtimeValue);
        }

        $environmentPath = ($basePath ?? base_path()).'/.env';

        if (! $this->files->isFile($environmentPath)) {
            return null;
        }

        $environment = $this->parse((string) $this->files->get($environmentPath));

        return $this->normalizedEnvironmentValue($environment[$key] ?? null);
    }

    public function usesManagedRegistryComposeTopology(?string $basePath = null): bool
    {
        $root = $basePath ?? base_path();
        $configuredComposeProjectName = $this->configuredValue('SYSTEM_UPDATER_COMPOSE_PROJECT_NAME', $basePath)
            ?? $this->templateValue('SYSTEM_UPDATER_COMPOSE_PROJECT_NAME')
            ?? 'auto';
        $configuredSelfService = $this->configuredValue('SYSTEM_UPDATER_SELF_SERVICE', $basePath)
            ?? $this->templateValue('SYSTEM_UPDATER_SELF_SERVICE')
            ?? 'system-updater';
        $labeledComposeConfiguration = $this->labeledComposeConfiguration(
            $configuredComposeProjectName,
            $configuredSelfService,
            false,
        );

        if (is_array($labeledComposeConfiguration)) {
            return $this->composeFilesRequireApplicationImage(
                $labeledComposeConfiguration['workdir'],
                $labeledComposeConfiguration['files'],
            ) !== false;
        }

        $configuredComposeFiles = $this->configuredValue('SYSTEM_UPDATER_COMPOSE_FILES', $basePath)
            ?? $this->templateValue('SYSTEM_UPDATER_COMPOSE_FILES')
            ?? '';

        if (strtolower($configuredComposeFiles) === 'auto') {
            return true;
        }

        $configuredComposeWorkdir = $this->configuredValue('SYSTEM_UPDATER_COMPOSE_WORKDIR', $basePath)
            ?? $this->templateValue('SYSTEM_UPDATER_COMPOSE_WORKDIR')
            ?? '';
        $configuredProjectPath = $this->configuredValue('SYSTEM_UPDATER_PROJECT_PATH', $basePath);
        $composeWorkdir = $this->composeDetectionWorkdir(
            $root,
            $configuredComposeWorkdir,
            $configuredProjectPath,
        );

        return $this->composeFilesRequireApplicationImage(
            $composeWorkdir,
            $this->splitComposeFiles($configuredComposeFiles),
        ) !== false;
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    public function sync(
        ?string $basePath = null,
        array $overrides = [],
        bool $replaceTemplateValues = false,
        ?bool $migrateLegacyUpdaterRuntimeServices = null,
    ): array {
        $root = $basePath ?? base_path();
        $templatePath = __DIR__.'/../../stubs/.env.example';
        $environmentPath = $root.'/.env';
        $environmentBackupPath = $root.'/.env.backup';

        if (! $this->files->exists($templatePath)) {
            return [];
        }

        $templateContents = (string) $this->files->get($templatePath);
        $template = $this->parse((string) $this->files->get($templatePath));
        $templateWithOverrides = array_replace($template, $overrides);
        $currentContents = $this->files->exists($environmentPath)
            ? (string) $this->files->get($environmentPath)
            : null;
        $current = $currentContents !== null ? $this->parse($currentContents) : [];
        $configuredComposeFiles = $overrides['SYSTEM_UPDATER_COMPOSE_FILES']
            ?? $current['SYSTEM_UPDATER_COMPOSE_FILES']
            ?? $templateWithOverrides['SYSTEM_UPDATER_COMPOSE_FILES']
            ?? '';
        $configuredComposeWorkdir = $overrides['SYSTEM_UPDATER_COMPOSE_WORKDIR']
            ?? $current['SYSTEM_UPDATER_COMPOSE_WORKDIR']
            ?? $templateWithOverrides['SYSTEM_UPDATER_COMPOSE_WORKDIR']
            ?? '';
        $configuredProjectPath = $overrides['SYSTEM_UPDATER_PROJECT_PATH']
            ?? $current['SYSTEM_UPDATER_PROJECT_PATH']
            ?? null;
        $configuredComposeProjectName = $overrides['SYSTEM_UPDATER_COMPOSE_PROJECT_NAME']
            ?? $current['SYSTEM_UPDATER_COMPOSE_PROJECT_NAME']
            ?? 'auto';
        $configuredSelfService = $overrides['SYSTEM_UPDATER_SELF_SERVICE']
            ?? $current['SYSTEM_UPDATER_SELF_SERVICE']
            ?? $templateWithOverrides['SYSTEM_UPDATER_SELF_SERVICE']
            ?? 'system-updater';
        $configuredComposeFiles = $this->normalizedEnvironmentValue($configuredComposeFiles) ?? '';
        $configuredComposeWorkdir = $this->normalizedEnvironmentValue($configuredComposeWorkdir) ?? '';
        $configuredProjectPath = $this->normalizedEnvironmentValue($configuredProjectPath);
        $configuredComposeProjectName = $this->normalizedEnvironmentValue($configuredComposeProjectName) ?? 'auto';
        $configuredSelfService = $this->normalizedEnvironmentValue($configuredSelfService) ?? 'system-updater';
        $composeDetectionWorkdir = $this->composeDetectionWorkdir(
            $root,
            $configuredComposeWorkdir,
            $configuredProjectPath,
        );
        $migrateLegacyUpdaterRuntimeServices ??= $this->shouldMigrateLegacyUpdaterRuntimeServices(
            $root,
            $composeDetectionWorkdir,
            $configuredComposeFiles,
            $current['SYSTEM_UPDATER_RUNTIME_SERVICES'] ?? null,
            $configuredComposeProjectName,
            $configuredSelfService,
        );

        if (
            ! $migrateLegacyUpdaterRuntimeServices
            && $this->normalizedUpdaterRuntimeServices($current['SYSTEM_UPDATER_RUNTIME_SERVICES'] ?? null)
                === self::LEGACY_UPDATER_RUNTIME_SERVICES
            && ! array_key_exists('SYSTEM_UPDATER_RUNTIME_SERVICES', $overrides)
        ) {
            $templateWithOverrides['SYSTEM_UPDATER_RUNTIME_SERVICES'] = self::LEGACY_UPDATER_RUNTIME_SERVICES;
        }

        if ($currentContents === null) {
            $this->files->copy($templatePath, $environmentPath);
            $this->files->put($environmentPath, $this->render($templateContents, $templateWithOverrides));

            return $templateWithOverrides;
        }

        if (! $replaceTemplateValues
            && ! array_key_exists('SYSTEM_UPDATES_AUTOMATIC_TIME', $current)
            && ! array_key_exists('SYSTEM_UPDATES_AUTOMATIC_TIME', $overrides)
            && array_key_exists('SYSTEM_UPDATES_AUTOMATIC_WINDOW_START', $current)) {
            $templateWithOverrides['SYSTEM_UPDATES_AUTOMATIC_TIME'] = $current['SYSTEM_UPDATES_AUTOMATIC_WINDOW_START'];
        }

        $synchronized = $current;

        foreach ($templateWithOverrides as $key => $value) {
            if (! $replaceTemplateValues && array_key_exists($key, $current)) {
                continue;
            }

            $synchronized[$key] = $value;
        }

        foreach ($overrides as $key => $value) {
            $synchronized[$key] = $value;
        }

        if (
            $migrateLegacyUpdaterRuntimeServices
            && $this->normalizedUpdaterRuntimeServices($synchronized['SYSTEM_UPDATER_RUNTIME_SERVICES'] ?? null)
                === self::LEGACY_UPDATER_RUNTIME_SERVICES
        ) {
            $synchronized['SYSTEM_UPDATER_RUNTIME_SERVICES'] = self::UPDATER_RUNTIME_SERVICES;
        }

        foreach (self::OBSOLETE_KEYS as $obsoleteKey) {
            unset($synchronized[$obsoleteKey]);
        }

        $this->files->put($environmentBackupPath, $currentContents);
        $this->files->put($environmentPath, $this->render($templateContents, $synchronized));

        return $synchronized;
    }

    private function shouldMigrateLegacyUpdaterRuntimeServices(
        string $root,
        string $composeWorkdir,
        string $configuredComposeFiles,
        ?string $currentRuntimeServices,
        string $configuredComposeProjectName,
        string $configuredSelfService,
    ): bool {
        $configuredComposeFiles = trim(trim($configuredComposeFiles), '"\'');
        $currentRuntimeServices = $this->normalizedUpdaterRuntimeServices($currentRuntimeServices);

        if (strtolower(trim($configuredComposeFiles)) === 'auto') {
            $labeledComposeConfiguration = $this->labeledComposeConfiguration(
                $configuredComposeProjectName,
                $configuredSelfService,
            );

            if ($labeledComposeConfiguration === false) {
                return false;
            }

            if ($labeledComposeConfiguration !== null) {
                return $this->configuredComposeFilesDefineNginx(
                    $labeledComposeConfiguration['workdir'],
                    implode(',', $labeledComposeConfiguration['files']),
                ) === false;
            }

            foreach (array_unique([$composeWorkdir, $root]) as $candidateWorkdir) {
                $fallbackComposeFile = $candidateWorkdir.'/'.self::AUTO_DETECTED_COMPOSE_FILE;

                if ($this->files->isFile($fallbackComposeFile)) {
                    return $this->composeDefinesService(
                        (string) $this->files->get($fallbackComposeFile),
                        'nginx',
                    ) === false;
                }
            }

            return $currentRuntimeServices !== self::LEGACY_UPDATER_RUNTIME_SERVICES;
        }

        return $this->configuredComposeFilesDefineNginx($composeWorkdir, $configuredComposeFiles) === false;
    }

    /**
     * @return array{workdir:string,files:list<string>}|false|null
     */
    private function labeledComposeConfiguration(
        string $configuredComposeProjectName,
        string $configuredSelfService,
        bool $requireReadableFiles = true,
    ): array|false|null {
        $configuredComposeProjectName = trim(trim($configuredComposeProjectName), '"\'');
        $configuredSelfService = trim(trim($configuredSelfService), '"\'');
        $composeProjectIsAuto = $configuredComposeProjectName === ''
            || strtolower($configuredComposeProjectName) === 'auto';
        $command = [
            'docker',
            'container',
            'ls',
            '--quiet',
            '--filter',
            'label=com.docker.compose.service='.($configuredSelfService !== '' ? $configuredSelfService : 'system-updater'),
        ];

        if (! $composeProjectIsAuto) {
            $command = [
                ...$command,
                '--filter',
                'label=com.docker.compose.project='.$configuredComposeProjectName,
            ];
        }

        try {
            $containers = Process::timeout(5)->run($command);

            if (! $containers->successful()) {
                return null;
            }

            $containerIds = array_values(array_filter(preg_split('/\s+/', trim($containers->output())) ?: []));

            if ($composeProjectIsAuto && count($containerIds) > 1) {
                return false;
            }

            foreach ($containerIds as $containerId) {
                $inspect = Process::timeout(5)->run([
                    'docker',
                    'container',
                    'inspect',
                    '--format',
                    '{{json .Config.Labels}}',
                    $containerId,
                ]);

                if (! $inspect->successful()) {
                    continue;
                }

                $labels = json_decode(trim($inspect->output()), true);

                if (! is_array($labels)) {
                    continue;
                }

                $files = $this->splitComposeFiles(
                    is_string($labels['com.docker.compose.project.config_files'] ?? null)
                        ? $labels['com.docker.compose.project.config_files']
                        : '',
                );
                $workdir = is_string($labels['com.docker.compose.project.working_dir'] ?? null)
                    ? trim($labels['com.docker.compose.project.working_dir'])
                    : '';

                if ($files === [] || $workdir === '') {
                    continue;
                }

                if ($requireReadableFiles && (! $this->files->isDirectory($workdir)
                    || ! $this->composeFilesAreReadable($workdir, $files))) {
                    return false;
                }

                return [
                    'workdir' => $workdir,
                    'files' => $files,
                ];
            }

            if ($containerIds !== []) {
                return false;
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    /** @param list<string> $files */
    private function composeFilesAreReadable(string $workdir, array $files): bool
    {
        foreach ($files as $file) {
            $path = str_starts_with($file, '/') ? $file : $workdir.'/'.$file;

            if (! $this->files->isFile($path)) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> */
    private function splitComposeFiles(string $files): array
    {
        return array_values(array_filter(
            array_map('trim', explode(',', $files)),
            static fn (string $file): bool => $file !== '',
        ));
    }

    /** @param list<string> $composeFiles */
    private function composeFilesRequireApplicationImage(string $composeWorkdir, array $composeFiles): ?bool
    {
        if ($composeFiles === []) {
            return null;
        }

        foreach ($composeFiles as $composeFile) {
            $basename = basename($composeFile);

            if (in_array($basename, [
                'docker-compose.registry.yml',
                'docker-compose.portainer.yml',
            ], true)) {
                return true;
            }

            if (in_array($basename, [
                'compose.yml',
                'docker-compose.yml',
                'docker-compose.dev.yml',
                'docker-compose.prod.yml',
            ], true)) {
                continue;
            }

            $path = str_starts_with($composeFile, '/')
                ? $composeFile
                : $composeWorkdir.'/'.$composeFile;

            if (! $this->files->isFile($path)) {
                return null;
            }

            if (preg_match('/\b(?:APP|PHP|NGINX)_IMAGE\b/', (string) $this->files->get($path)) === 1) {
                return true;
            }
        }

        return false;
    }

    private function templateValue(string $key): ?string
    {
        $templatePath = __DIR__.'/../../stubs/.env.example';

        if (! $this->files->isFile($templatePath)) {
            return null;
        }

        $template = $this->parse((string) $this->files->get($templatePath));

        return $this->normalizedEnvironmentValue($template[$key] ?? null);
    }

    private function usesCurrentApplicationBasePath(?string $basePath): bool
    {
        if ($basePath === null) {
            return true;
        }

        $currentBasePath = base_path();
        $resolvedBasePath = realpath($basePath);
        $resolvedCurrentBasePath = realpath($currentBasePath);

        if (is_string($resolvedBasePath) && is_string($resolvedCurrentBasePath)) {
            return $resolvedBasePath === $resolvedCurrentBasePath;
        }

        return rtrim($basePath, DIRECTORY_SEPARATOR) === rtrim($currentBasePath, DIRECTORY_SEPARATOR);
    }

    private function composeDetectionWorkdir(
        string $root,
        string $configuredComposeWorkdir,
        ?string $configuredProjectPath,
    ): string {
        $configuredComposeWorkdir = trim(trim($configuredComposeWorkdir), '"\'');

        if ($configuredComposeWorkdir === '' || strtolower($configuredComposeWorkdir) === 'auto') {
            return $root;
        }

        if ($configuredComposeWorkdir === '/workspace'
            || str_starts_with($configuredComposeWorkdir, '/workspace/')) {
            $workspaceRelativePath = substr($configuredComposeWorkdir, strlen('/workspace'));
            $hostProjectPath = $root;

            if ($configuredProjectPath !== null && trim($configuredProjectPath) !== '') {
                $configuredProjectPath = trim(trim($configuredProjectPath), '"\'');
                $hostProjectPath = str_starts_with($configuredProjectPath, '/')
                    ? $configuredProjectPath
                    : $root.'/'.$configuredProjectPath;
            }

            return $workspaceRelativePath === ''
                ? $hostProjectPath
                : rtrim($hostProjectPath, '/').$workspaceRelativePath;
        }

        return str_starts_with($configuredComposeWorkdir, '/')
            ? $configuredComposeWorkdir
            : $root.'/'.$configuredComposeWorkdir;
    }

    private function configuredComposeFilesDefineNginx(string $composeWorkdir, string $configuredComposeFiles): ?bool
    {
        $hasConfiguredFile = false;
        $allConfiguredFilesAreReadable = true;
        $allConfiguredFilesUseSupportedSyntax = true;

        foreach (explode(',', $configuredComposeFiles) as $configuredComposeFile) {
            $configuredComposeFile = trim(trim($configuredComposeFile), '"\'');

            if ($configuredComposeFile === '') {
                continue;
            }

            $hasConfiguredFile = true;

            $path = str_starts_with($configuredComposeFile, '/')
                ? $configuredComposeFile
                : $composeWorkdir.'/'.$configuredComposeFile;

            if (! $this->files->isFile($path)) {
                $allConfiguredFilesAreReadable = false;

                continue;
            }

            $definesNginx = $this->composeDefinesService((string) $this->files->get($path), 'nginx');

            if ($definesNginx === true) {
                return true;
            }

            if ($definesNginx === null) {
                $allConfiguredFilesUseSupportedSyntax = false;
            }
        }

        if (! $hasConfiguredFile || ! $allConfiguredFilesAreReadable || ! $allConfiguredFilesUseSupportedSyntax) {
            return null;
        }

        return false;
    }

    private function composeDefinesService(string $contents, string $service): ?bool
    {
        $servicesIndentation = null;
        $serviceIndentation = null;

        foreach (preg_split('/\r\n|\r|\n/', $contents) ?: [] as $line) {
            if ($servicesIndentation === null) {
                if (preg_match('/^services\s*:\s*(?:#.*)?$/', $line) === 1) {
                    $servicesIndentation = 0;

                    continue;
                }

                if (preg_match('/^(?:services|"services"|\'services\')\s*:/', $line) === 1) {
                    return null;
                }

                continue;
            }

            if (trim($line) === '' || str_starts_with(ltrim($line), '#')) {
                continue;
            }

            $indentation = strlen($line) - strlen(ltrim($line));

            if ($indentation <= $servicesIndentation) {
                break;
            }

            $isServiceEntry = preg_match(
                '/^\s+(?:"([^"]+)"|\'([^\']+)\'|([A-Za-z0-9_.-]+))\s*:/',
                $line,
                $matches,
            ) === 1;

            if ($serviceIndentation === null && ! $isServiceEntry) {
                return null;
            }

            if ($serviceIndentation !== null && $indentation === $serviceIndentation && ! $isServiceEntry) {
                return null;
            }

            if (! $isServiceEntry) {
                continue;
            }

            $serviceIndentation ??= $indentation;
            $key = $matches[1] !== '' ? $matches[1] : ($matches[2] !== '' ? $matches[2] : $matches[3]);

            if ($indentation === $serviceIndentation && $key === $service) {
                return true;
            }
        }

        return $servicesIndentation !== null && $serviceIndentation !== null
            ? false
            : null;
    }

    private function normalizedEnvironmentValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($this->stripEnvironmentInlineComment($value));

        if (strlen($value) >= 2) {
            $firstCharacter = $value[0];
            $lastCharacter = $value[strlen($value) - 1];

            if (($firstCharacter === '"' && $lastCharacter === '"')
                || ($firstCharacter === "'" && $lastCharacter === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        return trim($value);
    }

    private function normalizedUpdaterRuntimeServices(?string $value): ?string
    {
        $value = $this->normalizedEnvironmentValue($value);

        if ($value === null) {
            return null;
        }

        $services = array_filter(
            array_map(trim(...), explode(',', $value)),
            static fn (string $service): bool => $service !== '',
        );

        return implode(',', $services);
    }

    private function stripEnvironmentInlineComment(string $value): string
    {
        $quote = null;
        $escaped = false;
        $length = strlen($value);

        for ($index = 0; $index < $length; $index++) {
            $character = $value[$index];

            if ($quote !== null) {
                if ($quote === '"' && $character === '\\' && ! $escaped) {
                    $escaped = true;

                    continue;
                }

                if ($character === $quote && ! $escaped) {
                    $quote = null;
                }

                $escaped = false;

                continue;
            }

            if ($character === '"' || $character === "'") {
                $quote = $character;

                continue;
            }

            if ($character === '#' && $index > 0 && ctype_space($value[$index - 1])) {
                return rtrim(substr($value, 0, $index));
            }
        }

        return $value;
    }

    /**
     * @return array<string, string>
     */
    private function parse(string $contents): array
    {
        $values = [];

        foreach (preg_split('/\r\n|\r|\n/', $contents) ?: [] as $line) {
            if ($line === '' || str_starts_with(trim($line), '#') || ! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $normalizedKey = preg_replace('/^export\s+/i', '', trim($key)) ?? trim($key);
            $values[$normalizedKey] = $value;
        }

        return $values;
    }

    /**
     * @param  array<string, string>  $values
     */
    private function render(string $originalContents, array $values): string
    {
        $renderedKeys = [];
        $lines = [];

        foreach (preg_split('/\r\n|\r|\n/', $originalContents) ?: [] as $line) {
            if ($line !== '' && ! str_starts_with(trim($line), '#') && str_contains($line, '=')) {
                [$key] = explode('=', $line, 2);
                $normalizedKey = trim($key);

                if (array_key_exists($normalizedKey, $values)) {
                    if (array_key_exists($normalizedKey, $renderedKeys)) {
                        continue;
                    }

                    $lines[] = $normalizedKey.'='.$values[$normalizedKey];
                    $renderedKeys[$normalizedKey] = true;

                    continue;
                }
            }

            $lines[] = $line;
        }

        foreach ($values as $key => $value) {
            if (! array_key_exists($key, $renderedKeys)) {
                $lines[] = $key.'='.$value;
            }
        }

        return rtrim(implode(PHP_EOL, $lines)).PHP_EOL;
    }
}
