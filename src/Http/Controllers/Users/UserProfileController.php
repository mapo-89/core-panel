<?php

declare(strict_types=1);

namespace CorePanel\Http\Controllers\Users;

use CorePanel\Domain\Permission\DTOs\RoleData;
use CorePanel\Domain\User\Actions\UpdateUserAction;
use CorePanel\Http\Requests\UpdateUserRequest;
use CorePanel\Http\Resources\UserResource;
use CorePanel\Support\ActivityLog\ActivityLogService;
use CorePanel\Support\Locale\SupportedLocales;
use CorePanel\Support\Permissions\CorePanelAccess;
use CorePanel\Support\Permissions\PermissionService;
use CorePanel\Support\Socialite\SocialiteProviderRegistry;
use CorePanel\Support\UserGroups\UserGroupModelManager;
use CorePanel\Support\Users\UserModelManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class UserProfileController extends Controller
{
    public function __construct(
        private readonly UserModelManager $users,
        private readonly PermissionService $permissions,
        private readonly CorePanelAccess $access,
        private readonly UserGroupModelManager $userGroups,
        private readonly UpdateUserAction $updateUser,
        private readonly ActivityLogService $activityLog,
        private readonly SocialiteProviderRegistry $socialite,
    ) {}

    public function show(Request $request, string $user): Response
    {
        $target = $this->users->findOrFail($user, true);
        Gate::authorize('view', $target);
        $actor = $request->user();
        $visibleRoles = $this->permissions->visibleRoles($actor);
        $assignableRoles = $this->permissions->assignableRoles($actor);

        return Inertia::render('Users/Show', [
            'canHardResetPassword' => $this->users->isSuperAdmin($request->user()),
            'canAssignRoles' => $assignableRoles->isNotEmpty(),
            'capabilities' => $this->users->capabilities(),
            'roleLabels' => $visibleRoles
                ->mapWithKeys(fn ($role): array => [
                    (string) $role->getAttribute('name') => $this->access->roleLabel((string) $role->getAttribute('name')),
                ])
                ->all(),
            'roles' => $assignableRoles
                ->map(static fn ($role): array => RoleData::fromModel($role)->toArray())
                ->values()
                ->all(),
            'sessionsEnabled' => config('session.driver') === 'database',
            'socialAccounts' => $this->socialite->linkedAccountsFor($target),
            'socialProviders' => $this->socialite->enabledProviders(),
            'userGroupOptions' => $this->userGroupOptions(),
            'user' => UserResource::make($target)->resolve(),
        ]);
    }

    public function edit(Request $request, string $user): Response
    {
        $target = $this->users->findOrFail($user, true);
        Gate::authorize('update', $target);
        $roles = $this->permissions->assignableRoles($request->user());

        return Inertia::render('Users/Edit', [
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
            'user' => UserResource::make($target)->resolve(),
        ]);
    }

    public function update(UpdateUserRequest $request, string $user): RedirectResponse
    {
        $target = $this->users->findOrFail($user, true);
        Gate::authorize('update', $target);

        $trackedAttributes = $this->trackedUserActivityAttributes();
        $originalAttributes = $this->captureTrackedAttributes($target, $trackedAttributes);

        $updatedUser = $this->updateUser->execute($target, $request->validated());

        $this->activityLog
            ->withCauser($request->user())
            ->log($updatedUser, 'updated', $this->userUpdateActivityProperties($updatedUser, $originalAttributes, $trackedAttributes));

        return redirect()
            ->route('core-panel.users.show', $updatedUser->getKey())
            ->with('status', __('page-users.users.updated'));
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
     * @return list<string>
     */
    private function trackedUserActivityAttributes(): array
    {
        $attributes = ['first_name', 'last_name', 'email'];

        if ($this->users->supportsLocale()) {
            $attributes[] = 'locale';
        }

        if ($this->users->supportsStatus()) {
            $attributes[] = 'status';
        }

        return $attributes;
    }

    /**
     * @param  list<string>  $trackedAttributes
     * @return array<string, mixed>
     */
    private function captureTrackedAttributes(Model $user, array $trackedAttributes): array
    {
        $attributes = [];

        foreach ($trackedAttributes as $attribute) {
            $attributes[$attribute] = $user->getAttribute($attribute);
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $originalAttributes
     * @param  list<string>  $trackedAttributes
     * @return array<string, mixed>
     */
    private function userUpdateActivityProperties(Model $user, array $originalAttributes, array $trackedAttributes): array
    {
        $newAttributes = [];
        $oldAttributes = [];

        foreach ($trackedAttributes as $attribute) {
            $originalValue = $originalAttributes[$attribute] ?? null;
            $currentValue = $user->getAttribute($attribute);

            if ($originalValue === $currentValue) {
                continue;
            }

            $newAttributes[$attribute] = $currentValue;
            $oldAttributes[$attribute] = $originalValue;
        }

        $properties = [
            'email' => (string) $user->getAttribute('email'),
        ];

        if ($newAttributes !== []) {
            $properties['attributes'] = $newAttributes;
            $properties['old'] = $oldAttributes;
        }

        return $properties;
    }
}
