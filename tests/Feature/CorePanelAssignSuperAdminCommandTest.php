<?php

declare(strict_types=1);

use CorePanel\Tests\FakeUser;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    if (! corePanelTestbenchDatabaseAvailable()) {
        test()->markTestSkipped('pdo_sqlite is not available in this environment.');
    }

    config()->set('auth.providers.users.model', FakeUser::class);
    config()->set('core-panel.user_model', FakeUser::class);

    $this->migrateScaffoldDatabase();
    app(PermissionRegistrar::class)->teams = false;
});

it('assigns the super-admin role to an existing user by email and synchronizes managed access', function (): void {
    /** @var FakeUser $user */
    $user = FakeUser::query()->create([
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@example.test',
        'password' => bcrypt('secret-password'),
        'status' => 'active',
    ]);

    expect(Role::query()->where('name', 'super-admin')->exists())->toBeFalse();

    $this->artisan('core-panel:user:assign-super-admin', [
        'user' => 'ada@example.test',
    ])->assertExitCode(0);

    $user->refresh();

    expect($user->hasRole('super-admin'))->toBeTrue()
        ->and(Role::query()->where('name', 'super-admin')->exists())->toBeTrue()
        ->and(Role::query()->where('name', 'super-admin')->firstOrFail()->permissions()->count())->toBeGreaterThan(0);
});

it('fails when the user cannot be found', function (): void {
    $this->artisan('core-panel:user:assign-super-admin', [
        'user' => 'missing@example.test',
    ])->assertExitCode(1);
});
