<?php

declare(strict_types=1);

namespace CorePanel\Domain\ActivityLog\Actions;

use CorePanel\Domain\ActivityLog\DTOs\ActivityLogData;
use CorePanel\Support\ActivityLog\ActivityLogService;

final readonly class GetActivityLogAction
{
    public function __construct(private ActivityLogService $activityLog) {}

    public function execute(string $id): ?ActivityLogData
    {
        return $this->activityLog->find($id);
    }
}
