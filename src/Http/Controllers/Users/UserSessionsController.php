<?php

declare(strict_types=1);

namespace CorePanel\Http\Controllers\Users;

use CorePanel\Support\Api\Concerns\RespondsWithApi;
use CorePanel\Support\Auth\ListBrowserSessions;
use CorePanel\Support\Auth\RevokeBrowserSession;
use CorePanel\Support\Users\UserModelManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

final class UserSessionsController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly UserModelManager $users,
        private readonly ListBrowserSessions $sessions,
        private readonly RevokeBrowserSession $revokeSession,
    ) {}

    public function index(string $user): JsonResponse
    {
        $target = $this->users->findOrFail($user, true);
        Gate::authorize('view', $target);

        return $this->success(
            $this->sessions->forUser($target, ''),
            meta: [
                'enabled' => config('session.driver') === 'database',
            ],
        );
    }

    public function destroy(string $user, string $session): RedirectResponse
    {
        $target = $this->users->findOrFail($user, true);
        Gate::authorize('update', $target);

        $this->revokeSession->execute($target, $session);

        return back()->with('status', __('page-users.users.session_revoked'));
    }
}
