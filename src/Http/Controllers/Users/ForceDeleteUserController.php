<?php

declare(strict_types=1);

namespace CorePanel\Http\Controllers\Users;

use CorePanel\Domain\User\Actions\ForceDeleteUserAction;
use CorePanel\Support\Users\UserModelManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

final class ForceDeleteUserController extends Controller
{
    public function __construct(
        private readonly UserModelManager $users,
        private readonly ForceDeleteUserAction $forceDeleteUser,
    ) {}

    public function __invoke(string $user): RedirectResponse
    {
        $target = $this->users->findOrFail($user, true);
        Gate::authorize('forceDelete', $target);

        $this->forceDeleteUser->execute($target);

        return redirect()
            ->route('core-panel.users.index')
            ->with('status', __('page-users.users.force_deleted'));
    }
}
