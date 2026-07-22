<?php

declare(strict_types=1);

namespace CorePanel\Http\Controllers\Permissions;

use CorePanel\Domain\User\Actions\AssignUserRolesAction;
use CorePanel\Support\Permissions\PermissionService;
use CorePanel\Support\Users\UserModelManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class AssignUserRoleController extends Controller
{
    public function __construct(
        private readonly PermissionService $permissions,
        private readonly AssignUserRolesAction $assignRoles,
        private readonly UserModelManager $users,
    ) {}

    public function store(Request $request, string $user): RedirectResponse
    {
        $actor = $request->user();

        if ($actor === null || ! $this->permissions->userHas($actor, 'roles.update')) {
            throw new AccessDeniedHttpException;
        }

        $visibleRoleNames = $this->permissions->assignableRoleNamesFor($actor);

        $validated = $request->validate([
            'roles' => ['array'],
            'roles.*' => ['string', Rule::in($visibleRoleNames)],
        ]);

        $assignableUser = $this->findUser($user);

        $this->assignRoles->execute(
            $assignableUser,
            array_values($validated['roles'] ?? []),
        );

        return back()->with('status', __('page-users.roles.assigned'));
    }

    private function findUser(string $userId): Model
    {
        return $this->users->findOrFail($userId, true);
    }
}
