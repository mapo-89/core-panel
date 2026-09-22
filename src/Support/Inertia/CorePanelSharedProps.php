<?php

declare(strict_types=1);

namespace CorePanel\Support\Inertia;

use CorePanel\Support\Locale\SupportedLocales;
use CorePanel\Support\Presence\PresenceManager;
use CorePanel\Support\Settings\SettingsLogoManager;
use CorePanel\Support\Settings\SettingsRepository;
use CorePanel\Support\Users\UserModelManager;
use CorePanel\Support\Version\AppVersionRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

final readonly class CorePanelSharedProps
{
    public function __construct(
        private UserModelManager $users,
        private PresenceManager $presence,
        private SettingsLogoManager $settingsLogo,
        private SettingsRepository $settings,
        private AppVersionRepository $version,
    ) {}

    /** @return array<string, mixed> */
    public function forRequest(Request $request): array
    {
        /** @var array{current?:string,default?:string,fallback?:string,supported?:list<string>,labels?:array<string,string>} $locale */
        $locale = (array) $request->attributes->get('core-panel.locale', []);
        $authenticatedUser = $request->user();
        $user = $authenticatedUser instanceof Model ? $authenticatedUser : null;
        $publicSettings = $this->settings->public();
        $appName = data_get($publicSettings, 'general.app_name');
        $appSubtitle = data_get($publicSettings, 'general.app_subtitle');
        $avatarMimeTypes = array_values((array) config('core-panel.files.avatar.allowed_mime_types', ['image/jpeg', 'image/png', 'image/webp']));
        $logoMimeTypes = array_values((array) config('core-panel.files.logo.allowed_mime_types', ['image/jpeg', 'image/png', 'image/svg+xml', 'image/webp']));
        $avatarFormatBadges = $this->formatBadges($avatarMimeTypes);
        $logoFormatBadges = $this->formatBadges($logoMimeTypes);

        return [
            'appName' => is_string($appName) && $appName !== '' ? $appName : config('app.name'),
            'appSubtitle' => Arr::has($publicSettings, 'general.app_subtitle')
                ? (is_string($appSubtitle) ? $appSubtitle : null)
                : (string) __('page-layout.brand_subtitle_default'),
            'appLogo' => fn (): ?string => $this->settingsLogo->currentUrl(),
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
                    'id' => (string) $user->getAuthIdentifier(),
                    'firstName' => is_string($user->getAttribute('first_name')) ? $user->getAttribute('first_name') : '',
                    'lastName' => is_string($user->getAttribute('last_name')) ? $user->getAttribute('last_name') : '',
                    'email' => (string) $user->getAttribute('email'),
                    'avatarUrl' => $this->users->avatarUrl($user),
                    'locale' => is_string($user->getAttribute('locale')) ? $user->getAttribute('locale') : null,
                    'presenceLastSeenAt' => $this->presence->lastSeenTimestamp($user),
                    'presenceStatus' => $this->presence->statusFor($user),
                ],
                'permissions' => $this->users->permissionNames($user),
                'role' => $this->users->primaryRole($user),
                'roles' => $user === null ? [] : $this->users->roleNames($user),
                'twoFactorEnabled' => $user !== null && method_exists($user, 'hasEnabledTwoFactorAuthentication')
                    ? $user->hasEnabledTwoFactorAuthentication()
                    : false,
            ],
            'corePanel' => [
                'debug' => (bool) config('app.debug', false),
                'environment' => app()->environment(),
                'isLocal' => app()->environment('local'),
                'name' => is_string($appName) && $appName !== '' ? $appName : config('app.name'),
                'shortName' => 'CorePanel',
                'version' => $this->version->releaseVersion(),
                'canRegister' => (bool) config('core-panel.auth.registration_enabled', false),
                'settings' => $publicSettings,
                'uploads' => [
                    'avatar' => $this->uploadProps($avatarMimeTypes, $avatarFormatBadges, (int) config('core-panel.files.avatar.max_upload_size', config('core-panel.files.max_upload_size', 10240))),
                    'logo' => $this->uploadProps($logoMimeTypes, $logoFormatBadges, (int) config('core-panel.files.logo.max_upload_size', 2048)),
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
     * @param  list<string>  $formatBadges
     * @return array{accept:string,badges:list<string>,formatBadges:list<string>,maxSizeMb:int,mimeTypes:list<string>}
     */
    private function uploadProps(array $mimeTypes, array $formatBadges, int $maxSizeKb): array
    {
        $maxSizeMb = (int) floor($maxSizeKb / 1024);

        return ['accept' => implode(',', $mimeTypes), 'badges' => [...$formatBadges, sprintf('%d MB', $maxSizeMb)], 'formatBadges' => $formatBadges, 'maxSizeMb' => $maxSizeMb, 'mimeTypes' => $mimeTypes];
    }

    /**
     * @param  list<string>  $mimeTypes
     * @return list<string>
     */
    private function formatBadges(array $mimeTypes): array
    {
        return collect($mimeTypes)->map(static fn (string $mimeType): string => match ($mimeType) {
            'image/jpeg' => 'JPG', 'image/png' => 'PNG', 'image/webp' => 'WEBP', 'image/svg+xml' => 'SVG',
            default => strtoupper((string) preg_replace('/^image\//', '', $mimeType)),
        })->unique()->values()->all();
    }
}
