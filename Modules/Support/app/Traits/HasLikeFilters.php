<?php

namespace Modules\Support\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * @method static Builder like(string $column, mixed $value, bool $caseSensitive = false, string $boolean = 'and')
 * @method static Builder likePrefix(string $column, mixed $value, bool $caseSensitive = false, string $boolean = 'and')
 * @method static Builder likeSuffix(string $column, mixed $value, bool $caseSensitive = false, string $boolean = 'and')
 * @method static Builder orLike(string $column, mixed $value, bool $caseSensitive = false)
 * @method static Builder orLikePrefix(string $column, mixed $value, bool $caseSensitive = false)
 * @method static Builder orLikeSuffix(string $column, mixed $value, bool $caseSensitive = false)
 */
trait HasLikeFilters
{
    /**
     * Scope a query to search using or like
     */
    public function scopeOrLike(Builder $query, string $column, mixed $value, bool $caseSensitive = false): void
    {
        $this->scopeLike($query, $column, $value, $caseSensitive, 'or');
    }

    /**
     * Scope a query to search using like
     */
    public function scopeLike(Builder $query, string $column, mixed $value, bool $caseSensitive = false, string $boolean = 'and'): void
    {
        $query->whereLike($column, "%$value%", $caseSensitive, $boolean);
    }

    /**
     * Scope a query to search using or like; this function will search by prefix
     */
    public function scopeOrLikePrefix(Builder $query, string $column, mixed $value, bool $caseSensitive = false): void
    {
        $this->scopeLikePrefix($query, $column, $value, $caseSensitive, 'or');
    }

    /**
     * Scope a query to search using like; this function will search by prefix
     */
    public function scopeLikePrefix(Builder $query, string $column, mixed $value, bool $caseSensitive = false, string $boolean = 'and'): void
    {
        $query->whereLike($column, "$value%", $caseSensitive, $boolean);
    }

    /**
     * Scope a query to search using or like; this function will search by suffix
     */
    public function scopeOrLikeSuffix(Builder $query, string $column, mixed $value, bool $caseSensitive = false): void
    {
        $this->scopeLikeSuffix($query, $column, $value, $caseSensitive, 'or');
    }

    /**
     * Scope a query to search using like; this function will search by suffix
     */
    public function scopeLikeSuffix(Builder $query, string $column, mixed $value, bool $caseSensitive = false, string $boolean = 'and'): void
    {
        $query->whereLike($column, "%$value", $caseSensitive, $boolean);
    }
}
