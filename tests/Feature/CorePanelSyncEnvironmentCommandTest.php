<?php

declare(strict_types=1);

it('synchronizes the host environment file from the template through the artisan command', function (): void {
    $temporaryBasePath = sys_get_temp_dir().'/core-panel-env-command-'.bin2hex(random_bytes(5));

    mkdir($temporaryBasePath, 0777, true);

    file_put_contents($temporaryBasePath.'/.env', implode(PHP_EOL, [
        'APP_NAME=HostApp',
        'DB_CONNECTION=sqlite',
        'CACHE_STORE=database',
        '',
    ]));

    $this->artisan('core-panel:env:sync', [
        '--base-path' => $temporaryBasePath,
    ])
        ->expectsOutputToContain('Environment synchronized')
        ->assertExitCode(0);

    $contents = file_get_contents($temporaryBasePath.'/.env');

    expect($contents)->toContain('APP_NAME=HostApp')
        ->and($contents)->toContain('DB_CONNECTION=sqlite')
        ->and($contents)->toContain('CACHE_STORE=database')
        ->and($contents)->toContain('QUEUE_CONNECTION=redis')
        ->and($contents)->toContain('REDIS_HOST=127.0.0.1');
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
