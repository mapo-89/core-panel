<?php

declare(strict_types=1);

use CorePanel\Console\ConvertMySqlDatetimesCommand;
use Illuminate\Support\Facades\Artisan;

it('registers the MySQL datetime conversion command', function (): void {
    $commands = Artisan::all();

    expect($commands)->toHaveKey('core-panel:convert-mysql-datetimes')
        ->and($commands['core-panel:convert-mysql-datetimes']->getDefinition()->hasOption('database'))->toBeTrue();
});

it('only allowlists legacy datetime columns and excludes OAuth expiry instants', function (): void {
    $columns = config('core-panel.database.mysql_datetime_conversion.datasets.central');

    expect($columns)->toBeArray()
        ->and($columns['oauth_access_tokens'])->toBe(['created_at', 'last_used_at', 'updated_at'])
        ->not->toHaveKey('oauth_auth_codes')
        ->not->toHaveKey('oauth_refresh_tokens')
        ->not->toHaveKey('oauth_device_codes');
});

it('recognizes local datetimes made ambiguous by a DST fallback', function (): void {
    $command = app(ConvertMySqlDatetimesCommand::class);
    $method = new ReflectionMethod($command, 'isDstFallbackLocalValue');

    expect($method->invoke($command, '2025-10-26 02:30:00', 'Europe/Berlin'))->toBeTrue()
        ->and($method->invoke($command, '2025-10-26 03:30:00', 'Europe/Berlin'))->toBeFalse();
});

it('rejects ambiguous and nonexistent source wall-clock datetimes', function (): void {
    $command = app(ConvertMySqlDatetimesCommand::class);
    $method = new ReflectionMethod($command, 'sourceWallClockIssue');

    expect($method->invoke($command, '2025-10-26 02:30:00', 'Europe/Berlin'))->toBe('Ambiguous during daylight-saving fall back')
        ->and($method->invoke($command, '2025-03-30 02:30:00', 'Europe/Berlin'))->toBe('Nonexistent during daylight-saving spring forward')
        ->and($method->invoke($command, '2025-03-30 03:30:00', 'Europe/Berlin'))->toBeNull();
});
