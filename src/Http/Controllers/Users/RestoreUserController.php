<?php

declare(strict_types=1);

namespace CorePanel\Http\Controllers\Users;

use CorePanel\Domain\User\Actions\RestoreUserAction;
use CorePanel\Support\Users\UserModelManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

final class RestoreUserController extends Controller
{
    public function __construct(
        private readonly UserModelManager $users,
        private readonly RestoreUserAction $restoreUser,
    ) {}

    public function __invoke(string $user): RedirectResponse
    {
        $target = $this->users->findOrFail($user, true);
        Gate::authorize('restore', $target);

        $this->restoreUser->execute($target);

        return back()->with('status', __('page-users.users.restored'));
    }
}
