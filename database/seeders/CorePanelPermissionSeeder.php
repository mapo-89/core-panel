<?php

declare(strict_types=1);

namespace CorePanel\Database\Seeders;

use CorePanel\Support\Permissions\CorePanelAccess;
use CorePanel\Support\Permissions\PermissionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

final class CorePanelPermissionSeeder extends Seeder
{
    public bool $fresh = false;

    public function run(PermissionService $permissions, CorePanelAccess $access): void
    {
        if (! $this->runningDuringInstaller()) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } elseif (app()->resolved(PermissionRegistrar::class)) {
            app(PermissionRegistrar::class)->clearPermissionsCollection();
        }

        $managedPermissions = $access->managedPermissions();

        foreach ($managedPermissions as $name) {
            $permissions->createPermission([
                'name' => $name,
                'guard_name' => config('auth.defaults.guard', 'web'),
            ]);
        }

        $removedPermissions = $this->removeOrphanedPermissions($managedPermissions, $permissions);

        $permissions->resetCache();
        $this->syncDefaultRoles($permissions, $access, $managedPermissions);

        $this->command?->info(sprintf(
            'Managed access synchronized: %d permissions configured, %d orphaned removed.',
            count($managedPermissions),
            $removedPermissions,
        ));
    }

    private function runningDuringInstaller(): bool
    {
        return (bool) config('core-panel.runtime.installing', false);
    }

    /**
     * @param  list<string>  $managedPermissions
     */
    private function syncDefaultRoles(
        PermissionService $permissions,
        CorePanelAccess $access,
        array $managedPermissions,
    ): void {
        foreach ($access->defaultRoles() as $roleName => $definition) {
            $role = $permissions->firstOrCreateRole([
                'name' => $roleName,
                'guard_name' => config('auth.defaults.guard', 'web'),
            ]);

            $configuredPermissions = $access->rolePermissions($roleName);
            $resolvedPermissions = array_values(array_intersect($configuredPermissions, $managedPermissions));
            $seededPermissions = RolePermissionMetadata::seededPermissions($role);
            $isNewRole = $seededPermissions === [] && $role->permissions()->count() === 0;

            if ($isNewRole || $this->fresh) {
                $permissions->syncRolePermissions($role, $resolvedPermissions);
            } else {
                $newManagedPermissions = array_values(array_diff($resolvedPermissions, $seededPermissions));
                $permissions->grantRolePermissions($role, $newManagedPermissions);
            }

            $permissions->updateRoleMetadata($role, [
                'core_panel_group' => $definition['group'],
                'core_panel_is_protected' => $definition['protected'],
                'core_panel_seeded_permissions' => $resolvedPermissions,
            ]);
        }
    }

    /**
     * @param  list<string>  $managedPermissions
     */
    private function removeOrphanedPermissions(array $managedPermissions, PermissionService $permissions): int
    {
        $permissionModel = $permissions->permissionModelClass();
        $orphans = $permissionModel::query()
            ->where('guard_name', config('auth.defaults.guard', 'web'))
            ->whereNotIn('name', $managedPermissions)
            ->get();

        if ($orphans->isEmpty()) {
            return 0;
        }

        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $pivotPermission = $columnNames['permission_pivot_key'] ?? 'permission_id';
        $orphanIds = $orphans->modelKeys();

        DB::table($tableNames['role_has_permissions'])
            ->whereIn($pivotPermission, $orphanIds)
            ->delete();

        DB::table($tableNames['model_has_permissions'])
            ->whereIn($pivotPermission, $orphanIds)
            ->delete();

        $permissionModel::query()->whereKey($orphanIds)->delete();

        return count($orphanIds);
    }
}
