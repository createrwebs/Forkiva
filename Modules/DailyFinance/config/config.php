<?php

use Modules\User\Enums\PermissionAction as Action;

return [
    'permissions' => [
        'daily_finance' => [Action::Index],
        'daily_finance_closings' => [Action::Index, Action::Close],
        'daily_finance_handovers' => [Action::Index, Action::Create, Action::Receive, Action::Cancel],
        'daily_finance_settlements' => [Action::Index, Action::Create, Action::Receive, Action::Cancel],
    ],
];
