<?php

namespace Modules\Loyalty\Events;

use Modules\Loyalty\Models\LoyaltyGift;

class LoyaltyRewardRedeemed
{
    /**
     * Create a new event instance.
     *
     * @param  array<string, string>  $action
     */
    public function __construct(
        public LoyaltyGift $gift,
        public array $action,
        public ?string $orderNumber = null,
        public ?int $branchId = null,
    ) {}
}
