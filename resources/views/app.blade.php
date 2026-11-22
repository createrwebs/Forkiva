@php use Modules\Media\Models\Media; @endphp
    <!doctype html>
<html lang="{{ setting('default_locale', config('app.locale', 'en')) }}"
      dir="{{ isRtl(setting('default_locale', config('app.locale', 'en'))) ? 'rtl' : 'ltr' }}"
      data-loader-theme="{{ setting('default_theme_mode', 'light') }}">
<head>
    <meta charset="UTF-8"/>
    <link
        href="{{ Media::getCacheMedia(setting('favicon'))?->url ?: asset('favicon.ico') }}"
        rel="icon"
    />
    <meta
        content="width=device-width, initial-scale=1.0"
        name="viewport"
    />
    <meta
        content="#ffffff"
        name="theme-color"
    />
    <meta
        content="yes"
        name="mobile-web-app-capable"
    />
    <meta
        content="yes"
        name="apple-mobile-web-app-capable"
    />
    <link
        href="{{ Media::getCacheMedia(setting('logo'))?->url ?: asset('logo.png') }}"
        rel="apple-touch-icon"
    />
    <title>{{config('app.name')}} – Restaurant POS</title>
    <script>
        // Read the same preference as appStore before the first loader paint.
        (() => {
            const root = document.documentElement;
            const supportedLocales = {{ \Illuminate\Support\Js::from(supportedLocaleKeys()) }};
            let mode = root.dataset.loaderTheme;

            try {
                const savedMode = localStorage.getItem('theme_mode');
                if (['light', 'dark', 'system'].includes(savedMode)) {
                    mode = savedMode;
                }
                const savedLocale = localStorage.getItem('current_locale');
                if (supportedLocales.includes(savedLocale)) {
                    root.lang = savedLocale;
                    root.dir = savedLocale === 'ar' || savedLocale.startsWith('ar-') ? 'rtl' : 'ltr';
                }
            } catch {
                // Storage may be unavailable; retain the restaurant default.
            }

            root.dataset.loaderTheme = ['light', 'dark', 'system'].includes(mode) ? mode : 'light';
        })();
    </script>
    <link
        href="{{ asset('loader.css') }}"
        rel="stylesheet"
        type="text/css"
    />
</head>
<body>
<div id="app">
    <div
        id="loading-bg"
        aria-live="polite"
        aria-labelledby="kitchen-loader-caption"
        role="status"
    >
        <div class="kitchen-loader">
            <div class="kitchen-loader__scene" aria-hidden="true">
                <svg
                    class="kitchen-loader__illustration"
                    viewBox="0 0 280 220"
                    fill="none"
                    focusable="false"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <defs>
                        <linearGradient id="kitchen-pan-finish" x1="132" y1="124" x2="132" y2="167"
                                        gradientUnits="userSpaceOnUse">
                            <stop class="kitchen-loader__finish-top"/>
                            <stop class="kitchen-loader__finish-bottom" offset="1"/>
                        </linearGradient>
                    </defs>
                    <g class="kitchen-loader__steam">
                        <path d="M108 86c-13-16 12-21 0-38"/>
                        <path d="M135 75c-13-16 12-21 0-38"/>
                        <path d="M162 86c-13-16 12-21 0-38"/>
                    </g>
                    <g class="kitchen-loader__toss">
                        <path class="kitchen-loader__leaf" d="M100 106c-1-13 7-20 19-18-1 12-8 19-19 18Z"/>
                        <path class="kitchen-loader__pepper" d="m138 93 10-5 6 11-10 5Z"/>
                        <circle class="kitchen-loader__seasoning" cx="171" cy="106" r="4"/>
                    </g>
                    <g class="kitchen-loader__pan">
                        <path class="kitchen-loader__handle" d="m189 126 53-22"/>
                        <path class="kitchen-loader__bowl" d="M64 124h136c-7 31-31 43-68 43s-61-12-68-43Z"/>
                        <path class="kitchen-loader__rim" d="M64 124h136"/>
                        <path class="kitchen-loader__shine" d="M85 138c9 11 22 16 40 17"/>
                    </g>
                    <g class="kitchen-loader__heat">
                        <path d="M113 193c-7-8 3-11 1-18 9 8 6 14-1 18Z"/>
                        <path d="M132 198c-10-11 4-15 1-26 12 11 9 21-1 26Z"/>
                        <path d="M151 193c-7-8 3-11 1-18 9 8 6 14-1 18Z"/>
                    </g>
                </svg>
            </div>
            <p class="kitchen-loader__brand">{{ config('app.name') }}</p>
            <p id="kitchen-loader-caption" class="kitchen-loader__caption">
                <span class="kitchen-loader__copy-en" lang="en">{{ __('loader.preparing', [], 'en') }}</span>
                <span class="kitchen-loader__copy-ar" lang="ar" dir="rtl">{{ __('loader.preparing', [], 'ar') }}</span>
            </p>
            <div class="kitchen-loader__progress" aria-hidden="true"><span></span></div>
        </div>
    </div>
</div>
@vite('resources/js/app.ts')
</body>
</html>
