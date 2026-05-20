<?php

declare(strict_types=1);

namespace CorePanel\Support\Socialite;

use CorePanel\Domains\SocialAccount\DTOs\SocialAccountData;
use CorePanel\Support\Settings\SettingsRepository;
use Illuminate\Contracts\Auth\Authenticatable;

final readonly class SocialiteProviderRegistry
{
    public function __construct(
        private SocialAccountStore $accounts,
        private SettingsRepository $settings,
    ) {}

    /**
     * @return list<array{
     *     enabled:bool,
     *     icon:?string,
     *     isMaster:bool,
     *     label:string,
     *     provider:string
     * }>
     */
    public function enabledProviders(bool $useRuntimeSettings = true): array
    {
        return collect($this->definitions())
            ->filter(fn (array $definition, string $provider): bool => $this->isEnabled($provider, $useRuntimeSettings))
            ->map(fn (array $definition, string $provider): array => [
                'enabled' => true,
                'icon' => $definition['icon'],
                'isMaster' => $this->isMasterProvider($provider, $useRuntimeSettings),
                'label' => $definition['label'],
                'provider' => $provider,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{
     *     avatarUrl:?string,
     *     connectedAt:?string,
     *     expiresAt:?string,
     *     id:string,
     *     label:string,
     *     provider:string,
     *     providerEmail:?string
     * }>
     */
    public function linkedAccountsFor(?Authenticatable $user): array
    {
        if ($user === null) {
            return [];
        }

        $accounts = $this->accounts->forUser($user);

        return $accounts
            ->map(fn ($account): array => SocialAccountData::fromModel(
                $account,
                $this->labelFor((string) $account->getAttribute('provider')),
            )->toArray())
            ->values()
            ->all();
    }

    public function isConfigured(string $provider): bool
    {
        return filled(config("services.{$provider}.client_id"))
            && filled(config("services.{$provider}.client_secret"))
            && filled(config("services.{$provider}.redirect"));
    }

    public function isEnabled(string $provider, bool $useRuntimeSettings = true): bool
    {
        if (! array_key_exists($provider, $this->definitions()) || ! $this->isConfigured($provider)) {
            return false;
        }

        $default = (bool) config("core-panel.auth.socialite.providers.{$provider}.enabled", false);

        if (! $useRuntimeSettings) {
            return $default;
        }

        return (bool) $this->settings->get('auth', "social_{$provider}_enabled", $default);
    }

    public function isMasterProvider(string $provider, bool $useRuntimeSettings = true): bool
    {
        return $this->masterProvider($useRuntimeSettings) === $provider;
    }

    public function isSupported(string $provider): bool
    {
        return array_key_exists($provider, $this->definitions());
    }

    public function labelFor(string $provider): string
    {
        return $this->definitions()[$provider]['label'] ?? ucfirst($provider);
    }

    public function masterProvider(bool $useRuntimeSettings = true): ?string
    {
        $default = config('core-panel.auth.socialite.master_provider');

        if (! $useRuntimeSettings) {
            return is_string($default) && array_key_exists($default, $this->definitions()) ? $default : null;
        }

        $provider = $this->settings->get('auth', 'social_master_provider', $default);

        return is_string($provider) && array_key_exists($provider, $this->definitions()) ? $provider : null;
    }

    /**
     * @return list<string>
     */
    public function scopesFor(string $provider): array
    {
        /** @var list<string> $scopes */
        $scopes = array_values((array) config("core-panel.auth.socialite.providers.{$provider}.scopes", []));

        return $scopes;
    }

    /**
     * @return array<string, array{icon:?string,label:string}>
     */
    private function definitions(): array
    {
        return [
            'github' => [
                'icon' => 'pi pi-github',
                'label' => __('page-auth.socialite.providers.github'),
            ],
            'google' => [
                'icon' => 'pi pi-google',
                'label' => __('page-auth.socialite.providers.google'),
            ],
            'microsoft' => [
                'icon' => 'pi pi-microsoft',
                'label' => __('page-auth.socialite.providers.microsoft'),
            ],
        ];
    }
}
