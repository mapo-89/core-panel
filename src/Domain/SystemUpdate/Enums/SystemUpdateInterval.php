<?php

declare(strict_types=1);

namespace CorePanel\Domain\SystemUpdate\Enums;

enum SystemUpdateInterval: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
}
