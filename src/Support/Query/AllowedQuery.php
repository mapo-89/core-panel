<?php

declare(strict_types=1);

namespace CorePanel\Support\Query;

final class AllowedQuery
{
    /**
     * @param  list<string>  $allowedFilters
     * @param  list<string>  $allowedIncludes
     * @param  list<string>  $allowedSorts
     * @param  list<string>  $globalSearchColumns
     * @param  list<string>|string|null  $defaultSort
     */
    public function __construct(
        public array $allowedFilters = [],
        public array $allowedIncludes = [],
        public array $allowedSorts = [],
        public array $globalSearchColumns = [],
        public array|string|null $defaultSort = null,
        public int $perPage = 15,
        public ?int $maxPerPage = 100,
        public string $searchParameter = 'search',
    ) {}

    public static function make(): self
    {
        return new self;
    }

    /**
     * @param  list<string>  $filters
     */
    public function filters(array $filters): self
    {
        $this->allowedFilters = array_values($filters);

        return $this;
    }

    /**
     * @param  list<string>  $includes
     */
    public function includes(array $includes): self
    {
        $this->allowedIncludes = array_values($includes);

        return $this;
    }

    /**
     * @param  list<string>  $sorts
     */
    public function sorts(array $sorts): self
    {
        $this->allowedSorts = array_values($sorts);

        return $this;
    }

    /**
     * @param  list<string>  $columns
     */
    public function globalSearch(array $columns, string $parameter = 'search'): self
    {
        $this->globalSearchColumns = array_values($columns);
        $this->searchParameter = $parameter;

        return $this;
    }

    /**
     * @param  list<string>|string|null  $defaultSort
     */
    public function defaultSort(array|string|null $defaultSort): self
    {
        $this->defaultSort = $defaultSort;

        return $this;
    }

    public function perPage(int $perPage, ?int $maxPerPage = 100): self
    {
        $this->perPage = max(1, $perPage);
        $this->maxPerPage = $maxPerPage !== null ? max(1, $maxPerPage) : null;

        return $this;
    }
}
