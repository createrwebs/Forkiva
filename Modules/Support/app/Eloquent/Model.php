<?php

namespace Modules\Support\Eloquent;

use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Query\Builder as QueryBuilder;

abstract class Model extends Eloquent
{
    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param  QueryBuilder  $query
     * @return Builder
     */
    public function newEloquentBuilder($query)
    {
        return new Builder($query);
    }
}
