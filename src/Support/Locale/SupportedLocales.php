<?php

declare(strict_types=1);

namespace CorePanel\Support\Locale;

use Illuminate\Contracts\Config\Repository as ConfigRepository;

final class SupportedLocales
{
    /**
     * @return array<string, string>
     */
    public static function labels(?ConfigRepository $config = null): array
    {
        $config ??= config();

        /** @var array<string, string> $configuredLanguages */
        $configuredLanguages = (array) $config->get('app.languages', []);

        return collect($configuredLanguages)
            ->filter(static fn (string $label, string $locale): bool => $locale !== '' && $label !== '')
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function availableCodes(?ConfigRepository $config = null): array
    {
        $config ??= config();

        $labelCodes = array_keys(self::labels($config));
        $filesystemCodes = self::filesystemCodes();
        /** @var list<string> $fallbackLocales */
        $fallbackLocales = array_values((array) $config->get('core-panel.i18n.supported_locales', ['de', 'en']));

        return self::normalizeCodes([
            ...$labelCodes,
            ...$filesystemCodes,
            ...$fallbackLocales,
        ]);
    }

    /**
     * @return list<string>
     */
    public static function codes(?ConfigRepository $config = null): array
    {
        $config ??= config();

        /** @var list<string> $configuredLocales */
        $configuredLocales = array_values((array) $config->get('core-panel.i18n.supported_locales', []));
        $normalizedLocales = self::normalizeCodes($configuredLocales);

        if ($normalizedLocales !== []) {
            return $normalizedLocales;
        }

        return self::availableCodes($config);
    }

    /**
     * @return list<string>
     */
    public static function normalize(mixed $locales, ?ConfigRepository $config = null): array
    {
        $normalized = self::normalizeCodes(is_array($locales) ? $locales : []);

        if ($normalized !== []) {
            return $normalized;
        }

        return self::availableCodes($config);
    }

    /**
     * @param  list<string>  $locales
     * @return array<string, string>
     */
    public static function labelsFor(array $locales, ?ConfigRepository $config = null): array
    {
        $config ??= config();

        $configuredLabels = self::labels($config);

        return collect($locales)
            ->mapWithKeys(static function (string $locale) use ($configuredLabels): array {
                return [$locale => $configuredLabels[$locale] ?? self::nativeLabel($locale)];
            })
            ->all();
    }

    /**
     * @return list<array{label:string,value:string}>
     */
    public static function options(?ConfigRepository $config = null): array
    {
        return collect(self::labelsFor(self::codes($config), $config))
            ->map(static fn (string $label, string $value): array => [
                'label' => $label,
                'value' => $value,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{label:string,value:string}>
     */
    public static function availableOptions(?ConfigRepository $config = null): array
    {
        return collect(self::labelsFor(self::availableCodes($config), $config))
            ->map(static fn (string $label, string $value): array => [
                'label' => $label,
                'value' => $value,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private static function filesystemCodes(): array
    {
        $langPath = self::langPath();

        if ($langPath === null || ! is_dir($langPath)) {
            return [];
        }

        $codes = [];

        foreach (scandir($langPath) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === 'vendor') {
                continue;
            }

            $path = $langPath.DIRECTORY_SEPARATOR.$entry;

            if (is_dir($path)) {
                $codes[] = $entry;

                continue;
            }

            if (is_file($path) && str_ends_with($entry, '.json')) {
                $codes[] = pathinfo($entry, PATHINFO_FILENAME);
            }
        }

        return self::normalizeCodes($codes);
    }

    private static function langPath(): ?string
    {
        if (! function_exists('app')) {
            return null;
        }

        try {
            $app = app();
        } catch (\Throwable) {
            return null;
        }

        return $app->langPath();
    }

    private static function nativeLabel(string $locale): string
    {
        $normalizedLocale = str_replace('-', '_', $locale);

        if (class_exists(\Locale::class)) {
            $label = \Locale::getDisplayLanguage($normalizedLocale, $normalizedLocale);

            if (is_string($label) && trim($label) !== '') {
                return $label;
            }
        }

        return match ($locale) {
            'de' => 'Deutsch',
            'en' => 'English',
            'es' => 'Español',
            'fr' => 'Français',
            'it' => 'Italiano',
            'nl' => 'Nederlands',
            'pt' => 'Português',
            'tr' => 'Türkçe',
            default => strtoupper($locale),
        };
    }

    /**
     * @param  array<int, mixed>  $locales
     * @return list<string>
     */
    private static function normalizeCodes(array $locales): array
    {
        return collect($locales)
            ->filter(static fn (mixed $locale): bool => is_string($locale) && $locale !== '')
            ->unique()
            ->values()
            ->all();
    }
}
