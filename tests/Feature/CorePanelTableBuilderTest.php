<?php

declare(strict_types=1);

use CorePanel\Support\Query\QueryBuilderAdapter;
use CorePanel\Support\TableBuilder\Actions\BulkDeleteAction;
use CorePanel\Support\TableBuilder\Actions\DeleteAction;
use CorePanel\Support\TableBuilder\Actions\EditAction;
use CorePanel\Support\TableBuilder\Columns\BadgeColumn;
use CorePanel\Support\TableBuilder\Columns\BooleanColumn;
use CorePanel\Support\TableBuilder\Columns\DateColumn;
use CorePanel\Support\TableBuilder\Columns\TextColumn;
use CorePanel\Support\TableBuilder\Filters\DateRangeFilter;
use CorePanel\Support\TableBuilder\Filters\SelectFilter;
use CorePanel\Support\TableBuilder\Filters\TextFilter;
use CorePanel\Support\TableBuilder\Table;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

function tableBuilderDataset(): Collection
{
    return collect([
        [
            'id' => 1,
            'name' => 'Anna',
            'email' => 'anna@example.test',
            'status' => 'active',
            'is_active' => true,
            'created_at' => '2026-05-01 10:00:00',
        ],
        [
            'id' => 2,
            'name' => 'Berta',
            'email' => 'berta@example.test',
            'status' => 'inactive',
            'is_active' => false,
            'created_at' => '2026-05-03 10:00:00',
        ],
        [
            'id' => 3,
            'name' => 'Clara',
            'email' => 'clara@example.test',
            'status' => 'active',
            'is_active' => true,
            'created_at' => '2026-05-02 10:00:00',
        ],
    ]);
}

function tableBuilder(): Table
{
    return Table::make()
        ->query(tableBuilderDataset())
        ->queryBuilderAdapter(new QueryBuilderAdapter)
        ->columns([
            TextColumn::make('name')->label('Name')->searchable()->sortable(),
            TextColumn::make('email')->label('E-Mail')->searchable(),
            BadgeColumn::make('status')->label('Status')->sortable(),
            BooleanColumn::make('is_active')->label('Aktiv'),
            DateColumn::make('created_at')->label('Erstellt am')->sortable(),
        ])
        ->filters([
            TextFilter::make('name')->label('Name'),
            SelectFilter::make('status')->label('Status')->options([
                'active' => 'Active',
                'inactive' => 'Inactive',
            ]),
            DateRangeFilter::make('created_at')->label('Erstellt am'),
        ])
        ->actions([
            EditAction::make(),
            DeleteAction::make(),
            BulkDeleteAction::make(),
        ]);
}

it('sorts rows server-side', function (): void {
    $result = tableBuilder()
        ->request(Request::create('/users', 'GET', [
            'sort' => '-created_at',
        ]))
        ->result()
        ->toArray();

    expect($result['rows'][0]['name'])->toBe('Berta')
        ->and($result['rows'][1]['name'])->toBe('Clara')
        ->and($result['rows'][2]['name'])->toBe('Anna');
});

it('searches rows server-side across searchable columns', function (): void {
    $result = tableBuilder()
        ->request(Request::create('/users', 'GET', [
            'search' => 'clara@example',
        ]))
        ->result()
        ->toArray();

    expect($result['rows'])->toHaveCount(1)
        ->and($result['rows'][0]['name'])->toBe('Clara');
});

it('filters rows server-side', function (): void {
    $result = tableBuilder()
        ->request(Request::create('/users', 'GET', [
            'filter' => [
                'status' => 'active',
                'created_at' => [
                    'from' => '2026-05-02',
                    'to' => '2026-05-03',
                ],
            ],
        ]))
        ->result()
        ->toArray();

    expect($result['rows'])->toHaveCount(1)
        ->and($result['rows'][0]['name'])->toBe('Clara');
});

it('paginates rows', function (): void {
    $result = tableBuilder()
        ->request(Request::create('/users', 'GET', [
            'page' => 2,
            'per_page' => 1,
            'sort' => 'name',
        ]))
        ->result()
        ->toArray();

    expect($result['rows'])->toHaveCount(1)
        ->and($result['rows'][0]['name'])->toBe('Berta')
        ->and($result['pagination']['page'])->toBe(2)
        ->and($result['pagination']['perPage'])->toBe(1)
        ->and($result['pagination']['total'])->toBe(3)
        ->and($result['pagination']['lastPage'])->toBe(3);
});

it('validates allowed filter keys', function (): void {
    expect(fn () => tableBuilder()
        ->request(Request::create('/users', 'GET', [
            'filter' => [
                'secret' => 'blocked',
            ],
        ]))
        ->result())
        ->toThrow(InvalidArgumentException::class);
});

it('returns row and bulk action metadata', function (): void {
    $result = tableBuilder()
        ->request(Request::create('/users', 'GET'))
        ->result()
        ->toArray();

    expect($result['actions'])->toHaveCount(2)
        ->and($result['bulkActions'])->toHaveCount(1)
        ->and($result['bulkActions'][0]['type'])->toBe('bulk-delete');
});
