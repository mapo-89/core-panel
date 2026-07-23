<?php

declare(strict_types=1);

namespace CorePanel\Support\TableBuilder;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/** @phpstan-consistent-constructor */
abstract class Filter
{
    protected ?string $label = null;

    /**
     * @var array<string, mixed>
     */
    protected array $meta = [];

    /**
     * @var array<int|string, string>
     */
    protected array $options = [];

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

    /**
     * @param  array<string, mixed>  $meta
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
     * @param  array<int|string, string>  $options
     */
    public function options(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    /**
     * @param  Builder<Model>  $builder
     * @return Builder<Model>
     */
    abstract public function applyToBuilder(Builder $builder, mixed $value): Builder;

    /**
     * @param  Collection<array-key, mixed>  $items
     * @return Collection<array-key, mixed>
     */
    abstract public function applyToCollection(Collection $items, mixed $value): Collection;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'meta' => $this->meta,
            'options' => $this->options,
            'type' => static::type(),
        ];
    }

    protected function resolveDefaultLabel(): string
    {
        $translationKey = 'core-panel::table-builder.filters.'.$this->key;
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
