<?php

declare(strict_types=1);

namespace CorePanel\Jobs;

use CorePanel\Support\ActivityLog\ActivityLogService;
use CorePanel\Support\Administration\SystemUpdates\SystemUpdateJobStatus;
use CorePanel\Support\Administration\SystemUpdates\SystemUpdaterClient;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Sleep;
use Throwable;

final class RunSystemUpdate implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Model $user,
        public readonly int $restartDelaySeconds = 3,
        public readonly string $attemptId = '',
    ) {}

    public function handle(
        ActivityLogService $activityLog,
        SystemUpdateJobStatus $jobStatus,
        SystemUpdaterClient $updater,
    ): void {
        Sleep::sleep(max(0, $this->restartDelaySeconds));

        $attemptId = $jobStatus->begin($this->attemptId);
        $result = $updater->update($attemptId);
        $jobStatus->accept($attemptId);

        if (! $this->user instanceof Authenticatable) {
            return;
        }

        try {
            $activityLog
                ->withCauser($this->user)
                ->log($this->user, 'system_updates.updated', [
                    'images' => data_get($result, 'images', []),
                    'update_available' => data_get($result, 'update_available'),
                ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function failed(?Throwable $exception): void
    {
        if ($exception instanceof RequestException && $exception->response->status() === 409) {
            app(SystemUpdateJobStatus::class)->reject($this->attemptId);

            return;
        }

        app(SystemUpdateJobStatus::class)->fail($this->attemptId);
    }
}
