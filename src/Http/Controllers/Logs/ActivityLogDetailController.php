<?php

declare(strict_types=1);

namespace CorePanel\Http\Controllers\Logs;

use CorePanel\Domains\ActivityLog\Actions\GetActivityLogAction;
use CorePanel\Support\Api\Concerns\RespondsWithApi;
use CorePanel\Support\Users\UserModelManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class ActivityLogDetailController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly GetActivityLogAction $getActivityLog,
        private readonly UserModelManager $users,
    ) {}

    public function show(Request $request, string $activity): JsonResponse
    {
        Gate::authorize('viewAny', $this->users->modelClass());

        $entry = $this->getActivityLog->execute($activity);
        abort_if($entry === null, 404);

        return $this->success($entry->toArray());
    }
}
