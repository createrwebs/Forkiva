@php
    $body = '<h1>'.e($title ?? '').'</h1>'.app(\Modules\Notification\Support\NotificationContentSanitizer::class)->html($content);
    $html = view('notification::emails.layout', [
        'emailBody' => $body,
        'title' => $title ?? null,
        'message' => $message ?? null,
    ])->render();
@endphp
{!! (new \TijsVerkoyen\CssToInlineStyles\CssToInlineStyles)->convert($html) !!}
