<?php

declare(strict_types=1);

namespace CorePanel\Domains\SocialAccount\DTOs;

use CorePanel\Models\SocialAccount;

final readonly class SocialAccountData
{
    public function __construct(
        public string $id,
        public string $provider,
        public string $label,
        public ?string $providerEmail,
        public ?string $avatarUrl,
        public ?string $expiresAt,
        public ?string $connectedAt,
    ) {}

    public static function fromModel(SocialAccount $account, string $label): self
    {
        return new self(
            id: (string) $account->getKey(),
            provider: (string) $account->getAttribute('provider'),
            label: $label,
            providerEmail: is_string($account->getAttribute('provider_email')) ? $account->getAttribute('provider_email') : null,
            avatarUrl: is_string($account->getAttribute('avatar_url')) ? $account->getAttribute('avatar_url') : null,
            expiresAt: $account->getAttribute('expires_at')?->toDateTimeString(),
            connectedAt: $account->getAttribute('created_at')?->toDateTimeString(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'avatarUrl' => $this->avatarUrl,
            'connectedAt' => $this->connectedAt,
            'expiresAt' => $this->expiresAt,
            'id' => $this->id,
            'label' => $this->label,
            'provider' => $this->provider,
            'providerEmail' => $this->providerEmail,
        ];
    }
}
