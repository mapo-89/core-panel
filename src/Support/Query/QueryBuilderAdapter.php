<?php

declare(strict_types=1);

namespace CorePanel\Support\Query;

use CorePanel\Support\Query\Filters\GlobalSearchFilter;
use CorePanel\Support\RequiresPackage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Spatie\QueryBuilder\QueryBuilder;

final class QueryBuilderAdapter
{
    use RequiresPackage;

    /**
     * @var list<string>
     */
    private array $allowedFilters = [];

    /**
     * @var list<string>
     */
    private array $allowedSorts = [];

    /**
     * @var list<string>
     */
    private array $allowedIncludes = [];

    /**
     * @var list<string>
     */
    private array $globalSearchColumns = [];

    /**
     * @var list<string>|string|null
     */
    private array|string|null $defaultSort = null;

    private int $perPage = 15;

    private ?int $maxPerPage = 100;

    private string $searchParameter = 'search';

    public function allowed(AllowedQuery $allowedQuery): self
    {
        $this->allowedFilters = $allowedQuery->allowedFilters;
        $this->allowedIncludes = $allowedQuery->allowedIncludes;
        $this->allowedSorts = $allowedQuery->allowedSorts;
        $this->globalSearchColumns = $allowedQuery->globalSearchColumns;
        $this->defaultSort = $allowedQuery->defaultSort;
        $this->perPage = $allowedQuery->perPage;
        $this->maxPerPage = $allowedQuery->maxPerPage;
        $this->searchParameter = $allowedQuery->searchParameter;

        return $this;
    }

    /**
     * @param  list<string>  $filters
     */
    public function allowedFilters(array $filters): self
    {
        $this->allowedFilters = $filters;

        return $this;
    }

    /**
     * @param  list<string>  $sorts
     */
    public function allowedSorts(array $sorts): self
    {
        $this->allowedSorts = $sorts;

        return $this;
    }

    /**
     * @param  list<string>  $includes
     */
    public function allowedIncludes(array $includes): self
    {
        $this->allowedIncludes = $includes;

        return $this;
    }

    /**
     * @param  list<string>  $columns
     */
    public function globalSearch(array $columns, string $parameter = 'search'): self
    {
        $this->globalSearchColumns = $columns;
        $this->searchParameter = $parameter;

        return $this;
    }

    /**
     * @param  list<string>|string|null  $sort
     */
    public function defaultSort(array|string|null $sort): self
    {
        $this->defaultSort = $sort;

        return $this;
    }

    public function perPage(int $perPage, ?int $maxPerPage = 100): self
    {
        $this->perPage = max(1, $perPage);
        $this->maxPerPage = $maxPerPage !== null ? max(1, $maxPerPage) : null;

        return $this;
    }

    public function validate(Request $request): void
    {
        $this->guardAllowList($request, 'filter', $this->allowedFilters);
        $this->guardAllowList($request, 'sort', $this->allowedSorts);
        $this->guardAllowList($request, 'include', $this->allowedIncludes);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return QueryBuilder<TModel>
     */
    public function for(Builder $query, Request $request): QueryBuilder
    {
        $this->requirePackage(
            QueryBuilder::class,
            'spatie/laravel-query-builder'
        );

        $this->validate($request);

        $query = $this->applyGlobalSearch($query, $request);

        $builder = QueryBuilder::for($query, $request)
            ->allowedFilters(...$this->allowedFilters)
            ->allowedSorts(...$this->allowedSorts)
            ->allowedIncludes(...$this->allowedIncludes);

        if ($this->defaultSort !== null) {
            $defaultSorts = is_array($this->defaultSort) ? $this->defaultSort : [$this->defaultSort];
            $builder->defaultSort(...$defaultSorts);
        }

        return $builder;
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return LengthAwarePaginator<int, TModel>
     */
    public function paginate(Builder $query, Request $request): LengthAwarePaginator
    {
        $builder = $this->for($query, $request);
        $perPage = max(1, (int) $request->integer('per_page', $this->perPage));

        if ($this->maxPerPage !== null) {
            $perPage = min($perPage, $this->maxPerPage);
        }

        return $builder->paginate($perPage)->appends($request->query());
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function applyGlobalSearch(Builder $query, Request $request): Builder
    {
        if ($this->globalSearchColumns === []) {
            return $query;
        }

        $filter = new GlobalSearchFilter($this->globalSearchColumns);

        return $filter->apply($query, $request->query($this->searchParameter));
    }

    /**
     * @param  list<string>  $allowedValues
     */
    private function guardAllowList(Request $request, string $parameter, array $allowedValues): void
    {
        $rawValue = $request->query($parameter);

        if (is_array($rawValue)) {
            $requestedValues = array_filter(array_map('strval', array_keys($rawValue)));
            $disallowedValues = array_diff($requestedValues, $allowedValues);

            if ($disallowedValues !== []) {
                throw new InvalidArgumentException(sprintf(
                    'The [%s] parameter contains values that are not allowed: %s',
                    $parameter,
                    implode(', ', $disallowedValues)
                ));
            }

            return;
        }

        if (! is_scalar($rawValue) || $rawValue === '') {
            return;
        }

        $requestedValues = array_filter(array_map(
            static fn (string $value): string => ltrim(trim($value), '-'),
            explode(',', (string) $rawValue)
        ));

        $disallowedValues = array_diff($requestedValues, $allowedValues);

        if ($disallowedValues !== []) {
            throw new InvalidArgumentException(sprintf(
                'The [%s] parameter contains values that are not allowed: %s',
                $parameter,
                implode(', ', $disallowedValues)
            ));
        }
    }
}
