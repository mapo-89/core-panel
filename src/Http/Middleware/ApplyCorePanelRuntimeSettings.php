<?php

declare(strict_types=1);

namespace CorePanel\Http\Middleware;

use Closure;
use CorePanel\Support\Locale\SupportedLocales;
use CorePanel\Support\Settings\SettingsRepository;
use Illuminate\Http\Request;
use Laravel\Fortify\Features;
use Symfony\Component\HttpFoundation\Response;

final readonly class ApplyCorePanelRuntimeSettings
{
    public function __construct(private SettingsRepository $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        $registrationEnabled = (bool) $this->settings->get(
            'auth',
            'registration_enabled',
            (bool) config('core-panel.auth.registration_enabled', false),
        );
        $emailVerificationEnabled = (bool) $this->settings->get(
            'auth',
            'email_verification_enabled',
            (bool) config('core-panel.auth.email_verification_enabled', true),
        );
        $passwordResetEnabled = (bool) $this->settings->get(
            'auth',
            'password_reset_enabled',
            (bool) config('core-panel.auth.password_reset_enabled', true),
        );
        $twoFactorEnabled = (bool) $this->settings->get(
            'auth',
            'two_factor_enabled',
            (bool) config('core-panel.auth.two_factor_enabled', true),
        );
        $microsoftClientId = $this->settings->get(
            'auth',
            'microsoft_client_id',
            config('services.microsoft.client_id'),
        );
        $githubClientId = $this->settings->get(
            'auth',
            'github_client_id',
            config('services.github.client_id'),
        );
        $githubClientSecret = $this->settings->get(
            'auth',
            'github_client_secret',
            config('services.github.client_secret'),
        );
        $googleClientId = $this->settings->get(
            'auth',
            'google_client_id',
            config('services.google.client_id'),
        );
        $googleClientSecret = $this->settings->get(
            'auth',
            'google_client_secret',
            config('services.google.client_secret'),
        );
        $microsoftClientSecret = $this->settings->get(
            'auth',
            'microsoft_client_secret',
            config('services.microsoft.client_secret'),
        );
        $microsoftTenant = $this->settings->get(
            'auth',
            'microsoft_tenant',
            config('services.microsoft.tenant', 'common'),
        );
        $socialMasterProvider = $this->settings->get(
            'auth',
            'social_master_provider',
            config('core-panel.auth.socialite.master_provider'),
        );
        $githubRedirect = $this->normalizeSocialiteRedirect(
            $request,
            config('services.github.redirect'),
            '/auth/github/callback',
        );
        $googleRedirect = $this->normalizeSocialiteRedirect(
            $request,
            config('services.google.redirect'),
            '/auth/google/callback',
        );
        $microsoftRedirect = $this->normalizeSocialiteRedirect(
            $request,
            config('services.microsoft.redirect'),
            '/auth/microsoft/callback',
        );
        $appName = $this->settings->get(
            'general',
            'app_name',
            config('app.name', 'CorePanel'),
        );
        $timezone = $this->settings->get(
            'general',
            'timezone',
            config('app.timezone', 'UTC'),
        );
        $defaultLocale = $this->settings->get(
            'i18n',
            'default_locale',
            config('core-panel.i18n.default_locale', 'de'),
        );
        $fallbackLocale = $this->settings->get(
            'i18n',
            'fallback_locale',
            config('core-panel.i18n.fallback_locale', 'en'),
        );
        $supportedLocales = $this->settings->get(
            'i18n',
            'languages',
            SupportedLocales::availableCodes(),
        );
        $supportedLocaleCodes = SupportedLocales::normalize($supportedLocales);
        $resolvedTimezone = is_string($timezone) && $timezone !== '' ? $timezone : config('app.timezone', 'UTC');
        config()->set('app.name', is_string($appName) && $appName !== '' ? $appName : config('app.name', 'CorePanel'));
        config()->set('core-panel.runtime_timezone', $resolvedTimezone);
        config()->set('app.locale', is_string($defaultLocale) && $defaultLocale !== '' ? $defaultLocale : config('app.locale', 'de'));
        config()->set('app.fallback_locale', is_string($fallbackLocale) && $fallbackLocale !== '' ? $fallbackLocale : config('app.fallback_locale', 'en'));
        config()->set('app.languages', SupportedLocales::labelsFor($supportedLocaleCodes));
        config()->set('core-panel.auth.registration_enabled', $registrationEnabled);
        config()->set('core-panel.auth.email_verification_enabled', $emailVerificationEnabled);
        config()->set('core-panel.auth.password_reset_enabled', $passwordResetEnabled);
        config()->set(
            'core-panel.auth.socialite.master_provider',
            is_string($socialMasterProvider) && $socialMasterProvider !== ''
                ? $socialMasterProvider
                : null,
        );
        config()->set('core-panel.auth.two_factor_enabled', $twoFactorEnabled);
        config()->set('services.github.client_id', is_string($githubClientId) ? $githubClientId : config('services.github.client_id'));
        config()->set('services.github.client_secret', is_string($githubClientSecret) ? $githubClientSecret : config('services.github.client_secret'));
        config()->set('services.github.redirect', $githubRedirect);
        config()->set('services.google.client_id', is_string($googleClientId) ? $googleClientId : config('services.google.client_id'));
        config()->set('services.google.client_secret', is_string($googleClientSecret) ? $googleClientSecret : config('services.google.client_secret'));
        config()->set('services.google.redirect', $googleRedirect);
        config()->set('services.microsoft.client_id', is_string($microsoftClientId) ? $microsoftClientId : config('services.microsoft.client_id'));
        config()->set('services.microsoft.client_secret', is_string($microsoftClientSecret) ? $microsoftClientSecret : config('services.microsoft.client_secret'));
        config()->set('services.microsoft.redirect', $microsoftRedirect);
        config()->set('services.microsoft.tenant', is_string($microsoftTenant) && $microsoftTenant !== '' ? $microsoftTenant : config('services.microsoft.tenant', 'common'));
        config()->set('fortify.features', array_values(array_filter([
            $registrationEnabled ? Features::registration() : null,
            $passwordResetEnabled ? Features::resetPasswords() : null,
            $emailVerificationEnabled ? Features::emailVerification() : null,
            Features::updateProfileInformation(),
            Features::updatePasswords(),
            $twoFactorEnabled ? Features::twoFactorAuthentication([
                'confirm' => true,
                'confirmPassword' => false,
            ]) : null,
        ])));
        config()->set('core-panel.i18n.default_locale', config('app.locale'));
        config()->set('core-panel.i18n.fallback_locale', config('app.fallback_locale'));
        config()->set('core-panel.i18n.supported_locales', $supportedLocaleCodes);

        return $next($request);
    }

    private function normalizeSocialiteRedirect(Request $request, mixed $redirect, string $fallback): string
    {
        $target = is_string($redirect) && trim($redirect) !== '' ? trim($redirect) : $fallback;

        if (str_starts_with($target, 'http://') || str_starts_with($target, 'https://')) {
            return $target;
        }

        return rtrim($request->getSchemeAndHttpHost(), '/').'/'.ltrim($target, '/');
    }
}
