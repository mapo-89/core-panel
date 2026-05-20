<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;

function corePanelPermissionMiddlewareDatabaseAvailable(): bool
{
    return corePanelTestbenchDatabaseAvailable();
}

beforeEach(function (): void {
    if (! corePanelPermissionMiddlewareDatabaseAvailable()) {
        $this->markTestSkipped('pdo_sqlite is not available in this environment.');
    }

    $this->migrateScaffoldDatabase();
});

function fakePermissionUser(array $abilities = []): AuthenticatableUser
{
    return new class($abilities) extends AuthenticatableUser
    {
        /**
         * @param  list<string>  $abilities
         */
        public function __construct(private array $abilities)
        {
            $this->forceFill([
                'email' => 'test@example.test',
                'first_name' => 'Test',
                'last_name' => 'User',
                'status' => 'active',
            ]);
        }

        public function corePanelUserStatus(): string
        {
            return 'active';
        }

        public function isSuperAdmin(): bool
        {
            return false;
        }

        public function supportsCorePanelStatus(): bool
        {
            return true;
        }

        public function can($abilities, $arguments = []): bool
        {
            return in_array((string) $abilities, $this->abilities, true);
        }

        public function hasRole($roles, ?string $guard = null): bool
        {
            return false;
        }
    };
}

it('allows authorized users through the resolved route permission middleware', function (): void {
    config()->set('core-panel-access.resources.reports', ['view']);

    Route::middleware(['web', 'auth', 'check.permission'])
        ->get('/_test/reports', static fn () => 'ok')
        ->name('core-panel.reports.index');

    $user = fakePermissionUser(['reports.view']);

    Permission::findOrCreate('reports.view', 'web');

    $this->actingAs($user)
        ->get('/_test/reports')
        ->assertOk()
        ->assertSeeText('ok');
});

it('allows missing permissions outside production and logs a warning', function (): void {
    config()->set('core-panel-access.resources.reports', ['view']);
    Log::spy();

    Route::middleware(['web', 'auth', 'check.permission'])
        ->get('/_test/reports-missing', static fn () => 'ok')
        ->name('core-panel.reports.show');

    $user = fakePermissionUser();

    $this->actingAs($user)
        ->get('/_test/reports-missing')
        ->assertOk()
        ->assertSeeText('ok');

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(static fn (string $message, array $context): bool => $message === 'CorePanel route permission is missing.'
            && $context['permission'] === 'reports.view'
            && $context['route'] === 'core-panel.reports.show');
});

it('denies missing permissions in production', function (): void {
    config()->set('core-panel-access.resources.reports', ['view']);
    $this->app->detectEnvironment(static fn (): string => 'production');

    Route::middleware(['web', 'auth', 'check.permission'])
        ->get('/_test/reports-production', static fn () => 'ok')
        ->name('core-panel.reports.edit');

    $user = fakePermissionUser();

    $this->actingAs($user)
        ->get('/_test/reports-production')
        ->assertForbidden();
});

it('supports explicit permission overrides on routes', function (): void {
    Route::middleware(['web', 'auth', 'check.permission:users.update'])
        ->get('/_test/users-explicit', static fn () => 'ok')
        ->name('core-panel.custom.users-explicit');

    $user = fakePermissionUser(['users.update']);

    Permission::findOrCreate('users.update', 'web');

    $this->actingAs($user)
        ->get('/_test/users-explicit')
        ->assertOk()
        ->assertSeeText('ok');
});
