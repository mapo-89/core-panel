<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        $publicSettings = app(\CorePanel\Support\Settings\SettingsRepository::class)->public();
        $appName = data_get($publicSettings, 'general.app_name');
        $resolvedAppName = is_string($appName) && $appName !== ''
            ? $appName
            : config('app.name', 'CorePanel');
    @endphp
    <title inertia>{{ $resolvedAppName }}</title>
    @vite(['resources/js/app.ts', 'resources/css/app.css'])
    @inertiaHead
</head>
<body class="font-sans antialiased">
    @inertia
</body>
</html>
