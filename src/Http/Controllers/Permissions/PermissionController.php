<?php

declare(strict_types=1);

namespace CorePanel\Http\Controllers\Permissions;

use CorePanel\Support\ActivityLog\ActivityLogService;
use CorePanel\Support\Permissions\PermissionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class PermissionController extends Controller
{
    public function __construct(
        private readonly PermissionService $permissions,
        private readonly ActivityLogService $activityLog,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $this->authorize($request, 'roles.create');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'guard_name' => ['nullable', 'string', 'max:255'],
        ]);

        $permission = $this->permissions->createPermission($validated);

        $this->activityLog
            ->withCauser($request->user())
            ->log($permission, 'created', ['name' => $validated['name']]);

        return back()->with('status', __('page-roles.permissions.created'));
    }

    public function update(Request $request, string $permission): RedirectResponse
    {
        $this->authorize($request, 'roles.update');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'guard_name' => ['nullable', 'string', 'max:255'],
        ]);

        $permissionModel = $this->findPermission($permission);
        $this->permissions->updatePermission($permissionModel, $validated);

        $this->activityLog
            ->withCauser($request->user())
            ->log($permissionModel, 'updated', ['name' => $validated['name']]);

        return back()->with('status', __('page-roles.permissions.updated'));
    }

    public function destroy(Request $request, string $permission): RedirectResponse
    {
        $this->authorize($request, 'roles.delete');

        $permissionModel = $this->findPermission($permission);
        $this->permissions->deletePermission($permissionModel);

        $this->activityLog
            ->withCauser($request->user())
            ->log($permissionModel, 'deleted', ['name' => (string) $permissionModel->getAttribute('name')]);

        return back()->with('status', __('page-roles.permissions.deleted'));
    }

    private function authorize(Request $request, string $permission): void
    {
        $user = $request->user();

        if ($user === null || ! $this->permissions->userHas($user, $permission)) {
            throw new AccessDeniedHttpException;
        }
    }

    private function findPermission(string $permissionId): Model
    {
        $permissionModel = $this->permissions->permissionModelClass();

        return $permissionModel::query()->findOrFail($permissionId);
    }
}
