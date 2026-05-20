<?php

declare(strict_types=1);

namespace CorePanel\Http\Controllers\Auth;

use CorePanel\Support\Socialite\SocialiteProviderRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class LinkSocialAccountController extends Controller
{
    public function __construct(private readonly SocialiteProviderRegistry $providers) {}

    public function __invoke(Request $request, string $provider): RedirectResponse
    {
        abort_unless($request->user() !== null, 403);
        abort_unless($this->providers->isSupported($provider), 404);
        abort_unless($this->providers->isEnabled($provider, true), 404);

        $request->session()->put('page-auth.socialite.intent', 'link');

        return redirect()->route('socialite.redirect', $provider);
    }
}
