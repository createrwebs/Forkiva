<?php

use Modules\User\Enums\PermissionAction as Action;

return [
    'name' => 'Service',
    'permissions' => [
        'services' => [Action::Index, Action::Edit],
    ],
];
