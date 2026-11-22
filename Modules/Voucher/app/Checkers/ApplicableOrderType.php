<?php

namespace Modules\Voucher\Checkers;

use Closure;
use Modules\Voucher\Exceptions\InapplicableVoucherException;
use Modules\Voucher\Models\Voucher;
use Throwable;

class ApplicableOrderType
{
    /**
     * @throws Throwable
     * @throws InapplicableVoucherException
     */
    public function handle(Voucher $voucher, Closure $next, string $cart): mixed
    {
        $orderTypes = collect($voucher->conditions['order_types'] ?? []);

        if ($orderTypes->isEmpty()) {
            return $next($voucher);
        }
        $cart = resolve($cart);

        throw_if(
            ! $cart::hasOrderType() || ! $orderTypes->contains($cart::orderType()->value()),
            InapplicableVoucherException::class
        );

        return $next($voucher);
    }
}
