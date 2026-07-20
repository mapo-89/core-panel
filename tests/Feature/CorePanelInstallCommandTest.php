<?php

declare(strict_types=1);

use CorePanel\Contracts\CorePanelInstallerInterface;
use CorePanel\Database\Seeders\CorePanelPermissionSeeder;
use CorePanel\Domains\Permission\Actions\ResyncAccessMatrixAction;
use CorePanel\Support\Install\CorePanelInstaller;
use CorePanel\Support\Install\CorePanelInstallOptions;
use CorePanel\Support\Migrations\HostMigrationExecutor;
use CorePanel\Support\Migrations\HostMigrationRunner;
use CorePanel\Support\Migrations\MigrationPathResolver;
use CorePanel\Support\ScaffoldsCorePanelStubs;
use CorePanel\Support\SynchronizesEnvironmentFile;
use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

use function Pest\Laravel\mock;

final class CapturingInstaller implements CorePanelInstallerInterface
{
    public ?CorePanelInstallOptions $options = null;

    public function install(CorePanelInstallOptions $options, Command $command): void
    {
        $this->options = $options;
    }
}

final class RecordingInstallerCommand extends Command
{
    protected $signature = 'core-panel:test-installer-runner';

    /**
     * @var list<array{command:string, arguments:array<string, mixed>}>
     */
    public array $calls = [];

    public function __construct(
        private readonly CorePanelInstaller $installer,
        private readonly CorePanelInstallOptions $options,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->installer->install($this->options, $this);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function call($command, array $arguments = []): int
    {
        $this->calls[] = [
            'command' => (string) $command,
            'arguments' => $arguments,
        ];

        return self::SUCCESS;
    }
}

final class InstallerRoleAwareUser extends AuthenticatableUser
{
    use HasRoles;
    use HasUuids;

    protected $table = 'users';

    protected $guarded = [];

    protected string $guard_name = 'web';

    public function supportsCorePanelLocale(): bool
    {
        return true;
    }
}

final class InstallerLegacyStyleUser extends AuthenticatableUser
{
    use HasRoles;

    protected $table = 'users';

    protected string $guard_name = 'web';

    public function supportsCorePanelLocale(): bool
    {
        return true;
    }
}

final class InstallerPlainLegacyUser extends AuthenticatableUser
{
    protected $table = 'users';

    protected $guarded = [];

    public function supportsCorePanelLocale(): bool
    {
        return true;
    }
}

final class InstallerRoleAwareCommand extends Command
{
    protected $signature = 'core-panel:test-installer-admin-user';

    public function handle(): int
    {
        return self::SUCCESS;
    }
}

final class RecordingMigrationCommand extends Command
{
    protected $signature = 'core-panel:test-migration-runner';

    /**
     * @var list<array{command:string, arguments:array<string, mixed>}>
     */
    public array $calls = [];

    public function handle(): int
    {
        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function call($command, array $arguments = []): int
    {
        $this->calls[] = [
            'command' => (string) $command,
            'arguments' => $arguments,
        ];

        return self::SUCCESS;
    }
}

it('supports no-interaction installer defaults', function (): void {
    $installer = new CapturingInstaller;
    $expectedDefaultAppUrl = sprintf(
        'https://%s.test',
        trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', strtolower(basename(base_path()))), '-'),
    );

    app()->instance(CorePanelInstallerInterface::class, $installer);

    $this->artisan('core-panel:install', ['--no-interaction' => true])->assertExitCode(0);

    expect($installer->options)->toBeInstanceOf(CorePanelInstallOptions::class)
        ->and($installer->options?->appUrl)->toBe($expectedDefaultAppUrl)
        ->and($installer->options?->databaseConnection)->toBe('pgsql')
        ->and($installer->options?->databaseHost)->toBe('127.0.0.1')
        ->and($installer->options?->databasePort)->toBe('5432')
        ->and($installer->options?->databaseName)->toBe('core_panel')
        ->and($installer->options?->databaseUsername)->toBe('core_panel')
        ->and($installer->options?->databasePassword)->toBe('core_panel')
        ->and($installer->options?->databaseTestName)->toBe('core_panel_test')
        ->and($installer->options?->centralDomain)->toBeNull()
        ->and($installer->options?->enableHorizon)->toBeTrue()
        ->and($installer->options?->enableSocialLogin)->toBeFalse()
        ->and($installer->options?->runSeeders)->toBeTrue()
        ->and($installer->options?->installFrontend)->toBeTrue()
        ->and($installer->options?->installTenancy)->toBeFalse()
        ->and($installer->options?->createAdmin)->toBeTrue();
});

it('rejects installation when core panel is already installed', function (): void {
    $installer = new CapturingInstaller;
    $manifestPath = base_path('storage/app/core-panel/scaffolds.json');

    if (! is_dir(dirname($manifestPath))) {
        mkdir(dirname($manifestPath), 0777, true);
    }

    file_put_contents($manifestPath, json_encode([
        '_meta' => [
            'package_version' => '1.0.0',
        ],
        'files' => [],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

    app()->instance(CorePanelInstallerInterface::class, $installer);

    try {
        $this->artisan('core-panel:install', ['--no-interaction' => true])
            ->expectsOutputToContain('Laravel CorePanel is already installed. Run core-panel:update to update an existing installation.')
            ->assertExitCode(1);

        expect($installer->options)->toBeNull();
    } finally {
        @unlink($manifestPath);
    }
});

it('runs host migrations in global timestamp order across domain directories', function (): void {
    $temporaryBasePath = sys_get_temp_dir().'/core-panel-domain-migration-order-'.bin2hex(random_bytes(5));

    mkdir($temporaryBasePath.'/database/migrations/auth', 0777, true);
    mkdir($temporaryBasePath.'/database/migrations/users', 0777, true);
    mkdir($temporaryBasePath.'/database/migrations/files', 0777, true);
    mkdir($temporaryBasePath.'/database/migrations/tenancy', 0777, true);
    mkdir($temporaryBasePath.'/database/migrations/tenant/users', 0777, true);

    file_put_contents($temporaryBasePath.'/database/migrations/auth/2016_06_01_000001_create_oauth_auth_codes_table.php', '<?php');
    file_put_contents($temporaryBasePath.'/database/migrations/users/0001_01_01_000000_create_users_table.php', '<?php');
    file_put_contents($temporaryBasePath.'/database/migrations/files/2019_01_01_000001_create_media_table.php', '<?php');
    file_put_contents($temporaryBasePath.'/database/migrations/tenancy/2026_01_01_000001_create_tenants_table.php', '<?php');
    file_put_contents($temporaryBasePath.'/database/migrations/tenant/users/0001_01_01_000000_create_users_table.php', '<?php');

    /** @var list<string> $migrationFiles */
    $migrationFiles = app(HostMigrationExecutor::class)->migrationFiles($temporaryBasePath);

    expect(array_map('basename', $migrationFiles))->toBe([
        '0001_01_01_000000_create_users_table.php',
        '2016_06_01_000001_create_oauth_auth_codes_table.php',
        '2019_01_01_000001_create_media_table.php',
        '2026_01_01_000001_create_tenants_table.php',
    ])
        ->and(MigrationPathResolver::central($temporaryBasePath))->toBe($migrationFiles);
});

it('resolves tenant migrations recursively in global timestamp order', function (): void {
    $temporaryBasePath = sys_get_temp_dir().'/core-panel-tenant-migration-order-'.bin2hex(random_bytes(5));

    mkdir($temporaryBasePath.'/database/migrations/auth', 0777, true);
    mkdir($temporaryBasePath.'/database/migrations/tenant/auth', 0777, true);
    mkdir($temporaryBasePath.'/database/migrations/tenant/users', 0777, true);
    mkdir($temporaryBasePath.'/database/migrations/tenant/files', 0777, true);

    file_put_contents($temporaryBasePath.'/database/migrations/auth/2016_06_01_000001_create_oauth_auth_codes_table.php', '<?php');
    file_put_contents($temporaryBasePath.'/database/migrations/tenant/auth/2016_06_01_000001_create_oauth_auth_codes_table.php', '<?php');
    file_put_contents($temporaryBasePath.'/database/migrations/tenant/users/0001_01_01_000000_create_users_table.php', '<?php');
    file_put_contents($temporaryBasePath.'/database/migrations/tenant/files/2019_01_01_000001_create_media_table.php', '<?php');

    $migrationFiles = MigrationPathResolver::tenant($temporaryBasePath);

    expect(array_map('basename', $migrationFiles))->toBe([
        '0001_01_01_000000_create_users_table.php',
        '2016_06_01_000001_create_oauth_auth_codes_table.php',
        '2019_01_01_000001_create_media_table.php',
    ]);
});

it('executes host migrations and reports the applied migration basenames', function (): void {
    $temporaryBasePath = sys_get_temp_dir().'/core-panel-domain-migration-execute-'.bin2hex(random_bytes(5));
    $database = (string) config('database.default');

    mkdir($temporaryBasePath.'/database/migrations/auth', 0777, true);
    mkdir($temporaryBasePath.'/database/migrations/users', 0777, true);

    file_put_contents($temporaryBasePath.'/database/migrations/auth/2016_06_01_000001_create_oauth_auth_codes_table.php', '<?php');
    file_put_contents($temporaryBasePath.'/database/migrations/users/0001_01_01_000000_create_users_table.php', '<?php');

    Schema::dropIfExists('migrations');
    Schema::create('migrations', function ($table): void {
        $table->id();
        $table->string('migration');
        $table->integer('batch');
    });

    $kernel = mock(Kernel::class);
    $kernel->shouldReceive('call')
        ->once()
        ->with('migrate', Mockery::on(static function (array $arguments) use ($database): bool {
            return $arguments['--database'] === $database
                && $arguments['--force'] === false
                && $arguments['--realpath'] === true
                && array_map('basename', $arguments['--path']) === [
                    '0001_01_01_000000_create_users_table.php',
                    '2016_06_01_000001_create_oauth_auth_codes_table.php',
                ];
        }))
        ->andReturnUsing(static function () use ($database): int {
            DB::connection($database)->table('migrations')->insert([
                ['migration' => '0001_01_01_000000_create_users_table', 'batch' => 1],
                ['migration' => '2016_06_01_000001_create_oauth_auth_codes_table', 'batch' => 1],
            ]);

            return 0;
        });
    $kernel->shouldReceive('output')
        ->once()
        ->andReturn('Migrated successfully.');

    app()->instance(Kernel::class, $kernel);

    $result = app(HostMigrationExecutor::class)->execute($database, false, $temporaryBasePath);

    expect($result)->toBe([
        'executed_migrations' => [
            '0001_01_01_000000_create_users_table',
            '2016_06_01_000001_create_oauth_auth_codes_table',
        ],
        'output' => 'Migrated successfully.',
    ]);
});

it('skips executing host migrations when no host migration files exist', function (): void {
    $temporaryBasePath = sys_get_temp_dir().'/core-panel-empty-host-migrations-'.bin2hex(random_bytes(5));
    $database = (string) config('database.default');

    mkdir($temporaryBasePath.'/database/migrations', 0777, true);

    $kernel = mock(Kernel::class);
    $kernel->shouldNotReceive('call');
    $kernel->shouldNotReceive('output');

    app()->instance(Kernel::class, $kernel);

    $result = app(HostMigrationExecutor::class)->execute($database, false, $temporaryBasePath);

    expect($result)->toBe([
        'executed_migrations' => [],
        'output' => '',
    ]);
});

it('keeps tenant migration path scoped when no tenant migration files exist', function (): void {
    $temporaryBasePath = sys_get_temp_dir().'/core-panel-empty-tenant-migrations-'.bin2hex(random_bytes(5));

    mkdir($temporaryBasePath.'/database/migrations', 0777, true);

    expect(MigrationPathResolver::tenant($temporaryBasePath))->toBe([
        $temporaryBasePath.'/database/migrations/tenant',
    ]);
});

it('executes host migrations in a single batch-preserving migrate call', function (): void {
    $temporaryBasePath = sys_get_temp_dir().'/core-panel-domain-migration-run-'.bin2hex(random_bytes(5));

    mkdir($temporaryBasePath.'/database/migrations/auth', 0777, true);
    mkdir($temporaryBasePath.'/database/migrations/users', 0777, true);

    file_put_contents($temporaryBasePath.'/database/migrations/auth/2016_06_01_000001_create_oauth_auth_codes_table.php', '<?php');
    file_put_contents($temporaryBasePath.'/database/migrations/users/0001_01_01_000000_create_users_table.php', '<?php');

    $runner = app(HostMigrationRunner::class);
    $command = new RecordingMigrationCommand;

    $runner->run($command, $temporaryBasePath);

    expect($command->calls)->toHaveCount(1)
        ->and($command->calls[0]['command'])->toBe('migrate')
        ->and($command->calls[0]['arguments']['--force'])->toBeTrue()
        ->and($command->calls[0]['arguments']['--realpath'])->toBeTrue()
        ->and(array_map('basename', $command->calls[0]['arguments']['--path']))->toBe([
            '0001_01_01_000000_create_users_table.php',
            '2016_06_01_000001_create_oauth_auth_codes_table.php',
        ]);
});

it('rejects duplicate host migration basenames across domain directories', function (): void {
    $temporaryBasePath = sys_get_temp_dir().'/core-panel-domain-migration-duplicates-'.bin2hex(random_bytes(5));

    mkdir($temporaryBasePath.'/database/migrations/auth', 0777, true);
    mkdir($temporaryBasePath.'/database/migrations/users', 0777, true);

    file_put_contents($temporaryBasePath.'/database/migrations/auth/2026_01_01_000001_create_users_table.php', '<?php');
    file_put_contents($temporaryBasePath.'/database/migrations/users/2026_01_01_000001_create_users_table.php', '<?php');

    $runner = app(HostMigrationRunner::class);
    $command = new RecordingMigrationCommand;

    expect(fn () => $runner->run($command, $temporaryBasePath))
        ->toThrow(RuntimeException::class, 'Duplicate host migration basenames detected');
});

it('automatically disables seeders when migrations are disabled', function (): void {
    $installer = new CapturingInstaller;

    app()->instance(CorePanelInstallerInterface::class, $installer);

    $this->artisan('core-panel:install', [
        '--no-interaction' => true,
        '--run-migrations' => 'false',
    ])->assertExitCode(0);

    expect($installer->options)->toBeInstanceOf(CorePanelInstallOptions::class)
        ->and($installer->options?->runMigrations)->toBeFalse()
        ->and($installer->options?->runSeeders)->toBeFalse();
});

it('rejects enabling seeders when migrations are disabled', function (): void {
    $installer = new CapturingInstaller;

    app()->instance(CorePanelInstallerInterface::class, $installer);

    $this->artisan('core-panel:install', [
        '--no-interaction' => true,
        '--run-migrations' => 'false',
        '--run-seeders' => 'true',
    ])->expectsOutputToContain('Seeders cannot run when migrations are disabled.')
        ->assertExitCode(1);
});

it('captures the non-tenancy installer options', function (): void {
    $installer = new CapturingInstaller;

    app()->instance(CorePanelInstallerInterface::class, $installer);

    $this->artisan('core-panel:install', [
        '--no-interaction' => true,
        '--app-url' => 'https://central.example.test',
        '--db-connection' => 'pgsql',
        '--db-host' => 'db-host',
        '--db-port' => '5433',
        '--db-database' => 'core_panel_pkg',
        '--db-username' => 'postgres',
        '--db-password' => 'secret',
        '--db-database-test' => 'core_panel_pkg_test',
        '--default-locale' => 'en',
        '--fallback-locale' => 'de',
        '--run-seeders' => 'false',
        '--install-frontend' => 'true',
        '--install-tenancy' => 'true',
        '--central-domain' => 'admin.example.test',
    ])->assertExitCode(0);

    expect($installer->options)->toBeInstanceOf(CorePanelInstallOptions::class)
        ->and($installer->options?->appUrl)->toBe('https://central.example.test')
        ->and($installer->options?->databaseConnection)->toBe('pgsql')
        ->and($installer->options?->databaseHost)->toBe('db-host')
        ->and($installer->options?->databasePort)->toBe('5433')
        ->and($installer->options?->databaseName)->toBe('core_panel_pkg')
        ->and($installer->options?->databaseUsername)->toBe('postgres')
        ->and($installer->options?->databasePassword)->toBe('secret')
        ->and($installer->options?->databaseTestName)->toBe('core_panel_pkg_test')
        ->and($installer->options?->centralDomain)->toBe('admin.example.test')
        ->and($installer->options?->defaultLocale)->toBe('en')
        ->and($installer->options?->fallbackLocale)->toBe('de')
        ->and($installer->options?->enableHorizon)->toBeTrue()
        ->and($installer->options?->enableSocialLogin)->toBeFalse()
        ->and($installer->options?->runSeeders)->toBeFalse()
        ->and($installer->options?->installFrontend)->toBeTrue()
        ->and($installer->options?->installTenancy)->toBeTrue();
});

it('normalizes tenancy central domains from full urls to bare hosts', function (): void {
    $installer = new CapturingInstaller;

    app()->instance(CorePanelInstallerInterface::class, $installer);

    $this->artisan('core-panel:install', [
        '--no-interaction' => true,
        '--install-tenancy' => 'true',
        '--app-url' => 'https://core-panel-app.test',
        '--central-domain' => 'https://admin.core-panel-app.test/settings',
    ])->assertExitCode(0);

    expect($installer->options)->toBeInstanceOf(CorePanelInstallOptions::class)
        ->and($installer->options?->centralDomain)->toBe('admin.core-panel-app.test');
});

it('derives the mysql default port and test database name from the selected driver and database', function (): void {
    $installer = new CapturingInstaller;

    app()->instance(CorePanelInstallerInterface::class, $installer);

    $this->artisan('core-panel:install', [
        '--no-interaction' => true,
        '--db-connection' => 'mysql',
        '--db-database' => 'core_panel_mysql',
    ])->assertExitCode(0);

    expect($installer->options)->toBeInstanceOf(CorePanelInstallOptions::class)
        ->and($installer->options?->databaseConnection)->toBe('mysql')
        ->and($installer->options?->databasePort)->toBe('3306')
        ->and($installer->options?->databaseTestName)->toBe('core_panel_mysql_test');
});

it('contains the passport key generation step for passport installs', function (): void {
    $contents = file_get_contents(__DIR__.'/../../src/Support/Install/CorePanelInstaller.php');

    expect($contents)->not->toContain("'passport-config'")
        ->and($contents)->not->toContain("'passport-migrations'")
        ->and($contents)->toContain("'passport:keys'")
        ->and($contents)->toContain("runStep(\$command, 'Ensuring Passport personal access client'")
        ->and($contents)->toContain("if (! Schema::hasTable('oauth_clients')) {");
});

it('persists the generated application key back into the environment file during installation', function (): void {
    $contents = file_get_contents(__DIR__.'/../../src/Support/Install/CorePanelInstaller.php');

    expect($contents)->toContain("\$command->call('key:generate', ['--force' => true]);")
        ->and($contents)->toContain('$this->environment->sync(overrides: [')
        ->and($contents)->toContain("'APP_KEY' => \$appKey,");
});

it('adds the local tenancy addon path repository and requirement to the host composer manifest', function (): void {
    $temporaryBasePath = sys_get_temp_dir().'/core-panel-install-tenancy-composer-'.bin2hex(random_bytes(5));

    mkdir($temporaryBasePath, 0777, true);

    $composerPath = $temporaryBasePath.'/composer.json';

    file_put_contents($composerPath, json_encode([
        'name' => 'acme/test-app',
        'require' => [
            'php' => '^8.5',
            'mapo-89/core-panel' => 'dev-main',
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

    $installer = app(CorePanelInstaller::class);
    $method = new ReflectionMethod($installer, 'ensureComposerManifestContainsAddon');
    $method->setAccessible(true);

    $method->invoke($installer, $composerPath, [
        'package' => 'mapo-89/core-panel-tenancy',
        'version' => 'dev-main',
        'path' => '/tmp/core-panel/packages/core-panel-tenancy',
    ]);

    /** @var array{repositories?:array<int, array<string, mixed>>, require:array<string, string>} $composer */
    $composer = json_decode((string) file_get_contents($composerPath), true, 512, JSON_THROW_ON_ERROR);

    expect($composer['require']['mapo-89/core-panel-tenancy'] ?? null)->toBe('dev-main')
        ->and($composer['repositories'] ?? [])->toContain([
            'type' => 'path',
            'url' => '/tmp/core-panel/packages/core-panel-tenancy',
            'options' => [
                'symlink' => true,
                'versions' => [
                    'mapo-89/core-panel-tenancy' => 'dev-main',
                ],
            ],
        ]);
});

it('updates an existing local tenancy addon path repository to include the expected version override', function (): void {
    $temporaryBasePath = sys_get_temp_dir().'/core-panel-install-tenancy-composer-update-'.bin2hex(random_bytes(5));

    mkdir($temporaryBasePath, 0777, true);

    $composerPath = $temporaryBasePath.'/composer.json';

    file_put_contents($composerPath, json_encode([
        'name' => 'acme/test-app',
        'repositories' => [
            [
                'type' => 'path',
                'url' => '/tmp/core-panel/packages/core-panel-tenancy',
                'options' => [
                    'symlink' => true,
                ],
            ],
        ],
        'require' => [
            'php' => '^8.5',
            'mapo-89/core-panel' => 'dev-main',
            'mapo-89/core-panel-tenancy' => 'dev-main',
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

    $installer = app(CorePanelInstaller::class);
    $method = new ReflectionMethod($installer, 'ensureComposerManifestContainsAddon');
    $method->setAccessible(true);

    $method->invoke($installer, $composerPath, [
        'package' => 'mapo-89/core-panel-tenancy',
        'version' => 'dev-main',
        'path' => '/tmp/core-panel/packages/core-panel-tenancy',
    ]);

    /** @var array{repositories?:array<int, array<string, mixed>>} $composer */
    $composer = json_decode((string) file_get_contents($composerPath), true, 512, JSON_THROW_ON_ERROR);

    expect($composer['repositories'] ?? [])->toContain([
        'type' => 'path',
        'url' => '/tmp/core-panel/packages/core-panel-tenancy',
        'options' => [
            'symlink' => true,
            'versions' => [
                'mapo-89/core-panel-tenancy' => 'dev-main',
            ],
        ],
    ]);
});

it('keeps frontend overlays out of the installer publish flow because the scaffold already ships resources js assets', function (): void {
    $contents = file_get_contents(__DIR__.'/../../src/Support/Install/CorePanelInstaller.php');

    expect($contents)->not->toContain("\$command->call('vendor:publish', ['--tag' => PublishTag::Theme->value")
        ->and($contents)->not->toContain("\$command->call('vendor:publish', ['--tag' => PublishTag::Components->value");
});

it('clears optimized caches after synchronizing the environment and allows seeders to be skipped', function (): void {
    $contents = file_get_contents(__DIR__.'/../../src/Support/Install/CorePanelInstaller.php');
    $commandContents = file_get_contents(__DIR__.'/../../src/Console/InstallCommand.php');
    $prepareDatabasePosition = strpos($contents, "runStep(\$command, 'Preparing database connection'");
    $clearCachesPosition = strpos($contents, "runStep(\$command, 'Clearing optimized caches'");
    $publishAssetsPosition = strpos($contents, "runStep(\$command, 'Preparing package runtime assets'");
    $passportClientPosition = strpos($contents, "runStep(\$command, 'Ensuring Passport personal access client'");

    expect($prepareDatabasePosition)->not->toBeFalse()
        ->and($clearCachesPosition)->not->toBeFalse()
        ->and($prepareDatabasePosition)->toBeLessThan($clearCachesPosition)
        ->and($publishAssetsPosition)->not->toBeFalse()
        ->and($passportClientPosition)->not->toBeFalse()
        ->and($publishAssetsPosition)->toBeLessThan($passportClientPosition);

    expect($contents)->toContain("runStep(\$command, 'Clearing optimized caches'")
        ->and($contents)->toContain('$this->refreshResolvedRuntimeServices();')
        ->and($contents)->toContain('if ($options->runMigrations) {')
        ->and($contents)->toContain('$this->migrations->run($command);')
        ->and($contents)->toContain('$this->applyRuntimeConfiguration($options, $environment);')
        ->and($contents)->toContain("\$command->call('optimize:clear')")
        ->and($contents)->toContain("\$originalCacheStore = config('cache.default');")
        ->and($contents)->toContain("config()->set('cache.default', 'array');")
        ->and($contents)->toContain("config()->set('cache.default', \$originalCacheStore);")
        ->and($contents)->toContain("config()->set('permission.cache.store', 'array');")
        ->and($contents)->toContain('$this->syncRuntimeEnvironment([')
        ->and($contents)->toContain("'DB_CACHE_CONNECTION' => \$options->databaseConnection")
        ->and($contents)->toContain("'DB_CACHE_LOCK_CONNECTION' => \$options->databaseConnection")
        ->and($contents)->toContain("'CACHE_STORE' => 'array'")
        ->and($contents)->toContain("'SESSION_DRIVER' => 'array'")
        ->and($contents)->toContain("'QUEUE_CONNECTION' => 'sync'")
        ->and($contents)->toContain("config()->set('cache.stores.database.connection', \$options->databaseConnection);")
        ->and($contents)->toContain("config()->set('cache.stores.database.lock_connection', \$options->databaseConnection);")
        ->and($contents)->toContain("app()->forgetInstance('db');")
        ->and($contents)->toContain("app()->forgetInstance('cache');")
        ->and($contents)->toContain("app()->forgetInstance('cache.store');")
        ->and($contents)->toContain('app()->forgetInstance(PermissionRegistrar::class);')
        ->and($contents)->toContain("Facade::clearResolvedInstance('db');")
        ->and($contents)->toContain("Facade::clearResolvedInstance('cache');")
        ->and($contents)->toContain("Facade::clearResolvedInstance('cache.store');")
        ->and($contents)->toContain('$this->ensureSeederPrerequisites();')
        ->and($contents)->toContain('$this->runInstallerSeeder($command, CorePanelPermissionSeeder::class);')
        ->and($contents)->toContain('$this->runInstallerSeeder($command, CorePanelSettingsSeeder::class);')
        ->and($contents)->toContain('private function runInstallerSeeder(Command $command, string $seederClass): void')
        ->and($contents)->toContain('$seeder->setContainer(app());')
        ->and($contents)->toContain('$seeder->setCommand($command);')
        ->and($contents)->toContain('$seeder->__invoke();')
        ->and($contents)->toContain('private function ensureSeederPrerequisites(): void')
        ->and($contents)->toContain("! \$this->permissionTablesExist() || ! Schema::hasTable('settings')")
        ->and($contents)->toContain('Cannot run CorePanel seeders before the required tables exist. Run migrations first or disable seeders during installation.')
        ->and($contents)->toContain("'APP_URL' => \$options->appUrl")
        ->and($contents)->toContain("config()->set('app.url', \$options->appUrl);")
        ->and($contents)->toContain("'CENTRAL_DOMAINS' => \$options->centralDomain")
        ->and($contents)->toContain("'TENANCY_CENTRAL_CONNECTION' => \$options->databaseConnection")
        ->and($contents)->toContain("'TENANCY_TEMPLATE_TENANT_CONNECTION' => ''")
        ->and($contents)->toContain("config()->set('tenancy.central_domains', [\$options->centralDomain]);")
        ->and($contents)->toContain("config()->set('tenancy.database.central_connection', \$options->databaseConnection);")
        ->and($contents)->toContain("config()->set('tenancy.database.template_tenant_connection', \$options->databaseConnection);")
        ->and($contents)->toContain('if ($options->runSeeders)')
        ->and($contents)->not->toContain("\$command->call('db:seed'")
        ->and($contents)->toContain('if ($options->installFrontend)')
        ->and($contents)->toContain('Preparing database connection')
        ->and($contents)->toContain('CREATE DATABASE IF NOT EXISTS')
        ->and($contents)->toContain('SELECT 1 FROM pg_database')
        ->and($contents)->toContain('Database reachable via %s://%s:%s/%s')
        ->and($contents)->toContain('Installing local tenancy addon dependency')
        ->and($contents)->toContain("'composer'")
        ->and($contents)->toContain("'core-panel:tenancy:install'")
        ->and($contents)->toContain("'type' => 'path'")
        ->and($contents)->toContain('deleteDirectory($nodeModulesPath)')
        ->and($contents)->toContain("['npm', 'install']")
        ->and($contents)->toContain("['npm', 'run', 'build']")
        ->and($commandContents)->toContain('{--run-seeders= : true|false}')
        ->and($commandContents)->toContain("booleanOption('run-seeders', 'Seeder jetzt ausführen?', true)")
        ->and($commandContents)->toContain('private function resolveRunSeeders(bool $runMigrations): bool')
        ->and($commandContents)->toContain('Seeders cannot run when migrations are disabled. Enable migrations or set --run-seeders=false.')
        ->and($commandContents)->toContain('{--install-frontend= : true|false}')
        ->and($commandContents)->toContain("booleanOption('install-frontend', 'Node-Abhängigkeiten installieren und Frontend bauen?', true)")
        ->and($commandContents)->toContain('{--install-tenancy= : true|false}')
        ->and($commandContents)->toContain("booleanOption('install-tenancy', 'Tenancy Addon installieren?', false)")
        ->and($commandContents)->toContain('{--app-url= : Application base URL}')
        ->and($commandContents)->toContain("textOption('app-url', 'APP_URL?', \$this->defaultAppUrl())")
        ->and($commandContents)->toContain('{--central-domain= : Central domain for tenancy}')
        ->and($commandContents)->toContain("textOption('central-domain', 'Zentrale Domain für Tenancy?', \$this->appHost(\$appUrl))")
        ->and($commandContents)->toContain('{--db-connection= : Database connection driver}')
        ->and($commandContents)->toContain("select(\n                label: 'DB Treiber?'")
        ->and($commandContents)->toContain('{--sync-environment= : true|false}')
        ->and($commandContents)->toContain("booleanOption('sync-environment', 'Umgebungsdatei synchronisieren?', true)")
        ->and($commandContents)->not->toContain('{--api-driver=')
        ->and($commandContents)->not->toContain('{--publish-theme=')
        ->and($commandContents)->not->toContain('{--dark-mode=')
        ->and($commandContents)->not->toContain('{--enable-horizon=')
        ->and($commandContents)->not->toContain('{--enable-social-login=');
});

it('forgets resolved database, cache, and permission services after runtime config changes', function (): void {
    $installer = app(CorePanelInstaller::class);

    $databaseBefore = DB::getFacadeRoot();
    $cacheBefore = Cache::getFacadeRoot();
    $permissionBefore = app(PermissionRegistrar::class);

    $method = new ReflectionMethod($installer, 'refreshResolvedRuntimeServices');
    $method->setAccessible(true);
    $method->invoke($installer);

    $databaseAfter = DB::getFacadeRoot();
    $cacheAfter = Cache::getFacadeRoot();
    $permissionAfter = app(PermissionRegistrar::class);

    expect($databaseAfter)->not->toBe($databaseBefore)
        ->and($cacheAfter)->not->toBe($cacheBefore)
        ->and($permissionAfter)->not->toBe($permissionBefore);

    Facade::clearResolvedInstance('db');
    Facade::clearResolvedInstance('cache');
});

it('forces in-memory cache stores during installation runtime', function (): void {
    $installer = app(CorePanelInstaller::class);

    $method = new ReflectionMethod($installer, 'applyRuntimeConfiguration');
    $method->setAccessible(true);

    $options = new CorePanelInstallOptions(
        appUrl: 'http://core-panel-app.test',
        databaseConnection: 'pgsql',
        databaseHost: '127.0.0.1',
        databasePort: '5432',
        databaseName: 'core_panel',
        databaseUsername: 'core_panel',
        databasePassword: 'core_panel',
        databaseTestName: 'core_panel_test',
        centralDomain: null,
        defaultLocale: 'de',
        fallbackLocale: 'en',
        createAdmin: false,
        adminName: null,
        adminEmail: null,
        adminPassword: null,
        enableHorizon: true,
        enableSocialLogin: false,
        runMigrations: false,
        runSeeders: false,
        installFrontend: false,
        installTenancy: false,
        syncEnvironment: true,
        force: false,
    );

    $method->invoke($installer, $options, []);

    expect(config('cache.default'))->toBe('array')
        ->and(config('permission.cache.store'))->toBe('array')
        ->and(config('cache.stores.database.connection'))->toBe('pgsql')
        ->and(config('cache.stores.database.lock_connection'))->toBe('pgsql')
        ->and(getenv('DB_CONNECTION'))->toBe('pgsql')
        ->and(getenv('CACHE_STORE'))->toBe('array')
        ->and(getenv('SESSION_DRIVER'))->toBe('array')
        ->and(getenv('QUEUE_CONNECTION'))->toBe('sync');
});

it('skips external permission cache writes while the installer seeders run', function (): void {
    if (! corePanelTestbenchDatabaseAvailable()) {
        test()->markTestSkipped('pdo_sqlite is not available in this environment.');
    }

    config()->set('permission.teams', false);
    config()->set('permission.testing', false);

    $this->migrateScaffoldDatabase();

    config()->set('permission.cache.store', 'default');
    config()->set('cache.default', 'database');
    config()->set('cache.stores.database.connection', 'missing-installer-cache');
    config()->set('core-panel.runtime.installing', true);

    app()->forgetInstance('cache');
    app()->forgetInstance('cache.store');
    app()->forgetInstance(PermissionRegistrar::class);
    Facade::clearResolvedInstance('cache');
    Facade::clearResolvedInstance('cache.store');

    $seeder = app(CorePanelPermissionSeeder::class);
    $seeder->setContainer(app());
    $seeder->setCommand(new InstallerRoleAwareCommand);

    expect(fn () => $seeder->__invoke())->not->toThrow(Throwable::class);

    config()->set('core-panel.runtime.installing', false);
});

it('keeps environment synchronization idempotent when the installer runs twice with the same overrides', function (): void {
    $temporaryBasePath = sys_get_temp_dir().'/core-panel-install-sync-'.bin2hex(random_bytes(5));

    mkdir($temporaryBasePath, 0777, true);
    file_put_contents($temporaryBasePath.'/.env', "APP_NAME=Laravel\n");

    $environment = app(SynchronizesEnvironmentFile::class);

    $environment->sync($temporaryBasePath, [
        'APP_LOCALE' => 'de',
    ]);

    $firstRun = file_get_contents($temporaryBasePath.'/.env');

    $environment->sync($temporaryBasePath, [
        'APP_LOCALE' => 'de',
    ]);

    $secondRun = file_get_contents($temporaryBasePath.'/.env');

    expect($secondRun)->toBe($firstRun);
});

it('synchronizes tenancy database connection settings when tenancy installation is enabled', function (): void {
    $contents = file_get_contents(__DIR__.'/../../src/Support/Install/CorePanelInstaller.php');

    expect($contents)->toContain("'APP_NAME' => 'CorePanel'")
        ->and($contents)->toContain("config()->set('app.name', 'CorePanel');")
        ->and($contents)->toContain("'TENANCY_CENTRAL_CONNECTION' => \$options->databaseConnection")
        ->and($contents)->toContain("'TENANCY_TEMPLATE_TENANT_CONNECTION' => ''")
        ->and($contents)->toContain("config()->set('tenancy.database.central_connection', \$options->databaseConnection);")
        ->and($contents)->toContain("config()->set('tenancy.database.template_tenant_connection', \$options->databaseConnection);");
});

it('preserves existing environment values while still applying explicit installer overrides', function (): void {
    $temporaryBasePath = sys_get_temp_dir().'/core-panel-install-preserve-env-'.bin2hex(random_bytes(5));

    mkdir($temporaryBasePath, 0777, true);
    file_put_contents($temporaryBasePath.'/.env', implode(PHP_EOL, [
        'DB_HOST=custom-db',
        'CACHE_STORE=database',
        'APP_LOCALE=en',
        '',
    ]));

    $environment = app(SynchronizesEnvironmentFile::class);

    $environment->sync($temporaryBasePath, [
        'APP_LOCALE' => 'de',
    ]);

    $contents = (string) file_get_contents($temporaryBasePath.'/.env');

    expect($contents)->toContain('DB_HOST=custom-db')
        ->and($contents)->toContain('CACHE_STORE=database')
        ->and($contents)->toContain('APP_LOCALE=de')
        ->and($contents)->toContain('QUEUE_CONNECTION=redis');
});

it('creates an environment backup before synchronizing existing values', function (): void {
    $temporaryBasePath = sys_get_temp_dir().'/core-panel-install-backup-env-'.bin2hex(random_bytes(5));

    mkdir($temporaryBasePath, 0777, true);

    $originalContents = implode(PHP_EOL, [
        'APP_NAME=HostApp',
        'CACHE_STORE=database',
        'LEGACY_ONLY=value',
        '',
    ]);

    file_put_contents($temporaryBasePath.'/.env', $originalContents);

    $environment = app(SynchronizesEnvironmentFile::class);
    $environment->sync($temporaryBasePath, [
        'APP_LOCALE' => 'de',
    ]);

    expect(file_get_contents($temporaryBasePath.'/.env.backup'))->toBe($originalContents)
        ->and(file_get_contents($temporaryBasePath.'/.env'))->toContain('LEGACY_ONLY=value');
});

it('replaces template-managed environment values during installation synchronization', function (): void {
    $temporaryBasePath = sys_get_temp_dir().'/core-panel-install-replace-env-'.bin2hex(random_bytes(5));

    mkdir($temporaryBasePath, 0777, true);
    file_put_contents($temporaryBasePath.'/.env', implode(PHP_EOL, [
        'APP_NAME=Laravel',
        'APP_URL=https://example.test',
        'DB_CONNECTION=sqlite',
        'FILESYSTEM_DISK=local',
        'CACHE_STORE=database',
        'CUSTOM_KEEP=value',
        '',
    ]));

    $environment = app(SynchronizesEnvironmentFile::class);

    $environment->sync($temporaryBasePath, [
        'APP_URL' => 'http://127.0.0.1:8000',
        'DB_CONNECTION' => 'pgsql',
    ], true);

    $contents = (string) file_get_contents($temporaryBasePath.'/.env');

    expect($contents)->toContain('APP_NAME="CorePanel"')
        ->and($contents)->toContain('APP_URL=http://127.0.0.1:8000')
        ->and($contents)->toContain('DB_CONNECTION=pgsql')
        ->and($contents)->toContain('FILESYSTEM_DISK=public')
        ->and($contents)->toContain('CACHE_STORE=redis')
        ->and($contents)->toContain('CUSTOM_KEEP=value');
});

it('renders the synchronized environment file using the template structure', function (): void {
    $temporaryBasePath = sys_get_temp_dir().'/core-panel-install-template-structure-env-'.bin2hex(random_bytes(5));

    mkdir($temporaryBasePath, 0777, true);
    file_put_contents($temporaryBasePath.'/.env', implode(PHP_EOL, [
        'CACHE_STORE=database',
        'APP_NAME=HostApp',
        'LEGACY_ONLY=value',
        '',
    ]));

    $environment = app(SynchronizesEnvironmentFile::class);
    $environment->sync($temporaryBasePath);

    $contents = (string) file_get_contents($temporaryBasePath.'/.env');
    $templateContents = (string) file_get_contents(__DIR__.'/../../stubs/.env.example');

    expect($contents)->toContain('APP_NAME=HostApp')
        ->and($contents)->toContain('CACHE_STORE=database')
        ->and($contents)->toContain('LEGACY_ONLY=value')
        ->and(strpos($contents, 'APP_NAME=HostApp'))->toBeLessThan(
            strpos($contents, 'CACHE_STORE=database'),
        )
        ->and(str_starts_with($contents, substr($templateContents, 0, 32)))->toBeTrue();
});

it('refreshes the scaffolded pwa manifest after installation environment sync', function (): void {
    $temporaryBasePath = sys_get_temp_dir().'/core-panel-install-refresh-pwa-manifest-'.bin2hex(random_bytes(5));

    mkdir($temporaryBasePath, 0777, true);
    file_put_contents($temporaryBasePath.'/.env', "APP_NAME=Laravel\n");

    app(ScaffoldsCorePanelStubs::class)->scaffold(false, $temporaryBasePath);

    $environment = app(SynchronizesEnvironmentFile::class);
    $environment->sync($temporaryBasePath, [
        'APP_NAME' => '"CorePanel"',
    ], true);

    app(ScaffoldsCorePanelStubs::class)->refreshHostRenderedScaffolds([
        'public/manifest.json',
    ], $temporaryBasePath);

    $manifest = json_decode((string) file_get_contents($temporaryBasePath.'/public/manifest.json'), true, 512, JSON_THROW_ON_ERROR);

    expect($manifest)->toBeArray()
        ->and($manifest['name'] ?? null)->toBe('CorePanel')
        ->and($manifest['short_name'] ?? null)->toBe('CorePanel');
});

it('preserves an existing unmanaged pwa manifest during installation refresh', function (): void {
    $temporaryBasePath = sys_get_temp_dir().'/core-panel-install-preserve-host-pwa-manifest-'.bin2hex(random_bytes(5));

    mkdir($temporaryBasePath.'/public', 0777, true);
    file_put_contents($temporaryBasePath.'/.env', "APP_NAME=Laravel\n");

    $hostManifest = json_encode([
        'name' => 'Existing Host App',
        'short_name' => 'Host App',
        'display' => 'standalone',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    file_put_contents($temporaryBasePath.'/public/manifest.json', $hostManifest.PHP_EOL);

    app(ScaffoldsCorePanelStubs::class)->scaffold(false, $temporaryBasePath);

    $environment = app(SynchronizesEnvironmentFile::class);
    $environment->sync($temporaryBasePath, [
        'APP_NAME' => '"CorePanel"',
    ], true);

    app(ScaffoldsCorePanelStubs::class)->refreshHostRenderedScaffolds([
        'public/manifest.json',
    ], $temporaryBasePath);

    $manifest = (string) file_get_contents($temporaryBasePath.'/public/manifest.json');
    $scaffoldManifest = json_decode((string) file_get_contents($temporaryBasePath.'/storage/app/core-panel/scaffolds.json'), true, 512, JSON_THROW_ON_ERROR);

    expect($manifest)->toBe($hostManifest.PHP_EOL)
        ->and(data_get($scaffoldManifest, 'files.public/manifest.json'))->toBeNull();
});

it('deduplicates existing environment keys when synchronizing the environment file', function (): void {
    $temporaryBasePath = sys_get_temp_dir().'/core-panel-install-deduplicate-env-'.bin2hex(random_bytes(5));

    mkdir($temporaryBasePath, 0777, true);
    file_put_contents($temporaryBasePath.'/.env', implode(PHP_EOL, [
        'APP_NAME=Laravel',
        'DB_HOST=postgres',
        'DB_HOST=127.0.0.1',
        'CACHE_STORE=database',
        '',
    ]));

    $environment = app(SynchronizesEnvironmentFile::class);

    $environment->sync($temporaryBasePath, [
        'APP_LOCALE' => 'de',
    ]);

    $contents = (string) file_get_contents($temporaryBasePath.'/.env');

    expect(substr_count($contents, 'DB_HOST='))->toBe(1)
        ->and($contents)->not->toContain('DB_HOST=postgres')
        ->and($contents)->toContain('DB_HOST=127.0.0.1')
        ->and($contents)->toContain('APP_LOCALE=de');
});

it('automatically synchronizes managed access before assigning the installer admin to super-admin', function (): void {
    if (! corePanelTestbenchDatabaseAvailable()) {
        test()->markTestSkipped('pdo_sqlite is not available in this environment.');
    }

    config()->set('auth.providers.users.model', InstallerRoleAwareUser::class);
    config()->set('core-panel.user_model', InstallerRoleAwareUser::class);
    config()->set('permission.teams', false);
    config()->set('permission.testing', false);

    $this->migrateScaffoldDatabase();

    app(PermissionRegistrar::class)->teams = false;

    $installer = app(CorePanelInstaller::class);
    $command = new InstallerRoleAwareCommand;
    $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput));
    $options = new CorePanelInstallOptions(
        appUrl: 'http://core-panel-app.test',
        databaseConnection: 'pgsql',
        databaseHost: '127.0.0.1',
        databasePort: '5432',
        databaseName: 'core_panel',
        databaseUsername: 'core_panel',
        databasePassword: 'core_panel',
        databaseTestName: 'core_panel_test',
        centralDomain: null,
        defaultLocale: 'de',
        fallbackLocale: 'en',
        createAdmin: true,
        adminName: 'Super Admin',
        adminEmail: 'admin@example.test',
        adminPassword: 'secret-password',
        enableHorizon: true,
        enableSocialLogin: false,
        runMigrations: false,
        runSeeders: false,
        installFrontend: false,
        installTenancy: false,
        syncEnvironment: true,
        force: false,
    );

    expect(Role::query()->where('name', 'super-admin')->exists())->toBeFalse();

    $method = new ReflectionMethod($installer, 'createAdminUser');
    $method->setAccessible(true);
    $user = $method->invoke($installer, $options, $command);

    expect($user)->toBeInstanceOf(InstallerRoleAwareUser::class)
        ->and($user->email)->toBe('admin@example.test')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('secret-password', (string) $user->password))->toBeTrue()
        ->and($user->hasRole('super-admin'))->toBeTrue()
        ->and(Role::query()->where('name', 'super-admin')->exists())->toBeTrue()
        ->and(Role::query()->where('name', 'super-admin')->firstOrFail()->permissions()->count())->toBeGreaterThan(0);
});

it('creates the installer admin user against a uuid users table even when the loaded user model behaves like the laravel default', function (): void {
    if (! corePanelTestbenchDatabaseAvailable()) {
        test()->markTestSkipped('pdo_sqlite is not available in this environment.');
    }

    config()->set('auth.providers.users.model', InstallerLegacyStyleUser::class);
    config()->set('core-panel.user_model', InstallerLegacyStyleUser::class);
    config()->set('permission.teams', false);
    config()->set('permission.testing', false);

    $this->migrateScaffoldDatabase();

    app(PermissionRegistrar::class)->teams = false;

    $installer = app(CorePanelInstaller::class);
    $command = new InstallerRoleAwareCommand;
    $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput));
    $options = new CorePanelInstallOptions(
        appUrl: 'http://core-panel-app.test',
        databaseConnection: 'pgsql',
        databaseHost: '127.0.0.1',
        databasePort: '5432',
        databaseName: 'core_panel',
        databaseUsername: 'core_panel',
        databasePassword: 'core_panel',
        databaseTestName: 'core_panel_test',
        centralDomain: null,
        defaultLocale: 'de',
        fallbackLocale: 'en',
        createAdmin: true,
        adminName: 'Legacy Admin',
        adminEmail: 'admin@example.test',
        adminPassword: 'secret-password',
        enableHorizon: true,
        enableSocialLogin: false,
        runMigrations: false,
        runSeeders: false,
        installFrontend: false,
        installTenancy: false,
        syncEnvironment: true,
        force: false,
    );

    $method = new ReflectionMethod($installer, 'createAdminUser');
    $method->setAccessible(true);
    $user = $method->invoke($installer, $options, $command);

    expect($user)->toBeInstanceOf(InstallerLegacyStyleUser::class)
        ->and($user->getKey())->not->toBeNull()
        ->and($user->email)->toBe('admin@example.test')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->getAttribute('first_name'))->toBe('Legacy')
        ->and($user->getAttribute('last_name'))->toBe('Admin')
        ->and(Hash::check('secret-password', (string) $user->password))->toBeTrue()
        ->and($user->hasRole('super-admin'))->toBeTrue();
});

it('assigns the super-admin role during tenancy-mode installation even when the loaded user model has no role trait yet', function (): void {
    if (! corePanelTestbenchDatabaseAvailable()) {
        test()->markTestSkipped('pdo_sqlite is not available in this environment.');
    }

    config()->set('auth.providers.users.model', InstallerPlainLegacyUser::class);
    config()->set('core-panel.user_model', InstallerPlainLegacyUser::class);
    config()->set('permission.teams', false);
    config()->set('permission.testing', false);

    $this->migrateScaffoldDatabase();

    app(PermissionRegistrar::class)->teams = false;

    $installer = app(CorePanelInstaller::class);
    $command = new InstallerRoleAwareCommand;
    $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput));
    $options = new CorePanelInstallOptions(
        appUrl: 'http://core-panel-tenancy.test',
        databaseConnection: 'pgsql',
        databaseHost: '127.0.0.1',
        databasePort: '5432',
        databaseName: 'core_panel',
        databaseUsername: 'core_panel',
        databasePassword: 'core_panel',
        databaseTestName: 'core_panel_test',
        centralDomain: 'core-panel-tenancy.test',
        defaultLocale: 'de',
        fallbackLocale: 'en',
        createAdmin: true,
        adminName: 'Central Admin',
        adminEmail: 'admin@example.test',
        adminPassword: 'secret-password',
        enableHorizon: true,
        enableSocialLogin: false,
        runMigrations: false,
        runSeeders: false,
        installFrontend: false,
        installTenancy: true,
        syncEnvironment: true,
        force: false,
    );

    $method = new ReflectionMethod($installer, 'createAdminUser');
    $method->setAccessible(true);

    /** @var InstallerPlainLegacyUser $user */
    $user = $method->invoke($installer, $options, $command);

    expect($user)->toBeInstanceOf(InstallerPlainLegacyUser::class)
        ->and($user->getKey())->not->toBeNull()
        ->and(DB::table(config('permission.table_names.model_has_roles', 'model_has_roles'))
            ->where('model_type', $user->getMorphClass())
            ->exists())->toBeTrue()
        ->and(Role::query()->where('name', 'super-admin')->exists())->toBeTrue();
});

it('uses the raw primary key value for installer role pivot writes when the loaded user model casts keys as integers', function (): void {
    $installer = app(CorePanelInstaller::class);
    $user = new InstallerPlainLegacyUser;
    $uuid = '019e16ac-f0ae-7354-8f89-2e0aa7f05863';

    $user->setRawAttributes([
        $user->getKeyName() => $uuid,
    ], true);

    expect((string) $user->getKey())->not->toBe($uuid)
        ->and((string) $user->getRawOriginal($user->getKeyName()))->toBe($uuid);

    $method = new ReflectionMethod($installer, 'authenticatablePrimaryKeyValue');
    $method->setAccessible(true);

    expect($method->invoke($installer, $user))->toBe($uuid);
});

it('removes the stale casted installer role pivot row before writing the raw uuid model_id', function (): void {
    if (! corePanelTestbenchDatabaseAvailable()) {
        test()->markTestSkipped('pdo_sqlite is not available in this environment.');
    }

    config()->set('auth.providers.users.model', InstallerPlainLegacyUser::class);
    config()->set('core-panel.user_model', InstallerPlainLegacyUser::class);
    config()->set('permission.teams', false);
    config()->set('permission.testing', false);

    $this->migrateScaffoldDatabase();

    app(PermissionRegistrar::class)->teams = false;
    app(ResyncAccessMatrixAction::class)->execute();

    $installer = app(CorePanelInstaller::class);
    $user = new InstallerPlainLegacyUser;
    $uuid = '019e16ac-f0ae-7354-8f89-2e0aa7f05863';

    DB::table('users')->insert([
        'id' => $uuid,
        'first_name' => 'Admin',
        'last_name' => 'User',
        'email' => 'admin@example.test',
        'status' => 'active',
        'password' => Hash::make('secret-password'),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user->exists = true;
    $user->setRawAttributes([
        'id' => $uuid,
        'email' => 'admin@example.test',
    ], true);

    $legacyCastedKey = (string) $user->getKey();
    $pivotTable = config('permission.table_names.model_has_roles', 'model_has_roles');
    $role = Role::query()->where('name', 'super-admin')->firstOrFail();

    DB::table($pivotTable)->insert([
        'role_id' => $role->getKey(),
        'model_type' => $user->getMorphClass(),
        'model_id' => $legacyCastedKey,
    ]);

    $method = new ReflectionMethod($installer, 'assignInstallerSuperAdminRole');
    $method->setAccessible(true);
    $method->invoke($installer, $user);

    expect(DB::table($pivotTable)
        ->where('role_id', $role->getKey())
        ->where('model_type', $user->getMorphClass())
        ->where('model_id', $legacyCastedKey)
        ->exists())->toBeFalse()
        ->and(DB::table($pivotTable)
            ->where('role_id', $role->getKey())
            ->where('model_type', $user->getMorphClass())
            ->where('model_id', $uuid)
            ->exists())->toBeTrue();
});

it('allows the installer to skip environment synchronization when requested', function (): void {
    $contents = file_get_contents(__DIR__.'/../../src/Support/Install/CorePanelInstaller.php');
    $commandContents = file_get_contents(__DIR__.'/../../src/Console/InstallCommand.php');

    expect($contents)->toContain('if ($options->syncEnvironment)')
        ->and($contents)->toContain('replaceTemplateValues: true')
        ->and($commandContents)->toContain('{--sync-environment= : true|false}')
        ->and($commandContents)->toContain('syncEnvironment: $syncEnvironment,');
});
