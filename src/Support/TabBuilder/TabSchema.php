<?php

declare(strict_types=1);

namespace CorePanel\Support\TabBuilder;

use InvalidArgumentException;
use JsonSerializable;

final class TabSchema implements JsonSerializable
{
    /**
     * @param  list<Tab>  $tabs
     */
    public function __construct(private array $tabs = []) {}

    /**
     * @param  list<Tab>  $tabs
     */
    public static function make(array $tabs = []): self
    {
        foreach ($tabs as $tab) {
            if (! $tab instanceof Tab) {
                throw new InvalidArgumentException('Tab schema entries must be tab instances.');
            }
        }

        return new self($tabs);
    }

    public function push(Tab $tab): self
    {
        $this->tabs[] = $tab;

        return $this;
    }

    /**
     * @return list<Tab>
     */
    public function tabs(): array
    {
        return $this->tabs;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_values(array_map(
            static fn (Tab $tab): array => $tab->toArray(),
            array_filter($this->tabs, static fn (Tab $tab): bool => $tab->visible()),
        ));
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
