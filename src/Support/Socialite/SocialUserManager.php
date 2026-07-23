<?php

declare(strict_types=1);

namespace CorePanel\Support\Socialite;

use CorePanel\Support\Users\UserModelManager;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SocialUserManager
{
    public function __construct(private readonly UserModelManager $users) {}

    public function createFromProviderUser(string $provider, mixed $providerUser): Authenticatable
    {
        return $this->createFromProviderPayload($provider, [
            'email' => (string) $providerUser->getEmail(),
            'name' => (string) ($providerUser->getName() ?: $providerUser->getNickname() ?: Str::before((string) $providerUser->getEmail(), '@')),
        ]);
    }

    /**
     * @param  array{email:string, name:?string}  $providerUser
     */
    public function createFromProviderPayload(string $provider, array $providerUser): Authenticatable
    {
        $user = $this->users->newModel();
        $displayName = trim((string) ($providerUser['name'] ?: Str::before((string) $providerUser['email'], '@')));
        $nameParts = $this->users->splitName($displayName !== '' ? $displayName : 'Social User');

        $attributes = [
            'email' => (string) $providerUser['email'],
            'password' => Hash::make((string) Str::password(32)),
        ];

        if ($this->users->hasColumn('first_name')) {
            $attributes['first_name'] = $nameParts['first_name'] !== '' ? $nameParts['first_name'] : 'Social';
        }

        if ($this->users->hasColumn('last_name')) {
            $attributes['last_name'] = $nameParts['last_name'] !== '' ? $nameParts['last_name'] : ucfirst($provider);
        }

        if ($this->users->hasColumn('name')) {
            $attributes['name'] = trim($displayName !== '' ? $displayName : implode(' ', [
                $attributes['first_name'] ?? 'Social',
                $attributes['last_name'] ?? 'User',
            ]));
        }

        if ($this->users->supportsEmailVerification()) {
            $attributes['email_verified_at'] = now();
        }

        if ($this->users->supportsStatus()) {
            $attributes['status'] = 'active';
        }

        if ($this->users->hasColumn('requires_password_setup')) {
            $attributes['requires_password_setup'] = $provider === 'microsoft';
        }

        $user->forceFill($attributes);
        $user->save();

        return $user;
    }

    public function findByEmail(string $email): ?Authenticatable
    {
        $user = $this->users->query()->where('email', $email)->first();

        return $user instanceof Authenticatable ? $user : null;
    }
}
