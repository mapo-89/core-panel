<?php

declare(strict_types=1);

use CorePanel\Models\SocialAccount;
use CorePanel\Support\Settings\SettingsRepository;
use CorePanel\Support\Socialite\MicrosoftAccessTokenResolver;
use CorePanel\Support\Socialite\SocialAccountStore;
use CorePanel\Tests\FakeUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function (): void {
    if (! getenv('CORE_PANEL_TEST_PGSQL_DATABASE')) {
        $this->markTestSkipped('Set CORE_PANEL_TEST_PGSQL_DATABASE to run PostgreSQL timezone tests.');
    }

    config([
        'database.default' => 'token-test',
        'database.connections.token-test' => [
            'driver' => 'pgsql',
            'host' => getenv('CORE_PANEL_TEST_PGSQL_HOST') ?: '127.0.0.1',
            'port' => getenv('CORE_PANEL_TEST_PGSQL_PORT') ?: '5432',
            'database' => getenv('CORE_PANEL_TEST_PGSQL_DATABASE'),
            'username' => getenv('CORE_PANEL_TEST_PGSQL_USERNAME'),
            'password' => getenv('CORE_PANEL_TEST_PGSQL_PASSWORD'),
            'timezone' => 'UTC',
        ],
        'services.microsoft.client_id' => 'test-client',
        'services.microsoft.client_secret' => 'test-secret',
    ]);
    DB::beginTransaction();
    DB::statement('CREATE TEMPORARY TABLE social_accounts (
        id uuid PRIMARY KEY, user_id uuid, provider varchar(255), provider_user_id varchar(255),
        provider_email varchar(255), avatar_url text, token_encrypted text, refresh_token_encrypted text,
        expires_at timestamptz, created_at timestamptz, updated_at timestamptz,
        UNIQUE(provider, provider_user_id)
    ) ON COMMIT DROP');
    $settings = Mockery::mock(SettingsRepository::class);
    $settings->shouldReceive('get')->andReturn(null);
    app()->instance(SettingsRepository::class, $settings);
    Http::preventStrayRequests();
});

afterEach(function (): void {
    if (config('database.default') === 'token-test') {
        DB::rollBack();
        DB::disconnect('token-test');
    }
});

it('preserves login and refresh instants through PostgreSQL across timezone changes', function (string $instant, string $databaseTimezone): void {
    $start = Carbon::parse($instant, 'UTC');
    $originalTimezone = date_default_timezone_get();
    $this->travelTo($start);
    config(['app.timezone' => 'Europe/Berlin']);
    date_default_timezone_set('Europe/Berlin');
    DB::statement("SET LOCAL TIME ZONE '{$databaseTimezone}'");
    $user = new FakeUser(['id' => (string) Str::uuid()]);
    Http::fake([
        'https://login.microsoftonline.com/*' => Http::sequence()
            ->push(['access_token' => 'refreshed-access', 'refresh_token' => 'rotated-refresh', 'expires_in' => 3600])
            ->push(['access_token' => 'next-access', 'expires_in' => 3600]),
        'https://graph.microsoft.com/*' => Http::response(['id' => 'graph-user']),
    ]);

    try {
        $store = app(SocialAccountStore::class);
        $account = $store->upsertForUserWithAttributes($user, 'microsoft', [
            'provider_user_id' => 'graph-user', 'token' => 'login-access',
            'refresh_token' => 'login-refresh', 'expires_in' => 3600,
        ]);
        assertCorePanelTokenTimes($account->refresh(), $start, 0);

        $this->travelTo($start->copy()->addSeconds(3601));
        $resolver = app(MicrosoftAccessTokenResolver::class);
        expect($resolver->forUser($user)['token'])->toBe('refreshed-access');
        assertCorePanelTokenTimes($account->refresh(), $start, 3601);

        $this->travelTo($start->copy()->addSeconds(7202));
        expect($resolver->forUser($user)['token'])->toBe('next-access');
        assertCorePanelTokenTimes($account->refresh(), $start, 7202);
        expect($account->getAttribute('refresh_token_encrypted'))->toBe('rotated-refresh');
        Http::assertSentCount(4);

        $this->travelTo($start->copy()->addSeconds(7300));
        $store->upsertForUserWithAttributes($user, 'microsoft', [
            'provider_user_id' => 'graph-user', 'token' => 'relogin-access', 'expires_in' => 3600,
        ]);
        assertCorePanelTokenTimes($account->refresh(), $start, 7300);
        expect($account->getAttribute('refresh_token_encrypted'))->toBe('rotated-refresh');
    } finally {
        date_default_timezone_set($originalTimezone);
    }
})->with([
    'summer' => '2026-07-15 12:00:00',
    'winter' => '2026-01-15 12:00:00',
    'spring clock change' => '2026-03-29 00:30:00',
    'autumn clock change' => '2026-10-25 00:30:00',
])->with(['UTC', 'America/New_York']);

function assertCorePanelTokenTimes(SocialAccount $account, Carbon $start, int $elapsed): void
{
    $expiry = $account->getAttribute('expires_at');
    $created = $account->getAttribute('created_at');
    $updated = $account->getAttribute('updated_at');
    expect($expiry instanceof Carbon ? $expiry->getTimestamp() : null)->toBe($start->getTimestamp() + $elapsed + 3600)
        ->and($created instanceof Carbon ? $created->getTimestamp() : null)->toBe($start->getTimestamp())
        ->and($updated instanceof Carbon ? $updated->getTimestamp() : null)->toBe($start->getTimestamp() + $elapsed);
}
