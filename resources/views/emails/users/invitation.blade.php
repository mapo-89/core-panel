<x-mail::message>
# {{ __('page-users.invitation_mail.greeting', ['name' => $user->getAttribute('first_name') ?: $user->getAttribute('email')]) }}

{{ __('page-users.invitation_mail.intro', ['app' => config('app.name')]) }}

{{ __('page-users.invitation_mail.instructions') }}

<x-mail::button :url="$invitationUrl">
{{ __('page-users.invitation_mail.action') }}
</x-mail::button>

{{ __('page-users.invitation_mail.outro') }}

{{ __('page-users.invitation_mail.expiry', ['count' => (int) config('auth.passwords.users.expire', 60)]) }}

{{ __('Regards,') }}<br>
{{ config('app.name') }}
</x-mail::message>
