<?php

declare(strict_types=1);

namespace CorePanel\Models\Concerns;

use Laravel\Passport\HasApiTokens as PassportHasApiTokens;

trait InteractsWithCorePanelPassport
{
    use PassportHasApiTokens;
}
