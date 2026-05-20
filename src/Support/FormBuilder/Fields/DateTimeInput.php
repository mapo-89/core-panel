<?php

declare(strict_types=1);

namespace CorePanel\Support\FormBuilder\Fields;

use CorePanel\Support\FormBuilder\Field;

final class DateTimeInput extends Field
{
    protected const TYPE = 'datetime';

    protected function baseRules(): array
    {
        return ['date'];
    }
}
