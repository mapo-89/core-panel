<?php

declare(strict_types=1);

namespace CorePanel\Support\Query\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class GlobalSearchFilter
{
    /**
     * @param  list<string>  $columns
     */
    public function __construct(
        private readonly array $columns,
    ) {}

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function apply(Builder $query, mixed $value): Builder
    {
        if (! is_scalar($value) || trim((string) $value) === '' || $this->columns === []) {
            return $query;
        }

        $needle = trim((string) $value);

        return $query->where(function (Builder $builder) use ($needle): void {
            foreach ($this->columns as $column) {
                $builder->orWhere($column, 'like', '%'.$needle.'%');
            }
        });
    }
}
