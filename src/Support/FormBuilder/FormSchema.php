<?php

declare(strict_types=1);

namespace CorePanel\Support\FormBuilder;

use InvalidArgumentException;
use JsonSerializable;

final class FormSchema implements JsonSerializable
{
    /**
     * @param  list<Field>  $fields
     */
    public function __construct(
        private array $fields = [],
    ) {}

    /**
     * @param  list<Field>  $fields
     */
    public static function make(array $fields = []): self
    {
        foreach ($fields as $field) {
            if (! $field instanceof Field) {
                throw new InvalidArgumentException('Form schema entries must be field instances.');
            }
        }

        return new self($fields);
    }

    public function push(Field $field): self
    {
        $this->fields[] = $field;

        return $this;
    }

    /**
     * @return list<Field>
     */
    public function fields(): array
    {
        return $this->fields;
    }

    public function rules(?string $prefix = null): array
    {
        $rules = [];

        foreach ($this->fields as $field) {
            $rules = [...$rules, ...$field->rulesFor($prefix)];
        }

        return $rules;
    }

    public function messages(?string $locale = null, ?string $prefix = null): array
    {
        $messages = [];

        foreach ($this->fields as $field) {
            $messages = [...$messages, ...$field->messagesFor($locale, $prefix)];
        }

        return $messages;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function toArray(): array
    {
        return array_map(
            static fn (Field $field): array => $field->toArray(),
            $this->fields,
        );
    }
}
