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
