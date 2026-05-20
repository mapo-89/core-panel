<?php

declare(strict_types=1);

namespace CorePanel\Support\Media;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

final readonly class CorePanelMediaPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        return $this->directory($media);
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->directory($media).'conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->directory($media).'responsive-images/';
    }

    private function directory(Media $media): string
    {
        return 'media/'.$media->getKey().'/';
    }
}
