<?php

declare(strict_types=1);

use CorePanel\Models\SocialAccount as SocialAccountModel;
use CorePanel\Support\Socialite\SocialAccountStore;
use CorePanel\Tests\FakeUser as User;
use Illuminate\Support\Str;

beforeEach(function (): void {
    if (! corePanelTestbenchDatabaseAvailable()) {
        $this->markTestSkipped('pdo_sqlite is not available.');
    }
    $this->migrateScaffoldDatabase();
});

test('microsoft login preserves the existing refresh token when none is returned', function (?string $refreshToken): void {
    $user = createCorePanelStoreUser();
    $store = app(SocialAccountStore::class);
    $store->upsertForUserWithAttributes($user, 'microsoft', [
        'provider_user_id' => 'provider-user-id',
        'refresh_token' => 'original-refresh',
        'token' => 'original-access',
    ]);
    $account = $store->upsertForUserWithAttributes($user, 'microsoft', [
        'provider_user_id' => 'provider-user-id',
        'refresh_token' => $refreshToken,
        'token' => 'new-access',
    ]);

    expect($account->fresh()?->getAttribute('refresh_token_encrypted'))->toBe('original-refresh')
        ->and($account->getAttribute('token_encrypted'))->toBe('new-access');
})->with([null, '', ' ']);

test('microsoft login replaces a refresh token when the provider returns one', function (): void {
    $user = createCorePanelStoreUser();
    $store = app(SocialAccountStore::class);
    foreach (['original-refresh', 'rotated-refresh'] as $token) {
        $store->upsertForUserWithAttributes($user, 'microsoft', [
            'provider_user_id' => 'provider-user-id', 'refresh_token' => $token,
        ]);
    }

    expect(SocialAccountModel::query()->sole()->getAttribute('refresh_token_encrypted'))->toBe('rotated-refresh');
});

test('microsoft account takeover does not inherit another users refresh token', function (): void {
    $store = app(SocialAccountStore::class);
    $store->upsertForUserWithAttributes(createCorePanelStoreUser(), 'microsoft', [
        'provider_user_id' => 'provider-user-id', 'refresh_token' => 'original-refresh',
    ]);
    $account = $store->upsertForUserWithAttributes(createCorePanelStoreUser(), 'microsoft', [
        'provider_user_id' => 'provider-user-id', 'token' => 'new-access',
    ]);

    expect($account->getAttribute('refresh_token_encrypted'))->toBeNull();
});

function createCorePanelStoreUser(): User
{
    return User::query()->create([
        'email' => Str::uuid().'@example.test',
        'first_name' => 'Microsoft',
        'last_name' => 'Tester',
        'password' => 'unused-test-password',
    ]);
}
