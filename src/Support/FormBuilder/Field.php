<?php

declare(strict_types=1);

namespace CorePanel\Support\FormBuilder;

use JsonSerializable;

/**
 * @phpstan-type FieldTranslationMap array<string, string>
 * @phpstan-type FieldValidationTranslation string|FieldTranslationMap
 * @phpstan-type FieldCondition array<string, mixed>|string|null
 * @phpstan-type FieldColumnSpan array<string, int|string>|int|string|null
 * @phpstan-type FieldOptions array<array-key, mixed>
 * @phpstan-type FieldMeta array<string, mixed>
 * @phpstan-type FieldArray array<string, mixed>
 *
 * @phpstan-consistent-constructor
 */
abstract class Field implements JsonSerializable
{
    protected ?string $label = null;

    /**
     * @var array<string, string>
     */
    protected array $labelTranslations = [];

    protected ?string $placeholder = null;

    /**
     * @var array<string, string>
     */
    protected array $placeholderTranslations = [];

    protected ?string $help = null;

    /**
     * @var array<string, string>
     */
    protected array $helpTranslations = [];

    protected mixed $default = null;

    protected bool $required = false;

    /**
     * @var list<string>
     */
    protected array $rules = [];

    /**
     * @var array<string, string|array<string, string>>
     */
    protected array $validationMessageTranslations = [];

    /**
     * @var FieldCondition
     */
    protected array|string|null $visibleIf = null;

    /**
     * @var FieldCondition
     */
    protected array|string|null $disabledIf = null;

    /**
     * @var FieldColumnSpan
     */
    protected int|string|array|null $columnSpan = null;

    /**
     * @var FieldOptions
     */
    protected array $options = [];

    /**
     * @var array<string, array<string, string>>
     */
    protected array $optionTranslations = [];

    /**
     * @var FieldMeta
     */
    protected array $meta = [];

    public function __construct(
        protected readonly string $name,
    ) {
        $this->label = $this->resolveDefaultLabel();
    }

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function label(?string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * @param  FieldTranslationMap  $translations
     */
    public function labelTranslations(array $translations): static
    {
        $this->labelTranslations = $translations;

        return $this;
    }

    public function placeholder(?string $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    /**
     * @param  FieldTranslationMap  $translations
     */
    public function placeholderTranslations(array $translations): static
    {
        $this->placeholderTranslations = $translations;

        return $this;
    }

    public function help(?string $help): static
    {
        $this->help = $help;

        return $this;
    }

    /**
     * @param  FieldTranslationMap  $translations
     */
    public function helpTranslations(array $translations): static
    {
        $this->helpTranslations = $translations;

        return $this;
    }

    public function default(mixed $value): static
    {
        $this->default = $value;

        return $this;
    }

    public function required(bool $required = true): static
    {
        $this->required = $required;

        return $this;
    }

    /**
     * @param  string|list<string>  $rules
     */
    public function rules(string|array $rules): static
    {
        $incomingRules = is_array($rules) ? $rules : [$rules];

        $this->rules = array_values(array_unique([
            ...$this->rules,
            ...$incomingRules,
        ]));

        return $this;
    }

    /**
     * @param  array<string, string|array<string, string>>  $translations
     */
    public function validationMessageTranslations(array $translations): static
    {
        $this->validationMessageTranslations = $translations;

        return $this;
    }

    /**
     * @param  FieldCondition  $condition
     */
    public function visibleIf(array|string|null $condition): static
    {
        $this->visibleIf = $condition;

        return $this;
    }

    /**
     * @param  FieldCondition  $condition
     */
    public function disabledIf(array|string|null $condition): static
    {
        $this->disabledIf = $condition;

        return $this;
    }

    /**
     * @param  FieldColumnSpan  $columnSpan
     */
    public function columnSpan(int|string|array|null $columnSpan): static
    {
        $this->columnSpan = $columnSpan;

        return $this;
    }

    /**
     * @param  FieldOptions  $options
     */
    public function options(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    /**
     * @param  array<string, array<string, string>>  $translations
     */
    public function optionTranslations(array $translations): static
    {
        $this->optionTranslations = $translations;

        return $this;
    }

    /**
     * @param  FieldMeta  $meta
     */
    public function meta(array $meta): static
    {
        $this->meta = [
            ...$this->meta,
            ...$meta,
        ];

        return $this;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rulesFor(?string $prefix = null): array
    {
        $rules = $this->resolvedRules();

        if ($rules === []) {
            return [];
        }

        return [
            $this->validationKey($prefix) => $rules,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messagesFor(?string $locale = null, ?string $prefix = null): array
    {
        $messages = [];

        foreach ($this->validationMessageTranslations as $rule => $translation) {
            $messages[$this->validationKey($prefix).'.'.$rule] = $this->resolveTranslationValue($translation, $locale);
        }

        return $messages;
    }

    /**
     * @return FieldArray
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @return FieldArray
     */
    public function toArray(): array
    {
        $payload = [
            'label' => $this->label,
            'name' => $this->name,
            'required' => $this->required,
            'rules' => $this->resolvedRules(),
            'type' => static::type(),
        ];

        foreach ([
            'columnSpan' => $this->columnSpan,
            'default' => $this->default,
            'disabledIf' => $this->disabledIf,
            'help' => $this->help,
            'helpTranslations' => $this->helpTranslations,
            'labelTranslations' => $this->labelTranslations,
            'meta' => $this->meta,
            'optionTranslations' => $this->optionTranslations,
            'options' => $this->options,
            'placeholder' => $this->placeholder,
            'placeholderTranslations' => $this->placeholderTranslations,
            'validationMessageTranslations' => $this->validationMessageTranslations,
            'visibleIf' => $this->visibleIf,
        ] as $key => $value) {
            if ($value === null || $value === []) {
                continue;
            }

            $payload[$key] = $value;
        }

        return $payload;
    }

    protected static function type(): string
    {
        return defined('static::TYPE') ? static::TYPE : str(class_basename(static::class))->snake()->toString();
    }

    /**
     * @return list<string>
     */
    protected function resolvedRules(): array
    {
        $rules = [
            ...$this->baseRules(),
            ...$this->rules,
        ];

        if ($this->required) {
            $rules[] = 'required';
        }

        return array_values(array_unique($rules));
    }

    /**
     * @return list<string>
     */
    protected function baseRules(): array
    {
        return [];
    }

    protected function validationKey(?string $prefix = null): string
    {
        if ($prefix === null || $prefix === '') {
            return $this->name;
        }

        return $prefix.'.'.$this->name;
    }

    /**
     * @param  FieldValidationTranslation  $translation
     */
    protected function resolveTranslationValue(string|array $translation, ?string $locale = null): string
    {
        if (is_string($translation)) {
            return $translation;
        }

        $activeLocale = $locale ?? app()->getLocale();
        $defaultLocale = (string) config('core-panel.i18n.default_locale', 'de');
        $fallbackLocale = (string) config('core-panel.i18n.fallback_locale', 'en');

        return $translation[$activeLocale]
            ?? $translation[$defaultLocale]
            ?? $translation[$fallbackLocale]
            ?? (string) reset($translation);
    }

    private function resolveDefaultLabel(): string
    {
        $translationKey = 'core-panel::form-builder.fields.'.$this->name;
        $translated = __($translationKey);

        if ($translated !== $translationKey) {
            return $translated;
        }

        return str($this->name)->headline()->toString();
    }
}
