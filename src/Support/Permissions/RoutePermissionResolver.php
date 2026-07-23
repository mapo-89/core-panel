<?php

declare(strict_types=1);

namespace CorePanel\Support\Permissions;

use Illuminate\Routing\Route;

final readonly class RoutePermissionResolver
{
    public function __construct(private CorePanelAccess $access) {}

    public function resolve(Route $route, ?string $explicitPermission = null): ?string
    {
        if ($explicitPermission !== null && trim($explicitPermission) !== '') {
            return trim($explicitPermission);
        }

        $routeName = $route->getName();

        if (! is_string($routeName) || $routeName === '') {
            return null;
        }

        /** @var list<string> $ignoredRouteNames */
        $ignoredRouteNames = (array) config('core-panel-access.ignored_route_names', []);

        if (in_array($routeName, $ignoredRouteNames, true)) {
            return null;
        }

        /** @var array<string, string|null> $overrides */
        $overrides = (array) config('core-panel-access.route_permissions', []);

        if (array_key_exists($routeName, $overrides)) {
            $permission = $overrides[$routeName];

            return is_string($permission) && trim($permission) !== ''
                ? trim($permission)
                : null;
        }

        $nameWithoutPrefix = str_starts_with($routeName, 'core-panel.')
            ? substr($routeName, strlen('core-panel.'))
            : $routeName;

        if ($nameWithoutPrefix === '') {
            return null;
        }

        $segments = explode('.', $nameWithoutPrefix);
        $resource = $this->resolveResource($segments[0]);

        if ($resource === null) {
            return null;
        }

        $ability = $this->resolveAbility($segments[array_key_last($segments)]);

        if ($ability === null) {
            return null;
        }

        return "{$resource}.{$ability}";
    }

    private function resolveResource(?string $segment): ?string
    {
        if (! is_string($segment) || trim($segment) === '') {
            return null;
        }

        $normalizedSegment = $this->normalizeResourceSegment($segment);

        foreach ($this->access->managedPermissions() as $permission) {
            $resource = $this->access->permissionResource($permission);

            if ($this->normalizeResourceSegment($resource) === $normalizedSegment) {
                return $resource;
            }
        }

        return null;
    }

    private function resolveAbility(?string $action): ?string
    {
        return match ($action) {
            'create', 'store' => 'create',
            'delete', 'destroy', 'force-delete' => 'delete',
            'download', 'index', 'preview', 'show' => 'view',
            'edit', 'publish', 'restore', 'update' => 'update',
            'switch' => 'switch',
            'upload' => 'upload',
            default => null,
        };
    }

    private function normalizeResourceSegment(string $value): string
    {
        return strtolower(str_replace(['.', '_'], '-', trim($value)));
    }
}
