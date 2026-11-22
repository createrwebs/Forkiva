<?php

use Modules\ActivityLog\Services\ActivityLog\ActivityLogServiceInterface;
use Modules\ActivityLog\Services\AuthenticationLog\AuthenticationLogServiceInterface;
use Modules\User\Enums\PermissionAction as Action;

return [
    'permissions' => [
        'activity_logs' => [Action::Index, Action::Show, Action::Export],
        'authentication_logs' => [Action::Index, Action::Export],
    ],

    'export_resources' => [
        'activity_logs' => ActivityLogServiceInterface::class,
        'authentication_logs' => AuthenticationLogServiceInterface::class,
    ],
];
