<?php

declare(strict_types=1);

namespace CorePanel\Http\Middleware;

use Closure;
use CorePanel\Support\Config\CorePanelConfig;
use CorePanel\Support\Locale\SupportedLocales;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class ShareLocaleDataWithInertia
{
    public function __construct(private CorePanelConfig $config) {}

    public function handle(Request $request, Closure $next): Response
    {
        $labels = SupportedLocales::labelsFor($this->config->i18n->supportedLocales);

        $request->attributes->set('core-panel.locale', [
            'current' => app()->currentLocale(),
            'default' => $this->config->i18n->defaultLocale,
            'fallback' => $this->config->i18n->fallbackLocale,
            'supported' => $this->config->i18n->supportedLocales,
            'labels' => $labels,
        ]);

        return $next($request);
    }
}
