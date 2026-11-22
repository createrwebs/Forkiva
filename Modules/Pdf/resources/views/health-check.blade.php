<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 4mm; }
        body { font-family: DejaVu Sans, sans-serif; color: #172033; font-size: 12px; }
        .card { border: 1px solid #d7dce5; padding: 12px; }
        .rtl { direction: rtl; text-align: right; }
    </style>
</head>
<body>
<div class="card">
    <strong>{{ __('pdf::settings.health_check.title') }}</strong>
    <p>{{ __('pdf::settings.health_check.driver', ['driver' => $driver]) }}</p>
    <p>{{ __('pdf::settings.health_check.english_sample') }}</p>
    <p class="rtl">{{ __('pdf::settings.health_check.arabic_sample') }}</p>
    <small>{{ $checkedAt }}</small>
</div>
</body>
</html>
