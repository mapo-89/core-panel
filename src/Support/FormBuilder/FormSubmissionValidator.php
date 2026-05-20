<?php

declare(strict_types=1);

namespace CorePanel\Support\FormBuilder;

use Illuminate\Support\Arr;

final class FormSubmissionValidator
{
    /**
     * @param  list<array<string, mixed>>  $schema
     * @return array<string, list<string>>
     */
    public function rules(array $schema): array
    {
        return $this->rulesForFields($schema);
    }

    /**
     * @param  list<array<string, mixed>>  $schema
     * @return array<string, string>
     */
    public function messages(array $schema, ?string $locale = null): array
    {
        return $this->messagesForFields($schema, $locale);
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @return array<string, list<string>>
     */
    private function rulesForFields(array $fields, ?string $prefix = null): array
    {
        $rules = [];

        foreach ($fields as $field) {
            $name = (string) ($field['name'] ?? '');

            if ($name === '') {
                continue;
            }

            $key = $prefix === null ? $name : $prefix.'.'.$name;
            $type = (string) ($field['type'] ?? 'text');

            $fieldRules = array_values(array_unique(array_filter([
                ...$this->baseRulesForType($type, $field),
                ...(array_values(array_map('strval', Arr::wrap($field['rules'] ?? [])))),
                (($field['required'] ?? false) === true) ? 'required' : null,
            ])));

            if ($fieldRules !== []) {
                $rules[$key] = $fieldRules;
            }

            $nestedSchema = $field['schema'] ?? null;

            if (! is_array($nestedSchema)) {
                continue;
            }

            if ($type === 'group') {
                $rules = [...$rules, ...$this->rulesForFields($nestedSchema, $key)];

                continue;
            }

            if ($type === 'repeater') {
                $rules[$key] = array_values(array_unique([
                    ...($rules[$key] ?? []),
                    'array',
                ]));

                $rules = [...$rules, ...$this->rulesForFields($nestedSchema, $key.'.*')];
            }
        }

        return $rules;
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @return array<string, string>
     */
    private function messagesForFields(array $fields, ?string $locale = null, ?string $prefix = null): array
    {
        $messages = [];

        foreach ($fields as $field) {
            $name = (string) ($field['name'] ?? '');

            if ($name === '') {
                continue;
            }

            $key = $prefix === null ? $name : $prefix.'.'.$name;

            foreach ((array) ($field['validationMessageTranslations'] ?? []) as $rule => $translation) {
                $messages[$key.'.'.$rule] = $this->resolveTranslation($translation, $locale);
            }

            $nestedSchema = $field['schema'] ?? null;

            if (! is_array($nestedSchema)) {
                continue;
            }

            $type = (string) ($field['type'] ?? 'text');
            $nestedPrefix = $type === 'repeater' ? $key.'.*' : $key;

            $messages = [...$messages, ...$this->messagesForFields($nestedSchema, $locale, $nestedPrefix)];
        }

        return $messages;
    }

    /**
     * @param  array<string, mixed>  $field
     * @return list<string>
     */
    private function baseRulesForType(string $type, array $field): array
    {
        $options = $this->optionValues($field);

        return match ($type) {
            'checkbox' => ['boolean'],
            'date', 'datetime' => ['date'],
            'email' => ['email'],
            'file' => ['file'],
            'group' => ['array'],
            'multi-select' => array_values(array_filter([
                'array',
                $options !== [] ? 'array' : null,
            ])),
            'number' => ['numeric'],
            'radio', 'select' => $options !== [] ? ['in:'.implode(',', $options)] : [],
            'repeater' => ['array'],
            'text', 'textarea', 'password', 'hidden' => ['string'],
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $field
     * @return list<string>
     */
    private function optionValues(array $field): array
    {
        $options = $field['options'] ?? [];

        if (! is_array($options)) {
            return [];
        }

        if (array_is_list($options)) {
            return array_values(array_map(
                static fn (mixed $option): string => is_array($option)
                    ? (string) ($option['value'] ?? '')
                    : (string) $option,
                $options
            ));
        }

        return array_values(array_map('strval', array_keys($options)));
    }

    private function resolveTranslation(mixed $translation, ?string $locale = null): string
    {
        if (is_string($translation)) {
            return $translation;
        }

        if (! is_array($translation)) {
            return '';
        }

        $activeLocale = $locale ?? app()->getLocale();
        $defaultLocale = (string) config('core-panel.i18n.default_locale', 'de');
        $fallbackLocale = (string) config('core-panel.i18n.fallback_locale', 'en');

        return (string) ($translation[$activeLocale]
            ?? $translation[$defaultLocale]
            ?? $translation[$fallbackLocale]
            ?? reset($translation)
            ?? '');
    }
}
