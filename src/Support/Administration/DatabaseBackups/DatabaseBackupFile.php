<?php

declare(strict_types=1);

namespace CorePanel\Support\Administration\DatabaseBackups;

use Illuminate\Support\Carbon;

final readonly class DatabaseBackupFile
{
    /**
     * @param  list<string>  $storageLocations
     */
    public function __construct(
        public string $name,
        public string $path,
        public int $size,
        public Carbon $createdAt,
        public bool $encrypted = false,
        public array $storageLocations = ['local'],
    ) {}

    /**
     * @return array{created_at: string, encrypted: bool, name: string, source: string, size: int, size_for_humans: string, storage_locations: list<string>}
     */
    public function toArray(): array
    {
        return [
            'created_at' => $this->createdAt->toIso8601String(),
            'encrypted' => $this->encrypted,
            'name' => $this->name,
            'source' => $this->source(),
            'size' => $this->size,
            'size_for_humans' => $this->sizeForHumans(),
            'storage_locations' => $this->storageLocations,
        ];
    }

    public function source(): string
    {
        if (str_ends_with($this->name, '-automatic.dump') || str_ends_with($this->name, '-automatic.dump.enc')) {
            return 'automatic';
        }

        if (str_ends_with($this->name, '-imported.dump') || str_ends_with($this->name, '-imported.dump.enc')) {
            return 'imported';
        }

        if (str_ends_with($this->name, '-manual.dump') || str_ends_with($this->name, '-manual.dump.enc')) {
            return 'manual';
        }

        return 'custom';
    }

    private function sizeForHumans(): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = (float) $this->size;
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return sprintf(
            '%s %s',
            $unitIndex === 0 ? (string) (int) $size : number_format($size, 1),
            $units[$unitIndex],
        );
    }
}
