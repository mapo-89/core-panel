<?php

declare(strict_types=1);

namespace CorePanel\Support\TabBuilder;

use JsonSerializable;

final class Tab implements JsonSerializable
{
    protected ?string $badge = null;

    protected ?string $component = null;

    protected ?string $icon = null;

    protected bool $lazy = false;

    protected ?string $label = null;

    /**
     * @var array<string, string>
     */
    protected array $labelTranslations = [];

    protected ?string $permission = null;

    /**
     * @var array<string, mixed>
     */
    protected array $meta = [];

    /**
     * @var list<array<string, mixed>>|null
     */
    protected ?array $schema = null;

    private bool|\Closure|null $visibleIf = null;

    public function __construct(protected readonly string $key)
    {
        $this->label = str($key)->headline()->toString();
    }

    public static function make(string $key): self
    {
        return new self($key);
    }

    public function badge(int|string|null $badge): self
    {
        $this->badge = $badge !== null ? (string) $badge : null;

        return $this;
    }

    public function component(?string $component): self
    {
        $this->component = $component;

        return $this;
    }

    public function icon(?string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function label(?string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * @param  array<string, string>  $translations
     */
    public function labelTranslations(array $translations): self
    {
        $this->labelTranslations = $translations;

        return $this;
    }

    public function lazy(bool $lazy = true): self
    {
        $this->lazy = $lazy;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function meta(array $meta): self
    {
        $this->meta = [
            ...$this->meta,
            ...$meta,
        ];

        return $this;
    }

    public function permission(?string $permission): self
    {
        $this->permission = $permission;

        return $this;
    }

    /**
     * @param  list<array<string, mixed>>|null  $schema
     */
    public function schema(?array $schema): self
    {
        $this->schema = $schema;

        return $this;
    }

    public function visibleIf(bool|\Closure|null $visibleIf): self
    {
        $this->visibleIf = $visibleIf;

        return $this;
    }

    public function visible(): bool
    {
        if ($this->permission !== null && ! $this->passesPermissionGate()) {
            return false;
        }

        if ($this->visibleIf instanceof \Closure) {
            return (bool) value($this->visibleIf);
        }

        if (is_bool($this->visibleIf)) {
            return $this->visibleIf;
        }

        return true;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'badge' => $this->badge,
            'component' => $this->component,
            'icon' => $this->icon,
            'key' => $this->key,
            'label' => $this->label,
            'labelTranslations' => $this->labelTranslations,
            'lazy' => $this->lazy,
            'meta' => $this->meta,
            'permission' => $this->permission,
            'schema' => $this->schema,
            'visible' => $this->visible(),
        ], static fn (mixed $value): bool => $value !== null && $value !== []);
    }

    private function passesPermissionGate(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return true;
        }

        return (bool) $user->can($this->permission);
    }
}
