<?php

declare(strict_types=1);

namespace CorePanel\Tests;

use CorePanel\Support\Presence\PresenceManager;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

final class FakeUser extends Authenticatable implements HasLocalePreference
{
    use CanResetPassword;
    use HasApiTokens;
    use HasRoles;
    use HasUuids;
    use Notifiable;

    protected $table = 'users';

    protected $guarded = [];

    protected string $guard_name = 'web';

    public function corePanelUserStatus(): string
    {
        return (string) ($this->getAttribute('status') ?? 'active');
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

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super-admin');
    }

    public function requiresPasswordSetup(): bool
    {
        return (bool) ($this->getAttribute('requires_password_setup') ?? false);
    }

    public function invitationExpiresAt(): ?Carbon
    {
        $invitedAt = $this->getAttribute('invited_at');

        if (! $invitedAt instanceof Carbon) {
            return null;
        }

        return $invitedAt->copy()->addMinutes((int) config('auth.passwords.users.expire', 60));
    }

    public function invitationStatus(): string
    {
        if (! $this->getAttribute('invited_at') instanceof Carbon) {
            return 'none';
        }

        if ($this->requiresPasswordSetup()) {
            $invitationExpiresAt = $this->invitationExpiresAt();

            return $invitationExpiresAt instanceof Carbon && $invitationExpiresAt->isPast()
                ? 'expired'
                : 'pending';
        }

        return $this->getAttribute('invitation_accepted_at') instanceof Carbon
            ? 'accepted'
            : 'none';
    }

    public function preferredLocale(): ?string
    {
        $locale = $this->getAttribute('locale');

        return is_string($locale) && $locale !== '' ? $locale : null;
    }

    public function supportsCorePanelStatus(): bool
    {
        return true;
    }
}
