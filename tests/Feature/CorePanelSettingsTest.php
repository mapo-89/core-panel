<?php

declare(strict_types=1);

use CorePanel\Models\Setting;
use CorePanel\Support\Settings\SettingsRepository;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class SettingsTestUser extends AuthenticatableUser
{
    public ?string $locale = null;
}

final class InMemorySettingsRepository extends SettingsRepository
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
            ->filter(function (Setting $setting) use ($group, $publicOnly): bool {
                if ($setting->getAttribute('group') !== $group) {
                    return false;
                }

                return ! $publicOnly || (bool) $setting->getAttribute('is_public');
            })
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

    public function exposedCacheKey(string $prefix, ?string $group, ?string $locale): string
    {
        return $this->cacheKey($prefix, $group, $locale);
    }
}

function settingsRepository(): InMemorySettingsRepository
{
    return new InMemorySettingsRepository(new CacheRepository(new ArrayStore), new Setting);
}

beforeEach(function (): void {
    if (! corePanelTestbenchDatabaseAvailable()) {
        $this->markTestSkipped('pdo_sqlite is not available in this environment.');
    }
});

afterEach(function (): void {
    app('auth')->forgetGuards();
    InMemorySettingsRepository::resetRecords();
});

it('stores and reads a setting', function (): void {
    $repository = settingsRepository();

    $repository->set('general', 'app_name', 'CorePanel X', 'text', true);

    expect($repository->get('general', 'app_name'))->toBe('CorePanel X')
        ->and($repository->public()['general']['app_name'])->toBe('CorePanel X');
});

it('invalidates cache entries when a setting changes', function (): void {
    $repository = settingsRepository();

    $repository->set('general', 'app_name', 'CorePanel', 'text', true);
    $repository->set('general', 'app_name', 'CorePanel Updated', 'text', true);

    expect($repository->get('general', 'app_name'))->toBe('CorePanel Updated');
});

it('resolves localized values through user, default, and fallback locale resolution', function (): void {
    config()->set('core-panel.i18n.default_locale', 'de');
    config()->set('core-panel.i18n.fallback_locale', 'en');

    $repository = settingsRepository();

    $repository->set('general', 'welcome', [
        'de' => 'Hallo',
        'en' => 'Hello',
    ], 'json', true, true);

    expect($repository->get('general', 'welcome'))->toBe('Hallo');

    $user = new SettingsTestUser;
    $user->locale = 'en';
    auth()->setUser($user);

    expect($repository->get('general', 'welcome', locale: 'en'))->toBe('Hello');
});

it('stores grouped settings with typed values', function (): void {
    $repository = settingsRepository();

    $repository->updateGroup('ui', [
        'layout_density' => ['type' => 'select', 'is_public' => true, 'value' => 'compact'],
        'primary_color_token' => ['type' => 'text', 'is_public' => true, 'value' => '#2463eb'],
        'radius_token' => ['type' => 'select', 'is_public' => true, 'value' => 'none'],
        'show_app_footer' => ['type' => 'boolean', 'is_public' => true, 'value' => false],
    ]);

    expect($repository->getGroup('ui'))->toMatchArray([
        'layout_density' => 'compact',
        'primary_color_token' => '#2463eb',
        'radius_token' => 'none',
        'show_app_footer' => false,
    ]);
});

it('namespaces settings cache keys per application installation context', function (): void {
    $repository = settingsRepository();

    config()->set('app.key', 'base64:first-app-key');
    config()->set('app.url', 'https://core-panel-app.test');
    config()->set('database.default', 'pgsql');
    config()->set('database.connections.pgsql.database', 'core_panel_playground');

    $firstKey = $repository->exposedCacheKey('public', null, 'de');

    config()->set('app.key', 'base64:second-app-key');
    config()->set('app.url', 'https://re-sulting-onehub.test');
    config()->set('database.connections.pgsql.database', 'onehub');

    $secondKey = $repository->exposedCacheKey('public', null, 'de');

    expect($firstKey)->not->toBe($secondKey)
        ->and($firstKey)->toStartWith('core-panel:settings:')
        ->and($secondKey)->toStartWith('core-panel:settings:');
});
