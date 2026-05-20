<?php

declare(strict_types=1);

use CorePanel\Http\Middleware\SecurityHeaders;
use CorePanel\Models\Setting;
use CorePanel\Support\Config\CorePanelConfig;
use CorePanel\Support\Security\SecurityHeaderConfig;
use CorePanel\Support\Settings\SettingsRepository;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class InMemorySecuritySettingsRepository extends SettingsRepository
{
    /**
     * @var list<Setting>
     */
    private static array $records = [];

    protected function persist(Setting $record): Setting
    {
        if ($record->getKey() === null) {
            $record->setAttribute($record->getKeyName(), (string) Str::uuid());
        }

        $record->exists = true;

        self::$records = array_values(array_filter(
            self::$records,
            fn (Setting $setting): bool => ! (
                $setting->getAttribute('group') === $record->getAttribute('group')
                && $setting->getAttribute('key') === $record->getAttribute('key')
            ),
        ));

        self::$records[] = clone $record;

        return $record;
    }

    /**
     * @return Collection<int, Setting>
     */
    protected function globalRecords(string $group, bool $publicOnly = false): Collection
    {
        return collect(self::$records)
            ->filter(fn (Setting $setting): bool => $setting->getAttribute('group') === $group
                && (! $publicOnly || (bool) $setting->getAttribute('is_public')))
            ->values();
    }

    protected function findWritableRecord(string $group, string $key): ?Setting
    {
        /** @var Setting|null $setting */
        $setting = collect(self::$records)
            ->first(fn (Setting $setting): bool => $setting->getAttribute('group') === $group
                && $setting->getAttribute('key') === $key);

        return $setting;
    }

    public static function resetRecords(): void
    {
        self::$records = [];
    }
}

function securityHeadersConfig(array $overrides = []): CorePanelConfig
{
    config()->set('core-panel.security.headers.enabled', $overrides['enabled'] ?? true);
    config()->set('core-panel.security.headers.csp', $overrides['csp'] ?? "default-src 'self'");
    config()->set('core-panel.security.headers.csp_report_only', $overrides['csp_report_only'] ?? false);
    config()->set('core-panel.security.headers.hsts', $overrides['hsts'] ?? 'max-age=31536000; includeSubDomains');
    config()->set('core-panel.security.headers.permissions_policy', $overrides['permissions_policy'] ?? 'camera=(), microphone=()');
    config()->set('core-panel.security.headers.referrer_policy', $overrides['referrer_policy'] ?? 'strict-origin-when-cross-origin');

    return CorePanelConfig::fromRepository(config());
}

function securityHeadersMiddleware(InMemorySecuritySettingsRepository $settings, array $overrides = []): SecurityHeaders
{
    return new SecurityHeaders(new SecurityHeaderConfig(securityHeadersConfig($overrides), $settings));
}

function secureRequest(string $uri = '/admin'): Request
{
    $request = Request::create($uri, 'GET');
    $request->server->set('HTTPS', 'on');

    return $request;
}

afterEach(function (): void {
    InMemorySecuritySettingsRepository::resetRecords();
});

it('sets security headers with safe defaults', function (): void {
    app()->detectEnvironment(fn () => 'production');

    $settings = new InMemorySecuritySettingsRepository(new CacheRepository(new ArrayStore), new Setting);
    $response = securityHeadersMiddleware($settings)->handle(secureRequest(), static fn () => new Response('ok'));

    expect($response->headers->get('X-Frame-Options'))->toBe('SAMEORIGIN')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin')
        ->and($response->headers->get('Content-Security-Policy'))->toBe("default-src 'self'")
        ->and($response->headers->get('Permissions-Policy'))->toBe('camera=(), microphone=()')
        ->and($response->headers->get('Strict-Transport-Security'))->toBe('max-age=31536000; includeSubDomains');
});

it('does not set headers when disabled', function (): void {
    $settings = new InMemorySecuritySettingsRepository(new CacheRepository(new ArrayStore), new Setting);
    $settings->set('security', 'headers_enabled', false, 'boolean');

    $response = securityHeadersMiddleware($settings)->handle(Request::create('/admin', 'GET'), static fn () => new Response('ok'));

    expect($response->headers->all())->not->toHaveKey('x-frame-options');
});
