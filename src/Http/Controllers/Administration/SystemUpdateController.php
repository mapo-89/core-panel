<?php

declare(strict_types=1);

namespace CorePanel\Http\Controllers\Administration;

use CorePanel\Support\ActivityLog\ActivityLogService;
use CorePanel\Support\Administration\SystemUpdates\SystemUpdaterClient;
use CorePanel\Support\Permissions\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Throwable;

final class SystemUpdateController extends Controller
{
    public function __construct(
        private readonly ActivityLogService $activityLog,
        private readonly SystemUpdaterClient $updater,
        private readonly PermissionService $permissions,
    ) {}

    public function status(Request $request): JsonResponse
    {
        abort_unless($this->updater->enabled(), 404);
        abort_unless($request->user() !== null && $this->permissions->userHas($request->user(), 'system-updates.view'), 403);

        return response()->json([
            'logs' => $this->updater->safeLogs(),
            'status' => $this->updater->safeStatus(),
        ]);
    }

    public function check(Request $request): RedirectResponse
    {
        abort_unless($this->updater->enabled(), 404);
        abort_unless($request->user() !== null && $this->permissions->userHas($request->user(), 'system-updates.update'), 403);

        try {
            $result = $this->updater->check();
            $this->logActivity($request, 'system_updates.checked', $result);

            return back()->with('success', __('system_updates.check_started'));
        } catch (Throwable $throwable) {
            report($throwable);

            return back()->with('error', __('system_updates.action_failed'));
        }
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($this->updater->enabled(), 404);
        abort_unless($request->user() !== null && $this->permissions->userHas($request->user(), 'system-updates.update'), 403);

        if ($request->boolean('force') && ! (bool) config('core-panel.administration.system_updates.force_update_enabled', false)) {
            return back()->with('error', __('system_updates.force_update_disabled'));
        }

        try {
            $result = $this->updater->update();
            $this->logActivity($request, 'system_updates.updated', $result);

            return back()->with('success', __('system_updates.update_started'));
        } catch (Throwable $throwable) {
            report($throwable);

            return back()->with('error', __('system_updates.action_failed'));
        }
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function logActivity(Request $request, string $event, array $result): void
    {
        $user = $request->user();

        if ($user === null) {
            return;
        }

        $this->activityLog
            ->withCauser($user)
            ->log($user, $event, [
                'images' => data_get($result, 'images', []),
                'update_available' => data_get($result, 'update_available'),
            ]);
    }
}
