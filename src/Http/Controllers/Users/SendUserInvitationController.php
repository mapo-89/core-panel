<?php

declare(strict_types=1);

namespace CorePanel\Http\Controllers\Users;

use CorePanel\Domains\User\Actions\SendUserInvitationAction;
use CorePanel\Support\Users\UserModelManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

final class SendUserInvitationController extends Controller
{
    public function __construct(
        private readonly UserModelManager $users,
        private readonly SendUserInvitationAction $sendUserInvitation,
    ) {}

    public function __invoke(Request $request, string $user): RedirectResponse
    {
        $target = $this->users->findOrFail($user, true);
        Gate::authorize('update', $target);

        $this->sendUserInvitation->execute($target);

        return back()->with('status', __('page-users.users.invited'));
    }
}
