<?php

declare(strict_types=1);

namespace CorePanel\Http\Controllers\Permissions;

use CorePanel\Domain\Permission\Actions\CreateRoleAction;
use CorePanel\Domain\Permission\Actions\DeleteRoleAction;
use CorePanel\Domain\Permission\Actions\ResyncAccessMatrixAction;
use CorePanel\Domain\Permission\Actions\SyncRolePermissionsAction;
use CorePanel\Domain\Permission\Actions\UpdateRoleAction;
use CorePanel\Domain\Permission\DTOs\PermissionData;
use CorePanel\Domain\Permission\DTOs\RoleData;
use CorePanel\Support\ActivityLog\ActivityLogService;
use CorePanel\Support\Permissions\CorePanelAccess;
use CorePanel\Support\Permissions\CorePanelPermissions;
use CorePanel\Support\Permissions\PermissionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class RoleController extends Controller
{
    public function __construct(
        private readonly PermissionService $permissions,
        private readonly CorePanelAccess $access,
        private readonly CreateRoleAction $createRole,
        private readonly UpdateRoleAction $updateRole,
        private readonly DeleteRoleAction $deleteRole,
        private readonly SyncRolePermissionsAction $syncPermissions,
        private readonly ResyncAccessMatrixAction $resyncAccessMatrix,
        private readonly ActivityLogService $activityLog,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', $this->roleModelClass());

        return Inertia::render('Roles/Index', $this->permissionManagementPayload());
    }

    public function matrix(Request $request): Response
    {
        Gate::authorize('viewAny', $this->roleModelClass());

        return Inertia::render('Roles/Matrix', $this->permissionManagementPayload());
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', $this->roleModelClass());

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'guard_name' => ['nullable', 'string', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => ['string'],
            'redirect_to_matrix' => ['sometimes', 'boolean'],
        ]);

        $roleData = $this->createRole->execute($validated);
        $role = $this->findRole((string) $roleData->id);
        $permissions = array_values($validated['permissions'] ?? []);

        if ($permissions !== []) {
            $this->syncPermissions->execute($role, $permissions);
        }

        $this->activityLog
            ->withCauser($request->user())
            ->log($request->user(), 'created', [
                'name' => $validated['name'],
                'permissions' => $permissions,
                'role_id' => $roleData->id,
                'subject_type' => 'role',
            ]);

        $status = __('page-roles.roles.created');

        if ((bool) ($validated['redirect_to_matrix'] ?? false)) {
            return to_route('core-panel.roles.matrix', [
                'role' => $roleData->id,
            ])->with('status', $status);
        }

        return back()->with('status', $status);
    }

    /** @return array<string, mixed> */
    private function permissionManagementPayload(): array
    {
        $actor = request()->user();
        $roles = $this->permissions->visibleRoles($actor)
            ->map(function (Model $role): array {
                $payload = RoleData::fromModel($role)->toArray();
                $payload['displayLabel'] = $this->access->roleLabel($payload['name']);

                return $payload;
            })
            ->values()
            ->all();

        $permissions = $this->permissions->permissions()
            ->map(fn (Model $permission): array => PermissionData::fromModel($permission, $this->access)->toArray())
            ->values()
            ->all();

        $users = $this->permissions->usersForAssignment()
            ->map(static function (Model $user): array {
                return [
                    'id' => (string) $user->getKey(),
                    'name' => (string) $user->getAttribute('name'),
                    'email' => (string) $user->getAttribute('email'),
                ];
            })
            ->values()
            ->all();

        return [
            'roles' => $roles,
            'permissions' => $permissions,
            'permissionDefaults' => CorePanelPermissions::defaults(),
            'defaultRoles' => collect($this->access->defaultRoles())
                ->map(fn (array $definition, string $roleName): array => [
                    'name' => $roleName,
                    'group' => $definition['group'],
                    'label' => $this->access->roleLabel($roleName),
                    'permissions' => $this->access->rolePermissions($roleName),
                    'protected' => $definition['protected'],
                ])
                ->filter(fn (array $role): bool => $this->permissions->canManageRole($actor, $role['name']))
                ->values()
                ->all(),
            'permissionGroups' => $this->access->groupLabels(),
            'users' => $users,
        ];
    }

    public function update(Request $request, string $role): RedirectResponse
    {
        $roleModel = $this->findRole($role);
        Gate::authorize('update', $roleModel);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'guard_name' => ['nullable', 'string', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => ['string'],
        ]);

        $this->updateRole->execute($roleModel, $validated);
        $permissions = array_values($validated['permissions'] ?? []);
        $this->syncPermissions->execute($roleModel, $permissions);

        $this->activityLog
            ->withCauser($request->user())
            ->log($request->user(), 'updated', [
                'name' => $validated['name'],
                'permissions' => $permissions,
                'role_id' => $role,
                'subject_type' => 'role',
            ]);

        return back()->with('status', __('page-roles.roles.updated'));
    }

    public function destroy(string $role): RedirectResponse
    {
        $roleModel = $this->findRole($role);
        Gate::authorize('delete', $roleModel);

        $this->deleteRole->execute($roleModel);

        $this->activityLog
            ->withCauser(auth()->user())
            ->log(auth()->user(), 'deleted', [
                'name' => (string) $roleModel->getAttribute('name'),
                'role_id' => $role,
                'subject_type' => 'role',
            ]);

        return back()->with('status', __('page-roles.roles.deleted'));
    }

    public function syncPermissions(Request $request, string $role): RedirectResponse
    {
        $roleModel = $this->findRole($role);
        Gate::authorize('update', $roleModel);

        $validated = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string'],
        ]);

        $this->syncPermissions->execute(
            $roleModel,
            array_values($validated['permissions'] ?? []),
        );

        $this->activityLog
            ->withCauser($request->user())
            ->log($request->user(), 'updated', [
                'causer_display' => 'system',
                'permissions' => array_values($validated['permissions'] ?? []),
                'subject_type' => 'role_permissions',
                'role_id' => $role,
            ]);

        return back()->with('status', __('page-roles.roles.permissions_updated'));
    }

    public function resync(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user === null || ! $this->permissions->userHas($user, 'roles.update') || ! method_exists($user, 'hasRole') || ! $user->hasRole('super-admin')) {
            abort(403);
        }

        $fresh = $request->boolean('fresh');
        $this->resyncAccessMatrix->execute($fresh);

        $this->activityLog
            ->withCauser($user)
            ->log($user, 'updated', [
                'causer_display' => 'system',
                'fresh' => $fresh,
                'subject_type' => 'managed_access_matrix',
            ]);

        return back()->with('status', __('page-roles.roles.resynced'));
    }

    /**
     * @return class-string<Model>
     */
    private function roleModelClass(): string
    {
        return $this->permissions->roleModelClass();
    }

    private function findRole(string $roleId): Model
    {
        $roleModel = $this->roleModelClass();

        return $roleModel::query()->with('permissions')->findOrFail($roleId);
    }
}
