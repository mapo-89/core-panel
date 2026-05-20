<?php

declare(strict_types=1);

namespace CorePanel\Support\FormBuilder\Fields;

use CorePanel\Support\FormBuilder\Field;

final class NumberInput extends Field
{
    protected const TYPE = 'number';

    protected function baseRules(): array
    {
        return ['numeric'];
    }
}
