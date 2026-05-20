<?php

declare(strict_types=1);

namespace CorePanel\Http\Controllers\Auth;

use CorePanel\Models\SocialAccount;
use CorePanel\Support\ActivityLog\ActivityLogService;
use CorePanel\Support\Socialite\SocialAccountStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class UnlinkSocialAccountController extends Controller
{
    public function __construct(
        private readonly SocialAccountStore $accounts,
        private readonly ActivityLogService $activityLog,
    ) {}

    public function __invoke(Request $request, string $provider): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $account = $this->accounts->deleteForUser($user, $provider);
        abort_unless($account instanceof SocialAccount, 404);

        $this->activityLog
            ->withCauser($user)
            ->log($account, 'unlinked', [
                'provider' => $provider,
                'subject_type' => 'social_account',
            ]);

        return back()->with('status', __('page-auth.social_accounts.unlinked'));
    }
}
