<?php

use Modules\Product\Imports\ProductImportDefinition;
use Modules\Product\Services\Product\ProductServiceInterface;
use Modules\User\Enums\PermissionAction as Action;

return [
    'permissions' => [
        'products' => [Action::Index, Action::Show, Action::Create, Action::Edit, Action::Destroy, Action::Export, Action::Import],
    ],

    'export_resources' => [
        'products' => ProductServiceInterface::class,
    ],

    'import_resources' => [
        'products' => ProductImportDefinition::class,
    ],
];
