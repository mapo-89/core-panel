<?php

declare(strict_types=1);

function makePublishBasePath(string $suffix): string
{
    return sys_get_temp_dir().'/core-panel-publish-'.bin2hex(random_bytes(4)).'-'.$suffix;
}

function readManifest(string $basePath): string
{
    return file_get_contents($basePath.'/storage/app/core-panel/published.json') ?: '';
}

function seedScaffoldManifest(string $basePath, string $relativePath, string $contents): void
{
    $sourceHash = hash('sha256', $contents);
    $snapshotPath = 'storage/app/core-panel/scaffolds/'.$sourceHash;

    mkdir(dirname($basePath.'/'.$snapshotPath), 0777, true);
    file_put_contents($basePath.'/'.$snapshotPath, $contents);
    file_put_contents($basePath.'/storage/app/core-panel/scaffolds.json', json_encode([
        '_meta' => [
            'package_version' => '1.0.0',
        ],
        'files' => [
            $relativePath => [
                'destination_hash' => $sourceHash,
                'package_version' => '1.0.0',
                'snapshot' => $snapshotPath,
                'source_hash' => $sourceHash,
            ],
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
}

it('publishes a single config tag', function (): void {
    $basePath = makePublishBasePath('config');

    $this->artisan('core-panel:publish', [
        '--tag' => 'config',
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    expect(file_exists($basePath.'/config/core-panel.php'))->toBeTrue()
        ->and(file_exists($basePath.'/config/core-panel-access.php'))->toBeTrue()
        ->and(readManifest($basePath))->toContain('core-panel-config');
});

it('publishes the theme tag', function (): void {
    $basePath = makePublishBasePath('theme');

    $this->artisan('core-panel:publish', [
        '--tag' => 'theme',
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    expect(is_dir($basePath.'/resources/js/theme/core-panel'))->toBeTrue()
        ->and(file_exists($basePath.'/resources/js/theme/core-panel/tokens.ts'))->toBeTrue()
        ->and(readManifest($basePath))->toContain('core-panel-theme');
});

it('does not change files or manifest on update dry-run', function (): void {
    $basePath = makePublishBasePath('dry-run');

    $this->artisan('core-panel:publish', [
        '--tag' => 'components',
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $target = $basePath.'/resources/js/components/FormBuilder/FormRenderer.vue';
    $beforeFile = file_get_contents($target);
    $beforeManifest = readManifest($basePath);

    $this->artisan('core-panel:update', [
        '--dry-run' => true,
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    expect(file_get_contents($target))->toBe($beforeFile)
        ->and(readManifest($basePath))->toBe($beforeManifest);
});

it('detects conflicts during update', function (): void {
    $basePath = makePublishBasePath('conflict');

    $this->artisan('core-panel:publish', [
        '--tag' => 'components',
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $target = $basePath.'/resources/js/components/FormBuilder/FormRenderer.vue';
    file_put_contents($target, (string) file_get_contents($target)."\n// local change\n");

    $this->artisan('core-panel:update', [
        '--dry-run' => true,
        '--base-path' => $basePath,
    ])->assertExitCode(1);
});

it('creates a backup before force updates overwrite local files', function (): void {
    $basePath = makePublishBasePath('force');

    $this->artisan('core-panel:publish', [
        '--tag' => 'components',
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $target = $basePath.'/resources/js/components/FormBuilder/FormRenderer.vue';
    file_put_contents($target, (string) file_get_contents($target)."\n// local change\n");

    $this->artisan('core-panel:update', [
        '--force' => true,
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $backups = glob($basePath.'/.core-panel-backups/*/resources/js/components/FormBuilder/FormRenderer.vue');

    expect($backups)->not->toBeFalse()
        ->and($backups)->not->toBeEmpty()
        ->and(file_get_contents($target))->not->toContain('// local change');
});

it('does not overwrite application scaffolds during force updates', function (): void {
    $basePath = makePublishBasePath('force-scaffold-preserve');
    $target = $basePath.'/resources/js/pages/Admin/Users/Index.vue';

    mkdir(dirname($target), 0777, true);
    file_put_contents($target, "<script setup lang=\"ts\">\nconst customized = true\n</script>\n");

    $this->artisan('core-panel:update', [
        '--force' => true,
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    expect(file_get_contents($target))->toContain('const customized = true');
});

it('updates clean manifest-managed application scaffolds during updates', function (): void {
    $basePath = makePublishBasePath('managed-scaffold-update');
    $target = $basePath.'/routes/console.php';

    $legacyContents = <<<'PHP'
<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
PHP;

    mkdir(dirname($target), 0777, true);
    file_put_contents($target, $legacyContents);
    seedScaffoldManifest($basePath, 'routes/console.php', $legacyContents);

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    expect(file_get_contents($target))->toContain("Schedule::command('horizon:snapshot')->everyFiveMinutes();");
});

it('does not create missing application scaffolds during updates without a previous version baseline', function (): void {
    $basePath = makePublishBasePath('missing-scaffold-no-baseline');
    $target = $basePath.'/routes/console.php';

    mkdir($basePath, 0777, true);

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    expect(file_exists($target))->toBeFalse();
});

it('creates explicitly versioned missing application scaffolds during updates', function (): void {
    $basePath = makePublishBasePath('missing-versioned-scaffold');
    $userGroupsTab = $basePath.'/resources/js/pages/Admin/Users/components/UserGroupsTab.vue';
    $usersTableTab = $basePath.'/resources/js/pages/Admin/Users/components/UsersTableTab.vue';

    mkdir($basePath, 0777, true);

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    expect(file_exists($userGroupsTab))->toBeTrue()
        ->and(file_exists($usersTableTab))->toBeTrue();
});

it('updates explicitly versioned existing application scaffolds without a previous baseline', function (): void {
    $basePath = makePublishBasePath('existing-versioned-scaffold');
    $userGroupsTab = $basePath.'/resources/js/pages/Admin/Users/components/UserGroupsTab.vue';
    $usersTableTab = $basePath.'/resources/js/pages/Admin/Users/components/UsersTableTab.vue';

    mkdir(dirname($userGroupsTab), 0777, true);
    file_put_contents($userGroupsTab, "<template>\n    <div>old groups</div>\n</template>\n");
    file_put_contents($usersTableTab, "<template>\n    <div>old table</div>\n</template>\n");

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    expect(file_get_contents($userGroupsTab))->not->toContain('old groups')
        ->and(file_get_contents($usersTableTab))->not->toContain('old table')
        ->and(glob($basePath.'/.core-panel-backups/*/resources/js/pages/Admin/Users/components/UserGroupsTab.vue'))->not->toBeEmpty()
        ->and(glob($basePath.'/.core-panel-backups/*/resources/js/pages/Admin/Users/components/UsersTableTab.vue'))->not->toBeEmpty();
});

it('synchronizes missing environment defaults during update', function (): void {
    $basePath = makePublishBasePath('env-sync');

    mkdir($basePath, 0777, true);
    file_put_contents($basePath.'/.env', "APP_NAME=CorePanel\n");

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $contents = file_get_contents($basePath.'/.env');

    expect($contents)->toContain("APP_NAME=CorePanel\n")
        ->and($contents)->toContain("LOG_CHANNEL=daily\n");
});

it('preserves a customized published app version metadata file during update', function (): void {
    $basePath = makePublishBasePath('app-version-update');

    mkdir($basePath.'/config', 0777, true);
    file_put_contents($basePath.'/.env', "APP_NAME=CorePanel\n");
    $customizedVersion = [
        'release_version' => '0.0.1',
        'display_version' => '0.0.1 (deadbee)',
        'image_version' => '0.0.1-deadbee',
        'commit' => 'deadbee',
        'commit_date' => '2026-01-01T00:00:00+00:00',
    ];
    file_put_contents($basePath.'/config/app-version.json', json_encode(
        $customizedVersion,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
    ).PHP_EOL);

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $publishedVersion = json_decode((string) file_get_contents($basePath.'/config/app-version.json'), true, 512, JSON_THROW_ON_ERROR);

    expect($publishedVersion)->toBe($customizedVersion);
});

it('preserves a customized published app version metadata file during force update', function (): void {
    $basePath = makePublishBasePath('app-version-force-update');

    mkdir($basePath.'/config', 0777, true);
    mkdir($basePath.'/resources/js', 0777, true);
    file_put_contents($basePath.'/.env', "APP_NAME=CorePanel\n");
    file_put_contents($basePath.'/resources/js/app.ts', "console.log('local app');\n");
    $customizedVersion = [
        'release_version' => '9.9.9',
        'display_version' => '9.9.9 (cafebabe)',
        'image_version' => '9.9.9-cafebabe',
        'commit' => 'cafebabe',
        'commit_date' => '2026-02-02T00:00:00+00:00',
    ];
    file_put_contents($basePath.'/config/app-version.json', json_encode(
        $customizedVersion,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
    ).PHP_EOL);

    $this->artisan('core-panel:update', [
        '--force' => true,
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $publishedVersion = json_decode((string) file_get_contents($basePath.'/config/app-version.json'), true, 512, JSON_THROW_ON_ERROR);
    expect($publishedVersion)->toBe($customizedVersion);
});

it('preserves a customized published app version metadata file during force update when the host marks it as application-managed', function (): void {
    $basePath = makePublishBasePath('app-version-force-update-managed');

    mkdir($basePath.'/config', 0777, true);
    $customizedVersion = [
        'managed_by_application' => true,
        'release_version' => '9.9.9',
        'display_version' => '9.9.9 (cafebabe)',
        'image_version' => '9.9.9-cafebabe',
        'commit' => 'cafebabe',
        'commit_date' => '2026-02-02T00:00:00+00:00',
    ];
    file_put_contents($basePath.'/.env', "APP_NAME=CorePanel\n");
    file_put_contents($basePath.'/config/app-version.json', json_encode(
        $customizedVersion,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
    ).PHP_EOL);

    $this->artisan('core-panel:update', [
        '--force' => true,
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $publishedVersion = json_decode((string) file_get_contents($basePath.'/config/app-version.json'), true, 512, JSON_THROW_ON_ERROR);

    expect($publishedVersion)->toBe($customizedVersion);
});

it('does not create domain-grouped migration directories during update scaffolding without a previous version baseline', function (): void {
    $basePath = makePublishBasePath('domain-migrations');

    mkdir($basePath.'/database/migrations', 0777, true);
    file_put_contents($basePath.'/.env', "APP_NAME=CorePanel\n");
    file_put_contents($basePath.'/database/migrations/0001_01_01_000000_create_users_table.php', 'legacy migration');

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    expect(file_exists($basePath.'/database/migrations/users/0001_01_01_000000_create_users_table.php'))->toBeFalse()
        ->and(file_exists($basePath.'/database/migrations/auth/2016_06_01_000001_create_oauth_auth_codes_table.php'))->toBeFalse()
        ->and(file_exists($basePath.'/database/migrations/0001_01_01_000000_create_users_table.php'))->toBeTrue();
});

it('skips automatic migrations for external base-path updates', function (): void {
    $basePath = makePublishBasePath('skip-migrations');

    mkdir($basePath, 0777, true);
    file_put_contents($basePath.'/.env', "APP_NAME=CorePanel\n");

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])
        ->expectsOutputToContain('Skipping automatic migrations for external base-path updates.')
        ->assertExitCode(0);
});

it('does not create optional publish targets during update when they were never published', function (): void {
    $basePath = makePublishBasePath('skip-unpublished');

    mkdir($basePath, 0777, true);
    file_put_contents($basePath.'/.env', "APP_NAME=CorePanel\n");

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    expect(file_exists($basePath.'/config/core-panel.php'))->toBeFalse()
        ->and(readManifest($basePath))->not->toContain('core-panel-config');
});
