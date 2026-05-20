<?php

declare(strict_types=1);

namespace CorePanel\Support\Socialite;

use CorePanel\Models\SocialAccount;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Stringable;

class SocialAccountStore
{
    public function deleteForUser(Authenticatable $user, string $provider): ?SocialAccount
    {
        $account = SocialAccount::query()
            ->where('provider', $provider)
            ->where('user_id', (string) $user->getAuthIdentifier())
            ->first();

        if ($account instanceof SocialAccount) {
            $account->delete();
        }

        return $account;
    }

    public function findByProviderUser(string $provider, string $providerUserId): ?SocialAccount
    {
        return SocialAccount::query()
            ->where('provider', $provider)
            ->where('provider_user_id', $providerUserId)
            ->first();
    }

    /**
     * @return EloquentCollection<int, SocialAccount>
     */
    public function forUser(Authenticatable $user): EloquentCollection
    {
        return SocialAccount::query()
            ->where('user_id', (string) $user->getAuthIdentifier())
            ->orderBy('provider')
            ->get();
    }

    public function forUserAndProvider(Authenticatable $user, string $provider): ?SocialAccount
    {
        return SocialAccount::query()
            ->where('provider', $provider)
            ->where('user_id', (string) $user->getAuthIdentifier())
            ->first();
    }

    public function resolveUser(SocialAccount $account): ?Authenticatable
    {
        $user = $account->user()->first();

        return $user instanceof Authenticatable ? $user : null;
    }

    public function upsertForUser(Authenticatable $user, string $provider, mixed $providerUser): SocialAccount
    {
        return $this->upsertForUserWithAttributes($user, $provider, [
            'avatar_url' => $this->avatarUrlFromProviderUser($providerUser),
            'expires_in' => isset($providerUser->expiresIn) && is_numeric($providerUser->expiresIn)
                ? (int) $providerUser->expiresIn
                : null,
            'provider_email' => $providerUser->getEmail(),
            'provider_user_id' => (string) $providerUser->getId(),
            'refresh_token' => $providerUser->refreshToken ?? null,
            'token' => $providerUser->token ?? null,
        ]);
    }

    /**
     * @param  array{
     *     avatar_url?: ?string,
     *     expires_in?: ?int,
     *     provider_email?: ?string,
     *     provider_user_id: string,
     *     refresh_token?: ?string,
     *     token?: ?string
     * }  $attributes
     */
    public function upsertForUserWithAttributes(Authenticatable $user, string $provider, array $attributes): SocialAccount
    {
        /** @var Model $userModel */
        $userModel = $user;

        /** @var SocialAccount $account */
        $account = SocialAccount::query()->updateOrCreate(
            [
                'provider' => $provider,
                'provider_user_id' => $attributes['provider_user_id'],
            ],
            [
                'avatar_url' => $attributes['avatar_url'] ?? null,
                'expires_at' => isset($attributes['expires_in']) && is_int($attributes['expires_in'])
                    ? now()->addSeconds($attributes['expires_in'])
                    : null,
                'provider_email' => $attributes['provider_email'] ?? null,
                'refresh_token_encrypted' => $attributes['refresh_token'] ?? null,
                'token_encrypted' => $attributes['token'] ?? null,
                'user_id' => (string) $userModel->getKey(),
            ],
        );

        return $account;
    }

    public function avatarUrlFromProviderUser(mixed $providerUser): ?string
    {
        if (! is_object($providerUser) || ! method_exists($providerUser, 'getAvatar')) {
            return null;
        }

        $avatar = $providerUser->getAvatar();

        if (is_string($avatar)) {
            $normalizedAvatar = trim($avatar);

            return $normalizedAvatar !== '' ? $normalizedAvatar : null;
        }

        if ($avatar instanceof Stringable) {
            $normalizedAvatar = trim((string) $avatar);

            return $normalizedAvatar !== '' ? $normalizedAvatar : null;
        }

        return null;
    }
}
