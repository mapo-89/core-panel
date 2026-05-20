<?php

declare(strict_types=1);

namespace CorePanel\Domains\Form\DTOs;

use CorePanel\Models\Form;

final readonly class FormData
{
    /**
     * @param  list<array<string, mixed>>  $schema
     * @param  array<string, mixed>|null  $settings
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public string $status,
        public array $schema,
        public ?array $settings,
        public ?string $createdBy,
        public int $version,
        public string $publicUrl,
    ) {}

    /**
     * @return array{
     *   id:string,
     *   name:string,
     *   slug:string,
     *   status:string,
     *   schema:list<array<string, mixed>>,
     *   settings:?array<string, mixed>,
     *   createdBy:?string,
     *   version:int,
     *   publicUrl:string
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->status,
            'schema' => $this->schema,
            'settings' => $this->settings,
            'createdBy' => $this->createdBy,
            'version' => $this->version,
            'publicUrl' => $this->publicUrl,
        ];
    }

    public static function fromModel(Form $form): self
    {
        $latestVersion = (int) ($form->relationLoaded('versions')
            ? $form->getRelation('versions')->max('version')
            : ($form->versions()->max('version') ?? 1));

        return new self(
            id: (string) $form->getKey(),
            name: (string) $form->getAttribute('name'),
            slug: (string) $form->getAttribute('slug'),
            status: (string) $form->getAttribute('status'),
            schema: (array) ($form->getAttribute('schema_json') ?? []),
            settings: is_array($form->getAttribute('settings_json')) ? $form->getAttribute('settings_json') : null,
            createdBy: $form->getAttribute('created_by') !== null ? (string) $form->getAttribute('created_by') : null,
            version: $latestVersion,
            publicUrl: route('core-panel.forms.public.show', ['slug' => $form->getAttribute('slug')]),
        );
    }
}
