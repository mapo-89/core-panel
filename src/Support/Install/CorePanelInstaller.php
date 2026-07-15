<?php

declare(strict_types=1);

namespace CorePanel\Support\Install;

use CorePanel\Contracts\CorePanelInstallerInterface;
use CorePanel\Database\Seeders\CorePanelPermissionSeeder;
use CorePanel\Database\Seeders\CorePanelSettingsSeeder;
use CorePanel\Domains\Permission\Actions\ResyncAccessMatrixAction;
use CorePanel\Support\Migrations\HostMigrationRunner;
use CorePanel\Support\Permissions\PermissionService;
use CorePanel\Support\PublishTag;
use CorePanel\Support\ScaffoldsCorePanelStubs;
use CorePanel\Support\SynchronizesEnvironmentFile;
use CorePanel\Support\Users\UserModelManager;
use Illuminate\Console\Command;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use JsonException;
use Laravel\Passport\ClientRepository;
use PDO;
use PDOException;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\Process\Process;
use Throwable;

final readonly class CorePanelInstaller implements CorePanelInstallerInterface
{
    public function __construct(
        private ScaffoldsCorePanelStubs $stubs,
        private AppServiceProviderMerger $appServiceProviderMerger,
        private SynchronizesEnvironmentFile $environment,
        private UserModelManager $users,
        private PermissionService $permissions,
        private ResyncAccessMatrixAction $resyncAccessMatrix,
        private Filesystem $files,
        private HostMigrationRunner $migrations,
    ) {}

    public function install(CorePanelInstallOptions $options, Command $command): void
    {
        config()->set('core-panel.runtime.installing', true);

        try {
            $command->newLine();

            $this->runStep($command, 'Synchronizing scaffolds', function () use ($options): void {
                $this->stubs->scaffold($options->force);
                $this->appServiceProviderMerger->merge();
            });

            $environment = [];

            if ($options->syncEnvironment) {
                $this->runStep($command, 'Synchronizing environment', function () use ($options, &$environment): void {
                    $environment = $this->environment->sync(
                        overrides: $this->environmentOverrides($options),
                        replaceTemplateValues: true,
                    );

                    $this->stubs->refreshHostRenderedScaffolds([
                        'public/manifest.json',
                    ]);
                });
            }

            $this->applyRuntimeConfiguration($options, $environment);
            $this->refreshResolvedRuntimeServices();

            $this->runStep($command, 'Preparing database connection', function () use ($command, $options): void {
                $this->prepareDatabaseConnection($command, $options);
            });

            $this->runStep($command, 'Clearing optimized caches', function () use ($command): void {
                $this->clearOptimizedState($command);
            });

            $this->runStep($command, 'Ensuring application key', function () use ($command): void {
                $this->ensureApplicationKey($command);
            });

            $this->runStep($command, 'Preparing package runtime assets', function () use ($command, $options): void {
                $this->publishMigrations($command, $options);
                $this->publishAssets($command, $options);
            });

            $this->runStep($command, 'Synchronizing optional addon overlays', function () use ($command, $options): void {
                $this->synchronizeOptionalAddonOverlays($command, $options);
            });

            if ($options->runMigrations) {
                $this->runStep($command, 'Running migrations', function () use ($command): void {
                    $this->migrations->run($command);
                });

                $this->applyRuntimeConfiguration($options, $environment);
                $this->refreshResolvedRuntimeServices();
            }

            if ($options->runSeeders) {
                $this->runStep($command, 'Seeding permissions and settings', function () use ($command): void {
                    $this->ensureSeederPrerequisites();
                    $this->runInstallerSeeder($command, CorePanelPermissionSeeder::class);
                    $this->runInstallerSeeder($command, CorePanelSettingsSeeder::class);
                });
            }

            $this->runStep($command, 'Ensuring Passport personal access client', function (): void {
                $this->ensurePassportPersonalAccessClient();
            });

            $admin = $this->createAdminUser($options, $command);
            $this->runStep($command, 'Linking storage', function () use ($command): void {
                $command->call('storage:link');
            });

            $this->runStep($command, 'Generating Wayfinder routes', function () use ($command): void {
                $this->generateWayfinderRoutes($command);
            });

            $this->runStep($command, 'Generating Swagger API docs', function () use ($command): void {
                $this->generateSwaggerDocs($command);
            });

            if ($options->installFrontend) {
                $this->installFrontend($command);
            } else {
                $command->warn('Frontend assets changed. Run npm install && npm run build or docker compose -f docker-compose.dev.yml up -d --build.');
            }
        } finally {
            config()->set('core-panel.runtime.installing', false);
        }
    }

    /**
     * @return array<string, string>
     */
    private function environmentOverrides(CorePanelInstallOptions $options): array
    {
        return [
            'APP_NAME' => 'CorePanel',
            'APP_URL' => $options->appUrl,
            'APP_LOCALE' => $options->defaultLocale,
            'APP_FALLBACK_LOCALE' => $options->fallbackLocale,
            'DB_CONNECTION' => $options->databaseConnection,
            'DB_HOST' => $options->databaseHost,
            'DB_PORT' => $options->databasePort,
            'DB_DATABASE' => $options->databaseName,
            'DB_USERNAME' => $options->databaseUsername,
            'DB_PASSWORD' => $options->databasePassword,
            'DB_DATABASE_TEST' => $options->databaseTestName,
            'CORE_PANEL_HORIZON_ENABLED' => $options->enableHorizon ? 'true' : 'false',
            'CORE_PANEL_SOCIAL_GITHUB_ENABLED' => 'false',
            'CORE_PANEL_SOCIAL_GOOGLE_ENABLED' => 'false',
            'CORE_PANEL_SOCIAL_MICROSOFT_ENABLED' => 'false',
            ...($options->installTenancy && $options->centralDomain !== null && $options->centralDomain !== ''
                ? [
                    'CENTRAL_DOMAINS' => $options->centralDomain,
                    'TENANCY_CENTRAL_CONNECTION' => $options->databaseConnection,
                    'TENANCY_TEMPLATE_TENANT_CONNECTION' => '',
                ]
                : []),
        ];
    }

    /**
     * @param  array<string, string>  $environment
     */
    private function applyRuntimeConfiguration(CorePanelInstallOptions $options, array $environment): void
    {
        unset($environment);

        $this->syncRuntimeEnvironment([
            'APP_NAME' => 'CorePanel',
            'APP_URL' => $options->appUrl,
            'APP_LOCALE' => $options->defaultLocale,
            'APP_FALLBACK_LOCALE' => $options->fallbackLocale,
            'DB_CONNECTION' => $options->databaseConnection,
            'DB_HOST' => $options->databaseHost,
            'DB_PORT' => $options->databasePort,
            'DB_DATABASE' => $options->databaseName,
            'DB_USERNAME' => $options->databaseUsername,
            'DB_PASSWORD' => $options->databasePassword,
            'DB_CACHE_CONNECTION' => $options->databaseConnection,
            'DB_CACHE_LOCK_CONNECTION' => $options->databaseConnection,
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
        ]);

        config()->set('app.locale', $options->defaultLocale);
        config()->set('app.fallback_locale', $options->fallbackLocale);
        config()->set('app.name', 'CorePanel');
        config()->set('app.url', $options->appUrl);
        config()->set('database.default', $options->databaseConnection);
        config()->set("database.connections.{$options->databaseConnection}.host", $options->databaseHost);
        config()->set("database.connections.{$options->databaseConnection}.port", $options->databasePort);
        config()->set("database.connections.{$options->databaseConnection}.database", $options->databaseName);
        config()->set("database.connections.{$options->databaseConnection}.username", $options->databaseUsername);
        config()->set("database.connections.{$options->databaseConnection}.password", $options->databasePassword);
        config()->set('cache.default', 'array');
        config()->set('cache.stores.database.connection', $options->databaseConnection);
        config()->set('cache.stores.database.lock_connection', $options->databaseConnection);
        config()->set('permission.cache.store', 'array');
        config()->set('core-panel.i18n.default_locale', $options->defaultLocale);
        config()->set('core-panel.i18n.fallback_locale', $options->fallbackLocale);
        config()->set('core-panel.horizon.enabled', $options->enableHorizon);
        config()->set('core-panel.auth.socialite.providers.github.enabled', false);
        config()->set('core-panel.auth.socialite.providers.google.enabled', false);
        config()->set('core-panel.auth.socialite.providers.microsoft.enabled', false);

        if ($options->installTenancy && $options->centralDomain !== null && $options->centralDomain !== '') {
            config()->set('tenancy.central_domains', [$options->centralDomain]);
            config()->set('tenancy.database.central_connection', $options->databaseConnection);
            config()->set('tenancy.database.template_tenant_connection', $options->databaseConnection);
        }
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function syncRuntimeEnvironment(array $variables): void
    {
        foreach ($variables as $key => $value) {
            putenv(sprintf('%s=%s', $key, $value));
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    private function refreshResolvedRuntimeServices(): void
    {
        DB::purge();

        app()->forgetInstance('db');
        app()->forgetInstance('cache');
        app()->forgetInstance('cache.store');
        app()->forgetInstance(PermissionRegistrar::class);

        Facade::clearResolvedInstance('db');
        Facade::clearResolvedInstance('cache');
        Facade::clearResolvedInstance('cache.store');
    }

    private function ensureApplicationKey(Command $command): void
    {
        if (! filled(config('app.key'))) {
            $command->call('key:generate', ['--force' => true]);
        }

        $appKey = (string) config('app.key', '');

        if ($appKey === '') {
            return;
        }

        $this->environment->sync(overrides: [
            'APP_KEY' => $appKey,
        ]);
    }

    private function publishMigrations(Command $command, CorePanelInstallOptions $options): void
    {
        unset($command, $options);
    }

    private function publishAssets(Command $command, CorePanelInstallOptions $options): void
    {
        foreach (PublishTag::installTags() as $tag) {
            $command->call('vendor:publish', ['--tag' => $tag, '--force' => $options->force]);
        }

        if (($command->getApplication()?->has('passport:keys') ?? false) || app()->bound('command.passport.keys')) {
            $command->call('passport:keys', ['--force' => true]);
        }
    }

    private function ensurePassportPersonalAccessClient(): void
    {
        if (! class_exists(ClientRepository::class)) {
            return;
        }

        if (! Schema::hasTable('oauth_clients')) {
            return;
        }

        $provider = 'users';

        try {
            app(ClientRepository::class)->personalAccessClient($provider);
        } catch (RuntimeException) {
            app(ClientRepository::class)->createPersonalAccessGrantClient(
                'CorePanel Personal Access Client',
                $provider,
            );
        }
    }

    private function synchronizeOptionalAddonOverlays(Command $command, CorePanelInstallOptions $options): void
    {
        if (! $options->installTenancy) {
            return;
        }

        if (($command->getApplication()?->has('core-panel:tenancy:install') ?? false) || app()->bound('command.core-panel.tenancy.install')) {
            $command->call('core-panel:tenancy:install', [
                '--force' => $options->force,
            ]);

            return;
        }

        $localAddon = $this->resolveLocalTenancyAddonPackage();

        if ($localAddon === null) {
            $command->warn('Tenancy addon requested, but no local addon package was found.');

            return;
        }

        $this->runStep($command, 'Installing local tenancy addon dependency', function () use ($command, $localAddon): void {
            $this->installLocalTenancyAddonDependency($localAddon);
            $this->runCriticalProcessStep(
                $command,
                'Running tenancy addon installer',
                [PHP_BINARY, 'artisan', 'core-panel:tenancy:install', '--force'],
                600,
            );
        });
    }

    private function createAdminUser(CorePanelInstallOptions $options, Command $command): ?Model
    {
        if (! $options->createAdmin) {
            return null;
        }

        $email = $options->adminEmail ?? 'admin@example.test';
        $name = $options->adminName ?? 'Admin User';
        $password = $options->adminPassword ?? $this->users->defaultPassword();
        $modelClass = $this->users->modelClass();
        $nameParts = $this->users->splitName($name);
        $payload = [
            'first_name' => $nameParts['first_name'],
            'last_name' => $nameParts['last_name'],
            'password' => Hash::make($password),
        ];

        if ($this->users->supportsLocale()) {
            $payload['locale'] = $options->defaultLocale;
        }

        if ($this->users->supportsEmailVerification()) {
            $payload['email_verified_at'] = now();
        }

        /** @var Model&Authenticatable $model */
        $model = new $modelClass;

        /** @var Model&Authenticatable|null $user */
        $user = Schema::hasColumn($model->getTable(), 'email')
            ? $modelClass::query()->where('email', $email)->first()
            : null;

        if (! $user instanceof Model) {
            $user = $model;
        }

        $attributes = [
            ...$payload,
            'email' => $email,
        ];

        if (! $user->exists) {
            $generatedKey = $this->installerPrimaryKeyValue($user);

            if ($generatedKey !== null) {
                $attributes[$user->getKeyName()] = $generatedKey;
            }
        }

        $user->forceFill($attributes);
        $user->save();

        if ($this->permissionTablesExist()) {
            $this->ensureSuperAdminAccess($command);
            $this->assignInstallerSuperAdminRole($user);
        }

        $command->info(sprintf('Admin user ready: %s', $email));

        if ($options->adminPassword === null) {
            $command->warn('Admin password was generated automatically. Store it securely before first login.');
        }

        return $user;
    }

    private function installerPrimaryKeyValue(Model $model): ?string
    {
        $table = $model->getTable();
        $keyName = $model->getKeyName();

        if (! Schema::hasColumn($table, $keyName)) {
            return null;
        }

        $columnType = Schema::getColumnType($table, $keyName);

        return match ($columnType) {
            'uuid' => (string) Str::uuid7(),
            'char', 'string', 'varchar' => (string) Str::uuid7(),
            default => null,
        };
    }

    private function ensureSuperAdminAccess(Command $command): void
    {
        if (! $this->permissionTablesExist()) {
            $command->warn('Skipping super-admin access synchronization because the permission tables are not migrated yet.');

            return;
        }

        $roleModel = $this->permissions->roleModelClass();
        $superAdminRole = $roleModel::query()
            ->where('name', 'super-admin')
            ->withCount('permissions')
            ->first();

        if ($superAdminRole !== null && (int) $superAdminRole->getAttribute('permissions_count') > 0) {
            return;
        }

        $command->line('  <fg=gray>→</> Synchronizing managed access for super-admin...');

        $this->resyncAccessMatrix->execute();
        $this->permissions->resetCache();

        $command->line('  <fg=green>✓</> Managed access ready for super-admin');
    }

    private function assignInstallerSuperAdminRole(Model&Authenticatable $user): void
    {
        if (method_exists($user, 'assignRole')) {
            $this->permissions->assignRole($user, 'super-admin');

            return;
        }

        /** @var array<string, string> $tableNames */
        $tableNames = config('permission.table_names', []);
        $pivotTable = $tableNames['model_has_roles'] ?? 'model_has_roles';
        $rolePivotColumn = $tableNames['role_pivot_key'] ?? 'role_id';
        $modelPivotColumn = config('permission.column_names.model_morph_key', 'model_id');
        $teamForeignKey = config('permission.column_names.team_foreign_key', 'team_id');
        $roleModel = $this->permissions->roleModelClass();
        $superAdminRole = $roleModel::query()
            ->where('name', 'super-admin')
            ->where('guard_name', config('auth.defaults.guard', 'web'))
            ->first();

        if (! $superAdminRole instanceof Model) {
            return;
        }

        $payload = [
            $rolePivotColumn => $superAdminRole->getKey(),
            'model_type' => $user->getMorphClass(),
            $modelPivotColumn => $this->authenticatablePrimaryKeyValue($user),
        ];
        $legacyCastedKey = (string) $user->getKey();

        if (Schema::hasColumn($pivotTable, $teamForeignKey)) {
            $payload[$teamForeignKey] = null;
        }

        if ($legacyCastedKey !== '' && $legacyCastedKey !== $payload[$modelPivotColumn]) {
            DB::table($pivotTable)
                ->where($rolePivotColumn, $payload[$rolePivotColumn])
                ->where('model_type', $payload['model_type'])
                ->where($modelPivotColumn, $legacyCastedKey)
                ->delete();
        }

        DB::table($pivotTable)->updateOrInsert([
            $rolePivotColumn => $payload[$rolePivotColumn],
            'model_type' => $payload['model_type'],
            $modelPivotColumn => $payload[$modelPivotColumn],
            ...array_key_exists($teamForeignKey, $payload)
                ? [$teamForeignKey => $payload[$teamForeignKey]]
                : [],
        ], $payload);

        $this->permissions->resetCache();
    }

    private function authenticatablePrimaryKeyValue(Model $user): string
    {
        $keyName = $user->getKeyName();
        $rawKey = $user->getRawOriginal($keyName);

        return (string) $rawKey;
    }

    private function permissionTablesExist(): bool
    {
        /** @var array<string, string> $tableNames */
        $tableNames = config('permission.table_names', []);

        foreach ([
            $tableNames['roles'] ?? 'roles',
            $tableNames['permissions'] ?? 'permissions',
            $tableNames['role_has_permissions'] ?? 'role_has_permissions',
            $tableNames['model_has_roles'] ?? 'model_has_roles',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        return true;
    }

    private function ensureSeederPrerequisites(): void
    {
        if (! $this->permissionTablesExist() || ! Schema::hasTable('settings')) {
            throw new RuntimeException(
                'Cannot run CorePanel seeders before the required tables exist. Run migrations first or disable seeders during installation.',
            );
        }
    }

    /**
     * @param  class-string<Seeder>  $seederClass
     */
    private function runInstallerSeeder(Command $command, string $seederClass): void
    {
        /** @var Seeder $seeder */
        $seeder = app($seederClass);
        $seeder->setContainer(app());
        $seeder->setCommand($command);
        $seeder->__invoke();
    }

    private function generateWayfinderRoutes(Command $command): void
    {
        if (($command->getApplication()?->has('wayfinder:generate') ?? false) || app()->bound('command.wayfinder.generate')) {
            $command->call('wayfinder:generate');
        }
    }

    private function generateSwaggerDocs(Command $command): void
    {
        if (! (($command->getApplication()?->has('l5-swagger:generate') ?? false) || app()->bound('command.l5-swagger.generate'))) {
            return;
        }

        config()->set('l5-swagger.documentations.default.paths.annotations', [
            base_path('app/OpenApi'),
        ]);

        try {
            $command->call('l5-swagger:generate');
        } catch (Throwable $exception) {
            $command->warn(sprintf(
                'Swagger docs were not generated automatically: %s',
                $exception->getMessage(),
            ));
        }
    }

    private function clearOptimizedState(Command $command): void
    {
        if (($command->getApplication()?->has('optimize:clear') ?? false) || app()->bound('command.optimize.clear')) {
            $originalCacheStore = config('cache.default');

            config()->set('cache.default', 'array');

            try {
                $command->call('optimize:clear');
            } finally {
                config()->set('cache.default', $originalCacheStore);
            }
        }
    }

    /**
     * @param  callable(): void  $callback
     */
    private function runStep(Command $command, string $label, callable $callback): void
    {
        $command->line(sprintf('  <fg=gray>→</> %s...', $label));
        $callback();
        $command->line(sprintf('  <fg=green>✓</> %s', $label));
    }

    private function installFrontend(Command $command): void
    {
        $this->runStep($command, 'Removing existing node_modules', function () use ($command): void {
            $this->removeExistingNodeModules($command);
        });
        $this->runProcessStep($command, 'Installing frontend dependencies', ['npm', 'install'], 600);
        $this->runProcessStep($command, 'Building frontend assets', ['npm', 'run', 'build'], 600);
    }

    private function prepareDatabaseConnection(Command $command, CorePanelInstallOptions $options): void
    {
        try {
            $this->ensureDatabaseExists($options);
            $this->testTargetDatabaseConnection($options);

            $command->line(sprintf(
                '  <fg=green>✓</> Database reachable via %s://%s:%s/%s',
                $options->databaseConnection,
                $options->databaseHost,
                $options->databasePort,
                $options->databaseName,
            ));
        } catch (RuntimeException $exception) {
            $command->warn($exception->getMessage());

            if ($options->runMigrations || $options->runSeeders || $options->createAdmin) {
                throw $exception;
            }
        }
    }

    private function ensureDatabaseExists(CorePanelInstallOptions $options): void
    {
        $maintenanceConnection = $this->createMaintenancePdo($options);

        match ($options->databaseConnection) {
            'mysql' => $this->ensureMysqlDatabaseExists($maintenanceConnection, $options->databaseName),
            'pgsql' => $this->ensurePostgresDatabaseExists($maintenanceConnection, $options->databaseName),
            default => throw new RuntimeException(sprintf(
                'Unsupported database connection [%s].',
                $options->databaseConnection,
            )),
        };
    }

    private function testTargetDatabaseConnection(CorePanelInstallOptions $options): void
    {
        config()->set('database.default', $options->databaseConnection);

        DB::purge($options->databaseConnection);
        DB::connection($options->databaseConnection)->getPdo();
        DB::disconnect($options->databaseConnection);
    }

    private function createMaintenancePdo(CorePanelInstallOptions $options): PDO
    {
        try {
            return match ($options->databaseConnection) {
                'mysql' => new PDO(
                    sprintf(
                        'mysql:host=%s;port=%s;charset=utf8mb4',
                        $options->databaseHost,
                        $options->databasePort,
                    ),
                    $options->databaseUsername,
                    $options->databasePassword,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
                ),
                'pgsql' => new PDO(
                    sprintf(
                        'pgsql:host=%s;port=%s;dbname=postgres',
                        $options->databaseHost,
                        $options->databasePort,
                    ),
                    $options->databaseUsername,
                    $options->databasePassword,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
                ),
                default => throw new RuntimeException(sprintf(
                    'Unsupported database connection [%s].',
                    $options->databaseConnection,
                )),
            };
        } catch (PDOException $exception) {
            throw new RuntimeException(sprintf(
                'Database server connection failed for %s://%s:%s (%s)',
                $options->databaseConnection,
                $options->databaseHost,
                $options->databasePort,
                $exception->getMessage(),
            ), previous: $exception);
        }
    }

    private function ensureMysqlDatabaseExists(PDO $connection, string $databaseName): void
    {
        $connection->exec(sprintf(
            'CREATE DATABASE IF NOT EXISTS %s CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
            $this->quoteMysqlIdentifier($databaseName),
        ));
    }

    private function ensurePostgresDatabaseExists(PDO $connection, string $databaseName): void
    {
        $statement = $connection->prepare('SELECT 1 FROM pg_database WHERE datname = :database LIMIT 1');
        $statement->execute(['database' => $databaseName]);

        if ($statement->fetchColumn() !== false) {
            return;
        }

        $connection->exec(sprintf(
            'CREATE DATABASE %s ENCODING %s TEMPLATE template0',
            $this->quotePostgresIdentifier($databaseName),
            $connection->quote('UTF8'),
        ));
    }

    private function quoteMysqlIdentifier(string $identifier): string
    {
        return sprintf('`%s`', str_replace('`', '``', $identifier));
    }

    private function quotePostgresIdentifier(string $identifier): string
    {
        return sprintf('"%s"', str_replace('"', '""', $identifier));
    }

    private function removeExistingNodeModules(Command $command): void
    {
        $nodeModulesPath = base_path('node_modules');

        if (! $this->files->isDirectory($nodeModulesPath)) {
            $command->line('  <fg=gray>•</> No existing node_modules found');

            return;
        }

        $this->files->deleteDirectory($nodeModulesPath);
    }

    /**
     * @return array{package:string, version:string, path:string}|null
     */
    private function resolveLocalTenancyAddonPackage(): ?array
    {
        $addonComposerPath = $this->packageBasePath('../core-panel-tenancy/composer.json');

        if (! $this->files->exists($addonComposerPath)) {
            return null;
        }

        try {
            /** @var array{name?:string, version?:string} $manifest */
            $manifest = json_decode((string) $this->files->get($addonComposerPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        $packageName = $manifest['name'] ?? null;

        if (! is_string($packageName) || $packageName === '') {
            return null;
        }

        return [
            'package' => $packageName,
            'version' => is_string($manifest['version'] ?? null) && $manifest['version'] !== ''
                ? $manifest['version']
                : 'dev-main',
            'path' => dirname($addonComposerPath),
        ];
    }

    /**
     * @param  array{package:string, version:string, path:string}  $addon
     */
    private function installLocalTenancyAddonDependency(array $addon): void
    {
        $composerPath = base_path('composer.json');

        if (! $this->files->exists($composerPath)) {
            throw new RuntimeException('Unable to install the local tenancy addon because composer.json was not found.');
        }

        $this->ensureComposerManifestContainsAddon($composerPath, $addon);

        $this->runCriticalProcess([
            'composer',
            'require',
            sprintf('%s:%s', $addon['package'], $addon['version']),
            '--no-interaction',
        ], 1200);
    }

    /**
     * @param  array{package:string, version:string, path:string}  $addon
     */
    private function ensureComposerManifestContainsAddon(string $composerPath, array $addon): void
    {
        try {
            /** @var array{repositories?:array<int, array<string, mixed>>, require?:array<string, string>} $composer */
            $composer = json_decode((string) $this->files->get($composerPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to read composer.json for tenancy addon installation.', previous: $exception);
        }

        $repositories = $composer['repositories'] ?? [];
        $require = $composer['require'] ?? [];
        $changed = false;

        $repositoryIndex = collect($repositories)->search(function (mixed $repository) use ($addon): bool {
            return is_array($repository)
                && ($repository['type'] ?? null) === 'path'
                && ($repository['url'] ?? null) === $addon['path'];
        });

        $expectedRepository = [
            'type' => 'path',
            'url' => $addon['path'],
            'options' => [
                'symlink' => true,
                'versions' => [
                    $addon['package'] => $addon['version'],
                ],
            ],
        ];

        if ($repositoryIndex === false) {
            $repositories[] = $expectedRepository;
            $changed = true;
        } elseif (($repositories[$repositoryIndex] ?? null) !== $expectedRepository) {
            $repositories[$repositoryIndex] = $expectedRepository;
            $changed = true;
        }

        if (($require[$addon['package']] ?? null) !== $addon['version']) {
            $require[$addon['package']] = $addon['version'];
            ksort($require);
            $changed = true;
        }

        if (! $changed) {
            return;
        }

        $composer['repositories'] = $repositories;
        $composer['require'] = $require;

        try {
            $this->files->put(
                $composerPath,
                json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL,
            );
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to update composer.json for tenancy addon installation.', previous: $exception);
        }
    }

    private function packageBasePath(string $path = ''): string
    {
        $basePath = dirname(__DIR__, 3);

        if ($path === '') {
            return $basePath;
        }

        return $basePath.DIRECTORY_SEPARATOR.$path;
    }

    /**
     * @param  list<string>  $processCommand
     */
    private function runProcessStep(Command $command, string $label, array $processCommand, int $timeoutInSeconds): void
    {
        $command->line(sprintf('  <fg=gray>→</> %s...', $label));

        $process = new Process($processCommand, base_path(), null, null, $timeoutInSeconds);
        $process->run();

        if (! $process->isSuccessful()) {
            $command->line(sprintf('  <fg=red>✗</> %s', $label));

            $errorOutput = trim($process->getErrorOutput()) !== '' ? trim($process->getErrorOutput()) : trim($process->getOutput());

            if ($errorOutput !== '') {
                $command->warn($errorOutput);
            }

            return;
        }

        $command->line(sprintf('  <fg=green>✓</> %s', $label));
    }

    /**
     * @param  list<string>  $processCommand
     */
    private function runCriticalProcessStep(Command $command, string $label, array $processCommand, int $timeoutInSeconds): void
    {
        $command->line(sprintf('  <fg=gray>→</> %s...', $label));

        $this->runCriticalProcess($processCommand, $timeoutInSeconds);

        $command->line(sprintf('  <fg=green>✓</> %s', $label));
    }

    /**
     * @param  list<string>  $processCommand
     */
    private function runCriticalProcess(array $processCommand, int $timeoutInSeconds): void
    {
        $process = new Process($processCommand, base_path(), null, null, $timeoutInSeconds);
        $process->run();

        if ($process->isSuccessful()) {
            return;
        }

        $errorOutput = trim($process->getErrorOutput()) !== '' ? trim($process->getErrorOutput()) : trim($process->getOutput());

        throw new RuntimeException($errorOutput !== '' ? $errorOutput : 'Process execution failed.');
    }
}
