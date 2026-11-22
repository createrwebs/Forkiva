<?php

namespace Modules\Voucher\Checkers;

use Closure;
use Modules\Voucher\Exceptions\InapplicableVoucherException;
use Modules\Voucher\Models\Voucher;
use Throwable;

class ApplicableHasItems
{
    /**
     * @throws Throwable
     * @throws InapplicableVoucherException
     */
    public function handle(Voucher $voucher, Closure $next, string $cart): mixed
    {
        throw_if(resolve($cart)::getContent()->isEmpty(), InapplicableVoucherException::class);

        return $next($voucher);
    }
}
