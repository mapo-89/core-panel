<?php

declare(strict_types=1);

namespace CorePanel\Http\Controllers\Auth;

use CorePanel\Support\Socialite\SocialiteProviderRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Laravel\Socialite\Facades\Socialite;

final class SocialiteRedirectController extends Controller
{
    public function __construct(private readonly SocialiteProviderRegistry $providers) {}

    public function __invoke(string $provider): RedirectResponse
    {
        abort_unless(class_exists(Socialite::class), 404);
        abort_unless($this->providers->isSupported($provider), 404);
        abort_unless($this->providers->isEnabled($provider, true), 404);

        $driver = Socialite::driver($provider);
        $scopes = $this->providers->scopesFor($provider);

        if ($scopes !== [] && method_exists($driver, 'scopes')) {
            $driver = $driver->scopes($scopes);
        }

        if ($provider === 'microsoft' && method_exists($driver, 'with')) {
            $driver = $driver->with(['prompt' => 'select_account']);
        }

        return $driver->redirect();
    }
}
