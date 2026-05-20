<?php

declare(strict_types=1);

use CorePanel\Database\Seeders\CorePanelPermissionSeeder;
use CorePanel\Domains\Permission\Actions\ResyncAccessMatrixAction;
use CorePanel\Support\Permissions\CorePanelAccess;
use CorePanel\Support\Permissions\PermissionService;
use CorePanel\Tests\FakeUser;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    if (! corePanelTestbenchDatabaseAvailable()) {
        test()->markTestSkipped('pdo_sqlite is not available in this environment.');
    }

    $this->migrateScaffoldDatabase();
});

it('seeds managed permissions and predefined roles from the access configuration', function (): void {
    app(CorePanelPermissionSeeder::class)->run(
        app(PermissionService::class),
        app(CorePanelAccess::class),
    );

    expect(Permission::query()->where('name', 'users.view')->exists())->toBeTrue()
        ->and(Permission::query()->where('name', 'core-panel.view-horizon')->exists())->toBeTrue()
        ->and(Role::query()->where('name', 'super-admin')->exists())->toBeTrue()
        ->and(Role::query()->where('name', 'admin')->exists())->toBeTrue()
        ->and(Role::query()->where('name', 'user')->exists())->toBeTrue();

    $adminRole = Role::query()->where('name', 'admin')->firstOrFail();

    expect($adminRole->getAttribute('core_panel_group'))->toBe('system')
        ->and((bool) $adminRole->getAttribute('core_panel_is_protected'))->toBeTrue();
});

it('resynchronizes managed access additively and preserves manual role extras', function (): void {
    $seeder = app(CorePanelPermissionSeeder::class);
    $seeder->run(app(PermissionService::class), app(CorePanelAccess::class));

    $adminRole = Role::query()->where('name', 'admin')->firstOrFail();
    Permission::findOrCreate('roles.delete', 'web');
    $adminRole->givePermissionTo('roles.delete');

    config()->set('core-panel-access.resources.reports', ['view']);
    config()->set('core-panel-access.permission_groups.access', [
        ...config('core-panel-access.permission_groups.access', []),
        'reports',
    ]);
    config()->set('core-panel-access.labels.resources.reports', [
        'en' => 'Reports',
        'de' => 'Berichte',
    ]);
    config()->set('core-panel-access.role_permissions.admin', [
        ...config('core-panel-access.role_permissions.admin', []),
        'reports.view',
    ]);

    app(ResyncAccessMatrixAction::class)->execute();

    $adminRole->refresh()->load('permissions');
    $permissions = $adminRole->permissions->pluck('name')->all();

    expect($permissions)->toContain('reports.view')
        ->and($permissions)->toContain('roles.delete');
});

it('fresh resynchronization removes managed role permission drift', function (): void {
    $seeder = app(CorePanelPermissionSeeder::class);
    $seeder->run(app(PermissionService::class), app(CorePanelAccess::class));

    $adminRole = Role::query()->where('name', 'admin')->firstOrFail();
    Permission::findOrCreate('roles.delete', 'web');
    $adminRole->givePermissionTo('roles.delete');

    app(ResyncAccessMatrixAction::class)->execute(true);

    $adminRole->refresh()->load('permissions');

    expect($adminRole->permissions->pluck('name')->all())->not->toContain('roles.delete');
});

it('counts assigned role users through the string-based permission pivot for uuid user models', function (): void {
    config()->set('core-panel.user_model', FakeUser::class);

    $seeder = app(CorePanelPermissionSeeder::class);
    $seeder->run(app(PermissionService::class), app(CorePanelAccess::class));

    $superAdminRole = Role::query()->where('name', 'super-admin')->firstOrFail();
    $user = FakeUser::query()->create([
        'first_name' => 'Admin',
        'last_name' => 'User',
        'email' => 'admin@example.test',
        'password' => 'secret-password',
        'status' => 'active',
    ]);

    $user->assignRole('super-admin');

    expect(DB::table('model_has_roles')
        ->where('role_id', $superAdminRole->getKey())
        ->where('model_type', $user->getMorphClass())
        ->where('model_id', (string) $user->getKey())
        ->exists())->toBeTrue();

    $countsMethod = new ReflectionMethod(PermissionService::class, 'roleUserCounts');
    $countsMethod->setAccessible(true);
    $counts = $countsMethod->invoke(app(PermissionService::class));

    expect($counts[(string) $superAdminRole->getKey()] ?? 0)->toBe(1);

    $role = app(PermissionService::class)->roles()
        ->first(static fn (Role $role): bool => $role->is($superAdminRole));

    expect($role)->not->toBeNull()
        ->and((int) $role->getAttribute('users_count'))->toBe(1);
});

it('resolves translated permission group labels for matrix payloads', function (): void {
    app()->setLocale('de');

    $labels = app(CorePanelAccess::class)->groupLabels();

    expect($labels)
        ->toMatchArray([
            'developer' => 'Entwicklung',
            'other' => 'Andere',
            'system' => 'System',
            'users' => 'Benutzerverwaltung',
        ]);
});
