<?php

declare(strict_types=1);

namespace CorePanel\Http\Responses;

use CorePanel\Support\Auth\ResolveLoginDestination;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;

final readonly class TwoFactorLoginResponse implements TwoFactorLoginResponseContract
{
    public function __construct(private ResolveLoginDestination $destination) {}

    public function toResponse($request): RedirectResponse
    {
        $user = $request->user() ?? Auth::guard('web')->user();

        if (! $user instanceof Authenticatable) {
            return redirect()->to('/login')->withErrors([
                'email' => [__('auth.failed')],
            ]);
        }

        $result = $this->destination->resolve($request, $user);

        if ($result['error'] !== null) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->to($result['destination'])->withErrors([
                'email' => [$result['error']],
            ]);
        }

        if ($verificationRedirect = $this->intendedVerificationRedirect($request)) {
            return redirect()->to($verificationRedirect);
        }

        return redirect()->to($result['destination']);
    }

    private function intendedVerificationRedirect($request): ?string
    {
        if (! method_exists($request, 'hasSession') || ! $request->hasSession()) {
            return null;
        }

        $intended = $request->session()->get('url.intended');

        if (! is_string($intended) || $intended === '') {
            return null;
        }

        $path = parse_url($intended, PHP_URL_PATH);

        if (! is_string($path) || ! preg_match('#/email/verify/[^/]+/[^/]+$#', $path)) {
            return null;
        }

        $request->session()->forget('url.intended');

        return $intended;
    }
}
