<?php

namespace Modules\Order\Facades;

use Illuminate\Support\Facades\Facade;

/** @see \Modules\Order\Pricing\Pricing */
class Pricing extends Facade
{
    /**
     * Resolve the request-scoped calculator from the container.
     */
    protected static function getFacadeAccessor(): string
    {
        return \Modules\Order\Pricing\Pricing::class;
    }
}
