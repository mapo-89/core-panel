<?php

declare(strict_types=1);

namespace CorePanel\Http\Middleware;

use Closure;
use CorePanel\Support\Permissions\PermissionService;
use CorePanel\Support\Permissions\RoutePermissionResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class CheckPermission
{
    public function __construct(
        private PermissionService $permissions,
        private RoutePermissionResolver $resolver,
    ) {}

    public function handle(Request $request, Closure $next, ?string $permission = null): Response
    {
        $route = $request->route();

        if ($route === null) {
            return $next($request);
        }

        $resolvedPermission = $this->resolver->resolve($route, $permission);

        if ($resolvedPermission === null) {
            return $next($request);
        }

        if (! $this->permissions->permissionExists($resolvedPermission)) {
            if (app()->environment('production')) {
                throw new AccessDeniedHttpException;
            }

            Log::warning('CorePanel route permission is missing.', [
                'permission' => $resolvedPermission,
                'route' => $route->getName(),
            ]);

            return $next($request);
        }

        $user = $request->user();

        if ($user === null || ! $this->permissions->userHas($user, $resolvedPermission)) {
            throw new AccessDeniedHttpException;
        }

        return $next($request);
    }
}
