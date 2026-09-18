<?php

declare(strict_types=1);

namespace CorePanel\Contracts;

interface SystemUpdateSettingsAccess
{
    public function allows(): bool;
}
