<?php

namespace Modules\Discount\Checkers;

use Closure;
use Modules\Discount\Exceptions\InvalidDiscountException;
use Modules\Discount\Models\Discount;
use Throwable;

class ValidDiscount
{
    /**
     * @throws InvalidDiscountException
     * @throws Throwable
     */
    public function handle(Discount $discount, Closure $next): mixed
    {
        throw_if($discount->invalid(), InvalidDiscountException::class);

        return $next($discount);
    }
}
