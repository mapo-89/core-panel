<?php

declare(strict_types=1);

namespace CorePanel\Domains\User\DTOs;

use CorePanel\Support\Users\UserModelManager;
use Illuminate\Database\Eloquent\Model;

final readonly class UserData
{
    /**
     * @param  list<string>  $roles
     * @param  list<array{id:string,color:string,name:string}>  $userGroups
     */
    public function __construct(
        public string $id,
        public string $firstName,
        public string $lastName,
        public string $name,
        public string $email,
        public ?string $locale,
        public string $status,
        public ?string $avatarUrl,
        public ?int $presenceLastSeenAt,
        public string $presenceStatus,
        public array $roles,
        public array $userGroups,
        public bool $twoFactorEnabled,
        public ?string $createdAt,
        public ?string $emailVerifiedAt,
        public ?string $deletedAt,
        public ?string $invitationAcceptedAt,
        public ?string $invitationExpiresAt,
        public ?string $invitationSentAt,
        public string $invitationStatus,
        public bool $requiresPasswordSetup,
    ) {}

    /**
     * @return array{
     *     id:string,
     *     firstName:string,
     *     lastName:string,
     *     name:string,
     *     email:string,
     *     locale:?string,
     *     status:string,
     *     avatarUrl:?string,
     *     presenceLastSeenAt:?int,
     *     presenceStatus:string,
     *     roles:list<string>,
     *     userGroups:list<array{id:string,color:string,name:string}>,
     *     twoFactorEnabled:bool,
     *     createdAt:?string,
     *     emailVerifiedAt:?string,
     *     deletedAt:?string,
     *     invitationAcceptedAt:?string,
     *     invitationExpiresAt:?string,
     *     invitationSentAt:?string,
     *     invitationStatus:string,
     *     requiresPasswordSetup:bool
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'name' => $this->name,
            'email' => $this->email,
            'locale' => $this->locale,
            'status' => $this->status,
            'avatarUrl' => $this->avatarUrl,
            'presenceLastSeenAt' => $this->presenceLastSeenAt,
            'presenceStatus' => $this->presenceStatus,
            'roles' => $this->roles,
            'userGroups' => $this->userGroups,
            'twoFactorEnabled' => $this->twoFactorEnabled,
            'createdAt' => $this->createdAt,
            'emailVerifiedAt' => $this->emailVerifiedAt,
            'deletedAt' => $this->deletedAt,
            'invitationAcceptedAt' => $this->invitationAcceptedAt,
            'invitationExpiresAt' => $this->invitationExpiresAt,
            'invitationSentAt' => $this->invitationSentAt,
            'invitationStatus' => $this->invitationStatus,
            'requiresPasswordSetup' => $this->requiresPasswordSetup,
        ];
    }

    public static function fromModel(Model $user, UserModelManager $users): self
    {
        /** @var list<array{id:string,color:string,name:string}> $userGroups */
        $userGroups = $user->relationLoaded('userGroups')
            ? $user->getRelation('userGroups')
                ->map(static function (Model $userGroup): array {
                    return [
                        'id' => (string) $userGroup->getKey(),
                        'color' => (string) ($userGroup->getAttribute('color') ?: '#6366F1'),
                        'name' => (string) $userGroup->getAttribute('name'),
                    ];
                })
                ->values()
                ->all()
            : [];

        $createdAt = $user->getAttribute('created_at');
        $emailVerifiedAt = $user->getAttribute('email_verified_at');
        $deletedAt = $user->getAttribute('deleted_at');
        $invitationAcceptedAt = $user->getAttribute('invitation_accepted_at');
        $invitationExpiresAt = method_exists($user, 'invitationExpiresAt') ? $user->invitationExpiresAt() : null;
        $invitationSentAt = $user->getAttribute('invited_at');
        $invitationStatus = method_exists($user, 'invitationStatus') ? $user->invitationStatus() : 'none';
        $firstName = (string) ($user->getAttribute('first_name') ?? '');
        $lastName = (string) ($user->getAttribute('last_name') ?? '');
        $name = $users->composeDisplayName($firstName, $lastName);
        $twoFactorEnabled = $users->supportsTwoFactor($user) && method_exists($user, 'hasEnabledTwoFactorAuthentication')
            ? (bool) $user->hasEnabledTwoFactorAuthentication()
            : false;

        return new self(
            id: (string) $user->getKey(),
            firstName: $firstName,
            lastName: $lastName,
            name: $name,
            email: (string) $user->getAttribute('email'),
            locale: $users->supportsLocale() ? (is_string($user->getAttribute('locale')) ? $user->getAttribute('locale') : null) : null,
            status: $users->status($user),
            avatarUrl: $users->avatarUrl($user),
            presenceLastSeenAt: $users->presenceLastSeenAt($user),
            presenceStatus: $users->presenceStatus($user),
            roles: $users->roleNames($user),
            userGroups: $userGroups,
            twoFactorEnabled: $twoFactorEnabled,
            createdAt: $createdAt instanceof \DateTimeInterface ? $createdAt->format(DATE_ATOM) : null,
            emailVerifiedAt: $emailVerifiedAt instanceof \DateTimeInterface ? $emailVerifiedAt->format(DATE_ATOM) : null,
            deletedAt: $deletedAt instanceof \DateTimeInterface ? $deletedAt->format(DATE_ATOM) : null,
            invitationAcceptedAt: $invitationAcceptedAt instanceof \DateTimeInterface ? $invitationAcceptedAt->format(DATE_ATOM) : null,
            invitationExpiresAt: $invitationExpiresAt instanceof \DateTimeInterface ? $invitationExpiresAt->format(DATE_ATOM) : null,
            invitationSentAt: $invitationSentAt instanceof \DateTimeInterface ? $invitationSentAt->format(DATE_ATOM) : null,
            invitationStatus: is_string($invitationStatus) ? $invitationStatus : 'none',
            requiresPasswordSetup: method_exists($user, 'requiresPasswordSetup') ? (bool) $user->requiresPasswordSetup() : false,
        );
    }
}
