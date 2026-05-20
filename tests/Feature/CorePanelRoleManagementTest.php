<?php

declare(strict_types=1);

use CorePanel\Tests\FakeUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    if (! corePanelTestbenchDatabaseAvailable()) {
        $this->markTestSkipped('pdo_sqlite is not available in this environment.');
    }

    $this->migrateScaffoldDatabase();

    Gate::before(static fn (...$arguments): bool => true);
});

it('syncs selected permissions when storing a role', function (): void {
    $user = FakeUser::query()->create([
        'email' => 'roles-store@example.test',
        'first_name' => 'Role',
        'last_name' => 'Manager',
        'password' => Hash::make('secret-password'),
    ]);

    Permission::findOrCreate('users.view', 'web');
    Permission::findOrCreate('users.update', 'web');

    $response = $this
        ->actingAs($user)
        ->from(route('core-panel.roles.index'))
        ->post(route('core-panel.roles.store'), [
            'name' => 'auditor',
            'guard_name' => 'web',
            'permissions' => ['users.view', 'users.update'],
        ]);

    $response
        ->assertRedirect(route('core-panel.roles.index'))
        ->assertSessionHas('status', 'Role created.');

    $role = Role::query()
        ->where('name', 'auditor')
        ->with('permissions')
        ->firstOrFail();

    expect($role->permissions->pluck('name')->all())
        ->toBe(['users.view', 'users.update']);
});

it('redirects newly created roles into the matrix when requested by the create dialog flow', function (): void {
    $user = FakeUser::query()->create([
        'email' => 'roles-store-matrix@example.test',
        'first_name' => 'Role',
        'last_name' => 'Creator',
        'password' => Hash::make('secret-password'),
    ]);

    $response = $this
        ->actingAs($user)
        ->from(route('core-panel.roles.index'))
        ->post(route('core-panel.roles.store'), [
            'name' => 'reviewer',
            'guard_name' => 'web',
            'redirect_to_matrix' => true,
        ]);

    $role = Role::query()
        ->where('name', 'reviewer')
        ->firstOrFail();

    $response
        ->assertRedirect(route('core-panel.roles.matrix', [
            'role' => $role->getKey(),
        ]))
        ->assertSessionHas('status', 'Role created.');
});

it('syncs an empty permission list when updating a role without selections', function (): void {
    $user = FakeUser::query()->create([
        'email' => 'roles-update@example.test',
        'first_name' => 'Role',
        'last_name' => 'Editor',
        'password' => Hash::make('secret-password'),
    ]);

    $role = Role::query()->create([
        'name' => 'auditor',
        'guard_name' => 'web',
    ]);

    Permission::findOrCreate('users.view', 'web');
    $role->givePermissionTo('users.view');

    $response = $this
        ->actingAs($user)
        ->from(route('core-panel.roles.index'))
        ->put(route('core-panel.roles.update', $role->getKey()), [
            'name' => 'reviewer',
            'guard_name' => 'web',
            'permissions' => [],
        ]);

    $response
        ->assertRedirect(route('core-panel.roles.index'))
        ->assertSessionHas('status', 'Role updated.');

    $role->refresh()->load('permissions');

    expect($role->getAttribute('name'))->toBe('reviewer')
        ->and($role->permissions)->toHaveCount(0);
});

it('resolves nested role and permission translation keys for status flashes', function (): void {
    expect(trans('page-roles.permissions.created'))->toBe('Permission created.')
        ->and(trans('page-roles.permissions.updated'))->toBe('Permission updated.')
        ->and(trans('page-roles.permissions.deleted'))->toBe('Permission deleted.')
        ->and(trans('page-roles.permissions.users.view'))->toBe('View users')
        ->and(trans('page-roles.roles.created'))->toBe('Role created.')
        ->and(trans('page-roles.roles.permissions_updated'))->toBe('Role permissions updated.')
        ->and(trans('page-roles.roles.resynced'))->toBe('Managed roles and permissions synchronized.');
});
