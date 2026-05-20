<?php

declare(strict_types=1);

namespace CorePanel\Http\Requests;

use CorePanel\Support\Settings\SettingsSchema;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function withValidator(Validator $validator): void
    {
        if ((string) $this->route('group') !== 'i18n') {
            return;
        }

        $validator->after(function (Validator $validator): void {
            $selectedLocales = collect((array) data_get($this->input('values', []), 'languages.value', []))
                ->filter(static fn (mixed $value): bool => is_string($value) && $value !== '')
                ->values()
                ->all();

            if ($selectedLocales === []) {
                $validator->errors()->add(
                    'values.languages.value',
                    __('core-panel::settings.validation.languages_required'),
                );

                return;
            }

            foreach (['default_locale', 'fallback_locale'] as $key) {
                $value = data_get($this->input('values', []), $key.'.value');

                if (is_string($value) && $value !== '' && ! in_array($value, $selectedLocales, true)) {
                    $validator->errors()->add(
                        'values.'.$key.'.value',
                        $key === 'default_locale'
                            ? __('core-panel::settings.validation.default_locale_enabled')
                            : __('core-panel::settings.validation.fallback_locale_enabled'),
                    );
                }
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $group = SettingsSchema::group((string) $this->route('group'));

        if ($group === null) {
            return [
                'values' => ['required', 'array'],
            ];
        }

        $rules = [
            'values' => ['required', 'array'],
        ];
        $selectedLocales = collect((array) data_get($this->input('values', []), 'languages.value', []))
            ->filter(static fn (mixed $value): bool => is_string($value) && $value !== '')
            ->values()
            ->all();

        foreach ((array) $group['fields'] as $key => $field) {
            $valueRules = $field['rules'] ?? ['nullable'];

            if ((string) $this->route('group') === 'i18n'
                && in_array($key, ['default_locale', 'fallback_locale'], true)
                && $selectedLocales === []) {
                $valueRules = ['nullable', 'string'];
            }
            $allowedOptionValues = collect((array) ($field['options'] ?? []))
                ->pluck('value')
                ->filter(static fn (mixed $value): bool => is_string($value) && $value !== '')
                ->values()
                ->all();

            $rules["values.{$key}"] = ['present'];
            $rules["values.{$key}.value"] = is_array($valueRules) ? $valueRules : ['nullable'];

            if (($field['type'] ?? null) === 'multiselect') {
                $rules["values.{$key}.value.*"] = $allowedOptionValues === []
                    ? ['string']
                    : ['string', 'in:'.implode(',', $allowedOptionValues)];
            }
        }

        return $rules;
    }
}
