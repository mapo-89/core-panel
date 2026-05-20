<?php

declare(strict_types=1);

namespace CorePanel\Support\Developer;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;

final class RouteCatalog
{
    /**
     * @var list<string>
     */
    private const TENANCY_MIDDLEWARE = [
        'Stancl\\Tenancy\\Middleware\\InitializeTenancyByDomain',
        'Stancl\\Tenancy\\Middleware\\InitializeTenancyBySubdomain',
        'Stancl\\Tenancy\\Middleware\\InitializeTenancyByDomainOrSubdomain',
        'Stancl\\Tenancy\\Middleware\\InitializeTenancyByPath',
        'Stancl\\Tenancy\\Middleware\\InitializeTenancyByRequestData',
        'Stancl\\Tenancy\\Middleware\\PreventAccessFromCentralDomains',
    ];

    /**
     * @return array{
     *     api: array{rows:list<array<string, mixed>>, total:int},
     *     web: array{rows:list<array<string, mixed>>, total:int},
     *     service: array{rows:list<array<string, mixed>>, total:int}
     * }
     */
    public function list(Request $request): array
    {
        $routes = collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(fn (Route $route): bool => $this->belongsToCurrentContext($request, $route))
            ->reject(fn (Route $route): bool => $this->isIgnoredRoute($route))
            ->values();

        return [
            'api' => $this->section($request, $routes->filter(fn (Route $route): bool => $this->isApiRoute($route))),
            'web' => $this->section($request, $routes->filter(fn (Route $route): bool => $this->isWebRoute($route))),
            'service' => $this->section($request, $routes->filter(fn (Route $route): bool => $this->isServiceRoute($route))),
        ];
    }

    /**
     * @param  Collection<int, Route>  $routes
     * @return array{rows:list<array<string, mixed>>, total:int}
     */
    private function section(Request $request, Collection $routes): array
    {
        $search = Str::of((string) $request->query('search', ''))->trim()->lower()->value();
        $method = $this->nullableString($request->query('filter.method'));
        $sort = $this->normalizeSort((string) $request->query('sort', ''));

        $rows = $routes
            ->map(fn (Route $route): array => $this->formatRoute($route))
            ->filter(function (array $row) use ($search, $method): bool {
                if ($method !== null && ! in_array($method, $row['methods'], true)) {
                    return false;
                }

                if ($search === '') {
                    return true;
                }

                $haystack = Str::lower(implode(' ', [
                    implode('|', $row['methods']),
                    $row['uri'],
                    $row['name'] ?? '',
                    $row['action'],
                    $row['domain'] ?? '',
                    implode(' ', $row['middleware']),
                ]));

                return str_contains($haystack, $search);
            })
            ->sortBy(
                fn (array $row): string => $this->sortValue($row, $sort['field']),
                SORT_NATURAL | SORT_FLAG_CASE,
                $sort['descending'],
            )
            ->values();

        return [
            'rows' => $rows->all(),
            'total' => $rows->count(),
        ];
    }

    private function belongsToCurrentContext(Request $request, Route $route): bool
    {
        if ($this->isUniversalRoute($route)) {
            return true;
        }

        return $this->isTenantContext($request)
            ? $this->isTenantSpecificRoute($route)
            : ! $this->isTenantSpecificRoute($route);
    }

    private function isTenantContext(Request $request): bool
    {
        if (function_exists('tenant') && tenant() !== null) {
            return true;
        }

        return $request->routeIs('tenant.*');
    }

    private function isUniversalRoute(Route $route): bool
    {
        return collect($route->gatherMiddleware())->contains(
            static fn (mixed $middleware): bool => is_string($middleware)
                && ($middleware === 'universal' || str_contains($middleware, 'UniversalRoutes')),
        );
    }

    private function isTenantSpecificRoute(Route $route): bool
    {
        $routeName = $route->getName();

        if (is_string($routeName) && str_starts_with($routeName, 'tenant.')) {
            return true;
        }

        return collect($route->gatherMiddleware())->contains(
            static fn (mixed $middleware): bool => is_string($middleware)
                && in_array($middleware, self::TENANCY_MIDDLEWARE, true),
        );
    }

    private function isIgnoredRoute(Route $route): bool
    {
        $uri = ltrim($route->uri(), '/');

        return $uri === 'up'
            || str_starts_with($uri, '_debugbar')
            || str_starts_with($uri, 'telescope')
            || str_starts_with($uri, 'ignition');
    }

    private function isApiRoute(Route $route): bool
    {
        return str_starts_with($route->uri(), 'api/');
    }

    private function isServiceRoute(Route $route): bool
    {
        return str_contains($route->getActionName(), 'Controllers\\Service\\');
    }

    private function isWebRoute(Route $route): bool
    {
        if ($this->isApiRoute($route) || $this->isServiceRoute($route)) {
            return false;
        }

        return collect($route->gatherMiddleware())->contains(
            static fn (mixed $middleware): bool => is_string($middleware) && str_contains($middleware, 'web'),
        );
    }

    /**
     * @return array{
     *     action:string,
     *     domain:?string,
     *     id:string,
     *     method:string,
     *     methods:list<string>,
     *     middleware:list<string>,
     *     name:?string,
     *     uri:string
     * }
     */
    private function formatRoute(Route $route): array
    {
        $methods = collect($route->methods())
            ->reject(static fn (string $method): bool => $method === 'HEAD')
            ->values()
            ->all();

        return [
            'action' => $this->formatAction($route->getActionName()),
            'domain' => $route->getDomain(),
            'id' => md5(implode('|', $methods).'|'.$route->uri().'|'.$route->getActionName()),
            'method' => implode('|', $methods),
            'methods' => $methods,
            'middleware' => collect($route->gatherMiddleware())
                ->map(static fn (mixed $middleware): string => is_string($middleware) ? $middleware : class_basename((string) $middleware))
                ->values()
                ->all(),
            'name' => $route->getName(),
            'uri' => '/'.ltrim($route->uri(), '/'),
        ];
    }

    private function formatAction(string $action): string
    {
        if ($action === 'Closure') {
            return $action;
        }

        if (! str_contains($action, '@')) {
            return class_basename(str_replace('\\', '/', $action));
        }

        [$class, $method] = explode('@', $action, 2);

        return class_basename(str_replace('\\', '/', $class)).'@'.$method;
    }

    /**
     * @return array{field:string, descending:bool}
     */
    private function normalizeSort(string $sort): array
    {
        $descending = str_starts_with($sort, '-');
        $field = ltrim(trim($sort), '-');

        if (! in_array($field, ['action', 'domain', 'method', 'name', 'uri'], true)) {
            $field = 'uri';
            $descending = false;
        }

        return [
            'field' => $field,
            'descending' => $descending,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function sortValue(array $row, string $field): string
    {
        return match ($field) {
            'action' => (string) ($row['action'] ?? ''),
            'domain' => (string) ($row['domain'] ?? ''),
            'method' => (string) ($row['method'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            default => (string) ($row['uri'] ?? ''),
        };
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
