<?php

return [
    'enabled' => (bool) env('PASSKEYS_ENABLED', true),
    'relying_party_id' => env(
        'PASSKEYS_RELYING_PARTY_ID',
        parse_url(config('app.url'), PHP_URL_HOST)
    ),
    'allowed_origins' => array_values(
        array_filter(
            array_map(
                'trim',
                explode(',', env('PASSKEYS_ALLOWED_ORIGINS', config('app.url')))
            )
        )
    ),
    'user_handle_secret' => env('PASSKEYS_USER_HANDLE_SECRET') ?: config('app.key'),
    'cache_store' => env(
        'PASSKEYS_CACHE_STORE',
        in_array(config('cache.default'), ['array', 'null'], true)
            ? 'file'
            : config('cache.default')
    ),
    'timeout' => (int) env('PASSKEYS_TIMEOUT', 60000),
];
