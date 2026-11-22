{!! app(\Modules\Notification\Support\EmailBranding::class)->name() !!}

{!! $title !!}

{!! trim(html_entity_decode(strip_tags(preg_replace('/<\s*(?:br\s*\/?|\/p|\/li|\/ul|\/ol)\s*>/i', "\n", $content)), ENT_QUOTES | ENT_HTML5, 'UTF-8')) !!}

{!! __('notification::email.automated', ['app' => app(\Modules\Notification\Support\EmailBranding::class)->name()]) !!}
