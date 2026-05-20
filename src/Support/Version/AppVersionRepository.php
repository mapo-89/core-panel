<?php

declare(strict_types=1);

namespace CorePanel\Support\Version;

use Illuminate\Filesystem\Filesystem;

final readonly class AppVersionRepository
{
    public function __construct(private Filesystem $files) {}

    /**
     * @return array{
     *     release_version:string|null,
     *     display_version:string|null,
     *     image_version:string|null,
     *     commit:string|null,
     *     commit_date:string|null
     * }
     */
    public function current(): array
    {
        foreach ($this->candidatePaths() as $path) {
            if (! $this->files->exists($path)) {
                continue;
            }

            /** @var array<string, mixed>|null $decoded */
            $decoded = json_decode((string) $this->files->get($path), true);

            if (! is_array($decoded)) {
                continue;
            }

            return [
                'release_version' => $this->nullableString($decoded['release_version'] ?? null),
                'display_version' => $this->nullableString($decoded['display_version'] ?? null),
                'image_version' => $this->nullableString($decoded['image_version'] ?? null),
                'commit' => $this->nullableString($decoded['commit'] ?? null),
                'commit_date' => $this->nullableString($decoded['commit_date'] ?? null),
            ];
        }

        $fallback = $this->nullableString(config('app.version'));

        return [
            'release_version' => $fallback,
            'display_version' => $fallback,
            'image_version' => $fallback,
            'commit' => null,
            'commit_date' => null,
        ];
    }

    public function displayVersion(): ?string
    {
        return $this->current()['display_version'];
    }

    public function releaseVersion(): ?string
    {
        return $this->current()['release_version'];
    }

    /**
     * @return list<string>
     */
    private function candidatePaths(): array
    {
        return [
            base_path('config/app-version.json'),
            dirname(__DIR__, 3).'/config/app-version.json',
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
