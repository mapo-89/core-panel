<?php

declare(strict_types=1);

use CorePanel\Http\Middleware\ApplyCorePanelRuntimeSettings;
use CorePanel\Models\Setting;
use CorePanel\Support\Settings\SettingsRepository;
use CorePanel\Tests\FakeUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    if (! corePanelTestbenchDatabaseAvailable()) {
        $this->markTestSkipped('pdo_sqlite is not available in this environment.');
    }

    $this->migrateScaffoldDatabase();

    Gate::before(static fn (...$arguments): bool => true);
});

it('stores the imported general settings block fields', function (): void {
    $user = FakeUser::query()->create([
        'email' => 'settings-general@example.test',
        'first_name' => 'Settings',
        'last_name' => 'Manager',
        'password' => Hash::make('secret-password'),
    ]);

    $response = $this
        ->actingAs($user)
        ->from(route('core-panel.settings.index', ['tab' => 'general']))
        ->put(route('core-panel.settings.update', ['group' => 'general']), [
            'values' => [
                'app_name' => [
                    'value' => 'Reference General Settings',
                ],
                'app_subtitle' => [
                    'value' => 'Ship admin projects faster',
                ],
                'timezone' => [
                    'value' => 'Europe/Berlin',
                ],
            ],
        ]);

    $response
        ->assertRedirect(route('core-panel.settings.index', ['tab' => 'general']))
        ->assertSessionHas('status', trans('core-panel::settings.messages.saved'));

    $appNameSetting = Setting::query()
        ->where('group', 'general')
        ->where('key', 'app_name')
        ->first();
    $appSubtitleSetting = Setting::query()
        ->where('group', 'general')
        ->where('key', 'app_subtitle')
        ->first();
    $timezoneSetting = Setting::query()
        ->where('group', 'general')
        ->where('key', 'timezone')
        ->first();

    expect($appNameSetting)->not->toBeNull()
        ->and($appSubtitleSetting)->not->toBeNull()
        ->and($timezoneSetting)->not->toBeNull()
        ->and($appNameSetting?->getRawOriginal('value_json'))->toBe('"Reference General Settings"')
        ->and($appSubtitleSetting?->getRawOriginal('value_json'))->toBe('"Ship admin projects faster"')
        ->and($timezoneSetting?->getRawOriginal('value_json'))->toBe('"Europe\/Berlin"');
});

it('allows clearing the application subtitle in the general settings group', function (): void {
    $user = FakeUser::query()->create([
        'email' => 'settings-general-empty-subtitle@example.test',
        'first_name' => 'Settings',
        'last_name' => 'Cleaner',
        'password' => Hash::make('secret-password'),
    ]);

    app(SettingsRepository::class)->set(
        'general',
        'app_subtitle',
        'Temporary subtitle',
        'text',
        true,
    );

    $response = $this
        ->actingAs($user)
        ->from(route('core-panel.settings.index', ['tab' => 'general']))
        ->put(route('core-panel.settings.update', ['group' => 'general']), [
            'values' => [
                'app_name' => [
                    'value' => 'Reference General Settings',
                ],
                'app_subtitle' => [
                    'value' => '',
                ],
                'timezone' => [
                    'value' => 'Europe/Berlin',
                ],
            ],
        ]);

    $response
        ->assertRedirect(route('core-panel.settings.index', ['tab' => 'general']))
        ->assertSessionHas('status', trans('core-panel::settings.messages.saved'));

    $appSubtitleSetting = Setting::query()
        ->where('group', 'general')
        ->where('key', 'app_subtitle')
        ->first();

    expect($appSubtitleSetting)->not->toBeNull()
        ->and($appSubtitleSetting?->getRawOriginal('value_json'))->toBe('""')
        ->and(app(SettingsRepository::class)->get('general', 'app_subtitle'))->toBe('');
});

it('exposes the api token manager inside the settings workspace', function (): void {
    $user = FakeUser::query()->create([
        'email' => 'settings-api@example.test',
        'first_name' => 'Api',
        'last_name' => 'Manager',
        'password' => Hash::make('secret-password'),
    ]);

    $this->actingAs($user)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->get(route('core-panel.settings.index', ['tab' => 'api']))
        ->assertOk()
        ->assertJsonPath('component', 'Settings/Index')
        ->assertJsonPath('props.currentGroup', 'api')
        ->assertJsonPath('props.apiTokenManager.canCreate', true)
        ->assertJsonPath('props.apiTokenManager.canDelete', true)
        ->assertJsonCount(4, 'props.apiTokenManager.abilities')
        ->assertJsonFragment([
            'label' => __('page-api-tokens.abilities.create'),
            'value' => 'create',
        ])
        ->assertJsonFragment([
            'label' => __('page-api-tokens.abilities.read'),
            'value' => 'read',
        ])
        ->assertJsonFragment([
            'label' => __('page-api-tokens.abilities.update'),
            'value' => 'update',
        ])
        ->assertJsonFragment([
            'label' => __('page-api-tokens.abilities.delete'),
            'value' => 'delete',
        ])
        ->assertJsonFragment([
            'key' => 'api',
            'label' => __('page-settings.tab_api'),
        ]);
});

it('stores the available languages in the language settings group', function (): void {
    $user = FakeUser::query()->create([
        'email' => 'settings-locale@example.test',
        'first_name' => 'Locale',
        'last_name' => 'Manager',
        'password' => Hash::make('secret-password'),
    ]);

    $response = $this
        ->actingAs($user)
        ->from(route('core-panel.settings.index', ['tab' => 'general']))
        ->put(route('core-panel.settings.update', ['group' => 'i18n']), [
            'values' => [
                'default_locale' => [
                    'value' => 'de',
                ],
                'fallback_locale' => [
                    'value' => 'en',
                ],
                'languages' => [
                    'value' => ['de', 'en'],
                ],
            ],
        ]);

    $response
        ->assertRedirect(route('core-panel.settings.index', ['tab' => 'general']))
        ->assertSessionHas('status', trans('core-panel::settings.messages.saved'));

    $languagesSetting = Setting::query()
        ->where('group', 'i18n')
        ->where('key', 'languages')
        ->first();

    expect($languagesSetting)->not->toBeNull()
        ->and($languagesSetting?->getAttribute('value_json'))->toBe(['de', 'en']);
});

it('requires at least one active language before saving locale settings', function (): void {
    $user = FakeUser::query()->create([
        'email' => 'settings-locale-empty@example.test',
        'first_name' => 'Locale',
        'last_name' => 'Guard',
        'password' => Hash::make('secret-password'),
    ]);

    $response = $this
        ->actingAs($user)
        ->from(route('core-panel.settings.index', ['tab' => 'general']))
        ->put(route('core-panel.settings.update', ['group' => 'i18n']), [
            'values' => [
                'default_locale' => [
                    'value' => null,
                ],
                'fallback_locale' => [
                    'value' => null,
                ],
                'languages' => [
                    'value' => [],
                ],
            ],
        ]);

    $response
        ->assertRedirect(route('core-panel.settings.index', ['tab' => 'general']))
        ->assertSessionHasErrors([
            'values.languages.value' => trans('core-panel::settings.validation.languages_required'),
        ]);
});

it('requires default and fallback locales to remain inside the active language selection', function (): void {
    $user = FakeUser::query()->create([
        'email' => 'settings-locale-invalid@example.test',
        'first_name' => 'Locale',
        'last_name' => 'Mismatch',
        'password' => Hash::make('secret-password'),
    ]);

    $response = $this
        ->actingAs($user)
        ->from(route('core-panel.settings.index', ['tab' => 'general']))
        ->put(route('core-panel.settings.update', ['group' => 'i18n']), [
            'values' => [
                'default_locale' => [
                    'value' => 'en',
                ],
                'fallback_locale' => [
                    'value' => 'en',
                ],
                'languages' => [
                    'value' => ['de'],
                ],
            ],
        ]);

    $response
        ->assertRedirect(route('core-panel.settings.index', ['tab' => 'general']))
        ->assertSessionHasErrors([
            'values.default_locale.value' => trans('core-panel::settings.validation.default_locale_enabled'),
            'values.fallback_locale.value' => trans('core-panel::settings.validation.fallback_locale_enabled'),
        ]);
});

it('stores the appearance palette and normalizes redirects for the combined appearance tab', function (): void {
    $user = FakeUser::query()->create([
        'email' => 'settings-appearance@example.test',
        'first_name' => 'Appearance',
        'last_name' => 'Manager',
        'password' => Hash::make('secret-password'),
    ]);

    $response = $this
        ->actingAs($user)
        ->from(route('core-panel.settings.index', ['tab' => 'appearance']))
        ->put(route('core-panel.settings.update', ['group' => 'appearance']), [
            'values' => [
                'theme_palette' => [
                    'value' => 'ocean',
                ],
            ],
        ]);

    $response
        ->assertRedirect(route('core-panel.settings.index', ['tab' => 'appearance']))
        ->assertSessionHas('status', trans('core-panel::settings.messages.saved'));

    $paletteSetting = Setting::query()
        ->where('group', 'appearance')
        ->where('key', 'theme_palette')
        ->first();

    expect($paletteSetting)->not->toBeNull()
        ->and($paletteSetting?->getAttribute('value_json'))->toBe('ocean');
});

it('redirects ui settings updates back to the combined appearance tab', function (): void {
    $user = FakeUser::query()->create([
        'email' => 'settings-ui@example.test',
        'first_name' => 'Ui',
        'last_name' => 'Manager',
        'password' => Hash::make('secret-password'),
    ]);

    $response = $this
        ->actingAs($user)
        ->from(route('core-panel.settings.index', ['tab' => 'appearance']))
        ->put(route('core-panel.settings.update', ['group' => 'ui']), [
            'values' => [
                'layout_density' => [
                    'value' => 'compact',
                ],
                'show_app_footer' => [
                    'value' => true,
                ],
                'primary_color_token' => [
                    'value' => '#1ab88f',
                ],
                'radius_token' => [
                    'value' => 'none',
                ],
            ],
        ]);

    $response
        ->assertRedirect(route('core-panel.settings.index', ['tab' => 'appearance']))
        ->assertSessionHas('status', trans('core-panel::settings.messages.saved'));

    $primaryColorSetting = Setting::query()
        ->where('group', 'ui')
        ->where('key', 'primary_color_token')
        ->first();

    expect($primaryColorSetting)->not->toBeNull()
        ->and($primaryColorSetting?->getAttribute('value_json'))->toBe('#1ab88f');
});

it('stores appearance and ui style settings through the combined appearance endpoint', function (): void {
    $user = FakeUser::query()->create([
        'email' => 'settings-style-card@example.test',
        'first_name' => 'Style',
        'last_name' => 'Manager',
        'password' => Hash::make('secret-password'),
    ]);

    $response = $this
        ->actingAs($user)
        ->from(route('core-panel.settings.index', ['tab' => 'appearance']))
        ->put(route('core-panel.settings.styles'), [
            'values' => [
                'layout_density' => [
                    'value' => 'compact',
                ],
                'show_app_footer' => [
                    'value' => true,
                ],
                'primary_color_token' => [
                    'value' => '#2463eb',
                ],
                'radius_token' => [
                    'value' => 'none',
                ],
                'theme_palette' => [
                    'value' => 'ocean',
                ],
            ],
        ]);

    $response
        ->assertRedirect(route('core-panel.settings.index', ['tab' => 'appearance']))
        ->assertSessionHas('status', trans('core-panel::settings.messages.saved'));

    $paletteSetting = Setting::query()
        ->where('group', 'appearance')
        ->where('key', 'theme_palette')
        ->first();
    $primaryColorSetting = Setting::query()
        ->where('group', 'ui')
        ->where('key', 'primary_color_token')
        ->first();

    expect($paletteSetting)->not->toBeNull()
        ->and($primaryColorSetting)->not->toBeNull()
        ->and($paletteSetting?->getAttribute('value_json'))->toBe('ocean')
        ->and($primaryColorSetting?->getAttribute('value_json'))->toBe('#2463eb');
});

it('applies saved runtime settings to the request config', function (): void {
    $settings = app(SettingsRepository::class);

    $settings->set('general', 'app_name', 'Configured CorePanel', 'text', true);
    $settings->set('general', 'timezone', 'Europe/Berlin', 'select', true);
    $settings->set('i18n', 'default_locale', 'de', 'select', true);
    $settings->set('i18n', 'fallback_locale', 'en', 'select', true);
    $settings->set('i18n', 'languages', ['de'], 'multiselect', true);

    config()->set('app.languages', [
        'de' => 'Deutsch',
        'en' => 'English',
    ]);

    expect(
        Setting::query()
            ->where('group', 'general')
            ->where('key', 'app_name')
            ->exists(),
    )->toBeTrue();
    expect(
        Setting::query()
            ->where('group', 'general')
            ->where('key', 'app_name')
            ->first()?->getAttribute('value_json'),
    )->toBe('Configured CorePanel');

    expect($settings->get('general', 'app_name'))->toBe('Configured CorePanel')
        ->and($settings->get('general', 'timezone'))->toBe('Europe/Berlin')
        ->and($settings->get('i18n', 'default_locale'))->toBe('de')
        ->and($settings->get('i18n', 'fallback_locale'))->toBe('en')
        ->and($settings->get('i18n', 'languages'))->toBe(['de']);

    $middleware = new ApplyCorePanelRuntimeSettings($settings);
    $response = $middleware->handle(Request::create('/settings', 'GET'), static fn () => response('ok'));

    expect($response->getContent())->toBe('ok')
        ->and(config('app.name'))->toBe('Configured CorePanel')
        ->and(config('app.timezone'))->toBe('Europe/Berlin')
        ->and(config('core-panel.runtime_timezone'))->toBe('Europe/Berlin')
        ->and(config('core-panel.i18n.default_locale'))->toBe('de')
        ->and(config('core-panel.i18n.fallback_locale'))->toBe('en')
        ->and(config('core-panel.i18n.supported_locales'))->toBe(['de'])
        ->and(config('app.languages'))->toBe([
            'de' => 'Deutsch',
        ]);
});
