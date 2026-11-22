<?php

use Modules\User\Enums\PermissionAction as Action;
use Modules\Voucher\Services\Voucher\VoucherServiceInterface;

return [
    'permissions' => [
        'vouchers' => [Action::Index, Action::Show, Action::Create, Action::Edit, Action::Destroy, Action::Export],
    ],

    'export_resources' => [
        'vouchers' => VoucherServiceInterface::class,
    ],
];
