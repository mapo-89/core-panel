<?php

declare(strict_types=1);

namespace CorePanel\Console;

use CorePanel\Contracts\CorePanelInstallerInterface;
use CorePanel\Support\Config\CorePanelConfig;
use CorePanel\Support\Install\CorePanelInstallOptions;
use Illuminate\Console\Command;
use RuntimeException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

final class InstallCommand extends Command
{
    protected $signature = 'core-panel:install
        {--force : Overwrite published package assets}
        {--app-url= : Application base URL}
        {--db-connection= : Database connection driver}
        {--db-host= : Database host}
        {--db-port= : Database port}
        {--db-database= : Database name}
        {--db-username= : Database username}
        {--db-password= : Database password}
        {--db-database-test= : Test database name}
        {--central-domain= : Central domain for tenancy}
        {--default-locale= : Default locale}
        {--fallback-locale= : Fallback locale}
        {--create-admin= : true|false}
        {--admin-name= : Initial admin name}
        {--admin-email= : Initial admin email}
        {--admin-password= : Initial admin password}
        {--run-migrations= : true|false}
        {--run-seeders= : true|false}
        {--install-frontend= : true|false}
        {--install-tenancy= : true|false}
        {--sync-environment= : true|false}';

    protected $description = 'Interactively install and configure Laravel CorePanel.';

    /**
     * @var list<string>
     */
    protected $aliases = ['core:install'];

    public function handle(): int
    {
        $this->components->info('Installing Laravel CorePanel...');

        try {
            app(CorePanelInstallerInterface::class)->install($this->resolveOptions(), $this);
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function resolveOptions(): CorePanelInstallOptions
    {
        /** @var CorePanelConfig $config */
        $config = app(CorePanelConfig::class);

        $appUrl = $this->textOption('app-url', 'APP_URL?', $this->defaultAppUrl());
        $databaseConnection = $this->databaseConnectionOption('pgsql');
        $databaseDefaults = $this->databaseDefaults($databaseConnection);

        $databaseHost = $this->textOption('db-host', 'DB Host?', $databaseDefaults['host']);
        $databasePort = $this->textOption('db-port', 'DB Port?', $databaseDefaults['port']);
        $databaseName = $this->textOption('db-database', 'DB Datenbank?', $databaseDefaults['database']);
        $databaseUsername = $this->textOption('db-username', 'DB Benutzer?', $databaseDefaults['username']);
        $databasePassword = $this->textOption('db-password', 'DB Passwort?', $databaseDefaults['password']);
        $databaseTestName = $this->textOption('db-database-test', 'DB Test-Datenbank?', sprintf('%s_test', $databaseName));
        $defaultLocale = $this->textOption('default-locale', 'Default Locale?', $config->i18n->defaultLocale);
        $fallbackLocale = $this->textOption('fallback-locale', 'Fallback Locale?', $config->i18n->fallbackLocale);
        $createAdmin = $this->booleanOption('create-admin', 'Admin User erstellen?', true);
        $runMigrations = $this->booleanOption('run-migrations', 'Migrations jetzt ausführen?', true);
        $runSeeders = $this->resolveRunSeeders($runMigrations);
        $installFrontend = $this->booleanOption('install-frontend', 'Node-Abhängigkeiten installieren und Frontend bauen?', true);
        $installTenancy = $this->booleanOption('install-tenancy', 'Tenancy Addon installieren?', false);
        $centralDomain = $installTenancy
            ? $this->normalizeCentralDomain(
                $this->textOption('central-domain', 'Zentrale Domain für Tenancy?', $this->appHost($appUrl)),
            )
            : null;
        $syncEnvironment = $this->booleanOption('sync-environment', 'Umgebungsdatei synchronisieren?', true);

        return new CorePanelInstallOptions(
            appUrl: $appUrl,
            databaseConnection: $databaseConnection,
            databaseHost: $databaseHost,
            databasePort: $databasePort,
            databaseName: $databaseName,
            databaseUsername: $databaseUsername,
            databasePassword: $databasePassword,
            databaseTestName: $databaseTestName,
            centralDomain: $centralDomain,
            defaultLocale: $defaultLocale,
            fallbackLocale: $fallbackLocale,
            createAdmin: $createAdmin,
            adminName: $createAdmin ? $this->textOption('admin-name', 'Admin Name?', 'Admin User') : null,
            adminEmail: $createAdmin ? $this->textOption('admin-email', 'Admin E-Mail?', 'admin@example.test') : null,
            adminPassword: $createAdmin ? $this->passwordOption('admin-password', 'Admin Passwort?') : null,
            enableHorizon: true,
            enableSocialLogin: false,
            runMigrations: $runMigrations,
            runSeeders: $runSeeders,
            installFrontend: $installFrontend,
            installTenancy: $installTenancy,
            syncEnvironment: $syncEnvironment,
            force: (bool) $this->option('force'),
        );
    }

    private function databaseConnectionOption(string $default): string
    {
        $value = $this->option('db-connection');

        if (is_string($value) && $value !== '') {
            return $this->normalizeDatabaseConnection($value);
        }

        if ($this->input->isInteractive()) {
            /** @var string $selected */
            $selected = select(
                label: 'DB Treiber?',
                options: [
                    'pgsql' => 'PostgreSQL',
                    'mysql' => 'MySQL',
                ],
                default: $default,
            );

            return $selected;
        }

        return $default;
    }

    private function booleanOption(string $name, string $label, bool $default): bool
    {
        $value = $this->option($name);

        if (is_string($value)) {
            return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
        }

        if ($this->input->isInteractive()) {
            return confirm(
                label: $label,
                default: $default,
            );
        }

        return $default;
    }

    private function textOption(string $name, string $label, string $default): string
    {
        $value = $this->option($name);

        if (is_string($value) && $value !== '') {
            return $value;
        }

        if ($this->input->isInteractive()) {
            return text(
                label: $label,
                default: $default,
                required: true,
            );
        }

        return $default;
    }

    private function passwordOption(string $name, string $label): ?string
    {
        $value = $this->option($name);

        if (is_string($value) && $value !== '') {
            return $value;
        }

        if ($this->input->isInteractive()) {
            return password(
                label: $label,
            );
        }

        return null;
    }

    private function resolveRunSeeders(bool $runMigrations): bool
    {
        if (! $runMigrations) {
            $value = $this->option('run-seeders');
            $explicitlyEnabled = is_string($value)
                ? (filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false)
                : false;

            if ($explicitlyEnabled) {
                throw new RuntimeException(
                    'Seeders cannot run when migrations are disabled. Enable migrations or set --run-seeders=false.',
                );
            }

            return false;
        }

        return $this->booleanOption('run-seeders', 'Seeder jetzt ausführen?', true);
    }

    /**
     * @return array{host:string, port:string, database:string, username:string, password:string}
     */
    private function databaseDefaults(string $connection): array
    {
        return match ($connection) {
            'mysql' => [
                'host' => '127.0.0.1',
                'port' => '3306',
                'database' => 'core_panel',
                'username' => 'core_panel',
                'password' => 'core_panel',
            ],
            'pgsql' => [
                'host' => '127.0.0.1',
                'port' => '5432',
                'database' => 'core_panel',
                'username' => 'core_panel',
                'password' => 'core_panel',
            ],
            default => throw new \InvalidArgumentException(sprintf('Unsupported database connection [%s].', $connection)),
        };
    }

    private function normalizeDatabaseConnection(string $connection): string
    {
        $normalized = strtolower(trim($connection));

        if (! in_array($normalized, ['pgsql', 'mysql'], true)) {
            throw new \InvalidArgumentException(sprintf(
                'Unsupported database connection [%s]. Use pgsql or mysql.',
                $connection,
            ));
        }

        return $normalized;
    }

    private function appHost(string $appUrl): string
    {
        $host = parse_url($appUrl, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : 'localhost';
    }

    private function normalizeCentralDomain(string $value): string
    {
        $normalized = trim($value);

        if ($normalized === '') {
            return 'localhost';
        }

        $candidate = str_contains($normalized, '://') || str_starts_with($normalized, '//')
            ? $normalized
            : 'https://'.$normalized;
        $host = parse_url($candidate, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : $normalized;
    }

    private function defaultAppUrl(): string
    {
        $configuredUrl = rtrim((string) config('app.url', ''), '/');
        $configuredHost = parse_url($configuredUrl, PHP_URL_HOST);

        if (
            is_string($configuredHost)
            && $configuredHost !== ''
            && ! in_array($configuredHost, ['127.0.0.1', 'localhost', 'laravel.test'], true)
        ) {
            return $configuredUrl;
        }

        $projectSlug = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', basename(base_path())));
        $projectSlug = trim($projectSlug, '-');

        if ($projectSlug === '') {
            $projectSlug = 'core-panel-app';
        }

        return sprintf('https://%s.test', $projectSlug);
    }
}
