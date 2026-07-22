<?php

declare(strict_types=1);

use CorePanel\Domain\User\Policies\UserPolicy;
use CorePanel\Support\Users\UserModelManager;
use CorePanel\Tests\FakeUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    if (! corePanelTestbenchDatabaseAvailable()) {
        $this->markTestSkipped('pdo_sqlite is not available in this environment.');
    }

    config()->set('auth.providers.users.model', FakeUser::class);
    config()->set('core-panel.user_model', FakeUser::class);
    Gate::policy(FakeUser::class, UserPolicy::class);

    $this->migrateScaffoldDatabase();

    Permission::findOrCreate('users.delete', 'web');
    Role::findOrCreate('super-admin', 'web')->givePermissionTo('users.delete');
    Role::findOrCreate('admin', 'web')->givePermissionTo('users.delete');
});

it('prevents deleting the last active super-admin', function (): void {
    $actor = FakeUser::query()->create([
        'email' => 'last-super-admin@example.test',
        'first_name' => 'Last',
        'last_name' => 'Super',
        'password' => Hash::make('secret-password'),
    ]);
    $actor->assignRole('super-admin');

    $this->actingAs($actor)
        ->delete(route('core-panel.users.destroy', $actor->getKey()))
        ->assertForbidden();
});

it('prevents a super-admin from deleting themselves when multiple super-admins exist', function (): void {
    $actor = FakeUser::query()->create([
        'email' => 'self-delete-super-admin@example.test',
        'first_name' => 'Self',
        'last_name' => 'Delete',
        'password' => Hash::make('secret-password'),
    ]);
    $actor->assignRole('super-admin');

    $other = FakeUser::query()->create([
        'email' => 'other-super-admin@example.test',
        'first_name' => 'Other',
        'last_name' => 'Admin',
        'password' => Hash::make('secret-password'),
    ]);
    $other->assignRole('super-admin');

    $this->actingAs($actor)
        ->delete(route('core-panel.users.destroy', $actor->getKey()))
        ->assertForbidden();
});

it('prevents non super-admins from deleting a super-admin', function (): void {
    $actor = FakeUser::query()->create([
        'email' => 'plain-admin@example.test',
        'first_name' => 'Plain',
        'last_name' => 'Admin',
        'password' => Hash::make('secret-password'),
    ]);
    $actor->assignRole('admin');

    $firstSuperAdmin = FakeUser::query()->create([
        'email' => 'first-super-admin@example.test',
        'first_name' => 'First',
        'last_name' => 'Super',
        'password' => Hash::make('secret-password'),
    ]);
    $firstSuperAdmin->assignRole('super-admin');

    $secondSuperAdmin = FakeUser::query()->create([
        'email' => 'second-super-admin@example.test',
        'first_name' => 'Second',
        'last_name' => 'Super',
        'password' => Hash::make('secret-password'),
    ]);
    $secondSuperAdmin->assignRole('super-admin');

    $this->actingAs($actor)
        ->delete(route('core-panel.users.destroy', $firstSuperAdmin->getKey()))
        ->assertForbidden();
});

it('allows deleting another super-admin when at least one other super-admin remains', function (): void {
    $actor = FakeUser::query()->create([
        'email' => 'deleting-super-admin@example.test',
        'first_name' => 'Deleting',
        'last_name' => 'Admin',
        'password' => Hash::make('secret-password'),
    ]);
    $actor->assignRole('super-admin');

    $target = FakeUser::query()->create([
        'email' => 'target-super-admin@example.test',
        'first_name' => 'Target',
        'last_name' => 'Admin',
        'password' => Hash::make('secret-password'),
    ]);
    $target->assignRole('super-admin');

    $this->actingAs($actor)
        ->from(route('core-panel.users.index'))
        ->delete(route('core-panel.users.destroy', $target->getKey()))
        ->assertRedirect(route('core-panel.users.index'))
        ->assertSessionHas('status', trans('page-users.users.deleted'));

    expect(FakeUser::query()->find($target->getKey()))->toBeNull();
});

it('counts remaining super-admins through the string permission pivot for uuid user models', function (): void {
    $firstSuperAdmin = FakeUser::query()->create([
        'email' => 'count-first-super-admin@example.test',
        'first_name' => 'Count',
        'last_name' => 'First',
        'password' => Hash::make('secret-password'),
    ]);
    $firstSuperAdmin->assignRole('super-admin');

    $secondSuperAdmin = FakeUser::query()->create([
        'email' => 'count-second-super-admin@example.test',
        'first_name' => 'Count',
        'last_name' => 'Second',
        'password' => Hash::make('secret-password'),
    ]);
    $secondSuperAdmin->assignRole('super-admin');

    $manager = app(UserModelManager::class);

    expect($manager->activeSuperAdminCount())->toBe(2)
        ->and($manager->activeSuperAdminCount($firstSuperAdmin))->toBe(1);
});
