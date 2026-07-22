<?php

declare(strict_types=1);

namespace CorePanel\Domain\Dashboard\Actions;

use CorePanel\Domain\Dashboard\DTOs\SystemHealthData;
use CorePanel\Support\Version\AppVersionRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Throwable;

final readonly class GetSystemHealthAction
{
    public function __construct(
        private ConfigRepository $config,
        private DatabaseManager $database,
        private AppVersionRepository $versions,
    ) {}

    public function execute(): SystemHealthData
    {
        return new SystemHealthData(
            appVersion: $this->versions->displayVersion() ?? (string) $this->config->get('app.version', 'dev'),
            phpVersion: PHP_VERSION,
            laravelVersion: app()->version(),
            queueStatus: $this->resolveQueueStatus(),
            redisStatus: $this->resolveRedisStatus(),
            databaseStatus: $this->resolveDatabaseStatus(),
            storageStatus: $this->resolveStorageStatus(),
            octaneStatus: $this->resolveOctaneStatus(),
        );
    }

    private function resolveDatabaseStatus(): string
    {
        try {
            $this->database->connection()->getPdo();

            return 'ok';
        } catch (Throwable) {
            return 'offline';
        }
    }

    private function resolveOctaneStatus(): string
    {
        $server = (string) $this->config->get('octane.server', '');

        if ($server === '') {
            return 'disabled';
        }

        return 'enabled';
    }

    private function resolveQueueStatus(): string
    {
        $driver = (string) $this->config->get('queue.default', '');

        if ($driver === '') {
            return 'offline';
        }

        return $driver === 'sync' ? 'degraded' : 'ok';
    }

    private function resolveRedisStatus(): string
    {
        try {
            $response = Redis::connection()->client()->ping();

            return filled($response) ? 'ok' : 'offline';
        } catch (Throwable) {
            return 'offline';
        }
    }

    private function resolveStorageStatus(): string
    {
        try {
            $path = Storage::disk((string) $this->config->get('filesystems.default', 'local'))->path('/');

            return is_writable($path) ? 'ok' : 'degraded';
        } catch (Throwable) {
            return is_writable(storage_path()) ? 'ok' : 'degraded';
        }
    }
}
