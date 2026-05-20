<?php

declare(strict_types=1);

namespace CorePanel\Models;

use Spatie\MediaLibrary\MediaCollections\Models\Media as BaseMedia;

class Media extends BaseMedia
{
    public function getConnectionName(): ?string
    {
        return parent::getConnectionName();
    }
}
