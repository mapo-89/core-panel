<?php

declare(strict_types=1);

namespace CorePanel\Support\Administration\DatabaseBackups;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

final class DatabaseBackupRestoreStatus
{
    /**
     * @param  list<string>  $tables
     */
    public function start(string $backup, string $mode, array $tables): string
    {
        $restoreId = (string) Str::uuid();

        $this->put($restoreId, [
            'backup' => $backup,
            'finished_at' => null,
            'message' => null,
            'message_key' => null,
            'mode' => $mode,
            'started_at' => now()->toIso8601String(),
            'status' => 'running',
            'tables' => $tables,
        ]);

        return $restoreId;
    }

    public function complete(string $restoreId): void
    {
        $this->update($restoreId, [
            'finished_at' => now()->toIso8601String(),
            'message' => __('database_backups.restored'),
            'message_key' => 'database_backups.restored',
            'status' => 'completed',
        ]);
    }

    public function fail(string $restoreId, Throwable|string $error): void
    {
        $message = $error instanceof Throwable ? $error->getMessage() : $error;

        $this->update($restoreId, [
            'finished_at' => now()->toIso8601String(),
            'message' => $message !== '' ? $message : __('database_backups.restore_failed'),
            'message_key' => null,
            'status' => 'failed',
        ]);
    }

    /**
     * @return array{backup:string|null, finished_at:string|null, message:string|null, message_key:string|null, mode:string|null, started_at:string|null, status:string, tables:list<string>}
     */
    public function get(string $restoreId): array
    {
        $status = $this->cache()->get($this->key($restoreId));

        if (! is_array($status)) {
            return [
                'backup' => null,
                'finished_at' => null,
                'message' => __('database_backups.restore_status_unknown'),
                'message_key' => 'database_backups.restore_status_unknown',
                'mode' => null,
                'started_at' => null,
                'status' => 'unknown',
                'tables' => [],
            ];
        }

        return [
            'backup' => is_string($status['backup'] ?? null) ? $status['backup'] : null,
            'finished_at' => is_string($status['finished_at'] ?? null) ? $status['finished_at'] : null,
            'message' => is_string($status['message'] ?? null) ? $status['message'] : null,
            'message_key' => is_string($status['message_key'] ?? null) ? $status['message_key'] : null,
            'mode' => is_string($status['mode'] ?? null) ? $status['mode'] : null,
            'started_at' => is_string($status['started_at'] ?? null) ? $status['started_at'] : null,
            'status' => is_string($status['status'] ?? null) ? $status['status'] : 'unknown',
            'tables' => array_values(array_filter((array) ($status['tables'] ?? []), static fn (mixed $table): bool => is_string($table))),
        ];
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function update(string $restoreId, array $changes): void
    {
        $this->put($restoreId, [
            ...$this->get($restoreId),
            ...$changes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function put(string $restoreId, array $status): void
    {
        $this->cache()->put($this->key($restoreId), $status, now()->addHours(12));
    }

    private function key(string $restoreId): string
    {
        return "core-panel:database-backup-restore-status:{$restoreId}";
    }

    private function cache(): Repository
    {
        return Cache::store((string) config('core-panel.administration.database_backups.restore_status_store', config('cache.default')));
    }
}
