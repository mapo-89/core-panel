<?php

declare(strict_types=1);

namespace CorePanel\Support\TableBuilder;

use JsonSerializable;

final readonly class TableResult implements JsonSerializable
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array<string, mixed>>  $columns
     * @param  list<array<string, mixed>>  $filters
     * @param  list<array<string, mixed>>  $actions
     * @param  list<array<string, mixed>>  $bulkActions
     * @param  array<string, mixed>  $pagination
     * @param  array<string, mixed>  $state
     */
    public function __construct(
        public array $rows,
        public array $columns,
        public array $filters,
        public array $actions,
        public array $bulkActions,
        public array $pagination,
        public array $state,
    ) {}

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'actions' => $this->actions,
            'bulkActions' => $this->bulkActions,
            'columns' => $this->columns,
            'filters' => $this->filters,
            'pagination' => $this->pagination,
            'rows' => $this->rows,
            'state' => $this->state,
        ];
    }
}
