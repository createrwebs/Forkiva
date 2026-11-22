<?php

use Modules\SeatingPlan\Imports\FloorImportDefinition;
use Modules\SeatingPlan\Imports\TableImportDefinition;
use Modules\SeatingPlan\Imports\ZoneImportDefinition;
use Modules\SeatingPlan\Services\Floor\FloorServiceInterface;
use Modules\SeatingPlan\Services\Table\TableServiceInterface;
use Modules\SeatingPlan\Services\TableMerge\TableMergeServiceInterface;
use Modules\SeatingPlan\Services\Zone\ZoneServiceInterface;
use Modules\User\Enums\PermissionAction as Action;

return [
    'permissions' => [
        'floors' => [Action::Index, Action::Show, Action::Create, Action::Edit, Action::Destroy, Action::Export, Action::Import],
        'zones' => [Action::Index, Action::Show, Action::Create, Action::Edit, Action::Destroy, Action::Export, Action::Import],
        'tables' => [
            Action::Index,
            Action::Show,
            Action::Create,
            Action::Edit,
            Action::Destroy,
            Action::Export,
            Action::Import,
            Action::Viewer,
            Action::Merge,
            Action::Split,
            Action::UpdateStatus,
            Action::AssignWaiter,
        ],
        'table_merges' => [Action::Index, Action::Show, Action::Export],
    ],

    'export_resources' => [
        'floors' => FloorServiceInterface::class,
        'tables' => TableServiceInterface::class,
        'zones' => ZoneServiceInterface::class,
        'table_merges' => TableMergeServiceInterface::class,
    ],

    'import_resources' => [
        'floors' => FloorImportDefinition::class,
        'zones' => ZoneImportDefinition::class,
        'tables' => TableImportDefinition::class,
    ],
];
