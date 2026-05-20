<?php

declare(strict_types=1);

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

it('translates password reset mail content through the scaffold json locale files', function (): void {
    app('translator')->addJsonPath(__DIR__.'/../../stubs/lang');
    app()->setLocale('de');

    $mailMessage = (new ResetPassword('reset-token'))->toMail(new FakeUser([
        'email' => 'target-password-locale@example.test',
    ]));

    expect($mailMessage->subject)->toBe('Passwort zurücksetzen')
        ->and($mailMessage->actionText)->toBe('Passwort zurücksetzen')
        ->and(__('Hello!'))->toBe('Hallo!')
        ->and(__('Regards,'))->toBe('Viele Grüße,')
        ->and(__('If you\'re having trouble clicking the ":actionText" button, copy and paste the URL below into your web browser:', [
            'actionText' => 'Passwort zurücksetzen',
        ]))->toBe('Falls du Probleme beim Klicken auf den Button "Passwort zurücksetzen" hast, kopiere den folgenden Link und füge ihn in deinen Webbrowser ein:')
        ->and(__('If you\'re having trouble clicking the ":actionText" button, copy and paste the URL below'."\n".'into your web browser:', [
            'actionText' => 'Passwort zurücksetzen',
        ]))->toBe('Falls du Probleme beim Klicken auf den Button "Passwort zurücksetzen" hast, kopiere den folgenden Link und füge ihn in deinen Webbrowser ein:');
});

it('translates verification mail content through the scaffold json locale files', function (): void {
    app('translator')->addJsonPath(__DIR__.'/../../stubs/lang');
    app()->setLocale('de');

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
        ->and($mailMessage->actionText)->toBe('E-Mail-Adresse verifizieren');
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
        ->and($target->requiresPasswordSetup())->toBeFalse();
});
