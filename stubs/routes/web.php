<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::redirect('/', config('core-panel.route_prefix', 'admin'));

$webRoutes = require __DIR__.'/web/routes.php';
$packageWebRoutesRoot = base_path('vendor/mapo-89/core-panel/routes/web');
$loadWebRouteFile = static function (string $file) use ($packageWebRoutesRoot): void {
    $hostRoutePath = __DIR__.'/web/'.$file;

    if (is_file($hostRoutePath)) {
        require $hostRoutePath;

        return;
    }

    $packageRoutePath = $packageWebRoutesRoot.'/'.$file;

    if (is_file($packageRoutePath)) {
        require $packageRoutePath;

        return;
    }

    throw new RuntimeException(sprintf(
        'Unable to locate CorePanel web route fragment [%s].',
        $file,
    ));
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
