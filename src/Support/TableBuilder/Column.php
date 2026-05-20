<?php

declare(strict_types=1);

namespace CorePanel\Support\TableBuilder;

abstract class Column
{
    protected ?string $label = null;

    protected bool $searchable = false;

    protected bool $sortable = false;

    protected bool $visible = true;

    protected bool $toggleable = true;

    /**
     * @var array<string, mixed>
     */
    protected array $meta = [];

    public function __construct(protected readonly string $key)
    {
        $this->label = $this->resolveDefaultLabel();
    }

    public static function make(string $key): static
    {
        return new static($key);
    }

    public function key(): string
    {
        return $this->key;
    }

    public function label(?string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function meta(array $meta): static
    {
        $this->meta = [
            ...$this->meta,
            ...$meta,
        ];

        return $this;
    }

    public function searchable(bool $searchable = true): static
    {
        $this->searchable = $searchable;

        return $this;
    }

    public function sortable(bool $sortable = true): static
    {
        $this->sortable = $sortable;

        return $this;
    }

    public function toggleable(bool $toggleable = true): static
    {
        $this->toggleable = $toggleable;

        return $this;
    }

    public function visible(bool $visible = true): static
    {
        $this->visible = $visible;

        return $this;
    }

    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    public function isSortable(): bool
    {
        return $this->sortable;
    }

    public function resolveValue(mixed $row): mixed
    {
        return data_get($row, $this->key);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'meta' => $this->meta,
            'searchable' => $this->searchable,
            'sortable' => $this->sortable,
            'toggleable' => $this->toggleable,
            'type' => static::type(),
            'visible' => $this->visible,
        ];
    }

    protected function resolveDefaultLabel(): string
    {
        $translationKey = 'core-panel::table-builder.columns.'.$this->key;
        $translated = __($translationKey);

        if ($translated !== $translationKey) {
            return $translated;
        }

        return str($this->key)->headline()->toString();
    }

    protected static function type(): string
    {
        return defined('static::TYPE') ? static::TYPE : str(class_basename(static::class))->snake()->toString();
    }
}
