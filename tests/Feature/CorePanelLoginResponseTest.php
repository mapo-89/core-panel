<?php

declare(strict_types=1);

use CorePanel\Http\Responses\LoginResponse;
use CorePanel\Support\Auth\ResolveLoginDestination;
use CorePanel\Tests\FakeUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

it('falls back to the authenticated web guard user when the request user resolver is empty', function (): void {
    $user = new FakeUser([
        'id' => 1,
        'email' => 'tenant@admin.com',
    ]);

    $destination = new class extends ResolveLoginDestination
    {
        public ?Authenticatable $capturedUser = null;

        public function resolve(Request $request, Authenticatable $user): array
        {
            $this->capturedUser = $user;

            return ['destination' => '/login', 'error' => null];
        }
    };

    Auth::shouldReceive('guard')
        ->once()
        ->with('web')
        ->andReturn(new class($user)
        {
            public function __construct(private readonly Authenticatable $user) {}

            public function user(): Authenticatable
            {
                return $this->user;
            }
        });

    $request = Request::create('/login', 'POST');
    $request->setUserResolver(static fn (): null => null);
    $request->setLaravelSession(app('session.store'));

    $response = (new LoginResponse($destination))->toResponse($request);

    expect($destination->capturedUser)->toBe($user)
        ->and($response->getTargetUrl())->toEndWith('/login');
});

it('does not require a session store when no intended verification redirect is present', function (): void {
    $user = new FakeUser([
        'id' => 1,
        'email' => 'tenant@admin.com',
    ]);

    $destination = new class extends ResolveLoginDestination
    {
        public function resolve(Request $request, Authenticatable $user): array
        {
            return ['destination' => '/login', 'error' => null];
        }
    };

    Auth::shouldReceive('guard')
        ->once()
        ->with('web')
        ->andReturn(new class($user)
        {
            public function __construct(private readonly Authenticatable $user) {}

            public function user(): Authenticatable
            {
                return $this->user;
            }
        });

    $request = Request::create('/login', 'POST');
    $request->setUserResolver(static fn (): null => null);

    $response = (new LoginResponse($destination))->toResponse($request);

    expect($response->getTargetUrl())->toEndWith('/login');
});
