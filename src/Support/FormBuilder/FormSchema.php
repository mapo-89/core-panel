<?php

declare(strict_types=1);

namespace CorePanel\Support\FormBuilder;

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

    /** @return array<string, mixed> */
    public function rules(?string $prefix = null): array
    {
        $rules = [];

        foreach ($this->fields as $field) {
            $rules = [...$rules, ...$field->rulesFor($prefix)];
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(?string $locale = null, ?string $prefix = null): array
    {
        $messages = [];

        foreach ($this->fields as $field) {
            $messages = [...$messages, ...$field->messagesFor($locale, $prefix)];
        }

        return $messages;
    }

    /** @return list<array<string, mixed>> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /** @return list<array<string, mixed>> */
    public function toArray(): array
    {
        return array_map(
            static fn (Field $field): array => $field->toArray(),
            $this->fields,
        );
    }
}
