<?php

declare(strict_types=1);

namespace CorePanel\Http\Controllers\Users;

use CorePanel\Domains\User\Actions\ResetUserPasswordAction;
use CorePanel\Http\Requests\AdminResetUserPasswordRequest;
use CorePanel\Support\Users\UserModelManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

final class ResetUserPasswordController extends Controller
{
    public function __construct(
        private readonly UserModelManager $users,
        private readonly ResetUserPasswordAction $resetUserPassword,
    ) {}

    public function __invoke(
        AdminResetUserPasswordRequest $request,
        string $user,
    ): RedirectResponse {
        $target = $this->users->findOrFail($user, true);
        Gate::authorize('update', $target);

        abort_unless($this->users->isSuperAdmin($request->user()), 403);

        $this->resetUserPassword->execute(
            $target,
            (string) $request->validated('password'),
            $this->preservedSessionId($request, $target),
        );

        return back()->with('status', __('page-users.users.password_reset_directly'));
    }

    private function preservedSessionId(Request $request, Model $target): ?string
    {
        $actor = $request->user();

        if (! $actor instanceof Model || ! $actor->is($target)) {
            return null;
        }

        return $request->session()->getId();
    }
}
