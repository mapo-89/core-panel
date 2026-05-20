<?php

declare(strict_types=1);

namespace CorePanel\Domains\ActivityLog\Actions;

use CorePanel\Support\ActivityLog\ActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

final readonly class ListActivityLogsAction
{
    public function __construct(private ActivityLogService $activityLog) {}

    public function execute(Request $request): LengthAwarePaginator
    {
        return $this->activityLog->list($request);
    }
}
