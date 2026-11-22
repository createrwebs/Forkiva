<?php

namespace Modules\Core\Database;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\PostgresGrammar;
use Illuminate\Support\Fluent;

class PostgresSchemaGrammar extends PostgresGrammar
{
    /**
     * JSONB supports equality/grouping used by translated model attributes.
     */
    protected function typeJson(Fluent $column)
    {
        return 'jsonb';
    }

    /**
     * Translate the fixed generated expressions in pre-PostgreSQL migrations.
     * Exact matches deliberately avoid rewriting arbitrary application SQL.
     */
    protected function modifyStoredAs(Blueprint $blueprint, Fluent $column)
    {
        $expressions = [
            'case when `is_active` = 1 then `zone_id` else null end' => 'case when is_active then zone_id else null end',
            'case when `is_current` = 1 then `order_delivery_id` else null end' => 'case when is_current then order_delivery_id else null end',
            'case when `is_current` = 1 then `driver_id` else null end' => 'case when is_current then driver_id else null end',
            'case when `type` = "per_delivery" and `order_delivery_id` is not null then concat(`order_delivery_id`, "-per_delivery") else null end' => "case when type = 'per_delivery' and order_delivery_id is not null then order_delivery_id::text || '-per_delivery' else null end",
            "case when deleted_at is null then concat(branch_id, ':', provider_id) else null end" => "case when deleted_at is null then branch_id::text || ':' || provider_id::text else null end",
        ];
        $translated = clone $column;
        if (is_string($column->storedAs) && isset($expressions[$column->storedAs])) {
            $translated->storedAs = $expressions[$column->storedAs];
        }

        return parent::modifyStoredAs($blueprint, $translated);
    }

    /**
     * PostgreSQL represents Laravel enums with CHECK constraints. Changing an
     * enum must replace that constraint separately from changing its SQL type.
     * This also allows existing migration history to run on a fresh database.
     *
     * @return string|string[]
     */
    public function compileChange(Blueprint $blueprint, Fluent $command)
    {
        if ($command->column->type !== 'enum') {
            $sql = parent::compileChange($blueprint, $command);
            if (in_array($command->column->type, ['json', 'jsonb'], true)) {
                $type = $this->getType($command->column);
                $column = $this->wrap($command->column);
                $sql = str_replace("alter column {$column} type {$type}", "alter column {$column} type {$type} using {$column}::{$type}", $sql);
            }

            return $sql;
        }

        $column = clone $command->column;
        $change = clone $command;
        $change->column = $column;
        $column->type = 'string';
        $column->length = 255;
        $table = $this->wrapTable($blueprint);
        $constraint = $this->wrap($this->connection->getTablePrefix().$blueprint->getTable().'_'.$column->name.'_check');

        return [
            "alter table {$table} drop constraint if exists {$constraint}",
            parent::compileChange($blueprint, $change),
            "alter table {$table} add constraint {$constraint} check (".$this->wrap($column).' in ('.$this->quoteString($command->column->allowed).'))',
        ];
    }
}
