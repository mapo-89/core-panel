<?php

declare(strict_types=1);

namespace CorePanel\Database\Seeders;

use Illuminate\Database\Eloquent\Model;

final class RolePermissionMetadata
{
    /**
     * @return list<string>
     */
    public static function seededPermissions(Model $role): array
    {
        $value = $role->getAttribute('core_panel_seeded_permissions');

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map('strval', $value),
            static fn (string $permission): bool => $permission !== '',
        ));
    }
}
