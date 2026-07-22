<?php

declare(strict_types=1);

use CorePanel\Domain\UserGroup\Actions\ImportUserGroupsAction;
use CorePanel\Domain\UserGroup\Services\UserGroupImportFileParser;
use CorePanel\Models\UserGroup;
use Illuminate\Http\UploadedFile;

it('parses user group csv imports with headers', function (): void {
    $parser = app(UserGroupImportFileParser::class);
    $path = tempnam(sys_get_temp_dir(), 'core-panel-user-groups');

    file_put_contents($path, "name,color\nSupport,#FF0000\nSales,#00FF00\n");

    $rows = $parser->parse($path, 'csv');

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['name'])->toBe('Support')
        ->and($rows[0]['color'])->toBe('#FF0000')
        ->and($rows[1]['name'])->toBe('Sales')
        ->and($rows[1]['color'])->toBe('#00FF00');

    @unlink($path);
});

it('imports and previews user groups from uploaded files', function (): void {
    if (! corePanelTestbenchDatabaseAvailable()) {
        $this->markTestSkipped('pdo_sqlite is not available in this environment.');
    }

    $this->migrateScaffoldDatabase();

    UserGroup::query()->create([
        'color' => '#111111',
        'name' => 'Support',
    ]);

    $path = tempnam(sys_get_temp_dir(), 'core-panel-user-groups');
    file_put_contents($path, "name,color\nSupport,#222222\nSales,#00FF00\n");

    $file = new UploadedFile($path, 'user-groups.csv', 'text/csv', null, true);
    $action = app(ImportUserGroupsAction::class);

    $preview = $action->preview($file);
    $result = $action->execute($file);

    expect($preview['total_count'])->toBe(2)
        ->and($preview['create_count'])->toBe(1)
        ->and($preview['update_count'])->toBe(1)
        ->and($result)->toBe([
            'created' => 1,
            'updated' => 1,
        ])
        ->and(UserGroup::query()->where('name', 'Support')->value('color'))->toBe('#222222')
        ->and(UserGroup::query()->where('name', 'Sales')->exists())->toBeTrue();

    @unlink($path);
});
