<?php

declare(strict_types=1);

namespace CorePanel\Http\Controllers\Users;

use CorePanel\Support\Users\UserModelManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;

final class SendUserPasswordResetLinkController extends Controller
{
    public function __construct(
        private readonly UserModelManager $users,
    ) {}

    public function __invoke(Request $request, string $user): RedirectResponse
    {
        $target = $this->users->findOrFail($user, true);
        Gate::authorize('update', $target);

        $status = Password::sendResetLink([
            'email' => (string) $target->getAttribute('email'),
        ]);

        if ($status !== Password::ResetLinkSent) {
            return back()->with('error', __($status));
        }

        return back()->with('status', __('page-users.users.password_reset_link_sent'));
    }
}
