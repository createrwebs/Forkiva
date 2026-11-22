<?php

use Modules\Menu\Imports\MenuImportDefinition;
use Modules\Menu\Services\Menu\MenuServiceInterface;
use Modules\Menu\Services\OnlineMenu\OnlineMenuServiceInterface;
use Modules\User\Enums\PermissionAction as Action;

return [
    'permissions' => [
        'menus' => [Action::Index, Action::Show, Action::Create, Action::Edit, Action::Destroy, Action::Export, Action::Import],
        'online_menus' => [Action::Index, Action::Show, Action::Create, Action::Edit, Action::Destroy, Action::Export],
    ],

    'export_resources' => [
        'menus' => MenuServiceInterface::class,
        'online_menus' => OnlineMenuServiceInterface::class,
    ],

    'import_resources' => [
        'menus' => MenuImportDefinition::class,
    ],
];
