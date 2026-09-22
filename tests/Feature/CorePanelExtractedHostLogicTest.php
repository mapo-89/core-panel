<?php

declare(strict_types=1);

use CorePanel\Actions\Fortify\CreateNewUser;
use CorePanel\Actions\Fortify\ResetUserPassword;
use CorePanel\Actions\Fortify\UpdateUserPassword;
use CorePanel\Actions\Fortify\UpdateUserProfileInformation;
use CorePanel\Http\Middleware\TrackUserPresence;
use CorePanel\Providers\CorePanelFortifyServiceProvider;
use CorePanel\Support\Fortify\HostActionOverrides;
use CorePanel\Support\Horizon\DefaultHorizonAccess;
use CorePanel\Support\Presence\PresenceManager;
use CorePanel\Support\Scheduling\CorePanelSchedule;
use CorePanel\Support\Version\CorePanelVersion;
use CorePanel\Tests\FakeUser;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Fortify\Contracts\ResetsUserPasswords;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Symfony\Component\HttpFoundation\Response;

it('registers and runs package presence middleware only for supported active users', function (): void {
    Cache::store('array')->flush();
    config()->set('cache.default', 'array');
    $middleware = app(TrackUserPresence::class);
    $user = new FakeUser;
    $user->forceFill(['id' => 'presence-user', 'status' => 'active']);
    $request = Request::create('/');
    $request->setUserResolver(static fn () => $user);

    $response = $middleware->handle($request, static fn (): Response => new Response('ok'));

    expect($response->getContent())->toBe('ok')
        ->and(app(PresenceManager::class)->lastSeenTimestamp($user))->toBeInt()
        ->and(app(Router::class)->getMiddleware()['core-panel.presence'] ?? null)->toBe(TrackUserPresence::class);

    Cache::store('array')->flush();
    config()->set('core-panel.presence.enabled', false);
    $middleware->handle($request, static fn (): Response => new Response('ok'));
    expect(app(PresenceManager::class)->lastSeenTimestamp($user))->toBeNull();
});

it('does not touch presence for guests or inactive users', function (): void {
    Cache::store('array')->flush();
    config()->set('cache.default', 'array');
    config()->set('core-panel.presence.enabled', true);
    $middleware = app(TrackUserPresence::class);
    $guestRequest = Request::create('/');
    $middleware->handle($guestRequest, static fn (): Response => new Response('ok'));

    $user = new FakeUser;
    $user->forceFill(['id' => 'inactive-user', 'status' => 'inactive']);
    $request = Request::create('/');
    $request->setUserResolver(static fn () => $user);
    $middleware->handle($request, static fn (): Response => new Response('ok'));

    expect(app(PresenceManager::class)->lastSeenTimestamp($user))->toBeNull();
});

it('registers core schedules once with their existing expressions and locks', function (): void {
    $schedule = app(Schedule::class);
    $registrar = app(CorePanelSchedule::class);
    $registrar->register($schedule);
    $registrar->register($schedule);
    $events = collect($schedule->events());

    expect($events->filter(fn ($event): bool => str_contains($event->command ?? '', 'database-backups:auto')))->toHaveCount(1)
        ->and($events->filter(fn ($event): bool => str_contains($event->command ?? '', 'system-updates:auto')))->toHaveCount(1)
        ->and($events->first(fn ($event): bool => str_contains($event->command ?? '', 'database-backups:auto'))->expression)->toBe('* * * * *')
        ->and($events->first(fn ($event): bool => str_contains($event->command ?? '', 'system-updates:auto'))->onOneServer)->toBeTrue();
});

it('ships valid configurable Fortify defaults and rejects invalid replacements', function (): void {
    expect(config('core-panel.auth.actions.create_user'))->toBe(CreateNewUser::class)
        ->and(is_a(CreateNewUser::class, CreatesNewUsers::class, true))->toBeTrue()
        ->and(is_a(UpdateUserProfileInformation::class, UpdatesUserProfileInformation::class, true))->toBeTrue()
        ->and(is_a(UpdateUserPassword::class, UpdatesUserPasswords::class, true))->toBeTrue()
        ->and(is_a(ResetUserPassword::class, ResetsUserPasswords::class, true))->toBeTrue();

    config()->set('core-panel.auth.actions.create_user', stdClass::class);

    expect(fn () => (new CorePanelFortifyServiceProvider(app()))->boot())
        ->toThrow(InvalidArgumentException::class, 'core-panel.auth.actions.create_user');
});

it('applies host Fortify action mappings outside the cached package configuration', function (): void {
    $basePath = corePanelTestTemporaryPath('host-fortify-action-overrides');
    $target = $basePath.'/'.HostActionOverrides::RELATIVE_PATH;

    mkdir(dirname($target), 0777, true);
    file_put_contents($target, <<<'PHP'
<?php

return [
    'create_user' => \CorePanel\Actions\Fortify\CreateNewUser::class,
];
PHP);
    config()->set('core-panel.auth.actions.create_user', stdClass::class);

    HostActionOverrides::apply(app('config'), $basePath);

    expect(config('core-panel.auth.actions.create_user'))->toBe(CreateNewUser::class);
});

it('uses one package Horizon authorizer and a central OpenAPI version', function (): void {
    $user = new class
    {
        public function isSuperAdmin(): bool
        {
            return true;
        }
    };

    expect(Gate::has('viewHorizon'))->toBeTrue()
        ->and(Gate::forUser($user)->allows('viewHorizon'))->toBeTrue()
        ->and((new DefaultHorizonAccess)->allows($user))->toBeTrue()
        ->and((new DefaultHorizonAccess)->allows(null))->toBeFalse()
        ->and(CorePanelVersion::RELEASE)->toMatch('/^\d+\.\d+\.\d+$/')
        ->and(is_file(__DIR__.'/../../src/OpenApi/CorePanelApiDocumentation.php'))->toBeTrue()
        ->and(is_file(__DIR__.'/../../stubs/app/OpenApi/CorePanelApiDocumentation.php'))->toBeFalse();
});
