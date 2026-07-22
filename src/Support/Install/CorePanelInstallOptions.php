<?php

declare(strict_types=1);

namespace CorePanel\Support\Install;

final readonly class CorePanelInstallOptions
{
    public function __construct(
        public string $appUrl,
        public string $databaseConnection,
        public string $databaseHost,
        public string $databasePort,
        public string $databaseName,
        public string $databaseUsername,
        public string $databasePassword,
        public string $databaseTestName,
        public ?string $centralDomain,
        public string $defaultLocale,
        public string $fallbackLocale,
        public bool $createAdmin,
        public ?string $adminName,
        public ?string $adminEmail,
        public ?string $adminPassword,
        public bool $enableHorizon,
        public bool $enableSocialLogin,
        public bool $runMigrations,
        public bool $runSeeders,
        public bool $installFrontend,
        public bool $installTenancy,
        public bool $syncEnvironment,
        public bool $force,
        public bool $installDeveloperTooling = false,
    ) {}
}
