<?php

use Modules\Discount\Services\Discount\DiscountServiceInterface;
use Modules\User\Enums\PermissionAction as Action;

return [
    'permissions' => [
        'discounts' => [Action::Index, Action::Show, Action::Create, Action::Edit, Action::Destroy, Action::Export],
    ],

    'export_resources' => [
        'discounts' => DiscountServiceInterface::class,
    ],
];
