<?php

declare(strict_types=1);

namespace CorePanel\Support\Socialite;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\ProviderInterface;
use Laravel\Socialite\Two\User;
use RuntimeException;

final class OidcProvider extends AbstractProvider implements ProviderInterface
{
    protected $scopeSeparator = ' ';

    /** @var list<string> */
    protected $scopes = ['openid', 'profile', 'email'];

    /** @var array<string, string> */
    private array $claims = [
        'avatar' => 'picture',
        'email' => 'email',
        'id' => 'sub',
        'name' => 'name',
        'nickname' => 'preferred_username',
    ];

    private string $issuer = '';

    /** @param array<string, mixed> $claims */
    public function configure(string $issuer, array $claims = []): self
    {
        $this->issuer = rtrim($issuer, '/');
        $this->claims = array_replace($this->claims, array_filter($claims, 'is_string'));

        return $this;
    }

    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase($this->discovery()['authorization_endpoint'], $state);
    }

    protected function getTokenUrl(): string
    {
        return $this->discovery()['token_endpoint'];
    }

    /** @return array<string, mixed> */
    protected function getUserByToken($token): array
    {
        $endpoint = $this->discovery()['userinfo_endpoint'] ?? null;

        if (! is_string($endpoint) || $endpoint === '') {
            throw new RuntimeException('The OIDC discovery document does not contain a userinfo endpoint.');
        }

        $user = Http::acceptJson()
            ->connectTimeout(3)
            ->timeout(10)
            ->withToken($token)
            ->get($endpoint)
            ->throw()
            ->json();

        if (! is_array($user)) {
            throw new RuntimeException('The OIDC userinfo response is invalid.');
        }

        return $user;
    }

    /** @param array<string, mixed> $user */
    protected function mapUserToObject(array $user): User
    {
        $avatar = Arr::get($user, $this->claims['avatar']);
        $subject = Arr::get($user, $this->claims['id']);

        if (! is_string($subject) || trim($subject) === '') {
            throw new RuntimeException('The OIDC userinfo response does not contain a valid subject claim.');
        }

        return (new User)->setRaw($user)->map([
            'avatar' => is_string($avatar) ? $avatar : null,
            'avatar_original' => is_string($avatar) ? $avatar : null,
            'email' => Arr::get($user, $this->claims['email']),
            'id' => $subject,
            'name' => Arr::get($user, $this->claims['name']),
            'nickname' => Arr::get($user, $this->claims['nickname']),
        ]);
    }

    /** @return array{authorization_endpoint: string, token_endpoint: string, userinfo_endpoint?: string} */
    private function discovery(): array
    {
        $issuer = $this->issuer;

        if (filter_var($issuer, FILTER_VALIDATE_URL) === false || ! $this->isSecureUrl($issuer)) {
            throw new RuntimeException('A valid HTTPS OIDC issuer URL is required.');
        }

        /** @var array{authorization_endpoint: string, token_endpoint: string, userinfo_endpoint?: string} $discovery */
        $discovery = Cache::remember('core-panel:oidc:discovery:'.hash('sha256', $issuer), now()->addHour(), function () use ($issuer): array {
            $document = Http::acceptJson()
                ->connectTimeout(3)
                ->timeout(10)
                ->get($issuer.'/.well-known/openid-configuration')
                ->throw()
                ->json();

            if (! is_array($document)
                || ! is_string($document['authorization_endpoint'] ?? null)
                || ! is_string($document['token_endpoint'] ?? null)
                || ! $this->isSecureUrl($document['authorization_endpoint'])
                || ! $this->isSecureUrl($document['token_endpoint'])
                || (array_key_exists('userinfo_endpoint', $document)
                    && (! is_string($document['userinfo_endpoint']) || ! $this->isSecureUrl($document['userinfo_endpoint'])))
                || rtrim((string) ($document['issuer'] ?? ''), '/') !== rtrim($issuer, '/')
            ) {
                throw new RuntimeException('The OIDC discovery document is invalid, insecure, or belongs to another issuer.');
            }

            return Arr::only($document, ['authorization_endpoint', 'token_endpoint', 'userinfo_endpoint']);
        });

        return $discovery;
    }

    private function isSecureUrl(string $url): bool
    {
        $parts = parse_url($url);

        return is_array($parts)
            && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && is_string($parts['host'] ?? null)
            && $parts['host'] !== '';
    }
}
