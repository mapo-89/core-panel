<?php

declare(strict_types=1);

namespace CorePanel\Support\FormBuilder\Fields;

use CorePanel\Support\FormBuilder\Field;
use CorePanel\Support\FormBuilder\FormSchema;

final class Repeater extends Field
{
    protected const TYPE = 'repeater';

    private FormSchema $schema;

    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->schema = FormSchema::make();
    }

    /**
     * @param  list<Field>|FormSchema  $schema
     */
    public function schema(array|FormSchema $schema): static
    {
        $this->schema = $schema instanceof FormSchema ? $schema : FormSchema::make($schema);

        return $this;
    }

    public function rulesFor(?string $prefix = null): array
    {
        $rules = parent::rulesFor($prefix);

        return [
            ...$rules,
            ...$this->schema->rules($this->validationKey($prefix).'.*'),
        ];
    }

    public function messagesFor(?string $locale = null, ?string $prefix = null): array
    {
        return [
            ...parent::messagesFor($locale, $prefix),
            ...$this->schema->messages($locale, $this->validationKey($prefix).'.*'),
        ];
    }

    protected function baseRules(): array
    {
        return ['array'];
    }

    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'schema' => $this->schema->toArray(),
        ];
    }
}
