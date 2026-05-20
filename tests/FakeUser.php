<?php

declare(strict_types=1);

namespace CorePanel\Tests;

use CorePanel\Support\Presence\PresenceManager;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

final class FakeUser extends Authenticatable
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

    public function supportsCorePanelStatus(): bool
    {
        return true;
    }
}
