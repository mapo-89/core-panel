<?php

declare(strict_types=1);

require_once __DIR__.'/bootstrap_spatie_fakes.php';
require_once __DIR__.'/bootstrap_socialite_fakes.php';
require_once __DIR__.'/../../core-panel-tenancy/tests/TestCase.php';

use CorePanel\Tests\TestCase;
use CorePanelTenancy\Tests\TestCase as CorePanelTenancyTestCase;

uses(TestCase::class)->in('Feature', 'Unit');
uses(CorePanelTenancyTestCase::class)->in('../../core-panel-tenancy/tests/Feature');

function corePanelTestbenchDatabaseAvailable(): bool
{
    return extension_loaded('pdo_sqlite') && in_array('sqlite', PDO::getAvailableDrivers(), true);
}
