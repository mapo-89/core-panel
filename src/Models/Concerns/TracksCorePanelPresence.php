<?php

declare(strict_types=1);

namespace CorePanel\Models\Concerns;

use CorePanel\Support\Presence\PresenceManager;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait TracksCorePanelPresence
{
    public const PRESENCE_ONLINE = 'online';

    public const PRESENCE_AWAY = 'away';

    public const PRESENCE_OFFLINE = 'offline';

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

    /** @return Attribute<string, never> */
    protected function presenceStatus(): Attribute
    {
        return Attribute::make(get: fn (): string => app(PresenceManager::class)->statusFor($this));
    }
}
