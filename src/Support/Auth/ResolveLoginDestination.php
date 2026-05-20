<?php

declare(strict_types=1);

namespace CorePanel\Support\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;

class ResolveLoginDestination
{
    /**
     * @return array{destination:string, error:?string}
     */
    public function resolve(Request $request, Authenticatable $user): array
    {
        if (
            (bool) config('core-panel.auth.email_verification_enabled', true)
            && $user instanceof MustVerifyEmail
            && ! $user->hasVerifiedEmail()
        ) {
            return ['destination' => '/email/verify', 'error' => null];
        }

        return [
            'destination' => (string) config('fortify.home', '/'.trim((string) config('core-panel.route_prefix', 'admin'), '/')),
            'error' => null,
        ];
    }
}
