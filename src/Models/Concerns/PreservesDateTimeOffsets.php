<?php

declare(strict_types=1);

namespace CorePanel\Models\Concerns;

trait PreservesDateTimeOffsets
{
    public function fromDateTime($value): mixed
    {
        if ($value === null) {
            return $value;
        }

        if ($this->getConnection()->getDriverName() !== 'pgsql') {
            return parent::fromDateTime($value);
        }

        return $this->asDateTime($value)->toIso8601String();
    }
}
