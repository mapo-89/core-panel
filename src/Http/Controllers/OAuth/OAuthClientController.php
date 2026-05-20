<?php

declare(strict_types=1);

namespace CorePanel\Http\Controllers\OAuth;

use CorePanel\Domains\OAuthClient\Actions\CreateOAuthClientAction;
use CorePanel\Domains\OAuthClient\Actions\DeleteOAuthClientAction;
use CorePanel\Domains\OAuthClient\Actions\ListOAuthClientsAction;
use CorePanel\Domains\OAuthClient\Actions\UpdateOAuthClientAction;
use CorePanel\Models\OAuthClient;
use CorePanel\Support\ActivityLog\ActivityLogService;
use CorePanel\Support\Config\CorePanelConfig;
use CorePanel\Support\Permissions\CorePanelPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class OAuthClientController extends Controller
{
    public function __construct(
        private readonly CorePanelConfig $corePanel,
        private readonly CreateOAuthClientAction $createClient,
        private readonly DeleteOAuthClientAction $deleteClient,
        private readonly ListOAuthClientsAction $listClients,
        private readonly UpdateOAuthClientAction $updateClient,
        private readonly ActivityLogService $activityLog,
    ) {}

    public function index(Request $request): Response
    {
        $this->ensurePassportIsEnabled();
        Gate::authorize('viewOAuthClients', $request->user());

        return Inertia::render('OAuthClients/Index', [
            'clients' => $this->listClients->execute($request),
            'personalAccessClientsEnabled' => (bool) config('core-panel.auth.passport.personal_access_clients_enabled', false),
            'scopes' => array_values(CorePanelPermissions::defaults()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensurePassportIsEnabled();
        Gate::authorize('createOAuthClients', $request->user());

        $validated = $request->validate([
            'confidential' => ['sometimes', 'boolean'],
            'name' => ['required', 'string', 'max:255'],
            'personal_access_client' => ['sometimes', 'boolean'],
            'provider' => ['nullable', 'string', 'max:255'],
            'redirect' => ['nullable', 'string', 'max:2000'],
            'scopes' => ['nullable', 'array'],
            'scopes.*' => ['string'],
        ]);

        $created = $this->createClient->execute($validated, $request->user());

        $this->activityLog
            ->withCauser($request->user())
            ->log($request->user(), 'created', [
                'client_id' => $created['id'] ?? null,
                'name' => $validated['name'],
                'scopes' => (array) ($validated['scopes'] ?? []),
                'subject_type' => 'oauth_client',
            ]);

        return back()->with([
            'oauthClientSecret' => $created['secret'] ?? null,
            'status' => __('page-oauth-clients.oauth_clients.created'),
        ]);
    }

    public function update(Request $request, OAuthClient $client): RedirectResponse
    {
        $this->ensurePassportIsEnabled();
        Gate::authorize('updateOAuthClients', $request->user());
        abort_unless($this->clientVisibleToActor($client, $request), 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'provider' => ['nullable', 'string', 'max:255'],
            'redirect' => ['nullable', 'string', 'max:2000'],
            'scopes' => ['nullable', 'array'],
            'scopes.*' => ['string'],
        ]);

        $this->updateClient->execute($client, $validated);

        $this->activityLog
            ->withCauser($request->user())
            ->log($client, 'updated', [
                'name' => $validated['name'],
                'scopes' => (array) ($validated['scopes'] ?? []),
                'subject_type' => 'oauth_client',
            ]);

        return back()->with('status', __('page-oauth-clients.oauth_clients.updated'));
    }

    public function destroy(Request $request, OAuthClient $client): RedirectResponse
    {
        $this->ensurePassportIsEnabled();
        Gate::authorize('deleteOAuthClients', $request->user());
        abort_unless($this->clientVisibleToActor($client, $request), 404);

        $this->deleteClient->execute($client);

        $this->activityLog
            ->withCauser($request->user())
            ->log($client, 'deleted', [
                'name' => (string) $client->getAttribute('name'),
                'subject_type' => 'oauth_client',
            ]);

        return back()->with('status', __('page-oauth-clients.oauth_clients.deleted'));
    }

    private function clientVisibleToActor(OAuthClient $client, Request $request): bool
    {
        return true;
    }

    private function ensurePassportIsEnabled(): void
    {
        abort_unless($this->corePanel->auth->usesPassport(), 404);
    }
}
