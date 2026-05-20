<?php

declare(strict_types=1);

namespace CorePanel\Support\FormBuilder\Fields;

use CorePanel\Support\FormBuilder\Field;

final class Checkbox extends Field
{
    protected const TYPE = 'checkbox';

    protected function baseRules(): array
    {
        return ['boolean'];
    }
}
