<?php

declare(strict_types=1);

namespace CorePanel\Tests;

use CorePanel\CorePanelServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Laravel\Passport\PassportServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use PDO;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

abstract class TestCase extends Orchestra
{
    /** @var list<string> */
    private static array $temporaryPaths = [];

    public static function temporaryPath(string $name): string
    {
        $path = sys_get_temp_dir().'/core-panel-'.$name.'-'.getmypid().'-'.bin2hex(random_bytes(5));
        self::$temporaryPaths[] = $path;

        return $path;
    }

    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            $filesystem = new Filesystem;

            foreach (array_reverse(self::$temporaryPaths) as $path) {
                if (is_dir($path)) {
                    $filesystem->deleteDirectory($path);
                } elseif (is_file($path)) {
                    $filesystem->delete($path);
                }
            }

            self::$temporaryPaths = [];
        }
    }

    protected static function usesSqliteTestDatabase(): bool
    {
        return extension_loaded('pdo_sqlite') && in_array('sqlite', PDO::getAvailableDrivers(), true);
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            CorePanelServiceProvider::class,
            PassportServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        if (self::usesSqliteTestDatabase()) {
            $app['config']->set('database.default', 'sqlite');
            $app['config']->set('database.connections.sqlite', [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ]);
        }

        $app['config']->set('database.connections.pgsql', [
            'driver' => 'pgsql',
            'host' => '127.0.0.1',
            'port' => 5432,
            'database' => 'core_panel',
            'username' => 'core_panel',
            'password' => 'secret',
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
            'sslmode' => 'prefer',
            'timezone' => 'UTC',
        ]);
        $app['config']->set('auth.providers.users.model', FakeUser::class);
        $app['config']->set('auth.guards.api', [
            'driver' => 'passport',
            'provider' => 'users',
        ]);
        $passportKeyPath = __DIR__.'/Fixtures/passport-keys';
        if (! is_dir($passportKeyPath)) {
            mkdir($passportKeyPath, 0777, true);
        }
        copy(__DIR__.'/Fixtures/passport-private.key', $passportKeyPath.'/oauth-private.key');
        copy(__DIR__.'/Fixtures/passport-public.key', $passportKeyPath.'/oauth-public.key');
        chmod($passportKeyPath.'/oauth-private.key', 0600);
        chmod($passportKeyPath.'/oauth-public.key', 0600);
        Passport::loadKeysFrom($passportKeyPath);
        $app['config']->set('permission.models.permission', Permission::class);
        $app['config']->set('permission.models.role', Role::class);
        $app['config']->set('permission.table_names', [
            'roles' => 'roles',
            'permissions' => 'permissions',
            'model_has_permissions' => 'model_has_permissions',
            'model_has_roles' => 'model_has_roles',
            'role_has_permissions' => 'role_has_permissions',
        ]);
        $app['config']->set('permission.column_names', [
            'role_pivot_key' => null,
            'permission_pivot_key' => null,
            'model_morph_key' => 'model_id',
            'team_foreign_key' => 'team_id',
        ]);
        $app['config']->set('permission.column_names.team_foreign_key', 'team_id');
        $app['config']->set('permission.cache.key', 'spatie.permission.cache');
        $app['config']->set('permission.cache.store', 'array');
        $app['config']->set('permission.teams', false);
        $app['config']->set('permission.testing', true);
    }

    protected function defineDatabaseMigrations(): void
    {
        //
    }

    protected function migrateScaffoldDatabase(): void
    {
        if (! self::usesSqliteTestDatabase()) {
            return;
        }

        $this->artisan('db:wipe', [
            '--database' => 'sqlite',
            '--force' => true,
        ])->run();

        $migrationFiles = $this->scaffoldMigrationFiles();

        foreach ($migrationFiles as $migrationFile) {
            $this->artisan('migrate', [
                '--database' => 'sqlite',
                '--path' => $migrationFile,
                '--realpath' => true,
                '--force' => true,
            ])->run();
        }

        app(ClientRepository::class)->createPersonalAccessGrantClient(
            'CorePanel Test Personal Access Client',
            'users',
        );
    }

    /**
     * @return list<string>
     */
    private function scaffoldMigrationFiles(): array
    {
        $files = [];

        foreach ([__DIR__.'/../stubs/database/migrations', __DIR__.'/../database/migrations'] as $root) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \RecursiveDirectoryIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        usort($files, static fn (string $left, string $right): int => strcmp(basename($left), basename($right)));

        return $files;
    }
}
