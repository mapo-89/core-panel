<?php

declare(strict_types=1);

namespace CorePanel\Http\Controllers\Users;

use CorePanel\Domain\Permission\DTOs\PermissionData;
use CorePanel\Domain\Permission\DTOs\RoleData;
use CorePanel\Domain\User\Actions\CreateUserAction;
use CorePanel\Domain\User\Actions\DeleteUserAction;
use CorePanel\Domain\User\Actions\SendUserInvitationAction;
use CorePanel\Http\Requests\StoreUserRequest;
use CorePanel\Http\Resources\UserResource;
use CorePanel\Support\ActivityLog\ActivityLogService;
use CorePanel\Support\Locale\SupportedLocales;
use CorePanel\Support\Permissions\CorePanelAccess;
use CorePanel\Support\Permissions\CorePanelPermissions;
use CorePanel\Support\Permissions\PermissionService;
use CorePanel\Support\Query\AllowedQuery;
use CorePanel\Support\Query\QueryBuilderAdapter;
use CorePanel\Support\Settings\SettingsRepository;
use CorePanel\Support\UserGroups\UserGroupModelManager;
use CorePanel\Support\Users\UserModelManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class UserController extends Controller
{
    public function __construct(
        private readonly UserModelManager $users,
        private readonly PermissionService $permissions,
        private readonly CorePanelAccess $access,
        private readonly QueryBuilderAdapter $queries,
        private readonly UserGroupModelManager $userGroups,
        private readonly SettingsRepository $settings,
        private readonly CreateUserAction $createUser,
        private readonly DeleteUserAction $deleteUser,
        private readonly SendUserInvitationAction $sendUserInvitation,
        private readonly ActivityLogService $activityLog,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', $this->users->modelClass());
        $actor = $request->user();

        $statusFilter = trim((string) $request->query('status', ''));
        $roleFilter = trim((string) $request->query('role', ''));
        $userGroupFilter = trim((string) $request->query('user_group_id', ''));
        $roles = $this->permissions->visibleRoles($actor);
        $assignableRoles = $this->permissions->assignableRoles($actor);
        $withTrashed = $this->users->supportsSoftDeletes() && $request->boolean('with_trashed');
        $canViewRoleTab = $actor !== null && $this->permissions->userHas($actor, 'roles.view');
        $canViewUserGroupTab = $actor !== null && $this->permissions->userHas($actor, 'user-groups.view');
        $requestedTab = (string) $request->query('tab', 'users');
        $availableTabs = array_values(array_filter([
            'users',
            $canViewUserGroupTab ? 'user_groups' : null,
            $canViewRoleTab ? 'roles' : null,
        ]));
        $activeTab = in_array($requestedTab, $availableTabs, true)
            ? $requestedTab
            : $availableTabs[0];

        $query = $this->users->visibleQuery($withTrashed)->with($this->users->relations());

        if ($statusFilter !== '' && $this->users->supportsStatus()) {
            $query->where('status', $statusFilter);
        }

        if ($roleFilter !== '' && $this->users->supportsRoles()) {
            $query->whereHas('roles', static function ($builder) use ($roleFilter): void {
                $builder->where('name', $roleFilter);
            });
        }

        if ($userGroupFilter !== '' && $this->users->supportsUserGroups()) {
            $query->whereHas('userGroups', static function ($builder) use ($userGroupFilter): void {
                $builder->whereKey($userGroupFilter);
            });
        }

        $users = $this->queries
            ->allowed(AllowedQuery::make()
                ->filters(['first_name', 'last_name', 'email'])
                ->includes($this->users->relations())
                ->sorts(['first_name', 'last_name', 'email', 'created_at', 'locale', 'status'])
                ->globalSearch(['first_name', 'last_name', 'email'])
                ->defaultSort('first_name')
                ->perPage(10))
            ->paginate($query, $request);

        return Inertia::render('Users/Index', [
            'assignableUsers' => $this->permissions->usersForAssignment()
                ->map(fn ($user): array => [
                    'id' => (string) $user->getKey(),
                    'name' => $this->users->composeDisplayName(
                        is_string($user->getAttribute('first_name')) ? $user->getAttribute('first_name') : null,
                        is_string($user->getAttribute('last_name')) ? $user->getAttribute('last_name') : null,
                    ),
                    'email' => (string) $user->getAttribute('email'),
                ])
                ->values()
                ->all(),
            'capabilities' => $this->users->capabilities(),
            'defaultRoles' => ($canViewRoleTab ? collect($this->access->defaultRoles()) : collect())
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
            'filters' => [
                'role' => $roleFilter,
                'search' => trim((string) $request->string('search')),
                'status' => $statusFilter,
                'userGroupId' => $userGroupFilter,
                'withTrashed' => $request->boolean('with_trashed'),
            ],
            'activeTab' => $activeTab,
            'locales' => SupportedLocales::codes(),
            'permissionDefaults' => $canViewRoleTab ? CorePanelPermissions::defaults() : [],
            'permissionGroups' => $canViewRoleTab ? $this->access->groupLabels() : [],
            'permissions' => $canViewRoleTab ? $this->permissions->permissions()
                ->map(fn ($permission): array => PermissionData::fromModel($permission, $this->access)->toArray())
                ->values()
                ->all() : [],
            'canAssignRoles' => $assignableRoles->isNotEmpty(),
            'roleLabels' => $roles
                ->mapWithKeys(fn ($role): array => [
                    (string) $role->getAttribute('name') => $this->access->roleLabel((string) $role->getAttribute('name')),
                ])
                ->all(),
            'roles' => $roles
                ->map(static fn ($role): array => RoleData::fromModel($role)->toArray())
                ->values()
                ->all(),
            'assignableRoles' => $assignableRoles
                ->map(static fn ($role): array => RoleData::fromModel($role)->toArray())
                ->values()
                ->all(),
            'userGroupOptions' => $this->userGroupOptions(),
            'userGroups' => $canViewUserGroupTab ? $this->userGroupRows() : [],
            'users' => UserResource::collection($users->getCollection())->resolve(),
            'usersTable' => [
                'pagination' => [
                    'page' => $users->currentPage(),
                    'perPage' => $users->perPage(),
                    'total' => $users->total(),
                    'lastPage' => $users->lastPage(),
                    'from' => $users->firstItem(),
                    'to' => $users->lastItem(),
                ],
                'state' => [
                    'filters' => [],
                    'search' => trim((string) $request->string('search')),
                    'sort' => (string) $request->query('sort', ''),
                    'visibleColumns' => $this->resolveVisibleColumns($request),
                ],
            ],
        ]);
    }

    /**
     * @return list<string>
     */
    private function resolveVisibleColumns(Request $request): array
    {
        $columns = trim((string) $request->query('columns', ''));

        if ($columns === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $column): string => trim($column),
            explode(',', $columns)
        )));
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', $this->users->modelClass());
        $roles = $this->permissions->assignableRoles($request->user());

        return Inertia::render('Users/Create', [
            'canAssignRoles' => $roles->isNotEmpty(),
            'capabilities' => $this->users->capabilities(),
            'locales' => SupportedLocales::options(),
            'roles' => $roles
                ->map(static fn ($role): array => RoleData::fromModel($role)->toArray())
                ->values()
                ->all(),
            'roleLabels' => $roles
                ->mapWithKeys(fn ($role): array => [
                    (string) $role->getAttribute('name') => $this->access->roleLabel((string) $role->getAttribute('name')),
                ])
                ->all(),
            'userGroupOptions' => $this->userGroupOptions(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        Gate::authorize('create', $this->users->modelClass());

        if (! $this->passwordResetEnabled()) {
            return back()
                ->withInput()
                ->with('error', __('page-users.users.invitation_requires_password_reset'));
        }

        $user = $this->createUser->execute($request->validated());
        $this->sendUserInvitation->execute($user);

        $this->activityLog
            ->withCauser($request->user())
            ->log($user, 'created', [
                'email' => (string) $user->getAttribute('email'),
            ]);

        return redirect()
            ->route('core-panel.users.show', $user->getKey())
            ->with('status', __('page-users.users.invited'));
    }

    public function destroy(string $user): RedirectResponse
    {
        $target = $this->users->findOrFail($user, true);
        Gate::authorize('delete', $target);

        $this->deleteUser->execute($target);

        $this->activityLog
            ->withCauser(auth()->user())
            ->log($target, 'deleted', [
                'email' => (string) $target->getAttribute('email'),
            ]);

        return redirect()
            ->route('core-panel.users.index')
            ->with('status', __('page-users.users.deleted'));
    }

    private function passwordResetEnabled(): bool
    {
        return (bool) $this->settings->get(
            'auth',
            'password_reset_enabled',
            (bool) config('core-panel.auth.password_reset_enabled', true),
        );
    }

    /**
     * @return list<array{label:string,value:string,color:string}>
     */
    private function userGroupOptions(): array
    {
        $modelClass = $this->userGroups->modelClass();

        return $modelClass::query()
            ->orderBy('name')
            ->get(['id', 'name', 'color'])
            ->map(static fn ($userGroup): array => [
                'label' => (string) $userGroup->getAttribute('name'),
                'value' => (string) $userGroup->getKey(),
                'color' => (string) ($userGroup->getAttribute('color') ?: '#6366F1'),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id:string,name:string,color:string,usersCount:int,createdAt:?string}>
     */
    private function userGroupRows(): array
    {
        $modelClass = $this->userGroups->modelClass();

        return $modelClass::query()
            ->withCount('users')
            ->orderBy('name')
            ->get(['id', 'name', 'color', 'created_at'])
            ->map(static function ($userGroup): array {
                $createdAt = $userGroup->getAttribute('created_at');

                return [
                    'id' => (string) $userGroup->getKey(),
                    'name' => (string) $userGroup->getAttribute('name'),
                    'color' => (string) ($userGroup->getAttribute('color') ?: '#6366F1'),
                    'usersCount' => (int) ($userGroup->getAttribute('users_count') ?? 0),
                    'createdAt' => $createdAt instanceof \DateTimeInterface ? $createdAt->format(DATE_ATOM) : null,
                ];
            })
            ->values()
            ->all();
    }
}
