<?php

declare(strict_types=1);

namespace CorePanel\Support\FormBuilder\Fields;

use CorePanel\Support\FormBuilder\Field;

final class PasswordInput extends Field
{
    protected const TYPE = 'password';

    protected function baseRules(): array
    {
        return ['string'];
    }
}
