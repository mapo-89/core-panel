<?php

declare(strict_types=1);

namespace CorePanel\Support\Install;

use Illuminate\Filesystem\Filesystem;

final readonly class AppServiceProviderMerger
{
    public function __construct(
        private Filesystem $files,
        private BackupManager $backups,
    ) {}

    public function merge(?string $basePath = null): void
    {
        $root = $basePath ?? base_path();
        $appServiceProviderPath = $root.'/app/Providers/AppServiceProvider.php';

        if (! $this->files->exists($appServiceProviderPath)) {
            return;
        }

        $contents = (string) $this->files->get($appServiceProviderPath);

        if (str_contains($contents, "URL::forceScheme('https');")) {
            return;
        }

        $updatedContents = $contents;

        foreach ($this->requiredImports() as $import) {
            if (str_contains($updatedContents, $import)) {
                continue;
            }

            $updatedContents = preg_replace(
                '/^namespace\s+App\\\\Providers;\n/m',
                "namespace App\\Providers;\n\n".$import,
                $updatedContents,
                1,
            ) ?? $updatedContents;
        }

        $updatedContents = preg_replace(
            '/public function boot\(\): void\s*\{\n/',
            "public function boot(): void\n    {\n".$this->hookContents(),
            $updatedContents,
            1,
        ) ?? $updatedContents;

        if ($updatedContents === $contents) {
            return;
        }

        $this->backups->backupPaths([
            $this->hookStubPath() => $appServiceProviderPath,
        ], $root);

        $this->files->put($appServiceProviderPath, $updatedContents);
    }

    /**
     * @return list<string>
     */
    private function requiredImports(): array
    {
        return [
            'use Illuminate\\Support\\Facades\\URL;',
        ];
    }

    private function hookContents(): string
    {
        return (string) $this->files->get($this->hookStubPath());
    }

    private function hookStubPath(): string
    {
        return __DIR__.'/../../../stubs/merge/app-service-provider.force-https-hook.stub';
    }
}
