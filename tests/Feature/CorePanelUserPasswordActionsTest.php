<?php

declare(strict_types=1);

use CorePanel\Http\Middleware\CheckPermission;
use CorePanel\Http\Middleware\EnsureCorePanelEmailIsVerified;
use CorePanel\Http\Resources\UserResource;
use CorePanel\Mail\UserInvitationMail;
use CorePanel\Support\Settings\SettingsRepository;
use CorePanel\Tests\FakeUser;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

final class VerifiableLocalizedFakeUser extends Authenticatable implements HasLocalePreference, MustVerifyEmail
{
    use HasUuids;
    use MustVerifyEmailTrait;
    use Notifiable;

    protected $table = 'users';

    protected $guarded = [];

    public function preferredLocale(): ?string
    {
        $locale = $this->getAttribute('locale');

        return is_string($locale) && $locale !== '' ? $locale : null;
    }
}

beforeEach(function (): void {
    if (! corePanelTestbenchDatabaseAvailable()) {
        $this->markTestSkipped('pdo_sqlite is not available in this environment.');
    }

    config()->set('auth.providers.users.model', FakeUser::class);
    config()->set('core-panel.user_model', FakeUser::class);

    $this->migrateScaffoldDatabase();

    Gate::before(static fn (...$arguments): bool => true);
});

function useGermanPackageTranslations(): void
{
    app('translator')->setLoaded([]);
    app('translator')->setLocale('de');
    config()->set('app.locale', 'de');
}

it('sends a password reset link for a managed user from the admin area', function (): void {
    Notification::fake();

    $actor = FakeUser::query()->create([
        'email' => 'admin-password-link@example.test',
        'first_name' => 'Admin',
        'last_name' => 'Link',
        'password' => Hash::make('secret-password'),
    ]);

    $target = FakeUser::query()->create([
        'email' => 'target-password-link@example.test',
        'first_name' => 'Target',
        'last_name' => 'Link',
        'password' => Hash::make('secret-password'),
    ]);

    $this->actingAs($actor)
        ->from(route('core-panel.users.show', $target->getKey()))
        ->post(route('core-panel.users.password.reset-link', $target->getKey()))
        ->assertRedirect(route('core-panel.users.show', $target->getKey()))
        ->assertSessionHas('status', trans('page-users.users.password_reset_link_sent'));

    Notification::assertSentTo($target, ResetPassword::class);
});

it('creates managed users through an invitation flow instead of requiring a password', function (): void {
    Mail::fake();
    $this->withoutMiddleware([CheckPermission::class, EnsureCorePanelEmailIsVerified::class]);

    $actor = FakeUser::query()->create([
        'email' => 'admin-invite@example.test',
        'email_verified_at' => now(),
        'first_name' => 'Admin',
        'last_name' => 'Invite',
        'password' => Hash::make('secret-password'),
        'status' => 'active',
    ]);

    $response = $this->actingAs($actor)
        ->post(route('core-panel.users.store'), [
            'email' => 'invited-user@example.test',
            'first_name' => 'Invited',
            'last_name' => 'User',
            'status' => 'active',
            'user_group_ids' => [],
        ]);

    $createdUser = FakeUser::query()
        ->where('email', 'invited-user@example.test')
        ->firstOrFail();

    $response
        ->assertRedirect(route('core-panel.users.show', $createdUser->getKey()))
        ->assertSessionHas('status', trans('page-users.users.invited'));

    expect($createdUser->requiresPasswordSetup())->toBeTrue()
        ->and($createdUser->getAttribute('invited_at'))->not->toBeNull()
        ->and($createdUser->getAttribute('invitation_accepted_at'))->toBeNull()
        ->and(Hash::check('', (string) $createdUser->getAttribute('password')))->toBeFalse();

    Mail::assertSent(UserInvitationMail::class, function (UserInvitationMail $mail) use ($createdUser): bool {
        return $mail->hasTo('invited-user@example.test')
            && $mail->user->is($createdUser)
            && str_contains($mail->invitationUrl, 'reset-password')
            && str_contains($mail->invitationUrl, 'context=invitation');
    });
});

it('blocks invited user creation when password resets are disabled', function (): void {
    Mail::fake();
    config()->set('core-panel.auth.password_reset_enabled', false);
    app(SettingsRepository::class)->set('auth', 'password_reset_enabled', false, 'boolean', false);
    $this->withoutMiddleware([CheckPermission::class, EnsureCorePanelEmailIsVerified::class]);

    $actor = FakeUser::query()->create([
        'email' => 'admin-invite-blocked@example.test',
        'email_verified_at' => now(),
        'first_name' => 'Admin',
        'last_name' => 'Invite',
        'password' => Hash::make('secret-password'),
        'status' => 'active',
    ]);

    $this->actingAs($actor)
        ->from(route('core-panel.users.create'))
        ->post(route('core-panel.users.store'), [
            'email' => 'blocked-invite@example.test',
            'first_name' => 'Blocked',
            'last_name' => 'Invite',
            'status' => 'active',
            'user_group_ids' => [],
        ])
        ->assertRedirect(route('core-panel.users.create'))
        ->assertSessionHas('error', trans('page-users.users.invitation_requires_password_reset'));

    expect(FakeUser::query()->where('email', 'blocked-invite@example.test')->exists())->toBeFalse();

    Mail::assertNothingSent();
});

it('renders a localized invitation mail', function (): void {
    app()->setLocale('de');
    config()->set('app.name', 'CorePanel');
    config()->set('auth.passwords.users.expire', 60);

    $user = FakeUser::query()->create([
        'email' => 'localized-invitation@example.test',
        'first_name' => 'Max',
        'last_name' => 'Mustermann',
        'locale' => 'de',
        'password' => Hash::make('secret-password'),
    ]);

    $mail = new UserInvitationMail($user, 'https://core-panel-app.test/reset-password/token?context=invitation');
    $rendered = $mail->render();

    expect($mail->envelope()->subject)->toBe('Deine Einladung zu CorePanel')
        ->and($rendered)->toContain('Hallo Max,')
        ->and($rendered)->toContain('Du wurdest eingeladen, CorePanel beizutreten.')
        ->and($rendered)->toContain('Passwort festlegen')
        ->and($rendered)->toContain('Dieser Einladungslink läuft nach 60 Minuten ab.');
});

it('derives invitation resource state for pending, accepted, and expired users', function (): void {
    config()->set('auth.passwords.users.expire', 60);

    $pendingUser = FakeUser::query()->create([
        'email' => 'pending-invitation@example.test',
        'first_name' => 'Pending',
        'last_name' => 'Invite',
        'invited_at' => now()->subMinutes(15),
        'password' => Hash::make('secret-password'),
        'requires_password_setup' => true,
    ]);

    $acceptedUser = FakeUser::query()->create([
        'email' => 'accepted-invitation@example.test',
        'first_name' => 'Accepted',
        'last_name' => 'Invite',
        'invitation_accepted_at' => now()->subMinutes(5),
        'invited_at' => now()->subMinutes(30),
        'password' => Hash::make('secret-password'),
        'requires_password_setup' => false,
    ]);

    $expiredUser = FakeUser::query()->create([
        'email' => 'expired-invitation@example.test',
        'first_name' => 'Expired',
        'last_name' => 'Invite',
        'invited_at' => now()->subMinutes(90),
        'password' => Hash::make('secret-password'),
        'requires_password_setup' => true,
    ]);

    $pendingResource = UserResource::make($pendingUser)->resolve(request());
    $acceptedResource = UserResource::make($acceptedUser)->resolve(request());
    $expiredResource = UserResource::make($expiredUser)->resolve(request());

    expect($pendingUser->invitationStatus())->toBe('pending')
        ->and($pendingResource['invitationStatus'])->toBe('pending')
        ->and($pendingResource['requiresPasswordSetup'])->toBeTrue()
        ->and($pendingResource['invitationExpiresAt'])->not->toBeNull()
        ->and($acceptedUser->invitationStatus())->toBe('accepted')
        ->and($acceptedResource['invitationStatus'])->toBe('accepted')
        ->and($acceptedResource['requiresPasswordSetup'])->toBeFalse()
        ->and($acceptedResource['invitationAcceptedAt'])->not->toBeNull()
        ->and($expiredUser->invitationStatus())->toBe('expired')
        ->and($expiredResource['invitationStatus'])->toBe('expired')
        ->and($expiredResource['requiresPasswordSetup'])->toBeTrue();
});

it('re-sends the custom invitation mail for an invited user from the admin area', function (): void {
    Mail::fake();

    $actor = FakeUser::query()->create([
        'email' => 'admin-reinvite@example.test',
        'first_name' => 'Admin',
        'last_name' => 'Reinvite',
        'password' => Hash::make('secret-password'),
    ]);

    $target = FakeUser::query()->create([
        'email' => 'target-reinvite@example.test',
        'first_name' => 'Target',
        'invited_at' => now()->subMinutes(10),
        'last_name' => 'Reinvite',
        'password' => Hash::make('secret-password'),
        'requires_password_setup' => true,
    ]);

    $this->actingAs($actor)
        ->post(route('core-panel.users.reinvite', $target->getKey()))
        ->assertRedirect()
        ->assertSessionHas('status', trans('page-users.users.invited'));

    $target->refresh();

    expect($target->getAttribute('invited_at'))->not->toBeNull()
        ->and($target->getAttribute('invitation_accepted_at'))->toBeNull();

    Mail::assertSent(UserInvitationMail::class, function (UserInvitationMail $mail) use ($target): bool {
        return $mail->hasTo('target-reinvite@example.test')
            && $mail->user->is($target)
            && str_contains($mail->invitationUrl, 'context=invitation');
    });
});

it('blocks re-invites when password resets are disabled', function (): void {
    Mail::fake();
    config()->set('core-panel.auth.password_reset_enabled', false);
    app(SettingsRepository::class)->set('auth', 'password_reset_enabled', false, 'boolean', false);

    $actor = FakeUser::query()->create([
        'email' => 'admin-reinvite-blocked@example.test',
        'first_name' => 'Admin',
        'last_name' => 'Reinvite',
        'password' => Hash::make('secret-password'),
    ]);

    $target = FakeUser::query()->create([
        'email' => 'target-reinvite-blocked@example.test',
        'first_name' => 'Target',
        'invited_at' => now()->subMinutes(10),
        'last_name' => 'Reinvite',
        'password' => Hash::make('secret-password'),
        'requires_password_setup' => true,
    ]);

    $this->actingAs($actor)
        ->from(route('core-panel.users.show', $target->getKey()))
        ->post(route('core-panel.users.reinvite', $target->getKey()))
        ->assertRedirect(route('core-panel.users.show', $target->getKey()))
        ->assertSessionHas('error', trans('page-users.users.invitation_requires_password_reset'));

    Mail::assertNothingSent();
});

it('blocks re-invites for users that already accepted their invitation', function (): void {
    Mail::fake();

    $actor = FakeUser::query()->create([
        'email' => 'admin-reinvite-accepted@example.test',
        'first_name' => 'Admin',
        'last_name' => 'Accepted',
        'password' => Hash::make('secret-password'),
    ]);

    $target = FakeUser::query()->create([
        'email' => 'target-reinvite-accepted@example.test',
        'first_name' => 'Target',
        'invitation_accepted_at' => now()->subMinute(),
        'invited_at' => now()->subMinutes(30),
        'last_name' => 'Accepted',
        'password' => Hash::make('secret-password'),
        'requires_password_setup' => false,
    ]);

    $this->actingAs($actor)
        ->from(route('core-panel.users.show', $target->getKey()))
        ->post(route('core-panel.users.reinvite', $target->getKey()))
        ->assertRedirect(route('core-panel.users.show', $target->getKey()))
        ->assertSessionHas('error', trans('page-users.users.invitation_already_accepted'));

    $target->refresh();

    expect($target->requiresPasswordSetup())->toBeFalse()
        ->and($target->getAttribute('invitation_accepted_at'))->not->toBeNull();

    Mail::assertNothingSent();
});

it('exposes the user locale as the preferred notification locale', function (): void {
    $target = FakeUser::query()->create([
        'email' => 'target-password-locale@example.test',
        'first_name' => 'Target',
        'last_name' => 'Locale',
        'locale' => 'de',
        'password' => Hash::make('secret-password'),
    ]);

    expect($target->preferredLocale())->toBe('de');
});

it('translates password reset mail content through package mail translations', function (): void {
    useGermanPackageTranslations();

    $mailMessage = (new ResetPassword('reset-token'))->toMail(new FakeUser([
        'email' => 'target-password-locale@example.test',
        'locale' => 'de',
    ]));

    expect($mailMessage->subject)->toBe('Passwort zurücksetzen')
        ->and($mailMessage->actionText)->toBe('Passwort zurücksetzen')
        ->and(__('account-mail.greeting'))->toBe('Hallo!')
        ->and(__('account-mail.salutation'))->toBe('Viele Grüße,')
        ->and(__('account-mail.subcopy', [
            'actionText' => 'Passwort zurücksetzen',
        ]))->toBe('Falls du Probleme beim Klicken auf den Button "Passwort zurücksetzen" hast, kopiere den folgenden Link und füge ihn in deinen Webbrowser ein:')
        ->and($mailMessage->view)->toBeArray()
        ->and($mailMessage->view['html'])->toBe('core-panel::emails.notifications.default-html')
        ->and($mailMessage->view['text'])->toBe('core-panel::emails.notifications.default-text');
});

it('translates verification mail content through package mail translations', function (): void {
    useGermanPackageTranslations();

    if (! Route::has('verification.verify')) {
        Route::get('/email/verify/{id}/{hash}', static fn () => 'ok')->name('verification.verify');
    }

    $user = new VerifiableLocalizedFakeUser([
        'id' => 1001,
        'email' => 'target-verify-locale@example.test',
        'locale' => 'de',
    ]);
    $user->exists = true;

    $mailMessage = (new VerifyEmail)->toMail($user);

    expect($mailMessage->subject)->toBe('Verifiziere deine E-Mail-Adresse')
        ->and($mailMessage->actionText)->toBe('E-Mail-Adresse verifizieren')
        ->and($mailMessage->view)->toBeArray()
        ->and($mailMessage->view['html'])->toBe('core-panel::emails.notifications.default-html')
        ->and($mailMessage->view['text'])->toBe('core-panel::emails.notifications.default-text');
});

it('forbids direct password resets for non super-admins', function (): void {
    $actor = FakeUser::query()->create([
        'email' => 'admin-password-forbidden@example.test',
        'first_name' => 'Admin',
        'last_name' => 'Forbidden',
        'password' => Hash::make('secret-password'),
    ]);

    $target = FakeUser::query()->create([
        'email' => 'target-password-forbidden@example.test',
        'first_name' => 'Target',
        'last_name' => 'Forbidden',
        'password' => Hash::make('secret-password'),
    ]);

    $this->actingAs($actor)
        ->put(route('core-panel.users.password.update', $target->getKey()), [
            'password' => 'very-secure-password',
            'password_confirmation' => 'very-secure-password',
        ])
        ->assertForbidden();
});

it('allows super-admins to reset a managed user password directly', function (): void {
    $actor = FakeUser::query()->create([
        'email' => 'super-admin-password-reset@example.test',
        'first_name' => 'Super',
        'last_name' => 'Admin',
        'password' => Hash::make('secret-password'),
    ]);

    Role::findOrCreate('super-admin', 'web');
    $actor->assignRole('super-admin');

    $target = FakeUser::query()->create([
        'email' => 'target-password-reset@example.test',
        'first_name' => 'Target',
        'last_name' => 'Reset',
        'password' => Hash::make('secret-password'),
        'requires_password_setup' => true,
    ]);

    $this->actingAs($actor)
        ->from(route('core-panel.users.show', $target->getKey()))
        ->put(route('core-panel.users.password.update', $target->getKey()), [
            'password' => 'very-secure-password',
            'password_confirmation' => 'very-secure-password',
        ])
        ->assertRedirect(route('core-panel.users.show', $target->getKey()))
        ->assertSessionHas('status', trans('page-users.users.password_reset_directly'));

    $target->refresh();

    expect(Hash::check('very-secure-password', (string) $target->getAttribute('password')))->toBeTrue()
        ->and($target->requiresPasswordSetup())->toBeFalse()
        ->and($target->getAttribute('invitation_accepted_at'))->not->toBeNull();
});
