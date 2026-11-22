<?php

use Modules\Invoice\Services\Invoice\InvoiceServiceInterface;
use Modules\User\Enums\PermissionAction as Action;

return [
    'permissions' => [
        'invoices' => [Action::Index, Action::Show, Action::Export],
    ],

    'export_resources' => [
        'invoices' => InvoiceServiceInterface::class,
    ],
];
