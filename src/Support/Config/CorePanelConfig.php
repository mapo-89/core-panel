<?php

declare(strict_types=1);

namespace CorePanel\Support\Config;

use CorePanel\Support\Locale\SupportedLocales;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

final readonly class CorePanelConfig
{
    public function __construct(
        public string $userModel,
        public string $routePrefix,
        /** @var list<string> */
        public array $middleware,
        public AuthConfig $auth,
        public I18nConfig $i18n,
        public UiConfig $ui,
        public FilesConfig $files,
        public SecurityConfig $security,
        public HorizonConfig $horizon,
    ) {}

    public static function fromRepository(ConfigRepository $config): self
    {
        /** @var list<string> $middleware */
        $middleware = array_values((array) $config->get('core-panel.middleware', ['web', 'auth']));
        /** @var list<string> $supportedLocales */
        $supportedLocales = SupportedLocales::codes($config);
        /** @var list<string> $allowedMimeTypes */
        $allowedMimeTypes = array_values((array) $config->get('core-panel.files.allowed_mime_types', ['application/pdf', 'image/jpeg', 'image/png', 'image/webp']));

        return new self(
            userModel: (string) $config->get('core-panel.user_model'),
            routePrefix: (string) $config->get('core-panel.route_prefix', 'admin'),
            middleware: $middleware,
            auth: new AuthConfig(
                registrationEnabled: (bool) $config->get('core-panel.auth.registration_enabled', false),
            ),
            i18n: new I18nConfig(
                defaultLocale: (string) $config->get('core-panel.i18n.default_locale', 'de'),
                fallbackLocale: (string) $config->get('core-panel.i18n.fallback_locale', 'en'),
                supportedLocales: $supportedLocales,
            ),
            ui: new UiConfig(
                library: (string) $config->get('core-panel.ui.library', 'primevue'),
                theme: (string) $config->get('core-panel.ui.theme', 'core-panel'),
            ),
            files: new FilesConfig(
                allowedMimeTypes: $allowedMimeTypes,
                disk: (string) $config->get('core-panel.files.disk', 'public'),
                maxUploadSize: (int) $config->get('core-panel.files.max_upload_size', 10240),
            ),
            security: new SecurityConfig(
                headersEnabled: (bool) $config->get('core-panel.security.headers.enabled', $config->get('core-panel.security.headers_enabled', true)),
                contentSecurityPolicy: (string) $config->get('core-panel.security.headers.csp', "default-src 'self'; frame-ancestors 'self'; img-src 'self' data: blob: https:; object-src 'none'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'"),
                cspReportOnly: (bool) $config->get('core-panel.security.headers.csp_report_only', false),
                strictTransportSecurity: (string) $config->get('core-panel.security.headers.hsts', 'max-age=31536000; includeSubDomains'),
                referrerPolicy: (string) $config->get('core-panel.security.headers.referrer_policy', 'strict-origin-when-cross-origin'),
                permissionsPolicy: (string) $config->get('core-panel.security.headers.permissions_policy', 'accelerometer=(), camera=(), geolocation=(), gyroscope=(), microphone=(), payment=(), usb=()'),
            ),
            horizon: new HorizonConfig(
                enabled: (bool) $config->get('core-panel.horizon.enabled', true),
            ),
        );
    }
}
