<?php

declare(strict_types=1);

use CorePanel\Domains\User\Policies\UserPolicy;
use CorePanel\Models\ApiToken;
use CorePanel\Tests\FakeUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    if (! corePanelTestbenchDatabaseAvailable()) {
        $this->markTestSkipped('pdo_sqlite is not available in this environment.');
    }

    $this->migrateScaffoldDatabase();
    Gate::policy(FakeUser::class, UserPolicy::class);
});

it('allows authenticated users to create and delete their own api tokens', function (): void {
    $user = FakeUser::query()->create([
        'email' => 'self-service-token@example.test',
        'first_name' => 'Self',
        'last_name' => 'Service',
        'password' => Hash::make('secret-password'),
    ]);

    $createResponse = $this
        ->actingAs($user)
        ->from(route('core-panel.settings.index', ['tab' => 'api']))
        ->post(route('core-panel.api-tokens.store'), [
            'abilities' => ['users.view'],
            'name' => 'CLI Access',
        ]);

    $createResponse
        ->assertRedirect(route('core-panel.settings.index', ['tab' => 'api']))
        ->assertSessionHas('status', __('page-api-tokens.api_tokens.created'))
        ->assertSessionHas('apiToken');

    $token = ApiToken::query()
        ->where('name', 'CLI Access')
        ->first();

    expect($token)->not->toBeNull();

    $deleteResponse = $this
        ->actingAs($user)
        ->from(route('core-panel.settings.index', ['tab' => 'api']))
        ->delete(route('core-panel.api-tokens.destroy', ['token' => (string) $token?->getKey()]));

    $deleteResponse
        ->assertRedirect(route('core-panel.settings.index', ['tab' => 'api']))
        ->assertSessionHas('status', __('page-api-tokens.api_tokens.deleted'));

    expect(ApiToken::query()->find($token?->getKey())?->revoked)->toBeTrue();
});

it('allows authenticated users to replace their own api tokens', function (): void {
    $user = FakeUser::query()->create([
        'email' => 'replace-token@example.test',
        'first_name' => 'Replace',
        'last_name' => 'Token',
        'password' => Hash::make('secret-password'),
    ]);

    $original = $user->createToken('CLI Access', ['read'])->token;

    $response = $this
        ->actingAs($user)
        ->from(route('core-panel.settings.index', ['tab' => 'api']))
        ->post(route('core-panel.api-tokens.replace', ['token' => (string) $original->getKey()]));

    $response
        ->assertRedirect(route('core-panel.settings.index', ['tab' => 'api']))
        ->assertSessionHas('status', __('page-api-tokens.api_tokens.replaced'))
        ->assertSessionHas('apiToken');

    $original->refresh();

    $replacement = ApiToken::query()
        ->where('name', 'CLI Access')
        ->whereKeyNot($original->getKey())
        ->latest('created_at')
        ->first();

    expect($original->revoked)->toBeTrue()
        ->and($replacement)->not->toBeNull()
        ->and($replacement?->revoked)->toBeFalse()
        ->and($replacement?->scopes)->toBe(['read']);
});

it('accepts passport bearer tokens on the versioned api me endpoint', function (): void {
    $user = FakeUser::query()->create([
        'email' => 'token-auth@example.test',
        'first_name' => 'Token',
        'last_name' => 'Auth',
        'password' => Hash::make('secret-password'),
    ]);

    $plainTextToken = $user->createToken('Postman', ['read'])->accessToken;
    $token = ApiToken::query()
        ->where('name', 'Postman')
        ->latest('created_at')
        ->first();

    $response = $this->withHeaders([
        'Accept' => 'application/json',
        'Authorization' => 'Bearer '.$plainTextToken,
    ])->getJson('/api/v1/me');

    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.email', 'token-auth@example.test');

    expect($token)->not->toBeNull();

    $token?->refresh();

    expect($token?->last_used_at)->not->toBeNull();
});
