<?php

namespace Modules\Discount\Checkers;

use Closure;
use Modules\Discount\Exceptions\DiscountNotExistsException;
use Modules\Discount\Models\Discount;
use Throwable;

class DiscountExists
{
    /**
     * @throws DiscountNotExistsException
     * @throws Throwable
     */
    public function handle(?Discount $discount, Closure $next): mixed
    {
        throw_if(is_null($discount), DiscountNotExistsException::class);

        return $next($discount);
    }
}
