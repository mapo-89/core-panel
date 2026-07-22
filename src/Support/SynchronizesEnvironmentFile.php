<?php

declare(strict_types=1);

namespace CorePanel\Support;

use Illuminate\Filesystem\Filesystem;

final readonly class SynchronizesEnvironmentFile
{
    public function __construct(private Filesystem $files) {}

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    public function sync(
        ?string $basePath = null,
        array $overrides = [],
        bool $replaceTemplateValues = false,
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

        if (! $this->files->exists($environmentPath)) {
            $this->files->copy($templatePath, $environmentPath);
            $this->files->put($environmentPath, $this->render($templateContents, $templateWithOverrides));

            return $templateWithOverrides;
        }

        $currentContents = (string) $this->files->get($environmentPath);
        $current = $this->parse($currentContents);
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

        $this->files->put($environmentBackupPath, $currentContents);
        $this->files->put($environmentPath, $this->render($templateContents, $synchronized));

        return $synchronized;
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
