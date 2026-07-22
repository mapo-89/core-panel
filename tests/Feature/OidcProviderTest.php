<?php

declare(strict_types=1);

use CorePanel\Support\Socialite\OidcProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

it('loads authentik-compatible endpoints through OIDC discovery', function (): void {
    Http::fake([
        'https://authentik.example.test/application/o/core-panel/.well-known/openid-configuration' => Http::response([
            'authorization_endpoint' => 'https://authentik.example.test/application/o/authorize/',
            'issuer' => 'https://authentik.example.test/application/o/core-panel',
            'token_endpoint' => 'https://authentik.example.test/application/o/token/',
            'userinfo_endpoint' => 'https://authentik.example.test/application/o/userinfo/',
        ]),
    ]);

    $provider = (new OidcProvider(
        Request::create('/login'),
        'core-panel-client',
        'client-secret',
        'https://core-panel.example.test/auth/oidc/callback',
    ))->configure('https://authentik.example.test/application/o/core-panel/');

    $discovery = (new ReflectionMethod($provider, 'discovery'))->invoke($provider);

    expect($discovery['authorization_endpoint'])->toBe('https://authentik.example.test/application/o/authorize/')
        ->and($discovery['token_endpoint'])->toBe('https://authentik.example.test/application/o/token/')
        ->and($discovery['userinfo_endpoint'])->toBe('https://authentik.example.test/application/o/userinfo/');

    Http::assertSent(static fn ($request): bool => $request->url() === 'https://authentik.example.test/application/o/core-panel/.well-known/openid-configuration');
});

it('rejects OIDC issuer URLs that do not use HTTPS', function (): void {
    $provider = (new OidcProvider(
        Request::create('/login'),
        'core-panel-client',
        'client-secret',
        'https://core-panel.example.test/auth/oidc/callback',
    ))->configure('http://idp.example.test');

    expect(fn (): mixed => (new ReflectionMethod($provider, 'discovery'))->invoke($provider))
        ->toThrow(RuntimeException::class, 'A valid HTTPS OIDC issuer URL is required.');
});

it('rejects OIDC discovery documents with insecure endpoints', function (string $endpoint): void {
    $issuer = 'https://idp.example.test/'.$endpoint;
    $document = [
        'authorization_endpoint' => 'https://idp.example.test/authorize',
        'issuer' => $issuer,
        'token_endpoint' => 'https://idp.example.test/token',
        'userinfo_endpoint' => 'https://idp.example.test/userinfo',
    ];
    $document[$endpoint] = 'http://idp.example.test/'.$endpoint;

    Http::fake([
        $issuer.'/.well-known/openid-configuration' => Http::response($document),
    ]);

    $provider = (new OidcProvider(
        Request::create('/login'),
        'core-panel-client',
        'client-secret',
        'https://core-panel.example.test/auth/oidc/callback',
    ))->configure($issuer);

    expect(fn (): mixed => (new ReflectionMethod($provider, 'discovery'))->invoke($provider))
        ->toThrow(RuntimeException::class, 'The OIDC discovery document is invalid, insecure, or belongs to another issuer.');
})->with(['authorization_endpoint', 'token_endpoint', 'userinfo_endpoint']);

it('rejects OIDC userinfo responses without a non-empty string subject', function (array $claims, array $userinfo): void {
    $provider = (new OidcProvider(
        Request::create('/login'),
        'core-panel-client',
        'client-secret',
        'https://core-panel.example.test/auth/oidc/callback',
    ))->configure('https://authentik.example.test/application/o/core-panel', $claims);

    expect(fn (): mixed => (new ReflectionMethod($provider, 'mapUserToObject'))->invoke($provider, $userinfo))
        ->toThrow(RuntimeException::class, 'The OIDC userinfo response does not contain a valid subject claim.');
})->with([
    'blank configured subject claim' => [['id' => ''], ['sub' => 'authentik-user-1']],
    'missing configured subject claim' => [['id' => 'external_id'], ['sub' => 'authentik-user-1']],
    'empty subject claim' => [[], ['sub' => '']],
    'whitespace subject claim' => [[], ['sub' => '   ']],
    'non-string subject claim' => [[], ['sub' => 42]],
]);
