<?php

namespace Modules\Voucher\Checkers;

use Closure;
use Modules\Voucher\Exceptions\VoucherAlreadyAppliedException;
use Modules\Voucher\Models\Voucher;
use Throwable;

class AlreadyApplied
{
    /**
     * @throws VoucherAlreadyAppliedException
     * @throws Throwable
     */
    public function handle(Voucher $voucher, Closure $next, string $cart): mixed
    {
        throw_if(
            resolve($cart)::discountAlreadyApplied($voucher),
            VoucherAlreadyAppliedException::class
        );

        return $next($voucher);
    }
}
