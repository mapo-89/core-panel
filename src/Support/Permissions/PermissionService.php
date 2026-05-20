<?php

declare(strict_types=1);

namespace CorePanel\Support\Permissions;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

final class PermissionService
{
    public function __construct(private PermissionRegistrar $registrar) {}

    public function userHas(Authenticatable $user, string $permission): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return (bool) $user->can($permission);
    }

    public function permissionExists(string $permission): bool
    {
        $permissionModel = $this->permissionModelClass();

        return $permissionModel::query()
            ->where('name', $permission)
            ->exists();
    }

    public function assignRole(Authenticatable $user, string $role): void
    {
        if (! method_exists($user, 'assignRole')) {
            throw new \RuntimeException(sprintf(
                'User model [%s] must support role assignment.',
                $user::class
            ));
        }

        $user->assignRole($role);
    }

    public function resetCache(): void
    {
        $this->registrar->forgetCachedPermissions();
        $this->registrar->clearPermissionsCollection();
    }

    public function roleModelClass(): string
    {
        /** @var class-string<Model> $roleModel */
        $roleModel = config('permission.models.role');

        return $roleModel;
    }

    public function permissionModelClass(): string
    {
        /** @var class-string<Model> $permissionModel */
        $permissionModel = config('permission.models.permission');

        return $permissionModel;
    }

    /**
     * @return Collection<int, Model>
     */
    public function roles(): Collection
    {
        $roles = $this->roleModelClass()::query()
            ->with('permissions')
            ->withCount('permissions');

        if ($this->roleMetadataColumnExists('core_panel_group')) {
            $roles->orderBy('core_panel_group');
        }

        $roles->orderBy('name');

        $collection = $roles->get()->collect();
        $userCounts = $this->roleUserCounts();

        return $collection->each(function (Model $role) use ($userCounts): void {
            $role->setAttribute('users_count', (int) ($userCounts[(string) $role->getKey()] ?? 0));
        });
    }

    public function canReferenceRoles(?Authenticatable $user): bool
    {
        if ($user === null) {
            return false;
        }

        foreach (['roles.view', 'roles.create', 'roles.update', 'roles.delete'] as $permission) {
            if ($this->userHas($user, $permission)) {
                return true;
            }
        }

        return false;
    }

    public function isSuperAdminRole(Model|string $role): bool
    {
        $roleName = is_string($role)
            ? $role
            : (string) $role->getAttribute('name');

        return trim(Str::lower($roleName)) === 'super-admin';
    }

    public function canManageRole(?Authenticatable $actor, Model|string $role): bool
    {
        if (! $this->isSuperAdminRole($role)) {
            return true;
        }

        return $actor !== null && $this->isSuperAdmin($actor);
    }

    /**
     * @return Collection<int, Model>
     */
    public function visibleRoles(?Authenticatable $actor): Collection
    {
        if (! $this->canReferenceRoles($actor)) {
            return collect();
        }

        $roles = $this->roles();

        if ($actor !== null && $this->isSuperAdmin($actor)) {
            return $roles;
        }

        return $roles
            ->reject(fn (Model $role): bool => $this->isSuperAdminRole($role))
            ->values();
    }

    /**
     * @return list<string>
     */
    public function visibleRoleNamesFor(?Authenticatable $actor): array
    {
        return $this->visibleRoles($actor)
            ->map(static fn (Model $role): string => (string) $role->getAttribute('name'))
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Model>
     */
    public function assignableRoles(?Authenticatable $actor): Collection
    {
        if ($actor === null || ! $this->userHas($actor, 'roles.update')) {
            return collect();
        }

        return $this->visibleRoles($actor);
    }

    /**
     * @return list<string>
     */
    public function assignableRoleNamesFor(?Authenticatable $actor): array
    {
        return $this->assignableRoles($actor)
            ->map(static fn (Model $role): string => (string) $role->getAttribute('name'))
            ->values()
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function roleUserCounts(): array
    {
        $configuredUserModel = trim((string) config('core-panel.user_model', ''));
        $userModelClass = $configuredUserModel !== ''
            ? $configuredUserModel
            : trim((string) config('auth.providers.users.model', ''));

        if ($userModelClass === '' || ! class_exists($userModelClass)) {
            return [];
        }

        /** @var Model $userModel */
        $userModel = new $userModelClass;
        $tableNames = config('permission.table_names', []);
        $columnNames = config('permission.column_names', []);
        $modelHasRolesTable = (string) ($tableNames['model_has_roles'] ?? 'model_has_roles');
        $rolePivotColumn = trim((string) ($columnNames['role_pivot_key'] ?? '')) ?: 'role_id';

        $rows = DB::connection($userModel->getConnectionName())
            ->table($modelHasRolesTable)
            ->selectRaw($modelHasRolesTable.'.'.$rolePivotColumn.' as role_key')
            ->selectRaw('count(*) as aggregate')
            ->where($modelHasRolesTable.'.model_type', $userModel->getMorphClass())
            ->groupBy($modelHasRolesTable.'.'.$rolePivotColumn);

        /** @var array<string, int> $counts */
        $counts = $rows
            ->pluck('aggregate', 'role_key')
            ->map(static fn (mixed $count): int => (int) $count)
            ->all();

        return $counts;
    }

    /**
     * @return Collection<int, Model>
     */
    public function permissions(): Collection
    {
        $permissionModel = $this->permissionModelClass();

        return $permissionModel::query()
            ->orderBy('name')
            ->get()
            ->collect();
    }

    /**
     * @param  array{name:string,guard_name?:string}  $attributes
     */
    public function createRole(array $attributes): Model
    {
        $roleModel = $this->roleModelClass();

        return $roleModel::query()->create([
            'name' => $attributes['name'],
            'guard_name' => $attributes['guard_name'] ?? config('auth.defaults.guard', 'web'),
        ]);
    }

    /**
     * @param  array{name:string,guard_name?:string}  $attributes
     */
    public function firstOrCreateRole(array $attributes): Model
    {
        $roleModel = $this->roleModelClass();

        return $roleModel::query()->firstOrCreate([
            'name' => $attributes['name'],
            'guard_name' => $attributes['guard_name'] ?? config('auth.defaults.guard', 'web'),
        ]);
    }

    /**
     * @param  array{name:string,guard_name?:string}  $attributes
     */
    public function updateRole(Model $role, array $attributes): Model
    {
        $role->fill([
            'name' => $attributes['name'],
            'guard_name' => $attributes['guard_name'] ?? $role->getAttribute('guard_name'),
        ]);
        $role->save();

        return $role->refresh();
    }

    public function deleteRole(Model $role): void
    {
        $role->delete();
    }

    /**
     * @param  list<string>  $permissionNames
     */
    public function syncRolePermissions(Model $role, array $permissionNames): Model
    {
        if (! method_exists($role, 'syncPermissions')) {
            throw new \RuntimeException(sprintf(
                'Role model [%s] must support syncing permissions.',
                $role::class
            ));
        }

        $role->syncPermissions($permissionNames);

        return $role->refresh()->load('permissions');
    }

    /**
     * @param  list<string>  $permissionNames
     */
    public function grantRolePermissions(Model $role, array $permissionNames): Model
    {
        if (! method_exists($role, 'givePermissionTo')) {
            throw new \RuntimeException(sprintf(
                'Role model [%s] must support granting permissions.',
                $role::class
            ));
        }

        $permissionNames = array_values(array_unique($permissionNames));

        if ($permissionNames !== []) {
            $role->givePermissionTo($permissionNames);
        }

        return $role->refresh()->load('permissions');
    }

    /**
     * @param  array{name:string,guard_name?:string}  $attributes
     */
    public function createPermission(array $attributes): Model
    {
        $permissionModel = $this->permissionModelClass();

        return $permissionModel::query()->firstOrCreate([
            'name' => $attributes['name'],
            'guard_name' => $attributes['guard_name'] ?? config('auth.defaults.guard', 'web'),
        ]);
    }

    public function updateRoleMetadata(Model $role, array $attributes): Model
    {
        $payload = [];

        if ($this->roleMetadataColumnExists('core_panel_group') && array_key_exists('core_panel_group', $attributes)) {
            $payload['core_panel_group'] = $attributes['core_panel_group'];
        }

        if ($this->roleMetadataColumnExists('core_panel_is_protected') && array_key_exists('core_panel_is_protected', $attributes)) {
            $payload['core_panel_is_protected'] = (bool) $attributes['core_panel_is_protected'];
        }

        if ($this->roleMetadataColumnExists('core_panel_seeded_permissions') && array_key_exists('core_panel_seeded_permissions', $attributes)) {
            $payload['core_panel_seeded_permissions'] = json_encode(
                array_values(array_unique(array_map('strval', Arr::wrap($attributes['core_panel_seeded_permissions'])))),
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        }

        if ($payload === []) {
            return $role;
        }

        $role->forceFill($payload);
        $role->save();

        return $role->refresh();
    }

    /**
     * @param  array{name:string,guard_name?:string}  $attributes
     */
    public function updatePermission(Model $permission, array $attributes): Model
    {
        $permission->fill([
            'name' => $attributes['name'],
            'guard_name' => $attributes['guard_name'] ?? $permission->getAttribute('guard_name'),
        ]);
        $permission->save();

        return $permission->refresh();
    }

    public function deletePermission(Model $permission): void
    {
        $permission->delete();
    }

    /**
     * @return Collection<int, Model>
     */
    public function usersForAssignment(): Collection
    {
        $userModel = config('core-panel.user_model');

        return $this->applyUserOrdering($userModel::query())->get()->collect();
    }

    /**
     * @param  list<string>  $roleNames
     */
    public function syncUserRoles(Authenticatable $user, array $roleNames): void
    {
        if (! method_exists($user, 'syncRoles')) {
            throw new \RuntimeException(sprintf(
                'User model [%s] must support role syncing.',
                $user::class
            ));
        }

        $user->syncRoles($roleNames);
    }

    private function roleMetadataColumnExists(string $column): bool
    {
        $table = $this->roleModelClass()::query()->getModel()->getTable();

        return Schema::hasColumn($table, $column);
    }

    private function applyUserOrdering(Builder $query): Builder
    {
        $model = $query->getModel();
        $connection = $model->getConnectionName();
        $table = $model->getTable();
        $schema = Schema::connection($connection);

        if ($schema->hasColumn($table, 'name')) {
            return $query->orderBy('name');
        }

        if ($schema->hasColumn($table, 'first_name')) {
            $query->orderBy('first_name');
        }

        if ($schema->hasColumn($table, 'last_name')) {
            $query->orderBy('last_name');
        }

        if (! $schema->hasColumn($table, 'first_name') && ! $schema->hasColumn($table, 'last_name') && $schema->hasColumn($table, 'email')) {
            $query->orderBy('email');
        }

        return $query;
    }

    private function isSuperAdmin(Authenticatable $user): bool
    {
        if (method_exists($user, 'isSuperAdmin')) {
            return (bool) $user->isSuperAdmin();
        }

        if (method_exists($user, 'hasRole')) {
            return (bool) $user->hasRole('super-admin');
        }

        return false;
    }
}
