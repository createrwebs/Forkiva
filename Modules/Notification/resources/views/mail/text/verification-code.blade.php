@props(['minutes'])
{{ __('notification::email.verification_code') }}
{{ trim($slot) }}
{{ __('notification::email.expires', ['minutes' => $minutes]) }}
