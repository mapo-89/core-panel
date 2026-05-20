<?php

declare(strict_types=1);

namespace CorePanel\Http\Controllers\Auth;

use CorePanel\Support\Api\Concerns\RespondsWithApi;
use CorePanel\Support\Socialite\SocialiteProviderRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class SocialiteProviderController extends Controller
{
    use RespondsWithApi;

    public function __construct(private readonly SocialiteProviderRegistry $providers) {}

    public function __invoke(Request $request): JsonResponse
    {
        return $this->success([
            'accounts' => $this->providers->linkedAccountsFor($request->user()),
            'providers' => $this->providers->enabledProviders($request->user() !== null),
        ]);
    }
}
