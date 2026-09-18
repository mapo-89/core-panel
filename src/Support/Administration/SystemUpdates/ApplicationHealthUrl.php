<?php

declare(strict_types=1);

namespace CorePanel\Support\Administration\SystemUpdates;

use Closure;
use Illuminate\Foundation\Configuration\ApplicationBuilder;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use ReflectionFunction;

final readonly class ApplicationHealthUrl
{
    public function __construct(private Router $router) {}

    public function resolve(): string
    {
        foreach ($this->router->getRoutes()->getRoutes() as $route) {
            if ($this->isFrameworkHealthRoute($route)) {
                return url('/'.ltrim($route->uri(), '/'));
            }
        }

        return url('/up');
    }

    private function isFrameworkHealthRoute(Route $route): bool
    {
        if (! in_array('GET', $route->methods(), true)) {
            return false;
        }

        $action = $route->getAction('uses');

        if (! $action instanceof Closure) {
            return false;
        }

        return (new ReflectionFunction($action))->getClosureScopeClass()?->getName() === ApplicationBuilder::class;
    }
}
