<?php

declare(strict_types=1);

namespace CorePanel\Domain\Dashboard\DTOs;

final readonly class SystemHealthData
{
    public function __construct(
        public string $appVersion,
        public string $phpVersion,
        public string $laravelVersion,
        public string $queueStatus,
        public string $redisStatus,
        public string $databaseStatus,
        public string $storageStatus,
        public string $octaneStatus,
    ) {}

    /**
     * @return array{
     *     appVersion:string,
     *     phpVersion:string,
     *     laravelVersion:string,
     *     queueStatus:string,
     *     redisStatus:string,
     *     databaseStatus:string,
     *     storageStatus:string,
     *     octaneStatus:string
     * }
     */
    public function toArray(): array
    {
        return [
            'appVersion' => $this->appVersion,
            'phpVersion' => $this->phpVersion,
            'laravelVersion' => $this->laravelVersion,
            'queueStatus' => $this->queueStatus,
            'redisStatus' => $this->redisStatus,
            'databaseStatus' => $this->databaseStatus,
            'storageStatus' => $this->storageStatus,
            'octaneStatus' => $this->octaneStatus,
        ];
    }
}
