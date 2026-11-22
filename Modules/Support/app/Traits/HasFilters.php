<?php

namespace Modules\Support\Traits;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Modules\Printer\Models\Printer;
use Modules\Support\DateRange;
use Modules\Support\Enums\GroupDateType;

/**
 * @method static Builder whereFromDate(string $date, Expression|string $column = "created_at")
 * @method static Builder whereToDate(string $date, Expression|string $column = "created_at")
 * @method static Builder filters(array $filters)
 */
trait HasFilters
{
    use HasLikeFilters;

    /**
     * Scope a query Filters
     */
    public function scopeFilters(Builder $query, array $filters): void
    {
        $allowFilters = $this->allowedFilterKeys();

        foreach ($filters as $key => $filter) {
            if ((! is_null($allowFilters) && ! in_array($key, $allowFilters)) || ($key !== 'is_active' && $key !== 'built_in' && is_null($filter))) {
                continue;
            }

            if ($key === 'role' && $this->getMorphClass() !== Printer::class) {
                $filter = (int) $filter;
            }

            $studlyKey = str($key)->studly()->toString();
            $method = in_array(strtolower($studlyKey), ['from', 'to']) ? "Where{$studlyKey}Date" : "Where$studlyKey";

            if (method_exists($this, "scope$studlyKey")) {
                $query->{$studlyKey}($filter);
            } elseif (method_exists($this, "scope$method")) {
                $query->{$method}($filter);
            } else {
                $query->where($key, $filter);
            }
        }
    }

    /**
     * Get default allowed filters
     */
    public function allowedFilterKeys(): array
    {
        return [];
    }

    /**
     * Scope a query to get from date.
     */
    public function scopeWhereFromDate(Builder $query, string $date, Expression|string $column = 'created_at'): void
    {
        $column = self::$defaultDateColumn ?? $column;
        $query->where(is_string($column) ? $query->qualifyColumn($column) : $column, '>=', DateRange::start($date));
    }

    /**
     * Scope a query to get to date
     */
    public function scopeWhereToDate(Builder $query, string $date, Expression|string $column = 'created_at'): void
    {
        $column = self::$defaultDateColumn ?? $column;
        $query->where(is_string($column) ? $query->qualifyColumn($column) : $column, '<', DateRange::endExclusive($date));
    }

    /**
     * Scope a query to get updated date.
     */
    public function scopeWhereUpdatedFromDate(Builder $query, string $date, Expression|string $column = 'updated_at'): void
    {
        $query->where($column, '>=', DateRange::start($date));
    }

    /**
     * Scope a query to get updated date
     */
    public function scopeWhereUpdatedToDate(Builder $query, string $date, Expression|string $column = 'updated_at'): void
    {
        $query->where($column, '<', DateRange::endExclusive($date));
    }

    /**
     * Group by date
     */
    public function scopeGroupByDate(Builder $query, string $group): void
    {
        if (in_array($group, GroupDateType::values())) {
            $query->{"groupBy{$group}"}();
        }
    }

    /**
     * Group by years
     */
    public function scopeGroupByYears(Builder $query): void
    {
        $query->groupAndOrderBy('YEAR');
    }

    /**
     * Group and order by
     */
    public function scopeGroupAndOrderBy(Builder $query, string $part): void
    {
        $dateColumn = $query->qualifyColumn(self::$defaultDateColumn ?? 'created_at');
        $alias = strtolower($part);
        $expression = "EXTRACT($part FROM $dateColumn)";
        if ($query->getConnection()->getDriverName() === 'sqlite') {
            $format = match ($part) {
                'YEAR' => '%Y',
                'MONTH' => '%m',
                'WEEK' => '%W',
                'DAY' => '%d',
            };
            $expression = "CAST(strftime('$format', $dateColumn) AS INTEGER)";
        }
        $query->selectRaw("$expression as $alias")
            ->groupByRaw($expression)
            ->orderbyDesc($alias);
    }

    /**
     * Group by months
     */
    public function scopeGroupByMonths(Builder $query): void
    {
        $query->groupAndOrderBy('YEAR')->groupAndOrderBy('MONTH');
    }

    /**
     * Group by weeks
     */
    public function scopeGroupByWeeks(Builder $query): void
    {
        $query->groupAndOrderBy('YEAR')->groupAndOrderBy('MONTH')->groupAndOrderBy('WEEK');
    }

    /**
     * Group by days
     */
    public function scopeGroupByDays(Builder $query): void
    {
        $query->groupAndOrderBy('YEAR')
            ->groupAndOrderBy('MONTH')
            ->groupAndOrderBy('WEEK')
            ->groupAndOrderBy('DAY');
    }
}
