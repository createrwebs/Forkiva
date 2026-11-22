<?php

use Modules\Branch\Services\Branch\BranchServiceInterface;
use Modules\User\Enums\PermissionAction as Action;

return [
    'permissions' => [
        'branches' => [Action::Index, Action::Show, Action::Create, Action::Edit, Action::Destroy, Action::Export],
    ],

    'export_resources' => [
        'branches' => BranchServiceInterface::class,
    ],
];
