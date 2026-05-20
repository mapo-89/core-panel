<?php

declare(strict_types=1);

namespace CorePanel\Domains\Dashboard\DTOs;

final readonly class DashboardData
{
    /**
     * @param  list<array{id:string,description:string,createdAt:?string,event:string}>  $recentActivities
     */
    public function __construct(
        public int $totalUsers,
        public int $activeUsers,
        public array $recentActivities,
        public int $pendingJobs,
        public int $failedJobs,
        public SystemHealthData $systemHealth,
    ) {}

    /**
     * @return array{
     *     totalUsers:int,
     *     activeUsers:int,
     *     recentActivities:list<array{id:string,description:string,createdAt:?string,event:string}>,
     *     pendingJobs:int,
     *     failedJobs:int,
     *     systemHealth:array{
     *         appVersion:string,
     *         phpVersion:string,
     *         laravelVersion:string,
     *         queueStatus:string,
     *         redisStatus:string,
     *         databaseStatus:string,
     *         storageStatus:string,
     *         octaneStatus:string
     *     }
     * }
     */
    public function toArray(): array
    {
        return [
            'totalUsers' => $this->totalUsers,
            'activeUsers' => $this->activeUsers,
            'recentActivities' => $this->recentActivities,
            'pendingJobs' => $this->pendingJobs,
            'failedJobs' => $this->failedJobs,
            'systemHealth' => $this->systemHealth->toArray(),
        ];
    }

    /**
     * @return array{
     *     totalUsers:int,
     *     activeUsers:int,
     *     pendingJobs:int,
     *     failedJobs:int,
     * }
     */
    public function summary(): array
    {
        return [
            'totalUsers' => $this->totalUsers,
            'activeUsers' => $this->activeUsers,
            'pendingJobs' => $this->pendingJobs,
            'failedJobs' => $this->failedJobs,
        ];
    }
}
