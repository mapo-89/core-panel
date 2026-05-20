<?php

declare(strict_types=1);

namespace CorePanel\Support\Logs;

use Carbon\CarbonInterface;

final readonly class LogFileData
{
    public function __construct(
        public string $name,
        public string $path,
        public int $sizeBytes,
        public CarbonInterface $modifiedAt,
        public string $channelType,
        public bool $isActive,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'channelType' => $this->channelType,
            'isActive' => $this->isActive,
            'modifiedAt' => $this->modifiedAt->toIso8601String(),
            'name' => $this->name,
            'path' => $this->path,
            'sizeBytes' => $this->sizeBytes,
        ];
    }
}
