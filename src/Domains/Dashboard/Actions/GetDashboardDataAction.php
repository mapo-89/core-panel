<?php

declare(strict_types=1);

namespace CorePanel\Domains\Dashboard\Actions;

use CorePanel\Domains\Dashboard\DTOs\DashboardData;
use CorePanel\Domains\Dashboard\DTOs\SystemHealthData;
use CorePanel\Support\Users\UserModelManager;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Throwable;

final readonly class GetDashboardDataAction
{
    public function __construct(
        private DatabaseManager $database,
        private GetSystemHealthAction $health,
        private UserModelManager $users,
    ) {}

    public function execute(): DashboardData
    {
        return new DashboardData(
            totalUsers: $this->totalUsers(),
            activeUsers: $this->activeUsers(),
            recentActivities: $this->recentActivities(),
            pendingJobs: $this->pendingJobs(),
            failedJobs: $this->failedJobs(),
            systemHealth: $this->health->execute(),
        );
    }

    /**
     * @return list<array{id:string,description:string,createdAt:?string,event:string}>
     */
    public function recentActivities(): array
    {
        $connection = $this->dashboardConnectionName();

        if (! $this->hasTable($connection, 'activity_log')) {
            return [];
        }

        $query = $this->database->connection($connection)
            ->table('activity_log')
            ->select(['id', 'description', 'event', 'created_at'])
            ->orderByDesc('created_at')
            ->limit(10);

        return $query->get()
            ->map(static fn (object $activity): array => [
                'id' => (string) $activity->id,
                'description' => (string) $activity->description,
                'createdAt' => $activity->created_at !== null
                    ? Carbon::parse((string) $activity->created_at)->toIso8601String()
                    : null,
                'event' => (string) ($activity->event ?? 'updated'),
            ])
            ->values()
            ->all();
    }

    public function systemHealth(): SystemHealthData
    {
        return $this->health->execute();
    }

    private function activeUsers(): int
    {
        try {
            $query = $this->userQuery();

            if ($this->users->hasColumn('status')) {
                return (int) $query->where('status', 'active')->count();
            }

            return (int) $query->count();
        } catch (Throwable) {
            return 0;
        }
    }

    private function dashboardConnectionName(): string
    {
        return (string) config('database.default');
    }

    private function failedJobs(): int
    {
        $connection = $this->dashboardConnectionName();

        if (! $this->hasTable($connection, 'failed_jobs')) {
            return 0;
        }

        return (int) $this->database->connection($connection)->table('failed_jobs')->count();
    }

    private function hasTable(string $connection, string $table): bool
    {
        try {
            return Schema::connection($connection)->hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }

    private function pendingJobs(): int
    {
        $connection = $this->dashboardConnectionName();

        if (! $this->hasTable($connection, 'jobs')) {
            return 0;
        }

        return (int) $this->database->connection($connection)->table('jobs')->count();
    }

    private function totalUsers(): int
    {
        try {
            return (int) $this->userQuery()->count();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * @return Builder<Model>
     */
    private function userQuery(): Builder
    {
        return $this->users->visibleQuery();
    }
}
