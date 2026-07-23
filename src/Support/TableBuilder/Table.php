<?php

declare(strict_types=1);

namespace CorePanel\Support\TableBuilder;

use CorePanel\Support\Query\QueryBuilderAdapter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * @phpstan-type TablePayload array<string, mixed>
 * @phpstan-type TableFilters array<string, mixed>
 * @phpstan-type TablePagination array{
 *     page: int,
 *     perPage: int,
 *     total: int,
 *     lastPage: int,
 *     from: int|null,
 *     to: int|null
 * }
 * @phpstan-type TableState array{
 *     filters: TableFilters,
 *     search: string,
 *     sort: string,
 *     visibleColumns: list<string>
 * }
 */
final class Table
{
    /**
     * @var list<Action>
     */
    private array $actions = [];

    /**
     * @var list<Column>
     */
    private array $columns = [];

    /**
     * @var list<Filter>
     */
    private array $filters = [];

    private int $perPage = 15;

    /**
     * @var Builder<Model>|Collection<int, mixed>|list<mixed>|null
     */
    private Builder|Collection|array|null $query = null;

    private ?Request $request = null;

    private ?QueryBuilderAdapter $queryBuilderAdapter = null;

    public static function make(): self
    {
        return new self;
    }

    /**
     * @param  list<Action>  $actions
     */
    public function actions(array $actions): self
    {
        $this->actions = $actions;

        return $this;
    }

    /**
     * @param  list<Column>  $columns
     */
    public function columns(array $columns): self
    {
        $this->columns = $columns;

        return $this;
    }

    /**
     * Retain the legacy fluent export extension point for host table definitions.
     *
     * Export execution is handled by the consuming application; the table result itself
     * does not expose an export pipeline.
     */
    public function exportUsing(callable $hook): self
    {
        unset($hook);

        return $this;
    }

    /**
     * @param  list<Filter>  $filters
     */
    public function filters(array $filters): self
    {
        $this->filters = $filters;

        return $this;
    }

    public function perPage(int $perPage): self
    {
        $this->perPage = max(1, $perPage);

        return $this;
    }

    /**
     * @param  Builder<Model>|Collection<int, mixed>|list<mixed>  $query
     */
    public function query(Builder|Collection|array $query): self
    {
        $this->query = $query;

        return $this;
    }

    public function queryBuilderAdapter(QueryBuilderAdapter $adapter): self
    {
        $this->queryBuilderAdapter = $adapter;

        return $this;
    }

    public function request(?Request $request = null): self
    {
        $this->request = $request;

        return $this;
    }

    public function result(): TableResult
    {
        if ($this->query === null) {
            throw new InvalidArgumentException('A table query source must be configured before building a result.');
        }

        $request = $this->request ?? request();
        $page = max(1, (int) $request->integer('page', 1));
        $perPage = max(1, (int) $request->integer('per_page', $this->perPage));
        $search = trim((string) $request->query('search', ''));
        $visibleColumns = $this->visibleColumns($request);
        $filterState = $this->normalizeFilters($request);

        $this->validateAllowLists($request);

        if ($this->query instanceof Builder) {
            return $this->resultFromBuilder(
                $this->query,
                $request,
                $page,
                $perPage,
                $search,
                $visibleColumns,
                $filterState,
            );
        }

        $items = $this->query instanceof Collection
            ? $this->query
            : collect($this->query);

        return $this->resultFromCollection(
            $items,
            $request,
            $page,
            $perPage,
            $search,
            $visibleColumns,
            $filterState,
        );
    }

    /**
     * @return list<TablePayload>
     */
    private function actionPayloads(bool $bulk): array
    {
        return array_values(array_map(
            static fn (Action $action): array => $action->toArray(),
            array_filter($this->actions, static fn (Action $action): bool => $action->isBulk() === $bulk),
        ));
    }

    /**
     * @param  Builder<Model>  $builder
     * @return Builder<Model>
     */
    private function applySearchToBuilder(Builder $builder, string $search): Builder
    {
        if ($search === '') {
            return $builder;
        }

        $searchableColumns = array_values(array_filter(
            $this->columns,
            static fn (Column $column): bool => $column->isSearchable()
        ));

        if ($searchableColumns === []) {
            return $builder;
        }

        return $builder->where(function (Builder $query) use ($search, $searchableColumns): void {
            foreach ($searchableColumns as $column) {
                $query->orWhere($column->key(), 'like', '%'.$search.'%');
            }
        });
    }

    /**
     * @param  Collection<int, mixed>  $items
     * @return Collection<int, mixed>
     */
    private function applySearchToCollection(Collection $items, string $search): Collection
    {
        if ($search === '') {
            return $items;
        }

        $searchableColumns = array_values(array_filter(
            $this->columns,
            static fn (Column $column): bool => $column->isSearchable()
        ));

        if ($searchableColumns === []) {
            return $items;
        }

        $needle = mb_strtolower($search);

        return $items->filter(static function (mixed $row) use ($needle, $searchableColumns): bool {
            foreach ($searchableColumns as $column) {
                $value = data_get($row, $column->key());

                if (str_contains(mb_strtolower((string) $value), $needle)) {
                    return true;
                }
            }

            return false;
        })->values();
    }

    /**
     * @param  Builder<Model>  $builder
     * @return Builder<Model>
     */
    private function applySortToBuilder(Builder $builder, Request $request): Builder
    {
        $sort = (string) $request->query('sort', '');

        if ($sort === '') {
            return $builder;
        }

        foreach (array_filter(explode(',', $sort)) as $sortKey) {
            $direction = str_starts_with($sortKey, '-') ? 'desc' : 'asc';
            $columnKey = ltrim(trim($sortKey), '-');

            if (! $this->isSortableColumn($columnKey)) {
                continue;
            }

            $builder->orderBy($columnKey, $direction);
        }

        return $builder;
    }

    /**
     * @param  Collection<int, mixed>  $items
     * @return Collection<int, mixed>
     */
    private function applySortToCollection(Collection $items, Request $request): Collection
    {
        $sort = (string) $request->query('sort', '');

        if ($sort === '') {
            return $items->values();
        }

        $sortKeys = array_filter(explode(',', $sort));
        $sorted = $items;

        foreach (array_reverse($sortKeys) as $sortKey) {
            $descending = str_starts_with($sortKey, '-');
            $columnKey = ltrim(trim($sortKey), '-');

            if (! $this->isSortableColumn($columnKey)) {
                continue;
            }

            $sorted = $sorted->sortBy(
                static fn (mixed $row): mixed => data_get($row, $columnKey),
                options: SORT_NATURAL,
                descending: $descending,
            );
        }

        return $sorted->values();
    }

    /**
     * @param  list<TablePayload>  $payloads
     * @param  list<string>  $visibleColumns
     * @return list<TablePayload>
     */
    private function applyVisibility(array $payloads, array $visibleColumns): array
    {
        if ($visibleColumns === []) {
            return $payloads;
        }

        return array_values(array_filter(
            $payloads,
            static fn (array $column): bool => in_array($column['key'], $visibleColumns, true)
        ));
    }

    /**
     * @param  Builder<Model>  $builder
     * @param  TableFilters  $filters
     * @return Builder<Model>
     */
    private function applyFiltersToBuilder(Builder $builder, array $filters): Builder
    {
        foreach ($this->filters as $filter) {
            $value = $filters[$filter->key()] ?? null;
            $builder = $filter->applyToBuilder($builder, $value);
        }

        return $builder;
    }

    /**
     * @param  Collection<int, mixed>  $items
     * @param  TableFilters  $filters
     * @return Collection<int, mixed>
     */
    private function applyFiltersToCollection(Collection $items, array $filters): Collection
    {
        foreach ($this->filters as $filter) {
            $value = $filters[$filter->key()] ?? null;
            $items = $filter->applyToCollection($items, $value);
        }

        return $items->values();
    }

    /**
     * @param  list<string>  $visibleColumns
     * @return list<TablePayload>
     */
    private function columnPayloads(array $visibleColumns): array
    {
        return $this->applyVisibility(
            array_map(static fn (Column $column): array => $column->toArray(), $this->columns),
            $visibleColumns,
        );
    }

    /**
     * @return list<TablePayload>
     */
    private function filterPayloads(): array
    {
        return array_map(static fn (Filter $filter): array => $filter->toArray(), $this->filters);
    }

    private function isSortableColumn(string $columnKey): bool
    {
        foreach ($this->columns as $column) {
            if ($column->key() === $columnKey && $column->isSortable()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return TableFilters
     */
    private function normalizeFilters(Request $request): array
    {
        $filters = $request->query('filter', []);

        if (! is_array($filters)) {
            return [];
        }

        return $filters;
    }

    /**
     * @param  list<mixed>  $items
     * @param  list<string>  $visibleColumns
     * @return list<TablePayload>
     */
    private function resolveRows(array $items, array $visibleColumns): array
    {
        return array_map(function (mixed $row) use ($visibleColumns): array {
            $payload = [];

            foreach ($this->columns as $column) {
                if ($visibleColumns !== [] && ! in_array($column->key(), $visibleColumns, true)) {
                    continue;
                }

                $payload[$column->key()] = $column->resolveValue($row);
            }

            return $payload;
        }, $items);
    }

    /**
     * @param  Builder<Model>  $builder
     * @param  list<string>  $visibleColumns
     * @param  TableFilters  $filters
     */
    private function resultFromBuilder(
        Builder $builder,
        Request $request,
        int $page,
        int $perPage,
        string $search,
        array $visibleColumns,
        array $filters,
    ): TableResult {
        $query = $this->applySearchToBuilder($builder, $search);
        $query = $this->applyFiltersToBuilder($query, $filters);
        $query = $this->applySortToBuilder($query, $request);

        /** @var LengthAwarePaginator<int, Model> $paginator */
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $items = $paginator->getCollection()->values()->all();

        return new TableResult(
            rows: $this->resolveRows($items, $visibleColumns),
            columns: $this->columnPayloads($visibleColumns),
            filters: $this->filterPayloads(),
            actions: $this->actionPayloads(false),
            bulkActions: $this->actionPayloads(true),
            pagination: [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'lastPage' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            state: [
                'filters' => $filters,
                'search' => $search,
                'sort' => (string) $request->query('sort', ''),
                'visibleColumns' => $visibleColumns,
            ],
        );
    }

    /**
     * @param  Collection<int, mixed>  $items
     * @param  list<string>  $visibleColumns
     * @param  TableFilters  $filters
     */
    private function resultFromCollection(
        Collection $items,
        Request $request,
        int $page,
        int $perPage,
        string $search,
        array $visibleColumns,
        array $filters,
    ): TableResult {
        $dataset = $this->applySearchToCollection($items, $search);
        $dataset = $this->applyFiltersToCollection($dataset, $filters);
        $dataset = $this->applySortToCollection($dataset, $request);

        $total = $dataset->count();
        $offset = ($page - 1) * $perPage;
        /** @var Collection<int, mixed> $pageItems */
        $pageItems = $dataset->slice($offset, $perPage)->values();

        return new TableResult(
            rows: $this->resolveRows($pageItems->all(), $visibleColumns),
            columns: $this->columnPayloads($visibleColumns),
            filters: $this->filterPayloads(),
            actions: $this->actionPayloads(false),
            bulkActions: $this->actionPayloads(true),
            pagination: [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'lastPage' => (int) max(1, (int) ceil($total / $perPage)),
                'from' => $total === 0 ? null : ($offset + 1),
                'to' => $total === 0 ? null : min($offset + $perPage, $total),
            ],
            state: [
                'filters' => $filters,
                'search' => $search,
                'sort' => (string) $request->query('sort', ''),
                'visibleColumns' => $visibleColumns,
            ],
        );
    }

    private function validateAllowLists(Request $request): void
    {
        $adapter = $this->queryBuilderAdapter ?? new QueryBuilderAdapter;
        $filterKeys = array_map(static fn (Filter $filter): string => $filter->key(), $this->filters);
        $sortableColumns = array_values(array_map(
            static fn (Column $column): string => $column->key(),
            array_filter($this->columns, static fn (Column $column): bool => $column->isSortable()),
        ));

        $adapter
            ->allowedFilters($filterKeys)
            ->allowedSorts($sortableColumns)
            ->allowedIncludes([])
            ->validate($request);
    }

    /**
     * @return list<string>
     */
    private function visibleColumns(Request $request): array
    {
        $raw = $request->query('columns');

        if (! is_scalar($raw) || $raw === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', (string) $raw))));
    }
}
