<x-mail::message :message="$message ?? null" :title="$subject" :category="isset($verificationCode) ? __('notification::email.security') : null">
# {{ $greeting ?: ($subject ?: app(\Modules\Notification\Support\EmailBranding::class)->name()) }}

@foreach ($introLines as $line)
{{ $line }}

@endforeach

@isset($verificationCode)
<x-mail::verification-code :minutes="$expiresInMinutes">
{{ $verificationCode }}
</x-mail::verification-code>
@isset($securityNote)
{{ $securityNote }}

@endisset
@endisset

@isset($actionText)
<x-mail::button :url="$actionUrl" :color="in_array($level, ['success', 'error'], true) ? $level : 'primary'">
{{ $actionText }}
</x-mail::button>
@endisset

@foreach ($outroLines as $line)
{{ $line }}

@endforeach

@if (! empty($salutation))
{{ $salutation }}
@elseif (! isset($verificationCode))
{{ __('notification::email.regards') }}<br>
{{ app(\Modules\Notification\Support\EmailBranding::class)->name() }}
@endif

@isset($actionText)
<x-slot:subcopy>
@lang(
    "If you're having trouble clicking the \":actionText\" button, copy and paste the URL below\n".
    'into your web browser:',
    ['actionText' => $actionText]
) <span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
@endisset
</x-mail::message>
