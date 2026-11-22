<?php

use Modules\Order\Services\Order\OrderServiceInterface;
use Modules\Order\Services\Reason\ReasonServiceInterface;
use Modules\User\Enums\PermissionAction as Action;

return [
    'permissions' => [
        'orders' => [
            Action::Index,
            Action::Show,
            Action::Create,
            Action::Edit,
            Action::Export,
            Action::Cancel,
            Action::Refund,
            Action::Active,
            Action::Upcoming,
            Action::ReceivePayment,
            Action::UpdateStatus,
            Action::Financials,
            Action::Print,
        ],
        'reasons' => [Action::Index, Action::Show, Action::Create, Action::Edit, Action::Destroy, Action::Export],
    ],

    'export_resources' => [
        'orders' => OrderServiceInterface::class,
        'reasons' => ReasonServiceInterface::class,
    ],
];
