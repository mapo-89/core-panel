<?php

declare(strict_types=1);

namespace CorePanel\Support;

use CorePanel\Support\TableBuilder\Table;

final class TableBuilder
{
    /**
     * @var list<array{key: string, label: string, sortable: bool}>
     */
    private array $columns = [];

    public static function make(): Table
    {
        return Table::make();
    }

    public function column(string $key, ?string $label = null, bool $sortable = false): self
    {
        $resolvedLabel = $label ?? __('core-panel::table-builder.columns.'.$key);

        if ($resolvedLabel === 'core-panel::table-builder.columns.'.$key) {
            $resolvedLabel = str($key)->headline()->toString();
        }

        $this->columns[] = [
            'key' => $key,
            'label' => $resolvedLabel,
            'sortable' => $sortable,
        ];

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'columns' => $this->columns,
        ];
    }
}
