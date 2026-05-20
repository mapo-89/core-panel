<?php

declare(strict_types=1);

namespace CorePanel\Console;

use CorePanel\Domains\Permission\Actions\ResyncAccessMatrixAction;
use CorePanel\Support\Permissions\PermissionService;
use CorePanel\Support\Users\UserModelManager;
use Illuminate\Console\Command;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

final class AssignSuperAdminCommand extends Command
{
    protected $signature = 'core-panel:user:assign-super-admin
        {user : User primary key or email address}';

    protected $description = 'Assign the super-admin role to an existing user.';

    /**
     * @var list<string>
     */
    protected $aliases = ['core:user:assign-super-admin'];

    public function handle(
        UserModelManager $users,
        PermissionService $permissions,
        ResyncAccessMatrixAction $resyncAccessMatrix,
    ): int {
        if (! $users->supportsRoles()) {
            $this->components->error('The configured user model does not support role assignment.');

            return self::FAILURE;
        }

        if (! $this->permissionTablesExist()) {
            $this->components->error('The permission tables are not migrated yet. Run the CorePanel migrations first.');

            return self::FAILURE;
        }

        $identifier = trim((string) $this->argument('user'));
        $user = $this->resolveUser($users, $identifier);

        if (! $user instanceof Model || ! $user instanceof Authenticatable) {
            $this->components->error(sprintf('No user found for [%s].', $identifier));

            return self::FAILURE;
        }

        $this->ensureSuperAdminAccess($permissions, $resyncAccessMatrix);

        if (method_exists($user, 'hasRole') && $user->hasRole('super-admin')) {
            $this->components->info(sprintf('User [%s] is already a super-admin.', (string) $user->getAttribute('email')));

            return self::SUCCESS;
        }

        $permissions->assignRole($user, 'super-admin');
        $permissions->resetCache();

        $this->components->info(sprintf(
            'Assigned super-admin role to [%s].',
            (string) ($user->getAttribute('email') ?: $user->getKey())
        ));

        return self::SUCCESS;
    }

    private function resolveUser(UserModelManager $users, string $identifier): ?Model
    {
        $query = $users->query();
        $user = $query->whereKey($identifier)->first();

        if ($user instanceof Model) {
            return $user;
        }

        $model = $users->newModel();

        if (Schema::hasColumn($model->getTable(), 'email')) {
            return $users->query()->where('email', $identifier)->first();
        }

        return null;
    }

    private function ensureSuperAdminAccess(
        PermissionService $permissions,
        ResyncAccessMatrixAction $resyncAccessMatrix,
    ): void {
        $roleModel = $permissions->roleModelClass();
        $superAdminRole = $roleModel::query()
            ->where('name', 'super-admin')
            ->withCount('permissions')
            ->first();

        if ($superAdminRole !== null && (int) $superAdminRole->getAttribute('permissions_count') > 0) {
            return;
        }

        $this->components->task('Synchronizing managed access for super-admin', function () use ($permissions, $resyncAccessMatrix): void {
            $resyncAccessMatrix->execute();
            $permissions->resetCache();
        });
    }

    private function permissionTablesExist(): bool
    {
        /** @var array<string, string> $tableNames */
        $tableNames = config('permission.table_names', []);

        foreach ([
            $tableNames['roles'] ?? 'roles',
            $tableNames['permissions'] ?? 'permissions',
            $tableNames['role_has_permissions'] ?? 'role_has_permissions',
            $tableNames['model_has_roles'] ?? 'model_has_roles',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        return true;
    }
}
