<?php

use Modules\Option\Services\Option\OptionServiceInterface;
use Modules\User\Enums\PermissionAction as Action;

return [
    'permissions' => [
        'options' => [Action::Index, Action::Show, Action::Create, Action::Edit, Action::Destroy, Action::Export],
    ],

    'export_resources' => [
        'options' => OptionServiceInterface::class,
    ],
];
