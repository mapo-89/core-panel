<?php

declare(strict_types=1);

namespace CorePanel\Http\Controllers\Auth;

use CorePanel\Http\Requests\ResolveSocialiteConflictRequest;
use CorePanel\Models\SocialAccount;
use CorePanel\Support\ActivityLog\ActivityLogService;
use CorePanel\Support\Auth\ResolveLoginDestination;
use CorePanel\Support\Media\MediaService;
use CorePanel\Support\Settings\SettingsRepository;
use CorePanel\Support\Socialite\SocialAccountStore;
use CorePanel\Support\Socialite\SocialiteProviderRegistry;
use CorePanel\Support\Socialite\SocialUserManager;
use CorePanel\Support\Users\UserModelManager;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

final class SocialiteCallbackController extends Controller
{
    private const PENDING_LINK_SESSION_KEY = 'page-auth.socialite.pending-link';

    private const PENDING_AVATAR_SYNC_SESSION_KEY = 'page-auth.socialite.pending-avatar-sync';

    private const AUTH_METHOD_ATTRIBUTE = 'core_panel.authentication_method';

    private const SOCIAL_PROVIDER_ATTRIBUTE = 'core_panel.social_provider';

    public function __construct(
        private readonly SocialAccountStore $accounts,
        private readonly ActivityLogService $activityLog,
        private readonly ResolveLoginDestination $loginDestination,
        private readonly MediaService $media,
        private readonly SettingsRepository $settings,
        private readonly SocialiteProviderRegistry $providers,
        private readonly SocialUserManager $users,
        private readonly UserModelManager $userModels,
    ) {}

    public function __invoke(Request $request, string $provider): RedirectResponse
    {
        abort_unless(class_exists(Socialite::class), 404);
        abort_unless($this->providers->isSupported($provider), 404);
        abort_unless($this->providers->isEnabled($provider, true), 404);

        try {
            $providerUser = Socialite::driver($provider)->user();
        } catch (Throwable) {
            return redirect()
                ->to('/login')
                ->withErrors(['socialite' => __('page-auth.socialite.login_failed')]);
        }

        $intent = (string) $request->session()->pull('page-auth.socialite.intent', 'login');

        if ($intent === 'link' && $request->user() !== null) {
            if ($this->providers->isMasterProvider($provider, true)) {
                return $this->linkMasterProviderAccount($request, $provider, $providerUser);
            }

            return $this->linkAccount($request, $provider, $providerUser);
        }

        if ($this->providers->isMasterProvider($provider, true)) {
            try {
                return $this->startMasterProviderLogin($request, $provider, $providerUser);
            } catch (DecryptException $exception) {
                return $this->handleBrokenMicrosoftConnectionDuringAuthentication($request, $provider, $exception);
            }
        }

        try {
            return $this->loginWithSocialAccount($request, $provider, $providerUser);
        } catch (DecryptException $exception) {
            return $this->handleBrokenMicrosoftConnectionDuringAuthentication($request, $provider, $exception);
        }
    }

    public function showConflict(Request $request, string $provider): Response|RedirectResponse
    {
        abort_unless($this->providers->isSupported($provider), 404);
        abort_unless($this->providers->isMasterProvider($provider, true), 404);

        $pending = $this->pendingConflictData($request, $provider);

        if ($pending === null) {
            return $this->redirectForContext((string) ($request->session()->get(self::PENDING_LINK_SESSION_KEY.'.intent') ?: 'link'))
                ->withErrors([
                    'socialite' => __('page-auth.socialite.conflict_missing', [
                        'provider' => $this->providers->labelFor($provider),
                    ]),
                ]);
        }

        $context = $this->pendingIntent($pending);
        $currentUser = $context === 'link' ? $request->user() : null;
        $existingUser = isset($pending['existing_user_id'])
            ? $this->findUserById((string) $pending['existing_user_id'])
            : null;

        return Inertia::render($context === 'link' ? 'Admin/Settings/SocialAccountConflict' : 'Auth/SocialAccountConflict', [
            'context' => $context,
            'currentEmail' => $currentUser !== null
                ? (string) data_get($currentUser, 'email', '')
                : ($existingUser instanceof Authenticatable ? (string) data_get($existingUser, 'email', '') : null),
            'currentAvatarUrl' => is_string($pending['current_avatar_url'] ?? null) ? $pending['current_avatar_url'] : null,
            'decisionType' => $this->decisionTypeForPendingLink($pending),
            'existingUser' => $existingUser instanceof Authenticatable ? [
                'email' => (string) data_get($existingUser, 'email', ''),
                'fullName' => $this->displayNameForUser($existingUser),
            ] : null,
            'provider' => $provider,
            'providerAvatarUrl' => is_string($pending['provider_avatar_url'] ?? null) ? $pending['provider_avatar_url'] : null,
            'providerEmail' => (string) ($pending['provider_email'] ?? ''),
            'providerLabel' => $this->providers->labelFor($provider),
        ]);
    }

    public function resolveConflict(ResolveSocialiteConflictRequest $request, string $provider): RedirectResponse
    {
        abort_unless($this->providers->isSupported($provider), 404);
        abort_unless($this->providers->isMasterProvider($provider, true), 404);

        /** @var array<string, mixed>|null $pending */
        $pending = $request->session()->pull(self::PENDING_LINK_SESSION_KEY);
        $context = is_array($pending) ? $this->pendingIntent($pending) : 'link';

        if (
            ! is_array($pending)
            || ($pending['provider'] ?? null) !== $provider
        ) {
            return $this->redirectForContext($context)
                ->withErrors([
                    'socialite' => __('page-auth.socialite.conflict_missing', [
                        'provider' => $this->providers->labelFor($provider),
                    ]),
                ]);
        }

        $user = $request->user();

        if (
            $context === 'link'
            && (
                $user === null
                || (string) ($pending['current_user_id'] ?? '') !== (string) $user->getAuthIdentifier()
            )
        ) {
            return $this->redirectToConnections()
                ->withErrors([
                    'socialite' => __('page-auth.socialite.conflict_missing', [
                        'provider' => $this->providers->labelFor($provider),
                    ]),
                ]);
        }

        $decision = $request->string('decision')->toString();
        $avatarDecision = $request->string('avatar_decision')->toString();

        if ($decision === 'cancel') {
            return $this->redirectForContext($context)
                ->with('status', __('page-auth.socialite.conflict_cancelled', [
                    'provider' => $this->providers->labelFor($provider),
                ]));
        }

        if ($context === 'login') {
            return match ($decision) {
                'change_email' => $this->completeLoginByChangingEmail($request, $provider, $pending, $avatarDecision),
                default => $this->redirectToLogin()
                    ->withErrors([
                        'socialite' => __('page-auth.socialite.conflict_missing', [
                            'provider' => $this->providers->labelFor($provider),
                        ]),
                    ]),
            };
        }

        return match ($decision) {
            'change_email' => $this->completeLinkByChangingEmail($request, $user, $provider, $pending, $avatarDecision),
            'confirm_link' => $this->completeLinkByConfirming($request, $user, $provider, $pending, $avatarDecision),
            'switch_user' => $this->completeLinkBySwitchingUser($request, $provider, $pending, $avatarDecision),
            'takeover_connection' => $this->completeLinkByTakingOverConnection($request, $user, $provider, $pending, $avatarDecision),
            default => $this->redirectToConnections()
                ->withErrors([
                    'socialite' => __('page-auth.socialite.conflict_missing', [
                        'provider' => $this->providers->labelFor($provider),
                    ]),
                ]),
        };
    }

    public function resolveAvatarSync(Request $request, string $provider): RedirectResponse
    {
        abort_unless($this->providers->isSupported($provider), 404);
        abort_unless($this->supportsAvatarSyncForProvider($provider), 404);

        $user = $request->user();
        abort_unless($user !== null, 403);

        /** @var array<string, mixed>|null $pending */
        $pending = $request->session()->pull(self::PENDING_AVATAR_SYNC_SESSION_KEY);

        if (
            ! is_array($pending)
            || ($pending['provider'] ?? null) !== $provider
            || (string) ($pending['user_id'] ?? '') !== (string) $user->getAuthIdentifier()
        ) {
            return back()->withErrors([
                'socialite' => __('core-panel::page-settings.social_avatar_sync_missing', [
                    'provider' => $this->providers->labelFor($provider),
                ]),
            ]);
        }

        $validated = $request->validate([
            'decision' => ['required', 'in:keep,replace'],
        ]);

        if ($validated['decision'] === 'replace') {
            $providerAvatarUrl = is_string($pending['provider_avatar_url'] ?? null)
                ? $pending['provider_avatar_url']
                : null;

            if ($providerAvatarUrl === null || ! $this->importProviderAvatar($user, $provider, $providerAvatarUrl, true)) {
                return back()->withErrors([
                    'socialite' => __('core-panel::page-settings.social_avatar_sync_replace_failed', [
                        'provider' => $this->providers->labelFor($provider),
                    ]),
                ]);
            }

            return back()->with('success', __('core-panel::page-settings.social_avatar_sync_replaced', [
                'provider' => $this->providers->labelFor($provider),
            ]));
        }

        return back()->with('info', __('core-panel::page-settings.social_avatar_sync_kept', [
            'provider' => $this->providers->labelFor($provider),
        ]));
    }

    public function sendTestMail(Request $request, string $provider): RedirectResponse
    {
        abort_unless($provider === 'microsoft', 404);
        abort_unless($this->providers->isSupported($provider), 404);

        $user = $request->user();
        abort_unless($user !== null, 403);

        $account = $this->accounts->forUserAndProvider($user, $provider);

        if (! $account instanceof SocialAccount) {
            return $this->redirectToConnections()->withErrors([
                'socialite' => __('core-panel::page-settings.microsoft_disconnected_hint'),
            ]);
        }

        $accessToken = $account->getAttribute('token_encrypted');

        if (! is_string($accessToken) || trim($accessToken) === '') {
            return $this->redirectToConnections()->withErrors([
                'socialite' => __('core-panel::page-settings.microsoft_reconnect_required'),
            ]);
        }

        $recipientEmail = $account->getAttribute('provider_email') ?: data_get($user, 'email');

        if (! is_string($recipientEmail) || trim($recipientEmail) === '') {
            return $this->redirectToConnections()->withErrors([
                'socialite' => __('core-panel::page-settings.microsoft_test_mail_missing_recipient'),
            ]);
        }

        try {
            Http::withToken($accessToken)
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(10)
                ->post('https://graph.microsoft.com/v1.0/me/sendMail', [
                    'message' => [
                        'subject' => __('core-panel::page-settings.microsoft_test_mail_subject', [
                            'app' => config('app.name'),
                        ]),
                        'body' => [
                            'contentType' => 'Text',
                            'content' => __('core-panel::page-settings.microsoft_test_mail_body', [
                                'app' => config('app.name'),
                                'timestamp' => now()->toDateTimeString(),
                            ]),
                        ],
                        'toRecipients' => [
                            [
                                'emailAddress' => [
                                    'address' => $recipientEmail,
                                ],
                            ],
                        ],
                    ],
                    'saveToSentItems' => true,
                ])->throw();
        } catch (Throwable $exception) {
            report($exception);

            return $this->redirectToConnections()->withErrors([
                'socialite' => __('core-panel::page-settings.microsoft_test_mail_failed'),
            ]);
        }

        return $this->redirectToConnections()->with('success', __('core-panel::page-settings.microsoft_test_mail_sent', [
            'email' => $recipientEmail,
        ]));
    }

    private function linkAccount(Request $request, string $provider, mixed $providerUser): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $existingAccount = $this->accounts->findByProviderUser($provider, (string) $providerUser->getId());

        if ($existingAccount instanceof SocialAccount && (string) $existingAccount->getAttribute('user_id') !== (string) $user->getAuthIdentifier()) {
            return $this->redirectToConnections()
                ->withErrors(['socialite' => __('page-auth.socialite.account_already_linked')]);
        }

        $previousProviderAvatarUrl = $this->providerAvatarUrlForUser($user, $provider);
        $account = $this->accounts->upsertForUser($user, $provider, $providerUser);
        $this->recordLinkActivity($user, $account, $provider, (string) $providerUser->getId());
        $this->syncMasterProviderAvatar($request, $user, $provider, $providerUser, $previousProviderAvatarUrl);

        return $this->redirectToConnections()
            ->with('status', __('page-auth.social_accounts.linked'));
    }

    private function linkMasterProviderAccount(Request $request, string $provider, mixed $providerUser): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $providerEmail = $providerUser->getEmail();

        if (! filled($providerEmail)) {
            return $this->redirectToConnections()
                ->withErrors(['socialite' => __('page-auth.socialite.email_required')]);
        }

        $currentEmail = (string) data_get($user, 'email', '');
        $existingUser = $this->users->findByEmail((string) $providerEmail);
        $previousProviderAvatarUrl = $this->providerAvatarUrlForUser($user, $provider);
        $pendingAvatarPrompt = $this->pendingAvatarPromptData($user, $provider, $providerUser, $previousProviderAvatarUrl);
        $conflictingAccount = SocialAccount::query()
            ->where('provider', $provider)
            ->where(function ($query) use ($providerUser, $providerEmail): void {
                $query->where('provider_user_id', (string) $providerUser->getId())
                    ->orWhere('provider_email', (string) $providerEmail);
            })
            ->where('user_id', '!=', (string) $user->getAuthIdentifier())
            ->first();

        if ($existingUser instanceof Authenticatable && (string) $existingUser->getAuthIdentifier() !== (string) $user->getAuthIdentifier()) {
            $request->session()->put(self::PENDING_LINK_SESSION_KEY, [
                ...$this->providerPayload($provider, $providerUser),
                ...($pendingAvatarPrompt ?? []),
                'current_user_id' => (string) $user->getAuthIdentifier(),
                'decision_type' => 'switch_user',
                'existing_user_id' => (string) $existingUser->getAuthIdentifier(),
                'intent' => 'link',
            ]);

            return $this->redirectToConflict($provider);
        }

        if ($conflictingAccount instanceof SocialAccount) {
            $request->session()->put(self::PENDING_LINK_SESSION_KEY, [
                ...$this->providerPayload($provider, $providerUser),
                ...($pendingAvatarPrompt ?? []),
                'current_user_id' => (string) $user->getAuthIdentifier(),
                'decision_type' => 'takeover_connection',
                'existing_social_account_id' => (string) $conflictingAccount->getKey(),
                'existing_user_id' => (string) $conflictingAccount->getAttribute('user_id'),
                'intent' => 'link',
            ]);

            return $this->redirectToConflict($provider);
        }
        if ($this->emailsMatch($currentEmail, (string) $providerEmail) && $pendingAvatarPrompt === null) {
            $account = $this->accounts->upsertForUser($user, $provider, $providerUser);
            $this->recordLinkActivity($user, $account, $provider, (string) $providerUser->getId());
            $this->syncMasterProviderAvatar($request, $user, $provider, $providerUser, $previousProviderAvatarUrl);

            return $this->redirectToConnections()
                ->with('status', __('page-auth.social_accounts.linked'));
        }

        if (
            $this->emailsMatch($currentEmail, (string) $providerEmail)
            && ($pendingAvatarPrompt['current_avatar_url'] ?? null) === null
        ) {
            $account = $this->accounts->upsertForUser($user, $provider, $providerUser);
            $this->recordLinkActivity($user, $account, $provider, (string) $providerUser->getId());
            $this->syncMasterProviderAvatar($request, $user, $provider, $providerUser, $previousProviderAvatarUrl);

            return $this->redirectToConnections()
                ->with('status', __('page-auth.social_accounts.linked'));
        }

        $request->session()->put(self::PENDING_LINK_SESSION_KEY, [
            ...$this->providerPayload($provider, $providerUser),
            ...($pendingAvatarPrompt ?? []),
            'current_user_id' => (string) $user->getAuthIdentifier(),
            'decision_type' => $this->emailsMatch($currentEmail, (string) $providerEmail)
                ? 'confirm_link'
                : 'change_email',
            'intent' => 'link',
        ]);

        return $this->redirectToConflict($provider);
    }

    private function startMasterProviderLogin(Request $request, string $provider, mixed $providerUser): RedirectResponse
    {
        $providerEmail = $providerUser->getEmail();

        if (! filled($providerEmail)) {
            return $this->redirectToLogin()
                ->withErrors(['socialite' => __('page-auth.socialite.email_required')]);
        }

        $account = $this->accounts->findByProviderUser($provider, (string) $providerUser->getId());
        $existingUser = $account instanceof SocialAccount
            ? $this->accounts->resolveUser($account)
            : null;

        if ($existingUser instanceof Authenticatable) {
            if (! $this->emailsMatch((string) data_get($existingUser, 'email', ''), (string) $providerEmail)) {
                $previousProviderAvatarUrl = $this->providerAvatarUrlForUser($existingUser, $provider);
                $pendingAvatarPrompt = $this->pendingAvatarPromptData($existingUser, $provider, $providerUser, $previousProviderAvatarUrl);

                $request->session()->put(self::PENDING_LINK_SESSION_KEY, [
                    ...$this->providerPayload($provider, $providerUser),
                    ...($pendingAvatarPrompt ?? []),
                    'decision_type' => 'change_email',
                    'existing_user_id' => (string) $existingUser->getAuthIdentifier(),
                    'intent' => 'login',
                ]);

                return $this->redirectToConflict($provider);
            }

            $previousProviderAvatarUrl = $this->providerAvatarUrlForUser($existingUser, $provider);
            $this->accounts->upsertForUser($existingUser, $provider, $providerUser);
            $this->syncMasterProviderAvatar($request, $existingUser, $provider, $providerUser, $previousProviderAvatarUrl);

            return $this->completeAuthenticatedSocialLogin($request, $provider, $existingUser);
        }

        $existingUser = $this->users->findByEmail((string) $providerEmail);

        if ($existingUser instanceof Authenticatable) {
            $previousProviderAvatarUrl = $this->providerAvatarUrlForUser($existingUser, $provider);
            $this->accounts->upsertForUser($existingUser, $provider, $providerUser);
            $this->syncMasterProviderAvatar($request, $existingUser, $provider, $providerUser, $previousProviderAvatarUrl);

            return $this->completeAuthenticatedSocialLogin($request, $provider, $existingUser);
        }

        $user = $this->users->createFromProviderUser($provider, $providerUser);
        $this->accounts->upsertForUser($user, $provider, $providerUser);
        $this->syncMasterProviderAvatar($request, $user, $provider, $providerUser, null);

        return $this->completeAuthenticatedSocialLogin($request, $provider, $user);
    }

    private function loginWithSocialAccount(Request $request, string $provider, mixed $providerUser): RedirectResponse
    {
        if (! filled($providerUser->getEmail())) {
            return redirect()
                ->to('/login')
                ->withErrors(['socialite' => __('page-auth.socialite.email_required')]);
        }

        $account = $this->accounts->findByProviderUser($provider, (string) $providerUser->getId());
        $user = $account instanceof SocialAccount ? $this->accounts->resolveUser($account) : null;

        if (! $user instanceof Authenticatable) {
            $user = $this->users->findByEmail((string) $providerUser->getEmail());
        }

        if (! $user instanceof Authenticatable) {
            if (! $this->registrationIsEnabled()) {
                return redirect()
                    ->to('/login')
                    ->withErrors(['socialite' => __('page-auth.socialite.registration_disabled')]);
            }

            $user = $this->users->createFromProviderUser($provider, $providerUser);
        }

        $previousProviderAvatarUrl = $this->providerAvatarUrlForUser($user, $provider);
        $this->accounts->upsertForUser($user, $provider, $providerUser);
        $this->syncMasterProviderAvatar($request, $user, $provider, $providerUser, $previousProviderAvatarUrl);

        if (! $this->userCanAuthenticate($user)) {
            return redirect()
                ->to('/login')
                ->withErrors(['socialite' => __('auth.failed')]);
        }

        $this->prepareAuthenticationLogContext($request, $provider);
        Auth::login($user, true);
        $request->session()->regenerate();

        if ($provider === 'microsoft' && $this->requiresPasswordSetup($user)) {
            return redirect()
                ->route($this->tenantAwareRouteName('profile.show'), ['tab' => 'password'])
                ->with('warning', __('core-panel::page-settings.password_setup_required_notice'));
        }

        $destination = $this->loginDestination->resolve($request, $user);
        $redirect = redirect()->to($destination['destination']);

        if ($destination['error'] !== null) {
            $redirect->withErrors(['socialite' => $destination['error']]);
        }

        return $redirect;
    }

    /**
     * @param  array<string, mixed>  $pending
     */
    private function completeLinkByChangingEmail(Request $request, Authenticatable $user, string $provider, array $pending, string $avatarDecision = ''): RedirectResponse
    {
        $providerEmail = $pending['provider_email'] ?? null;

        if (! is_string($providerEmail) || $providerEmail === '') {
            return $this->redirectToConnections()
                ->withErrors([
                    'socialite' => __('page-auth.socialite.conflict_missing', [
                        'provider' => $this->providers->labelFor($provider),
                    ]),
                ]);
        }

        $otherUser = $this->users->findByEmail($providerEmail);

        if ($otherUser instanceof Authenticatable && (string) $otherUser->getAuthIdentifier() !== (string) $user->getAuthIdentifier()) {
            return $this->redirectToConnections()
                ->withErrors([
                    'socialite' => __('core-panel::page-settings.social_master_conflict_email_taken', [
                        'provider' => $this->providers->labelFor($provider),
                    ]),
                ]);
        }

        $this->updateUserEmail($user, $providerEmail);

        $previousProviderAvatarUrl = $this->providerAvatarUrlForUser($user, $provider);
        $account = $this->upsertLinkedAccount($user, $provider, $pending);
        $this->recordLinkActivity($user, $account, $provider, (string) $pending['provider_user_id']);
        $avatarImportFailed = ! $this->finalizePendingAvatarDecision($request, $user, $provider, $pending, $avatarDecision);

        if ($avatarDecision === '' && ! $avatarImportFailed) {
            $this->syncMasterProviderAvatar($request, $user, $provider, $pending, $previousProviderAvatarUrl);
        }

        $redirect = $this->redirectToConnections()
            ->with('status', __('core-panel::page-settings.social_master_connected_email_updated', [
                'provider' => $this->providers->labelFor($provider),
            ]));

        if ($avatarImportFailed) {
            $redirect->with('warning', __('core-panel::page-settings.social_avatar_sync_replace_failed', [
                'provider' => $this->providers->labelFor($provider),
            ]));
        }

        return $redirect;
    }

    /**
     * @param  array<string, mixed>  $pending
     */
    private function completeLinkByConfirming(Request $request, Authenticatable $user, string $provider, array $pending, string $avatarDecision = ''): RedirectResponse
    {
        $previousProviderAvatarUrl = $this->providerAvatarUrlForUser($user, $provider);
        $account = $this->upsertLinkedAccount($user, $provider, $pending);
        $this->recordLinkActivity($user, $account, $provider, (string) $pending['provider_user_id']);
        $avatarImportFailed = ! $this->finalizePendingAvatarDecision($request, $user, $provider, $pending, $avatarDecision);

        if ($avatarDecision === '' && ! $avatarImportFailed) {
            $this->syncMasterProviderAvatar($request, $user, $provider, $pending, $previousProviderAvatarUrl);
        }

        $redirect = $this->redirectToConnections()
            ->with('status', __('page-auth.social_accounts.linked'));

        if ($avatarImportFailed) {
            $redirect->with('warning', __('core-panel::page-settings.social_avatar_sync_replace_failed', [
                'provider' => $this->providers->labelFor($provider),
            ]));
        }

        return $redirect;
    }

    /**
     * @param  array<string, mixed>  $pending
     */
    private function completeLinkBySwitchingUser(Request $request, string $provider, array $pending, string $avatarDecision = ''): RedirectResponse
    {
        $targetUser = isset($pending['existing_user_id'])
            ? $this->findUserById((string) $pending['existing_user_id'])
            : null;

        if (! $targetUser instanceof Authenticatable) {
            return $this->redirectForContext($this->pendingIntent($pending))
                ->withErrors([
                    'socialite' => __('page-auth.socialite.conflict_missing', [
                        'provider' => $this->providers->labelFor($provider),
                    ]),
                ]);
        }

        if (! $this->userCanAuthenticate($targetUser)) {
            return redirect()
                ->to('/login')
                ->withErrors(['socialite' => __('auth.failed')]);
        }

        $previousProviderAvatarUrl = $this->providerAvatarUrlForUser($targetUser, $provider);
        $account = $this->upsertLinkedAccount($targetUser, $provider, $pending);
        $this->recordLinkActivity($targetUser, $account, $provider, (string) $pending['provider_user_id']);
        $avatarImportFailed = ! $this->finalizePendingAvatarDecision($request, $targetUser, $provider, $pending, $avatarDecision);

        if ($avatarDecision === '' && ! $avatarImportFailed) {
            $this->syncMasterProviderAvatar($request, $targetUser, $provider, $pending, $previousProviderAvatarUrl);
        }

        $this->prepareAuthenticationLogContext($request, $provider);
        Auth::login($targetUser, true);
        $request->session()->regenerate();

        if ($provider === 'microsoft' && $this->requiresPasswordSetup($targetUser)) {
            return redirect()
                ->route($this->tenantAwareRouteName('profile.show'), ['tab' => 'password'])
                ->with('warning', __('core-panel::page-settings.password_setup_required_notice'));
        }

        $destination = $this->loginDestination->resolve($request, $targetUser);

        $redirect = redirect()->to($destination['destination']);

        if ($avatarImportFailed) {
            $redirect->with('warning', __('core-panel::page-settings.social_avatar_sync_replace_failed', [
                'provider' => $this->providers->labelFor($provider),
            ]));
        }

        return $redirect;
    }

    /**
     * @param  array<string, mixed>  $pending
     */
    private function completeLoginByChangingEmail(Request $request, string $provider, array $pending, string $avatarDecision = ''): RedirectResponse
    {
        $targetUser = isset($pending['existing_user_id'])
            ? $this->findUserById((string) $pending['existing_user_id'])
            : null;
        $providerEmail = $pending['provider_email'] ?? null;

        if (! $targetUser instanceof Authenticatable || ! is_string($providerEmail) || $providerEmail === '') {
            return $this->redirectToLogin()
                ->withErrors([
                    'socialite' => __('page-auth.socialite.conflict_missing', [
                        'provider' => $this->providers->labelFor($provider),
                    ]),
                ]);
        }

        $otherUser = $this->users->findByEmail($providerEmail);

        if ($otherUser instanceof Authenticatable && (string) $otherUser->getAuthIdentifier() !== (string) $targetUser->getAuthIdentifier()) {
            return $this->redirectToLogin()
                ->withErrors([
                    'socialite' => __('core-panel::page-settings.social_master_conflict_email_taken', [
                        'provider' => $this->providers->labelFor($provider),
                    ]),
                ]);
        }

        $this->updateUserEmail($targetUser, $providerEmail);
        $previousProviderAvatarUrl = $this->providerAvatarUrlForUser($targetUser, $provider);
        $account = $this->upsertLinkedAccount($targetUser, $provider, $pending);
        $this->recordLinkActivity($targetUser, $account, $provider, (string) $pending['provider_user_id']);
        $avatarImportFailed = ! $this->finalizePendingAvatarDecision($request, $targetUser, $provider, $pending, $avatarDecision);

        if ($avatarDecision === '' && ! $avatarImportFailed) {
            $this->syncMasterProviderAvatar($request, $targetUser, $provider, $pending, $previousProviderAvatarUrl);
        }

        $redirect = $this->completeAuthenticatedSocialLogin($request, $provider, $targetUser);

        if ($avatarImportFailed) {
            $redirect->with('warning', __('core-panel::page-settings.social_avatar_sync_replace_failed', [
                'provider' => $this->providers->labelFor($provider),
            ]));
        }

        return $redirect;
    }

    /**
     * @param  array<string, mixed>  $pending
     */
    private function completeLinkByTakingOverConnection(
        Request $request,
        Authenticatable $user,
        string $provider,
        array $pending,
        string $avatarDecision = '',
    ): RedirectResponse {
        $existingAccountId = $pending['existing_social_account_id'] ?? null;
        $providerEmail = $pending['provider_email'] ?? null;

        $existingAccount = is_string($existingAccountId)
            ? SocialAccount::query()->whereKey($existingAccountId)->where('provider', $provider)->first()
            : null;

        if (
            ! $existingAccount instanceof SocialAccount
            || (string) $existingAccount->getAttribute('user_id') === (string) $user->getAuthIdentifier()
            || ! is_string($providerEmail)
            || $providerEmail === ''
        ) {
            return $this->redirectToConnections()
                ->withErrors([
                    'socialite' => __('core-panel::page-settings.social_master_conflict_missing', [
                        'provider' => $this->providers->labelFor($provider),
                    ]),
                ]);
        }

        $otherUser = $this->users->findByEmail($providerEmail);

        if ($otherUser instanceof Authenticatable && (string) $otherUser->getAuthIdentifier() !== (string) $user->getAuthIdentifier()) {
            return $this->redirectToConnections()
                ->withErrors([
                    'socialite' => __('core-panel::page-settings.social_master_conflict_email_taken', [
                        'provider' => $this->providers->labelFor($provider),
                    ]),
                ]);
        }

        $previousProviderAvatarUrl = $this->providerAvatarUrlForUser($user, $provider);

        /** @var SocialAccount $account */
        $account = DB::transaction(function () use ($existingAccount, $pending, $provider, $providerEmail, $user): SocialAccount {
            $existingAccount->delete();
            $this->updateUserEmail($user, $providerEmail);

            return $this->upsertLinkedAccount($user, $provider, $pending);
        });

        $this->recordLinkActivity($user, $account, $provider, (string) $pending['provider_user_id']);
        $avatarImportFailed = ! $this->finalizePendingAvatarDecision($request, $user, $provider, $pending, $avatarDecision);

        if ($avatarDecision === '' && ! $avatarImportFailed) {
            $this->syncMasterProviderAvatar($request, $user, $provider, $pending, $previousProviderAvatarUrl);
        }

        $redirect = $this->redirectToConnections()
            ->with('status', __('core-panel::page-settings.social_master_connected_reassigned', [
                'provider' => $this->providers->labelFor($provider),
            ]));

        if ($avatarImportFailed) {
            $redirect->with('warning', __('core-panel::page-settings.social_avatar_sync_replace_failed', [
                'provider' => $this->providers->labelFor($provider),
            ]));
        }

        return $redirect;
    }

    private function registrationIsEnabled(): bool
    {
        return (bool) $this->settings->get(
            'auth',
            'registration_enabled',
            (bool) config('core-panel.auth.registration_enabled', false),
        );
    }

    private function requiresPasswordSetup(Authenticatable $user): bool
    {
        return method_exists($user, 'requiresPasswordSetup') && (bool) $user->requiresPasswordSetup();
    }

    private function userCanAuthenticate(Authenticatable $user): bool
    {
        if (
            method_exists($user, 'supportsCorePanelStatus')
            && $user->supportsCorePanelStatus()
            && method_exists($user, 'corePanelUserStatus')
        ) {
            return $user->corePanelUserStatus() === 'active';
        }

        return true;
    }

    /**
     * @return array{
     *     avatar_url:?string,
     *     expires_in:?int,
     *     provider:string,
     *     provider_email:?string,
     *     provider_user_id:string,
     *     refresh_token:?string,
     *     token:?string
     * }
     */
    private function providerPayload(string $provider, mixed $providerUser): array
    {
        return [
            'avatar_url' => $this->accounts->avatarUrlFromProviderUser($providerUser),
            'expires_in' => isset($providerUser->expiresIn) && is_numeric($providerUser->expiresIn)
                ? (int) $providerUser->expiresIn
                : null,
            'provider' => $provider,
            'provider_email' => $providerUser->getEmail(),
            'provider_user_id' => (string) $providerUser->getId(),
            'refresh_token' => $providerUser->refreshToken ?? null,
            'token' => $providerUser->token ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $pending
     */
    private function upsertLinkedAccount(Authenticatable $user, string $provider, array $pending): SocialAccount
    {
        return $this->accounts->upsertForUserWithAttributes($user, $provider, [
            'avatar_url' => is_string($pending['avatar_url'] ?? null) ? $pending['avatar_url'] : null,
            'expires_in' => isset($pending['expires_in']) && is_numeric($pending['expires_in']) ? (int) $pending['expires_in'] : null,
            'provider_email' => is_string($pending['provider_email'] ?? null) ? $pending['provider_email'] : null,
            'provider_user_id' => (string) $pending['provider_user_id'],
            'refresh_token' => is_string($pending['refresh_token'] ?? null) ? $pending['refresh_token'] : null,
            'token' => is_string($pending['token'] ?? null) ? $pending['token'] : null,
        ]);
    }

    private function recordLinkActivity(Authenticatable $user, SocialAccount $account, string $provider, string $providerUserId): void
    {
        $this->activityLog
            ->withCauser($user)
            ->log($account, 'linked', [
                'provider' => $provider,
                'provider_user_id' => $providerUserId,
                'subject_type' => 'social_account',
            ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function pendingConflictData(Request $request, string $provider): ?array
    {
        $pending = $request->session()->get(self::PENDING_LINK_SESSION_KEY);

        if (! is_array($pending) || ($pending['provider'] ?? null) !== $provider) {
            return null;
        }

        $intent = $this->pendingIntent($pending);

        if ($intent !== 'login') {
            $user = $request->user();

            if (
                $user === null
                || (string) ($pending['current_user_id'] ?? '') !== (string) $user->getAuthIdentifier()
            ) {
                return null;
            }
        }

        return $pending;
    }

    /**
     * @param  array<string, mixed>  $pending
     * @return 'change_email'|'switch_user'|'takeover_connection'
     */
    private function decisionTypeForPendingLink(array $pending): string
    {
        if (isset($pending['decision_type']) && is_string($pending['decision_type']) && $pending['decision_type'] !== '') {
            return $pending['decision_type'];
        }

        if (filled($pending['existing_social_account_id'] ?? null)) {
            return 'takeover_connection';
        }

        if (filled($pending['existing_user_id'] ?? null)) {
            return 'switch_user';
        }

        return 'change_email';
    }

    /**
     * @param  array<string, mixed>  $pending
     */
    private function pendingIntent(array $pending): string
    {
        return ($pending['intent'] ?? 'link') === 'login' ? 'login' : 'link';
    }

    private function findUserById(string $id): ?Authenticatable
    {
        $userModelClass = (string) config('core-panel.user_model', config('auth.providers.users.model'));

        if ($userModelClass === '' || ! class_exists($userModelClass)) {
            return null;
        }

        $user = $userModelClass::query()->find($id);

        return $user instanceof Authenticatable ? $user : null;
    }

    private function displayNameForUser(Authenticatable $user): string
    {
        $fullName = trim((string) data_get($user, 'full_name', ''));

        if ($fullName !== '') {
            return $fullName;
        }

        $name = trim((string) data_get($user, 'name', ''));

        if ($name !== '') {
            return $name;
        }

        return trim(sprintf(
            '%s %s',
            (string) data_get($user, 'first_name', ''),
            (string) data_get($user, 'last_name', ''),
        )) ?: (string) data_get($user, 'email', '');
    }

    private function emailsMatch(?string $left, ?string $right): bool
    {
        return Str::lower(trim((string) $left)) === Str::lower(trim((string) $right));
    }

    private function updateUserEmail(Authenticatable $user, string $email): void
    {
        abort_unless($user instanceof Model, 500);

        $user->forceFill([
            'email' => $email,
            'email_verified_at' => now(),
        ])->save();
    }

    private function redirectToConflict(string $provider): RedirectResponse
    {
        return redirect()->route($this->tenantAwareRouteName('socialite.conflict'), ['provider' => $provider]);
    }

    private function completeAuthenticatedSocialLogin(Request $request, string $provider, Authenticatable $user): RedirectResponse
    {
        if (! $this->userCanAuthenticate($user)) {
            return $this->redirectToLogin()
                ->withErrors(['socialite' => __('auth.failed')]);
        }

        $this->prepareAuthenticationLogContext($request, $provider);
        Auth::login($user, true);
        $request->session()->regenerate();

        if ($provider === 'microsoft' && $this->requiresPasswordSetup($user)) {
            return redirect()
                ->route($this->tenantAwareRouteName('profile.show'), ['tab' => 'password'])
                ->with('warning', __('core-panel::page-settings.password_setup_required_notice'));
        }

        $destination = $this->loginDestination->resolve($request, $user);
        $redirect = redirect()->to($destination['destination']);

        if ($destination['error'] !== null) {
            $redirect->withErrors(['socialite' => $destination['error']]);
        }

        return $redirect;
    }

    private function providerAvatarUrlForUser(Authenticatable $user, string $provider): ?string
    {
        return $this->accounts
            ->forUserAndProvider($user, $provider)
            ?->getAttribute('avatar_url');
    }

    /**
     * @param  array<string, mixed>|object  $providerPayload
     */
    private function syncMasterProviderAvatar(
        Request $request,
        Authenticatable $user,
        string $provider,
        array|object $providerPayload,
        ?string $previousProviderAvatarUrl,
    ): void {
        if (! $this->supportsAvatarSyncForProvider($provider)) {
            return;
        }

        if (! $user instanceof Model || ! $this->userModels->supportsMedia()) {
            return;
        }

        $pendingAvatarPrompt = $this->pendingAvatarPromptData($user, $provider, $providerPayload, $previousProviderAvatarUrl);

        if ($pendingAvatarPrompt === null) {
            return;
        }

        if (($pendingAvatarPrompt['current_avatar_url'] ?? null) === null) {
            $this->importProviderAvatar($user, $provider, (string) $pendingAvatarPrompt['provider_avatar_url'], false);

            return;
        }

        $request->session()->put(self::PENDING_AVATAR_SYNC_SESSION_KEY, $pendingAvatarPrompt + [
            'provider_label' => $this->providers->labelFor($provider),
            'user_id' => (string) $user->getAuthIdentifier(),
        ]);
    }

    /**
     * @param  array<string, mixed>|object  $providerPayload
     * @return array<string, string|null>|null
     */
    private function pendingAvatarPromptData(
        Authenticatable $user,
        string $provider,
        array|object $providerPayload,
        ?string $previousProviderAvatarUrl,
    ): ?array {
        if (! $user instanceof Model) {
            return null;
        }

        $providerAvatarUrl = is_array($providerPayload)
            ? (is_string($providerPayload['avatar_url'] ?? null) ? $providerPayload['avatar_url'] : null)
            : $this->accounts->avatarUrlFromProviderUser($providerPayload);

        if ($providerAvatarUrl === null || $providerAvatarUrl === '') {
            return null;
        }

        $currentAvatarUrl = $this->userModels->avatarUrl($user);

        if ($currentAvatarUrl !== null && $this->normalizeAvatarFingerprint($currentAvatarUrl) === $this->normalizeAvatarFingerprint($providerAvatarUrl)) {
            return null;
        }

        if ($this->avatarAlreadyImportedFromProvider($user, $provider, $providerAvatarUrl)) {
            return null;
        }

        if (
            $currentAvatarUrl !== null
            && $this->normalizeAvatarFingerprint($previousProviderAvatarUrl) === $this->normalizeAvatarFingerprint($providerAvatarUrl)
        ) {
            return [
                'current_avatar_url' => $currentAvatarUrl,
                'provider' => $provider,
                'provider_avatar_url' => $providerAvatarUrl,
            ];
        }

        return [
            'current_avatar_url' => $currentAvatarUrl,
            'provider' => $provider,
            'provider_avatar_url' => $providerAvatarUrl,
        ];
    }

    /**
     * @param  array<string, mixed>  $pending
     */
    private function finalizePendingAvatarDecision(
        Request $request,
        Authenticatable $user,
        string $provider,
        array $pending,
        string $avatarDecision,
    ): bool {
        $providerAvatarUrl = is_string($pending['provider_avatar_url'] ?? null)
            ? $pending['provider_avatar_url']
            : null;

        if ($providerAvatarUrl === null || $providerAvatarUrl === '') {
            return true;
        }

        $currentAvatarUrl = is_string($pending['current_avatar_url'] ?? null)
            ? $pending['current_avatar_url']
            : null;

        if ($currentAvatarUrl === null) {
            return $this->importProviderAvatar($user, $provider, $providerAvatarUrl, false);
        }

        if ($avatarDecision !== 'replace') {
            $request->session()->forget(self::PENDING_AVATAR_SYNC_SESSION_KEY);

            return true;
        }

        $request->session()->forget(self::PENDING_AVATAR_SYNC_SESSION_KEY);

        return $this->importProviderAvatar($user, $provider, $providerAvatarUrl, true);
    }

    private function importProviderAvatar(
        Authenticatable $user,
        string $provider,
        string $providerAvatarUrl,
        bool $replaceExistingAvatar,
    ): bool {
        if (! $user instanceof Model || ! $this->userModels->supportsMedia()) {
            return false;
        }

        try {
            if ($replaceExistingAvatar && method_exists($user, 'clearMediaCollection')) {
                $user->clearMediaCollection('avatars');
            }

            $this->media->uploadFromUrlWithProperties(
                $user,
                $providerAvatarUrl,
                'avatars',
                customProperties: [
                    'source' => 'socialite-avatar',
                    'social_avatar_fingerprint' => $this->normalizeAvatarFingerprint($providerAvatarUrl),
                    'social_provider' => $provider,
                ],
            );

            return true;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    private function normalizeAvatarFingerprint(?string $url): ?string
    {
        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        return Str::lower(trim($url));
    }

    private function avatarAlreadyImportedFromProvider(
        Authenticatable $user,
        string $provider,
        string $providerAvatarUrl,
    ): bool {
        if (! $user instanceof Model || ! method_exists($user, 'getFirstMedia')) {
            return false;
        }

        $media = $user->getFirstMedia('avatars');

        if ($media === null || ! method_exists($media, 'getCustomProperty')) {
            return false;
        }

        return $media->getCustomProperty('source') === 'socialite-avatar'
            && $media->getCustomProperty('social_provider') === $provider
            && $this->normalizeAvatarFingerprint((string) $media->getCustomProperty('social_avatar_fingerprint'))
                === $this->normalizeAvatarFingerprint($providerAvatarUrl);
    }

    private function supportsAvatarSyncForProvider(string $provider): bool
    {
        return $provider === 'microsoft' || $this->providers->isMasterProvider($provider, true);
    }

    private function redirectToLogin(): RedirectResponse
    {
        return redirect()->to('/login');
    }

    private function prepareAuthenticationLogContext(Request $request, string $provider): void
    {
        $request->attributes->set(self::AUTH_METHOD_ATTRIBUTE, 'socialite');
        $request->attributes->set(self::SOCIAL_PROVIDER_ATTRIBUTE, $provider);
    }

    private function redirectForContext(string $context): RedirectResponse
    {
        return $context === 'login'
            ? $this->redirectToLogin()
            : $this->redirectToConnections();
    }

    private function redirectToConnections(): RedirectResponse
    {
        return redirect()->route($this->tenantAwareRouteName('profile.show'), ['tab' => 'connections']);
    }

    private function handleBrokenMicrosoftConnectionDuringAuthentication(
        Request $request,
        string $provider,
        DecryptException $exception,
    ): RedirectResponse {
        if ($provider === 'microsoft') {
            $this->purgeBrokenMicrosoftAccounts();

            Log::warning('Microsoft sign-in failed because a stored social account token could not be decrypted.', [
                'message' => $exception->getMessage(),
            ]);

            return $this->redirectToLogin()
                ->withErrors([
                    'socialite' => __('core-panel::page-settings.microsoft_reconnect_required'),
                ]);
        }

        throw $exception;
    }

    private function purgeBrokenMicrosoftAccounts(): void
    {
        SocialAccount::query()
            ->where('provider', 'microsoft')
            ->lazyById(column: 'id')
            ->each(function (SocialAccount $account): void {
                try {
                    $account->getAttribute('token_encrypted');
                    $account->getAttribute('refresh_token_encrypted');
                } catch (DecryptException $exception) {
                    $deleted = $account->delete();

                    Log::warning('Broken Microsoft social account was removed during social login cleanup.', [
                        'deleted' => $deleted,
                        'social_account_id' => (string) $account->getKey(),
                        'user_id' => (string) $account->getAttribute('user_id'),
                        'message' => $exception->getMessage(),
                    ]);
                }
            });
    }

    private function tenantAwareRouteName(string $routeName): string
    {
        if (! function_exists('tenancy') || ! tenancy()->initialized) {
            return $routeName;
        }

        return 'tenant.'.$routeName;
    }
}
