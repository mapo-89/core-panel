<?php

declare(strict_types=1);

namespace CorePanel\Support;

enum PublishTag: string
{
    case Components = 'core-panel-components';
    case Config = 'core-panel-config';
    case Lang = 'core-panel-lang';
    case Stubs = 'core-panel-stubs';
    case Theme = 'core-panel-theme';
    case Views = 'core-panel-views';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $tag): string => $tag->value,
            self::cases(),
        );
    }

    /**
     * @return list<string>
     */
    public static function installTags(): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    public static function updateTags(): array
    {
        return [];
    }

    public static function normalize(string $tag): ?string
    {
        return match ($tag) {
            'components', self::Components->value => self::Components->value,
            'config', self::Config->value => self::Config->value,
            'lang', self::Lang->value => self::Lang->value,
            'stubs', self::Stubs->value => self::Stubs->value,
            'theme', self::Theme->value => self::Theme->value,
            'views', self::Views->value => self::Views->value,
            default => null,
        };
    }
}
