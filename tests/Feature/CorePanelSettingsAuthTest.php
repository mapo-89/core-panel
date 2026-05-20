<?php

declare(strict_types=1);

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use CorePanel\Http\Middleware\ApplyCorePanelRuntimeSettings;
use CorePanel\Http\Responses\LoginResponse;
use CorePanel\Models\Setting;
use CorePanel\Support\Auth\ResolveLoginDestination;
use CorePanel\Support\Settings\SettingsRepository;
use CorePanel\Support\Socialite\SocialiteProviderRegistry;
use CorePanel\Tests\FakeUser;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Features;
use Spatie\Permission\Traits\HasRoles;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class VerifiableFakeUser extends Authenticatable implements MustVerifyEmail
{
    use HasRoles;
    use HasUuids;
    use MustVerifyEmailTrait;

    protected $table = 'users';

    protected $guarded = [];

    protected string $guard_name = 'web';

    public function corePanelUserStatus(): string
    {
        return (string) ($this->getAttribute('status') ?? 'active');
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super-admin');
    }

    public function supportsCorePanelStatus(): bool
    {
        return true;
    }
}

beforeEach(function (): void {
    if (! corePanelTestbenchDatabaseAvailable()) {
        $this->markTestSkipped('pdo_sqlite is not available in this environment.');
    }

    $this->migrateScaffoldDatabase();

    Gate::before(static fn (...$arguments): bool => true);
});

it('stores the authentication settings group fields', function (): void {
    $user = FakeUser::query()->create([
        'email' => 'settings-auth@example.test',
        'first_name' => 'Auth',
        'last_name' => 'Manager',
        'password' => Hash::make('secret-password'),
    ]);

    $response = $this
        ->actingAs($user)
        ->from(route('core-panel.settings.index', ['tab' => 'auth']))
        ->put(route('core-panel.settings.update', ['group' => 'auth']), [
            'values' => [
                'email_verification_enabled' => [
                    'value' => false,
                ],
                'github_client_id' => [
                    'value' => 'github-client-id',
                ],
                'github_client_secret' => [
                    'value' => 'github-client-secret',
                ],
                'google_client_id' => [
                    'value' => 'google-client-id',
                ],
                'google_client_secret' => [
                    'value' => 'google-client-secret',
                ],
                'microsoft_client_id' => [
                    'value' => '00000000-0000-0000-0000-000000000000',
                ],
                'microsoft_client_secret' => [
                    'value' => 'secret-value',
                ],
                'microsoft_tenant' => [
                    'value' => 'common',
                ],
                'password_reset_enabled' => [
                    'value' => false,
                ],
                'registration_enabled' => [
                    'value' => true,
                ],
                'social_github_enabled' => [
                    'value' => false,
                ],
                'social_google_enabled' => [
                    'value' => false,
                ],
                'social_master_provider' => [
                    'value' => 'microsoft',
                ],
                'social_microsoft_enabled' => [
                    'value' => true,
                ],
                'two_factor_enabled' => [
                    'value' => false,
                ],
            ],
        ]);

    $response
        ->assertRedirect(route('core-panel.settings.index', ['tab' => 'auth']))
        ->assertSessionHas('status', trans('core-panel::settings.messages.saved'));

    expect(
        Setting::query()
            ->where('group', 'auth')
            ->where('key', 'registration_enabled')
            ->first()?->getAttribute('value_json'),
    )->toBeTrue()
        ->and(
            Setting::query()
                ->where('group', 'auth')
                ->where('key', 'email_verification_enabled')
                ->first()?->getAttribute('value_json'),
        )->toBeFalse()
        ->and(
            Setting::query()
                ->where('group', 'auth')
                ->where('key', 'password_reset_enabled')
                ->first()?->getAttribute('value_json'),
        )->toBeFalse()
        ->and(
            Setting::query()
                ->where('group', 'auth')
                ->where('key', 'two_factor_enabled')
                ->first()?->getAttribute('value_json'),
        )->toBeFalse()
        ->and(
            Setting::query()
                ->where('group', 'auth')
                ->where('key', 'github_client_id')
                ->first()?->getAttribute('value_json'),
        )->toBe('github-client-id')
        ->and(
            Setting::query()
                ->where('group', 'auth')
                ->where('key', 'google_client_id')
                ->first()?->getAttribute('value_json'),
        )->toBe('google-client-id')
        ->and(
            Setting::query()
                ->where('group', 'auth')
                ->where('key', 'social_master_provider')
                ->first()?->getAttribute('value_json'),
        )->toBe('microsoft')
        ->and(
            Setting::query()
                ->where('group', 'auth')
                ->where('key', 'social_microsoft_enabled')
                ->first()?->getAttribute('value_json'),
        )->toBeTrue()
        ->and(
            Setting::query()
                ->where('group', 'auth')
                ->where('key', 'microsoft_client_id')
                ->first()?->getAttribute('value_json'),
        )->toBe('00000000-0000-0000-0000-000000000000');
});

it('applies saved authentication runtime settings to config and fortify features', function (): void {
    $settings = app(SettingsRepository::class);

    $settings->set('auth', 'email_verification_enabled', false, 'boolean', false);
    $settings->set('auth', 'github_client_id', 'github-client-id', 'text', false);
    $settings->set('auth', 'github_client_secret', 'github-client-secret', 'text', false);
    $settings->set('auth', 'google_client_id', 'google-client-id', 'text', false);
    $settings->set('auth', 'google_client_secret', 'google-client-secret', 'text', false);
    $settings->set('auth', 'microsoft_client_id', 'microsoft-client-id', 'text', false);
    $settings->set('auth', 'microsoft_client_secret', 'microsoft-client-secret', 'text', false);
    $settings->set('auth', 'microsoft_tenant', 'organizations', 'text', false);
    $settings->set('auth', 'password_reset_enabled', false, 'boolean', false);
    $settings->set('auth', 'registration_enabled', true, 'boolean', true);
    $settings->set('auth', 'social_master_provider', 'microsoft', 'text', false);
    $settings->set('auth', 'two_factor_enabled', false, 'boolean', false);

    $middleware = new ApplyCorePanelRuntimeSettings($settings);
    $response = $middleware->handle(Request::create('/settings', 'GET'), static fn () => response('ok'));

    expect($response->getContent())->toBe('ok')
        ->and(config('core-panel.auth.registration_enabled'))->toBeTrue()
        ->and(config('core-panel.auth.email_verification_enabled'))->toBeFalse()
        ->and(config('core-panel.auth.password_reset_enabled'))->toBeFalse()
        ->and(config('core-panel.auth.socialite.master_provider'))->toBe('microsoft')
        ->and(config('core-panel.auth.two_factor_enabled'))->toBeFalse()
        ->and(config('services.github.client_id'))->toBe('github-client-id')
        ->and(config('services.github.client_secret'))->toBe('github-client-secret')
        ->and(config('services.google.client_id'))->toBe('google-client-id')
        ->and(config('services.google.client_secret'))->toBe('google-client-secret')
        ->and(config('services.microsoft.client_id'))->toBe('microsoft-client-id')
        ->and(config('services.microsoft.client_secret'))->toBe('microsoft-client-secret')
        ->and(config('services.microsoft.tenant'))->toBe('organizations')
        ->and(config('fortify.features'))->toContain(Features::registration())
        ->and(config('fortify.features'))->toContain(Features::updateProfileInformation())
        ->and(config('fortify.features'))->toContain(Features::updatePasswords())
        ->and(config('fortify.features'))->not->toContain(Features::emailVerification())
        ->and(config('fortify.features'))->not->toContain(Features::resetPasswords())
        ->and(config('fortify.features'))->not->toContain(Features::twoFactorAuthentication())
        ->and(Features::canManageTwoFactorAuthentication())->toBeFalse();
});

it('exposes runtime-enabled microsoft social login providers and normalizes the callback redirect', function (): void {
    config()->set('services.microsoft.client_id', null);
    config()->set('services.microsoft.client_secret', null);
    config()->set('services.microsoft.redirect', '');

    $settings = app(SettingsRepository::class);
    $settings->set('auth', 'social_microsoft_enabled', true, 'boolean', false);
    $settings->set('auth', 'social_master_provider', 'microsoft', 'text', false);
    $settings->set('auth', 'microsoft_client_id', 'microsoft-client-id', 'text', false);
    $settings->set('auth', 'microsoft_client_secret', 'microsoft-client-secret', 'text', false);
    $settings->set('auth', 'microsoft_tenant', 'common', 'text', false);

    $middleware = new ApplyCorePanelRuntimeSettings($settings);
    $middleware->handle(Request::create('https://tenant.example.test/login', 'GET'), static fn () => response('ok'));

    $registry = app(SocialiteProviderRegistry::class);
    $providers = $registry->enabledProviders();

    expect(config('services.microsoft.redirect'))->toBe('https://tenant.example.test/auth/microsoft/callback')
        ->and($registry->masterProvider())->toBe('microsoft')
        ->and($registry->isEnabled('microsoft'))->toBeTrue()
        ->and($registry->isMasterProvider('microsoft'))->toBeTrue()
        ->and(collect($providers)->firstWhere('provider', 'microsoft')['isMaster'] ?? null)->toBeTrue()
        ->and(collect($providers)->pluck('provider')->all())->toContain('microsoft');
});

it('hides disabled auth pages and skips verification redirects when email verification is disabled', function (): void {
    $settings = app(SettingsRepository::class);
    $settings->set('auth', 'email_verification_enabled', false, 'boolean', false);
    $settings->set('auth', 'password_reset_enabled', false, 'boolean', false);
    $settings->set('auth', 'registration_enabled', false, 'boolean', true);
    $settings->set('auth', 'two_factor_enabled', false, 'boolean', false);

    $guestResponse = $this->get(route('auth.register'));
    $forgotPasswordResponse = $this->get(route('password.request'));
    $resetPasswordResponse = $this->get(route('password.reset', ['token' => 'reset-token']));
    $twoFactorChallengeResponse = $this->get(route('two-factor.login'));

    $user = FakeUser::query()->create([
        'email' => 'verify-disabled@example.test',
        'first_name' => 'Verify',
        'last_name' => 'Disabled',
        'email_verified_at' => null,
        'password' => Hash::make('secret-password'),
    ]);

    $verifyEmailResponse = $this->actingAs($user)->get(route('auth.verification.notice'));

    $destination = app(ResolveLoginDestination::class)->resolve(Request::create('/login', 'GET'), $user);

    expect($guestResponse->status())->toBe(404)
        ->and($forgotPasswordResponse->status())->toBe(404)
        ->and($resetPasswordResponse->status())->toBe(404)
        ->and($twoFactorChallengeResponse->status())->toBe(404)
        ->and($verifyEmailResponse->status())->toBe(404)
        ->and($destination)->toBe([
            'destination' => '/admin',
            'error' => null,
        ]);
});

it('blocks registration and password reset actions when the auth settings disable them', function (): void {
    config()->set('core-panel.auth.registration_enabled', false);
    config()->set('core-panel.auth.password_reset_enabled', false);

    require_once __DIR__.'/../../stubs/app/Actions/Fortify/CreateNewUser.php';
    require_once __DIR__.'/../../stubs/app/Actions/Fortify/ResetUserPassword.php';

    $user = FakeUser::query()->create([
        'email' => 'auth-action-guard@example.test',
        'first_name' => 'Auth',
        'last_name' => 'Guard',
        'password' => Hash::make('secret-password'),
    ]);

    expect(fn () => (new CreateNewUser)->create([
        'email' => 'new-user@example.test',
        'first_name' => 'New',
        'last_name' => 'User',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
    ]))->toThrow(NotFoundHttpException::class)
        ->and(fn () => (new ResetUserPassword)->reset($user, [
            'password' => 'another-secret-password',
            'password_confirmation' => 'another-secret-password',
        ]))->toThrow(NotFoundHttpException::class);
});

it('preserves intended verification links after login for unverified users', function (): void {
    $user = new VerifiableFakeUser([
        'id' => 1001,
        'email' => 'verify-intended@example.test',
        'name' => 'Verify Intended',
        'email_verified_at' => null,
    ]);
    $user->exists = true;

    $request = Request::create('/login', 'POST');
    $request->setUserResolver(static fn () => $user);
    $request->setLaravelSession(app('session.store'));
    $request->session()->start();
    $request->session()->put('url.intended', 'http://localhost/email/verify/1001/test-hash?expires=123&signature=test');

    $response = app(LoginResponse::class)->toResponse($request);

    expect($response->getTargetUrl())->toBe('http://localhost/email/verify/1001/test-hash?expires=123&signature=test')
        ->and($request->session()->has('url.intended'))->toBeFalse();
});

it('redirects unverified users away from the admin area when verification is enabled', function (): void {
    $user = VerifiableFakeUser::query()->create([
        'email' => 'verify-guard@example.test',
        'first_name' => 'Verify',
        'last_name' => 'Guard',
        'email_verified_at' => null,
        'password' => Hash::make('secret-password'),
    ]);

    $response = $this->actingAs($user)->get(route('core-panel.dashboard'));

    $response->assertRedirect(route('auth.verification.notice'));
});
