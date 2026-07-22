<?php

declare(strict_types=1);

namespace CorePanel\Http\Controllers\Logs;

use CorePanel\Domain\ActivityLog\Actions\ListActivityLogsAction;
use CorePanel\Models\AuthenticationLog;
use CorePanel\Support\Auth\AuthenticationLogRecorder;
use CorePanel\Support\Logs\LogFileData;
use CorePanel\Support\Logs\LogFileQuery;
use CorePanel\Support\Permissions\PermissionService;
use CorePanel\Support\Users\UserModelManager;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class LogController extends Controller
{
    public function __construct(
        private ListActivityLogsAction $listActivityLogs,
        private AuthenticationLogRecorder $authenticationLogs,
        private LogFileQuery $logFiles,
        private PermissionService $permissions,
        private UserModelManager $users,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $availableTabs = collect([
            'activity' => $this->permissions->userHas($user, 'activity-logs.view'),
            'authentication' => $this->permissions->userHas($user, 'authentication-logs.view'),
            'logs' => $this->users->isSuperAdmin($user),
        ])->filter()->keys()->values();

        abort_if($availableTabs->isEmpty(), 403);

        $requestedTab = (string) $request->query('tab', 'activity');
        $activeTab = $availableTabs->contains($requestedTab)
            ? $requestedTab
            : (string) $availableTabs->first();

        return Inertia::render('Logs/Index', [
            'activeTab' => $activeTab,
            'activityTab' => $availableTabs->contains('activity')
                ? $this->buildActivityTab($request)
                : null,
            'authenticationTab' => $availableTabs->contains('authentication')
                ? $this->buildAuthenticationTab($request)
                : null,
            'logsTab' => $availableTabs->contains('logs')
                ? $this->buildLogsTab($request)
                : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildActivityTab(Request $request): array
    {
        $activityRequest = $this->activityRequest($request);
        $logs = $this->listActivityLogs->execute($activityRequest);
        $items = collect($logs->items());

        return [
            'filters' => [
                'date_from' => $activityRequest->query('date_from'),
                'date_to' => $activityRequest->query('date_to'),
                'event' => $activityRequest->query('event'),
                'search' => trim((string) $activityRequest->query('search', '')),
                'subject_type' => $activityRequest->query('subject_type'),
                'user' => $activityRequest->query('user'),
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
                    'label' => __("activity.{$event}"),
                    'value' => $event,
                ])->all(),
                'subjectTypes' => $items->pluck('subjectType')->filter()->unique()->sort()->values()->map(fn (string $type): array => [
                    'label' => $this->activitySubjectTypeLabel($type),
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
        ];
    }

    private function activitySubjectTypeLabel(string $type): string
    {
        if ($type === $this->users->modelClass()) {
            return __('activity.models.user');
        }

        return class_basename($type);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAuthenticationTab(Request $request): array
    {
        $usesTableQuery = $this->usesTabTableQuery($request, 'authentication');
        $filters = $usesTableQuery ? $this->tableFilters($request) : [];
        $search = $usesTableQuery
            ? $this->stringFromQuery($request, 'search')
            : $this->stringFromQuery($request, 'auth_search');
        $guard = $this->stringFromFilters($filters, 'guard')
            ?? $this->nullableString($request->query('auth_guard'));
        $userId = $this->stringFromFilters($filters, 'user')
            ?? $this->nullableString($request->query('auth_user'));
        $result = $this->stringFromFilters($filters, 'result')
            ?? $this->nullableString($request->query('auth_result'));
        $dateFrom = $this->stringFromFilters($filters, 'date_from')
            ?? $this->nullableString($request->query('auth_date_from'));
        $dateTo = $this->stringFromFilters($filters, 'date_to')
            ?? $this->nullableString($request->query('auth_date_to'));

        if (! Schema::hasTable('authentication_logs')) {
            return [
                'filters' => [
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'guard' => $guard,
                    'result' => $result,
                    'search' => $search,
                    'user' => $userId,
                ],
                'logs' => [
                    'currentPage' => 1,
                    'data' => [],
                    'lastPage' => 1,
                    'perPage' => 15,
                    'total' => 0,
                ],
                'options' => [
                    'guards' => [],
                    'results' => $this->authenticationResultOptions(),
                    'users' => [],
                ],
            ];
        }

        $this->authenticationLogs->recordExpiredDatabaseSessions();

        $query = AuthenticationLog::query()->with('user');

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('login', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhere('browser', 'like', "%{$search}%")
                    ->orWhere('platform', 'like', "%{$search}%");
            });
        }

        if ($guard !== null) {
            $query->where('guard', $guard);
        }

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        if ($result === '1') {
            $result = 'successful';
        }

        if ($result === '0') {
            $result = 'failed';
        }

        match ($result) {
            'expired' => $query
                ->where('login_successful', true)
                ->where('properties->logout_reason', 'expired'),
            'failed' => $query->where('login_successful', false),
            'logout' => $query
                ->where('login_successful', true)
                ->where('properties->logout_reason', 'logout'),
            'revoked' => $query
                ->where('login_successful', true)
                ->whereIn('properties->logout_reason', ['revoked', 'revoked_other_sessions']),
            'successful' => $query
                ->where('login_successful', true)
                ->whereNull('logout_at'),
            default => null,
        };

        if ($dateFrom !== null) {
            $query->whereDate('login_at', '>=', $dateFrom);
        }

        if ($dateTo !== null) {
            $query->whereDate('login_at', '<=', $dateTo);
        }

        $sort = $this->authenticationSort($usesTableQuery
            ? $this->stringFromQuery($request, 'sort')
            : $this->stringFromQuery($request, 'auth_sort'));

        if ($sort !== '') {
            foreach (explode(',', $sort) as $sortField) {
                $direction = str_starts_with($sortField, '-') ? 'desc' : 'asc';
                $column = ltrim(trim($sortField), '-');

                if ($column === '') {
                    continue;
                }

                $query->orderBy($column, $direction);
            }
        } else {
            $query->latest('login_at');
        }

        $perPage = $usesTableQuery
            ? $this->intFromQuery($request, 'per_page', 15)
            : $this->intFromQuery($request, 'auth_per_page', 15);
        $page = $usesTableQuery
            ? ($this->intFromQuery($request, 'page', 1) ?? 1)
            : ($this->intFromQuery($request, 'auth_page', 1) ?? 1);
        $logs = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'guard' => $guard,
                'result' => $result,
                'search' => $search,
                'user' => $userId,
            ],
            'logs' => [
                'currentPage' => $logs->currentPage(),
                'data' => $logs->getCollection()
                    ->map(fn (AuthenticationLog $log): array => $this->transformAuthenticationLog($log))
                    ->all(),
                'lastPage' => $logs->lastPage(),
                'perPage' => $logs->perPage(),
                'total' => $logs->total(),
            ],
            'options' => [
                'guards' => AuthenticationLog::query()
                    ->whereNotNull('guard')
                    ->distinct()
                    ->orderBy('guard')
                    ->pluck('guard')
                    ->map(static fn (string $value): array => [
                        'label' => $value,
                        'value' => $value,
                    ])
                    ->values()
                    ->all(),
                'results' => $this->authenticationResultOptions(),
                'users' => $this->userOptions(),
            ],
        ];
    }

    /**
     * @return list<array{label:string,value:string}>
     */
    private function userOptions(): array
    {
        $model = $this->users->newModel();
        $table = $model->getTable();
        $connection = $model->getConnectionName();
        $query = $this->users->query();

        if (Schema::connection($connection)->hasColumn($table, 'first_name')) {
            $query->orderBy('first_name');
        }

        if (Schema::connection($connection)->hasColumn($table, 'last_name')) {
            $query->orderBy('last_name');
        }

        if (Schema::connection($connection)->hasColumn($table, 'email')) {
            $query->orderBy('email');
        }

        return $query
            ->get()
            ->map(function ($user): array {
                $name = $this->users->composeDisplayName(
                    $user->getAttribute('first_name'),
                    $user->getAttribute('last_name'),
                );
                $email = (string) ($user->getAttribute('email') ?? $user->getKey());

                return [
                    'label' => trim(($name !== '' ? $name : $email).' ('.$email.')'),
                    'value' => (string) $user->getKey(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLogsTab(Request $request): array
    {
        $files = $this->logFiles->all();
        $usesTableQuery = $this->usesTabTableQuery($request, 'logs');
        $filters = $usesTableQuery ? $this->tableFilters($request) : [];
        $search = $usesTableQuery
            ? $this->stringFromQuery($request, 'search')
            : $this->stringFromQuery($request, 'log_search');
        $channel = $this->stringFromFilters($filters, 'channel')
            ?? $this->nullableString($request->query('log_channel'));
        $state = $this->stringFromFilters($filters, 'state')
            ?? $this->nullableString($request->query('log_state'));
        $rawSort = $usesTableQuery
            ? $this->stringFromQuery($request, 'sort')
            : $this->stringFromQuery($request, 'log_sort');
        $direction = str_starts_with($rawSort, '-') ? 'desc' : 'asc';
        $sort = ltrim($rawSort, '-');
        if ($sort === '') {
            $sort = 'modifiedAt';
            $direction = strtolower((string) $request->query('log_direction', 'desc'));
        }
        $page = $usesTableQuery
            ? ($this->intFromQuery($request, 'page', 1) ?? 1)
            : ($this->intFromQuery($request, 'log_page', 1) ?? 1);
        $perPage = $usesTableQuery
            ? $this->intFromQuery($request, 'per_page', 12)
            : $this->intFromQuery($request, 'log_per_page', 12);

        if ($search !== '') {
            $needle = strtolower($search);
            $files = $files
                ->filter(static fn (LogFileData $file): bool => str_contains(strtolower($file->name), $needle))
                ->values();
        }

        if ($channel !== null) {
            $files = $files
                ->filter(static fn (LogFileData $file): bool => $file->channelType === $channel)
                ->values();
        }

        if ($state !== null) {
            $files = $files
                ->filter(static fn (LogFileData $file): bool => match ($state) {
                    'active' => $file->isActive,
                    'archived' => ! $file->isActive,
                    'clearable' => $file->canClear,
                    default => true,
                })
                ->values();
        }

        $files = $this->sortLogFiles($files, $sort, $direction);
        $total = $files->count();
        $pageItems = $files->slice(($page - 1) * $perPage, $perPage)->values();

        return [
            'filters' => [
                'channel' => $channel,
                'direction' => $direction,
                'search' => $search,
                'state' => $state,
                'sort' => $sort,
            ],
            'files' => [
                'currentPage' => $page,
                'data' => $pageItems->map(static fn (LogFileData $file): array => $file->toArray())->all(),
                'lastPage' => max(1, (int) ceil($total / $perPage)),
                'perPage' => $perPage,
                'total' => $total,
            ],
            'options' => [
                'channels' => [
                    ['label' => __('page-log-files.channels.daily'), 'value' => 'daily'],
                    ['label' => __('page-log-files.channels.single'), 'value' => 'single'],
                    ['label' => __('page-log-files.channels.other'), 'value' => 'other'],
                ],
                'states' => [
                    ['label' => __('page-log-files.states.active'), 'value' => 'active'],
                    ['label' => __('page-log-files.states.archived'), 'value' => 'archived'],
                    ['label' => __('page-log-files.states.clearable'), 'value' => 'clearable'],
                ],
            ],
        ];
    }

    /**
     * @return Collection<int, LogFileData>
     */
    /**
     * @param  Collection<int, LogFileData>  $files
     * @return Collection<int, LogFileData>
     */
    private function sortLogFiles(Collection $files, string $sort, string $direction): Collection
    {
        $descending = $direction === 'desc';

        return $files
            ->sortBy(
                static fn (LogFileData $file): mixed => match ($sort) {
                    'channelType' => $file->channelType,
                    'name' => $file->name,
                    'sizeBytes' => $file->sizeBytes,
                    default => $file->modifiedAt->timestamp,
                },
                SORT_REGULAR,
                $descending,
            )
            ->values();
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function activityRequest(Request $request): Request
    {
        if ($this->usesTabTableQuery($request, 'activity') && ($request->has('search') || $request->has('filter') || $request->has('page') || $request->has('per_page') || $request->has('sort') || $request->has('columns'))) {
            return Request::create(
                uri: $request->path(),
                method: 'GET',
                parameters: [
                    'date_from' => $this->stringFromFilters($this->tableFilters($request), 'date_from'),
                    'date_to' => $this->stringFromFilters($this->tableFilters($request), 'date_to'),
                    'event' => $this->stringFromFilters($this->tableFilters($request), 'event'),
                    'page' => $this->intFromQuery($request, 'page', 1),
                    'per_page' => $this->intFromQuery($request, 'per_page', 15),
                    'search' => $this->stringFromQuery($request, 'search'),
                    'sort' => $this->activitySort($this->stringFromQuery($request, 'sort')),
                    'subject_type' => $this->stringFromFilters($this->tableFilters($request), 'subject_type'),
                    'user' => $this->stringFromFilters($this->tableFilters($request), 'user'),
                ],
            );
        }

        $activityRequest = $this->prefixedRequest($request, 'activity_');
        $activityRequest->query->set('sort', $this->activitySort((string) $activityRequest->query('sort', '')));

        return $activityRequest;
    }

    private function activitySort(string $sort): string
    {
        return match ($sort) {
            '-causerName' => '-causer_id',
            '-createdAt' => '-created_at',
            '-subjectId' => '-subject_id',
            '-subjectType' => '-subject_type',
            'causerName' => 'causer_id',
            'createdAt' => 'created_at',
            'subjectId' => 'subject_id',
            'subjectType' => 'subject_type',
            default => $sort,
        };
    }

    private function authenticationSort(string $sort): string
    {
        return match ($sort) {
            '-guard' => '-guard',
            '-loginAt' => '-login_at',
            '-loginSuccessful' => '-login_successful',
            '-logoutAt' => '-logout_at',
            '-userLabel' => '-user_id',
            'guard' => 'guard',
            'loginAt' => 'login_at',
            'loginSuccessful' => 'login_successful',
            'logoutAt' => 'logout_at',
            'userLabel' => 'user_id',
            default => $sort,
        };
    }

    private function usesTabTableQuery(Request $request, string $tab): bool
    {
        return $this->nullableString($request->query('tab')) === $tab;
    }

    /**
     * @return array<string, mixed>
     */
    private function tableFilters(Request $request): array
    {
        $filters = $request->query('filter');

        return is_array($filters) ? $filters : [];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function stringFromFilters(array $filters, string $key): ?string
    {
        return $this->nullableString($filters[$key] ?? null);
    }

    private function stringFromQuery(
        Request $request,
        string $primaryKey,
        ?string $fallbackKey = null,
    ): string {
        $value = $this->nullableString($request->query($primaryKey));

        if ($value !== null) {
            return $value;
        }

        if ($fallbackKey !== null) {
            return $this->nullableString($request->query($fallbackKey)) ?? '';
        }

        return '';
    }

    private function intFromQuery(
        Request $request,
        string $key,
        ?int $default = null,
    ): ?int {
        $value = $request->query($key);

        if (is_numeric($value)) {
            return max(1, (int) $value);
        }

        return $default;
    }

    /**
     * @return array<string, mixed>
     */
    private function transformAuthenticationLog(AuthenticationLog $log): array
    {
        $user = $log->getRelationValue('user');
        $name = $user !== null
            ? $this->users->composeDisplayName(
                $user->getAttribute('first_name'),
                $user->getAttribute('last_name'),
            )
            : null;

        return [
            'authenticationResult' => $this->authenticationResult($log),
            'browser' => $log->getAttribute('browser'),
            'deviceName' => $log->getAttribute('device_name'),
            'deviceType' => $log->getAttribute('device_type'),
            'guard' => $log->getAttribute('guard'),
            'id' => (string) $log->getKey(),
            'ipAddress' => $log->getAttribute('ip_address'),
            'lastActiveAt' => $log->getAttribute('last_active_at')?->toIso8601String(),
            'login' => $log->getAttribute('login'),
            'loginAt' => $log->getAttribute('login_at')?->toIso8601String(),
            'loginSuccessful' => (bool) $log->getAttribute('login_successful'),
            'logoutAt' => $log->getAttribute('logout_at')?->toIso8601String(),
            'platform' => $log->getAttribute('platform'),
            'properties' => $properties = (array) ($log->getAttribute('properties') ?? []),
            'authMethod' => isset($properties['auth_method']) && is_string($properties['auth_method'])
                ? $properties['auth_method']
                : 'form',
            'userAvatarUrl' => $user !== null ? $this->users->avatarUrl($user) : null,
            'userEmail' => $user?->getAttribute('email'),
            'userAgent' => isset($properties['user_agent']) && is_string($properties['user_agent'])
                ? $properties['user_agent']
                : null,
            'userId' => $log->getAttribute('user_id'),
            'userName' => $name !== '' ? $name : null,
            'socialProvider' => isset($properties['social_provider']) && is_string($properties['social_provider'])
                ? $properties['social_provider']
                : null,
            'logoutReason' => isset($properties['logout_reason']) && is_string($properties['logout_reason'])
                ? $properties['logout_reason']
                : null,
        ];
    }

    private function authenticationResult(AuthenticationLog $log): string
    {
        if (! (bool) $log->getAttribute('login_successful')) {
            return 'failed';
        }

        $properties = (array) ($log->getAttribute('properties') ?? []);
        $reason = isset($properties['logout_reason']) && is_string($properties['logout_reason'])
            ? $properties['logout_reason']
            : null;

        return match ($reason) {
            'expired' => 'expired',
            'logout' => 'logout',
            'revoked', 'revoked_other_sessions' => 'revoked',
            default => 'successful',
        };
    }

    /**
     * @return list<array{label:string,value:string}>
     */
    private function authenticationResultOptions(): array
    {
        return [
            ['label' => __('page-authentication-logs.results.successful'), 'value' => 'successful'],
            ['label' => __('page-authentication-logs.results.failed'), 'value' => 'failed'],
            ['label' => __('page-authentication-logs.results.logout'), 'value' => 'logout'],
            ['label' => __('page-authentication-logs.results.revoked'), 'value' => 'revoked'],
            ['label' => __('page-authentication-logs.results.expired'), 'value' => 'expired'],
        ];
    }

    private function prefixedRequest(Request $request, string $prefix): Request
    {
        $query = [];

        foreach ($request->query() as $key => $value) {
            if (str_starts_with((string) $key, $prefix)) {
                $query[substr((string) $key, strlen($prefix))] = $value;
            }
        }

        return Request::create(
            uri: $request->path(),
            method: 'GET',
            parameters: $query,
        );
    }
}
