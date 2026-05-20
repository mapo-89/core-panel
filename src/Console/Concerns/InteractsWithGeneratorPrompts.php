<?php

declare(strict_types=1);

namespace CorePanel\Console\Concerns;

use function Laravel\Prompts\confirm;

trait InteractsWithGeneratorPrompts
{
    private function basePath(): string
    {
        $option = $this->option('base-path');

        return is_string($option) && $option !== ''
            ? $option
            : base_path();
    }

    private function booleanGeneratorOption(string $name, string $label, bool $default): bool
    {
        $value = $this->option($name);

        if (is_string($value)) {
            return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
        }

        if ($this->input->isInteractive()) {
            return confirm(
                label: $label,
                default: $default,
            );
        }

        return $default;
    }
}
