<?php

namespace Modules\Voucher\Checkers;

use Closure;
use Modules\Discount\Exceptions\InapplicableDiscountException;
use Modules\Voucher\Models\Voucher;
use Throwable;

class ApplicableCustomer
{
    /**
     * @throws Throwable
     * @throws InapplicableDiscountException
     */
    public function handle(Voucher $voucher, Closure $next, string $cart): mixed
    {
        if (empty($voucher->meta['customer_id'])) {
            return $next($voucher);
        }

        throw_if(
            (int) $voucher->meta['customer_id'] !== (int) resolve($cart)::customer()?->id(),
            InapplicableDiscountException::class
        );

        return $next($voucher);
    }
}
