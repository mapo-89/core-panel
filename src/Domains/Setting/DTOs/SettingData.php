<?php

declare(strict_types=1);

namespace CorePanel\Domains\Setting\DTOs;

use CorePanel\Models\Setting;

final readonly class SettingData
{
    public function __construct(
        public string $id,
        public string $group,
        public string $key,
        public mixed $value,
        public string $type,
        public bool $isPublic,
        public bool $isLocalized,
    ) {}

    public static function fromModel(Setting $setting): self
    {
        return new self(
            id: (string) $setting->getKey(),
            group: (string) $setting->getAttribute('group'),
            key: (string) $setting->getAttribute('key'),
            value: $setting->getAttribute('value_json'),
            type: (string) $setting->getAttribute('type'),
            isPublic: (bool) $setting->getAttribute('is_public'),
            isLocalized: (bool) $setting->getAttribute('is_localized'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'group' => $this->group,
            'id' => $this->id,
            'isLocalized' => $this->isLocalized,
            'isPublic' => $this->isPublic,
            'key' => $this->key,
            'type' => $this->type,
            'value' => $this->value,
        ];
    }
}
