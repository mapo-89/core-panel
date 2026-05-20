<?php

declare(strict_types=1);

namespace CorePanel\Support\ActivityLog;

use CorePanel\Domains\ActivityLog\DTOs\ActivityLogData;
use CorePanel\Support\Query\AllowedQuery;
use CorePanel\Support\Query\QueryBuilderAdapter;
use CorePanel\Support\RequiresPackage;
use CorePanel\Support\Users\UserModelManager;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as PaginationLengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Spatie\QueryBuilder\QueryBuilder;

class ActivityLogService
{
    use RequiresPackage;

    private const FAKE_STORE_CLASS = 'CorePanel\\Tests\\Fakes\\ActivityLogStore';

    private ?Authenticatable $causer = null;

    public function __construct(private UserModelManager $users) {}

    public function withCauser(?Authenticatable $user): self
    {
        $clone = clone $this;
        $clone->causer = $user;

        return $clone;
    }

    public function cleanup(int $days): int
    {
        $cutoff = now()->subDays($days);
        if ($this->usesFakeStore()) {
            $entries = $this->fakeEntries();
            $before = count($entries);
            $entries = array_values(array_filter(
                $entries,
                static fn (array $entry): bool => ! Carbon::parse((string) ($entry['created_at'] ?? now()))->lt($cutoff),
            ));
            $this->replaceFakeEntries($entries);

            return $before - count($entries);
        }

        $this->requirePackage(
            Activity::class,
            'spatie/laravel-activitylog'
        );

        $query = Activity::query()->where('created_at', '<', $cutoff);

        return $query->delete();
    }

    public function find(string $id): ?ActivityLogData
    {
        if ($this->usesFakeStore()) {
            /** @var array<string, mixed>|null $entry */
            $entry = collect($this->fakeEntries())->firstWhere('id', $id);

            if (! is_array($entry)) {
                return null;
            }

            return ActivityLogData::fromArray($entry);
        }

        $this->requirePackage(
            Activity::class,
            'spatie/laravel-activitylog'
        );

        $query = Activity::query()->with(['causer', 'subject']);

        $activity = $query->find($id);

        return $activity instanceof Activity ? ActivityLogData::fromModel($activity) : null;
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function list(Request $request): LengthAwarePaginator
    {
        if ($this->usesFakeStore()) {
            return $this->listFromFakeStore($request);
        }

        $query = $this->query($request);
        $perPage = max(1, (int) $request->integer('per_page', 15));
        $paginator = $query->paginate(min($perPage, 100))->appends($request->query());

        return new PaginationLengthAwarePaginator(
            collect($paginator->items())
                ->map(fn (Activity $activity): array => $this->transformActivity($activity))
                ->all(),
            $paginator->total(),
            $paginator->perPage(),
            $paginator->currentPage(),
            [
                'pageName' => $paginator->getPageName(),
                'path' => $paginator->path(),
            ],
        );
    }

    /**
     * @return QueryBuilder<Activity>
     */
    public function query(Request $request): QueryBuilder
    {
        $this->requirePackage(
            Activity::class,
            'spatie/laravel-activitylog'
        );

        $query = Activity::query();
        $table = $query->getModel()->getTable();

        $query->with(['causer', 'subject']);

        $this->applyFilters($query, $request, $table);

        return (new QueryBuilderAdapter)
            ->allowed(AllowedQuery::make()
                ->filters(['description', 'event', 'log_name', 'subject_type'])
                ->includes(['causer', 'subject'])
                ->sorts(['causer_id', 'created_at', 'event', 'subject_id', 'subject_type'])
                ->globalSearch(['description', 'event', 'subject_type'])
                ->defaultSort('-created_at'))
            ->for($query, $request);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public function log(Model $subject, string $event, array $properties = []): void
    {
        if ($this->usesFakeStore()) {
            $causer = $this->causer;
            $causerId = $causer?->getAuthIdentifier();
            $causerName = null;

            if ($causer instanceof Model) {
                $causerName = (string) ($causer->getAttribute('name') ?? $causer->getAttribute('email') ?? $causerId);
            }

            $entries = $this->fakeEntries();
            $entries[] = [
                'id' => (string) Str::uuid(),
                'subject' => $subject,
                'subject_id' => $subject->getKey() !== null ? (string) $subject->getKey() : null,
                'subject_label' => (string) ($subject->getAttribute('name') ?? $subject->getAttribute('title') ?? $subject->getKey()),
                'subject_type' => $subject::class,
                'event' => $event,
                'description' => $event,
                'causer' => $causer,
                'causer_id' => $causerId !== null ? (string) $causerId : null,
                'causer_avatar_url' => $causer instanceof Model ? $this->resolveUserAvatarUrl($causer) : null,
                'causer_name' => $causerName,
                'created_at' => now()->toDateTimeString(),
                'log_name' => 'default',
                'changes' => (array) ($properties['changes'] ?? []),
                'properties' => $properties,
            ];
            $this->replaceFakeEntries($entries);

            return;
        }

        $this->requirePackage(
            Activity::class,
            'spatie/laravel-activitylog'
        );

        $activity = activity()
            ->performedOn($subject)
            ->event($event)
            ->withProperties($properties);

        if ($this->causer instanceof Model) {
            $activity->causedBy($this->causer);
        }

        $activity->log($event);
    }

    /**
     * @param  Builder<Activity>  $query
     */
    private function applyFilters(Builder $query, Request $request, string $table): void
    {
        $causer = $request->query('user');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $event = $request->query('event');
        $subjectType = $request->query('subject_type');

        if (is_scalar($causer) && (string) $causer !== '') {
            $query->where('causer_id', (string) $causer);
        }

        if (is_scalar($event) && (string) $event !== '') {
            $query->where('event', (string) $event);
        }

        if (is_scalar($subjectType) && (string) $subjectType !== '') {
            $query->where('subject_type', (string) $subjectType);
        }

        if (is_scalar($dateFrom) && (string) $dateFrom !== '') {
            $query->whereDate('created_at', '>=', (string) $dateFrom);
        }

        if (is_scalar($dateTo) && (string) $dateTo !== '') {
            $query->whereDate('created_at', '<=', (string) $dateTo);
        }
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function listFromFakeStore(Request $request): LengthAwarePaginator
    {
        $records = collect($this->fakeEntries())
            ->filter(function (array $entry) use ($request): bool {
                if (($user = $request->query('user')) !== null && (string) $user !== '' && (string) data_get($entry, 'causer_id') !== (string) $user) {
                    return false;
                }

                if (($event = $request->query('event')) !== null && (string) $event !== '' && (string) data_get($entry, 'event') !== (string) $event) {
                    return false;
                }

                if (($subjectType = $request->query('subject_type')) !== null && (string) $subjectType !== '' && (string) data_get($entry, 'subject_type') !== (string) $subjectType) {
                    return false;
                }

                if (($dateFrom = $request->query('date_from')) !== null && (string) $dateFrom !== '' && Carbon::parse((string) data_get($entry, 'created_at'))->lt(Carbon::parse((string) $dateFrom)->startOfDay())) {
                    return false;
                }

                if (($dateTo = $request->query('date_to')) !== null && (string) $dateTo !== '' && Carbon::parse((string) data_get($entry, 'created_at'))->gt(Carbon::parse((string) $dateTo)->endOfDay())) {
                    return false;
                }

                return true;
            })
            ->when(trim((string) $request->query('search', '')) !== '', function (Collection $items) use ($request): Collection {
                $needle = Str::lower(trim((string) $request->query('search')));

                return $items->filter(function (array $entry) use ($needle): bool {
                    return str_contains(Str::lower((string) data_get($entry, 'description', '')), $needle)
                        || str_contains(Str::lower((string) data_get($entry, 'event', '')), $needle)
                        || str_contains(Str::lower((string) data_get($entry, 'subject_type', '')), $needle);
                });
            })
            ->values();

        $sort = (string) $request->query('sort', '-created_at');
        $descending = str_starts_with($sort, '-');
        $sortKey = ltrim($sort, '-');
        $records = $records->sortBy(
            static fn (array $entry): mixed => match ($sortKey) {
                'causer_id' => data_get($entry, 'causer_id'),
                'event' => data_get($entry, 'event'),
                'subject_id' => data_get($entry, 'subject_id'),
                'subject_type' => data_get($entry, 'subject_type'),
                default => data_get($entry, 'created_at'),
            },
            SORT_REGULAR,
            $descending,
        )->values();

        $page = max(1, (int) $request->integer('page', 1));
        $perPage = min(max(1, (int) $request->integer('per_page', 15)), 100);
        $slice = $records->forPage($page, $perPage)->values();

        return new PaginationLengthAwarePaginator(
            $slice->map(fn (array $entry): array => $this->transformFakeActivity($entry))->all(),
            $records->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'pageName' => 'page']
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function transformActivity(Activity $activity): array
    {
        $data = ActivityLogData::fromModel($activity)->toArray();
        $causer = $activity->causer;

        $data['causerAvatarUrl'] = $causer instanceof Model
            ? $this->resolveUserAvatarUrl($causer)
            : null;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function transformFakeActivity(array $entry): array
    {
        $data = ActivityLogData::fromArray($entry)->toArray();
        $causer = $entry['causer'] ?? null;

        $data['causerAvatarUrl'] = $causer instanceof Model
            ? $this->resolveUserAvatarUrl($causer)
            : ($data['causerAvatarUrl'] ?? null);

        return $data;
    }

    private function resolveUserAvatarUrl(Model $model): ?string
    {
        return $model::class === $this->users->modelClass()
            ? $this->users->avatarUrl($model)
            : null;
    }

    private function usesFakeStore(): bool
    {
        return class_exists(self::FAKE_STORE_CLASS);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fakeEntries(): array
    {
        if (! $this->usesFakeStore()) {
            return [];
        }

        $storeClass = self::FAKE_STORE_CLASS;

        if (! class_exists($storeClass)) {
            return [];
        }

        /** @var mixed $entries */
        $entries = $storeClass::$entries;

        return is_array($entries) ? array_values($entries) : [];
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     */
    private function replaceFakeEntries(array $entries): void
    {
        if (! $this->usesFakeStore()) {
            return;
        }

        $storeClass = self::FAKE_STORE_CLASS;

        if (! class_exists($storeClass)) {
            return;
        }

        $storeClass::$entries = $entries;
    }
}
