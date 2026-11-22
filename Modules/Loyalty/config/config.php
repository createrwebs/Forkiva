<?php

use Modules\Loyalty\Services\LoyaltyCustomer\LoyaltyCustomerServiceInterface;
use Modules\Loyalty\Services\LoyaltyGift\LoyaltyGiftServiceInterface;
use Modules\Loyalty\Services\LoyaltyProgram\LoyaltyProgramServiceInterface;
use Modules\Loyalty\Services\LoyaltyPromotion\LoyaltyPromotionServiceInterface;
use Modules\Loyalty\Services\LoyaltyReward\LoyaltyRewardServiceInterface;
use Modules\Loyalty\Services\LoyaltyTier\LoyaltyTierServiceInterface;
use Modules\Loyalty\Services\LoyaltyTransaction\LoyaltyTransactionServiceInterface;
use Modules\User\Enums\PermissionAction as Action;

return [
    'permissions' => [
        'loyalty_programs' => [Action::Index, Action::Show, Action::Create, Action::Edit, Action::Destroy, Action::Export],
        'loyalty_rewards' => [Action::Index, Action::Show, Action::Create, Action::Edit, Action::Destroy, Action::Export],
        'loyalty_tiers' => [Action::Index, Action::Show, Action::Create, Action::Edit, Action::Destroy, Action::Export],
        'loyalty_promotions' => [Action::Index, Action::Show, Action::Create, Action::Edit, Action::Destroy, Action::Export],
        'loyalty_customers' => [Action::Index, Action::Show, Action::Export],
        'loyalty_transactions' => [Action::Index, Action::Show, Action::Export],
        'loyalty_gifts' => [Action::Index, Action::IndexRewards, Action::Redeem, Action::Export],
    ],

    'export_resources' => [
        'loyalty_programs' => LoyaltyProgramServiceInterface::class,
        'loyalty_tiers' => LoyaltyTierServiceInterface::class,
        'loyalty_rewards' => LoyaltyRewardServiceInterface::class,
        'loyalty_promotions' => LoyaltyPromotionServiceInterface::class,
        'loyalty_customers' => LoyaltyCustomerServiceInterface::class,
        'loyalty_gifts' => LoyaltyGiftServiceInterface::class,
        'loyalty_transactions' => LoyaltyTransactionServiceInterface::class,
    ],
];
