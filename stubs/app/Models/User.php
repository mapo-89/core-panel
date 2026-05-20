<?php

declare(strict_types=1);

namespace App\Models;

use CorePanel\Models\SocialAccount;
use CorePanel\Support\Presence\PresenceManager;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Passport\HasApiTokens as PassportHasApiTokens;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Permission\Traits\HasRoles;

final class User extends Authenticatable implements HasLocalePreference, HasMedia, MustVerifyEmail
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_BLOCKED = 'blocked';

    public const PRESENCE_ONLINE = 'online';

    public const PRESENCE_AWAY = 'away';

    public const PRESENCE_OFFLINE = 'offline';

    private const PRESENCE_ONLINE_WINDOW_MINUTES = 5;

    private const PRESENCE_AWAY_WINDOW_MINUTES = 15;

    use HasFactory;
    use HasRoles;
    use HasUuids;
    use InteractsWithMedia;
    use Notifiable;
    use PassportHasApiTokens {
        PassportHasApiTokens::clients as passportClients;
        PassportHasApiTokens::oauthApps as passportOauthApps;
    }
    use SoftDeletes;
    use TwoFactorAuthenticatable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'locale',
        'password',
        'requires_password_setup',
        'status',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'requires_password_setup' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function userGroups(): BelongsToMany
    {
        /** @var class-string<UserGroup> $userGroupModel */
        $userGroupModel = (string) config('core-panel.user_group_model', UserGroup::class);

        return $this->belongsToMany($userGroupModel, 'user_group_user')
            ->withTimestamps()
            ->orderBy('name');
    }

    public function clients(): HasMany
    {
        return $this->passportClients();
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function microsoftAccount(): HasOne
    {
        return $this->hasOne(SocialAccount::class)->where('provider', 'microsoft');
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super-admin');
    }

    public function requiresPasswordSetup(): bool
    {
        return (bool) ($this->getAttribute('requires_password_setup') ?? false);
    }

    public function preferredLocale(): ?string
    {
        $locale = $this->getAttribute('locale');

        return is_string($locale) && $locale !== '' ? $locale : null;
    }

    public function oauthApps(): mixed
    {
        return $this->passportOauthApps();
    }

    public function presenceCacheKey(): string
    {
        return 'user-presence:'.$this->getKey();
    }

    public function corePanelPresenceStatus(): string
    {
        return app(PresenceManager::class)->statusFor($this);
    }

    public function corePanelPresenceLastSeenAt(): ?int
    {
        return app(PresenceManager::class)->lastSeenTimestamp($this);
    }

    public function corePanelUserStatus(): string
    {
        return (string) ($this->getAttribute('status') ?? self::STATUS_ACTIVE);
    }

    public function supportsCorePanelStatus(): bool
    {
        return true;
    }

    protected function presenceStatus(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                return app(PresenceManager::class)->statusFor($this);
            },
        );
    }
}
