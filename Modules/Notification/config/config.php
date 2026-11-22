<?php

use Modules\User\Enums\PermissionAction as Action;

return [
    'permissions' => [
        'notification_workflows' => [Action::Index, Action::Edit],
        'notification_templates' => [Action::Index, Action::Show, Action::Edit],
    ],
];
