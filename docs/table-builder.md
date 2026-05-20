# Table Builder

CorePanel includes a backend table schema builder and a Vue renderer based on PrimeVue DataTable.

## Backend API

```php
use CorePanel\Support\TableBuilder\Table;
use CorePanel\Support\TableBuilder\Columns\TextColumn;

$table = Table::make()
    ->columns([
        TextColumn::make('name')->label('Name')->searchable()->sortable(),
    ]);
```

## Features

- server-side search
- server-side sort
- filters
- pagination
- row actions
- bulk actions
- column visibility

## Query layer

The backend integrates through `QueryBuilderAdapter`, so filtering and sorting stay allowlisted.
