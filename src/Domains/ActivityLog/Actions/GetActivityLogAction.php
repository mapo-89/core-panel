<?php

declare(strict_types=1);

namespace CorePanel\Domains\ActivityLog\Actions;

use CorePanel\Domains\ActivityLog\DTOs\ActivityLogData;
use CorePanel\Support\ActivityLog\ActivityLogService;

final readonly class GetActivityLogAction
{
    public function __construct(private ActivityLogService $activityLog) {}

    public function execute(string $id): ?ActivityLogData
    {
        return $this->activityLog->find($id);
    }
}
