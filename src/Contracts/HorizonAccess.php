<?php

declare(strict_types=1);

namespace CorePanel\Contracts;

interface HorizonAccess
{
    public function allows(mixed $user): bool;
}
