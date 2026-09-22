<?php

declare(strict_types=1);

namespace App\Models;

use CorePanel\Models\Concerns\DeterminesCorePanelAdministrationAccess;
use CorePanel\Models\Concerns\HasCorePanelGroups;
use CorePanel\Models\Concerns\HasCorePanelInvitationStatus;
use CorePanel\Models\Concerns\HasCorePanelSocialAccounts;
use CorePanel\Models\Concerns\HasCorePanelUserStatus;
use CorePanel\Models\Concerns\InteractsWithCorePanelPassport;
use CorePanel\Models\Concerns\TracksCorePanelPresence;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Permission\Traits\HasRoles;

final class User extends Authenticatable implements HasLocalePreference, HasMedia, MustVerifyEmail, OAuthenticatable
{
    use DeterminesCorePanelAdministrationAccess;
    use HasCorePanelGroups;
    use HasCorePanelInvitationStatus;
    use HasCorePanelSocialAccounts;
    use HasCorePanelUserStatus;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use HasUuids;
    use InteractsWithCorePanelPassport;
    use InteractsWithMedia;
    use Notifiable;
    use SoftDeletes;
    use TracksCorePanelPresence;
    use TwoFactorAuthenticatable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'invitation_accepted_at',
        'invited_at',
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
            'invitation_accepted_at' => 'datetime',
            'invited_at' => 'datetime',
            'password' => 'hashed',
            'requires_password_setup' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function preferredLocale(): ?string
    {
        $locale = $this->getAttribute('locale');

        return is_string($locale) && $locale !== '' ? $locale : null;
    }
}
