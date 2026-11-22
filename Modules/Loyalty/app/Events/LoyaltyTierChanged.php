<?php

namespace Modules\Loyalty\Events;

use Modules\Loyalty\Models\LoyaltyCustomer;
use Modules\Loyalty\Models\LoyaltyTier;

class LoyaltyTierChanged
{
    /**
     * Create a new event instance.
     *
     * @param  array<string, string>  $action
     * @param  array<string, string>|string|null  $rewardName
     */
    public function __construct(
        public LoyaltyCustomer $loyaltyCustomer,
        public ?LoyaltyTier $oldTier,
        public ?LoyaltyTier $newTier,
        public array $action,
        public int $points,
        public array|string|null $rewardName = null,
        public ?string $orderNumber = null,
        public ?int $branchId = null,
    ) {}
}
