<?php

declare(strict_types=1);

namespace CorePanel\Http\Requests;

use CorePanel\Support\Settings\SettingsSchema;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateStyleSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'values' => ['required', 'array'],
        ];

        foreach (['appearance', 'ui'] as $groupKey) {
            $group = SettingsSchema::group($groupKey);

            if ($group === null) {
                continue;
            }

            foreach ((array) $group['fields'] as $key => $field) {
                $valueRules = $field['rules'] ?? ['nullable'];
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
        }

        return $rules;
    }
}
