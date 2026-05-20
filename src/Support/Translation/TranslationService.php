<?php

declare(strict_types=1);

namespace CorePanel\Support\Translation;

use CorePanel\Support\Config\CorePanelConfig;
use Illuminate\Database\Eloquent\Model;

final readonly class TranslationService
{
    public function __construct(
        private CorePanelConfig $config,
    ) {}

    public function get(Model|array $source, string $field, ?string $locale = null): mixed
    {
        $translations = $this->translationsFor($source, $field);

        if ($translations === null) {
            return data_get($source, $field);
        }

        foreach ($this->localePriority($locale) as $candidateLocale) {
            if (array_key_exists($candidateLocale, $translations)) {
                return $translations[$candidateLocale];
            }
        }

        return null;
    }

    public function set(Model $model, string $field, string $locale, mixed $value): void
    {
        if (method_exists($model, 'setTranslation')) {
            $model->setTranslation($field, $locale, $value);

            return;
        }

        $translations = $this->translationsFor($model, $field) ?? [];
        $translations[$locale] = $value;
        $model->setAttribute($field, $translations);
    }

    /**
     * @return list<string>
     */
    private function localePriority(?string $preferredLocale = null): array
    {
        $request = request();
        $selectedLocale = $request->hasSession()
            ? $request->session()->get('locale')
            : $request->input('locale', $request->query('locale'));
        $userLocale = data_get(auth()->user(), 'locale');

        return array_values(array_unique(array_filter([
            is_string($preferredLocale) && $preferredLocale !== '' ? $preferredLocale : null,
            is_string($selectedLocale) && $selectedLocale !== '' ? $selectedLocale : null,
            is_string($userLocale) && $userLocale !== '' ? $userLocale : null,
            $this->config->i18n->defaultLocale,
            $this->config->i18n->fallbackLocale,
        ])));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function translationsFor(Model|array $source, string $field): ?array
    {
        if ($source instanceof Model && method_exists($source, 'getTranslations')) {
            /** @var array<string, mixed> $translations */
            $translations = $source->getTranslations($field);

            return $translations;
        }

        $value = data_get($source, $field);

        return is_array($value) ? $value : null;
    }
}
