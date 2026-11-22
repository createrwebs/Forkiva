@php
    $body = \Illuminate\Mail\Markdown::parse($slot)->toHtml();
    if (isset($subcopy)) {
        $body .= '<div class="subcopy">'.\Illuminate\Mail\Markdown::parse($subcopy)->toHtml().'</div>';
    }
@endphp
@include('notification::emails.layout', ['emailBody' => $body, 'message' => $message ?? null])
