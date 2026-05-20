<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

$middleware = [
    ...config('core-panel.middleware', ['web', 'auth']),
    'core-panel.verified',
    'check.permission',
];

Route::middleware($middleware)
    ->prefix(config('core-panel.route_prefix', 'admin'))
    ->name('core-panel.')
    ->group(function (): void {
        Route::redirect('/', '/dashboard');

        foreach ([
            'logs.php',
            'developer.php',
            'files.php',
            'api-tokens.php',
            'oauth-clients.php',
            'settings.php',
            'roles.php',
            'user-groups.php',
            'permissions.php',
            'forms.php',
            'users.php',
        ] as $routeFile) {
            require __DIR__.'/admin/'.$routeFile;
        }
    });
