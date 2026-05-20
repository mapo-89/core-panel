<?php

declare(strict_types=1);

namespace CorePanel\Support\TableBuilder\Filters;

use CorePanel\Support\TableBuilder\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class DateRangeFilter extends Filter
{
    public const TYPE = 'date-range';

    public function applyToBuilder(Builder $builder, mixed $value): Builder
    {
        if (! is_array($value)) {
            return $builder;
        }

        if (filled($value['from'] ?? null)) {
            $builder->whereDate($this->key(), '>=', (string) $value['from']);
        }

        if (filled($value['to'] ?? null)) {
            $builder->whereDate($this->key(), '<=', (string) $value['to']);
        }

        return $builder;
    }

    public function applyToCollection(Collection $items, mixed $value): Collection
    {
        if (! is_array($value)) {
            return $items;
        }

        $from = filled($value['from'] ?? null) ? Carbon::parse((string) $value['from'])->startOfDay() : null;
        $to = filled($value['to'] ?? null) ? Carbon::parse((string) $value['to'])->endOfDay() : null;

        return $items->filter(function (mixed $row) use ($from, $to): bool {
            $raw = data_get($row, $this->key());

            if ($raw === null || $raw === '') {
                return false;
            }

            $date = $raw instanceof Carbon ? $raw : Carbon::parse((string) $raw);

            if ($from instanceof Carbon && $date->lt($from)) {
                return false;
            }

            if ($to instanceof Carbon && $date->gt($to)) {
                return false;
            }

            return true;
        })->values();
    }
}
