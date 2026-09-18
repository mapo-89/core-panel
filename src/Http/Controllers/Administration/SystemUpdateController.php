<?php

declare(strict_types=1);

namespace CorePanel\Http\Controllers\Administration;

use CorePanel\Domain\SystemUpdate\Actions\UpdateAutomaticSystemUpdateSettingsAction;
use CorePanel\Domain\SystemUpdate\DTOs\AutomaticSystemUpdateSettingsData;
use CorePanel\Http\Requests\UpdateAutomaticSystemUpdateSettingsRequest;
use CorePanel\Jobs\RunSystemUpdate;
use CorePanel\Support\ActivityLog\ActivityLogService;
use CorePanel\Support\Administration\SystemUpdates\ApplicationHealthUrl;
use CorePanel\Support\Administration\SystemUpdates\SystemUpdateJobStatus;
use CorePanel\Support\Administration\SystemUpdates\SystemUpdaterClient;
use CorePanel\Support\Permissions\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Throwable;

final class SystemUpdateController extends Controller
{
    public function __construct(
        private readonly ActivityLogService $activityLog,
        private readonly ApplicationHealthUrl $healthUrl,
        private readonly SystemUpdateJobStatus $jobStatus,
        private readonly SystemUpdaterClient $updater,
        private readonly PermissionService $permissions,
    ) {}

    public function status(Request $request): JsonResponse
    {
        abort_unless($this->updater->enabled(), 404);
        abort_unless($request->user() !== null && $this->permissions->userHas($request->user(), 'system-updates.view'), 403);

        $attemptId = $request->string('attempt_id')->trim()->toString();
        $attemptId = Str::isUuid($attemptId) ? $attemptId : null;
        $reportFailures = $attemptId === null;

        return response()->json([
            'logs' => $this->updater->safeLogs(reportFailures: $reportFailures),
            'status' => $this->updater->safeStatus($attemptId, reportFailures: $reportFailures),
        ]);
    }

    public function check(Request $request): RedirectResponse
    {
        abort_unless($this->updater->enabled(), 404);
        abort_unless($request->user() !== null && $this->permissions->userHas($request->user(), 'system-updates.update'), 403);

        try {
            $result = $this->updater->check();
            $this->logActivity($request, 'system_updates.checked', $result);

            return back()->with('info', __('system_updates.check_completed'));
        } catch (Throwable $throwable) {
            report($throwable);

            return back()->with('error', __('system_updates.action_failed'));
        }
    }

    public function update(Request $request): JsonResponse|RedirectResponse
    {
        abort_unless($this->updater->enabled(), 404);
        abort_unless($request->user() !== null && $this->permissions->userHas($request->user(), 'system-updates.update'), 403);

        $validated = $request->validate([
            'attempt_id' => ['nullable', 'uuid'],
        ]);

        if ($request->boolean('force') && ! $this->updater->forceUpdateEnabled()) {
            return $request->expectsJson()
                ? response()->json(['message' => __('system_updates.force_update_disabled')], 422)
                : back()->with('error', __('system_updates.force_update_disabled'));
        }

        $user = $request->user();

        $restartDelaySeconds = max(1, (int) config('core-panel.administration.system_updates.restart_delay_seconds', 3));

        $attemptId = $this->jobStatus->begin(
            is_string($validated['attempt_id'] ?? null) ? $validated['attempt_id'] : null,
        );
        RunSystemUpdate::dispatch($user, $restartDelaySeconds, $attemptId)
            ->onConnection('sync')
            ->afterResponse();

        if (! $request->expectsJson()) {
            return back()->with('success', __('system_updates.update_started'));
        }

        return response()->json([
            'accepted' => true,
            'attempt_id' => $attemptId,
            'health_url' => $this->healthUrl->resolve(),
            'message' => __('system_updates.update_started'),
        ], 202);
    }

    public function updateSettings(
        UpdateAutomaticSystemUpdateSettingsRequest $request,
        UpdateAutomaticSystemUpdateSettingsAction $action,
    ): RedirectResponse {
        abort_unless($this->updater->enabled(), 404);

        $data = AutomaticSystemUpdateSettingsData::fromArray($request->validated());
        $action->execute($data);

        $user = $request->user();

        if ($user !== null) {
            $this->activityLog
                ->withCauser($user)
                ->log($user, 'system_updates.settings_updated', [
                    'automatic_enabled' => $data->enabled,
                    'interval' => $data->interval->value,
                    'maintenance_window_enabled' => $data->maintenanceWindowEnabled,
                    'mode' => $data->mode->value,
                    'time' => $data->time,
                    'weekday' => $data->weekday?->value,
                    'window_end' => $data->windowEnd,
                    'window_start' => $data->windowStart,
                ]);
        }

        return back()->with('success', __('system_updates.settings_saved'));
    }

    /** @param array<string, mixed> $result */
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
