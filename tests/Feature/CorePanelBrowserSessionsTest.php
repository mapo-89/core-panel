<?php

declare(strict_types=1);

use CorePanel\Models\AuthenticationLog;
use CorePanel\Support\Auth\AuthenticationLogRecorder;
use CorePanel\Support\Auth\RevokeBrowserSession;
use CorePanel\Tests\FakeUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

it('logs out all other browser sessions for the authenticated user', function (): void {
    if (! corePanelTestbenchDatabaseAvailable()) {
        $this->markTestSkipped('pdo_sqlite is not available in this environment.');
    }

    $this->migrateScaffoldDatabase();

    config()->set('session.driver', 'database');
    config()->set('session.table', 'sessions');

    if (! Schema::hasTable('sessions')) {
        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    $user = FakeUser::query()->create([
        'email' => 'sessions@example.test',
        'first_name' => 'Session',
        'last_name' => 'Owner',
        'password' => Hash::make('secret-password'),
    ]);

    $this->startSession();

    $currentSessionId = session()->getId();

    DB::table('sessions')->insert([
        [
            'id' => $currentSessionId,
            'user_id' => $user->getAuthIdentifier(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Current Browser',
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ],
        [
            'id' => 'other-session-a',
            'user_id' => $user->getAuthIdentifier(),
            'ip_address' => '10.0.0.2',
            'user_agent' => 'Other Browser',
            'payload' => 'payload',
            'last_activity' => now()->subMinute()->timestamp,
        ],
    ]);

    AuthenticationLog::query()->create([
        'guard' => 'web',
        'last_active_at' => now(),
        'login' => $user->getAttribute('email'),
        'login_at' => now()->subMinutes(5),
        'login_successful' => true,
        'session_id' => $currentSessionId,
        'user_id' => (string) $user->getAuthIdentifier(),
    ]);
    $otherLog = AuthenticationLog::query()->create([
        'guard' => 'web',
        'last_active_at' => now(),
        'login' => $user->getAttribute('email'),
        'login_at' => now()->subMinutes(4),
        'login_successful' => true,
        'session_id' => 'other-session-a',
        'user_id' => (string) $user->getAuthIdentifier(),
    ]);

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->post(route('profile.sessions.destroy-others'), [
            'password' => 'secret-password',
        ]);

    $response
        ->assertRedirect('/profile')
        ->assertSessionHas('status', trans('page-users.users.other_sessions_revoked'));

    expect(DB::table('sessions')->count())->toBe(1)
        ->and(DB::table('sessions')->where('id', 'other-session-a')->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('user_id', $user->getAuthIdentifier())->count())->toBe(1)
        ->and($otherLog->fresh()?->getAttribute('logout_at'))->not->toBeNull()
        ->and($otherLog->fresh()?->getAttribute('properties'))->toMatchArray([
            'logout_actor_id' => (string) $user->getAuthIdentifier(),
            'logout_reason' => 'revoked_other_sessions',
        ]);
});

it('logs individually revoked browser sessions', function (): void {
    if (! corePanelTestbenchDatabaseAvailable()) {
        $this->markTestSkipped('pdo_sqlite is not available in this environment.');
    }

    $this->migrateScaffoldDatabase();

    config()->set('session.driver', 'database');
    config()->set('session.table', 'sessions');

    if (! Schema::hasTable('sessions')) {
        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    $user = FakeUser::query()->create([
        'email' => 'target@example.test',
        'first_name' => 'Target',
        'last_name' => 'User',
        'password' => Hash::make('secret-password'),
    ]);
    $actor = FakeUser::query()->create([
        'email' => 'actor@example.test',
        'first_name' => 'Admin',
        'last_name' => 'User',
        'password' => Hash::make('secret-password'),
    ]);

    DB::table('sessions')->insert([
        'id' => 'revoked-session',
        'user_id' => $user->getAuthIdentifier(),
        'ip_address' => '10.0.0.3',
        'user_agent' => 'Revoked Browser',
        'payload' => 'payload',
        'last_activity' => now()->timestamp,
    ]);

    $log = AuthenticationLog::query()->create([
        'guard' => 'web',
        'last_active_at' => now(),
        'login' => $user->getAttribute('email'),
        'login_at' => now()->subMinutes(5),
        'login_successful' => true,
        'session_id' => 'revoked-session',
        'user_id' => (string) $user->getAuthIdentifier(),
    ]);

    $request = request();
    $request->setUserResolver(static fn (): FakeUser => $actor);

    app(RevokeBrowserSession::class)->execute($user, 'revoked-session');

    expect(DB::table('sessions')->where('id', 'revoked-session')->exists())->toBeFalse()
        ->and($log->fresh()?->getAttribute('logout_at'))->not->toBeNull()
        ->and($log->fresh()?->getAttribute('properties'))->toMatchArray([
            'logout_actor_id' => (string) $actor->getAuthIdentifier(),
            'logout_reason' => 'revoked',
        ]);
});

it('logs expired database sessions when their session row is gone', function (): void {
    if (! corePanelTestbenchDatabaseAvailable()) {
        $this->markTestSkipped('pdo_sqlite is not available in this environment.');
    }

    $this->migrateScaffoldDatabase();

    config()->set('session.driver', 'database');
    config()->set('session.table', 'sessions');

    if (! Schema::hasTable('sessions')) {
        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    $user = FakeUser::query()->create([
        'email' => 'expired@example.test',
        'first_name' => 'Expired',
        'last_name' => 'User',
        'password' => Hash::make('secret-password'),
    ]);

    $activeLog = AuthenticationLog::query()->create([
        'guard' => 'web',
        'last_active_at' => now(),
        'login' => $user->getAttribute('email'),
        'login_at' => now()->subMinutes(5),
        'login_successful' => true,
        'session_id' => 'active-session',
        'user_id' => (string) $user->getAuthIdentifier(),
    ]);
    $expiredLog = AuthenticationLog::query()->create([
        'guard' => 'web',
        'last_active_at' => now()->subHour(),
        'login' => $user->getAttribute('email'),
        'login_at' => now()->subHour(),
        'login_successful' => true,
        'session_id' => 'expired-session',
        'user_id' => (string) $user->getAuthIdentifier(),
    ]);

    DB::table('sessions')->insert([
        'id' => 'active-session',
        'user_id' => $user->getAuthIdentifier(),
        'ip_address' => '10.0.0.4',
        'user_agent' => 'Active Browser',
        'payload' => 'payload',
        'last_activity' => now()->timestamp,
    ]);

    app(AuthenticationLogRecorder::class)->recordExpiredDatabaseSessions();

    expect($activeLog->fresh()?->getAttribute('logout_at'))->toBeNull()
        ->and($expiredLog->fresh()?->getAttribute('logout_at'))->not->toBeNull()
        ->and($expiredLog->fresh()?->getAttribute('properties'))->toMatchArray([
            'logout_reason' => 'expired',
        ]);
});

it('does not mark the current request session as expired before it is persisted', function (): void {
    if (! corePanelTestbenchDatabaseAvailable()) {
        $this->markTestSkipped('pdo_sqlite is not available in this environment.');
    }

    $this->migrateScaffoldDatabase();

    config()->set('session.driver', 'database');
    config()->set('session.table', 'sessions');

    if (! Schema::hasTable('sessions')) {
        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    $user = FakeUser::query()->create([
        'email' => 'current@example.test',
        'first_name' => 'Current',
        'last_name' => 'User',
        'password' => Hash::make('secret-password'),
    ]);

    $session = app('session.store');
    $session->setId('current-request-session');
    $session->start();
    $currentSessionId = $session->getId();

    $request = Request::create('/admin/logs', 'GET');
    $request->setLaravelSession($session);
    app()->instance('request', $request);

    $currentLog = AuthenticationLog::query()->create([
        'guard' => 'web',
        'last_active_at' => now(),
        'login' => $user->getAttribute('email'),
        'login_at' => now()->subMinute(),
        'login_successful' => true,
        'session_id' => $currentSessionId,
        'user_id' => (string) $user->getAuthIdentifier(),
    ]);
    $expiredLog = AuthenticationLog::query()->create([
        'guard' => 'web',
        'last_active_at' => now()->subHour(),
        'login' => $user->getAttribute('email'),
        'login_at' => now()->subHour(),
        'login_successful' => true,
        'session_id' => 'expired-session',
        'user_id' => (string) $user->getAuthIdentifier(),
    ]);

    app(AuthenticationLogRecorder::class)->recordExpiredDatabaseSessions();

    expect($currentLog->fresh()?->getAttribute('logout_at'))->toBeNull()
        ->and($expiredLog->fresh()?->getAttribute('logout_at'))->not->toBeNull()
        ->and($expiredLog->fresh()?->getAttribute('properties'))->toMatchArray([
            'logout_reason' => 'expired',
        ]);
});

it('reconciles a rotated current session before marking stale sessions as expired', function (): void {
    if (! corePanelTestbenchDatabaseAvailable()) {
        $this->markTestSkipped('pdo_sqlite is not available in this environment.');
    }

    $this->migrateScaffoldDatabase();

    config()->set('session.driver', 'database');
    config()->set('session.table', 'sessions');

    if (! Schema::hasTable('sessions')) {
        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    $user = FakeUser::query()->create([
        'email' => 'rotated@example.test',
        'first_name' => 'Rotated',
        'last_name' => 'Session',
        'password' => Hash::make('secret-password'),
    ]);

    $session = app('session.store');
    $session->setId('current-session-after-login');
    $session->start();
    $currentSessionId = $session->getId();

    DB::table('sessions')->insert([
        'id' => $currentSessionId,
        'user_id' => $user->getAuthIdentifier(),
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Current Browser',
        'payload' => 'payload',
        'last_activity' => now()->timestamp,
    ]);

    $this->actingAs($user);

    $request = Request::create('/admin/logs', 'GET');
    $request->setUserResolver(static fn (): FakeUser => $user);
    $request->setLaravelSession($session);
    app()->instance('request', $request);

    $rotatedLog = AuthenticationLog::query()->create([
        'guard' => 'web',
        'last_active_at' => now(),
        'login' => $user->getAttribute('email'),
        'login_at' => now()->subMinute(),
        'login_successful' => true,
        'session_id' => 'session-id-before-login-rotation',
        'user_id' => (string) $user->getAuthIdentifier(),
    ]);
    $expiredLog = AuthenticationLog::query()->create([
        'guard' => 'web',
        'last_active_at' => now()->subHour(),
        'login' => $user->getAttribute('email'),
        'login_at' => now()->subHour(),
        'login_successful' => true,
        'session_id' => 'expired-session',
        'user_id' => (string) $user->getAuthIdentifier(),
    ]);

    app(AuthenticationLogRecorder::class)->recordExpiredDatabaseSessions();

    expect($rotatedLog->fresh()?->getAttribute('session_id'))->toBe($currentSessionId)
        ->and($rotatedLog->fresh()?->getAttribute('logout_at'))->toBeNull()
        ->and($expiredLog->fresh()?->getAttribute('logout_at'))->not->toBeNull()
        ->and($expiredLog->fresh()?->getAttribute('properties'))->toMatchArray([
            'logout_reason' => 'expired',
        ]);
});
