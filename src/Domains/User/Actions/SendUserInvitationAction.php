<?php

declare(strict_types=1);

namespace CorePanel\Domains\User\Actions;

use CorePanel\Mail\UserInvitationMail;
use CorePanel\Support\Users\UserModelManager;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

final readonly class SendUserInvitationAction
{
    public function __construct(
        private UserModelManager $users,
    ) {}

    public function execute(Model $user): void
    {
        if (! $user instanceof Authenticatable || ! $user instanceof CanResetPassword) {
            throw new \InvalidArgumentException('Invitation mail targets must implement Authenticatable and CanResetPassword.');
        }

        $attributes = [];

        if ($this->users->hasColumn('requires_password_setup')) {
            $attributes['requires_password_setup'] = true;
        }

        if ($this->users->hasColumn('invited_at')) {
            $attributes['invited_at'] = now();
        }

        if ($this->users->hasColumn('invitation_accepted_at')) {
            $attributes['invitation_accepted_at'] = null;
        }

        if ($attributes !== []) {
            $user->forceFill($attributes)->save();
        }

        /** @var PasswordBroker $passwordBroker */
        $passwordBroker = Password::broker();

        $token = $passwordBroker->createToken($user);
        $invitationUrl = route('password.reset', [
            'context' => 'invitation',
            'token' => $token,
            'email' => (string) $user->getAttribute('email'),
        ]);

        $mailer = Mail::to((string) $user->getAttribute('email'));

        if (method_exists($user, 'preferredLocale')) {
            $preferredLocale = $user->preferredLocale();

            if (is_string($preferredLocale) && $preferredLocale !== '') {
                $mailer->locale($preferredLocale);
            }
        }

        $mailer->send(new UserInvitationMail($user, $invitationUrl));
    }
}
