{{ __('page-users.invitation_mail.subject', ['app' => config('app.name')]) }}

{{ __('page-users.invitation_mail.greeting', ['name' => $user->getAttribute('first_name') ?: $user->getAttribute('email')]) }}

{{ __('page-users.invitation_mail.intro', ['app' => config('app.name')]) }}

{{ __('page-users.invitation_mail.instructions') }}

{{ __('page-users.invitation_mail.action') }}: {{ $invitationUrl }}

{{ __('page-users.invitation_mail.outro') }}

{{ __('page-users.invitation_mail.expiry', ['count' => (int) config('auth.passwords.users.expire', 60)]) }}

{{ __('account-mail.salutation') }}
{{ config('app.name') }}

{{ __('account-mail.subcopy', ['actionText' => __('page-users.invitation_mail.action')]) }}
{{ $invitationUrl }}

{{ __('account-mail.footer') }}
