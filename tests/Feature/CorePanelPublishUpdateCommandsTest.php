<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Process;

function makePublishBasePath(string $suffix): string
{
    return corePanelTestTemporaryPath('publish-'.$suffix);
}

function readManifest(string $basePath): string
{
    return file_get_contents($basePath.'/storage/app/core-panel/published.json') ?: '';
}

function seedScaffoldManifest(string $basePath, string $relativePath, string $contents, string $packageVersion = '1.0.0'): void
{
    seedScaffoldManifestFiles($basePath, [$relativePath => $contents], $packageVersion);
}

/**
 * @param  array<string, string>  $files
 */
function seedScaffoldManifestFiles(string $basePath, array $files, string $packageVersion = '1.0.0'): void
{
    $manifestFiles = [];

    foreach ($files as $relativePath => $contents) {
        $sourceHash = hash('sha256', $contents);
        $snapshotPath = 'storage/app/core-panel/scaffolds/'.$sourceHash;

        if (! is_dir(dirname($basePath.'/'.$snapshotPath))) {
            mkdir(dirname($basePath.'/'.$snapshotPath), 0777, true);
        }
        file_put_contents($basePath.'/'.$snapshotPath, $contents);

        $manifestFiles[$relativePath] = [
            'destination_hash' => $sourceHash,
            'package_version' => $packageVersion,
            'snapshot' => $snapshotPath,
            'source_hash' => $sourceHash,
        ];
    }

    file_put_contents($basePath.'/storage/app/core-panel/scaffolds.json', json_encode([
        '_meta' => [
            'package_version' => $packageVersion,
        ],
        'files' => $manifestFiles,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
}

function currentCorePanelPackageVersion(): string
{
    $contents = file_get_contents(__DIR__.'/../../config/app-version.json');
    $decoded = json_decode((string) $contents, true, 512, JSON_THROW_ON_ERROR);

    expect($decoded)->toBeArray()
        ->and($decoded['release_version'] ?? null)->toBeString();

    return $decoded['release_version'];
}

function fakePublishUpdaterComposeLabels(string $workdir, string $composeFiles): void
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

function legacyCriticalBootstrapAppContents(): string
{
    return <<<'PHP'
<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\TrackUserPresence;
use CorePanel\Http\Middleware\ApplyCorePanelRuntimeSettings;
use CorePanel\Http\Middleware\CheckPermission;
use CorePanel\Http\Middleware\ResolveCorePanelLocale;
use CorePanel\Http\Middleware\SecurityHeaders;
use CorePanel\Http\Middleware\ShareLocaleDataWithInertia;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

/** @var callable(): array{web:string, api:?string, commands:?string, health:string} $corePanelRoutingPaths */
$corePanelRoutingPaths = static function (): array {
    $basePath = dirname(__DIR__);

    $apiRoutes = $basePath.'/routes/api.php';
    $centralRoutes = $basePath.'/routes/central.php';
    $consoleRoutes = $basePath.'/routes/console.php';

    return [
        'web' => file_exists($centralRoutes) ? $centralRoutes : $basePath.'/routes/web.php',
        'api' => file_exists($apiRoutes) ? $apiRoutes : null,
        'commands' => file_exists($consoleRoutes) ? $consoleRoutes : null,
        'health' => '/up',
    ];
};

['web' => $webRoutes, 'api' => $apiRoutes, 'commands' => $consoleRoutes, 'health' => $healthRoute] = $corePanelRoutingPaths();

$tenantSessionCookieMiddlewareClass = 'CorePanelTenancy\\Http\\Middleware\\SetTenantAwareSessionCookie';
$tenantSessionCookieMiddleware = class_exists($tenantSessionCookieMiddlewareClass)
    ? [$tenantSessionCookieMiddlewareClass]
    : [];

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: $webRoutes,
        api: $apiRoutes,
        commands: $consoleRoutes,
        health: $healthRoute,
    )
    ->withMiddleware(function (Middleware $middleware) use ($tenantSessionCookieMiddleware): void {
        $middleware->statefulApi();
        $middleware->redirectUsersTo(static fn (Request $request): string => '/'.trim((string) config('core-panel.route_prefix', 'admin'), '/'));
        $middleware->redirectGuestsTo(static fn (Request $request): ?string => $request->expectsJson() ? null : '/login');
        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
            'check.permission' => CheckPermission::class,
        ]);
        $middleware->group('universal', []);

        $middleware->web(prepend: $tenantSessionCookieMiddleware);
        $middleware->web(append: [
            ApplyCorePanelRuntimeSettings::class,
            SecurityHeaders::class,
            ResolveCorePanelLocale::class,
            ShareLocaleDataWithInertia::class,
            TrackUserPresence::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->api(append: [
            ApplyCorePanelRuntimeSettings::class,
            SecurityHeaders::class,
            ResolveCorePanelLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
PHP;
}

function legacyCriticalRoutesConsoleContents(): string
{
    return <<<'PHP'
<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

if ((bool) config('core-panel.horizon.enabled', true) && app()->bound('command.horizon.snapshot')) {
    Schedule::command('horizon:snapshot')->everyFiveMinutes();
}
PHP;
}

function legacyCriticalEnvExampleContents(): string
{
    return <<<'TEXT'
APP_NAME="CorePanel"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_VERSION=dev

APP_LOCALE=de
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=de_DE
LOG_CHANNEL=daily

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=core_panel
DB_USERNAME=core_panel
DB_PASSWORD=core_panel
DB_DATABASE_TEST=core_panel_test

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=public
QUEUE_CONNECTION=redis

CACHE_STORE=redis

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025

CORE_PANEL_ROUTE_PREFIX=admin
CORE_PANEL_PASSPORT_PERSONAL_ACCESS_CLIENTS_ENABLED=false
CORE_PANEL_PASSPORT_REFRESH_TOKEN_TTL_DAYS=30
CORE_PANEL_PASSPORT_TOKEN_TTL_MINUTES=15
CORE_PANEL_PASSPORT_PERSONAL_ACCESS_TOKEN_TTL_DAYS=180
CORE_PANEL_REGISTRATION_ENABLED=true
CORE_PANEL_SOCIAL_GITHUB_ENABLED=false
CORE_PANEL_SOCIAL_GOOGLE_ENABLED=false
CORE_PANEL_SOCIAL_MASTER_PROVIDER=
CORE_PANEL_SOCIAL_MICROSOFT_ENABLED=false
CORE_PANEL_DARK_MODE=false
CORE_PANEL_PUBLISH_THEME=true
CORE_PANEL_FILES_DISK=public
CORE_PANEL_HORIZON_ENABLED=true
HORIZON_SLACK_CHANNEL=
HORIZON_SLACK_WEBHOOK_URL=

GITHUB_CLIENT_ID=
GITHUB_CLIENT_SECRET=
GITHUB_REDIRECT_URI=
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=
MICROSOFT_CLIENT_ID=
MICROSOFT_CLIENT_SECRET=
MICROSOFT_REDIRECT_URI=
TEXT;
}

function legacyCriticalDockerComposeDevContents(): string
{
    return <<<'YAML'
services:
  app:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
    working_dir: /var/www/html
    volumes:
      - ./:/var/www/html
    command: >
      sh -lc "if [ ! -f .env ] && [ -f .env.example ]; then cp .env.example .env; fi &&
      composer install &&
      php artisan key:generate --ansi --force &&
      php artisan optimize:clear &&
      php artisan migrate --force &&
      php artisan serve --host=0.0.0.0 --port=8000"
    ports:
      - "8000:8000"
    depends_on:
      - postgres
      - redis

  vite:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
    working_dir: /var/www/html
    volumes:
      - ./:/var/www/html
    command: >
      sh -lc "npm install &&
      npm run dev -- --host 0.0.0.0 --port 5173"
    ports:
      - "5173:5173"

  app-test:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
    working_dir: /var/www/html
    volumes:
      - ./:/var/www/html
    command: ["sleep", "infinity"]
    depends_on:
      - postgres
      - redis

  horizon:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
    working_dir: /var/www/html
    volumes:
      - ./:/var/www/html
    command: >
      sh -lc "if [ ! -f .env ] && [ -f .env.example ]; then cp .env.example .env; fi &&
      composer install &&
      php artisan key:generate --ansi --force &&
      php artisan horizon"
    depends_on:
      - postgres
      - redis

  postgres:
    image: postgres:17
    environment:
      POSTGRES_DB: core_panel
      POSTGRES_USER: core_panel
      POSTGRES_PASSWORD: core_panel
    ports:
      - "5432:5432"
    volumes:
      - postgres-data:/var/lib/postgresql/data

  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"
    volumes:
      - redis-data:/data

volumes:
  postgres-data:
  redis-data:
YAML;
}

function previousComposeBeforePackageEnvironmentMerge(string $contents): string
{
    $packageManagedKeys = [
        'SYSTEM_UPDATES_AUTOMATIC_GRACE_MINUTES',
        'SYSTEM_UPDATES_AUTOMATIC_INTERVAL',
        'SYSTEM_UPDATES_AUTOMATIC_MAINTENANCE_WINDOW_ENABLED',
        'SYSTEM_UPDATES_AUTOMATIC_MODE',
        'SYSTEM_UPDATES_AUTOMATIC_TIME',
        'SYSTEM_UPDATES_AUTOMATIC_WEEKDAY',
    ];
    $lines = preg_split('/\R/', $contents);

    expect($lines)->toBeArray();

    return implode(PHP_EOL, array_values(array_filter(
        $lines,
        static fn (string $line): bool => ! array_any(
            $packageManagedKeys,
            static fn (string $key): bool => preg_match('/^\s+'.preg_quote($key, '/').'\s*:/', $line) === 1,
        ),
    )));
}

function legacyCriticalOldRoutesConsoleContents(): string
{
    return <<<'PHP'
<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
PHP;
}

/**
 * @return list<string>
 */
function legacyCriticalRoutesWebContents(): array
{
    return [
        <<<'PHP'
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::redirect('/', config('core-panel.route_prefix', 'admin'));

$webRoutes = require __DIR__.'/web/routes.php';
$loadWebRouteFile = static function (string $file): void {
    require __DIR__.'/web/'.$file;
};
$shouldLoadPublicRoutes = ! file_exists(__DIR__.'/universal.php');
$corePanelRouteMiddleware = array_values(array_filter(
    (array) config('core-panel.middleware', ['web', 'auth']),
    static fn (string $middleware): bool => $middleware !== 'web',
));

if ($shouldLoadPublicRoutes) {
    foreach ($webRoutes['public'] as $publicRouteFile) {
        $loadWebRouteFile($publicRouteFile);
    }
}

Route::middleware([...$corePanelRouteMiddleware, 'core-panel.verified'])->group(function () use ($loadWebRouteFile, $webRoutes): void {
    foreach ($webRoutes['authenticated_without_permission'] as $authenticatedRouteFile) {
        $loadWebRouteFile($authenticatedRouteFile);
    }

    Route::middleware('check.permission')->group(function () use ($loadWebRouteFile, $webRoutes): void {
        foreach ($webRoutes['permission_protected'] as $permissionProtectedRouteFile) {
            $loadWebRouteFile($permissionProtectedRouteFile);
        }
    });
});
PHP,
        <<<'PHP'
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::redirect('/', config('core-panel.route_prefix', 'admin'));

$webRoutes = require __DIR__.'/web/routes.php';
$loadWebRouteFile = static function (string $file): void {
    require __DIR__.'/web/'.$file;
};
$corePanelRouteMiddleware = array_values(array_filter(
    (array) config('core-panel.middleware', ['web', 'auth']),
    static fn (string $middleware): bool => $middleware !== 'web',
));

foreach ($webRoutes['public'] as $publicRouteFile) {
    $loadWebRouteFile($publicRouteFile);
}

Route::middleware([...$corePanelRouteMiddleware, 'core-panel.verified'])->group(function () use ($loadWebRouteFile, $webRoutes): void {
    foreach ($webRoutes['authenticated_without_permission'] as $authenticatedRouteFile) {
        $loadWebRouteFile($authenticatedRouteFile);
    }

    Route::middleware('check.permission')->group(function () use ($loadWebRouteFile, $webRoutes): void {
        foreach ($webRoutes['permission_protected'] as $permissionProtectedRouteFile) {
            $loadWebRouteFile($permissionProtectedRouteFile);
        }
    });
});
PHP,
        <<<'PHP'
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', static function () {
    return redirect()->route(
        Auth::check() ? 'core-panel.dashboard' : 'auth.login',
    );
});
PHP,
    ];
}

function legacyCriticalDockerignoreContents(): string
{
    return <<<'TEXT'
.agents
.ai
.claude
.codex
.git
.github
.idea
.vscode
node_modules
vendor
storage/logs
storage/framework/cache/*
storage/framework/sessions/*
storage/framework/testing/*
storage/framework/views/*
bootstrap/cache/*.php
.env
AGENTS.md
boost.json
docker-compose.*
Dockerfile
TEXT;
}

function legacyCriticalL5SwaggerContents(): string
{
    $contents = (string) file_get_contents(__DIR__.'/../../stubs/config/l5-swagger.php');

    return str_replace(
        [
            "use CorePanel\\CorePanelServiceProvider;\n\n",
            "                    dirname((new ReflectionClass(CorePanelServiceProvider::class))->getFileName()).'/OpenApi',\n                    ...is_dir(base_path('app/OpenApi')) ? [base_path('app/OpenApi')] : [],",
        ],
        [
            '',
            "                    base_path('app/OpenApi'),",
        ],
        $contents,
    );
}

/**
 * @return list<string>
 */
function versionedUpdateScaffoldPaths(): array
{
    $contents = file_get_contents(__DIR__.'/../../src/Support/ScaffoldsCorePanelStubs.php');

    expect($contents)->toBeString();

    preg_match(
        '/private const VERSIONED_UPDATE_SCAFFOLDS = \[(.*?)\];/s',
        (string) $contents,
        $matches,
    );

    expect($matches[1] ?? null)->toBeString();

    preg_match_all("/'([^']+)'/", $matches[1], $paths);

    return $paths[1];
}

/**
 * @return list<string>
 */
function criticalVersionedUpdateScaffoldPaths(): array
{
    return [
        '.env.example',
        'bootstrap/app.php',
        'config/database.php',
        'config/l5-swagger.php',
        'config/services.php',
        'resources/js/components/AppIcon.vue',
        '.docker/bin/php-entrypoint.sh',
        '.docker/bin/prepare-local-environment.sh',
        '.docker/bin/start-dev-app.sh',
        '.docker/bin/start-dev-artisan.sh',
        '.docker/nginx/default.conf',
        '.docker/php/banner.sh',
        '.docker/php/entrypoint.sh',
        '.docker/php/opcache.ini',
        '.docker/php/php.ini',
        '.docker/php-fpm/zz-docker.conf',
        '.dockerignore',
        'Dockerfile',
        'docker-compose.dev.yml',
        'docker-compose.portainer.yml',
        'docker-compose.prod.yml',
        'docker-compose.registry.yml',
        'docker-compose.yml',
        'routes/web/platform.php',
        'routes/web.php',
        'routes/console.php',
        'updater/Dockerfile',
        'updater/go.mod',
        'updater/main.go',
        'vite.config.ts',
    ];
}

/**
 * @return list<string>
 */
function updatePreservedScaffoldPaths(): array
{
    return [
        '.docker/bin/php-entrypoint.sh',
        '.docker/php/banner.sh',
        '.docker/php/php.ini',
    ];
}

it('versions the managed update scaffolds that still require host copies', function (): void {
    expect(versionedUpdateScaffoldPaths())->toContain(
        '.env.example',
        'bootstrap/app.php',
        'config/l5-swagger.php',
        'config/database.php',
        'config/pwa.php',
        'config/trustedproxy.php',
        'docker-compose.portainer.yml',
        'updater/Dockerfile',
        'updater/go.mod',
        'updater/main.go',
        'public/logo.png',
        'public/manifest.json',
        'public/offline.html',
        'public/sw.js',
        'resources/css/app.css',
        'routes/console.php',
        'routes/web/platform.php',
        'routes/web.php',
    )->not->toContain(
        'bootstrap/providers.php',
    );
});

it('creates a missing Vite scaffold during updates and records it in the manifest', function (): void {
    $basePath = makePublishBasePath('missing-vite-scaffold');
    $target = $basePath.'/vite.config.ts';
    $expectedContents = (string) file_get_contents(__DIR__.'/../../stubs/vite.config.ts');

    mkdir($basePath, 0777, true);

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $manifest = json_decode(
        (string) file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );
    $manifestEntry = $manifest['files']['vite.config.ts'] ?? null;
    $expectedHash = hash('sha256', $expectedContents);

    expect(file_get_contents($target))->toBe($expectedContents)
        ->and($manifestEntry)->toBeArray()
        ->and($manifestEntry['source_hash'] ?? null)->toBe($expectedHash)
        ->and($manifestEntry['destination_hash'] ?? null)->toBe($expectedHash)
        ->and(glob($basePath.'/.core-panel-backups/*/vite.config.ts'))->toBe([]);
});

it('creates a missing Swagger config with package and optional host scan paths', function (): void {
    $basePath = makePublishBasePath('missing-swagger-config');
    $relativePath = 'config/l5-swagger.php';
    $target = $basePath.'/'.$relativePath;

    mkdir($basePath, 0777, true);

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $manifest = json_decode(
        (string) file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    expect(file_get_contents($target))
        ->toBe(file_get_contents(__DIR__.'/../../stubs/'.$relativePath))
        ->toContain("dirname((new ReflectionClass(CorePanelServiceProvider::class))->getFileName()).'/OpenApi'")
        ->toContain("...is_dir(base_path('app/OpenApi')) ? [base_path('app/OpenApi')] : []")
        ->and($manifest['files'][$relativePath] ?? null)->toBeArray()
        ->and(glob($basePath.'/.core-panel-backups/*/'.$relativePath))->toBe([]);
});

it('backs up and updates an untracked legacy Swagger config', function (): void {
    $basePath = makePublishBasePath('legacy-swagger-config');
    $relativePath = 'config/l5-swagger.php';
    $target = $basePath.'/'.$relativePath;

    mkdir(dirname($target), 0777, true);
    file_put_contents($target, legacyCriticalL5SwaggerContents());

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $manifest = json_decode(
        (string) file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    expect(file_get_contents($target))
        ->toBe(file_get_contents(__DIR__.'/../../stubs/'.$relativePath))
        ->toContain("dirname((new ReflectionClass(CorePanelServiceProvider::class))->getFileName()).'/OpenApi'")
        ->toContain("...is_dir(base_path('app/OpenApi')) ? [base_path('app/OpenApi')] : []")
        ->and(glob($basePath.'/.core-panel-backups/*/'.$relativePath))->not->toBeEmpty()
        ->and($manifest['files'][$relativePath] ?? null)->toBeArray();
});

it('preserves an existing customized untracked Vite scaffold during updates', function (): void {
    $basePath = makePublishBasePath('untracked-vite-scaffold');
    $target = $basePath.'/vite.config.ts';
    $hostContents = <<<'TYPESCRIPT'
import customPlugin from 'custom-vite-plugin'
import { defineConfig } from 'vite'

export default defineConfig({
    plugins: [customPlugin()],
    resolve: { alias: { '@host': '/custom/host/path' } },
})
TYPESCRIPT;

    mkdir($basePath, 0777, true);
    file_put_contents($target, $hostContents."\n");

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $backups = glob($basePath.'/.core-panel-backups/*/vite.config.ts');
    $manifest = json_decode(
        (string) file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    expect(file_get_contents($target))->toBe($hostContents."\n")
        ->and($backups)->toBe([])
        ->and($manifest['files']['vite.config.ts'] ?? null)->toBeNull();
});

it('backs up and updates a known legacy Vite scaffold without manifest tracking', function (): void {
    $basePath = makePublishBasePath('known-legacy-vite-scaffold');
    $target = $basePath.'/vite.config.ts';
    $expectedContents = (string) file_get_contents(__DIR__.'/../../stubs/vite.config.ts');
    $legacyContents = str_replace(
        ['? [wayfinder()]', 'import.meta.dirname'],
        ['? [wayfinder({ actions: false })]', '__dirname'],
        $expectedContents,
    );
    $legacyContents = str_replace(
        [
            <<<'TS'
    path.resolve(
        __dirname,
        'vendor/mapo-89/core-panel/resources/lang',
    ),
TS,
            <<<'TS'
                replacement: path.resolve(
                    __dirname,
                    'node_modules/primevue',
                ),
TS,
            <<<'TS'
                replacement:
                    path.resolve(__dirname, 'node_modules/primevue') +
                    '/$1',
TS,
            <<<'TS'
                replacement: path.resolve(
                    __dirname,
                    'node_modules/vue',
                ),
TS,
        ],
        [
            "    path.resolve(__dirname, 'vendor/mapo-89/core-panel/resources/lang'),",
            "                replacement: path.resolve(__dirname, 'node_modules/primevue'),",
            <<<'TS'
                replacement:
                    path.resolve(__dirname, 'node_modules/primevue') + '/$1',
TS,
            "                replacement: path.resolve(__dirname, 'node_modules/vue'),",
        ],
        $legacyContents,
    );

    mkdir($basePath, 0777, true);
    file_put_contents($target, $legacyContents);

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $manifest = json_decode(
        (string) file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );
    $manifestEntry = $manifest['files']['vite.config.ts'] ?? null;
    $backups = glob($basePath.'/.core-panel-backups/*/vite.config.ts');
    $expectedHash = hash('sha256', $expectedContents);

    expect(hash('sha256', $legacyContents))->toBe('125a89df0aeabdd154fff978afb2c64005750ceb8792cb600513e11f3e38fdaf')
        ->and(file_get_contents($target))->toBe($expectedContents)
        ->and($backups)->not->toBeFalse()
        ->and($backups)->toHaveCount(1)
        ->and(file_get_contents($backups[0]))->toBe($legacyContents)
        ->and($manifestEntry)->toBeArray()
        ->and($manifestEntry['source_hash'] ?? null)->toBe($expectedHash)
        ->and($manifestEntry['destination_hash'] ?? null)->toBe($expectedHash);
});

it('keeps vendor-first administration pages absent during update for existing applications', function (): void {
    $basePath = makePublishBasePath('administration-missing-scaffold');

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    expect(file_exists($basePath.'/resources/js/pages/Admin/Administration/Index.vue'))->toBeFalse()
        ->and(file_exists($basePath.'/resources/js/pages/Admin/Administration/components/DatabaseBackupRestoreDialog.vue'))->toBeFalse()
        ->and(file_exists($basePath.'/resources/js/pages/Admin/Administration/components/DatabaseBackupSettingsDialog.vue'))->toBeFalse()
        ->and(file_exists($basePath.'/resources/js/pages/Admin/Administration/components/HorizonTab.vue'))->toBeFalse()
        ->and(file_exists($basePath.'/routes/web/admin/administration.php'))->toBeFalse()
        ->and(file_exists($basePath.'/routes/console.php'))->toBeTrue();
});

it('keeps missing administration route scaffolds vendor-first during updates', function (): void {
    $basePath = makePublishBasePath('administration-untracked-scaffold');

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $manifestContents = file_exists($basePath.'/storage/app/core-panel/scaffolds.json')
        ? (string) file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json')
        : '';

    expect(file_exists($basePath.'/routes/web/admin/administration.php'))->toBeFalse()
        ->and($manifestContents)->not->toContain('routes/web/admin/administration.php');
});

it('publishes a single config tag', function (): void {
    $basePath = makePublishBasePath('config');

    $this->artisan('core-panel:publish', [
        '--tag' => 'config',
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    expect(file_exists($basePath.'/config/core-panel.php'))->toBeTrue()
        ->and(file_exists($basePath.'/config/core-panel-access.php'))->toBeTrue()
        ->and(readManifest($basePath))->toContain('core-panel-config');
});

it('publishes the theme tag', function (): void {
    $basePath = makePublishBasePath('theme');

    $this->artisan('core-panel:publish', [
        '--tag' => 'theme',
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    expect(is_dir($basePath.'/resources/js/theme/core-panel'))->toBeTrue()
        ->and(file_exists($basePath.'/resources/js/theme/core-panel/tokens.ts'))->toBeTrue()
        ->and(readManifest($basePath))->toContain('core-panel-theme');
});

it('does not change files or manifest on update dry-run', function (): void {
    $basePath = makePublishBasePath('dry-run');

    $this->artisan('core-panel:publish', [
        '--tag' => 'components',
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $target = $basePath.'/resources/js/components/FormBuilder/FormRenderer.vue';
    $beforeFile = file_get_contents($target);
    $beforeManifest = readManifest($basePath);

    $this->artisan('core-panel:update', [
        '--dry-run' => true,
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    expect(file_get_contents($target))->toBe($beforeFile)
        ->and(readManifest($basePath))->toBe($beforeManifest);
});

it('does not create published snapshots during update dry-runs for legacy manifest entries', function (): void {
    $basePath = makePublishBasePath('dry-run-legacy-snapshot');
    $relativePath = 'resources/js/components/FormBuilder/FormRenderer.vue';
    $target = $basePath.'/'.$relativePath;
    $source = __DIR__.'/../../resources/js/components/FormBuilder/FormRenderer.vue';
    $contents = (string) file_get_contents($source);
    $hash = md5($contents);
    $manifestPath = $basePath.'/storage/app/core-panel/published.json';

    mkdir(dirname($target), 0777, true);
    mkdir(dirname($manifestPath), 0777, true);

    file_put_contents($target, $contents);
    file_put_contents($manifestPath, json_encode([
        'files' => [
            $target => [
                'tag' => 'core-panel-components',
                'source' => $source,
                'source_hash' => $hash,
                'destination_hash' => $hash,
                'published_at' => now()->subDay()->toAtomString(),
            ],
        ],
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n");

    $beforeManifest = readManifest($basePath);

    $this->artisan('core-panel:update', [
        '--dry-run' => true,
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    expect(readManifest($basePath))->toBe($beforeManifest)
        ->and(file_exists($basePath.'/storage/app/core-panel/published'))->toBeFalse();
});

it('reports but does not fail routine update dry-runs when published frontend overrides are kept', function (): void {
    $basePath = makePublishBasePath('conflict');

    $this->artisan('core-panel:publish', [
        '--tag' => 'components',
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $target = $basePath.'/resources/js/components/FormBuilder/FormRenderer.vue';
    file_put_contents($target, (string) file_get_contents($target)."\n// local change\n");

    $this->artisan('core-panel:update', [
        '--dry-run' => true,
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    expect(file_exists($target))->toBeTrue()
        ->and(readManifest($basePath))->toContain('core-panel-components');
});

it('creates a backup before force updates remove local published frontend overrides', function (): void {
    $basePath = makePublishBasePath('force');

    $this->artisan('core-panel:publish', [
        '--tag' => 'components',
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $target = $basePath.'/resources/js/components/FormBuilder/FormRenderer.vue';
    file_put_contents($target, (string) file_get_contents($target)."\n// local change\n");

    $this->artisan('core-panel:update', [
        '--force' => true,
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $backups = glob($basePath.'/.core-panel-backups/*/resources/js/components/FormBuilder/FormRenderer.vue');

    expect($backups)->not->toBeFalse()
        ->and($backups)->not->toBeEmpty()
        ->and(file_exists($target))->toBeFalse();
});

it('migrates unchanged published frontend overlays back to vendor assets by default', function (): void {
    $basePath = makePublishBasePath('vendor-first-clean');

    $this->artisan('core-panel:publish', [
        '--tag' => 'components',
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $this->artisan('core-panel:publish', [
        '--tag' => 'theme',
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    expect(is_dir($basePath.'/resources/js/components/FormBuilder'))->toBeFalse()
        ->and(is_dir($basePath.'/resources/js/theme/core-panel'))->toBeFalse()
        ->and(readManifest($basePath))->not->toContain('core-panel-components')
        ->and(readManifest($basePath))->not->toContain('core-panel-theme');
});

it('keeps locally modified published frontend overlays unless vendor-first is forced', function (): void {
    $basePath = makePublishBasePath('vendor-first-conflict');

    $this->artisan('core-panel:publish', [
        '--tag' => 'components',
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $target = $basePath.'/resources/js/components/FormBuilder/FormRenderer.vue';
    file_put_contents($target, (string) file_get_contents($target)."\n<!-- local override -->\n");

    $this->artisan('core-panel:update', [
        '--vendor-first' => true,
        '--dry-run' => true,
        '--base-path' => $basePath,
    ])->assertExitCode(1);

    expect(file_exists($target))->toBeTrue()
        ->and(readManifest($basePath))->toContain('core-panel-components');
});

it('does not fail routine updates when locally modified published frontend overlays are intentionally kept', function (): void {
    $basePath = makePublishBasePath('vendor-first-routine-keep-published');

    $this->artisan('core-panel:publish', [
        '--tag' => 'components',
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $target = $basePath.'/resources/js/components/FormBuilder/FormRenderer.vue';
    $customizedContents = (string) file_get_contents($target)."\n<!-- local override -->\n";
    file_put_contents($target, $customizedContents);

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    expect(file_exists($target))->toBeTrue()
        ->and(file_get_contents($target))->toBe($customizedContents)
        ->and(readManifest($basePath))->toContain('core-panel-components');
});

it('accepts the vendor-first flag as an alias for the default frontend migration', function (): void {
    $basePath = makePublishBasePath('vendor-first-flag-alias');

    $this->artisan('core-panel:publish', [
        '--tag' => 'components',
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $this->artisan('core-panel:update', [
        '--vendor-first' => true,
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    expect(is_dir($basePath.'/resources/js/components/FormBuilder'))->toBeFalse()
        ->and(readManifest($basePath))->not->toContain('core-panel-components');
});

it('supports dedicated vendor-first cleanup without running the full update workflow', function (): void {
    $basePath = makePublishBasePath('vendor-first-command-cleanup');

    $this->artisan('core-panel:publish', [
        '--tag' => 'components',
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $this->artisan('core-panel:publish', [
        '--tag' => 'theme',
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $this->artisan('core-panel:vendor-first', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    expect(is_dir($basePath.'/resources/js/components/FormBuilder'))->toBeFalse()
        ->and(is_dir($basePath.'/resources/js/theme/core-panel'))->toBeFalse()
        ->and(file_exists($basePath.'/routes/console.php'))->toBeFalse()
        ->and(readManifest($basePath))->not->toContain('core-panel-components')
        ->and(readManifest($basePath))->not->toContain('core-panel-theme');
});

it('supports dedicated vendor-first cleanup for scaffold-managed frontend overlays', function (): void {
    $basePath = makePublishBasePath('vendor-first-command-scaffold');
    $jsonRelativePath = 'lang/de.json';
    $componentRelativePath = 'resources/js/components/FormBuilder/FormRenderer.vue';
    $cssRelativePath = 'resources/css/theme/_auth.css';
    $pageRelativePath = 'resources/js/pages/Admin/Dashboard/Index.vue';
    $themeRelativePath = 'resources/js/theme/core-panel/tokens.ts';
    $viewRelativePath = 'resources/views/app.blade.php';

    $jsonContents = <<<'JSON'
{
    "Reset Password": "Passwort zurücksetzen"
}
JSON;
    $componentContents = (string) file_get_contents(__DIR__.'/../../resources/js/components/FormBuilder/FormRenderer.vue');
    $cssContents = (string) file_get_contents(__DIR__.'/../../resources/css/theme/_auth.css');
    $pageContents = (string) file_get_contents(__DIR__.'/../../resources/js/pages/Admin/Dashboard/Index.vue');
    $themeContents = (string) file_get_contents(__DIR__.'/../../resources/js/theme/core-panel/tokens.ts');
    $viewContents = (string) file_get_contents(__DIR__.'/../../resources/views/app.blade.php');

    foreach ([
        $jsonRelativePath => $jsonContents,
        $componentRelativePath => $componentContents,
        $cssRelativePath => $cssContents,
        $pageRelativePath => $pageContents,
        $themeRelativePath => $themeContents,
        $viewRelativePath => $viewContents,
    ] as $relativePath => $contents) {
        $target = $basePath.'/'.$relativePath;

        mkdir(dirname($target), 0777, true);
        file_put_contents($target, $contents);
    }

    seedScaffoldManifestFiles($basePath, [
        $jsonRelativePath => $jsonContents,
        $componentRelativePath => $componentContents,
        $cssRelativePath => $cssContents,
        $pageRelativePath => $pageContents,
        $themeRelativePath => $themeContents,
        $viewRelativePath => $viewContents,
    ]);

    $this->artisan('core-panel:vendor-first', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    expect(file_exists($basePath.'/'.$jsonRelativePath))->toBeFalse()
        ->and(file_exists($basePath.'/'.$componentRelativePath))->toBeFalse()
        ->and(file_exists($basePath.'/'.$cssRelativePath))->toBeFalse()
        ->and(file_exists($basePath.'/'.$pageRelativePath))->toBeFalse()
        ->and(file_exists($basePath.'/'.$themeRelativePath))->toBeFalse()
        ->and(file_exists($basePath.'/'.$viewRelativePath))->toBeFalse()
        ->and((string) file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json'))->not->toContain($jsonRelativePath)
        ->and((string) file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json'))->not->toContain($componentRelativePath)
        ->and((string) file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json'))->not->toContain($cssRelativePath)
        ->and((string) file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json'))->not->toContain($pageRelativePath)
        ->and((string) file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json'))->not->toContain($themeRelativePath)
        ->and((string) file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json'))->not->toContain($viewRelativePath)
        ->and(file_exists($basePath.'/routes/console.php'))->toBeFalse();
});

it('backs up locally modified published frontend overlays before migrating them back to vendor assets', function (): void {
    $basePath = makePublishBasePath('vendor-first-force');

    $this->artisan('core-panel:publish', [
        '--tag' => 'components',
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $target = $basePath.'/resources/js/components/FormBuilder/FormRenderer.vue';
    file_put_contents($target, (string) file_get_contents($target)."\n<!-- local override -->\n");

    $this->artisan('core-panel:update', [
        '--vendor-first' => true,
        '--force' => true,
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $backups = glob($basePath.'/.core-panel-backups/*/resources/js/components/FormBuilder/FormRenderer.vue');

    expect(file_exists($target))->toBeFalse()
        ->and($backups)->not->toBeFalse()
        ->and($backups)->not->toBeEmpty()
        ->and(readManifest($basePath))->not->toContain('core-panel-components');
});

it('migrates unchanged scaffold-managed frontend overlays back to vendor assets', function (): void {
    $basePath = makePublishBasePath('vendor-first-scaffold-clean');
    $componentRelativePath = 'resources/js/components/FormBuilder/FormRenderer.vue';
    $layoutRelativePath = 'resources/js/layouts/AppLayout.vue';
    $pageRelativePath = 'resources/js/pages/Admin/Dashboard/Index.vue';
    $themeRelativePath = 'resources/js/theme/core-panel/tokens.ts';

    $componentContents = (string) file_get_contents(__DIR__.'/../../resources/js/components/FormBuilder/FormRenderer.vue');
    $layoutContents = (string) file_get_contents(__DIR__.'/../../resources/js/layouts/AppLayout.vue');
    $pageContents = (string) file_get_contents(__DIR__.'/../../resources/js/pages/Admin/Dashboard/Index.vue');
    $themeContents = (string) file_get_contents(__DIR__.'/../../resources/js/theme/core-panel/tokens.ts');

    foreach ([
        $componentRelativePath => $componentContents,
        $layoutRelativePath => $layoutContents,
        $pageRelativePath => $pageContents,
        $themeRelativePath => $themeContents,
    ] as $relativePath => $contents) {
        $target = $basePath.'/'.$relativePath;

        mkdir(dirname($target), 0777, true);
        file_put_contents($target, $contents);
    }

    seedScaffoldManifestFiles($basePath, [
        $componentRelativePath => $componentContents,
        $layoutRelativePath => $layoutContents,
        $pageRelativePath => $pageContents,
        $themeRelativePath => $themeContents,
    ]);

    $this->artisan('core-panel:update', [
        '--vendor-first' => true,
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    expect(file_exists($basePath.'/'.$componentRelativePath))->toBeFalse()
        ->and(file_exists($basePath.'/'.$layoutRelativePath))->toBeFalse()
        ->and(file_exists($basePath.'/'.$pageRelativePath))->toBeFalse()
        ->and(file_exists($basePath.'/'.$themeRelativePath))->toBeFalse()
        ->and((string) file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json'))->not->toContain($componentRelativePath)
        ->and((string) file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json'))->not->toContain($layoutRelativePath)
        ->and((string) file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json'))->not->toContain($pageRelativePath)
        ->and((string) file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json'))->not->toContain($themeRelativePath);
});

it('migrates unchanged scaffold-managed frontend overlays from legacy flat scaffold manifests', function (): void {
    $basePath = makePublishBasePath('vendor-first-scaffold-flat-manifest');
    $relativePath = 'resources/js/components/FormBuilder/FormRenderer.vue';
    $contents = (string) file_get_contents(__DIR__.'/../../resources/js/components/FormBuilder/FormRenderer.vue');
    $target = $basePath.'/'.$relativePath;
    $sourceHash = hash('sha256', $contents);
    $snapshotPath = 'storage/app/core-panel/scaffolds/'.$sourceHash;

    mkdir(dirname($target), 0777, true);
    mkdir(dirname($basePath.'/'.$snapshotPath), 0777, true);

    file_put_contents($target, $contents);
    file_put_contents($basePath.'/'.$snapshotPath, $contents);
    file_put_contents($basePath.'/storage/app/core-panel/scaffolds.json', json_encode([
        '_meta' => [
            'package_version' => '1.0.0',
        ],
        $relativePath => [
            'destination_hash' => $sourceHash,
            'package_version' => '1.0.0',
            'snapshot' => $snapshotPath,
            'source_hash' => $sourceHash,
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

    $this->artisan('core-panel:update', [
        '--vendor-first' => true,
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    expect(file_exists($target))->toBeFalse()
        ->and((string) file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json'))->not->toContain($relativePath);
});

it('migrates stale scaffold-managed frontend overlays before publish updates can report vendor-first conflicts', function (): void {
    $basePath = makePublishBasePath('vendor-first-scaffold-before-publish');
    $relativePath = 'resources/js/components/FormBuilder/FormRenderer.vue';
    $target = $basePath.'/'.$relativePath;
    $legacyContents = "<template>\n    <div>legacy scaffolded renderer</div>\n</template>\n";

    mkdir(dirname($target), 0777, true);
    file_put_contents($target, $legacyContents);
    seedScaffoldManifest($basePath, $relativePath, $legacyContents);

    $this->artisan('core-panel:update', [
        '--vendor-first' => true,
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    expect(file_exists($target))->toBeFalse()
        ->and((string) file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json'))->not->toContain($relativePath)
        ->and(readManifest($basePath))->not->toContain('core-panel-components');
});

it('keeps locally modified scaffold-managed frontend overlays unless vendor-first is forced', function (): void {
    $basePath = makePublishBasePath('vendor-first-scaffold-conflict');
    $relativePath = 'resources/js/layouts/AppLayout.vue';
    $target = $basePath.'/'.$relativePath;
    $contents = (string) file_get_contents(__DIR__.'/../../resources/js/layouts/AppLayout.vue');

    mkdir(dirname($target), 0777, true);
    file_put_contents($target, $contents."\n<!-- local scaffold override -->\n");
    seedScaffoldManifest($basePath, $relativePath, $contents);

    $this->artisan('core-panel:update', [
        '--vendor-first' => true,
        '--dry-run' => true,
        '--base-path' => $basePath,
    ])->assertExitCode(1);

    expect(file_exists($target))->toBeTrue()
        ->and((string) file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json'))->toContain($relativePath);
});

it('does not fail routine updates when locally modified scaffold-managed frontend overlays are intentionally kept', function (): void {
    $basePath = makePublishBasePath('vendor-first-routine-keep-scaffold');
    $relativePath = 'resources/js/layouts/AppLayout.vue';
    $target = $basePath.'/'.$relativePath;
    $contents = (string) file_get_contents(__DIR__.'/../../resources/js/layouts/AppLayout.vue');
    $customizedContents = $contents."\n<!-- local scaffold override -->\n";

    mkdir(dirname($target), 0777, true);
    file_put_contents($target, $customizedContents);
    seedScaffoldManifest($basePath, $relativePath, $contents);

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    expect(file_exists($target))->toBeTrue()
        ->and(file_get_contents($target))->toBe($customizedContents)
        ->and((string) file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json'))->toContain($relativePath);
});

it('preserves local theme imports in app css when scaffold-managed theme files are kept on migration conflict', function (): void {
    $basePath = makePublishBasePath('vendor-first-routine-keep-theme-imports');
    $themeRelativePath = 'resources/css/theme/_auth.css';
    $themeTarget = $basePath.'/'.$themeRelativePath;
    $sourceThemeContents = (string) file_get_contents(__DIR__.'/../../resources/css/theme/_auth.css');
    $customizedThemeContents = $sourceThemeContents."\n.local-auth-override { color: red; }\n";

    mkdir(dirname($themeTarget), 0777, true);
    file_put_contents($basePath.'/resources/css/app.css', '/* legacy host app css */'."\n");
    file_put_contents($themeTarget, $customizedThemeContents);
    seedScaffoldManifest($basePath, $themeRelativePath, $sourceThemeContents);

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $appCss = (string) file_get_contents($basePath.'/resources/css/app.css');

    expect($appCss)->toContain("@import '@core-panel/theme/core-panel/index.css';")
        ->and($appCss)->toContain("@import './theme/_auth.css';")
        ->and(file_get_contents($themeTarget))->toBe($customizedThemeContents)
        ->and((string) file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json'))->toContain($themeRelativePath);
});

it('backs up locally modified scaffold-managed frontend overlays before migrating them back to vendor assets', function (): void {
    $basePath = makePublishBasePath('vendor-first-scaffold-force');
    $relativePath = 'resources/js/composables/useMenuBuilder.ts';
    $target = $basePath.'/'.$relativePath;
    $contents = (string) file_get_contents(__DIR__.'/../../resources/js/composables/useMenuBuilder.ts');

    mkdir(dirname($target), 0777, true);
    file_put_contents($target, $contents."\n// local scaffold override\n");
    seedScaffoldManifest($basePath, $relativePath, $contents);

    $this->artisan('core-panel:update', [
        '--vendor-first' => true,
        '--force' => true,
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $backups = glob($basePath.'/.core-panel-backups/*/'.$relativePath);

    expect(file_exists($target))->toBeFalse()
        ->and($backups)->not->toBeFalse()
        ->and($backups)->not->toBeEmpty()
        ->and((string) file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json'))->not->toContain($relativePath);
});

it('leaves unmanaged legacy frontend overlays untouched during force updates', function (): void {
    $basePath = makePublishBasePath('force-adopt-unmanaged');
    $target = $basePath.'/resources/js/components/FormBuilder/FormRenderer.vue';

    mkdir(dirname($target), 0777, true);
    file_put_contents($target, "<template>\n    <div>legacy local component</div>\n</template>\n");

    $this->artisan('core-panel:update', [
        '--dry-run' => true,
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    expect(file_exists($basePath.'/storage/app/core-panel/published.json'))->toBeFalse();

    $this->artisan('core-panel:update', [
        '--force' => true,
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $backups = glob($basePath.'/.core-panel-backups/*/resources/js/components/FormBuilder/FormRenderer.vue');

    expect($backups)->toBeEmpty()
        ->and(file_get_contents($target))->toContain('legacy local component')
        ->and(readManifest($basePath))->not->toContain('core-panel-components');
});

it('does not overwrite application scaffolds during force updates', function (): void {
    $basePath = makePublishBasePath('force-scaffold-preserve');
    $target = $basePath.'/resources/js/pages/Admin/Settings/Index.vue';

    mkdir(dirname($target), 0777, true);
    file_put_contents($target, "<script setup lang=\"ts\">\nconst customized = true\n</script>\n");

    $this->artisan('core-panel:update', [
        '--force' => true,
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    expect(file_get_contents($target))->toContain('const customized = true');
});

it('updates clean manifest-managed application scaffolds during updates', function (): void {
    $basePath = makePublishBasePath('managed-scaffold-update');
    $target = $basePath.'/routes/console.php';

    $legacyContents = <<<'PHP'
<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
PHP;

    mkdir(dirname($target), 0777, true);
    file_put_contents($target, $legacyContents);
    seedScaffoldManifest($basePath, 'routes/console.php', $legacyContents);

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    expect(file_get_contents($target))->toBe((string) file_get_contents(__DIR__.'/../../stubs/routes/console.php'))
        ->not->toContain('Schedule::');
});

it('does not merge untracked existing package json during updates', function (): void {
    $basePath = makePublishBasePath('untracked-package-json-update');
    $target = $basePath.'/package.json';
    $makefileTarget = $basePath.'/Makefile';
    $hostPackage = [
        'name' => 'host-app',
        'private' => true,
        'scripts' => [
            'custom' => 'echo host',
        ],
        'dependencies' => [
            'axios' => '^1.7.0',
        ],
        'devDependencies' => [
            'sass' => '^1.93.2',
            'vitest' => '^3.0.0',
        ],
    ];

    mkdir($basePath, 0777, true);
    file_put_contents($target, json_encode($hostPackage, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

    $this->artisan('core-panel:update', [
        '--force' => true,
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $packageJson = json_decode((string) file_get_contents($target), true, 512, JSON_THROW_ON_ERROR);

    expect($packageJson)->toBe($hostPackage)
        ->and(file_get_contents($makefileTarget))
        ->toContain('npm exec vue-tsc -- --noEmit --incremental --tsBuildInfoFile node_modules/.vue-tsc.tsbuildinfo -p tsconfig.json')
        ->not->toContain('npm run typecheck:incremental')
        ->and(glob($basePath.'/.core-panel-backups/*/package.json'))->toBe([]);
});

it('merges manifest-managed existing package json during updates', function (): void {
    $basePath = makePublishBasePath('managed-package-json-update');
    $target = $basePath.'/package.json';
    $hostPackage = [
        'name' => 'host-app',
        'private' => true,
        'scripts' => [
            'custom' => 'echo host',
        ],
        'dependencies' => [
            'axios' => '^1.7.0',
        ],
        'devDependencies' => [
            'sass' => '^1.93.2',
            'vitest' => '^3.0.0',
        ],
    ];
    $hostContents = json_encode($hostPackage, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;

    mkdir($basePath, 0777, true);
    file_put_contents($target, $hostContents);
    seedScaffoldManifest($basePath, 'package.json', $hostContents);

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $packageJson = json_decode((string) file_get_contents($target), true, 512, JSON_THROW_ON_ERROR);

    expect($packageJson)->toBeArray()
        ->and($packageJson['name'])->toBe('host-app')
        ->and($packageJson['scripts'])->toHaveKey('custom')
        ->and($packageJson['scripts'])->toHaveKey('build')
        ->and($packageJson['scripts'])->toHaveKey('typecheck:incremental')
        ->and($packageJson['dependencies'])->toHaveKey('axios')
        ->and($packageJson['dependencies'])->toHaveKey('vue')
        ->and($packageJson['devDependencies'])->toHaveKey('vitest')
        ->and($packageJson['devDependencies'])->toHaveKey('@vitejs/plugin-vue')
        ->and($packageJson['devDependencies'])->not->toHaveKey('sass')
        ->and(glob($basePath.'/.core-panel-backups/*/package.json'))->not->toBe([]);
});

it('creates missing versioned application scaffolds during updates without a previous version baseline', function (): void {
    $basePath = makePublishBasePath('missing-scaffold-no-baseline');
    $target = $basePath.'/routes/console.php';

    mkdir($basePath, 0777, true);

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    expect(file_exists($target))->toBeTrue()
        ->and(file_get_contents($target))->toContain("Artisan::command('inspire'")
        ->and(file_get_contents($target))->not->toContain('Schedule::');
});

it('creates missing unified application image scaffolds and records their baselines', function (): void {
    $basePath = makePublishBasePath('missing-unified-image-scaffolds');
    $relativePaths = [
        '.docker/nginx/default.conf',
        '.docker/php/entrypoint.sh',
        'Dockerfile',
        'docker-compose.dev.yml',
        'docker-compose.portainer.yml',
        'docker-compose.prod.yml',
        'docker-compose.registry.yml',
        'docker-compose.yml',
    ];

    mkdir($basePath, 0777, true);

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $manifest = json_decode(
        (string) file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    foreach ($relativePaths as $relativePath) {
        expect(file_get_contents($basePath.'/'.$relativePath))
            ->toBe(file_get_contents(__DIR__.'/../../stubs/'.$relativePath))
            ->and($manifest['files'][$relativePath] ?? null)->toBeArray()
            ->and(glob($basePath.'/.core-panel-backups/*/'.$relativePath))->toBe([]);
    }
});

it('updates managed unified application image scaffolds with backups and new manifest entries', function (): void {
    $basePath = makePublishBasePath('managed-unified-image-scaffolds');
    $legacyFiles = [
        'Dockerfile' => "FROM php:8.5-fpm-bookworm AS php-prod\n",
        'docker-compose.registry.yml' => "services:\n  app:\n    image: \${PHP_IMAGE}\n  nginx:\n    image: \${NGINX_IMAGE}\n",
    ];

    seedScaffoldManifestFiles($basePath, $legacyFiles);
    file_put_contents($basePath.'/.env', "APP_IMAGE=registry.example/core-panel/app:latest\n");

    foreach ($legacyFiles as $relativePath => $contents) {
        file_put_contents($basePath.'/'.$relativePath, $contents);
    }

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $manifest = json_decode(
        (string) file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    foreach ($legacyFiles as $relativePath => $legacyContents) {
        $expectedContents = (string) file_get_contents(__DIR__.'/../../stubs/'.$relativePath);
        $backups = glob($basePath.'/.core-panel-backups/*/'.$relativePath);

        expect(file_get_contents($basePath.'/'.$relativePath))->toBe($expectedContents)
            ->and($backups)->not->toBeEmpty()
            ->and(file_get_contents($backups[0]))->toBe($legacyContents)
            ->and($manifest['files'][$relativePath]['destination_hash'] ?? null)
            ->toBe(hash('sha256', $expectedContents));
    }
});

it('preserves operator customizations across all managed runtime migration scaffolds', function (): void {
    $basePath = makePublishBasePath('customized-managed-runtime-migration-scaffolds');
    $releaseFixturePath = __DIR__.'/../Fixtures/scaffolds/release-1.5.0';
    $managedFiles = [
        '.docker/nginx/default.conf' => (string) file_get_contents(__DIR__.'/../../stubs/.docker/nginx/default.conf'),
        '.docker/php/entrypoint.sh' => (string) file_get_contents($releaseFixturePath.'/.docker/php/entrypoint.sh'),
        'Dockerfile' => (string) file_get_contents($releaseFixturePath.'/Dockerfile'),
        'docker-compose.dev.yml' => (string) file_get_contents($releaseFixturePath.'/docker-compose.dev.yml'),
        'docker-compose.portainer.yml' => (string) file_get_contents($releaseFixturePath.'/docker-compose.portainer.yml'),
        'docker-compose.prod.yml' => (string) file_get_contents($releaseFixturePath.'/docker-compose.prod.yml'),
        'docker-compose.registry.yml' => (string) file_get_contents($releaseFixturePath.'/docker-compose.registry.yml'),
        'docker-compose.yml' => (string) file_get_contents($releaseFixturePath.'/docker-compose.yml'),
    ];

    seedScaffoldManifestFiles($basePath, $managedFiles);

    foreach ($managedFiles as $relativePath => $managedContents) {
        $target = $basePath.'/'.$relativePath;

        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0777, true);
        }

        $customizedContents = $managedContents."\n# operator customization\n";
        file_put_contents($target, $customizedContents);
        $managedFiles[$relativePath] = $customizedContents;
    }

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $manifest = json_decode(
        (string) file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    foreach ($managedFiles as $relativePath => $customizedContents) {
        $installedContents = (string) file_get_contents($basePath.'/'.$relativePath);

        expect($installedContents)->toContain('# operator customization')
            ->and($installedContents)->not->toBe((string) file_get_contents(__DIR__.'/../../stubs/'.$relativePath));

        if (! in_array($relativePath, ['docker-compose.prod.yml', 'docker-compose.portainer.yml'], true)) {
            expect($installedContents)->toBe($customizedContents)
                ->and(glob($basePath.'/.core-panel-backups/*/'.$relativePath))->toBe([])
                ->and($manifest['files'][$relativePath]['destination_hash'] ?? null)
                ->not->toBe(hash('sha256', $customizedContents));
        }
    }
});

it('preserves the complete runtime topology when only one managed scaffold is customized', function (): void {
    $basePath = makePublishBasePath('partially-customized-managed-runtime-migration-scaffolds');
    $releaseFixturePath = __DIR__.'/../Fixtures/scaffolds/release-1.5.0';
    $managedFiles = [
        '.docker/nginx/default.conf' => (string) file_get_contents(__DIR__.'/../../stubs/.docker/nginx/default.conf'),
        '.docker/php/entrypoint.sh' => (string) file_get_contents($releaseFixturePath.'/.docker/php/entrypoint.sh'),
        'Dockerfile' => (string) file_get_contents($releaseFixturePath.'/Dockerfile'),
        'docker-compose.dev.yml' => (string) file_get_contents($releaseFixturePath.'/docker-compose.dev.yml'),
        'docker-compose.portainer.yml' => (string) file_get_contents($releaseFixturePath.'/docker-compose.portainer.yml'),
        'docker-compose.prod.yml' => (string) file_get_contents($releaseFixturePath.'/docker-compose.prod.yml'),
        'docker-compose.registry.yml' => (string) file_get_contents($releaseFixturePath.'/docker-compose.registry.yml'),
        'docker-compose.yml' => (string) file_get_contents($releaseFixturePath.'/docker-compose.yml'),
    ];

    seedScaffoldManifestFiles($basePath, $managedFiles);
    file_put_contents($basePath.'/.env', "SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler,nginx\n");

    foreach ($managedFiles as $relativePath => $managedContents) {
        $target = $basePath.'/'.$relativePath;

        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0777, true);
        }

        file_put_contents(
            $target,
            $relativePath === 'docker-compose.prod.yml'
                ? $managedContents."\n# operator customization\n"
                : $managedContents,
        );
    }

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    foreach (array_diff(array_keys($managedFiles), ['docker-compose.prod.yml', 'docker-compose.portainer.yml']) as $relativePath) {
        expect(file_get_contents($basePath.'/'.$relativePath))->toBe($managedFiles[$relativePath]);
    }

    expect(file_get_contents($basePath.'/Dockerfile'))
        ->toContain('AS php-prod')
        ->not->toContain('AS app-prod')
        ->and(file_get_contents($basePath.'/docker-compose.registry.yml'))
        ->toContain('NGINX_IMAGE')
        ->and(file_get_contents($basePath.'/docker-compose.prod.yml'))
        ->toContain('# operator customization')
        ->toContain('target: nginx-prod')
        ->not->toBe((string) file_get_contents(__DIR__.'/../../stubs/docker-compose.prod.yml'))
        ->and(file_get_contents($basePath.'/docker-compose.portainer.yml'))
        ->toContain('NGINX_IMAGE')
        ->not->toBe((string) file_get_contents(__DIR__.'/../../stubs/docker-compose.portainer.yml'))
        ->and(file_get_contents($basePath.'/.env'))
        ->toContain("SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler,nginx\n")
        ->not->toContain("SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler\n");
});

it('uses the unified updater service list when customized retained scaffolds have no nginx service', function (): void {
    $relativePaths = [
        '.docker/nginx/default.conf',
        '.docker/php/entrypoint.sh',
        'Dockerfile',
        'docker-compose.dev.yml',
        'docker-compose.portainer.yml',
        'docker-compose.prod.yml',
        'docker-compose.registry.yml',
        'docker-compose.yml',
    ];
    $managedFiles = [];

    foreach ($relativePaths as $relativePath) {
        $managedFiles[$relativePath] = (string) file_get_contents(__DIR__.'/../../stubs/'.$relativePath);
    }

    foreach ([
        'legacy-value' => "SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler,nginx\n",
        'missing-value' => "APP_NAME=CorePanel\n",
    ] as $scenario => $environmentContents) {
        $basePath = makePublishBasePath('customized-unified-runtime-'.$scenario);

        seedScaffoldManifestFiles($basePath, $managedFiles);
        file_put_contents($basePath.'/.env', $environmentContents);

        foreach ($managedFiles as $relativePath => $managedContents) {
            $target = $basePath.'/'.$relativePath;

            if (! is_dir(dirname($target))) {
                mkdir(dirname($target), 0777, true);
            }

            file_put_contents(
                $target,
                $relativePath === 'Dockerfile'
                    ? $managedContents."\n# operator customization\n"
                    : $managedContents,
            );
        }

        $this->artisan('core-panel:update', [
            '--base-path' => $basePath,
        ])->assertExitCode(0);

        expect(file_get_contents($basePath.'/Dockerfile'))
            ->toContain('# operator customization')
            ->and(file_get_contents($basePath.'/.env'))
            ->toContain("SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler\n")
            ->not->toContain('SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler,nginx');
    }
});

it('stops a legacy registry scaffold migration until APP_IMAGE is configured', function (): void {
    $basePath = makePublishBasePath('legacy-registry-without-app-image');
    $releaseFixturePath = __DIR__.'/../Fixtures/scaffolds/release-1.5.0';
    $legacyFiles = [
        'Dockerfile' => (string) file_get_contents($releaseFixturePath.'/Dockerfile'),
        'docker-compose.registry.yml' => (string) file_get_contents($releaseFixturePath.'/docker-compose.registry.yml'),
    ];

    mkdir($basePath, 0777, true);
    file_put_contents($basePath.'/.env', implode(PHP_EOL, [
        'PHP_IMAGE=registry.example/core-panel/php:1.5.0',
        'NGINX_IMAGE=registry.example/core-panel/nginx:1.5.0',
        'SYSTEM_UPDATER_COMPOSE_FILES=docker-compose.prod.yml,docker-compose.registry.yml',
        '',
    ]));

    foreach ($legacyFiles as $relativePath => $contents) {
        file_put_contents($basePath.'/'.$relativePath, $contents);
    }

    $previousAppImage = getenv('APP_IMAGE');
    putenv('APP_IMAGE=registry.example/invoking-application/app:latest');

    try {
        $this->artisan('core-panel:update', [
            '--base-path' => $basePath,
        ])
            ->expectsOutputToContain('Cannot migrate the Docker runtime scaffolds because APP_IMAGE is not configured')
            ->assertExitCode(1);
    } finally {
        putenv($previousAppImage === false ? 'APP_IMAGE' : 'APP_IMAGE='.$previousAppImage);
    }

    foreach ($legacyFiles as $relativePath => $contents) {
        expect(file_get_contents($basePath.'/'.$relativePath))->toBe($contents)
            ->and(glob($basePath.'/.core-panel-backups/*/'.$relativePath))->toBe([]);
    }

    expect(file_get_contents($basePath.'/.env'))
        ->toContain('PHP_IMAGE=registry.example/core-panel/php:1.5.0')
        ->toContain('NGINX_IMAGE=registry.example/core-panel/nginx:1.5.0')
        ->not->toContain('APP_IMAGE=')
        ->and(file_exists($basePath.'/storage/app/core-panel/scaffolds.json'))->toBeFalse();
});

it('stops a legacy registry migration when the active overlay was renamed', function (): void {
    $basePath = makePublishBasePath('renamed-active-legacy-registry-overlay');
    $releaseFixturePath = __DIR__.'/../Fixtures/scaffolds/release-1.5.0';
    $legacyRegistryCompose = (string) file_get_contents($releaseFixturePath.'/docker-compose.registry.yml');
    $renamedOverlayPath = $basePath.'/deploy/registry.yml';

    mkdir(dirname($renamedOverlayPath), 0777, true);
    file_put_contents($basePath.'/.env', implode(PHP_EOL, [
        'SYSTEM_UPDATER_COMPOSE_FILES=docker-compose.prod.yml,deploy/registry.yml',
        'SYSTEM_UPDATER_COMPOSE_WORKDIR=/workspace',
        'SYSTEM_UPDATER_PROJECT_PATH='.$basePath,
        '',
    ]));
    file_put_contents($basePath.'/docker-compose.registry.yml', $legacyRegistryCompose);
    file_put_contents($renamedOverlayPath, <<<'YAML'
services:
  app:
    image: ${PHP_IMAGE}
  nginx:
    image: ${NGINX_IMAGE}
YAML);
    Process::fake(['*' => Process::result(output: '')]);

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])
        ->expectsOutputToContain('Cannot migrate the Docker runtime scaffolds because APP_IMAGE is not configured')
        ->assertExitCode(1);

    expect(file_get_contents($basePath.'/docker-compose.registry.yml'))
        ->toBe($legacyRegistryCompose)
        ->and(file_get_contents($renamedOverlayPath))
        ->toContain('${PHP_IMAGE}')
        ->toContain('${NGINX_IMAGE}')
        ->and(file_exists($basePath.'/storage/app/core-panel/scaffolds.json'))->toBeFalse();
});

it('stops a legacy registry migration when a renamed active overlay is not readable from the application', function (): void {
    $basePath = makePublishBasePath('unreadable-renamed-active-registry-overlay');
    $releaseFixturePath = __DIR__.'/../Fixtures/scaffolds/release-1.5.0';
    $legacyRegistryCompose = (string) file_get_contents($releaseFixturePath.'/docker-compose.registry.yml');

    mkdir($basePath, 0777, true);
    file_put_contents($basePath.'/.env', implode(PHP_EOL, [
        'SYSTEM_UPDATER_COMPOSE_FILES=docker-compose.prod.yml',
        'SYSTEM_UPDATER_COMPOSE_PROJECT_NAME=core-panel',
        '',
    ]));
    file_put_contents($basePath.'/docker-compose.registry.yml', $legacyRegistryCompose);
    fakePublishUpdaterComposeLabels('/unmounted/registry-stack', 'docker-compose.prod.yml,deploy/registry.yml');

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])
        ->expectsOutputToContain('Cannot migrate the Docker runtime scaffolds because APP_IMAGE is not configured')
        ->assertExitCode(1);

    expect(file_get_contents($basePath.'/docker-compose.registry.yml'))
        ->toBe($legacyRegistryCompose)
        ->and(file_exists($basePath.'/storage/app/core-panel/scaffolds.json'))->toBeFalse();
});

it('stops a legacy Portainer scaffold migration when image variables are configured outside the application environment', function (): void {
    $basePath = makePublishBasePath('legacy-portainer-with-external-image-variables');
    $releaseFixturePath = __DIR__.'/../Fixtures/scaffolds/release-1.5.0';
    $legacyFiles = [
        'Dockerfile' => (string) file_get_contents($releaseFixturePath.'/Dockerfile'),
        'docker-compose.portainer.yml' => (string) file_get_contents($releaseFixturePath.'/docker-compose.portainer.yml'),
    ];

    $previousAppImage = getenv('APP_IMAGE');
    $previousPhpImage = getenv('PHP_IMAGE');
    $previousNginxImage = getenv('NGINX_IMAGE');

    putenv('APP_IMAGE');
    putenv('PHP_IMAGE=registry.example/core-panel/php:1.5.0');
    putenv('NGINX_IMAGE=registry.example/core-panel/nginx:1.5.0');

    mkdir($basePath, 0777, true);
    $environmentContents = implode(PHP_EOL, [
        'APP_NAME=CorePanel',
        'SYSTEM_UPDATER_COMPOSE_FILES=docker-compose.prod.yml',
        'SYSTEM_UPDATER_COMPOSE_PROJECT_NAME=core-panel',
        '',
    ]);
    file_put_contents($basePath.'/.env', $environmentContents);
    fakePublishUpdaterComposeLabels('/unmounted/portainer-stack', 'docker-compose.portainer.yml');

    foreach ($legacyFiles as $relativePath => $contents) {
        file_put_contents($basePath.'/'.$relativePath, $contents);
    }

    try {
        $this->artisan('core-panel:update', [
            '--base-path' => $basePath,
        ])
            ->expectsOutputToContain('Cannot migrate the Docker runtime scaffolds because APP_IMAGE is not configured')
            ->assertExitCode(1);
    } finally {
        putenv($previousAppImage === false ? 'APP_IMAGE' : 'APP_IMAGE='.$previousAppImage);
        putenv($previousPhpImage === false ? 'PHP_IMAGE' : 'PHP_IMAGE='.$previousPhpImage);
        putenv($previousNginxImage === false ? 'NGINX_IMAGE' : 'NGINX_IMAGE='.$previousNginxImage);
    }

    foreach ($legacyFiles as $relativePath => $contents) {
        expect(file_get_contents($basePath.'/'.$relativePath))->toBe($contents)
            ->and(glob($basePath.'/.core-panel-backups/*/'.$relativePath))->toBe([]);
    }

    expect(file_get_contents($basePath.'/.env'))
        ->toBe($environmentContents)
        ->and(file_exists($basePath.'/storage/app/core-panel/scaffolds.json'))->toBeFalse();
});

it('migrates dormant registry scaffolds without requiring APP_IMAGE for a build-based deployment', function (): void {
    $basePath = makePublishBasePath('dormant-legacy-registry-without-app-image');
    $releaseFixturePath = __DIR__.'/../Fixtures/scaffolds/release-1.5.0';
    $legacyRegistryCompose = (string) file_get_contents($releaseFixturePath.'/docker-compose.registry.yml');

    mkdir($basePath, 0777, true);
    file_put_contents($basePath.'/.env', "SYSTEM_UPDATER_COMPOSE_FILES=docker-compose.prod.yml\n");
    file_put_contents($basePath.'/docker-compose.registry.yml', $legacyRegistryCompose);

    $previousComposeFiles = getenv('SYSTEM_UPDATER_COMPOSE_FILES');
    putenv('SYSTEM_UPDATER_COMPOSE_FILES=docker-compose.prod.yml,docker-compose.registry.yml');

    try {
        $this->artisan('core-panel:update', [
            '--base-path' => $basePath,
        ])->assertExitCode(0);
    } finally {
        putenv($previousComposeFiles === false
            ? 'SYSTEM_UPDATER_COMPOSE_FILES'
            : 'SYSTEM_UPDATER_COMPOSE_FILES='.$previousComposeFiles);
    }

    expect(file_get_contents($basePath.'/docker-compose.registry.yml'))
        ->toBe(file_get_contents(__DIR__.'/../../stubs/docker-compose.registry.yml'))
        ->and(file_get_contents($basePath.'/.env'))
        ->toContain("SYSTEM_UPDATER_COMPOSE_FILES=docker-compose.prod.yml\n")
        ->not->toContain('APP_IMAGE=registry.')
        ->and(glob($basePath.'/.core-panel-backups/*/docker-compose.registry.yml'))->not->toBeEmpty();
});

it('upgrades every exact 1.5.0 runtime scaffold without a manifest', function (): void {
    $basePath = makePublishBasePath('release-1-5-0-runtime-without-manifest');
    $releaseFixturePath = __DIR__.'/../Fixtures/scaffolds/release-1.5.0';
    $releasedFiles = [
        '.docker/php/entrypoint.sh' => (string) file_get_contents($releaseFixturePath.'/.docker/php/entrypoint.sh'),
        'Dockerfile' => (string) file_get_contents($releaseFixturePath.'/Dockerfile'),
        'docker-compose.dev.yml' => (string) file_get_contents($releaseFixturePath.'/docker-compose.dev.yml'),
        'docker-compose.portainer.yml' => (string) file_get_contents($releaseFixturePath.'/docker-compose.portainer.yml'),
        'docker-compose.prod.yml' => (string) file_get_contents($releaseFixturePath.'/docker-compose.prod.yml'),
        'docker-compose.registry.yml' => (string) file_get_contents($releaseFixturePath.'/docker-compose.registry.yml'),
        'docker-compose.yml' => (string) file_get_contents($releaseFixturePath.'/docker-compose.yml'),
    ];
    $releasedHashes = [
        '.docker/php/entrypoint.sh' => '0d925b3792945a4a368eb1792984b72a81179baaa159430846e559584f08e28f',
        'Dockerfile' => '651df5baf782cb9af41a2bdcb56e1bf440972c673c47c0554c9c4bb8fc3d3d7c',
        'docker-compose.dev.yml' => '18b871d6e4d52e607cabff4329c3bbb32a89f6b3a3a3a6c4f86474b779e7e915',
        'docker-compose.portainer.yml' => 'fc634d74167a50dc0e71f8f6e0f16955fadb25eb68518659de035fe83f3a3aff',
        'docker-compose.prod.yml' => 'afbb4e05ebb897fb5a0c9196d918b70b57de08d15ceb43796d085ae01b5051d8',
        'docker-compose.registry.yml' => 'd3b1222af0dd05b455c4823d598254cfa3ca978040e07c0f242c60d647ebd764',
        'docker-compose.yml' => '698df829ef20ada661bc48d30e0f59ce48b162e818bc5b8b3f6670e7cfebaa65',
    ];

    mkdir($basePath, 0777, true);
    file_put_contents($basePath.'/.env', implode(PHP_EOL, [
        'APP_IMAGE=registry.example/core-panel/app:1.5.0',
        'SYSTEM_UPDATER_COMPOSE_FILES=auto',
        'SYSTEM_UPDATER_COMPOSE_WORKDIR=/workspace',
        'SYSTEM_UPDATER_PROJECT_PATH='.$basePath.'/unmounted-host-project',
        'SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler,nginx',
        '',
    ]));

    foreach ($releasedFiles as $relativePath => $contents) {
        expect(hash('sha256', $contents))->toBe($releasedHashes[$relativePath]);

        $target = $basePath.'/'.$relativePath;
        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0777, true);
        }
        file_put_contents($target, $contents);
    }

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $manifest = json_decode(
        (string) file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    foreach ($releasedFiles as $relativePath => $releasedContents) {
        $expectedContents = (string) file_get_contents(__DIR__.'/../../stubs/'.$relativePath);
        $backups = glob($basePath.'/.core-panel-backups/*/'.$relativePath);

        expect(file_get_contents($basePath.'/'.$relativePath))->toBe($expectedContents)
            ->and($backups)->not->toBeEmpty()
            ->and(file_get_contents($backups[0]))->toBe($releasedContents)
            ->and($manifest['files'][$relativePath]['source_hash'] ?? null)->toBe(hash('sha256', $expectedContents))
            ->and($manifest['files'][$relativePath]['destination_hash'] ?? null)->toBe(hash('sha256', $expectedContents));
    }

    expect(file_get_contents($basePath.'/Dockerfile'))
        ->toContain('FROM serversideup/php:8.5-fpm-nginx AS php-extension-base')
        ->toContain('FROM app-runtime-base AS app-prod')
        ->toContain('FROM app-runtime-base AS app-dev')
        ->not->toContain(' AS php-prod')
        ->not->toContain(' AS php-dev')
        ->not->toContain(' AS nginx-prod')
        ->and(file_get_contents($basePath.'/.docker/php/entrypoint.sh'))
        ->toContain('PHP_FPM_CHILD_PROCESS_USER')
        ->toContain('Prepared writable runtime directories')
        ->and(file_get_contents($basePath.'/docker-compose.yml'))
        ->toContain('target: app-prod')
        ->toContain('target: app-dev')
        ->not->toContain('target: php-prod')
        ->and(file_get_contents($basePath.'/docker-compose.registry.yml'))
        ->toContain('${APP_IMAGE:?Set APP_IMAGE}')
        ->not->toContain('PHP_IMAGE')
        ->not->toContain('NGINX_IMAGE')
        ->and(file_get_contents($basePath.'/docker-compose.prod.yml'))
        ->not->toContain('target: nginx-prod')
        ->not->toContain("\n  nginx:\n")
        ->and(file_get_contents($basePath.'/docker-compose.portainer.yml'))
        ->toContain('${APP_IMAGE:?Set APP_IMAGE}')
        ->not->toContain('PHP_IMAGE')
        ->not->toContain('NGINX_IMAGE')
        ->not->toContain("\n  nginx:\n")
        ->and(file_get_contents($basePath.'/.env'))
        ->toContain("SYSTEM_UPDATER_COMPOSE_FILES=auto\n")
        ->toContain("SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler\n")
        ->not->toContain('SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler,nginx');
});

it('upgrades unified development scaffolds that predate host user mapping', function (): void {
    $basePath = makePublishBasePath('unified-development-before-user-mapping');
    $currentFiles = [
        'Dockerfile' => (string) file_get_contents(__DIR__.'/../../stubs/Dockerfile'),
        'docker-compose.dev.yml' => (string) file_get_contents(__DIR__.'/../../stubs/docker-compose.dev.yml'),
    ];
    $previousFiles = [
        'Dockerfile' => str_replace(<<<'DOCKERFILE'
ARG USER_ID=1000
ARG GROUP_ID=1000

RUN docker-php-serversideup-set-id www-data "${USER_ID}:${GROUP_ID}"
DOCKERFILE.PHP_EOL.PHP_EOL, '', $currentFiles['Dockerfile']),
        'docker-compose.dev.yml' => str_replace(
            [
                '    user: root'.PHP_EOL,
                '    pull_policy: never'.PHP_EOL,
                'UPDATER_COMPOSE_FILES: docker-compose.yml,docker-compose.dev.yml',
                'UPDATER_COMPOSE_WORKDIR: /workspace',
            ],
            [
                '',
                '',
                'UPDATER_COMPOSE_FILES: ${SYSTEM_UPDATER_COMPOSE_FILES:-auto}',
                'UPDATER_COMPOSE_WORKDIR: ${SYSTEM_UPDATER_COMPOSE_WORKDIR:-auto}',
            ],
            str_replace([
                <<<'COMPOSE'
x-development-build-args: &development-build-args
  USER_ID: ${DEV_USER_ID:-1000}
  GROUP_ID: ${DEV_GROUP_ID:-1000}
COMPOSE.PHP_EOL.PHP_EOL,
                <<<'COMPOSE'
      args:
        <<: *development-build-args
        APP_ENV: testing
COMPOSE.PHP_EOL,
                '        <<: *development-build-args'.PHP_EOL,
            ], '', $currentFiles['docker-compose.dev.yml']),
        ),
    ];

    expect(hash('sha256', $previousFiles['Dockerfile']))
        ->toBe('92b9b0142fc76cb9341ef68efc99477e59d6afd02c93d01011aaa626bcc4d90a')
        ->and(hash('sha256', $previousFiles['docker-compose.dev.yml']))
        ->toBe('dce23fe0b0ed4e04bbc4dcfcf35e3cae324572a1a957793884b12d6339179837');

    mkdir($basePath, 0777, true);

    foreach ($previousFiles as $relativePath => $contents) {
        file_put_contents($basePath.'/'.$relativePath, $contents);
    }

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $manifest = json_decode(
        (string) file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    foreach ($currentFiles as $relativePath => $currentContents) {
        $backups = glob($basePath.'/.core-panel-backups/*/'.$relativePath);

        expect(file_get_contents($basePath.'/'.$relativePath))->toBe($currentContents)
            ->and($backups)->not->toBeEmpty()
            ->and(file_get_contents($backups[0]))->toBe($previousFiles[$relativePath])
            ->and($manifest['files'][$relativePath]['destination_hash'] ?? null)->toBe(hash('sha256', $currentContents));
    }
});

it('upgrades the development compose scaffold with container-safe updater paths', function (): void {
    $basePath = makePublishBasePath('development-updater-container-paths');
    $relativePath = 'docker-compose.dev.yml';
    $currentContents = (string) file_get_contents(__DIR__.'/../../stubs/'.$relativePath);
    $previousContents = str_replace(
        [
            'UPDATER_COMPOSE_FILES: docker-compose.yml,docker-compose.dev.yml',
            'UPDATER_COMPOSE_WORKDIR: /workspace',
            '- ./:/workspace:ro',
        ],
        [
            'UPDATER_COMPOSE_FILES: ${SYSTEM_UPDATER_COMPOSE_FILES:-auto}',
            'UPDATER_COMPOSE_WORKDIR: ${SYSTEM_UPDATER_COMPOSE_WORKDIR:-/workspace}',
            '- ./:${SYSTEM_UPDATER_COMPOSE_WORKDIR:-/workspace}:ro',
        ],
        $currentContents,
    );

    expect(hash('sha256', $previousContents))
        ->toBe('30fa0802a5decceb07129f307cbbdccaaa834bac35abba3ab80d0ed3228901e6');

    mkdir($basePath, 0777, true);
    file_put_contents($basePath.'/'.$relativePath, $previousContents);

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $backups = glob($basePath.'/.core-panel-backups/*/'.$relativePath);

    expect(file_get_contents($basePath.'/'.$relativePath))->toBe($currentContents)
        ->and($backups)->not->toBeEmpty()
        ->and(file_get_contents($backups[0]))->toBe($previousContents);
});

it('upgrades the exact previous production compose scaffolds with and without package environment merge manifests', function (): void {
    $previousFiles = [
        'docker-compose.prod.yml' => (string) file_get_contents(__DIR__.'/../Fixtures/scaffolds/release-1.5.0/docker-compose.prod.yml'),
        'docker-compose.portainer.yml' => (string) file_get_contents(__DIR__.'/../Fixtures/scaffolds/release-1.5.0/docker-compose.portainer.yml'),
    ];
    $beforeEnvironmentMergeFiles = array_map(
        previousComposeBeforePackageEnvironmentMerge(...),
        $previousFiles,
    );

    expect(hash('sha256', $previousFiles['docker-compose.prod.yml']))
        ->toBe('afbb4e05ebb897fb5a0c9196d918b70b57de08d15ceb43796d085ae01b5051d8')
        ->and(hash('sha256', $previousFiles['docker-compose.portainer.yml']))
        ->toBe('fc634d74167a50dc0e71f8f6e0f16955fadb25eb68518659de035fe83f3a3aff')
        ->and(hash('sha256', $beforeEnvironmentMergeFiles['docker-compose.prod.yml']))
        ->toBe('a26ba0f8d1ba9cb20a70c75ad6116024d211b2dcc2b591f6894c9cf58324c17e')
        ->and(hash('sha256', $beforeEnvironmentMergeFiles['docker-compose.portainer.yml']))
        ->toBe('24a09c2b7a0947ccee51df077718e6e825598cc91e172ccf594f34350b4afbdd');

    foreach ([false, true] as $withEnvironmentMergeManifest) {
        $basePath = makePublishBasePath($withEnvironmentMergeManifest
            ? 'previous-compose-package-environment-merge'
            : 'previous-compose-without-manifest');

        mkdir($basePath, 0777, true);

        if ($withEnvironmentMergeManifest) {
            seedScaffoldManifestFiles($basePath, $beforeEnvironmentMergeFiles, '1.4.1');
        }

        file_put_contents($basePath.'/.env', "APP_IMAGE=registry.example/core-panel/app:latest\n");

        foreach ($previousFiles as $relativePath => $contents) {
            file_put_contents($basePath.'/'.$relativePath, $contents);
        }

        $this->artisan('core-panel:update', [
            '--base-path' => $basePath,
        ])->assertExitCode(0);

        $manifest = json_decode(
            (string) file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        foreach ($previousFiles as $relativePath => $previousContents) {
            $expectedContents = (string) file_get_contents(__DIR__.'/../../stubs/'.$relativePath);
            $backups = glob($basePath.'/.core-panel-backups/*/'.$relativePath);

            expect(file_get_contents($basePath.'/'.$relativePath))->toBe($expectedContents)
                ->and($backups)->not->toBeEmpty()
                ->and(file_get_contents($backups[0]))->toBe($previousContents)
                ->and($manifest['files'][$relativePath]['source_hash'] ?? null)->toBe(hash('sha256', $expectedContents))
                ->and($manifest['files'][$relativePath]['destination_hash'] ?? null)->toBe(hash('sha256', $expectedContents));
        }

        expect(file_get_contents($basePath.'/docker-compose.prod.yml'))
            ->not->toContain('target: nginx-prod')
            ->not->toContain("\n  nginx:\n")
            ->toContain('    user: root')
            ->toContain("      app:\n        condition: service_healthy")
            ->toContain('"${APP_PORT:-8000}:8080"')
            ->and(file_get_contents($basePath.'/docker-compose.portainer.yml'))
            ->toContain('${APP_IMAGE:?Set APP_IMAGE}')
            ->toContain('    user: root')
            ->toContain("      app:\n        condition: service_healthy")
            ->not->toContain('PHP_IMAGE')
            ->not->toContain('NGINX_IMAGE')
            ->not->toContain("\n  nginx:\n");
    }
});

it('upgrades the runtime entrypoint that predates mounted storage ownership initialization', function (): void {
    $basePath = makePublishBasePath('previous-runtime-entrypoint-storage-ownership');
    $relativePath = '.docker/php/entrypoint.sh';
    $currentContents = (string) file_get_contents(__DIR__.'/../../stubs/'.$relativePath);
    $previousContents = str_replace([
        <<<'SH'
if [ "$(id -u)" -eq 0 ]; then
    runtime_user="${PHP_FPM_CHILD_PROCESS_USER:-www-data}"
    runtime_group="${PHP_FPM_CHILD_PROCESS_GROUP:-www-data}"
fi
SH."\n\n",
        <<<'SH'
if [ "$(id -u)" -eq 0 ]; then
    chown -R "${runtime_user}:${runtime_group}" storage bootstrap/cache
    log "✅ success " "Prepared writable runtime directories for ${runtime_user}:${runtime_group}"
fi
SH."\n\n",
    ], '', $currentContents);

    expect(hash('sha256', $previousContents))
        ->toBe('ace55903a4c43bb51305ee968b4ddcd50dd8df21180c346a4d486f10387b25ba');

    mkdir(dirname($basePath.'/'.$relativePath), 0777, true);
    file_put_contents($basePath.'/'.$relativePath, $previousContents);

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $manifest = json_decode(
        (string) file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );
    $backups = glob($basePath.'/.core-panel-backups/*/'.$relativePath);

    expect(file_get_contents($basePath.'/'.$relativePath))->toBe($currentContents)
        ->and($backups)->not->toBeEmpty()
        ->and(file_get_contents($backups[0]))->toBe($previousContents)
        ->and($manifest['files'][$relativePath]['destination_hash'] ?? null)->toBe(hash('sha256', $currentContents));
});

it('removes legacy generated route scaffolds during upgrades', function (): void {
    $basePath = makePublishBasePath('legacy-system-update-route-scaffold');
    $relativePath = 'resources/js/routes/core-panel/system-updates.ts';
    $target = $basePath.'/'.$relativePath;
    $legacyContents = <<<'TS'
import { action } from '../_wayfinder'

export default {
    check: action('post'),
    settings: {
        update: action('put'),
    },
    status: action('get'),
    update: action('post'),
}
TS;
    $legacyContents .= "\n";

    mkdir(dirname($target), 0777, true);
    file_put_contents($target, $legacyContents);

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $manifest = json_decode(
        (string) file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    expect(file_exists($target))->toBeFalse()
        ->and($manifest['files'][$relativePath] ?? null)->toBeNull()
        ->and(glob($basePath.'/.core-panel-backups/*/'.$relativePath))->not->toBeEmpty();
});

it('preserves an existing untracked customized route file during upgrades', function (): void {
    $basePath = makePublishBasePath('untracked-system-update-route-scaffold');
    $relativePath = 'resources/js/routes/core-panel/system-updates.ts';
    $target = $basePath.'/'.$relativePath;
    $customContents = "export default { custom: true }\n";

    mkdir(dirname($target), 0777, true);
    file_put_contents($target, $customContents);

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $manifest = json_decode(
        (string) file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );
    $backups = glob($basePath.'/.core-panel-backups/*/'.$relativePath);

    expect(file_get_contents($target))->toBe($customContents)
        ->and($backups)->toBeEmpty()
        ->and($manifest['files'][$relativePath] ?? null)->toBeNull();
});

it('creates the OIDC services scaffold when it is missing during an update', function (): void {
    $basePath = makePublishBasePath('missing-oidc-services-scaffold');

    mkdir($basePath, 0777, true);

    $this->artisan('core-panel:update', ['--base-path' => $basePath])
        ->assertExitCode(0);

    expect((string) file_get_contents($basePath.'/config/services.php'))
        ->toContain("'oidc' => [")
        ->toContain("env('OIDC_ISSUER')")
        ->toContain("env('MICROSOFT_FORCE_TLS12', false)");
});

it('adopts an existing untracked services scaffold with the OIDC provider during a forced update', function (): void {
    $basePath = makePublishBasePath('existing-oidc-services-scaffold');
    $legacy = "<?php\n\nreturn [];\n";
    $target = $basePath.'/config/services.php';

    mkdir(dirname($target), 0777, true);
    file_put_contents($target, $legacy);

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
        '--force' => true,
    ])
        ->assertExitCode(0);

    $manifest = json_decode(
        (string) file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );
    $backups = glob($basePath.'/.core-panel-backups/*/config/services.php');

    expect((string) file_get_contents($target))
        ->toContain("'oidc' => [")
        ->toContain("env('OIDC_CLIENT_ID')")
        ->toContain("env('MICROSOFT_FORCE_TLS12', false)")
        ->and($backups)->not->toBeEmpty()
        ->and((string) file_get_contents($backups[0]))->toBe($legacy)
        ->and($manifest['files']['config/services.php'] ?? null)->toBeArray();
});

it('does not create unlisted missing application scaffolds just because another scaffold has an old baseline', function (): void {
    $basePath = makePublishBasePath('missing-unlisted-scaffold-with-baseline');
    $managedContents = "<?php\n\n// old managed console scaffold\n";
    $unlistedTarget = $basePath.'/resources/js/pages/Admin/Settings/Index.vue';

    seedScaffoldManifest($basePath, 'routes/console.php', $managedContents);

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    expect(file_exists($unlistedTarget))->toBeFalse();
});

it('restores missing manifest-managed application scaffolds during updates', function (): void {
    $basePath = makePublishBasePath('missing-managed-scaffold');
    $target = $basePath.'/routes/console.php';
    $legacyContents = "<?php\n\n// old managed console scaffold\n";

    seedScaffoldManifest($basePath, 'routes/console.php', $legacyContents);

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    expect(file_exists($target))->toBeTrue()
        ->and(file_get_contents($target))->toContain("Artisan::command('inspire'")
        ->and(file_get_contents($target))->not->toContain('Schedule::');
});

it('creates explicitly versioned missing application scaffolds during updates', function (): void {
    $basePath = makePublishBasePath('missing-versioned-scaffold');

    mkdir($basePath, 0777, true);

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    foreach (versionedUpdateScaffoldPaths() as $relativePath) {
        if (str_starts_with($relativePath, 'lang/')) {
            expect(file_exists($basePath.'/'.$relativePath))
                ->toBeFalse("Expected {$relativePath} to stay vendor-first when missing.");

            continue;
        }

        if ($relativePath === 'resources/js/components/AppIcon.vue') {
            expect(file_exists($basePath.'/'.$relativePath))
                ->toBeFalse("Expected {$relativePath} to stay absent when the host has no existing scaffold.");

            continue;
        }

        if (in_array($relativePath, updatePreservedScaffoldPaths(), true)) {
            expect(file_exists($basePath.'/'.$relativePath))
                ->toBeFalse("Expected {$relativePath} to stay host-owned when missing during updates.");

            continue;
        }

        expect(file_exists($basePath.'/'.$relativePath))
            ->toBeTrue("Expected {$relativePath} to be created during managed-only updates.");
    }
});

it('keeps administration route scaffolds vendor-first during updates', function (): void {
    $basePath = makePublishBasePath('local-full-scaffold-sync');
    $existingTarget = $basePath.'/routes/web/admin/administration.php';
    $missingTarget = $basePath.'/routes/web/admin/system-updates.php';
    $customContents = "<?php\n\n// custom local administration routes\n";

    mkdir(dirname($existingTarget), 0777, true);
    file_put_contents($existingTarget, $customContents);

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    expect(file_exists($existingTarget))->toBeTrue()
        ->and(file_get_contents($existingTarget))->toBe($customContents)
        ->and(file_exists($missingTarget))->toBeFalse()
        ->and(glob($basePath.'/.core-panel-backups/*/routes/web/admin/administration.php'))
        ->toBe([]);
});

it('preserves local theme imports in app css during local full scaffold sync updates', function (): void {
    $basePath = makePublishBasePath('local-full-scaffold-sync-theme-imports');
    $originalEnvironment = app()->environment();
    $themeRelativePath = 'resources/css/theme/_auth.css';
    $themeTarget = $basePath.'/'.$themeRelativePath;
    $sourceThemeContents = (string) file_get_contents(__DIR__.'/../../resources/css/theme/_auth.css');
    $customizedThemeContents = $sourceThemeContents."\n.local-auth-override { color: red; }\n";

    mkdir(dirname($themeTarget), 0777, true);
    file_put_contents($basePath.'/resources/css/app.css', '/* legacy host app css */'."\n");
    file_put_contents($themeTarget, $customizedThemeContents);
    seedScaffoldManifest($basePath, $themeRelativePath, $sourceThemeContents);

    app()->instance('env', 'local');

    try {
        $this->artisan('core-panel:update', [
            '--base-path' => $basePath,
        ])->assertExitCode(0);
    } finally {
        app()->instance('env', $originalEnvironment);
    }

    $appCss = (string) file_get_contents($basePath.'/resources/css/app.css');

    expect($appCss)->toContain("@import '@core-panel/theme/core-panel/index.css';")
        ->and($appCss)->toContain("@import './theme/_auth.css';")
        ->and(file_get_contents($themeTarget))->toBe($customizedThemeContents)
        ->and(glob($basePath.'/.core-panel-backups/*/resources/css/app.css'))->not->toBeEmpty();
});

it('creates explicitly versioned missing application scaffolds without per-file current manifest entries', function (): void {
    $basePath = makePublishBasePath('missing-versioned-scaffold-no-file-entry');
    $managedContents = "<?php\n\n// current managed console scaffold\n";

    seedScaffoldManifest($basePath, 'routes/console.php', $managedContents, currentCorePanelPackageVersion());

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    foreach (versionedUpdateScaffoldPaths() as $relativePath) {
        if (str_starts_with($relativePath, 'lang/')) {
            expect(file_exists($basePath.'/'.$relativePath))
                ->toBeFalse("Expected {$relativePath} to stay vendor-first when it has no current scaffold manifest entry.");

            continue;
        }

        if ($relativePath === 'resources/js/components/AppIcon.vue') {
            expect(file_exists($basePath.'/'.$relativePath))
                ->toBeFalse("Expected {$relativePath} to stay absent when it has no current scaffold manifest entry.");

            continue;
        }

        if (in_array($relativePath, updatePreservedScaffoldPaths(), true)) {
            expect(file_exists($basePath.'/'.$relativePath))
                ->toBeFalse("Expected {$relativePath} to stay host-owned when it has no current scaffold manifest entry.");

            continue;
        }

        expect(file_exists($basePath.'/'.$relativePath))
            ->toBeTrue("Expected {$relativePath} to be created when it has no current scaffold manifest entry.");
    }
});

it('creates the versioned bootstrap middleware scaffold during updates', function (): void {
    $basePath = makePublishBasePath('missing-versioned-bootstrap-app');
    $target = $basePath.'/bootstrap/app.php';

    mkdir($basePath, 0777, true);

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    expect(file_exists($target))->toBeTrue()
        ->and(file_get_contents($target))->toContain('use CorePanel\Http\Middleware\AllowBlobImageCsp;')
        ->and(file_get_contents($target))->toContain('AllowBlobImageCsp::class');
});

it('creates the versioned pwa scaffolds during updates', function (): void {
    $basePath = makePublishBasePath('missing-versioned-pwa-scaffolds');
    $configTarget = $basePath.'/config/pwa.php';
    $manifestTarget = $basePath.'/public/manifest.json';
    $serviceWorkerTarget = $basePath.'/public/sw.js';
    $offlineTarget = $basePath.'/public/offline.html';
    $logoTarget = $basePath.'/public/logo.png';

    mkdir($basePath, 0777, true);
    file_put_contents($basePath.'/.env', "APP_NAME=\"Reference Control\"\n");

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    expect(file_exists($configTarget))->toBeTrue()
        ->and(file_get_contents($configTarget))->toContain("'install-button' => true")
        ->and(file_get_contents($configTarget))->toContain("'src' => 'logo.png'")
        ->and(file_exists($manifestTarget))->toBeTrue()
        ->and(file_get_contents($manifestTarget))->toContain('"name": "Reference Control"')
        ->and(file_get_contents($manifestTarget))->toContain('"short_name": "Reference Control"')
        ->and(file_get_contents($manifestTarget))->toContain('"src": "logo.png"')
        ->and(file_exists($serviceWorkerTarget))->toBeTrue()
        ->and(file_get_contents($serviceWorkerTarget))->toContain("const OFFLINE_URL = '/offline.html';")
        ->and(file_exists($offlineTarget))->toBeTrue()
        ->and(file_get_contents($offlineTarget))->toContain('Check your internet connection')
        ->and(file_exists($logoTarget))->toBeTrue();
});

it('creates the versioned trusted proxy scaffold during updates', function (): void {
    $basePath = makePublishBasePath('missing-versioned-trustedproxy');
    $target = $basePath.'/config/trustedproxy.php';

    mkdir($basePath, 0777, true);

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    expect(file_exists($target))->toBeTrue()
        ->and(file_get_contents($target))->toContain("'proxies' => env('TRUSTED_PROXIES')")
        ->and(file_get_contents($target))->toContain('Request::HEADER_X_FORWARDED_PROTO');
});

it('does not overwrite existing unmanaged generic pwa public assets during updates', function (): void {
    $basePath = makePublishBasePath('preserve-unmanaged-pwa-public-assets');
    $manifestTarget = $basePath.'/public/manifest.json';
    $serviceWorkerTarget = $basePath.'/public/sw.js';
    $offlineTarget = $basePath.'/public/offline.html';
    $logoTarget = $basePath.'/public/logo.png';

    mkdir(dirname($manifestTarget), 0777, true);
    file_put_contents($manifestTarget, "{\"name\":\"Host App\"}\n");
    file_put_contents($serviceWorkerTarget, "const CACHE_NAME = 'host-app-cache';\n");
    file_put_contents($offlineTarget, "<html><body>Host offline page</body></html>\n");
    file_put_contents($logoTarget, 'host-logo');

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    expect(file_get_contents($manifestTarget))->toBe("{\"name\":\"Host App\"}\n")
        ->and(file_get_contents($serviceWorkerTarget))->toBe("const CACHE_NAME = 'host-app-cache';\n")
        ->and(file_get_contents($offlineTarget))->toBe("<html><body>Host offline page</body></html>\n")
        ->and(file_get_contents($logoTarget))->toBe('host-logo')
        ->and(glob($basePath.'/.core-panel-backups/*/public/manifest.json'))->toBe([])
        ->and(glob($basePath.'/.core-panel-backups/*/public/sw.js'))->toBe([])
        ->and(glob($basePath.'/.core-panel-backups/*/public/offline.html'))->toBe([])
        ->and(glob($basePath.'/.core-panel-backups/*/public/logo.png'))->toBe([]);
});

it('does not overwrite an existing unmanaged pwa config during updates', function (): void {
    $basePath = makePublishBasePath('preserve-unmanaged-pwa-config');
    $target = $basePath.'/config/pwa.php';

    mkdir(dirname($target), 0777, true);
    file_put_contents($target, <<<'PHP'
<?php

return [
    'install-button' => false,
    'manifest' => [
        'name' => 'Host App',
        'short_name' => 'Host',
        'background_color' => '#123456',
        'display' => 'browser',
        'description' => 'Host-managed PWA configuration.',
        'theme_color' => '#654321',
        'icons' => [
            [
                'src' => 'host-icon.png',
                'sizes' => '192x192',
                'type' => 'image/png',
            ],
        ],
    ],
];
PHP);

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    expect(file_get_contents($target))->toContain("'install-button' => false")
        ->and(file_get_contents($target))->toContain("'name' => 'Host App'")
        ->and(file_get_contents($target))->toContain("'src' => 'host-icon.png'")
        ->and(glob($basePath.'/.core-panel-backups/*/config/pwa.php'))->toBe([]);
});

it('does not overwrite customized managed pwa scaffolds during updates', function (): void {
    $basePath = makePublishBasePath('preserve-managed-pwa-scaffolds');
    $currentVersion = currentCorePanelPackageVersion();
    $managedFiles = [
        'config/pwa.php' => [
            'source' => (string) file_get_contents(__DIR__.'/../../stubs/config/pwa.php'),
            'customized' => <<<'PHP'
<?php

return [
    'install-button' => false,
    'manifest' => [
        'name' => 'Managed Host App',
        'short_name' => 'ManagedHost',
        'background_color' => '#101010',
        'display' => 'browser',
        'description' => 'Managed host PWA configuration.',
        'theme_color' => '#f97316',
        'icons' => [
            [
                'src' => 'managed-icon.png',
                'sizes' => '512x512',
                'type' => 'image/png',
            ],
        ],
    ],
];
PHP,
        ],
        'public/manifest.json' => [
            'source' => (string) file_get_contents(__DIR__.'/../../stubs/public/manifest.json'),
            'customized' => "{\n    \"name\": \"Managed Host App\",\n    \"short_name\": \"ManagedHost\",\n    \"start_url\": \"/\",\n    \"background_color\": \"#101010\",\n    \"description\": \"Managed host manifest.\",\n    \"display\": \"browser\",\n    \"theme_color\": \"#f97316\",\n    \"icons\": [\n        {\n            \"src\": \"managed-icon.png\",\n            \"sizes\": \"512x512\",\n            \"type\": \"image/png\"\n        }\n    ]\n}\n",
        ],
        'public/offline.html' => [
            'source' => (string) file_get_contents(__DIR__.'/../../stubs/public/offline.html'),
            'customized' => "<html><body>Managed offline page</body></html>\n",
        ],
        'public/sw.js' => [
            'source' => (string) file_get_contents(__DIR__.'/../../stubs/public/sw.js'),
            'customized' => "const CACHE_NAME = 'managed-host-cache';\n",
        ],
        'public/logo.png' => [
            'source' => (string) file_get_contents(__DIR__.'/../../stubs/public/logo.png'),
            'customized' => 'managed-logo',
        ],
    ];

    foreach ($managedFiles as $relativePath => $file) {
        $target = $basePath.'/'.$relativePath;
        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0777, true);
        }
        file_put_contents($target, $file['customized']);
    }

    seedScaffoldManifestFiles(
        $basePath,
        array_map(
            static fn (array $file): string => $file['source'],
            $managedFiles,
        ),
        $currentVersion,
    );

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    foreach ($managedFiles as $relativePath => $file) {
        expect(file_get_contents($basePath.'/'.$relativePath))->toBe($file['customized'])
            ->and(glob($basePath.'/.core-panel-backups/*/'.$relativePath))->toBe([]);
    }
});

it('merges the pwa provider into existing bootstrap providers during updates', function (): void {
    $basePath = makePublishBasePath('merge-bootstrap-providers');
    $target = $basePath.'/bootstrap/providers.php';

    mkdir(dirname($target), 0777, true);
    file_put_contents($target, <<<'PHP'
<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\TelemetryServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    HorizonServiceProvider::class,
    TelemetryServiceProvider::class,
];
PHP);

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    expect(file_get_contents($target))->toContain('use App\Providers\TelemetryServiceProvider;')
        ->and(file_get_contents($target))->toContain('use App\Providers\FortifyServiceProvider;')
        ->and(file_get_contents($target))->toContain('use App\Providers\HorizonServiceProvider;')
        ->and(file_get_contents($target))->toContain('use EragLaravelPwa\EragLaravelPwaServiceProvider;')
        ->and(file_get_contents($target))->toContain('FortifyServiceProvider::class,')
        ->and(file_get_contents($target))->toContain('HorizonServiceProvider::class,')
        ->and(file_get_contents($target))->toContain('TelemetryServiceProvider::class,')
        ->and(file_get_contents($target))->toContain('EragLaravelPwaServiceProvider::class,')
        ->and(glob($basePath.'/.core-panel-backups/*/bootstrap/providers.php'))
        ->not->toBeEmpty();
});

it('keeps customized unmanaged host providers registered during updates', function (): void {
    $basePath = makePublishBasePath('preserve-customized-host-providers');
    $bootstrapTarget = $basePath.'/bootstrap/providers.php';
    $fortifyTarget = $basePath.'/app/Providers/FortifyServiceProvider.php';
    $horizonTarget = $basePath.'/app/Providers/HorizonServiceProvider.php';
    $fortifyContents = "<?php\n\n// Customized Fortify hooks.\n";
    $horizonContents = "<?php\n\n// Customized Horizon authorization.\n";

    mkdir(dirname($bootstrapTarget), 0777, true);
    mkdir(dirname($fortifyTarget), 0777, true);
    file_put_contents($fortifyTarget, $fortifyContents);
    file_put_contents($horizonTarget, $horizonContents);
    file_put_contents($bootstrapTarget, <<<'PHP'
<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\HorizonServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    HorizonServiceProvider::class,
];
PHP);

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $bootstrapContents = (string) file_get_contents($bootstrapTarget);

    expect(file_get_contents($fortifyTarget))->toBe($fortifyContents)
        ->and(file_get_contents($horizonTarget))->toBe($horizonContents)
        ->and($bootstrapContents)->toContain('use App\Providers\FortifyServiceProvider;')
        ->and($bootstrapContents)->toContain('use App\Providers\HorizonServiceProvider;')
        ->and($bootstrapContents)->toContain('FortifyServiceProvider::class,')
        ->and($bootstrapContents)->toContain('HorizonServiceProvider::class,')
        ->and(glob($basePath.'/.core-panel-backups/*/app/Providers/FortifyServiceProvider.php'))->toBe([])
        ->and(glob($basePath.'/.core-panel-backups/*/app/Providers/HorizonServiceProvider.php'))->toBe([]);
});

it('unregisters unchanged provider scaffolds confirmed as package managed', function (): void {
    $basePath = makePublishBasePath('remove-managed-host-providers');
    $bootstrapTarget = $basePath.'/bootstrap/providers.php';
    $providerScaffolds = [
        'app/Providers/FortifyServiceProvider.php' => "<?php\n\n// Managed Fortify provider.\n",
        'app/Providers/HorizonServiceProvider.php' => "<?php\n\n// Managed Horizon provider.\n",
    ];

    foreach ($providerScaffolds as $relativePath => $contents) {
        $target = $basePath.'/'.$relativePath;

        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0777, true);
        }

        file_put_contents($target, $contents);
    }

    mkdir(dirname($bootstrapTarget), 0777, true);
    file_put_contents($bootstrapTarget, <<<'PHP'
<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\HorizonServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    HorizonServiceProvider::class,
];
PHP);
    seedScaffoldManifestFiles($basePath, $providerScaffolds);

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $bootstrapContents = (string) file_get_contents($bootstrapTarget);

    expect(file_exists($basePath.'/app/Providers/FortifyServiceProvider.php'))->toBeFalse()
        ->and(file_exists($basePath.'/app/Providers/HorizonServiceProvider.php'))->toBeFalse()
        ->and($bootstrapContents)->not->toContain('FortifyServiceProvider')
        ->and($bootstrapContents)->not->toContain('HorizonServiceProvider')
        ->and(glob($basePath.'/.core-panel-backups/*/app/Providers/FortifyServiceProvider.php'))->not->toBeEmpty()
        ->and(glob($basePath.'/.core-panel-backups/*/app/Providers/HorizonServiceProvider.php'))->not->toBeEmpty();
});

it('keeps same-named vendor providers registered when obsolete app providers are removed', function (): void {
    $basePath = makePublishBasePath('preserve-same-named-vendor-providers');
    $bootstrapTarget = $basePath.'/bootstrap/providers.php';
    $providerScaffolds = [
        'app/Providers/FortifyServiceProvider.php' => "<?php\n\n// Managed Fortify provider.\n",
        'app/Providers/HorizonServiceProvider.php' => "<?php\n\n// Managed Horizon provider.\n",
    ];

    foreach ($providerScaffolds as $relativePath => $contents) {
        $target = $basePath.'/'.$relativePath;

        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0777, true);
        }

        file_put_contents($target, $contents);
    }

    mkdir(dirname($bootstrapTarget), 0777, true);
    file_put_contents($bootstrapTarget, <<<'PHP'
<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use Vendor\Auth\FortifyServiceProvider;
use Vendor\Queue\HorizonServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    HorizonServiceProvider::class,
];
PHP);
    seedScaffoldManifestFiles($basePath, $providerScaffolds);

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $bootstrapContents = (string) file_get_contents($bootstrapTarget);

    expect(file_exists($basePath.'/app/Providers/FortifyServiceProvider.php'))->toBeFalse()
        ->and(file_exists($basePath.'/app/Providers/HorizonServiceProvider.php'))->toBeFalse()
        ->and($bootstrapContents)->toContain('use Vendor\Auth\FortifyServiceProvider;')
        ->and($bootstrapContents)->toContain('use Vendor\Queue\HorizonServiceProvider;')
        ->and($bootstrapContents)->toContain('FortifyServiceProvider::class,')
        ->and($bootstrapContents)->toContain('HorizonServiceProvider::class,');
});

it('keeps locally modified managed host providers registered during updates', function (): void {
    $basePath = makePublishBasePath('preserve-modified-managed-host-providers');
    $bootstrapTarget = $basePath.'/bootstrap/providers.php';
    $managedProviderScaffolds = [
        'app/Providers/FortifyServiceProvider.php' => "<?php\n\n// Published Fortify provider.\n",
        'app/Providers/HorizonServiceProvider.php' => "<?php\n\n// Published Horizon provider.\n",
    ];
    $customizedProviderScaffolds = [
        'app/Providers/FortifyServiceProvider.php' => "<?php\n\n// Customized Fortify hooks and rate limiters.\n",
        'app/Providers/HorizonServiceProvider.php' => "<?php\n\n// Customized Horizon authorization.\n",
    ];

    foreach ($managedProviderScaffolds as $relativePath => $contents) {
        $target = $basePath.'/'.$relativePath;

        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0777, true);
        }

        file_put_contents($target, $contents);
    }

    seedScaffoldManifestFiles($basePath, $managedProviderScaffolds);

    foreach ($customizedProviderScaffolds as $relativePath => $contents) {
        file_put_contents($basePath.'/'.$relativePath, $contents);
    }

    mkdir(dirname($bootstrapTarget), 0777, true);
    file_put_contents($bootstrapTarget, <<<'PHP'
<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\HorizonServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    HorizonServiceProvider::class,
];
PHP);

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $bootstrapContents = (string) file_get_contents($bootstrapTarget);
    $manifest = json_decode(
        (string) file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    foreach ($customizedProviderScaffolds as $relativePath => $contents) {
        expect(file_get_contents($basePath.'/'.$relativePath))->toBe($contents)
            ->and($manifest['files'])->toHaveKey($relativePath)
            ->and(glob($basePath.'/.core-panel-backups/*/'.$relativePath))->toBe([]);
    }

    expect($bootstrapContents)->toContain('use App\Providers\FortifyServiceProvider;')
        ->and($bootstrapContents)->toContain('use App\Providers\HorizonServiceProvider;')
        ->and($bootstrapContents)->toContain('FortifyServiceProvider::class,')
        ->and($bootstrapContents)->toContain('HorizonServiceProvider::class,');
});

it('keeps locally modified obsolete managed scaffolds during updates', function (): void {
    $basePath = makePublishBasePath('preserve-modified-obsolete-managed-scaffolds');
    $managedScaffolds = [
        'app/Actions/Fortify/CreateNewUser.php' => "<?php\n\n// Published Fortify action.\n",
        'app/Http/Middleware/TrackUserPresence.php' => "<?php\n\n// Published presence middleware.\n",
        'app/OpenApi/CorePanelApiDocumentation.php' => "<?php\n\n// Published OpenAPI documentation.\n",
    ];
    $customizedScaffolds = [
        'app/Actions/Fortify/CreateNewUser.php' => "<?php\n\n// Customized Fortify registration action.\n",
        'app/Http/Middleware/TrackUserPresence.php' => "<?php\n\n// Customized presence tracking.\n",
        'app/OpenApi/CorePanelApiDocumentation.php' => "<?php\n\n// Customized host API documentation.\n",
    ];

    foreach ($managedScaffolds as $relativePath => $contents) {
        $target = $basePath.'/'.$relativePath;

        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0777, true);
        }

        file_put_contents($target, $contents);
    }

    seedScaffoldManifestFiles($basePath, $managedScaffolds);

    foreach ($customizedScaffolds as $relativePath => $contents) {
        file_put_contents($basePath.'/'.$relativePath, $contents);
    }

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $manifest = json_decode(
        (string) file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    foreach ($customizedScaffolds as $relativePath => $contents) {
        expect(file_get_contents($basePath.'/'.$relativePath))->toBe($contents)
            ->and($manifest['files'])->toHaveKey($relativePath)
            ->and(glob($basePath.'/.core-panel-backups/*/'.$relativePath))->toBe([]);
    }
});

it('keeps a preserved presence middleware wired when updating a managed bootstrap scaffold', function (): void {
    $basePath = makePublishBasePath('preserve-customized-presence-wiring');
    $presenceRelativePath = 'app/Http/Middleware/TrackUserPresence.php';
    $bootstrapRelativePath = 'bootstrap/app.php';
    $presenceTarget = $basePath.'/'.$presenceRelativePath;
    $bootstrapTarget = $basePath.'/'.$bootstrapRelativePath;
    $publishedPresence = "<?php\n\n// Published presence middleware.\n";
    $customizedPresence = "<?php\n\n// Customized host presence behavior.\n";
    $publishedBootstrap = legacyCriticalBootstrapAppContents();

    mkdir(dirname($presenceTarget), 0777, true);
    mkdir(dirname($bootstrapTarget), 0777, true);
    file_put_contents($presenceTarget, $publishedPresence);
    file_put_contents($bootstrapTarget, $publishedBootstrap);
    seedScaffoldManifestFiles($basePath, [
        $presenceRelativePath => $publishedPresence,
        $bootstrapRelativePath => $publishedBootstrap,
    ]);
    file_put_contents($presenceTarget, $customizedPresence);

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $bootstrapContents = (string) file_get_contents($bootstrapTarget);

    expect(file_get_contents($presenceTarget))->toBe($customizedPresence)
        ->and($bootstrapContents)->toContain('class_exists(App\Http\Middleware\TrackUserPresence::class)')
        ->and($bootstrapContents)->toContain('? App\Http\Middleware\TrackUserPresence::class')
        ->and($bootstrapContents)->toContain(': TrackUserPresence::class;')
        ->and($bootstrapContents)->toContain('$presenceMiddleware,')
        ->and(glob($basePath.'/.core-panel-backups/*/'.$presenceRelativePath))->toBe([])
        ->and(glob($basePath.'/.core-panel-backups/*/'.$bootstrapRelativePath))->not->toBeEmpty();
});

it('removes unchanged obsolete managed scaffolds during updates', function (): void {
    $basePath = makePublishBasePath('remove-unchanged-obsolete-managed-scaffolds');
    $relativePath = 'app/Actions/Fortify/CreateNewUser.php';
    $managedContents = "<?php\n\n// Published Fortify action.\n";
    $target = $basePath.'/'.$relativePath;

    mkdir(dirname($target), 0777, true);
    file_put_contents($target, $managedContents);
    seedScaffoldManifest($basePath, $relativePath, $managedContents);

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $manifest = json_decode(
        (string) file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    expect(file_exists($target))->toBeFalse()
        ->and($manifest['files'])->not->toHaveKey($relativePath)
        ->and(glob($basePath.'/.core-panel-backups/*/'.$relativePath))->not->toBeEmpty();
});

it('maps a preserved customized Fortify action after removing its unchanged provider', function (): void {
    $basePath = makePublishBasePath('map-preserved-fortify-action');
    $actionRelativePath = 'app/Actions/Fortify/CreateNewUser.php';
    $providerRelativePath = 'app/Providers/FortifyServiceProvider.php';
    $actionTarget = $basePath.'/'.$actionRelativePath;
    $providerTarget = $basePath.'/'.$providerRelativePath;
    $bootstrapTarget = $basePath.'/bootstrap/providers.php';
    $publishedAction = "<?php\n\n// Published registration action.\n";
    $customizedAction = "<?php\n\n// Customized registration rules.\n";
    $publishedProvider = "<?php\n\n// Published Fortify provider.\n";

    mkdir(dirname($actionTarget), 0777, true);
    mkdir(dirname($providerTarget), 0777, true);
    mkdir(dirname($bootstrapTarget), 0777, true);
    file_put_contents($actionTarget, $publishedAction);
    file_put_contents($providerTarget, $publishedProvider);
    seedScaffoldManifestFiles($basePath, [
        $actionRelativePath => $publishedAction,
        $providerRelativePath => $publishedProvider,
    ]);
    file_put_contents($actionTarget, $customizedAction);
    file_put_contents($bootstrapTarget, <<<'PHP'
<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
];
PHP);

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $overrideTarget = $basePath.'/config/core-panel-fortify-actions.php';
    $overrides = require $overrideTarget;

    expect(file_get_contents($actionTarget))->toBe($customizedAction)
        ->and(file_exists($providerTarget))->toBeFalse()
        ->and(file_get_contents($bootstrapTarget))->not->toContain('FortifyServiceProvider')
        ->and(file_exists($overrideTarget))->toBeTrue()
        ->and($overrides)->toBe([
            'create_user' => 'App\Actions\Fortify\CreateNewUser',
        ])
        ->and(glob($basePath.'/.core-panel-backups/*/'.$actionRelativePath))->toBe([])
        ->and(glob($basePath.'/.core-panel-backups/*/'.$providerRelativePath))->not->toBeEmpty();
});

it('merges the fully qualified pwa provider into bootstrap providers without imports', function (): void {
    $basePath = makePublishBasePath('merge-bootstrap-providers-without-imports');
    $target = $basePath.'/bootstrap/providers.php';

    mkdir(dirname($target), 0777, true);
    file_put_contents($target, <<<'PHP'
<?php

declare(strict_types=1);

return [
    \App\Providers\AppServiceProvider::class,
    \App\Providers\FortifyServiceProvider::class,
    \App\Providers\CustomTelemetryServiceProvider::class,
];
PHP);

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    expect(file_get_contents($target))->not->toContain('use EragLaravelPwa\EragLaravelPwaServiceProvider;')
        ->and(file_get_contents($target))->toContain('\App\Providers\FortifyServiceProvider::class,')
        ->and(file_get_contents($target))->toContain('\App\Providers\CustomTelemetryServiceProvider::class,')
        ->and(file_get_contents($target))->toContain('\EragLaravelPwa\EragLaravelPwaServiceProvider::class,')
        ->and(glob($basePath.'/.core-panel-backups/*/bootstrap/providers.php'))
        ->not->toBeEmpty();
});

it('does not create missing page-users translation scaffolds during updates', function (): void {
    $basePath = makePublishBasePath('missing-versioned-page-users-translations');
    $englishTarget = $basePath.'/lang/en/page-users.php';
    $germanTarget = $basePath.'/lang/de/page-users.php';

    mkdir($basePath, 0777, true);

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    expect(file_exists($englishTarget))->toBeFalse()
        ->and(file_exists($germanTarget))->toBeFalse();
});

it('does not create missing account-mail translation scaffolds during updates', function (): void {
    $basePath = makePublishBasePath('missing-versioned-account-mail-translations');
    $englishTarget = $basePath.'/lang/en/account-mail.php';
    $germanTarget = $basePath.'/lang/de/account-mail.php';

    mkdir($basePath, 0777, true);

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    expect(file_exists($englishTarget))->toBeFalse()
        ->and(file_exists($germanTarget))->toBeFalse();
});

it('updates explicitly versioned existing application scaffolds without a previous baseline', function (): void {
    $basePath = makePublishBasePath('existing-versioned-scaffold');

    foreach (versionedUpdateScaffoldPaths() as $relativePath) {
        if (in_array($relativePath, [...criticalVersionedUpdateScaffoldPaths(), 'config/pwa.php', 'public/logo.png', 'public/manifest.json', 'public/offline.html', 'public/sw.js'], true)) {
            continue;
        }

        $target = $basePath.'/'.$relativePath;

        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0777, true);
        }

        file_put_contents($target, "old versioned scaffold: {$relativePath}\n");
    }

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $manifest = json_decode((string) file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json'), true, 512, JSON_THROW_ON_ERROR);

    foreach (versionedUpdateScaffoldPaths() as $relativePath) {
        if (in_array($relativePath, [...criticalVersionedUpdateScaffoldPaths(), 'config/pwa.php', 'public/logo.png', 'public/manifest.json', 'public/offline.html', 'public/sw.js'], true)) {
            continue;
        }

        $target = $basePath.'/'.$relativePath;

        expect(file_get_contents($target))
            ->not->toContain("old versioned scaffold: {$relativePath}")
            ->and(glob($basePath.'/.core-panel-backups/*/'.$relativePath))
            ->not->toBeEmpty("Expected {$relativePath} to be backed up before update.")
            ->and($manifest['files'][$relativePath] ?? null)
            ->toBeArray("Expected {$relativePath} to be recorded in the scaffold manifest.");
    }
});

it('merges the https url hook into an existing app service provider during updates', function (): void {
    $basePath = makePublishBasePath('merge-app-service-provider');
    $target = $basePath.'/app/Providers/AppServiceProvider.php';

    mkdir(dirname($target), 0777, true);
    file_put_contents($target, <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        //
    }
}
PHP);

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $contents = (string) file_get_contents($target);

    expect($contents)->toContain('use Illuminate\Support\Facades\URL;')
        ->and($contents)->toContain("if (parse_url((string) config('app.url'), PHP_URL_SCHEME) === 'https') {")
        ->and($contents)->toContain("URL::forceScheme('https');")
        ->and(glob($basePath.'/.core-panel-backups/*/app/Providers/AppServiceProvider.php'))
        ->not->toBeEmpty();

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    expect(substr_count((string) file_get_contents($target), "URL::forceScheme('https');"))->toBe(1);
});

it('does not overwrite critical versioned scaffolds without a previous baseline during updates', function (): void {
    $basePath = makePublishBasePath('critical-versioned-scaffold');
    $criticalFiles = [
        'bootstrap/app.php' => "<?php\n\nreturn 'host bootstrap';\n",
        'routes/web.php' => "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\nRoute::get('/host-routes', fn () => 'host');\n",
        'routes/console.php' => "<?php\n\nreturn 'host console';\n",
        '.env.example' => "APP_NAME=\"Host App\"\n",
        '.docker/bin/php-entrypoint.sh' => "#!/usr/bin/env sh\n\necho host-php-entrypoint\n",
        '.docker/bin/start-dev-app.sh' => "#!/usr/bin/env sh\n\necho host-start-dev-app\n",
        '.docker/php/entrypoint.sh' => "#!/usr/bin/env sh\n\necho host-entrypoint\n",
        '.docker/php/opcache.ini' => "opcache.enable=0\n",
        '.docker/php-fpm/zz-docker.conf' => "[www]\n; host override\n",
        '.dockerignore' => ".env\nnode_modules\n",
        'docker-compose.yml' => "services:\n  app:\n    image: host-app\n",
        'updater/main.go' => "package main\n\nfunc main() {}\n",
    ];

    foreach ($criticalFiles as $relativePath => $contents) {
        $target = $basePath.'/'.$relativePath;

        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0777, true);
        }

        file_put_contents($target, str_ends_with($contents, "\n") ? $contents : $contents."\n");
    }

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $manifest = file_exists($basePath.'/storage/app/core-panel/scaffolds.json')
        ? json_decode((string) file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json'), true, 512, JSON_THROW_ON_ERROR)
        : ['files' => []];

    foreach ($criticalFiles as $relativePath => $contents) {
        expect(file_get_contents($basePath.'/'.$relativePath))->toBe($contents)
            ->and(glob($basePath.'/.core-panel-backups/*/'.$relativePath))->toBe([])
            ->and($manifest['files'][$relativePath] ?? null)->toBeNull();
    }
});

it('adopts unchanged critical versioned scaffolds into the manifest during updates', function (): void {
    $basePath = makePublishBasePath('adopt-critical-versioned-scaffold');
    $criticalFiles = [
        'bootstrap/app.php' => (string) file_get_contents(__DIR__.'/../../stubs/bootstrap/app.php'),
        'routes/web.php' => (string) file_get_contents(__DIR__.'/../../stubs/routes/web.php'),
        'routes/console.php' => (string) file_get_contents(__DIR__.'/../../stubs/routes/console.php'),
        '.docker/bin/start-dev-app.sh' => (string) file_get_contents(__DIR__.'/../../stubs/.docker/bin/start-dev-app.sh'),
        '.docker/php/opcache.ini' => (string) file_get_contents(__DIR__.'/../../stubs/.docker/php/opcache.ini'),
        '.docker/php-fpm/zz-docker.conf' => (string) file_get_contents(__DIR__.'/../../stubs/.docker/php-fpm/zz-docker.conf'),
        '.dockerignore' => (string) file_get_contents(__DIR__.'/../../stubs/.dockerignore'),
        'updater/main.go' => (string) file_get_contents(__DIR__.'/../../stubs/updater/main.go'),
    ];

    foreach ($criticalFiles as $relativePath => $contents) {
        $target = $basePath.'/'.$relativePath;

        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0777, true);
        }

        file_put_contents($target, str_ends_with($contents, "\n") ? $contents : $contents."\n");
    }

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $manifest = json_decode((string) file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json'), true, 512, JSON_THROW_ON_ERROR);

    foreach ($criticalFiles as $relativePath => $contents) {
        $manifestEntry = $manifest['files'][$relativePath] ?? null;

        expect(file_get_contents($basePath.'/'.$relativePath))->toBe($contents)
            ->and(glob($basePath.'/.core-panel-backups/*/'.$relativePath))->toBe([])
            ->and($manifestEntry)->toBeArray()
            ->and(is_string($manifestEntry['snapshot'] ?? null))->toBeTrue()
            ->and(file_get_contents($basePath.'/'.$manifestEntry['snapshot']))->toBe($contents);
    }
});

it('updates known legacy critical versioned scaffolds without a previous baseline', function (): void {
    $basePath = makePublishBasePath('legacy-critical-versioned-scaffold');
    $criticalFiles = [
        'bootstrap/app.php' => legacyCriticalBootstrapAppContents(),
        'routes/console.php' => legacyCriticalRoutesConsoleContents(),
        '.dockerignore' => legacyCriticalDockerignoreContents(),
    ];

    foreach ($criticalFiles as $relativePath => $contents) {
        $target = $basePath.'/'.$relativePath;

        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0777, true);
        }

        file_put_contents($target, str_ends_with($contents, "\n") ? $contents : $contents."\n");
    }

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $manifest = json_decode((string) file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json'), true, 512, JSON_THROW_ON_ERROR);

    expect(file_get_contents($basePath.'/bootstrap/app.php'))->toContain('use CorePanel\Http\Middleware\AllowBlobImageCsp;')
        ->and(file_get_contents($basePath.'/bootstrap/app.php'))->toContain('AllowBlobImageCsp::class')
        ->and(glob($basePath.'/.core-panel-backups/*/bootstrap/app.php'))->not->toBeEmpty()
        ->and($manifest['files']['bootstrap/app.php'] ?? null)->toBeArray()
        ->and(file_get_contents($basePath.'/routes/console.php'))->not->toContain('Schedule::')
        ->and(glob($basePath.'/.core-panel-backups/*/routes/console.php'))->not->toBeEmpty()
        ->and($manifest['files']['routes/console.php'] ?? null)->toBeArray()
        ->and(file_get_contents($basePath.'/.dockerignore'))->toContain('.gitea')
        ->and(file_get_contents($basePath.'/.dockerignore'))->toContain('storage/*.key')
        ->and(file_get_contents($basePath.'/.dockerignore'))->toContain('!.env.example')
        ->and(glob($basePath.'/.core-panel-backups/*/.dockerignore'))->not->toBeEmpty()
        ->and($manifest['files']['.dockerignore'] ?? null)->toBeArray();
});

it('updates known legacy web route scaffolds without a previous baseline', function (): void {
    $currentContents = (string) file_get_contents(__DIR__.'/../../stubs/routes/web.php');

    foreach (legacyCriticalRoutesWebContents() as $index => $legacyRoutesWebContents) {
        $basePath = makePublishBasePath('legacy-critical-routes-web-'.$index);
        $target = $basePath.'/routes/web.php';

        mkdir(dirname($target), 0777, true);
        file_put_contents($target, $legacyRoutesWebContents."\n");

        $this->artisan('core-panel:update', [
            '--base-path' => $basePath,
        ])->assertExitCode(0);

        $manifest = json_decode((string) file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json'), true, 512, JSON_THROW_ON_ERROR);

        expect((string) file_get_contents($target))->toBe($currentContents)
            ->and(glob($basePath.'/.core-panel-backups/*/routes/web.php'))->not->toBeEmpty()
            ->and($manifest['files']['routes/web.php'] ?? null)->toBeArray();
    }
});

it('updates additional pre-manifest critical scaffolds without a previous baseline', function (): void {
    $criticalFiles = [
        '.env.example' => [
            'legacy' => legacyCriticalEnvExampleContents(),
            'current' => (string) file_get_contents(__DIR__.'/../../stubs/.env.example'),
        ],
        'routes/console.php' => [
            'legacy' => legacyCriticalOldRoutesConsoleContents(),
            'current' => (string) file_get_contents(__DIR__.'/../../stubs/routes/console.php'),
        ],
    ];

    foreach ($criticalFiles as $relativePath => $file) {
        $basePath = makePublishBasePath('additional-legacy-critical-'.str_replace(['/', '.'], '-', $relativePath));
        $target = $basePath.'/'.$relativePath;

        mkdir(dirname($target), 0777, true);
        file_put_contents($target, str_ends_with($file['legacy'], "\n") ? $file['legacy'] : $file['legacy']."\n");

        $this->artisan('core-panel:update', [
            '--base-path' => $basePath,
        ])->assertExitCode(0);

        $manifest = json_decode((string) file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json'), true, 512, JSON_THROW_ON_ERROR);
        $hasBackup = glob($basePath.'/.core-panel-backups/*/'.$relativePath) !== [];

        expect((string) file_get_contents($target))->toBe($file['current'])
            ->and($hasBackup)->toBe($file['current'] !== $file['legacy']."\n")
            ->and($manifest['files'][$relativePath] ?? null)->toBeArray();
    }
});

it('keeps unmanaged customized docker scaffolds untouched during updates', function (): void {
    $basePath = makePublishBasePath('update-preserved-docker-scaffolds');
    $preservedFiles = [
        '.docker/bin/php-entrypoint.sh' => "#!/usr/bin/env sh\n\necho preserved-php-entrypoint\n",
        '.docker/nginx/default.conf' => "server { return 200 'preserved nginx'; }\n",
        '.docker/php/banner.sh' => "#!/usr/bin/env sh\n\necho preserved-banner\n",
        '.docker/php/entrypoint.sh' => "#!/usr/bin/env sh\n\necho preserved-entrypoint\n",
        '.docker/php/php.ini' => "memory_limit=768M\n",
        'Dockerfile' => "FROM busybox:1.36\n",
        'docker-compose.dev.yml' => "services:\n  app:\n    image: preserved-dev\n",
        'docker-compose.portainer.yml' => "services:\n  app:\n    image: preserved-portainer\n",
        'docker-compose.prod.yml' => "services:\n  app:\n    image: preserved-prod\n",
        'docker-compose.registry.yml' => "services:\n  app:\n    image: preserved-registry\n",
        'docker-compose.yml' => "services:\n  app:\n    image: preserved-base\n",
    ];

    foreach ($preservedFiles as $relativePath => $contents) {
        $target = $basePath.'/'.$relativePath;

        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0777, true);
        }

        file_put_contents($target, $contents);
    }

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $manifest = file_exists($basePath.'/storage/app/core-panel/scaffolds.json')
        ? json_decode((string) file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json'), true, 512, JSON_THROW_ON_ERROR)
        : ['files' => []];

    foreach ($preservedFiles as $relativePath => $contents) {
        expect(file_get_contents($basePath.'/'.$relativePath))->toBe($contents)
            ->and(glob($basePath.'/.core-panel-backups/*/'.$relativePath))->toBe([])
            ->and($manifest['files'][$relativePath] ?? null)->toBeNull();
    }
});

it('merges system update environment mappings into preserved production compose scaffolds during updates', function (): void {
    $basePath = makePublishBasePath('merge-preserved-compose-environment');
    mkdir($basePath, 0777, true);

    $composeFiles = [
        'docker-compose.prod.yml' => <<<'YAML'
"x-php-environment": &custom-production-php # host-specific shared environment
  APP_NAME: ${APP_NAME:-Custom Production}
  "SYSTEM_UPDATES_STATUS_STORE": ${SYSTEM_UPDATES_STATUS_STORE:-redis}
  SYSTEM_UPDATES_TIMEOUT: ${SYSTEM_UPDATES_TIMEOUT:-42}
  CUSTOM_HOST_SETTING: preserved-production

services:
  app:
    environment: *custom-production-php
    labels:
      custom.host: preserved
YAML,
        'docker-compose.portainer.yml' => <<<'YAML'
'x-php-environment': &portainer-runtime # retained inline comment
  APP_NAME: ${APP_NAME:-Custom Portainer}
  'SYSTEM_UPDATES_RESTART_DELAY_SECONDS': ${SYSTEM_UPDATES_RESTART_DELAY_SECONDS:-9}
  SYSTEM_UPDATES_TIMEOUT: ${SYSTEM_UPDATES_TIMEOUT:-84}
  CUSTOM_HOST_SETTING: preserved-portainer

services:
  app:
    environment: *portainer-runtime
YAML,
    ];

    foreach ($composeFiles as $relativePath => $contents) {
        file_put_contents($basePath.'/'.$relativePath, $contents."\n");
    }

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $productionContents = (string) file_get_contents($basePath.'/docker-compose.prod.yml');
    $portainerContents = (string) file_get_contents($basePath.'/docker-compose.portainer.yml');
    $productionBackups = glob($basePath.'/.core-panel-backups/*/docker-compose.prod.yml');
    $portainerBackups = glob($basePath.'/.core-panel-backups/*/docker-compose.portainer.yml');
    $manifest = json_decode((string) file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json'), true, 512, JSON_THROW_ON_ERROR);

    $automaticUpdateEnvironmentMappings = [
        'SYSTEM_UPDATES_AUTOMATIC_GRACE_MINUTES: ${SYSTEM_UPDATES_AUTOMATIC_GRACE_MINUTES:-15}',
        'SYSTEM_UPDATES_AUTOMATIC_INTERVAL: ${SYSTEM_UPDATES_AUTOMATIC_INTERVAL:-daily}',
        'SYSTEM_UPDATES_AUTOMATIC_MAINTENANCE_WINDOW_ENABLED: ${SYSTEM_UPDATES_AUTOMATIC_MAINTENANCE_WINDOW_ENABLED:-true}',
        'SYSTEM_UPDATES_AUTOMATIC_MODE: ${SYSTEM_UPDATES_AUTOMATIC_MODE:-install}',
        'SYSTEM_UPDATES_AUTOMATIC_TIME: ${SYSTEM_UPDATES_AUTOMATIC_TIME:-${SYSTEM_UPDATES_AUTOMATIC_WINDOW_START:-02:00}}',
        'SYSTEM_UPDATES_AUTOMATIC_WEEKDAY: ${SYSTEM_UPDATES_AUTOMATIC_WEEKDAY:-monday}',
    ];

    expect($productionContents)
        ->toContain('"x-php-environment": &custom-production-php # host-specific shared environment')
        ->toContain('environment: *custom-production-php')
        ->toContain('CUSTOM_HOST_SETTING: preserved-production')
        ->toContain('custom.host: preserved')
        ->toContain('SYSTEM_UPDATES_RESTART_DELAY_SECONDS: ${SYSTEM_UPDATES_RESTART_DELAY_SECONDS:-3}')
        ->toContain('"SYSTEM_UPDATES_STATUS_STORE": ${SYSTEM_UPDATES_STATUS_STORE:-redis}')
        ->and(substr_count($productionContents, '  SYSTEM_UPDATES_RESTART_DELAY_SECONDS:'))->toBe(1)
        ->and(preg_match_all("/^\\s+(?:SYSTEM_UPDATES_STATUS_STORE|\"SYSTEM_UPDATES_STATUS_STORE\"|'SYSTEM_UPDATES_STATUS_STORE')\\s*:/m", $productionContents))->toBe(1)
        ->and($productionBackups)->not->toBeEmpty()
        ->and(file_get_contents($productionBackups[0]))->toBe($composeFiles['docker-compose.prod.yml']."\n")
        ->and($portainerContents)
        ->toContain("'x-php-environment': &portainer-runtime # retained inline comment")
        ->toContain('environment: *portainer-runtime')
        ->toContain('CUSTOM_HOST_SETTING: preserved-portainer')
        ->toContain("'SYSTEM_UPDATES_RESTART_DELAY_SECONDS': \${SYSTEM_UPDATES_RESTART_DELAY_SECONDS:-9}")
        ->toContain('SYSTEM_UPDATES_STATUS_STORE: ${SYSTEM_UPDATES_STATUS_STORE:-file}')
        ->and(preg_match_all("/^\\s+(?:SYSTEM_UPDATES_RESTART_DELAY_SECONDS|\"SYSTEM_UPDATES_RESTART_DELAY_SECONDS\"|'SYSTEM_UPDATES_RESTART_DELAY_SECONDS')\\s*:/m", $portainerContents))->toBe(1)
        ->and(substr_count($portainerContents, '  SYSTEM_UPDATES_STATUS_STORE:'))->toBe(1)
        ->and($portainerBackups)->not->toBeEmpty()
        ->and(file_get_contents($portainerBackups[0]))->toBe($composeFiles['docker-compose.portainer.yml']."\n");

    foreach ([$productionContents, $portainerContents] as $contents) {
        foreach ($automaticUpdateEnvironmentMappings as $mapping) {
            expect($contents)->toContain($mapping)
                ->and(substr_count($contents, '  '.$mapping))->toBe(1);
        }
    }

    foreach (array_keys($composeFiles) as $relativePath) {
        $manifestEntry = $manifest['files'][$relativePath] ?? null;
        $sourceContents = (string) file_get_contents(__DIR__.'/../../stubs/'.$relativePath);

        expect($manifestEntry)->toBeArray()
            ->and($manifestEntry['destination_hash'] ?? null)->toBe(hash('sha256', (string) file_get_contents($basePath.'/'.$relativePath)))
            ->and($manifestEntry['source_hash'] ?? null)->toBe(hash('sha256', $sourceContents))
            ->and($manifestEntry['snapshot'] ?? null)->toBeString()
            ->and(file_get_contents($basePath.'/'.$manifestEntry['snapshot']))->toBe($sourceContents);
    }

    $manifestContents = (string) file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json');

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    expect(file_get_contents($basePath.'/docker-compose.prod.yml'))->toBe($productionContents)
        ->and(file_get_contents($basePath.'/docker-compose.portainer.yml'))->toBe($portainerContents)
        ->and(glob($basePath.'/.core-panel-backups/*/docker-compose.prod.yml'))->toBe($productionBackups)
        ->and(glob($basePath.'/.core-panel-backups/*/docker-compose.portainer.yml'))->toBe($portainerBackups)
        ->and(file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json'))->toBe($manifestContents);
});

it('does not overwrite customized legacy critical scaffolds without a previous baseline during updates', function (): void {
    $basePath = makePublishBasePath('customized-legacy-critical-versioned-scaffold');
    $criticalFiles = [
        'bootstrap/app.php' => <<<'PHP'
<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\TrackUserPresence;
use CorePanel\Http\Middleware\ApplyCorePanelRuntimeSettings;
use CorePanel\Http\Middleware\CheckPermission;
use CorePanel\Http\Middleware\ResolveCorePanelLocale;
use CorePanel\Http\Middleware\SecurityHeaders;
use CorePanel\Http\Middleware\ShareLocaleDataWithInertia;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

/** @var callable(): array{web:string, api:?string, commands:?string, health:string} $corePanelRoutingPaths */
$corePanelRoutingPaths = static function (): array {
    $basePath = dirname(__DIR__);

    $apiRoutes = $basePath.'/routes/api.php';
    $centralRoutes = $basePath.'/routes/central.php';
    $consoleRoutes = $basePath.'/routes/console.php';

    return [
        'web' => file_exists($centralRoutes) ? $centralRoutes : $basePath.'/routes/web.php',
        'api' => file_exists($apiRoutes) ? $apiRoutes : null,
        'commands' => file_exists($consoleRoutes) ? $consoleRoutes : null,
        'health' => '/up',
    ];
};

['web' => $webRoutes, 'api' => $apiRoutes, 'commands' => $consoleRoutes, 'health' => $healthRoute] = $corePanelRoutingPaths();

$tenantSessionCookieMiddlewareClass = 'CorePanelTenancy\\Http\\Middleware\\SetTenantAwareSessionCookie';
$tenantSessionCookieMiddleware = class_exists($tenantSessionCookieMiddlewareClass)
    ? [$tenantSessionCookieMiddlewareClass]
    : [];

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: $webRoutes,
        api: $apiRoutes,
        commands: $consoleRoutes,
        health: $healthRoute,
    )
    ->withMiddleware(function (Middleware $middleware) use ($tenantSessionCookieMiddleware): void {
        $middleware->redirectUsersTo(static fn (Request $request): string => '/'.trim((string) config('core-panel.route_prefix', 'admin'), '/'));
        $middleware->redirectGuestsTo(static fn (Request $request): ?string => $request->expectsJson() ? null : '/login');
        $middleware->alias([
            'check.permission' => CheckPermission::class,
        ]);
        $middleware->group('universal', []);

        $middleware->web(prepend: $tenantSessionCookieMiddleware);
        $middleware->web(append: [
            ApplyCorePanelRuntimeSettings::class,
            SecurityHeaders::class,
            ResolveCorePanelLocale::class,
            ShareLocaleDataWithInertia::class,
            TrackUserPresence::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\CustomAuditMiddleware::class,
        ]);

        $middleware->api(append: [
            ApplyCorePanelRuntimeSettings::class,
            SecurityHeaders::class,
            ResolveCorePanelLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(static fn (\Throwable $throwable): null => null);
    })
    ->create();
PHP,
        'routes/web.php' => <<<'PHP'
<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('/', config('core-panel.route_prefix', 'admin'));
    Route::get('/generator-preview', fn () => 'host');
});
PHP,
        'routes/console.php' => <<<'PHP'
<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

if ((bool) config('core-panel.horizon.enabled', true) && app()->bound('command.horizon.snapshot')) {
    Schedule::command('horizon:snapshot')->everyFiveMinutes();
}

if ((bool) config('database-backups.enabled', config('core-panel.administration.database_backups.enabled', true))) {
    Schedule::command('database-backups:auto')
        ->everyMinute()
        ->withoutOverlapping(60);
}

if ((bool) config('core-panel.administration.system_updates.enabled', true)) {
    Schedule::command('system-updates:auto')
        ->everyMinute()
        ->withoutOverlapping(20)
        ->onOneServer();
}

Artisan::command('host:custom', function () {
    $this->comment('host');
});

Schedule::command('host:custom')->hourly();
PHP,
        '.dockerignore' => <<<'TEXT'
.agents
.ai
.claude
.codex
.git
.github
.idea
.vscode
node_modules
vendor
storage/logs
storage/framework/cache/*
storage/framework/sessions/*
storage/framework/testing/*
storage/framework/views/*
bootstrap/cache/*.php
.env
AGENTS.md
boost.json
docker-compose.*
Dockerfile
custom-host-artifacts
TEXT,
    ];

    foreach ($criticalFiles as $relativePath => $contents) {
        $target = $basePath.'/'.$relativePath;

        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0777, true);
        }

        file_put_contents($target, str_ends_with($contents, "\n") ? $contents : $contents."\n");
    }

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $manifest = file_exists($basePath.'/storage/app/core-panel/scaffolds.json')
        ? json_decode((string) file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json'), true, 512, JSON_THROW_ON_ERROR)
        : ['files' => []];

    expect(file_get_contents($basePath.'/bootstrap/app.php'))->toContain('CustomAuditMiddleware::class')
        ->and(file_get_contents($basePath.'/bootstrap/app.php'))->not->toContain('AllowBlobImageCsp::class')
        ->and(glob($basePath.'/.core-panel-backups/*/bootstrap/app.php'))->toBeEmpty()
        ->and($manifest['files']['bootstrap/app.php'] ?? null)->toBeNull()
        ->and(file_get_contents($basePath.'/routes/web.php'))->toContain('/generator-preview')
        ->and(file_get_contents($basePath.'/routes/web.php'))->not->toContain("Route::get('/', function () {")
        ->and(glob($basePath.'/.core-panel-backups/*/routes/web.php'))->toBeEmpty()
        ->and($manifest['files']['routes/web.php'] ?? null)->toBeNull()
        ->and(file_get_contents($basePath.'/routes/console.php'))->toContain("Artisan::command('host:custom'")
        ->and(file_get_contents($basePath.'/routes/console.php'))->toContain("Schedule::command('host:custom')->hourly()")
        ->and(file_get_contents($basePath.'/routes/console.php'))->not->toContain("Schedule::command('horizon:snapshot')")
        ->and(file_get_contents($basePath.'/routes/console.php'))->not->toContain("Schedule::command('database-backups:auto')")
        ->and(file_get_contents($basePath.'/routes/console.php'))->not->toContain("Schedule::command('system-updates:auto')")
        ->and(glob($basePath.'/.core-panel-backups/*/routes/console.php'))->not->toBeEmpty()
        ->and($manifest['files']['routes/console.php'] ?? null)->toBeNull()
        ->and(file_get_contents($basePath.'/.dockerignore'))->toContain('custom-host-artifacts')
        ->and(file_get_contents($basePath.'/.dockerignore'))->not->toContain('.gitea')
        ->and(glob($basePath.'/.core-panel-backups/*/.dockerignore'))->toBeEmpty()
        ->and($manifest['files']['.dockerignore'] ?? null)->toBeNull();
});

it('removes earlier CorePanel schedule variants from a preserved console route file', function (): void {
    $basePath = makePublishBasePath('migrate-earlier-core-panel-schedules');
    $target = $basePath.'/routes/console.php';
    $legacyContents = <<<'PHP'
<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

if ((bool) config('core-panel.administration.database_backups.enabled', true)) {
    Schedule::command('database-backups:auto')
        ->everyMinute()
        ->withoutOverlapping(60);
}

if ((bool) config('core-panel.administration.system_updates.automatic.enabled', false)) {
    Schedule::command('system-updates:auto')->everyFiveMinutes();
}

if ((bool) config('system-updates.automatic.enabled', config('core-panel.administration.system_updates.automatic.enabled', false))) {
    Schedule::command('system-updates:auto')->everyFiveMinutes();
}

Artisan::command('host:custom', function () {
    $this->comment('host');
});
PHP;

    mkdir(dirname($target), 0777, true);
    file_put_contents($target, str_replace("\n", "\r\n", $legacyContents)."\r\n");

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $contents = (string) file_get_contents($target);
    $manifest = file_exists($basePath.'/storage/app/core-panel/scaffolds.json')
        ? json_decode((string) file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json'), true, 512, JSON_THROW_ON_ERROR)
        : ['files' => []];

    expect($contents)->toContain("Artisan::command('host:custom'")
        ->and($contents)->not->toContain('database-backups:auto')
        ->and($contents)->not->toContain('system-updates:auto')
        ->and($contents)->not->toContain('use Illuminate\Support\Facades\Schedule;')
        ->and(glob($basePath.'/.core-panel-backups/*/routes/console.php'))->not->toBeEmpty()
        ->and($manifest['files']['routes/console.php'] ?? null)->toBeNull();
});

it('updates existing page-users translation scaffolds without a previous baseline', function (): void {
    $basePath = makePublishBasePath('existing-versioned-page-users-translations');
    $englishTarget = $basePath.'/lang/en/page-users.php';
    $germanTarget = $basePath.'/lang/de/page-users.php';

    mkdir(dirname($englishTarget), 0777, true);
    mkdir(dirname($germanTarget), 0777, true);
    file_put_contents($englishTarget, <<<'PHP'
<?php

declare(strict_types=1);

return [
    'users' => 'Users',
];
PHP);
    file_put_contents($germanTarget, <<<'PHP'
<?php

declare(strict_types=1);

return [
    'users' => 'Benutzer',
];
PHP);

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $manifest = json_decode((string) file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json'), true, 512, JSON_THROW_ON_ERROR);

    expect(file_get_contents($englishTarget))->toContain("'groups' => 'Groups'")
        ->and(file_get_contents($germanTarget))->toContain("'groups' => 'Gruppen'")
        ->and(glob($basePath.'/.core-panel-backups/*/lang/en/page-users.php'))
        ->not->toBeEmpty()
        ->and(glob($basePath.'/.core-panel-backups/*/lang/de/page-users.php'))
        ->not->toBeEmpty()
        ->and($manifest['files']['lang/en/page-users.php'] ?? null)
        ->toBeArray()
        ->and($manifest['files']['lang/de/page-users.php'] ?? null)
        ->toBeArray();
});

it('does not create extra backups for current versioned scaffolds that are already up to date', function (): void {
    $basePath = makePublishBasePath('current-versioned-scaffold-repeat');

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $manifest = (string) file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json');

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    foreach (versionedUpdateScaffoldPaths() as $relativePath) {
        expect(glob($basePath.'/.core-panel-backups/*/'.$relativePath))
            ->toBeEmpty("Expected {$relativePath} not to be backed up when the package version is unchanged.");
    }

    expect((string) file_get_contents($basePath.'/storage/app/core-panel/scaffolds.json'))->toBe($manifest);
});

it('synchronizes missing environment defaults during update', function (): void {
    $basePath = makePublishBasePath('env-sync');

    mkdir($basePath, 0777, true);
    file_put_contents($basePath.'/.env', implode(PHP_EOL, [
        'APP_NAME=CorePanel',
        'OCTANE_HOST=0.0.0.0',
        'OCTANE_HTTPS=false',
        'OCTANE_PORT=8000',
        'OCTANE_SERVER=frankenphp',
        'SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler,nginx',
        'SYSTEM_UPDATES_AUTOMATIC_WINDOW_START=03:00',
        '',
    ]));

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $contents = file_get_contents($basePath.'/.env');

    expect($contents)->toContain("APP_NAME=CorePanel\n")
        ->and($contents)->toContain("LOG_CHANNEL=daily\n")
        ->and($contents)->toContain("SYSTEM_UPDATES_AUTOMATIC_TIME=03:00\n")
        ->and($contents)->toContain("SYSTEM_UPDATES_AUTOMATIC_WINDOW_START=03:00\n")
        ->and($contents)->toContain("SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler\n")
        ->and($contents)->not->toContain('SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler,nginx')
        ->and($contents)->not->toContain('OCTANE_')
        ->and(file_get_contents($basePath.'/.env.backup'))->toContain('OCTANE_SERVER=frankenphp')
        ->and(file_get_contents($basePath.'/.env.backup'))->toContain('SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler,nginx');
});

it('preserves a customized published app version metadata file during update', function (): void {
    $basePath = makePublishBasePath('app-version-update');

    mkdir($basePath.'/config', 0777, true);
    file_put_contents($basePath.'/.env', "APP_NAME=CorePanel\n");
    $customizedVersion = [
        'release_version' => '0.0.1',
        'display_version' => '0.0.1 (deadbee)',
        'image_version' => '0.0.1-deadbee',
        'commit' => 'deadbee',
        'commit_date' => '2026-01-01T00:00:00+00:00',
    ];
    file_put_contents($basePath.'/config/app-version.json', json_encode(
        $customizedVersion,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
    ).PHP_EOL);

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $publishedVersion = json_decode((string) file_get_contents($basePath.'/config/app-version.json'), true, 512, JSON_THROW_ON_ERROR);

    expect($publishedVersion)->toBe($customizedVersion);
});

it('preserves a customized published app version metadata file during force update', function (): void {
    $basePath = makePublishBasePath('app-version-force-update');

    mkdir($basePath.'/config', 0777, true);
    mkdir($basePath.'/resources/js', 0777, true);
    file_put_contents($basePath.'/.env', "APP_NAME=CorePanel\n");
    file_put_contents($basePath.'/resources/js/app.ts', "console.log('local app');\n");
    $customizedVersion = [
        'release_version' => '9.9.9',
        'display_version' => '9.9.9 (cafebabe)',
        'image_version' => '9.9.9-cafebabe',
        'commit' => 'cafebabe',
        'commit_date' => '2026-02-02T00:00:00+00:00',
    ];
    file_put_contents($basePath.'/config/app-version.json', json_encode(
        $customizedVersion,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
    ).PHP_EOL);

    $this->artisan('core-panel:update', [
        '--force' => true,
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $publishedVersion = json_decode((string) file_get_contents($basePath.'/config/app-version.json'), true, 512, JSON_THROW_ON_ERROR);
    expect($publishedVersion)->toBe($customizedVersion);
});

it('preserves a customized published app version metadata file during force update when the host marks it as application-managed', function (): void {
    $basePath = makePublishBasePath('app-version-force-update-managed');

    mkdir($basePath.'/config', 0777, true);
    $customizedVersion = [
        'managed_by_application' => true,
        'release_version' => '9.9.9',
        'display_version' => '9.9.9 (cafebabe)',
        'image_version' => '9.9.9-cafebabe',
        'commit' => 'cafebabe',
        'commit_date' => '2026-02-02T00:00:00+00:00',
    ];
    file_put_contents($basePath.'/.env', "APP_NAME=CorePanel\n");
    file_put_contents($basePath.'/config/app-version.json', json_encode(
        $customizedVersion,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
    ).PHP_EOL);

    $this->artisan('core-panel:update', [
        '--force' => true,
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $publishedVersion = json_decode((string) file_get_contents($basePath.'/config/app-version.json'), true, 512, JSON_THROW_ON_ERROR);

    expect($publishedVersion)->toBe($customizedVersion);
});

it('does not create domain-grouped migration directories during update scaffolding without a previous version baseline', function (): void {
    $basePath = makePublishBasePath('domain-migrations');

    mkdir($basePath.'/database/migrations', 0777, true);
    file_put_contents($basePath.'/.env', "APP_NAME=CorePanel\n");
    file_put_contents($basePath.'/database/migrations/0001_01_01_000000_create_users_table.php', 'legacy migration');

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    expect(file_exists($basePath.'/database/migrations/users/0001_01_01_000000_create_users_table.php'))->toBeFalse()
        ->and(file_exists($basePath.'/database/migrations/auth/2016_06_01_000001_create_oauth_auth_codes_table.php'))->toBeFalse()
        ->and(file_exists($basePath.'/database/migrations/0001_01_01_000000_create_users_table.php'))->toBeTrue();
});

it('skips automatic migrations for external base-path updates', function (): void {
    $basePath = makePublishBasePath('skip-migrations');

    mkdir($basePath, 0777, true);
    file_put_contents($basePath.'/.env', "APP_NAME=CorePanel\n");

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])
        ->expectsOutputToContain('Skipping automatic migrations for external base-path updates.')
        ->assertExitCode(0);
});

it('does not create optional publish targets during update when they were never published', function (): void {
    $basePath = makePublishBasePath('skip-unpublished');

    mkdir($basePath, 0777, true);
    file_put_contents($basePath.'/.env', "APP_NAME=CorePanel\n");

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    expect(file_exists($basePath.'/config/core-panel.php'))->toBeFalse()
        ->and(readManifest($basePath))->not->toContain('core-panel-config');
});

it('requires breaking-change mode before relocating legacy domain migrations', function (): void {
    $basePath = makePublishBasePath('migration-relocation-guard');
    $relativePath = 'database/migrations/auth/2016_06_01_000001_create_oauth_auth_codes_table.php';
    $target = $basePath.'/'.$relativePath;
    $source = __DIR__.'/../../database/migrations/'.basename($target);

    mkdir(dirname($target), 0777, true);
    file_put_contents($target, (string) file_get_contents($source));

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
    ])->assertExitCode(1);

    expect(file_get_contents($target))->toBe(file_get_contents($source));
});

it('backs up and removes legacy domain migrations in breaking-change mode', function (): void {
    $basePath = makePublishBasePath('migration-relocation');
    $relativePath = 'database/migrations/auth/2016_06_01_000001_create_oauth_auth_codes_table.php';
    $target = $basePath.'/'.$relativePath;
    $source = __DIR__.'/../../database/migrations/'.basename($target);

    mkdir(dirname($target), 0777, true);
    file_put_contents($target, (string) file_get_contents($source));

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
        '--breaking-changes' => true,
    ])->assertExitCode(0);

    $backups = glob($basePath.'/.core-panel-backups/*/'.$relativePath);

    expect(file_exists($target))->toBeFalse()
        ->and($backups)->not->toBeFalse()
        ->and($backups)->toHaveCount(1)
        ->and(file_get_contents($backups[0]))->toBe(file_get_contents($source));
});

it('backs up customized managed domain migrations and removes their scaffold manifest entries', function (): void {
    $basePath = makePublishBasePath('managed-migration-relocation');
    $relativePath = 'database/migrations/auth/2016_06_01_000001_create_oauth_auth_codes_table.php';
    $target = $basePath.'/'.$relativePath;
    $customized = "<?php\n// customized managed migration\n";
    $manifestPath = $basePath.'/storage/app/core-panel/scaffolds.json';

    mkdir(dirname($target), 0777, true);
    mkdir(dirname($manifestPath), 0777, true);
    file_put_contents($target, $customized);
    file_put_contents($manifestPath, json_encode([
        '_meta' => ['package_version' => '1.5.0'],
        'files' => [
            $relativePath => [
                'source_hash' => hash('sha256', 'legacy'),
                'destination_hash' => hash('sha256', $customized),
                'package_version' => '1.5.0',
                'snapshot' => 'storage/app/core-panel/scaffolds/legacy',
            ],
        ],
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL);

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
        '--breaking-changes' => true,
    ])->assertExitCode(0);

    $backups = glob($basePath.'/.core-panel-backups/*/'.$relativePath);
    $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);

    expect(file_exists($target))->toBeFalse()
        ->and($backups)->not->toBeFalse()
        ->and($backups)->toHaveCount(1)
        ->and(file_get_contents($backups[0]))->toBe($customized)
        ->and($manifest['files'])->not->toHaveKey($relativePath);
});

it('refuses to relocate a scaffold-managed domain migration modified after publication', function (): void {
    $basePath = makePublishBasePath('modified-managed-migration-conflict');
    $relativePath = 'database/migrations/auth/2016_06_01_000001_create_oauth_auth_codes_table.php';
    $target = $basePath.'/'.$relativePath;
    $publishedContents = (string) file_get_contents(__DIR__.'/../../database/migrations/'.basename($target));
    $customizedContents = $publishedContents."\n// host schema customization\n";
    $manifestPath = $basePath.'/storage/app/core-panel/scaffolds.json';

    mkdir(dirname($target), 0777, true);
    mkdir(dirname($manifestPath), 0777, true);
    file_put_contents($target, $customizedContents);
    file_put_contents($manifestPath, json_encode([
        '_meta' => ['package_version' => '1.5.0'],
        'files' => [
            $relativePath => [
                'source_hash' => hash('sha256', $publishedContents),
                'destination_hash' => hash('sha256', $publishedContents),
                'package_version' => '1.5.0',
                'snapshot' => 'storage/app/core-panel/scaffolds/legacy',
            ],
        ],
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL);

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
        '--breaking-changes' => true,
    ])->assertExitCode(1);

    $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);

    expect(file_get_contents($target))->toBe($customizedContents)
        ->and($manifest['files'])->toHaveKey($relativePath)
        ->and(glob($basePath.'/.core-panel-backups/*/'.$relativePath))->toBe([]);
});

it('refuses to remove customized unmanaged domain migrations', function (): void {
    $basePath = makePublishBasePath('unmanaged-migration-conflict');
    $target = $basePath.'/database/migrations/auth/2016_06_01_000001_create_oauth_auth_codes_table.php';
    $customized = "<?php\n// host-owned migration\n";

    mkdir(dirname($target), 0777, true);
    file_put_contents($target, $customized);

    $this->artisan('core-panel:update', [
        '--base-path' => $basePath,
        '--breaking-changes' => true,
    ])->assertExitCode(1);

    expect(file_get_contents($target))->toBe($customized)
        ->and(glob($basePath.'/.core-panel-backups/*/database/migrations/auth/'.basename($target)))->toBe([]);
});

it('updates a managed services scaffold while retaining the Microsoft TLS environment opt-in', function (): void {
    $basePath = makePublishBasePath('microsoft-tls-services-upgrade');
    $legacy = "<?php\n\nreturn ['microsoft' => []];\n";
    seedScaffoldManifest($basePath, 'config/services.php', $legacy);
    mkdir($basePath.'/config', 0777, true);
    file_put_contents($basePath.'/config/services.php', $legacy);
    file_put_contents($basePath.'/.env', "MICROSOFT_FORCE_TLS12=true\n");

    $this->artisan('core-panel:update', ['--base-path' => $basePath])->assertExitCode(0);

    expect(file_get_contents($basePath.'/config/services.php'))
        ->toBe(file_get_contents(__DIR__.'/../../stubs/config/services.php'))
        ->and(file_get_contents($basePath.'/.env'))
        ->toContain('MICROSOFT_FORCE_TLS12=true')
        ->not->toContain('MICROSOFT_FORCE_TLS12=false');

    $backups = glob($basePath.'/.core-panel-backups/*/config/services.php');
    expect($backups)->not->toBeEmpty()
        ->and(file_get_contents($backups[0]))->toBe($legacy);
});

it('delivers the local image inventory updater during a package upgrade', function (): void {
    $basePath = makePublishBasePath('local-image-inventory-updater');
    $legacy = "package main\n\nfunc main() {}\n";
    seedScaffoldManifest($basePath, 'updater/main.go', $legacy);
    mkdir($basePath.'/updater', 0777, true);
    file_put_contents($basePath.'/updater/main.go', $legacy);

    $this->artisan('core-panel:update', ['--base-path' => $basePath])->assertExitCode(0);

    expect(file_get_contents($basePath.'/updater/main.go'))
        ->toBe(file_get_contents(__DIR__.'/../../stubs/updater/main.go'));
    $backups = glob($basePath.'/.core-panel-backups/*/updater/main.go');
    expect($backups)->not->toBeEmpty()
        ->and(file_get_contents($backups[0]))->toBe($legacy);
});
