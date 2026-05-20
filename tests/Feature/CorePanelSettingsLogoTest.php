<?php

declare(strict_types=1);

use CorePanel\Models\Setting;
use CorePanel\Support\Settings\SettingsLogoManager;
use CorePanel\Support\Settings\SettingsRepository;
use CorePanel\Tests\FakeUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    if (! corePanelTestbenchDatabaseAvailable()) {
        $this->markTestSkipped('pdo_sqlite is not available in this environment.');
    }

    $this->migrateScaffoldDatabase();

    Gate::before(static fn (...$arguments): bool => true);
});

it('uploads and removes the settings logo through dedicated endpoints', function (): void {
    Storage::fake('public');

    $user = FakeUser::query()->create([
        'email' => 'settings-logo@example.test',
        'first_name' => 'Settings',
        'last_name' => 'Manager',
        'password' => Hash::make('secret-password'),
    ]);

    $uploadResponse = $this
        ->actingAs($user)
        ->post(route('core-panel.settings.logo.store'), [
            'logo' => UploadedFile::fake()->create('logo.png', 32, 'image/png'),
        ], [
            'Accept' => 'application/json',
        ]);

    $uploadResponse
        ->assertOk()
        ->assertJsonPath('message', trans('core-panel::page-settings.general_logo_uploaded_status'));

    $record = Setting::query()
        ->where('group', 'general')
        ->where('key', 'app_logo_path')
        ->first();

    $path = $record?->getAttribute('value_json')['path'] ?? null;

    expect($path)->toBeString()->not->toBe('');

    Storage::disk('public')->assertExists((string) $path);

    $deleteResponse = $this
        ->actingAs($user)
        ->delete(route('core-panel.settings.logo.destroy'), [], [
            'Accept' => 'application/json',
        ]);

    $deleteResponse
        ->assertOk()
        ->assertJsonPath('data.logo_url', null)
        ->assertJsonPath('message', trans('core-panel::page-settings.general_logo_removed_status'));

    expect(Setting::query()->where('group', 'general')->where('key', 'app_logo_path')->doesntExist())->toBeTrue()
        ->and(app(SettingsRepository::class)->get('general', 'app_logo_path'))->toBeNull();

    Storage::disk('public')->assertMissing((string) $path);
});

it('uses the public storage asset url for logo urls by default', function (): void {
    Storage::fake('public');

    $record = new Setting;
    $record->forceFill([
        'group' => 'general',
        'is_localized' => false,
        'is_public' => false,
        'key' => 'app_logo_path',
        'type' => 'json',
        'value_json' => [
            'path' => 'branding/logo.png',
        ],
    ]);
    $record->save();

    expect(app(SettingsLogoManager::class)->currentUrl())
        ->toBe(rtrim((string) config('app.url'), '/').'/storage/branding/logo.png');
});
