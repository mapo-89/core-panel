<?php

declare(strict_types=1);

use CorePanel\Domains\User\Policies\UserPolicy;
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
    $this->migrateScaffoldDatabase();
    Gate::policy(FakeUser::class, UserPolicy::class);
});

it('serves the versioned public api endpoints', function (): void {
    $response = $this->getJson('/api/v1/ping');

    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.package', 'core-panel')
        ->assertJsonPath('meta.version', 'v1');
});

it('serves the versioned me endpoint for read tokens', function (): void {
    $user = FakeUser::query()->create([
        'email' => 'versioned-me@example.test',
        'first_name' => 'Versioned',
        'last_name' => 'Me',
        'password' => Hash::make('secret-password'),
        'email_verified_at' => now(),
    ]);

    $plainTextToken = $user->createToken('Versioned Me', ['read'])->accessToken;

    $response = $this->withHeaders([
        'Accept' => 'application/json',
        'Authorization' => 'Bearer '.$plainTextToken,
    ])->getJson('/api/v1/me');

    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'Versioned Me')
        ->assertJsonPath('data.email', 'versioned-me@example.test')
        ->assertJsonPath('data.presenceStatus', 'offline')
        ->assertJsonPath('data.presenceLastSeenAt', null)
        ->assertJsonPath('meta.version', 'v1');
});

it('does not expose the legacy api me endpoint anymore', function (): void {
    $user = FakeUser::query()->create([
        'email' => 'legacy-me@example.test',
        'first_name' => 'Legacy',
        'last_name' => 'Removed',
        'password' => Hash::make('secret-password'),
        'email_verified_at' => now(),
    ]);

    $plainTextToken = $user->createToken('Legacy Me', ['read'])->accessToken;

    $response = $this->withHeaders([
        'Accept' => 'application/json',
        'Authorization' => 'Bearer '.$plainTextToken,
    ])->getJson('/api/me');

    $response->assertNotFound();
});

it('serves a versioned users api for authorized read tokens', function (): void {
    $actor = FakeUser::query()->create([
        'email' => 'versioned-users-actor@example.test',
        'first_name' => 'Versioned',
        'last_name' => 'Actor',
        'password' => Hash::make('secret-password'),
        'email_verified_at' => now(),
    ]);

    $target = FakeUser::query()->create([
        'email' => 'versioned-users-target@example.test',
        'first_name' => 'Target',
        'last_name' => 'User',
        'password' => Hash::make('secret-password'),
        'email_verified_at' => now(),
    ]);

    Permission::findOrCreate('users.view', 'web');
    Role::findOrCreate('super-admin', 'web')->givePermissionTo('users.view');
    $actor->assignRole('super-admin');

    $plainTextToken = $actor->createToken('Versioned Users', ['read'])->accessToken;

    $indexResponse = $this->withHeaders([
        'Accept' => 'application/json',
        'Authorization' => 'Bearer '.$plainTextToken,
    ])->getJson('/api/v1/users?sort=first_name');

    $indexResponse->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('meta.version', 'v1')
        ->assertJsonPath('meta.pagination.total', 2);

    expect($indexResponse->json('data'))
        ->toBeArray()
        ->and(collect($indexResponse->json('data'))->pluck('email')->all())
        ->toContain('versioned-users-actor@example.test', 'versioned-users-target@example.test');

    $showResponse = $this->withHeaders([
        'Accept' => 'application/json',
        'Authorization' => 'Bearer '.$plainTextToken,
    ])->getJson('/api/v1/users/'.$target->getKey());

    $showResponse->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.email', 'versioned-users-target@example.test')
        ->assertJsonPath('meta.version', 'v1');
});
