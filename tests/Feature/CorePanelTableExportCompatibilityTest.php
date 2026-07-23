<?php

declare(strict_types=1);

use CorePanel\Support\TableBuilder\Table;

it('retains the legacy exportUsing fluent method', function (): void {
    $table = Table::make();

    expect($table->exportUsing(static fn (): array => []))->toBe($table);
});
