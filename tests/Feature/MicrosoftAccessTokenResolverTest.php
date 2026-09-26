<?php

declare(strict_types=1);

use CorePanel\Models\SocialAccount;
use CorePanel\Support\Settings\SettingsRepository;
use CorePanel\Support\Socialite\MicrosoftAccessTokenResolver;
use CorePanel\Tests\FakeUser as User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

beforeEach(function (): void {
    if (! corePanelTestbenchDatabaseAvailable()) {
        $this->markTestSkipped('pdo_sqlite is not available.');
    }
    $this->migrateScaffoldDatabase();
});

it('returns the current microsoft token when it is still valid', function (): void {
    config([
        'services.microsoft.client_id' => 'client-id',
        'services.microsoft.client_secret' => 'client-secret',
        'services.microsoft.tenant' => 'common',
    ]);

    $token = fakeMicrosoftJwt('User.Read Mail.Send');

    Http::fake([
        'https://graph.microsoft.com/v1.0/me?$select=id' => Http::response(['id' => 'graph-user'], 200),
    ]);

    $account = new SocialAccount([
        'expires_at' => now()->addHour(),
        'provider' => 'microsoft',
        'provider_email' => 'sender@example.com',
        'provider_user_id' => 'graph-user',
        'refresh_token_encrypted' => 'refresh-token',
        'token_encrypted' => $token,
    ]);

    $user = new User([
        'email' => 'sender@example.com',
        'first_name' => 'Manual',
        'last_name' => 'Tester',
    ]);
    $user->setRelation('microsoftAccount', $account);

    $resolved = app(MicrosoftAccessTokenResolver::class)->forAccount($account);

    expect($resolved['token'])->toBe($token)
        ->and($resolved['scopes'])->toContain('Mail.Send');
});

it('uses microsoft credentials from auth settings when env credentials are empty', function (): void {
    config([
        'services.microsoft.client_id' => null,
        'services.microsoft.client_secret' => null,
        'services.microsoft.tenant' => null,
    ]);

    app(SettingsRepository::class)->updateGroup('auth', [
        'microsoft_client_id' => ['value' => 'settings-client-id'],
        'microsoft_client_secret' => ['value' => 'settings-client-secret'],
        'microsoft_tenant' => ['value' => 'settings-tenant'],
    ]);

    $token = fakeMicrosoftJwt('User.Read Mail.Send');

    Http::fake([
        'https://graph.microsoft.com/v1.0/me?$select=id' => Http::response(['id' => 'graph-user'], 200),
    ]);

    $account = new SocialAccount([
        'expires_at' => now()->addHour(),
        'provider' => 'microsoft',
        'provider_email' => 'sender@example.com',
        'provider_user_id' => 'graph-user',
        'refresh_token_encrypted' => 'refresh-token',
        'token_encrypted' => $token,
    ]);

    $user = new User([
        'email' => 'sender@example.com',
        'first_name' => 'Manual',
        'last_name' => 'Tester',
    ]);
    $user->setRelation('microsoftAccount', $account);

    $resolved = app(MicrosoftAccessTokenResolver::class)->forAccount($account);

    expect($resolved['token'])->toBe($token)
        ->and($resolved['scopes'])->toContain('Mail.Send');
});

it('refreshes the microsoft token when the current one is expired', function (): void {
    config([
        'services.microsoft.client_id' => 'client-id',
        'services.microsoft.client_secret' => 'client-secret',
        'services.microsoft.tenant' => 'common',
    ]);

    $expiredToken = fakeMicrosoftJwt('User.Read');
    $refreshedToken = fakeMicrosoftJwt('User.Read Mail.Send');

    Http::fake([
        'https://graph.microsoft.com/v1.0/me?$select=id' => Http::sequence()
            ->push(['id' => 'graph-user'], 200),
        'https://login.microsoftonline.com/common/oauth2/v2.0/token' => Http::response([
            'access_token' => $refreshedToken,
            'expires_in' => 3600,
            'refresh_token' => 'new-refresh-token',
        ], 200),
    ]);

    $account = new SocialAccount([
        'expires_at' => now()->subMinute(),
        'provider' => 'microsoft',
        'provider_email' => 'sender@example.com',
        'provider_user_id' => 'graph-user',
        'refresh_token_encrypted' => 'refresh-token',
        'token_encrypted' => $expiredToken,
    ]);

    $user = new User([
        'email' => 'sender@example.com',
        'first_name' => 'Manual',
        'last_name' => 'Tester',
    ]);
    $user->setRelation('microsoftAccount', $account);

    $resolved = app(MicrosoftAccessTokenResolver::class)->forAccount($account);

    expect($resolved['token'])->toBe($refreshedToken)
        ->and($resolved['scopes'])->toContain('Mail.Send')
        ->and($account->getAttribute('token_encrypted'))->toBe($refreshedToken)
        ->and($account->getAttribute('refresh_token_encrypted'))->toBe('new-refresh-token');
});

it('deletes a broken microsoft account so the connection can be reconnected cleanly', function (): void {
    config([
        'services.microsoft.client_id' => 'client-id',
        'services.microsoft.client_secret' => 'client-secret',
        'services.microsoft.tenant' => 'common',
    ]);

    $user = createCorePanelMicrosoftUser([
        'email' => 'sender@example.com',
    ]);

    DB::table('social_accounts')->insert([
        'id' => (string) Str::uuid(),
        'user_id' => $user->getKey(),
        'provider' => 'microsoft',
        'provider_user_id' => 'graph-user',
        'provider_email' => 'sender@example.com',
        'token_encrypted' => 'broken-encrypted-token',
        'refresh_token_encrypted' => 'broken-encrypted-refresh-token',
        'expires_at' => now()->addHour(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $resolved = app(MicrosoftAccessTokenResolver::class)->forUser(
        $user,
    );

    expect($resolved['token'])->toBeNull()
        ->and($resolved['message'])->toBe(__('core-panel::page-settings.microsoft_reconnect_required'))
        ->and(
            SocialAccount::query()
                ->where('provider', 'microsoft')
                ->where('user_id', $user->getKey())
                ->exists(),
        )->toBeFalse();
});

function fakeMicrosoftJwt(string $scopes): string
{
    $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'none', 'typ' => 'JWT']) ?: '{}'), '+/', '-_'), '=');
    $payload = rtrim(strtr(base64_encode(json_encode(['scp' => $scopes]) ?: '{}'), '+/', '-_'), '=');

    return $header.'.'.$payload.'.signature';
}

it('persists refreshed tokens across requests and two token lifetimes', function (): void {
    config([
        'services.microsoft.client_id' => 'client-id',
        'services.microsoft.client_secret' => 'client-secret',
        'services.microsoft.tenant' => 'common',
    ]);
    $this->travelTo(now()->startOfSecond());
    Http::preventStrayRequests();
    $firstToken = fakeMicrosoftJwt('User.Read Mail.Send');
    $secondToken = fakeMicrosoftJwt('User.Read Mail.Send Calendars.ReadWrite');
    Http::fake([
        'https://graph.microsoft.com/*' => Http::response(['id' => 'graph-user']),
        'https://login.microsoftonline.com/*' => Http::sequence()
            ->push(['access_token' => $firstToken, 'expires_in' => 3600])
            ->push(['access_token' => $secondToken, 'expires_in' => 3600, 'refresh_token' => 'rotated-refresh'])
            ->push(['access_token' => 'third-access', 'expires_in' => 3600, 'refresh_token' => 'third-refresh']),
    ]);
    $account = storedMicrosoftRefreshAccount();
    $resolver = app(MicrosoftAccessTokenResolver::class);

    expect($resolver->forAccount($account)['token'])->toBe($firstToken);
    $account->refresh();
    $expiresAt = $account->getAttribute('expires_at');
    expect($account->getAttribute('refresh_token_encrypted'))->toBe('original-refresh')
        ->and($expiresAt instanceof Carbon && $expiresAt->equalTo(now()->addHour()))->toBeTrue();

    $this->travel(59)->minutes();
    expect($resolver->forAccount(SocialAccount::query()->sole())['token'])->toBe($firstToken);
    Http::assertSentCount(3);

    $this->travel(2)->minutes();
    expect($resolver->forAccount(SocialAccount::query()->sole())['token'])->toBe($secondToken);
    $account->refresh();
    expect($account->getAttribute('refresh_token_encrypted'))->toBe('rotated-refresh')
        ->and($account->getAttribute('token_encrypted'))->toBe($secondToken);
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'login.microsoftonline.com')
        && $request['grant_type'] === 'refresh_token'
        && $request['refresh_token'] === 'original-refresh');
    Http::assertSentCount(5);

    $this->travel(61)->minutes();
    expect($resolver->forAccount(SocialAccount::query()->sole())['token'])->toBe('third-access');
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'login.microsoftonline.com')
        && $request['refresh_token'] === 'rotated-refresh');
    expect($account->refresh()->getAttribute('refresh_token_encrypted'))->toBe('third-refresh');
    Http::assertSentCount(7);
});

it('uses the configured TLS options for refresh and graph verification', function (): void {
    config([
        'services.microsoft.client_id' => 'client-id',
        'services.microsoft.client_secret' => 'client-secret',
        'services.microsoft.tenant' => 'common',
        'services.microsoft.guzzle' => ['curl' => [CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2 | CURL_SSLVERSION_MAX_TLSv1_2]],
    ]);
    Http::preventStrayRequests();
    $verified = 0;
    Http::fake(function (Request $request, array $options) use (&$verified) {
        expect($options['curl'][CURLOPT_SSLVERSION])->toBe(CURL_SSLVERSION_TLSv1_2 | CURL_SSLVERSION_MAX_TLSv1_2)
            ->and($options['verify'])->toBeTrue();
        $verified++;

        return Http::response(str_contains($request->url(), 'login.microsoftonline.com')
            ? ['access_token' => 'new-access', 'expires_in' => 3600]
            : ['id' => 'graph-user']);
    });

    expect(app(MicrosoftAccessTokenResolver::class)->forAccount(storedMicrosoftRefreshAccount())['token'])->toBe('new-access')
        ->and($verified)->toBe(2);
});

it('keeps rotated tokens when graph is temporarily unavailable and uses them on the next request', function (): void {
    config([
        'services.microsoft.client_id' => 'client-id',
        'services.microsoft.client_secret' => 'client-secret',
        'services.microsoft.tenant' => 'common',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://login.microsoftonline.com/*' => Http::sequence()->push([
            'access_token' => 'new-access', 'refresh_token' => 'new-refresh', 'expires_in' => 3600,
        ]),
        'https://graph.microsoft.com/*' => Http::sequence()->push([], 503)->push(['id' => 'graph-user']),
    ]);
    $account = storedMicrosoftRefreshAccount();
    $resolver = app(MicrosoftAccessTokenResolver::class);
    $resolved = $resolver->forAccount($account);

    expect($resolved['token'])->toBeNull()
        ->and($resolved['message'])->toBe(__('core-panel::microsoft.connection_unavailable'));
    $account->refresh();
    expect($account->getAttribute('token_encrypted'))->toBe('new-access')
        ->and($account->getAttribute('refresh_token_encrypted'))->toBe('new-refresh')
        ->and($resolver->forAccount($account)['token'])->toBe('new-access');
    Http::assertSentCount(3);
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'graph.microsoft.com')
        && $request->hasHeader('Authorization', 'Bearer new-access'));
});

it('does not refresh a valid token during a graph outage', function (): void {
    config(['services.microsoft.client_id' => 'client-id', 'services.microsoft.client_secret' => 'client-secret']);
    Http::preventStrayRequests();
    Http::fake(['https://graph.microsoft.com/*' => Http::response([], 503)]);
    $account = storedMicrosoftRefreshAccount();
    $account->forceFill(['expires_at' => now()->addHour()])->save();

    expect(app(MicrosoftAccessTokenResolver::class)->forAccount($account)['message'])->toBe(__('core-panel::microsoft.connection_unavailable'));
    Http::assertSentCount(1);
});

it('logs only safe refresh diagnostics and keeps existing tokens on errors', function (string $error, int $status, bool $reconnect): void {
    config(['services.microsoft.client_id' => 'client-id', 'services.microsoft.client_secret' => 'client-secret']);
    Log::spy();
    Http::preventStrayRequests();
    Http::fake(['https://login.microsoftonline.com/*' => Http::response([
        'error' => $error,
        'error_description' => 'AADSTS70000 secret-do-not-log access-token-do-not-log',
        'access_token' => 'access-token-do-not-log',
        'refresh_token' => 'refresh-token-do-not-log',
    ], $status)]);
    $account = storedMicrosoftRefreshAccount();
    $resolved = app(MicrosoftAccessTokenResolver::class)->forAccount($account);

    expect($resolved['token'])->toBeNull()
        ->and($resolved['message'])->toBe(__($reconnect ? 'core-panel::page-settings.microsoft_reconnect_required' : 'core-panel::microsoft.connection_unavailable'));
    $account->refresh();
    expect($account->getAttribute('token_encrypted'))->toBe('original-access')
        ->and($account->getAttribute('refresh_token_encrypted'))->toBe('original-refresh');
    Log::shouldHaveReceived('warning')->once()->with('Microsoft token resolution failed.', [
        'phase' => 'refresh', 'http_status' => $status, 'exception' => null,
        'oauth_error' => $error, 'aadsts_code' => 'AADSTS70000',
    ]);
})->with([
    'revoked grant' => ['invalid_grant', 400, true],
    'interaction required' => ['interaction_required', 400, true],
    'scope configuration' => ['invalid_scope', 400, false],
    'service outage' => ['temporarily_unavailable', 503, false],
]);

it('does not log transport exception messages containing credentials', function (): void {
    config(['services.microsoft.client_id' => 'client-id', 'services.microsoft.client_secret' => 'client-secret']);
    Log::spy();
    Http::fake(fn () => throw new ConnectionException('client_secret=do-not-log'));

    expect(app(MicrosoftAccessTokenResolver::class)->forAccount(storedMicrosoftRefreshAccount())['message'])->toBe(__('core-panel::microsoft.connection_unavailable'));
    Log::shouldHaveReceived('warning')->once()->with('Microsoft token resolution failed.', [
        'phase' => 'refresh', 'http_status' => null, 'exception' => ConnectionException::class,
    ]);
});

function storedMicrosoftRefreshAccount(): SocialAccount
{
    return SocialAccount::query()->create([
        'user_id' => createCorePanelMicrosoftUser()->getKey(),
        'provider' => 'microsoft',
        'provider_user_id' => (string) Str::uuid(),
        'provider_email' => 'sender@example.test',
        'expires_at' => now()->subMinute(),
        'token_encrypted' => 'original-access',
        'refresh_token_encrypted' => 'original-refresh',
    ]);
}

function createCorePanelMicrosoftUser(array $attributes = []): User
{
    return User::query()->create(array_merge([
        'email' => Str::uuid().'@example.test',
        'first_name' => 'Microsoft',
        'last_name' => 'Tester',
        'password' => 'unused-test-password',
    ], $attributes));
}

it('resolves tokens for the configured authenticatable without a microsoft relation', function (): void {
    config(['services.microsoft.client_id' => 'client-id', 'services.microsoft.client_secret' => 'client-secret']);
    Http::preventStrayRequests();
    Http::fake([
        'https://login.microsoftonline.com/*' => Http::response(['access_token' => 'new-access', 'expires_in' => 3600]),
        'https://graph.microsoft.com/*' => Http::response(['id' => 'graph-user']),
    ]);
    $account = storedMicrosoftRefreshAccount();
    $user = User::query()->findOrFail($account->getAttribute('user_id'));

    expect(app(MicrosoftAccessTokenResolver::class)->forUser($user)['token'])->toBe('new-access');
});

it('retains host scopes in refresh requests and returns granted scopes', function (): void {
    $scopes = ['openid', 'offline_access', 'User.Read', 'Mail.Send', 'Calendars.ReadWrite', 'Files.ReadWrite.All', 'Sites.ReadWrite.All'];
    config([
        'services.microsoft.client_id' => 'client-id', 'services.microsoft.client_secret' => 'client-secret',
        'core-panel.auth.socialite.providers.microsoft.scopes' => $scopes,
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://login.microsoftonline.com/*' => Http::response([
            'access_token' => fakeMicrosoftJwt('User.Read Mail.Send Calendars.ReadWrite Files.ReadWrite.All Sites.ReadWrite.All'),
            'expires_in' => 3600,
        ]),
        'https://graph.microsoft.com/*' => Http::response(['id' => 'graph-user']),
    ]);

    $resolved = app(MicrosoftAccessTokenResolver::class)->forAccount(storedMicrosoftRefreshAccount());

    expect($resolved['scopes'])->toContain('Mail.Send', 'Calendars.ReadWrite', 'Files.ReadWrite.All', 'Sites.ReadWrite.All');
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'login.microsoftonline.com')
        && $request['scope'] === implode(' ', $scopes));
});
