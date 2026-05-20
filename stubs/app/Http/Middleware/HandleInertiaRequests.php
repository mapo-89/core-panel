<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use CorePanel\Support\Locale\SupportedLocales;
use CorePanel\Support\Presence\PresenceManager;
use CorePanel\Support\Settings\SettingsLogoManager;
use CorePanel\Support\Settings\SettingsRepository;
use CorePanel\Support\Users\UserModelManager;
use Illuminate\Http\Request;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'core-panel::app';

    public function version(Request $request): ?string
    {
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        /** @var array{current?:string,default?:string,fallback?:string,supported?:list<string>,labels?:array<string,string>} $locale */
        $locale = (array) $request->attributes->get('core-panel.locale', []);
        $user = $request->user();
        $firstName = is_string($user?->getAttribute('first_name')) ? $user->getAttribute('first_name') : '';
        $lastName = is_string($user?->getAttribute('last_name')) ? $user->getAttribute('last_name') : '';
        /** @var list<string> $avatarMimeTypes */
        $avatarMimeTypes = array_values((array) config('core-panel.files.avatar.allowed_mime_types', [
            'image/jpeg',
            'image/png',
            'image/webp',
        ]));
        /** @var list<string> $avatarFormatBadges */
        $avatarFormatBadges = $this->formatBadges($avatarMimeTypes);
        /** @var list<string> $logoMimeTypes */
        $logoMimeTypes = array_values((array) config('core-panel.files.logo.allowed_mime_types', [
            'image/jpeg',
            'image/png',
            'image/svg+xml',
            'image/webp',
        ]));
        /** @var list<string> $logoFormatBadges */
        $logoFormatBadges = $this->formatBadges($logoMimeTypes);
        $users = app(UserModelManager::class);
        $presence = app(PresenceManager::class);
        $roleNames = $user === null ? [] : $users->roleNames($user);
        $permissionNames = $users->permissionNames($user);
        $settingsLogo = app(SettingsLogoManager::class);
        $publicSettings = app(SettingsRepository::class)->public();
        $appSubtitle = data_get($publicSettings, 'general.app_subtitle');

        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'appSubtitle' => is_string($appSubtitle)
                ? $appSubtitle
                : (string) __('page-layout.brand_subtitle_default'),
            'appLogo' => fn (): ?string => $settingsLogo->currentUrl(),
            'flash' => [
                'apiToken' => fn (): ?string => $request->session()->get('apiToken'),
                'error' => fn (): ?string => $request->session()->get('error'),
                'info' => fn (): ?string => $request->session()->get('info'),
                'socialAvatarPrompt' => fn (): ?array => $request->session()->get('page-auth.socialite.pending-avatar-sync'),
                'status' => fn (): ?string => $request->session()->get('status'),
                'success' => fn (): ?string => $request->session()->get('success'),
                'warning' => fn (): ?string => $request->session()->get('warning'),
            ],
            'auth' => [
                'user' => $user === null ? null : [
                    'id' => (string) $user->getKey(),
                    'firstName' => $firstName,
                    'lastName' => $lastName,
                    'email' => (string) $user->getAttribute('email'),
                    'avatarUrl' => $users->avatarUrl($user),
                    'locale' => is_string($user->getAttribute('locale')) ? $user->getAttribute('locale') : null,
                    'presenceLastSeenAt' => $presence->lastSeenTimestamp($user),
                    'presenceStatus' => $presence->statusFor($user),
                ],
                'permissions' => $permissionNames,
                'role' => $users->primaryRole($user),
                'roles' => $roleNames,
                'twoFactorEnabled' => $user?->hasEnabledTwoFactorAuthentication() ?? false,
            ],
            'corePanel' => [
                'debug' => (bool) config('app.debug', false),
                'environment' => app()->environment(),
                'isLocal' => app()->environment('local'),
                'name' => config('app.name'),
                'shortName' => 'CorePanel',
                'version' => config('core-panel.app_version'),
                'canRegister' => (bool) config('core-panel.auth.registration_enabled', false),
                'settings' => app(SettingsRepository::class)->public(),
                'uploads' => [
                    'avatar' => [
                        'accept' => implode(',', $avatarMimeTypes),
                        'badges' => [
                            ...$avatarFormatBadges,
                            sprintf('%d MB', (int) floor(((int) config('core-panel.files.avatar.max_upload_size', config('core-panel.files.max_upload_size', 10240))) / 1024)),
                        ],
                        'formatBadges' => $avatarFormatBadges,
                        'maxSizeMb' => (int) floor(((int) config('core-panel.files.avatar.max_upload_size', config('core-panel.files.max_upload_size', 10240))) / 1024),
                        'mimeTypes' => $avatarMimeTypes,
                    ],
                    'logo' => [
                        'accept' => implode(',', $logoMimeTypes),
                        'badges' => [
                            ...$logoFormatBadges,
                            sprintf('%d MB', (int) floor(((int) config('core-panel.files.logo.max_upload_size', 2048)) / 1024)),
                        ],
                        'formatBadges' => $logoFormatBadges,
                        'maxSizeMb' => (int) floor(((int) config('core-panel.files.logo.max_upload_size', 2048)) / 1024),
                        'mimeTypes' => $logoMimeTypes,
                    ],
                ],
            ],
            'locale' => [
                'current' => $locale['current'] ?? app()->currentLocale(),
                'default' => $locale['default'] ?? config('app.locale'),
                'fallback' => $locale['fallback'] ?? config('app.fallback_locale'),
                'supported' => $locale['supported'] ?? SupportedLocales::codes(),
                'labels' => $locale['labels'] ?? SupportedLocales::labelsFor(SupportedLocales::codes()),
            ],
        ];
    }

    /**
     * @param  list<string>  $mimeTypes
     * @return list<string>
     */
    private function formatBadges(array $mimeTypes): array
    {
        return collect($mimeTypes)
            ->map(static function (string $mimeType): string {
                return match ($mimeType) {
                    'image/jpeg' => 'JPG',
                    'image/png' => 'PNG',
                    'image/webp' => 'WEBP',
                    'image/svg+xml' => 'SVG',
                    default => strtoupper((string) preg_replace('/^image\//', '', $mimeType)),
                };
            })
            ->unique()
            ->values()
            ->all();
    }
}
