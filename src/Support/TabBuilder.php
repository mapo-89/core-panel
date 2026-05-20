<?php

declare(strict_types=1);

namespace CorePanel\Support;

use CorePanel\Support\TabBuilder\Tabs;

final class TabBuilder
{
    /**
     * @var list<array{key: string, label: string}>
     */
    private array $tabs = [];

    public function tab(string $key, ?string $label = null): self
    {
        $this->tabs[] = [
            'key' => $key,
            'label' => $label ?? str($key)->headline()->toString(),
        ];

        return $this;
    }

    /**
     * @return array{tabs: list<array{key: string, label: string}>}
     */
    public function toArray(): array
    {
        return [
            'tabs' => $this->tabs,
        ];
    }

    public static function make(): Tabs
    {
        return Tabs::make();
    }
}
