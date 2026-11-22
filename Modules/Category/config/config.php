<?php

use Modules\Category\Imports\CategoryImportDefinition;
use Modules\Category\Services\Category\CategoryServiceInterface;
use Modules\User\Enums\PermissionAction as Action;

return [
    'permissions' => [
        'categories' => [Action::Index, Action::Show, Action::Create, Action::Edit, Action::Destroy, Action::Export, Action::Import],
    ],

    'export_resources' => [
        'categories' => CategoryServiceInterface::class,
    ],

    'import_resources' => [
        'categories' => CategoryImportDefinition::class,
    ],
];
