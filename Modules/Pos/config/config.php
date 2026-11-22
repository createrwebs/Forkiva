<?php

use Modules\Pos\Imports\PosRegisterImportDefinition;
use Modules\Pos\Services\PosCashMovement\PosCashMovementServiceInterface;
use Modules\Pos\Services\PosRegister\PosRegisterServiceInterface;
use Modules\Pos\Services\PosSession\PosSessionServiceInterface;
use Modules\User\Enums\PermissionAction as Action;

return [
    'permissions' => [
        'pos' => [Action::Index, Action::KitchenViewer],
        'pos_registers' => [Action::Index, Action::Show, Action::Create, Action::Edit, Action::Destroy, Action::Export, Action::Import],
        'pos_sessions' => [Action::Index, Action::Show, Action::Open, Action::Close, Action::CloseOthers, Action::Export],
        'pos_cash_movements' => [Action::Index, Action::Show, Action::Create, Action::Export],
    ],

    'export_resources' => [
        'pos_registers' => PosRegisterServiceInterface::class,
        'pos_sessions' => PosSessionServiceInterface::class,
        'pos_cash_movements' => PosCashMovementServiceInterface::class,
    ],

    'import_resources' => [
        'pos_registers' => PosRegisterImportDefinition::class,
    ],
];
