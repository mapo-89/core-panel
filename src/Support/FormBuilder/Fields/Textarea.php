<?php

declare(strict_types=1);

namespace CorePanel\Support\FormBuilder\Fields;

use CorePanel\Support\FormBuilder\Field;

final class Textarea extends Field
{
    protected const TYPE = 'textarea';

    protected function baseRules(): array
    {
        return ['string'];
    }
}
