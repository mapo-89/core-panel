<?php

declare(strict_types=1);

namespace CorePanel\Support\Logs;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use SplFileInfo;

final class LogFileQuery
{
    private const FILENAME_REGEX = '/^[A-Za-z0-9._-]+\.log$/';

    /**
     * @return Collection<int, LogFileData>
     */
    public function all(): Collection
    {
        $logsPath = storage_path('logs');

        if (! File::isDirectory($logsPath)) {
            return collect();
        }

        return collect(File::files($logsPath))
            ->filter(static fn (SplFileInfo $file): bool => preg_match(self::FILENAME_REGEX, $file->getFilename()) === 1)
            ->map(function (SplFileInfo $file): LogFileData {
                $name = $file->getFilename();
                $absolute = $file->getRealPath() ?: $file->getPathname();
                $channelType = $this->detectChannelType($name);
                $isActive = $this->isActive($name, $absolute);

                return new LogFileData(
                    name: $name,
                    path: $absolute,
                    sizeBytes: (int) $file->getSize(),
                    modifiedAt: CarbonImmutable::createFromTimestamp($file->getMTime()),
                    channelType: $channelType,
                    isActive: $isActive,
                    canDelete: ! $isActive,
                    canClear: $channelType === 'single',
                );
            })
            ->values();
    }

    public function find(string $filename): ?LogFileData
    {
        if (! preg_match(self::FILENAME_REGEX, $filename)) {
            return null;
        }

        return $this->all()->firstWhere('name', $filename);
    }

    private function detectChannelType(string $name): string
    {
        if (preg_match('/^laravel-\d{4}-\d{2}-\d{2}\.log$/', $name) === 1) {
            return 'daily';
        }

        if ($name === 'laravel.log') {
            return 'single';
        }

        return 'other';
    }

    private function isActive(string $name, string $absolutePath): bool
    {
        if ($name === 'laravel.log') {
            return true;
        }

        if ($name === 'laravel-'.now()->toDateString().'.log') {
            return true;
        }

        $mtime = @filemtime($absolutePath);

        return $mtime !== false && (time() - $mtime) <= 5;
    }
}
