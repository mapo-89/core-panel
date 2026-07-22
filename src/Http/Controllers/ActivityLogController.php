<?php

declare(strict_types=1);

namespace CorePanel\Http\Controllers;

use CorePanel\Domain\ActivityLog\Actions\ListActivityLogsAction;
use CorePanel\Support\Users\UserModelManager;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class ActivityLogController extends Controller
{
    public function __construct(
        private readonly ListActivityLogsAction $listActivityLogs,
        private readonly UserModelManager $users,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', $this->users->modelClass());

        $logs = $this->listActivityLogs->execute($request);
        $items = collect($logs->items());

        return Inertia::render('Activity/Index', [
            'filters' => [
                'date_from' => $request->query('date_from'),
                'date_to' => $request->query('date_to'),
                'event' => $request->query('event'),
                'search' => trim((string) $request->query('search', '')),
                'subject_type' => $request->query('subject_type'),
                'user' => $request->query('user'),
            ],
            'logs' => [
                'currentPage' => $logs->currentPage(),
                'data' => $items->all(),
                'lastPage' => $logs->lastPage(),
                'perPage' => $logs->perPage(),
                'total' => $logs->total(),
            ],
            'options' => [
                'events' => $items->pluck('event')->filter()->unique()->sort()->values()->map(static fn (string $event): array => [
                    'label' => __("core-panel::activity.{$event}"),
                    'value' => $event,
                ])->all(),
                'subjectTypes' => $items->pluck('subjectType')->filter()->unique()->sort()->values()->map(static fn (string $type): array => [
                    'label' => class_basename($type),
                    'value' => $type,
                ])->all(),
                'users' => $items->filter(static fn (array $entry): bool => filled($entry['causerId'] ?? null))
                    ->unique('causerId')
                    ->values()
                    ->map(static fn (array $entry): array => [
                        'label' => (string) ($entry['causerName'] ?? $entry['causerId']),
                        'value' => (string) $entry['causerId'],
                    ])->all(),
            ],
        ]);
    }
}
