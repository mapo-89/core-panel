<?php

declare(strict_types=1);

namespace CorePanel\Models\Concerns;

trait HasCorePanelUserStatus
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_BLOCKED = 'blocked';

    public function corePanelUserStatus(): string
    {
        return (string) ($this->getAttribute('status') ?? self::STATUS_ACTIVE);
    }

    public function supportsCorePanelStatus(): bool
    {
        return true;
    }
}
