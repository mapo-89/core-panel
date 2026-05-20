<?php

declare(strict_types=1);

namespace CorePanel\Support\FormBuilder\Fields;

use CorePanel\Support\FormBuilder\Field;

final class EmailInput extends Field
{
    protected const TYPE = 'email';

    protected function baseRules(): array
    {
        return ['email'];
    }
}
