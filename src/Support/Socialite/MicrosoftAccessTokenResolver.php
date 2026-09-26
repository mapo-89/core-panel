<?php

declare(strict_types=1);

namespace CorePanel\Support\Socialite;

use CorePanel\Models\SocialAccount;
use CorePanel\Support\Settings\SettingsRepository;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class MicrosoftAccessTokenResolver
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly SocialAccountStore $accounts,
    ) {}

    /**
     * @return array{message:string, scopes:list<string>, token:?string}
     */
    public function forUser(Authenticatable $user): array
    {
        $account = $this->accounts->forUserAndProvider($user, 'microsoft');

        if (! $account instanceof SocialAccount) {
            return [
                'message' => __('core-panel::page-settings.microsoft_disconnected_hint'),
                'scopes' => [],
                'token' => null,
            ];
        }

        return $this->forAccount($account);
    }

    /**
     * @return array{message:string, scopes:list<string>, token:?string}
     */
    public function forAccount(SocialAccount $account): array
    {
        if (! $this->isMicrosoftConfigured()) {
            return [
                'message' => __('core-panel::microsoft.not_configured'),
                'scopes' => [],
                'token' => null,
            ];
        }

        try {
            $token = $this->normalizeString($account->getAttribute('token_encrypted'));
            $refreshToken = $this->normalizeString(
                $account->getAttribute('refresh_token_encrypted'),
            );
        } catch (DecryptException $exception) {
            $accountDeleted = false;

            if ($account->exists) {
                $accountDeleted = (bool) $account->delete();
            }

            Log::warning('Microsoft social account token could not be decrypted.', [
                'account_deleted' => $accountDeleted,
                'provider' => $account->getAttribute('provider'),
                'social_account_id' => $this->socialAccountKey($account),
                'user_id' => $this->normalizeString($account->getAttribute('user_id')),
                'message' => $exception->getMessage(),
            ]);

            return [
                'message' => __('core-panel::page-settings.microsoft_reconnect_required'),
                'scopes' => [],
                'token' => null,
            ];
        }

        if (
            $token !== null
            && $this->expiresAt($account)?->isFuture() === true
        ) {
            $accessible = $this->canAccessMicrosoftGraph($token);

            if ($accessible === null) {
                return $this->failedResult(false);
            }

            if ($accessible) {
                return [
                    'message' => __('core-panel::microsoft.connection_valid'),
                    'scopes' => $this->extractScopes($token),
                    'token' => $token,
                ];
            }
        }

        if ($refreshToken === null) {
            return [
                'message' => __('core-panel::page-settings.microsoft_reconnect_required'),
                'scopes' => [],
                'token' => null,
            ];
        }

        try {
            $response = Http::asForm()
                ->withOptions($this->transportOptions())
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(10)
                ->post(sprintf(
                    'https://login.microsoftonline.com/%s/oauth2/v2.0/token',
                    $this->microsoftTenant(),
                ), [
                    'client_id' => $this->microsoftClientId(),
                    'client_secret' => $this->microsoftClientSecret(),
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                    'scope' => implode(' ', $this->microsoftScopes()),
                ]);
        } catch (Throwable $exception) {
            $this->logFailure('refresh', exception: $exception);

            return $this->failedResult(false);
        }

        if (! $response->successful()) {
            $this->logFailure('refresh', $response);

            return $this->failedResult(in_array($response->json('error'), ['invalid_grant', 'interaction_required', 'consent_required'], true));
        }

        $responseData = $response->json();
        $responseData = is_array($responseData) ? $responseData : [];
        $refreshedToken = $this->normalizeString($responseData['access_token'] ?? null);

        if ($refreshedToken === null) {
            $this->logFailure('refresh_response', $response);

            return $this->failedResult(false);
        }

        $account->forceFill([
            'expires_at' => isset($responseData['expires_in']) && is_numeric($responseData['expires_in'])
                ? now('UTC')->addSeconds((int) $responseData['expires_in'])->setTimezone(date_default_timezone_get())
                : null,
            'refresh_token_encrypted' => $this->normalizeString($responseData['refresh_token'] ?? null)
                ?? $refreshToken,
            'token_encrypted' => $refreshedToken,
        ]);

        if ($account->exists) {
            $account->save();
        }

        $accessible = $this->canAccessMicrosoftGraph($refreshedToken);

        if ($accessible !== true) {
            return $this->failedResult($accessible === false);
        }

        return [
            'message' => __('core-panel::microsoft.connection_valid'),
            'scopes' => $this->extractScopes($refreshedToken),
            'token' => $refreshedToken,
        ];
    }

    private function isMicrosoftConfigured(): bool
    {
        return $this->microsoftClientId() !== null
            && $this->microsoftClientSecret() !== null;
    }

    private function microsoftClientId(): ?string
    {
        return $this->normalizeString($this->settings->get('auth', 'microsoft_client_id'))
            ?? $this->normalizeString(config('services.microsoft.client_id'));
    }

    private function microsoftClientSecret(): ?string
    {
        return $this->normalizeString($this->settings->get('auth', 'microsoft_client_secret'))
            ?? $this->normalizeString(config('services.microsoft.client_secret'));
    }

    private function microsoftTenant(): string
    {
        return $this->normalizeString($this->settings->get('auth', 'microsoft_tenant'))
            ?? $this->normalizeString(config('services.microsoft.tenant'))
            ?? 'common';
    }

    private function canAccessMicrosoftGraph(string $token): ?bool
    {
        try {
            $response = Http::withToken($token)
                ->withOptions($this->transportOptions())
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(10)
                ->get('https://graph.microsoft.com/v1.0/me?$select=id');
        } catch (Throwable $exception) {
            $this->logFailure('graph', exception: $exception);

            return null;
        }

        if ($response->successful()) {
            return true;
        }

        $this->logFailure('graph', $response);

        return $response->unauthorized() ? false : null;
    }

    /** @return array<string, mixed> */
    private function transportOptions(): array
    {
        $options = config('services.microsoft.guzzle', []);
        $transportOptions = [];

        if (is_array($options)) {
            foreach ($options as $key => $value) {
                if (is_string($key)) {
                    $transportOptions[$key] = $value;
                }
            }
        }

        return $transportOptions;
    }

    /** @return array{message:string, scopes:list<string>, token:?string} */
    private function failedResult(bool $reconnect): array
    {
        return [
            'message' => __($reconnect ? 'core-panel::page-settings.microsoft_reconnect_required' : 'core-panel::microsoft.connection_unavailable'),
            'scopes' => [],
            'token' => null,
        ];
    }

    private function logFailure(string $phase, ?Response $response = null, ?Throwable $exception = null): void
    {
        $error = $response?->json('error');
        $description = $response?->json('error_description');
        $context = [
            'phase' => $phase,
            'http_status' => $response?->status(),
            'exception' => $exception === null ? null : $exception::class,
        ];

        if (is_string($error) && in_array($error, ['invalid_grant', 'invalid_scope', 'invalid_client', 'invalid_request', 'interaction_required', 'consent_required', 'temporarily_unavailable', 'server_error'], true)) {
            $context['oauth_error'] = $error;
        }

        if (is_string($description) && preg_match('/\bAADSTS\d+\b/', $description, $match) === 1) {
            $context['aadsts_code'] = $match[0];
        }

        Log::warning('Microsoft token resolution failed.', $context);
    }

    private function expiresAt(SocialAccount $account): ?Carbon
    {
        $expiresAt = $account->getAttribute('expires_at');

        return $expiresAt instanceof Carbon ? $expiresAt : null;
    }

    /**
     * @return list<string>
     */
    private function microsoftScopes(): array
    {
        $scopes = config('core-panel.auth.socialite.providers.microsoft.scopes', []);

        if (! is_array($scopes)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn (mixed $scope): ?string => $this->normalizeString($scope), $scopes),
            static fn (?string $scope): bool => $scope !== null,
        ));
    }

    private function socialAccountKey(SocialAccount $account): string
    {
        $key = $account->getKey();

        return is_string($key) ? $key : '';
    }

    /**
     * @return list<string>
     */
    private function extractScopes(string $token): array
    {
        $segments = explode('.', $token);

        if (count($segments) < 2) {
            return [];
        }

        $payload = $this->decodeBase64Url($segments[1]);

        if ($payload === null) {
            return [];
        }

        $decodedPayload = json_decode($payload, true);

        if (! is_array($decodedPayload)) {
            return [];
        }

        $scopeString = $this->normalizeString($decodedPayload['scp'] ?? null)
            ?? $this->normalizeString($decodedPayload['scope'] ?? null);

        if ($scopeString === null) {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', preg_split('/\s+/', $scopeString) ?: []),
            static fn (string $scope): bool => $scope !== '',
        ));
    }

    private function decodeBase64Url(string $value): ?string
    {
        $padded = strtr($value, '-_', '+/');
        $padding = strlen($padded) % 4;

        if ($padding > 0) {
            $padded .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($padded, true);

        return $decoded === false ? null : $decoded;
    }

    private function normalizeString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }
}
