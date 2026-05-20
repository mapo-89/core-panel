<?php

declare(strict_types=1);

use CorePanel\Domains\ActivityLog\DTOs\ActivityLogData;
use CorePanel\Models\AuthenticationLog;
use CorePanel\Support\Auth\AuthenticationLogRecorder;
use CorePanel\Support\Logs\LogEntryFilter;
use CorePanel\Support\Logs\LogEntryQuery;
use CorePanel\Support\Logs\LogFileQuery;
use CorePanel\Tests\Fakes\ActivityLogStore;
use CorePanel\Tests\FakeUser;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    if (! corePanelTestbenchDatabaseAvailable()) {
        $this->markTestSkipped('pdo_sqlite is not available in this environment.');
    }

    $this->migrateScaffoldDatabase();
    ActivityLogStore::reset();
});

it('records successful, failed, and logout authentication events', function (): void {
    if (! corePanelTestbenchDatabaseAvailable()) {
        expect(true)->toBeTrue();

        return;
    }

    $session = app('session.store');
    $session->start();

    $user = FakeUser::query()->create([
        'email' => 'developer@example.test',
        'first_name' => 'Dev',
        'last_name' => 'User',
        'password' => bcrypt('password'),
    ]);

    $successRequest = Request::create('/login', 'POST', [
        'email' => 'developer@example.test',
    ]);
    $successRequest->headers->set('User-Agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X) Chrome/124.0');
    $successRequest->server->set('REMOTE_ADDR', '127.0.0.1');
    $successRequest->setLaravelSession($session);
    app()->instance('request', $successRequest);

    /** @var AuthenticationLogRecorder $recorder */
    $recorder = app(AuthenticationLogRecorder::class);
    $recorder->recordSuccessfulLogin(new Login('web', $user, false));

    $successfulLog = AuthenticationLog::query()->latest('login_at')->first();

    expect($successfulLog)->not->toBeNull()
        ->and($successfulLog?->getAttribute('login_successful'))->toBeTrue()
        ->and($successfulLog?->getAttribute('user_id'))->toBe((string) $user->getAuthIdentifier())
        ->and($successfulLog?->getAttribute('browser'))->toBe('Chrome')
        ->and($successfulLog?->getAttribute('properties'))->toMatchArray([
            'auth_method' => 'form',
        ]);

    $failedRequest = Request::create('/login', 'POST', [
        'email' => 'unknown@example.test',
    ]);
    $failedRequest->headers->set('User-Agent', 'Mozilla/5.0 (X11; Linux x86_64) Firefox/126.0');
    $failedRequest->server->set('REMOTE_ADDR', '127.0.0.2');
    $failedRequest->setLaravelSession($session);
    app()->instance('request', $failedRequest);

    $recorder->recordFailedLogin(new Failed('web', null, [
        'email' => 'unknown@example.test',
    ]));

    $failedLog = AuthenticationLog::query()
        ->where('login', 'unknown@example.test')
        ->latest('login_at')
        ->first();

    expect($failedLog)->not->toBeNull()
        ->and($failedLog?->getAttribute('login_successful'))->toBeFalse()
        ->and($failedLog?->getAttribute('browser'))->toBe('Firefox')
        ->and($failedLog?->getAttribute('properties'))->toMatchArray([
            'auth_method' => 'form',
        ]);

    $session->migrate(true);
    $rotatedSessionId = $session->getId();

    $logoutRequest = Request::create('/logout', 'POST');
    $logoutRequest->setLaravelSession($session);
    app()->instance('request', $logoutRequest);

    $recorder->recordLogout(new Logout('web', $user));

    $refreshedSuccessfulLog = $successfulLog?->fresh();

    expect($refreshedSuccessfulLog?->getAttribute('logout_at'))->not->toBeNull()
        ->and($refreshedSuccessfulLog?->getAttribute('session_id'))->toBe($rotatedSessionId);
});

it('returns structured authentication log details for the detail modal', function (): void {
    if (! corePanelTestbenchDatabaseAvailable()) {
        expect(true)->toBeTrue();

        return;
    }

    config()->set('core-panel.user_model', FakeUser::class);

    $user = FakeUser::query()->create([
        'email' => 'developer@example.test',
        'first_name' => 'Dev',
        'last_name' => 'User',
        'password' => bcrypt('password'),
    ]);

    Role::findOrCreate('super-admin', 'web');
    $user->assignRole('super-admin');

    $log = AuthenticationLog::query()->create([
        'browser' => 'Chrome',
        'device_name' => 'MacBook Pro',
        'device_type' => 'desktop',
        'guard' => 'web',
        'ip_address' => '127.0.0.1',
        'last_active_at' => now()->subMinute(),
        'login' => $user->getAttribute('email'),
        'login_at' => now()->subHour(),
        'login_successful' => true,
        'logout_at' => now(),
        'platform' => 'macOS',
        'properties' => [
            'auth_method' => 'socialite',
            'remember' => true,
            'social_provider' => 'github',
            'user_agent' => 'Mozilla/5.0',
        ],
        'user_id' => $user->getKey(),
    ]);

    $this->actingAs($user)
        ->getJson(route('core-panel.authentication-logs.show', $log))
        ->assertOk()
        ->assertJsonPath('data.authMethod', 'socialite')
        ->assertJsonPath('data.socialProvider', 'github')
        ->assertJsonPath('data.userAvatarUrl', null)
        ->assertJsonPath('data.userAgent', 'Mozilla/5.0')
        ->assertJsonPath('data.deviceName', 'MacBook Pro')
        ->assertJsonPath('data.deviceType', 'desktop')
        ->assertJsonPath('data.browser', 'Chrome')
        ->assertJsonPath('data.platform', 'macOS')
        ->assertJsonPath('data.properties.remember', true);
});

it('lists log files and parses structured log entries', function (): void {
    $logsDirectory = storage_path('logs');
    File::ensureDirectoryExists($logsDirectory);
    $filename = 'laravel.log';
    $path = $logsDirectory.'/'.$filename;

    File::put($path, implode("\n", [
        '[2026-05-12 10:11:12] local.ERROR: First failure {"request_id":"abc"}',
        '#0 /var/www/html/app/Example.php(10): Demo->run()',
        '[2026-05-12 10:12:13] local.INFO: Follow-up message',
        '',
    ]));

    /** @var LogFileQuery $files */
    $files = app(LogFileQuery::class);
    /** @var LogEntryQuery $entries */
    $entries = app(LogEntryQuery::class);

    $file = $files->find($filename);
    $result = $entries->paginate($filename, LogEntryFilter::fromArray([
        'per_page' => 10,
    ]));

    expect($file)->not->toBeNull()
        ->and($file?->channelType)->toBe('single')
        ->and($result['eof'])->toBeTrue()
        ->and($result['entries'])->toHaveCount(2)
        ->and($result['entries'][0]['level'])->toBe('error')
        ->and($result['entries'][0]['context'])->toMatchArray([
            'request_id' => 'abc',
        ])
        ->and($result['entries'][0]['stack'])->toContain('Demo->run')
        ->and($result['entries'][1]['level'])->toBe('info');

    File::delete($path);
});

it('renders the log file detail page for super admins', function (): void {
    $logsDirectory = storage_path('logs');
    File::ensureDirectoryExists($logsDirectory);
    $filename = 'laravel-2026-05-13.log';
    $path = $logsDirectory.'/'.$filename;

    File::put($path, implode("\n", [
        '[2026-05-13 10:11:12] local.ERROR: First failure {"request_id":"abc"}',
        '#0 /var/www/html/app/Example.php(10): Demo->run()',
        '',
    ]));

    $user = FakeUser::query()->create([
        'email' => 'super-admin@example.test',
        'first_name' => 'Super',
        'last_name' => 'Admin',
        'password' => bcrypt('password'),
    ]);

    Role::findOrCreate('super-admin', 'web');
    $user->assignRole('super-admin');

    $this->actingAs($user)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->get(route('core-panel.log-files.show', ['filename' => $filename]))
        ->assertOk()
        ->assertJsonPath('component', 'Logs/File')
        ->assertJsonPath('props.file.name', $filename)
        ->assertJsonPath('props.file.channelType', 'daily')
        ->assertJsonCount(1, 'props.initialEntries');

    File::delete($path);
});

it('maps activity models without calling an undefined changes method', function (): void {
    $activity = Activity::query()->create([
        'event' => 'updated',
        'description' => 'updated',
        'log_name' => 'default',
        'properties' => [
            'changes' => [
                'attributes' => [
                    'name' => 'Updated name',
                ],
            ],
        ],
        'created_at' => now(),
    ]);

    $data = ActivityLogData::fromModel($activity);

    expect($data->changes)->toMatchArray([
        'attributes' => [
            'name' => 'Updated name',
        ],
    ]);
});

it('treats system-triggered activity entries as system even when a user caused them', function (): void {
    $user = FakeUser::query()->create([
        'email' => 'admin@example.test',
        'first_name' => 'Admin',
        'last_name' => 'User',
        'password' => bcrypt('password'),
    ]);

    $activity = Activity::query()->create([
        'event' => 'updated',
        'description' => 'updated',
        'log_name' => 'default',
        'causer_type' => $user::class,
        'causer_id' => $user->getKey(),
        'properties' => [
            'causer_display' => 'system',
            'subject_type' => 'role_permissions',
        ],
        'created_at' => now(),
    ]);

    $data = ActivityLogData::fromModel($activity);

    expect($data->systemCauser)->toBeTrue()
        ->and($data->causerId)->toBeNull()
        ->and($data->causerName)->toBeNull()
        ->and($data->toArray())->toMatchArray([
            'systemCauser' => true,
            'causerId' => null,
            'causerName' => null,
        ]);
});

it('extracts activity old and new values from top-level properties for the detail table', function (): void {
    $activity = Activity::query()->create([
        'event' => 'updated',
        'description' => 'updated',
        'log_name' => 'default',
        'properties' => [
            'attributes' => [
                'name' => 'Updated name',
                'status' => 'active',
            ],
            'old' => [
                'name' => 'Original name',
                'status' => 'draft',
            ],
        ],
        'created_at' => now(),
    ]);

    $data = ActivityLogData::fromModel($activity);

    expect($data->changes)->toMatchArray([
        'attributes' => [
            'name' => 'Updated name',
            'status' => 'active',
        ],
        'old' => [
            'name' => 'Original name',
            'status' => 'draft',
        ],
    ]);
});

it('records changed user profile attributes when updating a user', function (): void {
    Gate::before(static fn (): bool => true);
    config()->set('core-panel.user_model', FakeUser::class);

    Permission::findOrCreate('users.update', 'web');

    $role = Role::findOrCreate('admin', 'web');

    $actor = FakeUser::query()->create([
        'email' => 'actor@example.test',
        'first_name' => 'Action',
        'last_name' => 'User',
        'password' => Hash::make('secret-password'),
        'status' => 'active',
    ]);
    $actor->givePermissionTo('users.update');

    $target = FakeUser::query()->create([
        'email' => 'target@example.test',
        'first_name' => 'Original',
        'last_name' => 'Name',
        'password' => Hash::make('secret-password'),
        'status' => 'active',
    ]);
    $target->assignRole($role);

    $response = $this
        ->actingAs($actor)
        ->put(route('core-panel.users.update', $target->getKey()), [
            'email' => 'target@example.test',
            'first_name' => 'Updated',
            'last_name' => 'Name',
            'password' => '',
            'password_confirmation' => '',
            'role_names' => [$role->getAttribute('name')],
            'status' => 'active',
            'user_group_ids' => [],
        ]);

    $response
        ->assertRedirect(route('core-panel.users.show', $target->getKey()))
        ->assertSessionHas('status', trans('page-users.users.updated'));

    $activity = collect(ActivityLogStore::$entries)->last();

    expect($activity)->not->toBeNull()
        ->and(data_get($activity, 'event'))->toBe('updated')
        ->and((array) data_get($activity, 'properties'))->toMatchArray([
            'attributes' => [
                'first_name' => 'Updated',
            ],
            'old' => [
                'first_name' => 'Original',
            ],
        ]);
});
