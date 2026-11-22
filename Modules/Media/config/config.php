<?php

use Modules\User\Enums\PermissionAction as Action;

return [
    'download_url_ttl_minutes' => (int) env('MEDIA_DOWNLOAD_URL_TTL_MINUTES', 10),

    'permissions' => [
        'media' => [Action::Index, Action::Create, Action::Edit, Action::Destroy],
    ],
];
