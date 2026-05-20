<?php

declare(strict_types=1);

namespace CorePanel\Support\Config;

final readonly class I18nConfig
{
    /**
     * @param  list<string>  $supportedLocales
     */
    public function __construct(
        public string $defaultLocale,
        public string $fallbackLocale,
        public array $supportedLocales,
    ) {}
}
