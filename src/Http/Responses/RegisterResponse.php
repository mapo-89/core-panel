<?php

declare(strict_types=1);

namespace CorePanel\Http\Responses;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;

final class RegisterResponse implements RegisterResponseContract
{
    public function toResponse($request): RedirectResponse
    {
        $user = $request->user();

        if (
            (bool) config('core-panel.auth.email_verification_enabled', true)
            && $user instanceof MustVerifyEmail
            && ! $user->hasVerifiedEmail()
        ) {
            return redirect()->to('/email/verify');
        }

        return redirect()->to((string) config('fortify.home', '/'.trim((string) config('core-panel.route_prefix', 'admin'), '/')));
    }
}
