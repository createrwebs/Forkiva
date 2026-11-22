<?php

use Modules\Payment\Services\Payment\PaymentServiceInterface;
use Modules\User\Enums\PermissionAction as Action;

return [
    'permissions' => [
        'payments' => [Action::Index, Action::Show, Action::Export],
    ],

    'export_resources' => [
        'payments' => PaymentServiceInterface::class,
    ],
];
