<?php

declare(strict_types=1);

namespace CorePanel\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class UserInvitationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Authenticatable $user,
        public string $invitationUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('page-users.invitation_mail.subject', [
                'app' => (string) config('app.name'),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'core-panel::emails.users.invitation-html',
            text: 'core-panel::emails.users.invitation-text',
        );
    }
}
