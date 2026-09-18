<?php

declare(strict_types=1);

namespace CorePanel\Domain\SystemUpdate\Enums;

enum SystemUpdateWeekday: string
{
    case Friday = 'friday';
    case Monday = 'monday';
    case Saturday = 'saturday';
    case Sunday = 'sunday';
    case Thursday = 'thursday';
    case Tuesday = 'tuesday';
    case Wednesday = 'wednesday';
}
