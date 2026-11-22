<?php

namespace Modules\Discount\Checkers;

use Closure;
use Modules\Discount\Exceptions\MinimumSpendException;
use Modules\Discount\Models\Discount;

class MinimumSpend
{
    /**
     * @throws MinimumSpendException
     */
    public function handle(Discount $discount, Closure $next, string $cart): mixed
    {
        $cart = resolve($cart);

        if ($discount->didNotSpendTheRequiredAmount($cart)) {
            throw new MinimumSpendException(
                $discount->minimum_spend->convert($cart::branch()?->currency())
            );
        }

        return $next($discount);
    }
}
