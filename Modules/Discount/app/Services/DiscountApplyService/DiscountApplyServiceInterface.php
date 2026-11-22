<?php

namespace Modules\Discount\Services\DiscountApplyService;

use Modules\Loyalty\Models\LoyaltyGift;

interface DiscountApplyServiceInterface
{
    /**
     * Apply discount
     */
    public function applyDiscount(string $cart, int $id, ?LoyaltyGift $gift = null): void;

    /**
     * Apply voucher
     */
    public function applyVoucher(string $cart, string $code, ?LoyaltyGift $gift = null): void;
}
