<?php

declare(strict_types=1);

namespace CorePanel\Domains\ApiToken\Actions;

use CorePanel\Models\ApiToken;
use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\PersonalAccessTokenResult;

final readonly class CreateApiTokenAction
{
    public function __construct(
        private ClientRepository $clients,
    ) {}

    /**
     * @param  list<string>  $abilities
     * @return array{plainTextToken:string,token:ApiToken}
     */
    public function execute(Authenticatable $user, string $name, array $abilities): array
    {
        $this->ensurePersonalAccessClient($user);
        $newAccessToken = $this->createToken($user, $name, $abilities);
        $token = $newAccessToken->getToken();

        if (! $token instanceof ApiToken) {
            throw new \RuntimeException('Passport token model is not configured for CorePanel.');
        }

        return [
            'plainTextToken' => (string) $newAccessToken->accessToken,
            'token' => $token,
        ];
    }

    private function ensurePersonalAccessClient(Authenticatable $user): void
    {
        if (! method_exists($user, 'getProviderName')) {
            return;
        }

        $provider = $user->getProviderName();

        try {
            $this->clients->personalAccessClient($provider);
        } catch (\RuntimeException) {
            $this->clients->createPersonalAccessGrantClient('CorePanel Personal Access Client', $provider);
        }
    }

    /**
     * @param  list<string>  $abilities
     * @return PersonalAccessTokenResult<mixed>
     */
    private function createToken(Authenticatable $user, string $name, array $abilities): PersonalAccessTokenResult
    {
        if (! is_callable([$user, 'createToken'])) {
            throw new \RuntimeException(sprintf(
                'User model [%s] must support Passport personal access tokens.',
                $user::class
            ));
        }

        $result = $user->createToken($name, $abilities);

        if (! $result instanceof PersonalAccessTokenResult) {
            throw new \RuntimeException(sprintf(
                'User model [%s] returned an invalid Passport personal access token result.',
                $user::class
            ));
        }

        return $result;
    }
}
