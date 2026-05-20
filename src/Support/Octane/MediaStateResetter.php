<?php

declare(strict_types=1);

namespace CorePanel\Support\Octane;

use CorePanel\Models\ManagedFile;
use CorePanel\Support\Media\CorePanelMediaPathGenerator;
use Spatie\MediaLibrary\Support\PathGenerator\PathGeneratorFactory;

final class MediaStateResetter
{
    public function reset(): void
    {
        if (! class_exists(PathGeneratorFactory::class)) {
            return;
        }

        PathGeneratorFactory::setCustomPathGenerators(ManagedFile::class, CorePanelMediaPathGenerator::class);
    }
}
