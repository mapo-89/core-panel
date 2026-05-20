<?php

declare(strict_types=1);

namespace CorePanel\Support\FormBuilder\Fields;

use CorePanel\Support\FormBuilder\Field;

final class DateInput extends Field
{
    protected const TYPE = 'date';

    protected function baseRules(): array
    {
        return ['date'];
    }
}
