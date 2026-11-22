<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>Forkiva · Installation</title>
    <link rel="icon" href="/web/logo.png" type="image/png">
    @php
        $installerVite = clone app(\Illuminate\Foundation\Vite::class);
        // Prefer built assets once present, and never trust a development hot file in production.
        if (is_file(public_path('build/manifest.json')) || app()->environment('production')) {
            $installerVite->useHotFile('');
        }
    @endphp
    {{ $installerVite('Modules/Installer/resources/assets/js/main.ts') }}
</head>
<body>
<div id="installer" data-state="{{ json_encode($initialState, JSON_THROW_ON_ERROR) }}"></div>
<noscript>Enable JavaScript to use the Forkiva installer.</noscript>
</body>
</html>
