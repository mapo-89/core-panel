<?php

declare(strict_types=1);

namespace CorePanel\Support\FormBuilder;

use JsonSerializable;

final class Form implements JsonSerializable
{
    public function __construct(
        private readonly string $name,
        private FormSchema $schema,
        private ?string $permission = null,
    ) {}

    public static function make(string $name): self
    {
        return new self($name, FormSchema::make());
    }

    /**
     * @param  list<Field>|FormSchema  $schema
     */
    public function schema(array|FormSchema $schema): self
    {
        $this->schema = $schema instanceof FormSchema ? $schema : FormSchema::make($schema);

        return $this;
    }

    public function permission(?string $permission): self
    {
        $this->permission = is_string($permission) && trim($permission) !== ''
            ? trim($permission)
            : null;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->schema->rules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(?string $locale = null): array
    {
        return $this->schema->messages($locale);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'permission' => $this->permission,
            'schema' => $this->schema->toArray(),
            'validation' => [
                'messages' => $this->messages(),
                'rules' => $this->rules(),
            ],
        ];
    }
}
