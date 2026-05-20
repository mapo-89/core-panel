<?php

declare(strict_types=1);

namespace CorePanel\Support\Octane;

final readonly class OctaneStateResetter
{
    public function __construct(
        private PermissionCacheResetter $permissions,
        private MediaStateResetter $media,
    ) {}

    public function reset(): void
    {
        $this->permissions->reset();
        $this->media->reset();
    }
}
