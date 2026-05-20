<?php

declare(strict_types=1);

namespace CorePanel\Http\Middleware;

use Closure;
use CorePanel\Support\Settings\SettingsRepository;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnsureCorePanelEmailIsVerified
{
    public function __construct(private SettingsRepository $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            ! $this->verificationIsEnabled()
            || ! $user instanceof MustVerifyEmail
            || $user->hasVerifiedEmail()
        ) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, 'Your email address is not verified.');
        }

        return redirect()->guest(route(Route::has('verification.notice') ? 'verification.notice' : 'auth.verification.notice'));
    }

    private function verificationIsEnabled(): bool
    {
        return (bool) $this->settings->get(
            'auth',
            'email_verification_enabled',
            (bool) config('core-panel.auth.email_verification_enabled', true),
        );
    }
}
