<?php

declare(strict_types=1);

namespace CorePanel\Support;

use CorePanel\Support\FormBuilder\Form;

final class FormBuilder
{
    public static function make(string $name): Form
    {
        return Form::make($name);
    }
}
