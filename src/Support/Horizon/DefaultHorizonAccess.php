<?php

declare(strict_types=1);

namespace CorePanel\Support\Horizon;

use CorePanel\Contracts\HorizonAccess;

final class DefaultHorizonAccess implements HorizonAccess
{
    public function allows(mixed $user): bool
    {
        if (! is_object($user)) {
            return false;
        }

        return (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin())
            || (method_exists($user, 'can') && ($user->can('horizon.view') || $user->can('core-panel.view-horizon')));
    }
}
