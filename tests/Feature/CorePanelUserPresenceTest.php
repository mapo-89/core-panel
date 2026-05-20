<?php

declare(strict_types=1);

use CorePanel\Domains\User\Policies\UserPolicy;
use CorePanel\Support\Presence\PresenceManager;
use CorePanel\Tests\FakeUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    if (! corePanelTestbenchDatabaseAvailable()) {
        $this->markTestSkipped('pdo_sqlite is not available in this environment.');
    }

    config()->set('auth.providers.users.model', FakeUser::class);
    config()->set('core-panel.user_model', FakeUser::class);
    $this->migrateScaffoldDatabase();
    Gate::policy(FakeUser::class, UserPolicy::class);
});

it('stores a heartbeat for the authenticated user', function (): void {
    $user = FakeUser::query()->create([
        'email' => 'presence-heartbeat@example.test',
        'first_name' => 'Presence',
        'last_name' => 'Heartbeat',
        'password' => Hash::make('secret-password'),
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user)->postJson('/presence/heartbeat');

    $response->assertSuccessful()
        ->assertJsonPath('data.0.userId', (string) $user->getKey())
        ->assertJsonPath('data.0.status', 'online');

    expect(app(PresenceManager::class)->lastSeenTimestamp($user))->toBeInt();
});

it('returns long-poll presence updates for tracked users', function (): void {
    $actor = FakeUser::query()->create([
        'email' => 'presence-actor@example.test',
        'first_name' => 'Presence',
        'last_name' => 'Actor',
        'password' => Hash::make('secret-password'),
        'email_verified_at' => now(),
    ]);

    $trackedUser = FakeUser::query()->create([
        'email' => 'presence-tracked@example.test',
        'first_name' => 'Tracked',
        'last_name' => 'User',
        'password' => Hash::make('secret-password'),
        'email_verified_at' => now(),
    ]);

    $touchResult = app(PresenceManager::class)->touch($trackedUser);

    $response = $this->actingAs($actor)->getJson(
        '/presence/updates?cursor=0&ids[]='.$trackedUser->getKey(),
    );

    $response->assertSuccessful()
        ->assertJsonPath('meta.cursor', $touchResult['cursor'])
        ->assertJsonPath('data.0.userId', (string) $trackedUser->getKey())
        ->assertJsonPath('data.0.status', 'online');
});
