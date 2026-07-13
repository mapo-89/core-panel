<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('page-users.invitation_mail.subject', ['app' => config('app.name')]) }}</title>
</head>
<body style="margin:0; padding:24px; background-color:#f5f5f5; color:#111827; font-family:Arial, sans-serif;">
    <div style="max-width:640px; margin:0 auto; background-color:#ffffff; border:1px solid #e5e7eb; border-radius:12px; overflow:hidden;">
        <div style="padding:32px;">
            <p style="margin:0 0 24px; font-size:24px; font-weight:700;">
                {{ __('page-users.invitation_mail.greeting', ['name' => $user->getAttribute('first_name') ?: $user->getAttribute('email')]) }}
            </p>

            <p style="margin:0 0 16px; font-size:16px; line-height:1.6;">{{ __('page-users.invitation_mail.intro', ['app' => config('app.name')]) }}</p>
            <p style="margin:0 0 16px; font-size:16px; line-height:1.6;">{{ __('page-users.invitation_mail.instructions') }}</p>

            <p style="margin:32px 0;">
                <a href="{{ $invitationUrl }}" style="display:inline-block; padding:14px 22px; border-radius:999px; background-color:#111827; color:#ffffff; font-size:15px; font-weight:700; text-decoration:none;">
                    {{ __('page-users.invitation_mail.action') }}
                </a>
            </p>

            <p style="margin:0 0 16px; font-size:16px; line-height:1.6;">{{ __('page-users.invitation_mail.outro') }}</p>
            <p style="margin:0 0 16px; font-size:16px; line-height:1.6;">{{ __('page-users.invitation_mail.expiry', ['count' => (int) config('auth.passwords.users.expire', 60)]) }}</p>

            <p style="margin:32px 0 0; font-size:16px; line-height:1.6;">
                {{ __('account-mail.salutation') }}<br>
                {{ config('app.name') }}
            </p>

            <div style="margin-top:32px; padding-top:24px; border-top:1px solid #e5e7eb;">
                <p style="margin:0 0 12px; font-size:14px; line-height:1.6;">{{ __('account-mail.subcopy', ['actionText' => __('page-users.invitation_mail.action')]) }}</p>
                <p style="margin:0; font-size:14px; line-height:1.6; word-break:break-all;">
                    <a href="{{ $invitationUrl }}" style="color:#2563eb; text-decoration:none;">{{ $invitationUrl }}</a>
                </p>
            </div>
        </div>

        <div style="padding:20px 32px; background-color:#f9fafb; border-top:1px solid #e5e7eb; color:#6b7280; font-size:12px;">
            {{ __('account-mail.footer') }}
        </div>
    </div>
</body>
</html>
