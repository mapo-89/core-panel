<?php

declare(strict_types=1);

use App\Models\User;
use CorePanel\Models\UserGroup;

return [
    'user_model' => env('CORE_PANEL_USER_MODEL', User::class),
    'user_group_model' => env('CORE_PANEL_USER_GROUP_MODEL', UserGroup::class),
    'route_prefix' => env('CORE_PANEL_ROUTE_PREFIX', 'admin'),
    'middleware' => ['web', 'auth'],
    'api' => [
        'version' => env('CORE_PANEL_API_VERSION', 'v1'),
    ],

    'auth' => [
        'email_verification_enabled' => env('CORE_PANEL_EMAIL_VERIFICATION_ENABLED', true),
        'password_reset_enabled' => env('CORE_PANEL_PASSWORD_RESET_ENABLED', true),
        'registration_enabled' => env('CORE_PANEL_REGISTRATION_ENABLED', false),
        'passport' => [
            'personal_access_clients_enabled' => env('CORE_PANEL_PASSPORT_PERSONAL_ACCESS_CLIENTS_ENABLED', false),
            'token_ttl_minutes' => env('CORE_PANEL_PASSPORT_TOKEN_TTL_MINUTES', 15),
            'refresh_token_ttl_days' => env('CORE_PANEL_PASSPORT_REFRESH_TOKEN_TTL_DAYS', 30),
            'personal_access_token_ttl_days' => env('CORE_PANEL_PASSPORT_PERSONAL_ACCESS_TOKEN_TTL_DAYS', 180),
        ],
        'socialite' => [
            'master_provider' => env('CORE_PANEL_SOCIAL_MASTER_PROVIDER'),
            'providers' => [
                'github' => [
                    'enabled' => env('CORE_PANEL_SOCIAL_GITHUB_ENABLED', false),
                    'scopes' => [],
                ],
                'google' => [
                    'enabled' => env('CORE_PANEL_SOCIAL_GOOGLE_ENABLED', false),
                    'scopes' => [],
                ],
                'microsoft' => [
                    'enabled' => env('CORE_PANEL_SOCIAL_MICROSOFT_ENABLED', false),
                    'scopes' => ['openid', 'profile', 'email', 'offline_access', 'User.Read', 'Mail.Send', 'Calendars.ReadWrite'],
                ],
            ],
        ],
        'two_factor_enabled' => env('CORE_PANEL_TWO_FACTOR_ENABLED', true),
    ],

    'i18n' => [
        'default_locale' => env('APP_LOCALE', 'de'),
        'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),
        'supported_locales' => ['de', 'en'],
    ],

    'ui' => [
        'library' => 'primevue',
        'theme' => 'core-panel',
    ],

    'files' => [
        'allowed_mime_types' => ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'],
        'avatar' => [
            'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
            'max_upload_size' => 10240,
        ],
        'disk' => env('FILESYSTEM_DISK', 'public'),
        'logo' => [
            'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/svg+xml', 'image/webp'],
            'directory' => 'branding',
            'disk' => env('FILESYSTEM_DISK', 'public'),
            'max_upload_size' => 2048,
        ],
        'max_upload_size' => 10240,
    ],

    'security' => [
        'headers' => [
            'csp' => "default-src 'self'; frame-ancestors 'self'; img-src 'self' data: blob: https:; object-src 'none'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'",
            'csp_report_only' => env('CORE_PANEL_SECURITY_CSP_REPORT_ONLY', false),
            'enabled' => env('CORE_PANEL_SECURITY_HEADERS_ENABLED', true),
            'hsts' => 'max-age=31536000; includeSubDomains',
            'permissions_policy' => 'accelerometer=(), camera=(), geolocation=(), gyroscope=(), microphone=(), payment=(), usb=()',
            'referrer_policy' => 'strict-origin-when-cross-origin',
        ],
    ],

    'horizon' => [
        'enabled' => env('CORE_PANEL_HORIZON_ENABLED', true),
    ],

    'activity_log' => [
        'clean_after_days' => 90,
    ],
];
