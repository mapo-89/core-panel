<?php

declare(strict_types=1);

namespace CorePanel\Domain\SystemUpdate\Enums;

enum SystemUpdateMode: string
{
    case Check = 'check';
    case Install = 'install';
}
