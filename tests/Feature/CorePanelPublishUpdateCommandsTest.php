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

function seedScaffoldManifest(string $basePath, string $relativePath, string $contents, string $packageVersion = '1.0.0'): void
{
    seedScaffoldManifestFiles($basePath, [$relativePath => $contents], $packageVersion);
}

/**
 * @param  array<string, string>  $files
 */
function seedScaffoldManifestFiles(string $basePath, array $files, string $packageVersion = '1.0.0'): void
{
    $manifestFiles = [];

    foreach ($files as $relativePath => $contents) {
        $sourceHash = hash('sha256', $contents);
        $snapshotPath = 'storage/app/core-panel/scaffolds/'.$sourceHash;

        if (! is_dir(dirname($basePath.'/'.$snapshotPath))) {
            mkdir(dirname($basePath.'/'.$snapshotPath), 0777, true);
        }
        file_put_contents($basePath.'/'.$snapshotPath, $contents);

        $manifestFiles[$relativePath] = [
            'destination_hash' => $sourceHash,
            'package_version' => $packageVersion,
            'snapshot' => $snapshotPath,
            'source_hash' => $sourceHash,
        ];
    }

    file_put_contents($basePath.'/storage/app/core-panel/scaffolds.json', json_encode([
        '_meta' => [
            'package_version' => $packageVersion,
        ],
        'files' => $manifestFiles,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
}

function currentCorePanelPackageVersion(): string
{
    $contents = file_get_contents(__DIR__.'/../../config/app-version.json');
    $decoded = json_decode((string) $contents, true, 512, JSON_THROW_ON_ERROR);

    expect($decoded)->toBeArray()
        ->and($decoded['release_version'] ?? null)->toBeString();

    return $decoded['release_version'];
}

/**
 * @return list<string>
 */
function versionedUpdateScaffoldPaths(): array
{
    $contents = file_get_contents(__DIR__.'/../../src/Support/ScaffoldsCorePanelStubs.php');

    expect($contents)->toBeString();

    preg_match(
        '/private const VERSIONED_UPDATE_SCAFFOLDS = \[(.*?)\];/s',
        (string) $contents,
        $matches,
    );

    expect($matches[1] ?? null)->toBeString();

    preg_match_all("/'([^']+)'/", $matches[1], $paths);

    return $paths[1];
}

it('versions the user record type scaffold with the user management views', function (): void {
    expect(versionedUpdateScaffoldPaths())->toContain(
        'resources/js/pages/Admin/Users/Index.vue',
        'resources/js/pages/Admin/Users/Show.vue',
        'resources/js/pages/Admin/Users/components/UserSecurityTab.vue',
        'resources/js/pages/Admin/Users/components/UserSessionsTab.vue',
        'resources/js/pages/Admin/Users/components/UsersTableTab.vue',
        'resources/js/types/core-panel.ts',
    );
});

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

it('adopts unmanaged published assets during force updates', function (): void {
    $basePath = makePublishBasePath('force-adopt-unmanaged');
    $target = $basePath.'/resources/js/components/FormBuilder/FormRenderer.vue';

    mkdir(dirname($target), 0777, true);
    file_put_contents($target, "<template>\n    <div>legacy local component</div>\n</template>\n");

    $this->artisan('core-panel:update', [
        '--dry-run' => true,
        '--base-path' => $basePath,
    ])->expectsOutputToContain('destination is not managed by the publish manifest')
        ->assertExitCode(0);

    expect(file_exists($basePath.'/storage/app/core-panel/published.json'))->toBeFalse();

    $this->artisan('core-panel:update', [
        '--force' => true,
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $backups = glob($basePath.'/.core-panel-backups/*/resources/js/components/FormBuilder/FormRenderer.vue');

    expect($backups)->not->toBeFalse()
        ->and($backups)->not->toBeEmpty()
        ->and(file_get_contents($target))->not->toContain('legacy local component')
        ->and(readManifest($basePath))->toContain('core-panel-components');
});

it('does not overwrite application scaffolds during force updates', function (): void {
    $basePath = makePublishBasePath('force-scaffold-preserve');
    $target = $basePath.'/resources/js/pages/Admin/Settings/Index.vue';

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

it('does not merge untracked existing package json during updates', function (): void {
    $basePath = makePublishBasePath('untracked-package-json-update');
    $target = $basePath.'/package.json';
    $hostPackage = [
        'name' => 'host-app',
        'private' => true,
        'scripts' => [
            'custom' => 'echo host',
        ],
        'dependencies' => [
            'axios' => '^1.7.0',
        ],
        'devDependencies' => [
            'sass' => '^1.93.2',
            'vitest' => '^3.0.0',
        ],
    ];

    mkdir($basePath, 0777, true);
    file_put_contents($target, json_encode($hostPackage, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

    $this->artisan('core-panel:update', [
        '--force' => true,
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $packageJson = json_decode((string) file_get_contents($target), true, 512, JSON_THROW_ON_ERROR);

    expect($packageJson)->toBe($hostPackage)
        ->and(glob($basePath.'/.core-panel-backups/*/package.json'))->toBe([]);
});

it('merges manifest-managed existing package json during updates', function (): void {
    $basePath = makePublishBasePath('managed-package-json-update');
    $target = $basePath.'/package.json';
    $hostPackage = [
        'name' => 'host-app',
        'private' => true,
        'scripts' => [
            'custom' => 'echo host',
        ],
        'dependencies' => [
            'axios' => '^1.7.0',
        ],
        'devDependencies' => [
            'sass' => '^1.93.2',
            'vitest' => '^3.0.0',
        ],
    ];
    $hostContents = json_encode($hostPackage, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;

    mkdir($basePath, 0777, true);
    file_put_contents($target, $hostContents);
    seedScaffoldManifest($basePath, 'package.json', $hostContents);

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $packageJson = json_decode((string) file_get_contents($target), true, 512, JSON_THROW_ON_ERROR);

    expect($packageJson)->toBeArray()
        ->and($packageJson['name'])->toBe('host-app')
        ->and($packageJson['scripts'])->toHaveKey('custom')
        ->and($packageJson['scripts'])->toHaveKey('build')
        ->and($packageJson['dependencies'])->toHaveKey('axios')
        ->and($packageJson['dependencies'])->toHaveKey('vue')
        ->and($packageJson['devDependencies'])->toHaveKey('vitest')
        ->and($packageJson['devDependencies'])->toHaveKey('@vitejs/plugin-vue')
        ->and($packageJson['devDependencies'])->not->toHaveKey('sass')
        ->and(glob($basePath.'/.core-panel-backups/*/package.json'))->not->toBe([]);
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

it('does not create unlisted missing application scaffolds just because another scaffold has an old baseline', function (): void {
    $basePath = makePublishBasePath('missing-unlisted-scaffold-with-baseline');
    $managedContents = "<?php\n\n// old managed console scaffold\n";
    $unlistedTarget = $basePath.'/resources/js/pages/Admin/Settings/Index.vue';

    seedScaffoldManifest($basePath, 'routes/console.php', $managedContents);

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    expect(file_exists($unlistedTarget))->toBeFalse();
});

it('restores missing manifest-managed application scaffolds during updates', function (): void {
    $basePath = makePublishBasePath('missing-managed-scaffold');
    $target = $basePath.'/routes/console.php';
    $legacyContents = "<?php\n\n// old managed console scaffold\n";

    seedScaffoldManifest($basePath, 'routes/console.php', $legacyContents);

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    expect(file_exists($target))->toBeTrue()
        ->and(file_get_contents($target))->toContain("Schedule::command('horizon:snapshot')->everyFiveMinutes();");
});

it('creates explicitly versioned missing application scaffolds during updates', function (): void {
    $basePath = makePublishBasePath('missing-versioned-scaffold');

    mkdir($basePath, 0777, true);

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    foreach (versionedUpdateScaffoldPaths() as $relativePath) {
        expect(file_exists($basePath.'/'.$relativePath))
            ->toBeTrue("Expected {$relativePath} to be created during managed-only updates.");
    }
});

it('creates explicitly versioned missing application scaffolds without per-file current manifest entries', function (): void {
    $basePath = makePublishBasePath('missing-versioned-scaffold-no-file-entry');
    $managedContents = "<?php\n\n// current managed console scaffold\n";

    seedScaffoldManifest($basePath, 'routes/console.php', $managedContents, currentCorePanelPackageVersion());

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    foreach (versionedUpdateScaffoldPaths() as $relativePath) {
        expect(file_exists($basePath.'/'.$relativePath))
            ->toBeTrue("Expected {$relativePath} to be created when it has no current scaffold manifest entry.");
    }
});

it('updates explicitly versioned existing application scaffolds without a previous baseline', function (): void {
    $basePath = makePublishBasePath('existing-versioned-scaffold');

    foreach (versionedUpdateScaffoldPaths() as $relativePath) {
        $target = $basePath.'/'.$relativePath;

        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0777, true);
        }

        file_put_contents($target, "old versioned scaffold: {$relativePath}\n");
    }

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $manifest = json_decode((string) file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json'), true, 512, JSON_THROW_ON_ERROR);

    foreach (versionedUpdateScaffoldPaths() as $relativePath) {
        $target = $basePath.'/'.$relativePath;

        expect(file_get_contents($target))
            ->not->toContain("old versioned scaffold: {$relativePath}")
            ->and(glob($basePath.'/.core-panel-backups/*/'.$relativePath))
            ->not->toBeEmpty("Expected {$relativePath} to be backed up before update.")
            ->and($manifest['files'][$relativePath] ?? null)
            ->toBeArray("Expected {$relativePath} to be recorded in the scaffold manifest.");
    }
});

it('does not create extra backups for current versioned scaffolds that are already up to date', function (): void {
    $basePath = makePublishBasePath('current-versioned-scaffold-repeat');

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $manifest = (string) file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json');

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    foreach (versionedUpdateScaffoldPaths() as $relativePath) {
        expect(glob($basePath.'/.core-panel-backups/*/'.$relativePath))
            ->toBeEmpty("Expected {$relativePath} not to be backed up when the package version is unchanged.");
    }

    expect((string) file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json'))->toBe($manifest);
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
