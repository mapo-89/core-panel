<?php

declare(strict_types=1);

namespace CorePanel\Support\FormBuilder\Fields;

use CorePanel\Support\FormBuilder\Field;

final class TextInput extends Field
{
    protected const TYPE = 'text';

    protected function baseRules(): array
    {
        return ['string'];
    }
}
