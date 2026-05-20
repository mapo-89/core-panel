<?php

declare(strict_types=1);

namespace CorePanel\Http\Controllers\Auth;

use CorePanel\Support\Auth\ListBrowserSessions;
use CorePanel\Support\Settings\SettingsRepository;
use CorePanel\Support\Socialite\SocialiteProviderRegistry;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class AuthPageController extends Controller
{
    public function __construct(
        private readonly ListBrowserSessions $browserSessions,
        private readonly SettingsRepository $settings,
        private readonly SocialiteProviderRegistry $socialite,
    ) {}

    public function login(): Response
    {
        return Inertia::render('Auth/Login', [
            'canRegister' => (bool) $this->settings->get('auth', 'registration_enabled', (bool) config('core-panel.auth.registration_enabled', false)),
            'socialProviders' => $this->socialite->enabledProviders(),
        ]);
    }

    public function register(): Response
    {
        abort_unless((bool) $this->settings->get('auth', 'registration_enabled', (bool) config('core-panel.auth.registration_enabled', false)), 404);

        return Inertia::render('Auth/Register');
    }

    public function forgotPassword(): Response
    {
        abort_unless(
            (bool) $this->settings->get('auth', 'password_reset_enabled', (bool) config('core-panel.auth.password_reset_enabled', true)),
            404,
        );

        return Inertia::render('Auth/ForgotPassword');
    }

    public function resetPassword(string $token, Request $request): Response
    {
        abort_unless(
            (bool) $this->settings->get('auth', 'password_reset_enabled', (bool) config('core-panel.auth.password_reset_enabled', true)),
            404,
        );

        return Inertia::render('Auth/ResetPassword', [
            'email' => is_scalar($request->query('email')) ? (string) $request->query('email') : null,
            'token' => $token,
        ]);
    }

    public function verifyEmail(): Response
    {
        abort_unless(
            (bool) $this->settings->get('auth', 'email_verification_enabled', (bool) config('core-panel.auth.email_verification_enabled', true)),
            404,
        );

        return Inertia::render('Auth/VerifyEmail');
    }

    public function twoFactorChallenge(): Response
    {
        abort_unless(
            (bool) $this->settings->get('auth', 'two_factor_enabled', (bool) config('core-panel.auth.two_factor_enabled', true)),
            404,
        );

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    public function profile(): Response
    {
        return Inertia::render('Settings/Profile', $this->profilePageProps(request()));
    }

    public function security(Request $request): Response
    {
        return Inertia::render('Settings/Security', $this->profilePageProps($request));
    }

    /**
     * @return array<string, mixed>
     */
    private function profilePageProps(Request $request): array
    {
        $user = $request->user();
        $hasTwoFactorSetup = $user !== null && $user->getAttribute('two_factor_secret') !== null;
        $hasConfirmedTwoFactorSetup = $user !== null
            && $user->getAttribute('two_factor_secret') !== null
            && $user->getAttribute('two_factor_confirmed_at') !== null;

        return [
            'browserSessions' => $user !== null
                ? $this->browserSessions->forUser($user, (string) $request->session()->getId())
                : [],
            'requiresPasswordSetup' => $user !== null
                && method_exists($user, 'requiresPasswordSetup')
                && (bool) $user->requiresPasswordSetup(),
            'socialAccounts' => $this->socialite->linkedAccountsFor($user),
            'socialProviders' => $this->socialite->enabledProviders($user !== null),
            'twoFactor' => [
                'confirmed' => $hasConfirmedTwoFactorSetup,
                'enabled' => $hasTwoFactorSetup,
            ],
        ];
    }
}
