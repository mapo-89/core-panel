<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Process;

function fakeUpdaterComposeLabels(string $workdir, string $composeFiles): void
{
    Process::fake(function ($process) use ($workdir, $composeFiles) {
        if (($process->command[2] ?? null) === 'ls') {
            return Process::result(output: "updater-container\n");
        }

        return Process::result(output: json_encode([
            'com.docker.compose.project' => 'core-panel',
            'com.docker.compose.project.config_files' => $composeFiles,
            'com.docker.compose.project.working_dir' => $workdir,
            'com.docker.compose.service' => 'system-updater',
        ], JSON_THROW_ON_ERROR));
    });
}

it('synchronizes the host environment file from the template through the artisan command', function (): void {
    $temporaryBasePath = sys_get_temp_dir().'/core-panel-env-command-'.bin2hex(random_bytes(5));

    mkdir($temporaryBasePath, 0777, true);
    copy(__DIR__.'/../../stubs/docker-compose.prod.yml', $temporaryBasePath.'/docker-compose.prod.yml');
    copy(__DIR__.'/../../stubs/docker-compose.registry.yml', $temporaryBasePath.'/docker-compose.registry.yml');
    copy(
        __DIR__.'/../Fixtures/scaffolds/release-1.5.0/docker-compose.dev.yml',
        $temporaryBasePath.'/docker-compose.dev.yml',
    );

    file_put_contents($temporaryBasePath.'/.env', implode(PHP_EOL, [
        'APP_NAME=HostApp',
        'DB_CONNECTION=sqlite',
        'CACHE_STORE=database',
        'SYSTEM_UPDATER_COMPOSE_FILES=docker-compose.prod.yml,docker-compose.registry.yml',
        'SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler,nginx',
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
        ->and($contents)->toContain('SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler')
        ->and($contents)->not->toContain('SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler,nginx')
        ->and($contents)->toContain('LEGACY_ONLY=value')
        ->and($backupContents)->toContain('APP_NAME=HostApp')
        ->and($backupContents)->toContain('SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler,nginx')
        ->and($backupContents)->toContain('LEGACY_ONLY=value');
});

it('migrates a quoted legacy updater service list for the unified topology', function (): void {
    $temporaryBasePath = sys_get_temp_dir().'/core-panel-env-command-quoted-runtime-'.bin2hex(random_bytes(5));

    mkdir($temporaryBasePath, 0777, true);
    copy(__DIR__.'/../../stubs/docker-compose.prod.yml', $temporaryBasePath.'/docker-compose.prod.yml');
    file_put_contents($temporaryBasePath.'/.env', implode(PHP_EOL, [
        'SYSTEM_UPDATER_COMPOSE_FILES=docker-compose.prod.yml',
        'SYSTEM_UPDATER_RUNTIME_SERVICES=  "app,horizon,scheduler,nginx"  ',
        '',
    ]));

    $this->artisan('core-panel:env:sync', [
        '--base-path' => $temporaryBasePath,
    ])->assertExitCode(0);

    expect(file_get_contents($temporaryBasePath.'/.env'))
        ->toContain("SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler\n")
        ->not->toContain('SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler,nginx')
        ->not->toContain('"app,horizon,scheduler,nginx"');
});

it('migrates a whitespace-separated legacy updater service list for the unified topology', function (): void {
    $temporaryBasePath = sys_get_temp_dir().'/core-panel-env-command-spaced-runtime-'.bin2hex(random_bytes(5));

    mkdir($temporaryBasePath, 0777, true);
    copy(__DIR__.'/../../stubs/docker-compose.prod.yml', $temporaryBasePath.'/docker-compose.prod.yml');
    file_put_contents($temporaryBasePath.'/.env', implode(PHP_EOL, [
        'SYSTEM_UPDATER_COMPOSE_FILES=docker-compose.prod.yml',
        'SYSTEM_UPDATER_RUNTIME_SERVICES=app, horizon, scheduler, nginx',
        '',
    ]));

    $this->artisan('core-panel:env:sync', [
        '--base-path' => $temporaryBasePath,
    ])->assertExitCode(0);

    expect(file_get_contents($temporaryBasePath.'/.env'))
        ->toContain("SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler\n")
        ->not->toContain('SYSTEM_UPDATER_RUNTIME_SERVICES=app, horizon, scheduler, nginx');
});

it('migrates the legacy updater service list when block compose services contain whitespace-only lines', function (): void {
    $temporaryBasePath = sys_get_temp_dir().'/core-panel-env-command-whitespace-compose-runtime-'.bin2hex(random_bytes(5));

    mkdir($temporaryBasePath, 0777, true);
    file_put_contents($temporaryBasePath.'/compose.yml', implode(PHP_EOL, [
        'services:',
        '    ',
        '  app:',
        '    image: example/app',
        '  horizon:',
        '    image: example/app',
        '  scheduler:',
        '    image: example/app',
        '',
    ]));
    file_put_contents($temporaryBasePath.'/.env', implode(PHP_EOL, [
        'SYSTEM_UPDATER_COMPOSE_FILES=compose.yml',
        'SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler,nginx',
        '',
    ]));

    $this->artisan('core-panel:env:sync', [
        '--base-path' => $temporaryBasePath,
    ])->assertExitCode(0);

    expect(file_get_contents($temporaryBasePath.'/.env'))
        ->toContain("SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler\n")
        ->not->toContain('SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler,nginx');
});

it('migrates a quoted legacy updater service list with a trailing dotenv comment', function (): void {
    $temporaryBasePath = sys_get_temp_dir().'/core-panel-env-command-commented-runtime-'.bin2hex(random_bytes(5));

    mkdir($temporaryBasePath, 0777, true);
    copy(__DIR__.'/../../stubs/docker-compose.prod.yml', $temporaryBasePath.'/docker-compose.prod.yml');
    file_put_contents($temporaryBasePath.'/.env', implode(PHP_EOL, [
        'SYSTEM_UPDATER_COMPOSE_FILES=docker-compose.prod.yml',
        'SYSTEM_UPDATER_RUNTIME_SERVICES="app,horizon,scheduler,nginx" # runtime containers',
        '',
    ]));

    $this->artisan('core-panel:env:sync', [
        '--base-path' => $temporaryBasePath,
    ])->assertExitCode(0);

    expect(file_get_contents($temporaryBasePath.'/.env'))
        ->toContain("SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler\n")
        ->not->toContain('SYSTEM_UPDATER_RUNTIME_SERVICES="app,horizon,scheduler,nginx" # runtime containers');
});

it('normalizes commented compose settings before detecting the unified topology', function (): void {
    $temporaryBasePath = sys_get_temp_dir().'/core-panel-env-command-commented-compose-settings-'.bin2hex(random_bytes(5));
    $composeWorkdir = $temporaryBasePath.'/deployment';

    mkdir($composeWorkdir, 0777, true);
    copy(__DIR__.'/../../stubs/docker-compose.prod.yml', $composeWorkdir.'/docker-compose.prod.yml');
    file_put_contents($temporaryBasePath.'/.env', implode(PHP_EOL, [
        'SYSTEM_UPDATER_COMPOSE_FILES="docker-compose.prod.yml" # production',
        'SYSTEM_UPDATER_COMPOSE_WORKDIR="deployment" # active stack',
        'SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler,nginx',
        '',
    ]));

    $this->artisan('core-panel:env:sync', [
        '--base-path' => $temporaryBasePath,
    ])->assertExitCode(0);

    expect(file_get_contents($temporaryBasePath.'/.env'))
        ->toContain("SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler\n")
        ->not->toContain('SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler,nginx');
});

it('preserves nginx in the updater service list when standalone sync detects the legacy runtime topology', function (): void {
    $temporaryBasePath = sys_get_temp_dir().'/core-panel-env-command-legacy-runtime-'.bin2hex(random_bytes(5));

    mkdir($temporaryBasePath, 0777, true);
    copy(
        __DIR__.'/../Fixtures/scaffolds/release-1.5.0/docker-compose.prod.yml',
        $temporaryBasePath.'/docker-compose.prod.yml',
    );
    file_put_contents($temporaryBasePath.'/.env', implode(PHP_EOL, [
        'APP_NAME=HostApp',
        'SYSTEM_UPDATER_COMPOSE_FILES=docker-compose.prod.yml',
        'SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler,nginx',
        '',
    ]));

    $this->artisan('core-panel:env:sync', [
        '--base-path' => $temporaryBasePath,
    ])
        ->expectsOutputToContain('Environment synchronized')
        ->assertExitCode(0);

    $contents = (string) file_get_contents($temporaryBasePath.'/.env');

    expect($contents)
        ->toContain("SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler,nginx\n")
        ->not->toContain("SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler\n")
        ->and(file_get_contents($temporaryBasePath.'/.env.backup'))
        ->toContain('SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler,nginx');
});

it('resolves an auto-detected unified Portainer topology before migrating the updater service list', function (): void {
    $temporaryBasePath = sys_get_temp_dir().'/core-panel-env-command-auto-portainer-runtime-'.bin2hex(random_bytes(5));

    mkdir($temporaryBasePath, 0777, true);
    copy(__DIR__.'/../../stubs/docker-compose.portainer.yml', $temporaryBasePath.'/docker-compose.portainer.yml');
    fakeUpdaterComposeLabels($temporaryBasePath, 'docker-compose.portainer.yml');
    file_put_contents($temporaryBasePath.'/.env', implode(PHP_EOL, [
        'SYSTEM_UPDATER_COMPOSE_FILES=auto',
        'SYSTEM_UPDATER_COMPOSE_WORKDIR=/workspace',
        'SYSTEM_UPDATER_PROJECT_PATH='.$temporaryBasePath.'/unmounted-host-project',
        'SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler,nginx',
        '',
    ]));

    $this->artisan('core-panel:env:sync', [
        '--base-path' => $temporaryBasePath,
    ])->assertExitCode(0);

    expect(file_get_contents($temporaryBasePath.'/.env'))
        ->toContain("SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler\n")
        ->not->toContain('SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler,nginx');
});

it('preserves nginx for an auto-detected legacy Portainer topology', function (): void {
    $temporaryBasePath = sys_get_temp_dir().'/core-panel-env-command-auto-legacy-portainer-runtime-'.bin2hex(random_bytes(5));
    $activeProjectPath = $temporaryBasePath.'/active-stack';

    mkdir($activeProjectPath, 0777, true);
    copy(__DIR__.'/../../stubs/docker-compose.portainer.yml', $temporaryBasePath.'/docker-compose.portainer.yml');
    copy(
        __DIR__.'/../Fixtures/scaffolds/release-1.5.0/docker-compose.portainer.yml',
        $activeProjectPath.'/docker-compose.portainer.yml',
    );
    fakeUpdaterComposeLabels($activeProjectPath, 'docker-compose.portainer.yml');
    file_put_contents($temporaryBasePath.'/.env', implode(PHP_EOL, [
        'SYSTEM_UPDATER_COMPOSE_FILES=auto',
        'SYSTEM_UPDATER_COMPOSE_WORKDIR=/workspace',
        'SYSTEM_UPDATER_PROJECT_PATH='.$activeProjectPath,
        'SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler,nginx',
        '',
    ]));

    $this->artisan('core-panel:env:sync', [
        '--base-path' => $temporaryBasePath,
    ])->assertExitCode(0);

    expect(file_get_contents($temporaryBasePath.'/.env'))
        ->toContain("SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler,nginx\n")
        ->not->toContain("SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler\n");
});

it('uses the active Compose labels instead of the published Portainer filename in auto mode', function (): void {
    $temporaryBasePath = sys_get_temp_dir().'/core-panel-env-command-auto-custom-runtime-'.bin2hex(random_bytes(5));
    $activeProjectPath = $temporaryBasePath.'/active-stack';

    mkdir($activeProjectPath, 0777, true);
    copy(__DIR__.'/../../stubs/docker-compose.portainer.yml', $temporaryBasePath.'/docker-compose.portainer.yml');
    copy(
        __DIR__.'/../Fixtures/scaffolds/release-1.5.0/docker-compose.portainer.yml',
        $activeProjectPath.'/custom-production-stack.yml',
    );
    fakeUpdaterComposeLabels($activeProjectPath, 'custom-production-stack.yml');
    file_put_contents($temporaryBasePath.'/.env', implode(PHP_EOL, [
        'SYSTEM_UPDATER_COMPOSE_FILES=auto',
        'SYSTEM_UPDATER_COMPOSE_PROJECT_NAME=core-panel',
        'SYSTEM_UPDATER_COMPOSE_WORKDIR=auto',
        'SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler,nginx',
        '',
    ]));

    $this->artisan('core-panel:env:sync', [
        '--base-path' => $temporaryBasePath,
    ])->assertExitCode(0);

    expect(file_get_contents($temporaryBasePath.'/.env'))
        ->toContain("SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler,nginx\n")
        ->not->toContain("SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler\n");

    Process::assertRan(fn ($process): bool => $process->command === [
        'docker',
        'container',
        'inspect',
        '--format',
        '{{json .Config.Labels}}',
        'updater-container',
    ]);
});

it('preserves the runtime service list when auto discovery finds multiple updater stacks', function (): void {
    $temporaryBasePath = sys_get_temp_dir().'/core-panel-env-command-auto-ambiguous-runtime-'.bin2hex(random_bytes(5));
    $inspectCalls = 0;

    mkdir($temporaryBasePath, 0777, true);
    copy(__DIR__.'/../../stubs/docker-compose.portainer.yml', $temporaryBasePath.'/docker-compose.portainer.yml');
    Process::fake(function ($process) use (&$inspectCalls) {
        if (($process->command[2] ?? null) === 'ls') {
            return Process::result(output: "first-stack-updater\nsecond-stack-updater\n");
        }

        $inspectCalls++;

        return Process::result(output: '{}');
    });
    file_put_contents($temporaryBasePath.'/.env', implode(PHP_EOL, [
        'SYSTEM_UPDATER_COMPOSE_FILES=auto',
        'SYSTEM_UPDATER_COMPOSE_PROJECT_NAME=auto',
        'SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler,nginx',
        '',
    ]));

    $this->artisan('core-panel:env:sync', [
        '--base-path' => $temporaryBasePath,
    ])->assertExitCode(0);

    expect(file_get_contents($temporaryBasePath.'/.env'))
        ->toContain("SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler,nginx\n")
        ->not->toContain("SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler\n")
        ->and($inspectCalls)->toBe(0);
});

it('does not substitute the application root for an inaccessible labeled Compose workdir', function (): void {
    $temporaryBasePath = sys_get_temp_dir().'/core-panel-env-command-auto-inaccessible-runtime-'.bin2hex(random_bytes(5));

    mkdir($temporaryBasePath, 0777, true);
    copy(__DIR__.'/../../stubs/docker-compose.portainer.yml', $temporaryBasePath.'/docker-compose.portainer.yml');
    fakeUpdaterComposeLabels('/host-only/custom-stack', 'docker-compose.portainer.yml');
    file_put_contents($temporaryBasePath.'/.env', implode(PHP_EOL, [
        'SYSTEM_UPDATER_COMPOSE_FILES=auto',
        'SYSTEM_UPDATER_COMPOSE_PROJECT_NAME=core-panel',
        'SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler,nginx',
        '',
    ]));

    $this->artisan('core-panel:env:sync', [
        '--base-path' => $temporaryBasePath,
    ])->assertExitCode(0);

    expect(file_get_contents($temporaryBasePath.'/.env'))
        ->toContain("SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler,nginx\n")
        ->not->toContain("SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler\n");
});

it('resolves relative compose files from the configured updater workdir', function (): void {
    $temporaryBasePath = sys_get_temp_dir().'/core-panel-env-command-compose-workdir-'.bin2hex(random_bytes(5));
    $composeWorkdir = $temporaryBasePath.'/deployment';

    mkdir($composeWorkdir, 0777, true);
    copy(__DIR__.'/../../stubs/docker-compose.prod.yml', $temporaryBasePath.'/docker-compose.prod.yml');
    copy(
        __DIR__.'/../Fixtures/scaffolds/release-1.5.0/docker-compose.prod.yml',
        $composeWorkdir.'/docker-compose.prod.yml',
    );
    file_put_contents($temporaryBasePath.'/.env', implode(PHP_EOL, [
        'SYSTEM_UPDATER_COMPOSE_FILES=docker-compose.prod.yml',
        'SYSTEM_UPDATER_COMPOSE_WORKDIR='.$composeWorkdir,
        'SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler,nginx',
        '',
    ]));

    $this->artisan('core-panel:env:sync', [
        '--base-path' => $temporaryBasePath,
    ])->assertExitCode(0);

    expect(file_get_contents($temporaryBasePath.'/.env'))
        ->toContain("SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler,nginx\n")
        ->not->toContain("SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler\n");
});

it('preserves the legacy service list when an explicit compose file is unreadable on the host', function (): void {
    $temporaryBasePath = sys_get_temp_dir().'/core-panel-env-command-explicit-inaccessible-runtime-'.bin2hex(random_bytes(5));

    mkdir($temporaryBasePath, 0777, true);
    copy(__DIR__.'/../../stubs/docker-compose.prod.yml', $temporaryBasePath.'/docker-compose.prod.yml');
    file_put_contents($temporaryBasePath.'/.env', implode(PHP_EOL, [
        'SYSTEM_UPDATER_COMPOSE_FILES=/workspace/deployment/compose.yml',
        'SYSTEM_UPDATER_COMPOSE_WORKDIR=/workspace/deployment',
        'SYSTEM_UPDATER_PROJECT_PATH='.$temporaryBasePath,
        'SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler,nginx',
        '',
    ]));

    $this->artisan('core-panel:env:sync', [
        '--base-path' => $temporaryBasePath,
    ])->assertExitCode(0);

    expect(file_get_contents($temporaryBasePath.'/.env'))
        ->toContain("SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler,nginx\n")
        ->not->toContain("SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler\n");
});

it('preserves the legacy service list when compose services use unsupported flow syntax', function (): void {
    $temporaryBasePath = sys_get_temp_dir().'/core-panel-env-command-flow-services-runtime-'.bin2hex(random_bytes(5));

    mkdir($temporaryBasePath, 0777, true);
    file_put_contents(
        $temporaryBasePath.'/compose.yml',
        "services: { app: { image: example/app }, nginx: { image: nginx:alpine } }\n",
    );
    file_put_contents($temporaryBasePath.'/.env', implode(PHP_EOL, [
        'SYSTEM_UPDATER_COMPOSE_FILES=compose.yml',
        'SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler,nginx',
        '',
    ]));

    $this->artisan('core-panel:env:sync', [
        '--base-path' => $temporaryBasePath,
    ])->assertExitCode(0);

    expect(file_get_contents($temporaryBasePath.'/.env'))
        ->toContain("SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler,nginx\n")
        ->not->toContain("SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler\n");
});

it('only uses the top-level compose services block when detecting nginx', function (): void {
    $temporaryBasePath = sys_get_temp_dir().'/core-panel-env-command-nested-services-runtime-'.bin2hex(random_bytes(5));

    mkdir($temporaryBasePath, 0777, true);
    file_put_contents($temporaryBasePath.'/compose.yml', <<<'YAML'
x-metadata:
  services:
    generated: true

services:
  app:
    image: example/app
  nginx:
    image: nginx:alpine
YAML.PHP_EOL);
    file_put_contents($temporaryBasePath.'/.env', implode(PHP_EOL, [
        'SYSTEM_UPDATER_COMPOSE_FILES=compose.yml',
        'SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler,nginx',
        '',
    ]));

    $this->artisan('core-panel:env:sync', [
        '--base-path' => $temporaryBasePath,
    ])->assertExitCode(0);

    expect(file_get_contents($temporaryBasePath.'/.env'))
        ->toContain("SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler,nginx\n")
        ->not->toContain("SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler\n");
});

it('uses the unified service default when the topology is unknown and the service list is absent', function (): void {
    $temporaryBasePath = sys_get_temp_dir().'/core-panel-env-command-unknown-runtime-without-services-'.bin2hex(random_bytes(5));

    mkdir($temporaryBasePath, 0777, true);
    file_put_contents($temporaryBasePath.'/.env', implode(PHP_EOL, [
        'SYSTEM_UPDATER_COMPOSE_FILES=/workspace/deployment/compose.yml',
        'SYSTEM_UPDATER_COMPOSE_WORKDIR=/workspace/deployment',
        'SYSTEM_UPDATER_PROJECT_PATH='.$temporaryBasePath,
        '',
    ]));

    $this->artisan('core-panel:env:sync', [
        '--base-path' => $temporaryBasePath,
    ])->assertExitCode(0);

    expect(file_get_contents($temporaryBasePath.'/.env'))
        ->toContain("SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler\n")
        ->not->toContain('SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler,nginx');
});

it('maps the updater workspace to the configured host project path', function (): void {
    $temporaryBasePath = sys_get_temp_dir().'/core-panel-env-command-project-path-'.bin2hex(random_bytes(5));
    $projectPath = $temporaryBasePath.'/deployed-project';

    mkdir($projectPath, 0777, true);
    copy(__DIR__.'/../../stubs/docker-compose.prod.yml', $temporaryBasePath.'/docker-compose.prod.yml');
    copy(
        __DIR__.'/../Fixtures/scaffolds/release-1.5.0/docker-compose.prod.yml',
        $projectPath.'/docker-compose.prod.yml',
    );
    file_put_contents($temporaryBasePath.'/.env', implode(PHP_EOL, [
        'SYSTEM_UPDATER_COMPOSE_FILES=docker-compose.prod.yml',
        'SYSTEM_UPDATER_COMPOSE_WORKDIR=/workspace',
        'SYSTEM_UPDATER_PROJECT_PATH='.$projectPath,
        'SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler,nginx',
        '',
    ]));

    $this->artisan('core-panel:env:sync', [
        '--base-path' => $temporaryBasePath,
    ])->assertExitCode(0);

    expect(file_get_contents($temporaryBasePath.'/.env'))
        ->toContain("SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler,nginx\n")
        ->not->toContain("SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler\n");
});

it('maps nested updater workspace directories beneath the configured host project path', function (): void {
    $temporaryBasePath = sys_get_temp_dir().'/core-panel-env-command-nested-project-path-'.bin2hex(random_bytes(5));
    $projectPath = $temporaryBasePath.'/deployed-project';
    $composeWorkdir = $projectPath.'/deployment';

    mkdir($composeWorkdir, 0777, true);
    copy(__DIR__.'/../../stubs/docker-compose.prod.yml', $temporaryBasePath.'/docker-compose.prod.yml');
    copy(
        __DIR__.'/../Fixtures/scaffolds/release-1.5.0/docker-compose.prod.yml',
        $composeWorkdir.'/docker-compose.prod.yml',
    );
    file_put_contents($temporaryBasePath.'/.env', implode(PHP_EOL, [
        'SYSTEM_UPDATER_COMPOSE_FILES=docker-compose.prod.yml',
        'SYSTEM_UPDATER_COMPOSE_WORKDIR=/workspace/deployment',
        'SYSTEM_UPDATER_PROJECT_PATH='.$projectPath,
        'SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler,nginx',
        '',
    ]));

    $this->artisan('core-panel:env:sync', [
        '--base-path' => $temporaryBasePath,
    ])->assertExitCode(0);

    expect(file_get_contents($temporaryBasePath.'/.env'))
        ->toContain("SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler,nginx\n")
        ->not->toContain("SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler\n");
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
        'SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler,custom-proxy',
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
        ->and($contents)->toContain('SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler,custom-proxy')
        ->and($contents)->toContain('LEGACY_ONLY=value');
});
