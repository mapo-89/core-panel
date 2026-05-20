<?php

declare(strict_types=1);

namespace CorePanel\Support\TableBuilder;

abstract class Action
{
    protected ?string $label = null;

    protected bool $bulk = false;

    /**
     * @var array<string, mixed>
     */
    protected array $meta = [];

    public function __construct(protected readonly string $name)
    {
        $this->label = $this->resolveDefaultLabel();
    }

    public static function make(): static
    {
        /** @phpstan-ignore-next-line */
        return new static(static::defaultName());
    }

    public function isBulk(): bool
    {
        return $this->bulk;
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

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'bulk' => $this->bulk,
            'label' => $this->label,
            'meta' => $this->meta,
            'name' => $this->name,
            'type' => static::type(),
        ];
    }

    protected static function defaultName(): string
    {
        return str(class_basename(static::class))->beforeLast('Action')->snake()->toString();
    }

    protected function resolveDefaultLabel(): string
    {
        $translationKey = 'core-panel::table-builder.actions.'.$this->name;
        $translated = __($translationKey);

        if ($translated !== $translationKey) {
            return $translated;
        }

        return str($this->name)->headline()->toString();
    }

    protected static function type(): string
    {
        return defined('static::TYPE') ? static::TYPE : str(class_basename(static::class))->snake()->toString();
    }
}
