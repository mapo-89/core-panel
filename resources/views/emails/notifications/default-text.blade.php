{{ $subject }}

@if (! empty($greeting))
{{ $greeting }}

@endif
@foreach ($introLines as $line)
{{ $line }}

@endforeach
@if (! empty($actionText) && ! empty($actionUrl))
{{ $actionText }}: {{ $actionUrl }}

@endif
@foreach ($outroLines as $line)
{{ $line }}

@endforeach
@if (! empty($salutation))
{{ $salutation }}
{{ $appName }}

@endif
@if (! empty($actionText) && ! empty($displayableActionUrl))
{{ $subcopy }}
{{ $displayableActionUrl }}

@endif
{{ $footer }}
