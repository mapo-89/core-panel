<?php

declare(strict_types=1);

namespace CorePanel\Models;

use CorePanel\Models\Concerns\PreservesDateTimeOffsets;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

class Activity extends SpatieActivity
{
    use PreservesDateTimeOffsets;
}
