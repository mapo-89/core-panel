<?php

declare(strict_types=1);

namespace CorePanel\Support\TabBuilder;

use JsonSerializable;

final class Tabs implements JsonSerializable
{
    public function __construct(private TabSchema $tabs) {}

    public static function make(): self
    {
        return new self(TabSchema::make());
    }

    /**
     * @param  list<Tab>|TabSchema  $tabs
     */
    public function tabs(array|TabSchema $tabs): self
    {
        $this->tabs = $tabs instanceof TabSchema ? $tabs : TabSchema::make($tabs);

        return $this;
    }

    /**
     * @return array{tabs:list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'tabs' => $this->tabs->toArray(),
        ];
    }

    /** @return array{tabs:list<array<string, mixed>>} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
