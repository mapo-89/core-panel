<?php

declare(strict_types=1);

use CorePanel\Domains\Permission\Policies\RolePolicy;
use CorePanel\Domains\User\Policies\UserPolicy;
use CorePanel\Http\Middleware\CheckPermission;
use CorePanel\Http\Middleware\EnsureCorePanelEmailIsVerified;
use CorePanel\Tests\FakeUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    if (! corePanelTestbenchDatabaseAvailable()) {
        $this->markTestSkipped('pdo_sqlite is not available in this environment.');
    }

    config()->set('auth.providers.users.model', FakeUser::class);
    config()->set('core-panel.user_model', FakeUser::class);
    config()->set('permission.teams', false);
    config()->set('permission.testing', false);
    Gate::policy(FakeUser::class, UserPolicy::class);
    Gate::policy(Role::class, RolePolicy::class);

    $this->migrateScaffoldDatabase();

    app(PermissionRegistrar::class)->teams = false;
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('forbids non super-admins from updating the super-admin role', function (): void {
    Permission::findOrCreate('roles.update', 'web');

    $superAdminRole = Role::findOrCreate('super-admin', 'web');

    $actor = FakeUser::query()->create([
        'email' => 'role-admin@example.test',
        'email_verified_at' => now(),
        'first_name' => 'Role',
        'last_name' => 'Admin',
        'password' => Hash::make('secret-password'),
    ]);
    $actor->givePermissionTo('roles.update');

    $this->actingAs($actor)
        ->put(route('core-panel.roles.update', $superAdminRole->getKey()), [
            'name' => 'super-admin',
            'guard_name' => 'web',
            'permissions' => [],
        ])
        ->assertForbidden();
});

it('hides super-admin roles from non super-admin user management payloads', function (): void {
    $this->withoutMiddleware([CheckPermission::class, EnsureCorePanelEmailIsVerified::class]);
    Gate::before(static fn ($user, string $ability): ?bool => $ability === 'viewAny' ? true : null);

    Permission::findOrCreate('users.view', 'web');
    Permission::findOrCreate('roles.view', 'web');

    $superAdminRole = Role::findOrCreate('super-admin', 'web');

    $actor = FakeUser::query()->create([
        'email' => 'users-admin@example.test',
        'email_verified_at' => now(),
        'first_name' => 'Users',
        'last_name' => 'Admin',
        'password' => Hash::make('secret-password'),
    ]);
    $actor->givePermissionTo(['users.view', 'roles.view']);

    $target = FakeUser::query()->create([
        'email' => 'managed-super-admin@example.test',
        'first_name' => 'Managed',
        'last_name' => 'Super',
        'password' => Hash::make('secret-password'),
    ]);
    $target->assignRole($superAdminRole);

    $response = $this->actingAs($actor)->get(route('core-panel.users.index'), [
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
    ]);

    $response->assertOk();

    /** @var array<string, mixed> $payload */
    $payload = $response->json();
    $props = $payload['props'] ?? [];
    $roles = $props['roles'] ?? [];
    $roleLabels = $props['roleLabels'] ?? [];
    $users = collect($props['users'] ?? []);
    $targetPayload = $users->firstWhere('email', 'managed-super-admin@example.test');

    expect($roles)->each(
        fn (array $role): bool => $role['name'] !== 'super-admin'
    )
        ->and($roleLabels)->not->toHaveKey('super-admin')
        ->and($targetPayload)->toBeArray()
        ->and($targetPayload['roles'])->toBe([]);
});

it('falls back to the users tab and strips role payloads when the actor lacks role permissions', function (): void {
    $this->withoutMiddleware([CheckPermission::class, EnsureCorePanelEmailIsVerified::class]);
    Gate::before(static fn ($user, string $ability): ?bool => $ability === 'viewAny' ? true : null);

    Permission::findOrCreate('users.view', 'web');

    Role::findOrCreate('super-admin', 'web');

    $actor = FakeUser::query()->create([
        'email' => 'users-viewer@example.test',
        'email_verified_at' => now(),
        'first_name' => 'Users',
        'last_name' => 'Viewer',
        'password' => Hash::make('secret-password'),
    ]);
    $actor->givePermissionTo('users.view');

    $response = $this->actingAs($actor)->get(route('core-panel.users.index', [
        'tab' => 'roles',
    ]), [
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
    ]);

    $response->assertOk()
        ->assertJsonPath('props.activeTab', 'users')
        ->assertJsonPath('props.roles', [])
        ->assertJsonPath('props.assignableRoles', [])
        ->assertJsonPath('props.permissionDefaults', [])
        ->assertJsonPath('props.permissions', []);
});

it('does not expose user update capabilities to actors without user update permission', function (): void {
    $this->withoutMiddleware([CheckPermission::class, EnsureCorePanelEmailIsVerified::class]);
    Gate::before(static fn ($user, string $ability): ?bool => in_array($ability, ['view', 'viewAny'], true) ? true : null);

    Permission::findOrCreate('users.view', 'web');
    Permission::findOrCreate('users.update', 'web');

    $actor = FakeUser::query()->create([
        'email' => 'users-readonly@example.test',
        'email_verified_at' => now(),
        'first_name' => 'Users',
        'last_name' => 'Readonly',
        'password' => Hash::make('secret-password'),
    ]);
    $actor->givePermissionTo('users.view');

    $target = FakeUser::query()->create([
        'email' => 'readonly-target@example.test',
        'email_verified_at' => now(),
        'first_name' => 'Readonly',
        'last_name' => 'Target',
        'password' => Hash::make('secret-password'),
    ]);

    $indexResponse = $this->actingAs($actor)->get(route('core-panel.users.index'), [
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
    ]);

    $indexResponse->assertOk();

    /** @var array<string, mixed> $indexPayload */
    $indexPayload = $indexResponse->json();
    $indexUsers = collect($indexPayload['props']['users'] ?? []);
    $targetPayload = $indexUsers->firstWhere('email', 'readonly-target@example.test');

    expect($targetPayload)->toBeArray()
        ->and($targetPayload['canUpdate'])->toBeFalse();

    $this->actingAs($actor)
        ->get(route('core-panel.users.edit', $target->getKey()))
        ->assertForbidden();

    $this->actingAs($actor)
        ->put(route('core-panel.users.update', $target->getKey()), [
            'email' => 'readonly-target-updated@example.test',
            'first_name' => 'Readonly',
            'last_name' => 'Target',
            'password' => '',
            'password_confirmation' => '',
            'status' => 'active',
            'user_group_ids' => [],
        ])
        ->assertForbidden();
});

it('rejects assigning the super-admin role through user updates for non super-admins', function (): void {
    $this->withoutMiddleware([CheckPermission::class, EnsureCorePanelEmailIsVerified::class]);

    Permission::findOrCreate('users.update', 'web');
    Permission::findOrCreate('roles.update', 'web');
    Permission::findOrCreate('roles.view', 'web');

    Role::findOrCreate('super-admin', 'web');
    Role::findOrCreate('admin', 'web');

    $actor = FakeUser::query()->create([
        'email' => 'user-editor@example.test',
        'email_verified_at' => now(),
        'first_name' => 'User',
        'last_name' => 'Editor',
        'password' => Hash::make('secret-password'),
    ]);
    $actor->givePermissionTo(['users.update', 'roles.update', 'roles.view']);

    $target = FakeUser::query()->create([
        'email' => 'target-user@example.test',
        'first_name' => 'Target',
        'last_name' => 'User',
        'password' => Hash::make('secret-password'),
    ]);

    $this->actingAs($actor)
        ->from(route('core-panel.users.edit', $target->getKey()))
        ->put(route('core-panel.users.update', $target->getKey()), [
            'email' => 'target-user@example.test',
            'first_name' => 'Target',
            'last_name' => 'User',
            'password' => '',
            'password_confirmation' => '',
            'role_names' => ['super-admin'],
            'status' => 'active',
            'user_group_ids' => [],
        ])
        ->assertRedirect(route('core-panel.users.edit', $target->getKey()))
        ->assertSessionHasErrors('role_names');

    expect($target->fresh()->hasRole('super-admin'))->toBeFalse();
});

it('forbids importing user groups without the dedicated import permission', function (): void {
    Permission::findOrCreate('user-groups.create', 'web');

    $actor = FakeUser::query()->create([
        'email' => 'user-groups-editor@example.test',
        'email_verified_at' => now(),
        'first_name' => 'Groups',
        'last_name' => 'Editor',
        'password' => Hash::make('secret-password'),
    ]);
    $actor->givePermissionTo('user-groups.create');

    $file = UploadedFile::fake()->createWithContent(
        'user-groups.csv',
        "name,color\nSupport,#FF0000\n",
    );

    $this->actingAs($actor)
        ->post(route('core-panel.user-groups.import'), [
            'file' => $file,
        ])
        ->assertForbidden();
});
