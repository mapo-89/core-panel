<?php

declare(strict_types=1);

use CorePanel\Tests\FakeUser;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

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
