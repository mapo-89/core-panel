<?php

declare(strict_types=1);

namespace CorePanel\Support\TableBuilder\Filters;

use CorePanel\Support\TableBuilder\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class SelectFilter extends Filter
{
    public const TYPE = 'select';

    /** @param Builder<Model> $builder @return Builder<\Illuminate\Database\Eloquent\Model> */
    public function applyToBuilder(Builder $builder, mixed $value): Builder
    {
        if (! is_scalar($value) || (string) $value === '') {
            return $builder;
        }

        return $builder->where($this->key(), (string) $value);
    }

    /** @param Collection<array-key, mixed> $items @return Collection<array-key, mixed> */
    public function applyToCollection(Collection $items, mixed $value): Collection
    {
        if (! is_scalar($value) || (string) $value === '') {
            return $items;
        }

        return $items->filter(
            fn (mixed $row): bool => (string) data_get($row, $this->key()) === (string) $value
        )->values();
    }
}
