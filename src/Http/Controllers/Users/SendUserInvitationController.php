<?php

declare(strict_types=1);

namespace CorePanel\Http\Controllers\Users;

use CorePanel\Domains\User\Actions\SendUserInvitationAction;
use CorePanel\Support\Settings\SettingsRepository;
use CorePanel\Support\Users\UserModelManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

final class SendUserInvitationController extends Controller
{
    public function __construct(
        private readonly UserModelManager $users,
        private readonly SettingsRepository $settings,
        private readonly SendUserInvitationAction $sendUserInvitation,
    ) {}

    public function __invoke(Request $request, string $user): RedirectResponse
    {
        $target = $this->users->findOrFail($user, true);
        Gate::authorize('update', $target);

        if (! (bool) $this->settings->get(
            'auth',
            'password_reset_enabled',
            (bool) config('core-panel.auth.password_reset_enabled', true),
        )) {
            return back()->with('error', __('page-users.users.invitation_requires_password_reset'));
        }

        if (method_exists($target, 'invitationStatus') && $target->invitationStatus() === 'accepted') {
            return back()->with('error', __('page-users.users.invitation_already_accepted'));
        }

        $this->sendUserInvitation->execute($target);

        return back()->with('status', __('page-users.users.invited'));
    }
}
