<?php

declare(strict_types=1);

namespace CorePanel\Support\Settings;

use CorePanel\Support\Locale\SupportedLocales;

final class SettingsSchema
{
    /**
     * @return array<string, array{
     *     label:string,
     *     description:string,
     *     fields:array<string, array{
     *         type:string,
     *         label:string,
     *         help:?string,
     *         default:mixed,
     *         is_public:bool,
     *         is_localized:bool,
     *         options?:list<array{label:string,value:mixed}>,
     *         rules:list<mixed>
     *     }>
     * }>
     */
    public static function definitions(): array
    {
        return [
            'appearance' => [
                'description' => __('core-panel::settings.descriptions.appearance'),
                'fields' => [
                    'theme_palette' => [
                        'default' => 'paper',
                        'help' => __('core-panel::settings.help.theme_palette'),
                        'is_localized' => false,
                        'is_public' => true,
                        'label' => __('core-panel::settings.fields.theme_palette'),
                        'options' => self::options([
                            'contrast' => __('core-panel::settings.options.theme_palette.contrast'),
                            'ocean' => __('core-panel::settings.options.theme_palette.ocean'),
                            'paper' => __('core-panel::settings.options.theme_palette.paper'),
                            'soft' => __('core-panel::settings.options.theme_palette.soft'),
                        ]),
                        'rules' => ['required', 'string', 'in:paper,soft,ocean,contrast'],
                        'type' => 'select',
                    ],
                ],
                'label' => __('core-panel::settings.groups.appearance'),
            ],
            'auth' => [
                'description' => __('core-panel::settings.descriptions.auth'),
                'fields' => [
                    'email_verification_enabled' => [
                        'default' => (bool) config('core-panel.auth.email_verification_enabled', true),
                        'help' => __('core-panel::settings.help.email_verification_enabled'),
                        'is_localized' => false,
                        'is_public' => false,
                        'label' => __('core-panel::settings.fields.email_verification_enabled'),
                        'rules' => ['required', 'boolean'],
                        'type' => 'boolean',
                    ],
                    'github_client_id' => [
                        'default' => (string) config('services.github.client_id', ''),
                        'help' => __('core-panel::settings.help.github_client_id'),
                        'is_localized' => false,
                        'is_public' => false,
                        'label' => __('core-panel::settings.fields.github_client_id'),
                        'rules' => ['nullable', 'string', 'max:255'],
                        'type' => 'text',
                    ],
                    'github_client_secret' => [
                        'default' => (string) config('services.github.client_secret', ''),
                        'help' => __('core-panel::settings.help.github_client_secret'),
                        'is_localized' => false,
                        'is_public' => false,
                        'label' => __('core-panel::settings.fields.github_client_secret'),
                        'rules' => ['nullable', 'string', 'max:255'],
                        'type' => 'text',
                    ],
                    'google_client_id' => [
                        'default' => (string) config('services.google.client_id', ''),
                        'help' => __('core-panel::settings.help.google_client_id'),
                        'is_localized' => false,
                        'is_public' => false,
                        'label' => __('core-panel::settings.fields.google_client_id'),
                        'rules' => ['nullable', 'string', 'max:255'],
                        'type' => 'text',
                    ],
                    'google_client_secret' => [
                        'default' => (string) config('services.google.client_secret', ''),
                        'help' => __('core-panel::settings.help.google_client_secret'),
                        'is_localized' => false,
                        'is_public' => false,
                        'label' => __('core-panel::settings.fields.google_client_secret'),
                        'rules' => ['nullable', 'string', 'max:255'],
                        'type' => 'text',
                    ],
                    'microsoft_client_id' => [
                        'default' => (string) config('services.microsoft.client_id', ''),
                        'help' => __('core-panel::settings.help.microsoft_client_id'),
                        'is_localized' => false,
                        'is_public' => false,
                        'label' => __('core-panel::settings.fields.microsoft_client_id'),
                        'rules' => ['nullable', 'string', 'max:255'],
                        'type' => 'text',
                    ],
                    'microsoft_client_secret' => [
                        'default' => (string) config('services.microsoft.client_secret', ''),
                        'help' => __('core-panel::settings.help.microsoft_client_secret'),
                        'is_localized' => false,
                        'is_public' => false,
                        'label' => __('core-panel::settings.fields.microsoft_client_secret'),
                        'rules' => ['nullable', 'string', 'max:255'],
                        'type' => 'text',
                    ],
                    'microsoft_tenant' => [
                        'default' => (string) config('services.microsoft.tenant', 'common'),
                        'help' => __('core-panel::settings.help.microsoft_tenant'),
                        'is_localized' => false,
                        'is_public' => false,
                        'label' => __('core-panel::settings.fields.microsoft_tenant'),
                        'rules' => ['nullable', 'string', 'max:255'],
                        'type' => 'text',
                    ],
                    'password_reset_enabled' => [
                        'default' => (bool) config('core-panel.auth.password_reset_enabled', true),
                        'help' => __('core-panel::settings.help.password_reset_enabled'),
                        'is_localized' => false,
                        'is_public' => false,
                        'label' => __('core-panel::settings.fields.password_reset_enabled'),
                        'rules' => ['required', 'boolean'],
                        'type' => 'boolean',
                    ],
                    'registration_enabled' => [
                        'default' => (bool) config('core-panel.auth.registration_enabled', false),
                        'help' => __('core-panel::settings.help.registration_enabled'),
                        'is_localized' => false,
                        'is_public' => true,
                        'label' => __('core-panel::settings.fields.registration_enabled'),
                        'rules' => ['required', 'boolean'],
                        'type' => 'boolean',
                    ],
                    'social_github_enabled' => [
                        'default' => (bool) config('core-panel.auth.socialite.providers.github.enabled', false),
                        'help' => __('core-panel::settings.help.social_github_enabled'),
                        'is_localized' => false,
                        'is_public' => true,
                        'label' => __('core-panel::settings.fields.social_github_enabled'),
                        'rules' => ['required', 'boolean'],
                        'type' => 'boolean',
                    ],
                    'social_google_enabled' => [
                        'default' => (bool) config('core-panel.auth.socialite.providers.google.enabled', false),
                        'help' => __('core-panel::settings.help.social_google_enabled'),
                        'is_localized' => false,
                        'is_public' => true,
                        'label' => __('core-panel::settings.fields.social_google_enabled'),
                        'rules' => ['required', 'boolean'],
                        'type' => 'boolean',
                    ],
                    'social_master_provider' => [
                        'default' => (string) config('core-panel.auth.socialite.master_provider', ''),
                        'help' => __('core-panel::settings.help.social_master_provider'),
                        'is_localized' => false,
                        'is_public' => false,
                        'label' => __('core-panel::settings.fields.social_master_provider'),
                        'options' => [
                            [
                                'label' => __('core-panel::settings.options.social_master_provider.none'),
                                'value' => '',
                            ],
                            [
                                'label' => __('page-auth.socialite.providers.github'),
                                'value' => 'github',
                            ],
                            [
                                'label' => __('page-auth.socialite.providers.google'),
                                'value' => 'google',
                            ],
                            [
                                'label' => __('page-auth.socialite.providers.microsoft'),
                                'value' => 'microsoft',
                            ],
                        ],
                        'rules' => ['nullable', 'string', 'in:,github,google,microsoft'],
                        'type' => 'select',
                    ],
                    'social_microsoft_enabled' => [
                        'default' => (bool) config('core-panel.auth.socialite.providers.microsoft.enabled', false),
                        'help' => __('core-panel::settings.help.social_microsoft_enabled'),
                        'is_localized' => false,
                        'is_public' => true,
                        'label' => __('core-panel::settings.fields.social_microsoft_enabled'),
                        'rules' => ['required', 'boolean'],
                        'type' => 'boolean',
                    ],
                    'two_factor_enabled' => [
                        'default' => (bool) config('core-panel.auth.two_factor_enabled', true),
                        'help' => __('core-panel::settings.help.two_factor_enabled'),
                        'is_localized' => false,
                        'is_public' => false,
                        'label' => __('core-panel::settings.fields.two_factor_enabled'),
                        'rules' => ['required', 'boolean'],
                        'type' => 'boolean',
                    ],
                ],
                'label' => __('core-panel::settings.groups.auth'),
            ],
            'files' => [
                'description' => __('core-panel::settings.descriptions.files'),
                'fields' => [
                    'allowed_mime_types' => [
                        'default' => array_values((array) config('core-panel.files.allowed_mime_types', ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])),
                        'help' => __('core-panel::settings.help.allowed_mime_types'),
                        'is_localized' => false,
                        'is_public' => false,
                        'label' => __('core-panel::settings.fields.allowed_mime_types'),
                        'rules' => ['required', 'array', 'min:1'],
                        'type' => 'multiselect',
                    ],
                    'max_upload_size' => [
                        'default' => (int) config('core-panel.files.max_upload_size', 10240),
                        'help' => __('core-panel::settings.help.max_upload_size'),
                        'is_localized' => false,
                        'is_public' => false,
                        'label' => __('core-panel::settings.fields.max_upload_size'),
                        'rules' => ['required', 'integer', 'min:1', 'max:102400'],
                        'type' => 'number',
                    ],
                ],
                'label' => __('core-panel::settings.groups.files'),
            ],
            'general' => [
                'description' => __('core-panel::settings.descriptions.general'),
                'fields' => [
                    'app_name' => [
                        'default' => (string) config('app.name', 'CorePanel'),
                        'help' => __('core-panel::settings.help.app_name'),
                        'is_localized' => false,
                        'is_public' => true,
                        'label' => __('core-panel::settings.fields.app_name'),
                        'rules' => ['required', 'string', 'max:255'],
                        'type' => 'text',
                    ],
                    'app_subtitle' => [
                        'default' => (string) __('page-layout.brand_subtitle_default'),
                        'help' => __('core-panel::settings.help.app_subtitle'),
                        'is_localized' => false,
                        'is_public' => true,
                        'label' => __('core-panel::settings.fields.app_subtitle'),
                        'rules' => ['nullable', 'string', 'max:255'],
                        'type' => 'text',
                    ],
                    'timezone' => [
                        'default' => (string) config('app.timezone', 'UTC'),
                        'help' => __('core-panel::settings.help.timezone'),
                        'is_localized' => false,
                        'is_public' => true,
                        'label' => __('core-panel::settings.fields.timezone'),
                        'options' => self::timezoneOptions(),
                        'rules' => ['required', 'string', 'in:'.implode(',', timezone_identifiers_list())],
                        'type' => 'select',
                    ],
                ],
                'label' => __('core-panel::settings.groups.general'),
            ],
            'i18n' => [
                'description' => __('core-panel::settings.descriptions.i18n'),
                'fields' => [
                    'languages' => [
                        'default' => SupportedLocales::codes(),
                        'help' => __('core-panel::settings.help.languages'),
                        'is_localized' => false,
                        'is_public' => true,
                        'label' => __('core-panel::settings.fields.languages'),
                        'options' => SupportedLocales::availableOptions(),
                        'rules' => ['required', 'array', 'min:1'],
                        'type' => 'multiselect',
                    ],
                    'default_locale' => [
                        'default' => (string) config('core-panel.i18n.default_locale', 'de'),
                        'help' => __('core-panel::settings.help.default_locale'),
                        'is_localized' => false,
                        'is_public' => true,
                        'label' => __('core-panel::settings.fields.default_locale'),
                        'options' => SupportedLocales::availableOptions(),
                        'rules' => ['required', 'string', 'in:'.implode(',', self::availableLocaleCodes())],
                        'type' => 'select',
                    ],
                    'fallback_locale' => [
                        'default' => (string) config('core-panel.i18n.fallback_locale', 'en'),
                        'help' => __('core-panel::settings.help.fallback_locale'),
                        'is_localized' => false,
                        'is_public' => true,
                        'label' => __('core-panel::settings.fields.fallback_locale'),
                        'options' => SupportedLocales::availableOptions(),
                        'rules' => ['required', 'string', 'in:'.implode(',', self::availableLocaleCodes())],
                        'type' => 'select',
                    ],
                ],
                'label' => __('core-panel::settings.groups.i18n'),
            ],
            'mail' => [
                'description' => __('core-panel::settings.descriptions.mail'),
                'fields' => [
                    'from_address' => [
                        'default' => (string) config('mail.from.address', ''),
                        'help' => __('core-panel::settings.help.from_address'),
                        'is_localized' => false,
                        'is_public' => false,
                        'label' => __('core-panel::settings.fields.from_address'),
                        'rules' => ['nullable', 'email', 'max:255'],
                        'type' => 'email',
                    ],
                    'from_name' => [
                        'default' => (string) config('mail.from.name', ''),
                        'help' => __('core-panel::settings.help.from_name'),
                        'is_localized' => false,
                        'is_public' => false,
                        'label' => __('core-panel::settings.fields.from_name'),
                        'rules' => ['nullable', 'string', 'max:255'],
                        'type' => 'text',
                    ],
                ],
                'label' => __('core-panel::settings.groups.mail'),
            ],
            'oauth' => [
                'description' => __('core-panel::settings.descriptions.oauth'),
                'fields' => [
                    'personal_access_clients_enabled' => [
                        'default' => (bool) config('core-panel.auth.passport.personal_access_clients_enabled', false),
                        'help' => __('core-panel::settings.help.personal_access_clients_enabled'),
                        'is_localized' => false,
                        'is_public' => false,
                        'label' => __('core-panel::settings.fields.personal_access_clients_enabled'),
                        'rules' => ['required', 'boolean'],
                        'type' => 'boolean',
                    ],
                    'refresh_token_ttl_days' => [
                        'default' => (int) config('core-panel.auth.passport.refresh_token_ttl_days', 30),
                        'help' => __('core-panel::settings.help.refresh_token_ttl_days'),
                        'is_localized' => false,
                        'is_public' => false,
                        'label' => __('core-panel::settings.fields.refresh_token_ttl_days'),
                        'rules' => ['required', 'integer', 'min:1', 'max:365'],
                        'type' => 'number',
                    ],
                    'token_ttl_minutes' => [
                        'default' => (int) config('core-panel.auth.passport.token_ttl_minutes', 15),
                        'help' => __('core-panel::settings.help.token_ttl_minutes'),
                        'is_localized' => false,
                        'is_public' => false,
                        'label' => __('core-panel::settings.fields.token_ttl_minutes'),
                        'rules' => ['required', 'integer', 'min:1', 'max:1440'],
                        'type' => 'number',
                    ],
                ],
                'label' => __('core-panel::settings.groups.oauth'),
            ],
            'security' => [
                'description' => __('core-panel::settings.descriptions.security'),
                'fields' => [
                    'content_security_policy' => [
                        'default' => (string) config('core-panel.security.headers.csp', "default-src 'self'; frame-ancestors 'self'; img-src 'self' data: https:; object-src 'none'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'"),
                        'help' => __('core-panel::settings.help.content_security_policy'),
                        'is_localized' => false,
                        'is_public' => false,
                        'label' => __('core-panel::settings.fields.content_security_policy'),
                        'rules' => ['nullable', 'string'],
                        'type' => 'text',
                    ],
                    'csp_report_only' => [
                        'default' => (bool) config('core-panel.security.headers.csp_report_only', false),
                        'help' => __('core-panel::settings.help.csp_report_only'),
                        'is_localized' => false,
                        'is_public' => false,
                        'label' => __('core-panel::settings.fields.csp_report_only'),
                        'rules' => ['required', 'boolean'],
                        'type' => 'boolean',
                    ],
                    'headers_enabled' => [
                        'default' => (bool) config('core-panel.security.headers.enabled', config('core-panel.security.headers_enabled', true)),
                        'help' => __('core-panel::settings.help.headers_enabled'),
                        'is_localized' => false,
                        'is_public' => false,
                        'label' => __('core-panel::settings.fields.headers_enabled'),
                        'rules' => ['required', 'boolean'],
                        'type' => 'boolean',
                    ],
                    'hsts' => [
                        'default' => (string) config('core-panel.security.headers.hsts', 'max-age=31536000; includeSubDomains'),
                        'help' => __('core-panel::settings.help.hsts'),
                        'is_localized' => false,
                        'is_public' => false,
                        'label' => __('core-panel::settings.fields.hsts'),
                        'rules' => ['nullable', 'string', 'max:255'],
                        'type' => 'text',
                    ],
                    'permissions_policy' => [
                        'default' => (string) config('core-panel.security.headers.permissions_policy', 'accelerometer=(), camera=(), geolocation=(), gyroscope=(), microphone=(), payment=(), usb=()'),
                        'help' => __('core-panel::settings.help.permissions_policy'),
                        'is_localized' => false,
                        'is_public' => false,
                        'label' => __('core-panel::settings.fields.permissions_policy'),
                        'rules' => ['nullable', 'string', 'max:500'],
                        'type' => 'text',
                    ],
                    'referrer_policy' => [
                        'default' => (string) config('core-panel.security.headers.referrer_policy', 'strict-origin-when-cross-origin'),
                        'help' => __('core-panel::settings.help.referrer_policy'),
                        'is_localized' => false,
                        'is_public' => false,
                        'label' => __('core-panel::settings.fields.referrer_policy'),
                        'rules' => ['required', 'string', 'max:255'],
                        'type' => 'text',
                    ],
                ],
                'label' => __('core-panel::settings.groups.security'),
            ],
            'storage' => [
                'description' => __('core-panel::settings.descriptions.storage'),
                'fields' => [
                    'disk' => [
                        'default' => (string) config('core-panel.files.disk', 'public'),
                        'help' => __('core-panel::settings.help.disk'),
                        'is_localized' => false,
                        'is_public' => false,
                        'label' => __('core-panel::settings.fields.disk'),
                        'rules' => ['required', 'string', 'max:120'],
                        'type' => 'text',
                    ],
                ],
                'label' => __('core-panel::settings.groups.storage'),
            ],
            'ui' => [
                'description' => __('core-panel::settings.descriptions.ui'),
                'fields' => [
                    'show_app_footer' => [
                        'default' => (bool) config('core-panel.ui.show_footer', true),
                        'help' => __('core-panel::settings.help.show_app_footer'),
                        'is_localized' => false,
                        'is_public' => true,
                        'label' => __('core-panel::settings.fields.show_app_footer'),
                        'rules' => ['required', 'boolean'],
                        'type' => 'boolean',
                    ],
                    'layout_density' => [
                        'default' => 'comfortable',
                        'help' => __('core-panel::settings.help.layout_density'),
                        'is_localized' => false,
                        'is_public' => true,
                        'label' => __('core-panel::settings.fields.layout_density'),
                        'options' => self::options([
                            'comfortable' => __('core-panel::settings.options.layout_density.comfortable'),
                            'compact' => __('core-panel::settings.options.layout_density.compact'),
                            'spacious' => __('core-panel::settings.options.layout_density.spacious'),
                        ]),
                        'rules' => ['required', 'string', 'in:comfortable,compact,spacious'],
                        'type' => 'select',
                    ],
                    'primary_color_token' => [
                        'default' => '#1ab88f',
                        'help' => __('core-panel::settings.help.primary_color_token'),
                        'is_localized' => false,
                        'is_public' => true,
                        'label' => __('core-panel::settings.fields.primary_color_token'),
                        'rules' => ['required', 'string', 'regex:/^#?[0-9A-Fa-f]{6}$/'],
                        'type' => 'text',
                    ],
                    'radius_token' => [
                        'default' => 'md',
                        'help' => __('core-panel::settings.help.radius_token'),
                        'is_localized' => false,
                        'is_public' => true,
                        'label' => __('core-panel::settings.fields.radius_token'),
                        'options' => self::options([
                            'none' => __('core-panel::settings.options.radius_token.none'),
                            'sm' => __('core-panel::settings.options.radius_token.sm'),
                            'md' => __('core-panel::settings.options.radius_token.md'),
                            'lg' => __('core-panel::settings.options.radius_token.lg'),
                            'xl' => __('core-panel::settings.options.radius_token.xl'),
                        ]),
                        'rules' => ['required', 'string', 'in:none,sm,md,lg,xl'],
                        'type' => 'select',
                    ],
                ],
                'label' => __('core-panel::settings.groups.ui'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function group(string $group): ?array
    {
        return self::definitions()[$group] ?? null;
    }

    /**
     * @return list<string>
     */
    public static function groupKeys(): array
    {
        return array_keys(self::definitions());
    }

    /**
     * @param  array<string, string>  $options
     * @return list<array{label:string,value:string}>
     */
    private static function options(array $options): array
    {
        return collect($options)
            ->map(static fn (string $label, string $value): array => [
                'label' => $label,
                'value' => $value,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{label:string,value:string}>
     */
    private static function localeOptions(): array
    {
        return self::options(self::availableLanguages());
    }

    /**
     * @return list<array{label:string,value:string}>
     */
    private static function timezoneOptions(): array
    {
        return collect(timezone_identifiers_list())
            ->map(static fn (string $timezone): array => [
                'label' => $timezone,
                'value' => $timezone,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private static function availableLanguages(): array
    {
        return SupportedLocales::labels();
    }

    /**
     * @return list<string>
     */
    private static function availableLocaleCodes(): array
    {
        return SupportedLocales::availableCodes();
    }
}
