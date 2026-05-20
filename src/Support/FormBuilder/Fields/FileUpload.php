<?php

declare(strict_types=1);

namespace CorePanel\Support\FormBuilder\Fields;

use CorePanel\Support\FormBuilder\Field;

final class FileUpload extends Field
{
    protected const TYPE = 'file';

    protected function baseRules(): array
    {
        return ['file'];
    }
}
