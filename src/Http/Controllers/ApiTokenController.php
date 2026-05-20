<?php

declare(strict_types=1);

namespace CorePanel\Http\Controllers;

use CorePanel\Domains\ApiToken\Actions\CreateApiTokenAction;
use CorePanel\Domains\ApiToken\Actions\DeleteApiTokenAction;
use CorePanel\Domains\ApiToken\Actions\ListApiTokensAction;
use CorePanel\Domains\ApiToken\Actions\ReplaceApiTokenAction;
use CorePanel\Http\Resources\ApiUserResource;
use CorePanel\Support\ActivityLog\ActivityLogService;
use CorePanel\Support\Api\ApiTokenAbilityOptions;
use CorePanel\Support\Api\Concerns\RespondsWithApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class ApiTokenController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly ListApiTokensAction $listApiTokens,
        private readonly CreateApiTokenAction $createApiToken,
        private readonly DeleteApiTokenAction $deleteApiToken,
        private readonly ReplaceApiTokenAction $replaceApiToken,
        private readonly ActivityLogService $activityLog,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewApiTokens', $request->user());

        return Inertia::render('ApiTokens/Index', [
            'abilities' => ApiTokenAbilityOptions::options(),
            'tokens' => $this->listApiTokens->execute($request->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('createApiTokens', $request->user());

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['nullable', 'array'],
            'abilities.*' => ['string'],
        ]);

        $created = $this->createApiToken->execute(
            $request->user(),
            $validated['name'],
            array_values((array) ($validated['abilities'] ?? [])),
        );

        $this->activityLog
            ->withCauser($request->user())
            ->log($request->user(), 'created', [
                'abilities' => array_values((array) ($validated['abilities'] ?? [])),
                'name' => $validated['name'],
                'subject_type' => 'api_token',
                'token_id' => $created['token']->getKey(),
            ]);

        return back()->with([
            'apiToken' => $created['plainTextToken'],
            'status' => __('page-api-tokens.api_tokens.created'),
        ]);
    }

    public function destroy(Request $request, string $token): RedirectResponse
    {
        Gate::authorize('deleteApiTokens', $request->user());
        $this->deleteApiToken->execute($request->user(), $token);

        $this->activityLog
            ->withCauser($request->user())
            ->log($request->user(), 'deleted', [
                'subject_type' => 'api_token',
                'token_id' => $token,
            ]);

        return back()->with('status', __('page-api-tokens.api_tokens.deleted'));
    }

    public function replace(Request $request, string $token): RedirectResponse
    {
        Gate::authorize('createApiTokens', $request->user());
        Gate::authorize('deleteApiTokens', $request->user());

        $replaced = $this->replaceApiToken->execute($request->user(), $token);

        $this->activityLog
            ->withCauser($request->user())
            ->log($request->user(), 'replaced', [
                'name' => (string) $replaced['token']->getAttribute('name'),
                'subject_type' => 'api_token',
                'token_id' => $replaced['token']->getKey(),
            ]);

        return back()->with([
            'apiToken' => $replaced['plainTextToken'],
            'status' => __('page-api-tokens.api_tokens.replaced'),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = is_object($user) && method_exists($user, 'currentAccessToken')
            ? $user->currentAccessToken()
            : null;

        if (is_object($token) && isset($token->oauth_access_token_id)) {
            $token->forceFill([
                'last_used_at' => now(),
            ])->save();
        }

        return $this->success(ApiUserResource::make($user));
    }
}
