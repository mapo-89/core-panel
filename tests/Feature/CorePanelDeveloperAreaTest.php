<?php

declare(strict_types=1);

use CorePanel\Tests\FakeUser;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    if (! corePanelTestbenchDatabaseAvailable()) {
        $this->markTestSkipped('pdo_sqlite is not available in this environment.');
    }

    $this->migrateScaffoldDatabase();
});

it('renders the developer area with route tabs and swagger docs for authorized users', function (): void {
    Route::middleware('web')->get('/api/example', static fn (): array => ['ok' => true])->name('api.example');

    $user = FakeUser::query()->create([
        'email' => 'developer@example.test',
        'email_verified_at' => now(),
        'first_name' => 'Dev',
        'last_name' => 'User',
        'password' => bcrypt('password'),
    ]);

    Role::findOrCreate('super-admin', 'web');
    Permission::findOrCreate('api-routes.view', 'web');
    Permission::findOrCreate('api-docs.view', 'web');
    $user->assignRole('super-admin');

    $this->actingAs($user)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->get(route('core-panel.developer.index'))
        ->assertOk()
        ->assertJsonPath('component', 'Developer/Index')
        ->assertJsonPath('props.activeTab', 'api')
        ->assertJsonPath('props.docsTab.docsUrl', '/api/documentation')
        ->assertJsonPath('props.apiTab.routes.currentPage', 1)
        ->assertJsonFragment([
            'name' => 'api.example',
            'uri' => '/api/example',
        ]);
});

it('forbids the developer area without developer permissions', function (): void {
    $user = FakeUser::query()->create([
        'email' => 'viewer@example.test',
        'email_verified_at' => now(),
        'first_name' => 'View',
        'last_name' => 'Only',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user)
        ->get(route('core-panel.developer.index'))
        ->assertForbidden();
});

it('can regenerate swagger docs for authorized users', function (): void {
    $user = FakeUser::query()->create([
        'email' => 'developer@example.test',
        'email_verified_at' => now(),
        'first_name' => 'Dev',
        'last_name' => 'User',
        'password' => bcrypt('password'),
    ]);

    Role::findOrCreate('super-admin', 'web');
    Permission::findOrCreate('api-docs.view', 'web');
    $user->assignRole('super-admin');

    $response = $this->actingAs($user)
        ->from(route('core-panel.developer.index'))
        ->post(route('core-panel.developer.regenerate-api-docs'))
        ->assertRedirect(route('core-panel.developer.index'));

    expect($response->getSession()->get('success') ?? $response->getSession()->get('warning'))
        ->toBeIn([
            __('page-developer.actions.generate_docs_success'),
            __('page-developer.actions.generate_docs_unavailable'),
        ]);
});
