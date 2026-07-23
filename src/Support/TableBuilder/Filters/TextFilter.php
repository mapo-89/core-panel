<?php

declare(strict_types=1);

namespace CorePanel\Support\TableBuilder\Filters;

use CorePanel\Support\TableBuilder\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class TextFilter extends Filter
{
    public const TYPE = 'text';

    /** @param Builder<Model> $builder @return Builder<\Illuminate\Database\Eloquent\Model> */
    public function applyToBuilder(Builder $builder, mixed $value): Builder
    {
        if (! is_scalar($value) || trim((string) $value) === '') {
            return $builder;
        }

        return $builder->where($this->key(), 'like', '%'.trim((string) $value).'%');
    }

    /** @param Collection<array-key, mixed> $items @return Collection<array-key, mixed> */
    public function applyToCollection(Collection $items, mixed $value): Collection
    {
        if (! is_scalar($value) || trim((string) $value) === '') {
            return $items;
        }

        $needle = mb_strtolower(trim((string) $value));

        return $items->filter(fn (mixed $row): bool => str_contains(
            mb_strtolower((string) data_get($row, $this->key())),
            $needle,
        ))->values();
    }
}
