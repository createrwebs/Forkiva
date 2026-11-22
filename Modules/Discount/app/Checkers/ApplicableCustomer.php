<?php

namespace Modules\Discount\Checkers;

use Closure;
use Modules\Discount\Exceptions\InapplicableDiscountException;
use Modules\Discount\Models\Discount;
use Throwable;

class ApplicableCustomer
{
    /**
     * @throws Throwable
     * @throws InapplicableDiscountException
     */
    public function handle(Discount $discount, Closure $next, string $cart): mixed
    {
        if (empty($discount->meta['customer_id'])) {
            return $next($discount);
        }

        throw_if(
            (int) $discount->meta['customer_id'] !== (int) resolve($cart)::customer()?->id(),
            InapplicableDiscountException::class
        );

        return $next($discount);
    }
}
