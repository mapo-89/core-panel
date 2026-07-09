<?php

declare(strict_types=1);

require_once __DIR__.'/bootstrap_spatie_fakes.php';
require_once __DIR__.'/bootstrap_socialite_fakes.php';
require_once __DIR__.'/../../core-panel-tenancy/tests/TestCase.php';

use CorePanel\Tests\TestCase;
use CorePanelTenancy\Tests\TestCase as CorePanelTenancyTestCase;
use Illuminate\Support\Env;

uses(TestCase::class)->in('Feature', 'Unit');
uses(CorePanelTenancyTestCase::class)->in('../../core-panel-tenancy/tests/Feature');

beforeEach(function (): void {
    resetCorePanelTestEnvironment();
});

afterEach(function (): void {
    resetCorePanelTestEnvironment();
});

function corePanelTestbenchDatabaseAvailable(): bool
{
    return extension_loaded('pdo_sqlite') && in_array('sqlite', PDO::getAvailableDrivers(), true);
}

function setCorePanelTestEnvironmentValue(string $key, string $value): void
{
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

function unsetCorePanelTestEnvironmentValue(string $key): void
{
    putenv($key);
    unset($_ENV[$key], $_SERVER[$key]);
}

function resetCorePanelTestEnvironment(): void
{
    unsetCorePanelTestEnvironmentValue('USER_MODEL');
    unsetCorePanelTestEnvironmentValue('REGISTRATION_ENABLED');
    setCorePanelTestEnvironmentValue('ROUTE_PREFIX', 'admin');
    setCorePanelTestEnvironmentValue('FILESYSTEM_DISK', 'public');
    Env::enablePutenv();

    if (! app()->bound('config')) {
        return;
    }

    /** @var array<string, mixed> $rawConfig */
    $rawConfig = require __DIR__.'/../config/core-panel.php';
    config()->set('core-panel', $rawConfig);
}
