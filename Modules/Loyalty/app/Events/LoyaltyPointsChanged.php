<?php

namespace Modules\Loyalty\Events;

use Modules\Loyalty\Models\LoyaltyCustomer;

class LoyaltyPointsChanged
{
    /**
     * Create a new event instance.
     *
     * @param  array<string, string>  $action
     * @param  array<string, string>|string|null  $rewardName
     */
    public function __construct(
        public LoyaltyCustomer $loyaltyCustomer,
        public array $action,
        public int $points,
        public array|string|null $rewardName = null,
        public ?string $orderNumber = null,
        public ?int $branchId = null,
    ) {}
}
