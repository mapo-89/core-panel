<?php

declare(strict_types=1);

use App\Models\User;
use CorePanel\Models\UserGroup;

$env = static fn (string $key, string $legacyKey, mixed $default = null): mixed => env($key, env($legacyKey, $default));

return [
    'user_model' => $env('USER_MODEL', 'CORE_PANEL_USER_MODEL', User::class),
    'user_group_model' => $env('USER_GROUP_MODEL', 'CORE_PANEL_USER_GROUP_MODEL', UserGroup::class),
    'route_prefix' => $env('ROUTE_PREFIX', 'CORE_PANEL_ROUTE_PREFIX', 'admin'),
    'middleware' => ['web', 'auth'],
    'api' => [
        'version' => $env('API_VERSION', 'CORE_PANEL_API_VERSION', 'v1'),
    ],

    'auth' => [
        'email_verification_enabled' => $env('EMAIL_VERIFICATION_ENABLED', 'CORE_PANEL_EMAIL_VERIFICATION_ENABLED', true),
        'password_reset_enabled' => $env('PASSWORD_RESET_ENABLED', 'CORE_PANEL_PASSWORD_RESET_ENABLED', true),
        'registration_enabled' => $env('REGISTRATION_ENABLED', 'CORE_PANEL_REGISTRATION_ENABLED', false),
        'passport' => [
            'personal_access_clients_enabled' => $env('PASSPORT_PERSONAL_ACCESS_CLIENTS_ENABLED', 'CORE_PANEL_PASSPORT_PERSONAL_ACCESS_CLIENTS_ENABLED', false),
            'token_ttl_minutes' => $env('PASSPORT_TOKEN_TTL_MINUTES', 'CORE_PANEL_PASSPORT_TOKEN_TTL_MINUTES', 15),
            'refresh_token_ttl_days' => $env('PASSPORT_REFRESH_TOKEN_TTL_DAYS', 'CORE_PANEL_PASSPORT_REFRESH_TOKEN_TTL_DAYS', 30),
            'personal_access_token_ttl_days' => $env('PASSPORT_PERSONAL_ACCESS_TOKEN_TTL_DAYS', 'CORE_PANEL_PASSPORT_PERSONAL_ACCESS_TOKEN_TTL_DAYS', 180),
        ],
        'socialite' => [
            'master_provider' => $env('SOCIAL_MASTER_PROVIDER', 'CORE_PANEL_SOCIAL_MASTER_PROVIDER'),
            'providers' => [
                'github' => [
                    'enabled' => $env('SOCIAL_GITHUB_ENABLED', 'CORE_PANEL_SOCIAL_GITHUB_ENABLED', false),
                    'scopes' => [],
                ],
                'google' => [
                    'enabled' => $env('SOCIAL_GOOGLE_ENABLED', 'CORE_PANEL_SOCIAL_GOOGLE_ENABLED', false),
                    'scopes' => [],
                ],
                'microsoft' => [
                    'enabled' => $env('SOCIAL_MICROSOFT_ENABLED', 'CORE_PANEL_SOCIAL_MICROSOFT_ENABLED', false),
                    'scopes' => ['openid', 'profile', 'email', 'offline_access', 'User.Read', 'Mail.Send', 'Calendars.ReadWrite'],
                ],
                'oidc' => [
                    'enabled' => $env('SOCIAL_OIDC_ENABLED', 'CORE_PANEL_SOCIAL_OIDC_ENABLED', false),
                    'label' => $env('OIDC_LABEL', 'CORE_PANEL_OIDC_LABEL', 'OpenID Connect'),
                    'scopes' => ['openid', 'profile', 'email'],
                ],
            ],
        ],
        'two_factor_enabled' => $env('TWO_FACTOR_ENABLED', 'CORE_PANEL_TWO_FACTOR_ENABLED', true),
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
            'csp_report_only' => $env('SECURITY_CSP_REPORT_ONLY', 'CORE_PANEL_SECURITY_CSP_REPORT_ONLY', false),
            'enabled' => $env('SECURITY_HEADERS_ENABLED', 'CORE_PANEL_SECURITY_HEADERS_ENABLED', true),
            'hsts' => 'max-age=31536000; includeSubDomains',
            'permissions_policy' => 'accelerometer=(), camera=(), geolocation=(), gyroscope=(), microphone=(), payment=(), usb=()',
            'referrer_policy' => 'strict-origin-when-cross-origin',
        ],
    ],

    'horizon' => [
        'enabled' => $env('HORIZON_ENABLED', 'CORE_PANEL_HORIZON_ENABLED', true),
    ],

    'administration' => [
        'database_backups' => [
            'enabled' => $env('DATABASE_BACKUPS_ENABLED', 'CORE_PANEL_DATABASE_BACKUPS_ENABLED', true),
            'path' => $env('DATABASE_BACKUPS_PATH', 'CORE_PANEL_DATABASE_BACKUPS_PATH', storage_path('app/backups/database')),
            'timeout' => $env('DATABASE_BACKUPS_TIMEOUT', 'CORE_PANEL_DATABASE_BACKUPS_TIMEOUT', 900),
            'restore_timeout' => $env('DATABASE_BACKUPS_RESTORE_TIMEOUT', 'CORE_PANEL_DATABASE_BACKUPS_RESTORE_TIMEOUT', 900),
            'restore_status_store' => $env('DATABASE_BACKUPS_RESTORE_STATUS_STORE', 'CORE_PANEL_DATABASE_BACKUPS_RESTORE_STATUS_STORE', env('CACHE_STORE', 'file')),
            'import_max_size_kb' => $env('DATABASE_BACKUPS_IMPORT_MAX_SIZE_KB', 'CORE_PANEL_DATABASE_BACKUPS_IMPORT_MAX_SIZE_KB', 1048576),
            'encryption' => [
                'enabled' => $env('DATABASE_BACKUPS_ENCRYPTION_ENABLED', 'CORE_PANEL_DATABASE_BACKUPS_ENCRYPTION_ENABLED', false),
            ],
            'retention' => [
                'mode' => $env('DATABASE_BACKUPS_RETENTION_MODE', 'CORE_PANEL_DATABASE_BACKUPS_RETENTION_MODE', 'count'),
                'count' => $env('DATABASE_BACKUPS_RETENTION_COUNT', 'CORE_PANEL_DATABASE_BACKUPS_RETENTION_COUNT', 30),
                'days' => $env('DATABASE_BACKUPS_RETENTION_DAYS', 'CORE_PANEL_DATABASE_BACKUPS_RETENTION_DAYS', 30),
            ],
            'automatic' => [
                'enabled' => $env('DATABASE_BACKUPS_AUTOMATIC_ENABLED', 'CORE_PANEL_DATABASE_BACKUPS_AUTOMATIC_ENABLED', false),
                'schedule_mode' => $env('DATABASE_BACKUPS_SCHEDULE_MODE', 'CORE_PANEL_DATABASE_BACKUPS_SCHEDULE_MODE', 'daily'),
                'time_mode' => $env('DATABASE_BACKUPS_TIME_MODE', 'CORE_PANEL_DATABASE_BACKUPS_TIME_MODE', 'system'),
                'time' => $env('DATABASE_BACKUPS_TIME', 'CORE_PANEL_DATABASE_BACKUPS_TIME', '02:00'),
                'system_time' => $env('DATABASE_BACKUPS_SYSTEM_TIME', 'CORE_PANEL_DATABASE_BACKUPS_SYSTEM_TIME', '02:00'),
                'timezone' => $env('DATABASE_BACKUPS_TIMEZONE', 'CORE_PANEL_DATABASE_BACKUPS_TIMEZONE', env('APP_TIMEZONE', 'UTC')),
                'weekdays' => [],
            ],
        ],
        'system_updates' => [
            'enabled' => $env('SYSTEM_UPDATES_ENABLED', 'CORE_PANEL_SYSTEM_UPDATES_ENABLED', true),
            'docker_only' => $env('SYSTEM_UPDATES_DOCKER_ONLY', 'CORE_PANEL_SYSTEM_UPDATES_DOCKER_ONLY', true),
            'updater_url' => $env('SYSTEM_UPDATES_UPDATER_URL', 'CORE_PANEL_SYSTEM_UPDATES_UPDATER_URL', ''),
            'token' => $env('SYSTEM_UPDATES_TOKEN', 'CORE_PANEL_SYSTEM_UPDATES_TOKEN', ''),
            'timeout' => $env('SYSTEM_UPDATES_TIMEOUT', 'CORE_PANEL_SYSTEM_UPDATES_TIMEOUT', 10),
            'connect_timeout' => $env('SYSTEM_UPDATES_CONNECT_TIMEOUT', 'CORE_PANEL_SYSTEM_UPDATES_CONNECT_TIMEOUT', 3),
            'check_timeout' => $env('SYSTEM_UPDATES_CHECK_TIMEOUT', 'CORE_PANEL_SYSTEM_UPDATES_CHECK_TIMEOUT', 120),
            'update_timeout' => $env('SYSTEM_UPDATES_UPDATE_TIMEOUT', 'CORE_PANEL_SYSTEM_UPDATES_UPDATE_TIMEOUT', 600),
            'force_update_enabled' => $env('SYSTEM_UPDATES_FORCE_UPDATE_ENABLED', 'CORE_PANEL_SYSTEM_UPDATES_FORCE_UPDATE_ENABLED', false),
            'automatic' => [
                'enabled' => $env('SYSTEM_UPDATES_AUTOMATIC_ENABLED', 'CORE_PANEL_SYSTEM_UPDATES_AUTOMATIC_ENABLED', false),
                'inactive_minutes' => $env('SYSTEM_UPDATES_AUTOMATIC_INACTIVE_MINUTES', 'CORE_PANEL_SYSTEM_UPDATES_AUTOMATIC_INACTIVE_MINUTES', 15),
                'timezone' => $env('SYSTEM_UPDATES_AUTOMATIC_TIMEZONE', 'CORE_PANEL_SYSTEM_UPDATES_AUTOMATIC_TIMEZONE', env('APP_TIMEZONE', 'UTC')),
                'window_start' => $env('SYSTEM_UPDATES_AUTOMATIC_WINDOW_START', 'CORE_PANEL_SYSTEM_UPDATES_AUTOMATIC_WINDOW_START', '02:00'),
                'window_end' => $env('SYSTEM_UPDATES_AUTOMATIC_WINDOW_END', 'CORE_PANEL_SYSTEM_UPDATES_AUTOMATIC_WINDOW_END', '04:00'),
            ],
        ],
    ],

    'activity_log' => [
        'clean_after_days' => 90,
    ],

    'database' => [
        'timestamp_tz_conversion' => [
            'legacy_timezone' => env('CORE_PANEL_TIMESTAMP_LEGACY_TIMEZONE', 'Europe/Berlin'),
            'datasets' => [
                'central' => [
                    'activity_log' => ['created_at', 'updated_at'],
                    'authentication_logs' => ['created_at', 'last_active_at', 'login_at', 'logout_at', 'updated_at'],
                    'failed_jobs' => ['failed_at'],
                    'file_folders' => ['created_at', 'updated_at'],
                    'form_submissions' => ['created_at', 'updated_at'],
                    'form_versions' => ['created_at', 'updated_at'],
                    'forms' => ['created_at', 'updated_at'],
                    'managed_files' => ['created_at', 'updated_at'],
                    'oauth_access_tokens' => ['created_at', 'last_used_at', 'updated_at'],
                    'oauth_clients' => ['created_at', 'updated_at'],
                    'password_reset_tokens' => ['created_at'],
                    'settings' => ['created_at', 'updated_at'],
                    'social_accounts' => ['created_at', 'expires_at', 'updated_at'],
                    'user_group_user' => ['created_at', 'updated_at'],
                    'user_groups' => ['created_at', 'deleted_at', 'updated_at'],
                    'users' => ['created_at', 'deleted_at', 'email_verified_at', 'invitation_accepted_at', 'invited_at', 'two_factor_confirmed_at', 'updated_at'],
                ],
            ],
        ],
    ],
];
