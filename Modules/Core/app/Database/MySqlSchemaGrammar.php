<?php

namespace Modules\Core\Database;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\MySqlGrammar;
use Illuminate\Support\Fluent;

class MySqlSchemaGrammar extends MySqlGrammar
{
    /**
     * Preserve historical migration expressions while allowing cascading foreign keys on MySQL.
     * Indexed virtual columns enforce the same uniqueness without stored-column cascade restrictions.
     */
    protected function modifyStoredAs(Blueprint $blueprint, Fluent $column)
    {
        $expressions = [
            'delivery_driver_assignments' => [
                'current_order_delivery_id' => 'case when `is_current` = 1 then `order_delivery_id` else null end',
                'current_driver_id' => 'case when `is_current` = 1 then `driver_id` else null end',
            ],
            'branch_delivery_provider_settings' => [
                'active_branch_provider_key' => "case when deleted_at is null then concat(branch_id, ':', provider_id) else null end",
            ],
            'delivery_driver_earning_entries' => [
                'per_delivery_unique_key' => 'case when `type` = "per_delivery" and `order_delivery_id` is not null then concat(`order_delivery_id`, "-per_delivery") else null end',
            ],
        ];

        if (isset($expressions[$blueprint->getTable()][$column->name])
            && $expressions[$blueprint->getTable()][$column->name] === $column->storedAs
            && ! $this->connection->isMaria()) {
            $virtual = clone $column;
            $virtual->virtualAs = $column->storedAs;

            return parent::modifyVirtualAs($blueprint, $virtual);
        }

        return parent::modifyStoredAs($blueprint, $column);
    }
}
