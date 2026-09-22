<?php

declare(strict_types=1);

namespace CorePanel\Models\Concerns;

use Illuminate\Support\Carbon;

trait HasCorePanelInvitationStatus
{
    public function requiresPasswordSetup(): bool
    {
        return (bool) ($this->getAttribute('requires_password_setup') ?? false);
    }

    public function invitationExpiresAt(): ?Carbon
    {
        $invitedAt = $this->getAttribute('invited_at');

        return $invitedAt instanceof Carbon
            ? $invitedAt->copy()->addMinutes((int) config('auth.passwords.users.expire', 60))
            : null;
    }

    public function invitationStatus(): string
    {
        if (! $this->getAttribute('invited_at') instanceof Carbon) {
            return 'none';
        }

        if ($this->requiresPasswordSetup()) {
            return $this->invitationExpiresAt()?->isPast() === true ? 'expired' : 'pending';
        }

        return $this->getAttribute('invitation_accepted_at') instanceof Carbon ? 'accepted' : 'none';
    }
}
