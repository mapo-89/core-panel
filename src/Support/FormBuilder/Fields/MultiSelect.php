<?php

declare(strict_types=1);

namespace CorePanel\Support\FormBuilder\Fields;

use CorePanel\Support\FormBuilder\Field;

final class MultiSelect extends Field
{
    protected const TYPE = 'multi-select';

    protected function baseRules(): array
    {
        return ['array'];
    }
}
