<?php

declare(strict_types=1);

use CorePanel\Support\Config\CorePanelConfig;
use CorePanel\Support\LocaleResolver;
use Illuminate\Http\Request;

it('prefers the locale cookie over the session and the default locale', function (): void {
    config()->set('core-panel.i18n.supported_locales', ['de', 'en']);
    config()->set('core-panel.i18n.default_locale', 'en');
    config()->set('core-panel.i18n.fallback_locale', 'en');

    $request = Request::create('/login', 'GET', cookies: [
        'locale' => 'de',
    ]);

    $request->setLaravelSession(session()->driver());
    $request->session()->put('locale', 'en');

    $resolver = new LocaleResolver(CorePanelConfig::fromRepository(config()));

    expect($resolver->resolve($request))->toBe('de');
});

it('falls back to the session locale when no cookie is present', function (): void {
    config()->set('core-panel.i18n.supported_locales', ['de', 'en']);
    config()->set('core-panel.i18n.default_locale', 'en');
    config()->set('core-panel.i18n.fallback_locale', 'en');

    $request = Request::create('/login', 'GET');
    $request->setLaravelSession(session()->driver());
    $request->session()->put('locale', 'de');

    $resolver = new LocaleResolver(CorePanelConfig::fromRepository(config()));

    expect($resolver->resolve($request))->toBe('de');
});
