<?php

declare(strict_types=1);

namespace CorePanel\Http\Middleware;

use Closure;
use CorePanel\Contracts\LocaleResolver;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class ResolveCorePanelLocale
{
    public function __construct(
        private LocaleResolver $locales,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale($this->locales->resolve($request));

        return $next($request);
    }
}
