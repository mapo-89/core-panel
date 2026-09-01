<?php

declare(strict_types=1);

namespace CorePanel\Http\Middleware;

use Closure;
use CorePanel\Support\Locale\SupportedLocales;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class ShareLocaleDataWithInertia
{
    public function __construct(private ConfigRepository $config) {}

    public function handle(Request $request, Closure $next): Response
    {
        $supportedLocales = SupportedLocales::codes($this->config);
        $labels = SupportedLocales::labelsFor($supportedLocales, $this->config);

        $request->attributes->set('core-panel.locale', [
            'current' => app()->currentLocale(),
            'default' => (string) $this->config->get('core-panel.i18n.default_locale', 'de'),
            'fallback' => (string) $this->config->get('core-panel.i18n.fallback_locale', 'en'),
            'supported' => $supportedLocales,
            'labels' => $labels,
        ]);

        return $next($request);
    }
}
