<?php

namespace Modules\Core\Database;

use Illuminate\Support\Facades\DB;

final class DatabaseExpression
{
    /**
     * Cast translated JSON objects to text when selecting a grouped label.
     */
    public static function text(string $column): string
    {
        $wrapped = DB::connection()->getQueryGrammar()->wrap($column);

        return DB::getDriverName() === 'pgsql' ? "CAST({$wrapped} AS text)" : $wrapped;
    }

    /**
     * Compile a trusted JSON field selector with the active connection grammar.
     */
    public static function jsonText(string $column, string $path): string
    {
        return DB::connection()->getQueryGrammar()->wrap($column.'->'.str_replace('.', '->', $path));
    }

    /**
     * PostgreSQL needs explicit numeric casts before aggregating JSON scalars.
     */
    public static function jsonNumber(string $column, string $path): string
    {
        $expression = self::jsonText($column, $path);

        return DB::getDriverName() === 'pgsql' ? "CAST({$expression} AS numeric)" : $expression;
    }

    /**
     * Represent JSON flags as 0/1 for portable comparisons and MAX aggregates.
     */
    public static function jsonFlag(string $column, string $path): string
    {
        if (DB::getDriverName() === 'pgsql') {
            return 'CAST(CAST('.self::jsonText($column, $path).' AS boolean) AS integer)';
        }

        $wrapped = DB::connection()->getQueryGrammar()->wrap($column);

        return "JSON_EXTRACT({$wrapped}, '$.{$path}')";
    }
}
