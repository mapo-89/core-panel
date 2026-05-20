<?php

declare(strict_types=1);

namespace CorePanel\Support\Config;

final readonly class UiConfig
{
    public function __construct(
        public string $library,
        public string $theme,
    ) {}
}
