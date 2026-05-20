<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\User;
use CorePanel\Http\Responses\LoginResponse;
use CorePanel\Http\Responses\LogoutResponse;
use CorePanel\Http\Responses\RegisterResponse;
use CorePanel\Http\Responses\ResetPasswordResponse;
use CorePanel\Http\Responses\TwoFactorLoginResponse;
use CorePanel\Support\Settings\SettingsRepository;
use CorePanel\Support\Socialite\SocialiteProviderRegistry;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Laravel\Fortify\Contracts\PasswordResetResponse as PasswordResetResponseContract;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

final class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
        $this->app->singleton(TwoFactorLoginResponseContract::class, TwoFactorLoginResponse::class);
        $this->app->singleton(RegisterResponseContract::class, RegisterResponse::class);
        $this->app->singleton(LogoutResponseContract::class, LogoutResponse::class);
        $this->app->singleton(PasswordResetResponseContract::class, ResetPasswordResponse::class);
    }

    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::loginView(function () {
            /** @var SettingsRepository $settings */
            $settings = app(SettingsRepository::class);
            /** @var SocialiteProviderRegistry $socialite */
            $socialite = app(SocialiteProviderRegistry::class);

            return Inertia::render('Auth/Login', [
                'canRegister' => (bool) $settings->get(
                    'auth',
                    'registration_enabled',
                    (bool) config('core-panel.auth.registration_enabled', false),
                ),
                'socialProviders' => $socialite->enabledProviders(),
            ]);
        });
        Fortify::registerView(function () {
            /** @var SettingsRepository $settings */
            $settings = app(SettingsRepository::class);

            abort_unless(
                Features::enabled(Features::registration())
                    && (bool) $settings->get(
                        'auth',
                        'registration_enabled',
                        (bool) config('core-panel.auth.registration_enabled', false),
                    ),
                404,
            );

            return Inertia::render('Auth/Register');
        });
        Fortify::requestPasswordResetLinkView(function () {
            /** @var SettingsRepository $settings */
            $settings = app(SettingsRepository::class);

            abort_unless(
                Features::enabled(Features::resetPasswords())
                    && (bool) $settings->get(
                        'auth',
                        'password_reset_enabled',
                        (bool) config('core-panel.auth.password_reset_enabled', true),
                    ),
                404,
            );

            return Inertia::render('Auth/ForgotPassword');
        });
        Fortify::resetPasswordView(function (Request $request) {
            /** @var SettingsRepository $settings */
            $settings = app(SettingsRepository::class);

            abort_unless(
                Features::enabled(Features::resetPasswords())
                    && (bool) $settings->get(
                        'auth',
                        'password_reset_enabled',
                        (bool) config('core-panel.auth.password_reset_enabled', true),
                    ),
                404,
            );

            return Inertia::render('Auth/ResetPassword', [
                'email' => is_scalar($request->query('email')) ? (string) $request->query('email') : null,
                'token' => (string) $request->route('token'),
            ]);
        });
        Fortify::verifyEmailView(function () {
            /** @var SettingsRepository $settings */
            $settings = app(SettingsRepository::class);

            abort_unless(
                Features::enabled(Features::emailVerification())
                    && (bool) $settings->get(
                        'auth',
                        'email_verification_enabled',
                        (bool) config('core-panel.auth.email_verification_enabled', true),
                    ),
                404,
            );

            return Inertia::render('Auth/VerifyEmail');
        });
        Fortify::twoFactorChallengeView(function () {
            /** @var SettingsRepository $settings */
            $settings = app(SettingsRepository::class);

            abort_unless(
                Features::enabled(Features::twoFactorAuthentication())
                    && (bool) $settings->get(
                        'auth',
                        'two_factor_enabled',
                        (bool) config('core-panel.auth.two_factor_enabled', true),
                    ),
                404,
            );

            return Inertia::render('Auth/TwoFactorChallenge');
        });
        Fortify::authenticateUsing(function (Request $request): ?Authenticatable {
            $modelClass = (string) config('auth.providers.users.model', User::class);

            if (! class_exists($modelClass)) {
                return null;
            }

            $model = new $modelClass;

            if (! $model instanceof Model) {
                return null;
            }

            $query = $model->newQuery()
                ->where('email', $request->string(Fortify::username())->toString());

            if (method_exists($model, 'microsoftAccount')) {
                $query = $query->with('microsoftAccount');
            }

            $user = $query->first();

            if (! $user instanceof Model || ! $user instanceof Authenticatable) {
                return null;
            }

            if (
                method_exists($user, 'supportsCorePanelStatus')
                && $user->supportsCorePanelStatus()
                && method_exists($user, 'corePanelUserStatus')
                && $user->corePanelUserStatus() !== 'active'
            ) {
                return null;
            }

            if (
                method_exists($user, 'requiresPasswordSetup')
                && $user->requiresPasswordSetup()
                && method_exists($user, 'microsoftAccount')
                && $user->microsoftAccount !== null
            ) {
                throw ValidationException::withMessages([
                    Fortify::username() => [__('page-auth.socialite.microsoft_password_required')],
                ]);
            }

            if (! Hash::check($request->string('password')->toString(), $user->getAuthPassword())) {
                return null;
            }

            return $user;
        });

        RateLimiter::for('login', function (Request $request): Limit {
            $throttleKey = Str::transliterate(Str::lower($request->string(Fortify::username()).'|'.$request->ip()));

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
