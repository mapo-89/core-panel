<?php

declare(strict_types=1);

namespace CorePanel\Models\Concerns;

trait DeterminesCorePanelAdministrationAccess
{
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super-admin');
    }
}
