<?php

declare(strict_types=1);

namespace CorePanel\Support;

use CorePanel\Contracts\LocaleResolver as LocaleResolverContract;
use CorePanel\Support\Config\CorePanelConfig;
use Illuminate\Http\Request;

final readonly class LocaleResolver implements LocaleResolverContract
{
    private const COOKIE_NAME = 'locale';

    public function __construct(private CorePanelConfig $config) {}

    public function resolve(Request $request): string
    {
        $selectedLocale = $this->normalizeLocale(
            $this->stringValue(
                $request->cookie(self::COOKIE_NAME)
                    ?? $request->input('locale')
                    ?? $request->query('locale')
                    ?? $request->headers->get('X-Locale')
                    ?? $this->sessionLocale($request)
            )
        );

        if ($selectedLocale !== null) {
            return $selectedLocale;
        }

        $userLocale = $this->normalizeLocale($this->stringAttribute($request->user(), 'locale'));

        if ($userLocale !== null) {
            return $userLocale;
        }

        $defaultLocale = $this->normalizeLocale($this->config->i18n->defaultLocale);

        if ($defaultLocale !== null) {
            return $defaultLocale;
        }

        return $this->config->i18n->fallbackLocale;
    }

    private function normalizeLocale(?string $locale): ?string
    {
        if ($locale === null || ! in_array($locale, $this->config->i18n->supportedLocales, true)) {
            return null;
        }

        return $locale;
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function stringAttribute(mixed $source, string $attribute): ?string
    {
        if (! is_object($source) || $attribute === '') {
            return null;
        }

        $value = data_get($source, $attribute);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function sessionLocale(Request $request): mixed
    {
        if (! $request->hasSession()) {
            return null;
        }

        return $request->session()->get('locale');
    }
}
