<x-mail::layout>
<x-slot:header>
{{ app(\Modules\Notification\Support\EmailBranding::class)->name() }}
</x-slot:header>

{{ $slot }}

@isset($subcopy)
<x-slot:subcopy>
{{ $subcopy }}
</x-slot:subcopy>
@endisset

<x-slot:footer>
{{ __('notification::email.automated', ['app' => app(\Modules\Notification\Support\EmailBranding::class)->name()]) }}
© {{ date('Y') }} {{ app(\Modules\Notification\Support\EmailBranding::class)->name() }}. {{ __('notification::email.rights') }}
</x-slot:footer>
</x-mail::layout>
