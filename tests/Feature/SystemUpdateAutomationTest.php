<?php

declare(strict_types=1);

use CorePanel\Domain\SystemUpdate\DTOs\AutomaticSystemUpdateSettingsData;
use CorePanel\Domain\SystemUpdate\Enums\SystemUpdateInterval;
use CorePanel\Domain\SystemUpdate\Enums\SystemUpdateMode;
use CorePanel\Domain\SystemUpdate\Enums\SystemUpdateWeekday;
use CorePanel\Support\Administration\SystemUpdates\SystemUpdateSettings;
use CorePanel\Support\Settings\SettingsRepository;
use CorePanel\Tests\Fakes\ActivityLogStore;
use CorePanel\Tests\FakeUser;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    if (! corePanelTestbenchDatabaseAvailable()) {
        $this->markTestSkipped('pdo_sqlite is not available in this environment.');
    }

    $this->migrateScaffoldDatabase();
    ActivityLogStore::reset();

    config()->set('cache.default', 'array');
    config()->set('core-panel.administration.system_updates.docker_only', false);
    config()->set('core-panel.administration.system_updates.enabled', true);
    config()->set('core-panel.administration.system_updates.token', 'secret-token');
    config()->set('core-panel.administration.system_updates.updater_url', 'http://system-updater:8080');
    config()->set('system-updates.automatic.grace_minutes', 15);
    config()->set('system-updates.automatic.inactive_minutes', 0);
    Gate::before(static fn (FakeUser $user, string $ability): ?bool => $user->getAllPermissions()->contains('name', $ability) ? true : null);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function systemUpdateAutomationUser(string ...$permissions): FakeUser
{
    $user = FakeUser::query()->create([
        'email' => 'updates@example.test',
        'email_verified_at' => now(),
        'first_name' => 'System',
        'last_name' => 'Administrator',
        'password' => Hash::make('password'),
    ]);

    foreach ($permissions as $permissionName) {
        $user->givePermissionTo(Permission::findOrCreate($permissionName, 'web'));
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

function persistAutomaticSystemUpdateSettings(
    bool $enabled = true,
    SystemUpdateInterval $interval = SystemUpdateInterval::Daily,
    SystemUpdateMode $mode = SystemUpdateMode::Install,
    string $time = '02:00',
    ?SystemUpdateWeekday $weekday = SystemUpdateWeekday::Monday,
    bool $maintenanceWindowEnabled = true,
    string $windowStart = '02:00',
    string $windowEnd = '04:00',
): void {
    app(SystemUpdateSettings::class)->update(new AutomaticSystemUpdateSettingsData(
        enabled: $enabled,
        interval: $interval,
        maintenanceWindowEnabled: $maintenanceWindowEnabled,
        mode: $mode,
        time: $time,
        weekday: $weekday,
        windowEnd: $windowEnd,
        windowStart: $windowStart,
    ));
}

function fakeAvailableSystemUpdate(): void
{
    Http::fake([
        'system-updater:8080/status' => Http::response([
            'images' => [],
            'update_available' => false,
            'update_running' => false,
        ]),
        'system-updater:8080/check' => Http::response([
            'images' => [['service' => 'app', 'update_available' => true]],
            'update_available' => true,
        ]),
        'system-updater:8080/update' => Http::response([
            'images' => [['service' => 'app', 'update_available' => true]],
            'update_available' => true,
            'update_running' => true,
        ]),
    ]);
}

/** @param list<string> $terminalStates */
function fakeAutomaticSystemUpdateAttempts(array $terminalStates): void
{
    $attemptStates = [];

    Http::fake(function (HttpRequest $request) use (&$attemptStates, $terminalStates) {
        $url = $request->url();

        if (str_contains($url, '/status')) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            $attemptId = $query['attempt_id'] ?? null;

            if (is_string($attemptId) && $attemptId !== '') {
                if (! array_key_exists($attemptId, $attemptStates)) {
                    $attemptStates[$attemptId] = $terminalStates[count($attemptStates)] ?? 'running';
                }

                $state = $attemptStates[$attemptId];

                return Http::response([
                    'last_update_state' => $state,
                    'update_attempt_id' => $attemptId,
                    'update_running' => $state === 'running',
                ]);
            }

            return Http::response(['update_running' => false]);
        }

        if (str_contains($url, '/check')) {
            return Http::response([
                'images' => [['service' => 'app', 'update_available' => true]],
                'update_available' => true,
            ]);
        }

        return Http::response([
            'images' => [['service' => 'app', 'update_available' => true]],
            'update_available' => true,
            'update_running' => true,
        ], 202);
    });
}

it('keeps automatic system updates disabled by default and exposes the effective timezone', function (): void {
    app(SettingsRepository::class)->set('general', 'timezone', 'Europe/Berlin');
    config()->set('core-panel.administration.system_updates.automatic.timezone', 'America/New_York');

    Http::fake([
        'system-updater:8080/status' => Http::response([
            'images' => [],
            'update_available' => false,
            'update_running' => false,
        ]),
        'system-updater:8080/logs' => Http::response(['entries' => []]),
    ]);

    $this->actingAs(systemUpdateAutomationUser('system-updates.view', 'system-updates.update'))
        ->withHeaders(['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'])
        ->get(route('core-panel.administration.index', ['tab' => 'system-updates']))
        ->assertSuccessful()
        ->assertJsonPath('props.systemUpdatesTab.automatic.enabled', false)
        ->assertJsonPath('props.systemUpdatesTab.automatic.canUpdate', true)
        ->assertJsonPath('props.systemUpdatesTab.automatic.timezone', 'America/New_York')
        ->assertJsonPath('props.systemUpdatesTab.automatic.lastAutomaticRunAt', null);
});

it('uses known valid times when stored and configured schedule times are invalid', function (): void {
    config()->set('system-updates.automatic.enabled', true);
    config()->set('system-updates.automatic.maintenance_window_enabled', false);
    config()->set('system-updates.automatic.mode', SystemUpdateMode::Check->value);
    config()->set('system-updates.automatic.time', 'invalid-configured-time');
    config()->set('system-updates.automatic.window_end', 'invalid-configured-end');
    config()->set('system-updates.automatic.window_start', 'invalid-configured-start');
    app(SettingsRepository::class)->set(SystemUpdateSettings::GROUP, 'time', 'invalid-stored-time');
    app(SettingsRepository::class)->set(SystemUpdateSettings::GROUP, 'window_end', 'invalid-stored-end');
    app(SettingsRepository::class)->set(SystemUpdateSettings::GROUP, 'window_start', 'invalid-stored-start');

    expect(app(SystemUpdateSettings::class)->toArray())->toMatchArray([
        'time' => '02:00',
        'window_end' => '04:00',
        'window_start' => '02:00',
    ]);

    Carbon::setTestNow('2026-09-07 02:05:00 UTC');
    fakeAvailableSystemUpdate();

    $this->artisan('system-updates:auto')
        ->expectsOutputToContain('System update automation checked: update available')
        ->assertSuccessful();
});

it('uses known enum choices when stored and configured defaults are invalid', function (): void {
    config()->set('system-updates.automatic.interval', SystemUpdateInterval::Weekly->value);
    config()->set('system-updates.automatic.mode', 'deploy');
    config()->set('system-updates.automatic.weekday', 'mon');

    expect(app(SystemUpdateSettings::class)->toArray())->toMatchArray([
        'interval' => SystemUpdateInterval::Weekly->value,
        'mode' => SystemUpdateMode::Install->value,
        'weekday' => SystemUpdateWeekday::Monday->value,
    ]);

    config()->set('system-updates.automatic.interval', 'sometimes');

    expect(app(SystemUpdateSettings::class)->toArray())->toMatchArray([
        'interval' => SystemUpdateInterval::Daily->value,
        'mode' => SystemUpdateMode::Install->value,
        'weekday' => SystemUpdateWeekday::Monday->value,
    ]);

    app(SettingsRepository::class)->updateGroup(SystemUpdateSettings::GROUP, [
        'interval' => ['type' => 'string', 'value' => 'hourly'],
        'mode' => ['type' => 'string', 'value' => 'restart'],
        'weekday' => ['type' => 'string', 'value' => 'funday'],
    ]);

    expect(app(SystemUpdateSettings::class)->toArray())->toMatchArray([
        'interval' => SystemUpdateInterval::Daily->value,
        'mode' => SystemUpdateMode::Install->value,
        'weekday' => SystemUpdateWeekday::Monday->value,
    ]);
});

it('allows an authorized system administrator to persist and audit valid automatic update settings', function (): void {
    $user = systemUpdateAutomationUser('system-updates.update');

    $this->actingAs($user)
        ->put(route('core-panel.system-updates.settings.update'), [
            'automatic_enabled' => true,
            'interval' => 'weekly',
            'maintenance_window_enabled' => true,
            'mode' => 'install',
            'time' => '23:30',
            'weekday' => 'friday',
            'window_end' => '02:00',
            'window_start' => '22:00',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', __('system_updates.settings_saved'));

    expect(app(SystemUpdateSettings::class)->toArray())->toMatchArray([
        'automatic_enabled' => true,
        'interval' => 'weekly',
        'maintenance_window_enabled' => true,
        'mode' => 'install',
        'time' => '23:30',
        'weekday' => 'friday',
        'window_end' => '02:00',
        'window_start' => '22:00',
    ]);

    expect(collect(ActivityLogStore::$entries)->last())
        ->not->toBeNull()
        ->and(data_get(collect(ActivityLogStore::$entries)->last(), 'event'))
        ->toBe('system_updates.settings_updated');
});

it('allows a super administrator without a directly assigned update permission to persist automatic settings', function (): void {
    Permission::findOrCreate('system-updates.update', 'web');
    Role::findOrCreate('super-admin', 'web');

    $user = systemUpdateAutomationUser();
    $user->assignRole('super-admin');

    $this->actingAs($user)
        ->put(route('core-panel.system-updates.settings.update'), [
            'automatic_enabled' => true,
            'interval' => 'daily',
            'maintenance_window_enabled' => false,
            'mode' => 'check',
            'time' => '02:00',
            'weekday' => null,
            'window_end' => null,
            'window_start' => null,
        ])
        ->assertRedirect()
        ->assertSessionHas('success', __('system_updates.settings_saved'));

    expect($user->getDirectPermissions())->toBeEmpty()
        ->and(app(SystemUpdateSettings::class)->toArray())->toMatchArray([
            'automatic_enabled' => true,
            'mode' => 'check',
        ]);
});

it('forbids users without the global update permission from changing automatic settings', function (): void {
    $this->actingAs(systemUpdateAutomationUser('system-updates.view'))
        ->put(route('core-panel.system-updates.settings.update'), [
            'automatic_enabled' => true,
            'interval' => 'daily',
            'maintenance_window_enabled' => false,
            'mode' => 'check',
            'time' => '02:00',
            'weekday' => null,
            'window_end' => null,
            'window_start' => null,
        ])
        ->assertForbidden();

    expect(app(SystemUpdateSettings::class)->toArray()['automatic_enabled'])->toBeFalse();
});

it('rejects unknown or unsupported automatic update settings and invalid maintenance windows', function (): void {
    $this->actingAs(systemUpdateAutomationUser('system-updates.update'))
        ->put(route('core-panel.system-updates.settings.update'), [
            'automatic_enabled' => 'sometimes',
            'interval' => 'hourly',
            'maintenance_window_enabled' => true,
            'mode' => 'deploy',
            'run_now' => true,
            'time' => '25:70',
            'weekday' => 'funday',
            'window_end' => '02:00',
            'window_start' => '02:00',
        ])
        ->assertSessionHasErrors([
            'automatic_updates',
            'automatic_enabled',
            'interval',
            'mode',
            'time',
            'weekday',
        ]);
});

it('rejects non-string interval and mode inputs without a server error', function (): void {
    $this->actingAs(systemUpdateAutomationUser('system-updates.update'))
        ->put(route('core-panel.system-updates.settings.update'), [
            'automatic_enabled' => true,
            'interval' => ['weekly'],
            'maintenance_window_enabled' => true,
            'mode' => ['install'],
            'time' => '02:00',
            'weekday' => null,
            'window_end' => '04:00',
            'window_start' => '02:00',
        ])
        ->assertSessionHasErrors(['interval', 'mode']);
});

it('rejects equal windows and execution times outside a maintenance window', function (): void {
    $user = systemUpdateAutomationUser('system-updates.update');

    $this->actingAs($user)
        ->put(route('core-panel.system-updates.settings.update'), [
            'automatic_enabled' => true,
            'interval' => 'daily',
            'maintenance_window_enabled' => true,
            'mode' => 'install',
            'time' => '05:00',
            'weekday' => null,
            'window_end' => '02:00',
            'window_start' => '02:00',
        ])
        ->assertSessionHasErrors(['window_end']);

    $this->actingAs($user)
        ->put(route('core-panel.system-updates.settings.update'), [
            'automatic_enabled' => true,
            'interval' => 'daily',
            'maintenance_window_enabled' => true,
            'mode' => 'install',
            'time' => '05:00',
            'weekday' => null,
            'window_end' => '04:00',
            'window_start' => '03:00',
        ])
        ->assertSessionHasErrors(['time']);
});

it('does not call the updater when automatic updates are disabled', function (): void {
    Carbon::setTestNow('2026-09-07 02:05:00 UTC');
    persistAutomaticSystemUpdateSettings(enabled: false);
    Http::fake();

    $this->artisan('system-updates:auto')
        ->expectsOutputToContain('System update automation skipped: automatic updates disabled')
        ->assertSuccessful();

    Http::assertNothingSent();
});

it('does not call the updater before the configured execution time', function (): void {
    Carbon::setTestNow('2026-09-07 01:59:00 UTC');
    persistAutomaticSystemUpdateSettings();
    Http::fake();

    $this->artisan('system-updates:auto')
        ->expectsOutputToContain('System update automation skipped: not due')
        ->assertSuccessful();

    Http::assertNothingSent();
});

it('runs a due installation through the existing updater endpoints only once per slot', function (): void {
    Carbon::setTestNow('2026-09-07 02:10:00 UTC');
    persistAutomaticSystemUpdateSettings();
    fakeAutomaticSystemUpdateAttempts(['success']);

    $this->artisan('system-updates:auto')
        ->expectsOutputToContain('System update automation updated: update started')
        ->assertSuccessful();

    expect(app(SystemUpdateSettings::class)->toArray()['last_automatic_run_at'])->toBeNull()
        ->and(app(SystemUpdateSettings::class)->pendingAutomaticAttempt())->not->toBeNull();

    $this->artisan('system-updates:auto')
        ->expectsOutputToContain('System update automation skipped: scheduled run already handled')
        ->assertSuccessful();

    $this->artisan('system-updates:auto')
        ->expectsOutputToContain('System update automation skipped: scheduled run already handled')
        ->assertSuccessful();

    Http::assertSentCount(4);
    Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'http://system-updater:8080/status');
    Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'http://system-updater:8080/check');
    Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'http://system-updater:8080/update');

    expect(app(SystemUpdateSettings::class)->toArray()['last_automatic_run_at'])->not->toBeNull()
        ->and(app(SystemUpdateSettings::class)->pendingAutomaticAttempt())->toBeNull();
});

it('retries the same due slot after an accepted installation fails asynchronously', function (): void {
    Carbon::setTestNow('2026-09-07 02:10:00 UTC');
    persistAutomaticSystemUpdateSettings();
    fakeAutomaticSystemUpdateAttempts(['failed', 'success']);

    $this->artisan('system-updates:auto')
        ->expectsOutputToContain('System update automation updated: update started')
        ->assertSuccessful();

    $firstAttempt = app(SystemUpdateSettings::class)->pendingAutomaticAttempt();

    Carbon::setTestNow('2026-09-07 03:00:00 UTC');

    $this->artisan('system-updates:auto')
        ->expectsOutputToContain('System update automation updated: update started')
        ->assertSuccessful();

    $secondAttempt = app(SystemUpdateSettings::class)->pendingAutomaticAttempt();

    expect($firstAttempt)->not->toBeNull()
        ->and($secondAttempt)->not->toBeNull()
        ->and($secondAttempt['attempt_id'])->not->toBe($firstAttempt['attempt_id'])
        ->and(app(SystemUpdateSettings::class)->toArray()['last_automatic_run_at'])->toBeNull();

    $this->artisan('system-updates:auto')
        ->expectsOutputToContain('System update automation skipped: scheduled run already handled')
        ->assertSuccessful();

    expect(Http::recorded(fn (HttpRequest $request): bool => $request->url() === 'http://system-updater:8080/update'))->toHaveCount(2)
        ->and(app(SystemUpdateSettings::class)->pendingAutomaticAttempt())->toBeNull()
        ->and(app(SystemUpdateSettings::class)->toArray()['last_automatic_run_at'])->not->toBeNull();
});

it('keeps an expired failed slot retryable until the next maintenance window', function (): void {
    Carbon::setTestNow('2026-09-07 03:59:00 UTC');
    persistAutomaticSystemUpdateSettings(time: '03:59');
    fakeAutomaticSystemUpdateAttempts(['failed', 'success']);

    $this->artisan('system-updates:auto')
        ->expectsOutputToContain('System update automation updated: update started')
        ->assertSuccessful();

    $failedAttempt = app(SystemUpdateSettings::class)->pendingAutomaticAttempt();

    Carbon::setTestNow('2026-09-07 04:40:00 UTC');

    $this->artisan('system-updates:auto')
        ->expectsOutputToContain('System update automation skipped: outside maintenance window')
        ->assertSuccessful();

    expect($failedAttempt)->not->toBeNull()
        ->and(app(SystemUpdateSettings::class)->pendingAutomaticAttempt())->toBeNull()
        ->and(app(SystemUpdateSettings::class)->retryableAutomaticSlot())->toBe($failedAttempt['slot'])
        ->and(Http::recorded(fn (HttpRequest $request): bool => $request->url() === 'http://system-updater:8080/update'))->toHaveCount(1);

    Carbon::setTestNow('2026-09-08 02:05:00 UTC');

    $this->artisan('system-updates:auto')
        ->expectsOutputToContain('System update automation updated: update started')
        ->assertSuccessful();

    expect(app(SystemUpdateSettings::class)->pendingAutomaticAttempt())->not->toBeNull()
        ->and(app(SystemUpdateSettings::class)->retryableAutomaticSlot())->toBeNull()
        ->and(Http::recorded(fn (HttpRequest $request): bool => $request->url() === 'http://system-updater:8080/update'))->toHaveCount(2);
});

it('retries an expired pending slot when the updater no longer knows its attempt', function (): void {
    Carbon::setTestNow('2026-09-07 02:10:00 UTC');
    persistAutomaticSystemUpdateSettings();

    Http::fake(function (HttpRequest $request) {
        if (str_contains($request->url(), '/status?attempt_id=')) {
            return Http::response([
                'last_update_state' => 'success',
                'update_attempt_id' => 'different-current-attempt',
                'update_running' => false,
            ]);
        }

        if (str_contains($request->url(), '/status')) {
            return Http::response(['update_running' => false]);
        }

        if (str_contains($request->url(), '/check')) {
            return Http::response([
                'images' => [['service' => 'app', 'update_available' => true]],
                'update_available' => true,
            ]);
        }

        return Http::response([
            'images' => [['service' => 'app', 'update_available' => true]],
            'update_available' => true,
            'update_running' => true,
        ], 202);
    });

    $this->artisan('system-updates:auto')
        ->expectsOutputToContain('System update automation updated: update started')
        ->assertSuccessful();

    $forgottenAttempt = app(SystemUpdateSettings::class)->pendingAutomaticAttempt();

    Carbon::setTestNow('2026-09-07 03:00:00 UTC');

    $this->artisan('system-updates:auto')
        ->expectsOutputToContain('System update automation updated: update started')
        ->assertSuccessful();

    $retriedAttempt = app(SystemUpdateSettings::class)->pendingAutomaticAttempt();

    expect($forgottenAttempt)->not->toBeNull()
        ->and($retriedAttempt)->not->toBeNull()
        ->and($retriedAttempt['attempt_id'])->not->toBe($forgottenAttempt['attempt_id'])
        ->and($retriedAttempt['slot'])->toBe($forgottenAttempt['slot'])
        ->and(app(SystemUpdateSettings::class)->retryableAutomaticSlot())->toBeNull()
        ->and(Http::recorded(fn (HttpRequest $request): bool => $request->url() === 'http://system-updater:8080/update'))->toHaveCount(2);
});

it('supports an installation window that crosses midnight', function (): void {
    Carbon::setTestNow('2026-09-07 23:35:00 UTC');
    persistAutomaticSystemUpdateSettings(
        time: '23:30',
        windowStart: '22:00',
        windowEnd: '02:00',
    );
    fakeAvailableSystemUpdate();

    $this->artisan('system-updates:auto')
        ->expectsOutputToContain('System update automation updated: update started')
        ->assertSuccessful();

    Http::assertSentCount(3);
});

it('does not execute the repeated daylight-saving-time slot twice', function (): void {
    app(SettingsRepository::class)->set('general', 'timezone', 'Europe/Berlin');
    config()->set('core-panel.administration.system_updates.automatic.timezone', 'Europe/Berlin');
    Carbon::setTestNow('2026-10-25T00:05:00Z');
    persistAutomaticSystemUpdateSettings(
        mode: SystemUpdateMode::Check,
        maintenanceWindowEnabled: false,
    );
    fakeAvailableSystemUpdate();

    $this->artisan('system-updates:auto')->assertSuccessful();

    Carbon::setTestNow('2026-10-25T01:05:00Z');

    $this->artisan('system-updates:auto')
        ->expectsOutputToContain('System update automation skipped: scheduled run already handled')
        ->assertSuccessful();

    Http::assertSentCount(2);
});

it('supports delayed check-only runs without starting an installation', function (): void {
    Carbon::setTestNow('2026-09-07 02:14:00 UTC');
    persistAutomaticSystemUpdateSettings(mode: SystemUpdateMode::Check, maintenanceWindowEnabled: false);
    fakeAvailableSystemUpdate();

    $this->artisan('system-updates:auto')
        ->expectsOutputToContain('System update automation checked: update available')
        ->assertSuccessful();

    Http::assertSentCount(2);
    Http::assertNotSent(fn (HttpRequest $request): bool => $request->url() === 'http://system-updater:8080/update');
});

it('extends a due slot for activity during the grace period', function (SystemUpdateInterval $interval): void {
    config()->set('system-updates.automatic.grace_minutes', 15);
    config()->set('system-updates.automatic.inactive_minutes', 15);
    config()->set('session.driver', 'database');
    config()->set('session.table', 'sessions');
    Carbon::setTestNow('2026-09-07 02:01:00 UTC');

    $user = systemUpdateAutomationUser();
    DB::table('sessions')->insert([
        'id' => 'automatic-update-due-slot',
        'ip_address' => '127.0.0.1',
        'last_activity' => now()->timestamp,
        'payload' => 'payload',
        'user_agent' => 'Pest',
        'user_id' => (string) $user->getKey(),
    ]);
    persistAutomaticSystemUpdateSettings(
        interval: $interval,
        mode: SystemUpdateMode::Check,
        maintenanceWindowEnabled: false,
    );
    fakeAvailableSystemUpdate();

    Carbon::setTestNow('2026-09-07 02:16:00 UTC');

    $this->artisan('system-updates:auto')
        ->expectsOutputToContain('System update automation skipped: recent user activity detected')
        ->assertSuccessful();

    Carbon::setTestNow('2026-09-07 02:17:00 UTC');

    $this->artisan('system-updates:auto')
        ->expectsOutputToContain('System update automation checked: update available')
        ->assertSuccessful();

    Http::assertSentCount(2);
})->with([
    SystemUpdateInterval::Daily,
    SystemUpdateInterval::Weekly,
]);

it('carries a daily due slot across midnight', function (): void {
    Carbon::setTestNow('2026-09-07 00:01:00 UTC');
    persistAutomaticSystemUpdateSettings(
        mode: SystemUpdateMode::Check,
        time: '23:55',
        maintenanceWindowEnabled: false,
    );
    fakeAvailableSystemUpdate();

    $this->artisan('system-updates:auto')
        ->expectsOutputToContain('System update automation checked: update available')
        ->assertSuccessful();

    Http::assertSentCount(2);
});

it('uses the previous scheduled weekday when a weekly due slot crosses midnight', function (): void {
    Carbon::setTestNow('2026-09-07 00:01:00 UTC');
    persistAutomaticSystemUpdateSettings(
        interval: SystemUpdateInterval::Weekly,
        mode: SystemUpdateMode::Check,
        time: '23:55',
        weekday: SystemUpdateWeekday::Sunday,
        maintenanceWindowEnabled: false,
    );
    fakeAvailableSystemUpdate();

    $this->artisan('system-updates:auto')
        ->expectsOutputToContain('System update automation checked: update available')
        ->assertSuccessful();

    Http::assertSentCount(2);
});

it('honors weekly schedules in the dedicated automatic update timezone', function (): void {
    app(SettingsRepository::class)->set('general', 'timezone', 'Europe/Berlin');
    config()->set('core-panel.administration.system_updates.automatic.timezone', 'America/New_York');
    Carbon::setTestNow('2026-09-07 06:05:00 UTC');
    persistAutomaticSystemUpdateSettings(
        interval: SystemUpdateInterval::Weekly,
        mode: SystemUpdateMode::Check,
        weekday: SystemUpdateWeekday::Monday,
        maintenanceWindowEnabled: false,
    );
    fakeAvailableSystemUpdate();

    $this->artisan('system-updates:auto')->assertSuccessful();

    Http::assertSentCount(2);
});

it('does not install outside the configured maintenance window', function (): void {
    Carbon::setTestNow('2026-09-07 02:05:00 UTC');
    persistAutomaticSystemUpdateSettings(windowStart: '03:00', windowEnd: '04:00');
    Http::fake();

    $this->artisan('system-updates:auto')
        ->expectsOutputToContain('System update automation skipped: outside maintenance window')
        ->assertSuccessful();

    Http::assertNothingSent();
});

it('allows a due installation grace tail past the maintenance window end', function (): void {
    Carbon::setTestNow('2026-09-07 04:05:00 UTC');
    persistAutomaticSystemUpdateSettings(
        time: '03:59',
        windowStart: '02:00',
        windowEnd: '04:00',
    );
    fakeAvailableSystemUpdate();

    $this->artisan('system-updates:auto')
        ->expectsOutputToContain('System update automation updated: update started')
        ->assertSuccessful();

    Http::assertSentCount(3);
});

it('does not start a parallel update and releases its coordination lock after errors', function (): void {
    Carbon::setTestNow('2026-09-07 02:05:00 UTC');
    persistAutomaticSystemUpdateSettings();

    Http::fake([
        'system-updater:8080/status' => Http::sequence()
            ->push(['update_running' => true], 200)
            ->push(['update_running' => false], 200),
        'system-updater:8080/check' => Http::response([], 500),
    ]);

    $this->artisan('system-updates:auto')
        ->expectsOutputToContain('System update automation skipped: update already running')
        ->assertSuccessful();

    $this->artisan('system-updates:auto')->assertFailed();

    expect(Cache::lock('core-panel:system-updates:auto', 900)->get())->toBeTrue();
});
