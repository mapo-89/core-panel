<?php

declare(strict_types=1);

namespace CorePanel\Http\Controllers\Users;

use CorePanel\Domain\UserGroup\Actions\ImportUserGroupsAction;
use CorePanel\Http\Requests\UserGroup\ImportUserGroupsRequest;
use CorePanel\Http\Requests\UserGroup\StoreUserGroupRequest;
use CorePanel\Http\Requests\UserGroup\UpdateUserGroupRequest;
use CorePanel\Support\ActivityLog\ActivityLogService;
use CorePanel\Support\UserGroups\UserGroupModelManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

final class UserGroupController extends Controller
{
    public function __construct(
        private readonly UserGroupModelManager $userGroups,
        private readonly ImportUserGroupsAction $importUserGroups,
        private readonly ActivityLogService $activityLog,
    ) {}

    public function index(Request $request): RedirectResponse
    {
        Gate::authorize('viewAny', $this->userGroups->modelClass());

        if ($request->route()?->getName() === 'core-panel.user-groups.index') {
            return redirect()->route('core-panel.users.index', ['tab' => 'user_groups']);
        }

        abort(404);
    }

    public function store(StoreUserGroupRequest $request): RedirectResponse
    {
        Gate::authorize('create', $this->userGroups->modelClass());

        $userGroup = $this->userGroups->newModel();
        $userGroup->forceFill($request->validated());
        $userGroup->save();

        $this->activityLog
            ->withCauser($request->user())
            ->log($userGroup, 'created', [
                'name' => (string) $userGroup->getAttribute('name'),
            ]);

        return back()->with('status', __('page-user-groups.groups.created'));
    }

    public function update(UpdateUserGroupRequest $request, string $userGroup): RedirectResponse
    {
        $target = $this->findUserGroup($userGroup);
        Gate::authorize('update', $target);

        $target->forceFill($request->validated());
        $target->save();

        $this->activityLog
            ->withCauser($request->user())
            ->log($target, 'updated', [
                'name' => (string) $target->getAttribute('name'),
            ]);

        return back()->with('status', __('page-user-groups.groups.updated'));
    }

    public function destroy(Request $request, string $userGroup): RedirectResponse
    {
        $target = $this->findUserGroup($userGroup);
        Gate::authorize('delete', $target);

        $target->delete();

        $this->activityLog
            ->withCauser($request->user())
            ->log($target, 'deleted', [
                'name' => (string) $target->getAttribute('name'),
            ]);

        return back()->with('status', __('page-user-groups.groups.deleted'));
    }

    public function preview(ImportUserGroupsRequest $request): JsonResponse
    {
        Gate::authorize('import', $this->userGroups->modelClass());

        return response()->json([
            'success' => true,
            'data' => $this->importUserGroups->preview($request->file('file')),
        ]);
    }

    public function import(ImportUserGroupsRequest $request): JsonResponse|RedirectResponse
    {
        Gate::authorize('import', $this->userGroups->modelClass());

        $result = $this->importUserGroups->execute($request->file('file'));
        $message = __('page-user-groups.groups.imported', [
            'created' => $result['created'],
            'updated' => $result['updated'],
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'success' => true,
            ]);
        }

        return back()->with('status', $message);
    }

    private function findUserGroup(string $userGroupId): Model
    {
        $modelClass = $this->userGroups->modelClass();

        return $modelClass::query()->withCount('users')->findOrFail($userGroupId);
    }
}
