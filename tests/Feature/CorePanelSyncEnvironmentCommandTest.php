<?php

declare(strict_types=1);

it('synchronizes the host environment file from the template through the artisan command', function (): void {
    $temporaryBasePath = sys_get_temp_dir().'/core-panel-env-command-'.bin2hex(random_bytes(5));

    mkdir($temporaryBasePath, 0777, true);

    file_put_contents($temporaryBasePath.'/.env', implode(PHP_EOL, [
        'APP_NAME=HostApp',
        'DB_CONNECTION=sqlite',
        'CACHE_STORE=database',
        'LEGACY_ONLY=value',
        '',
    ]));

    $this->artisan('core-panel:env:sync', [
        '--base-path' => $temporaryBasePath,
    ])
        ->expectsOutputToContain('Environment synchronized')
        ->assertExitCode(0);

    $contents = file_get_contents($temporaryBasePath.'/.env');
    $backupContents = file_get_contents($temporaryBasePath.'/.env.backup');

    expect($contents)->toContain('APP_NAME=HostApp')
        ->and($contents)->toContain('DB_CONNECTION=sqlite')
        ->and($contents)->toContain('CACHE_STORE=database')
        ->and($contents)->toContain('QUEUE_CONNECTION=redis')
        ->and($contents)->toContain('REDIS_HOST=127.0.0.1')
        ->and($contents)->toContain('LEGACY_ONLY=value')
        ->and($backupContents)->toContain('APP_NAME=HostApp')
        ->and($backupContents)->toContain('LEGACY_ONLY=value');
});

it('can replace existing template-managed environment values when requested', function (): void {
    $temporaryBasePath = sys_get_temp_dir().'/core-panel-env-command-replace-'.bin2hex(random_bytes(5));

    mkdir($temporaryBasePath, 0777, true);

    file_put_contents($temporaryBasePath.'/.env', implode(PHP_EOL, [
        'APP_NAME=HostApp',
        'QUEUE_CONNECTION=sync',
        '',
    ]));

    $this->artisan('core-panel:env:sync', [
        '--base-path' => $temporaryBasePath,
        '--replace-template-values' => true,
    ])->assertExitCode(0);

    $contents = file_get_contents($temporaryBasePath.'/.env');

    expect($contents)->toContain('APP_NAME="CorePanel"')
        ->and($contents)->toContain('QUEUE_CONNECTION=redis');
});

it('preserves exported environment values while synchronizing template-managed keys', function (): void {
    $temporaryBasePath = sys_get_temp_dir().'/core-panel-env-command-exported-'.bin2hex(random_bytes(5));

    mkdir($temporaryBasePath, 0777, true);

    file_put_contents($temporaryBasePath.'/.env', implode(PHP_EOL, [
        'export APP_KEY=base64:host-secret-key',
        'export DB_PASSWORD=super-secret',
        'APP_NAME=HostApp',
        '',
    ]));

    $this->artisan('core-panel:env:sync', [
        '--base-path' => $temporaryBasePath,
    ])->assertExitCode(0);

    $contents = file_get_contents($temporaryBasePath.'/.env');

    expect($contents)->toContain('APP_KEY=base64:host-secret-key')
        ->and($contents)->toContain('DB_PASSWORD=super-secret')
        ->and($contents)->toContain('APP_NAME=HostApp')
        ->and($contents)->not->toContain('APP_KEY='."\n")
        ->and($contents)->not->toContain('DB_PASSWORD=CHANGEME');
});

it('preserves supported environment keys that are not listed in the template', function (): void {
    $temporaryBasePath = sys_get_temp_dir().'/core-panel-env-command-supported-keys-'.bin2hex(random_bytes(5));

    mkdir($temporaryBasePath, 0777, true);

    file_put_contents($temporaryBasePath.'/.env', implode(PHP_EOL, [
        'APP_NAME=HostApp',
        'DB_SOCKET=/var/run/mysqld/mysqld.sock',
        'DB_TIMEZONE=Europe/Berlin',
        'CORE_PANEL_ROUTE_PREFIX=cp-admin',
        'LEGACY_ONLY=value',
        '',
    ]));

    $this->artisan('core-panel:env:sync', [
        '--base-path' => $temporaryBasePath,
    ])->assertExitCode(0);

    $contents = file_get_contents($temporaryBasePath.'/.env');

    expect($contents)->toContain('APP_NAME=HostApp')
        ->and($contents)->toContain('DB_SOCKET=/var/run/mysqld/mysqld.sock')
        ->and($contents)->toContain('DB_TIMEZONE=Europe/Berlin')
        ->and($contents)->toContain('CORE_PANEL_ROUTE_PREFIX=cp-admin')
        ->and($contents)->toContain('LEGACY_ONLY=value');
});
