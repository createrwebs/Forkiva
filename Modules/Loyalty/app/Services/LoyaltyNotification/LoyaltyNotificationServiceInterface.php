<?php

namespace Modules\Loyalty\Services\LoyaltyNotification;

use Modules\Loyalty\Events\LoyaltyPointsChanged;
use Modules\Loyalty\Events\LoyaltyRewardRedeemed;
use Modules\Loyalty\Events\LoyaltyTierChanged;

interface LoyaltyNotificationServiceInterface
{
    /**
     * Send the loyalty-points-changed notification.
     */
    public function sendPointsChanged(LoyaltyPointsChanged $event): void;

    /**
     * Send the loyalty-reward-redeemed notification.
     */
    public function sendRewardRedeemed(LoyaltyRewardRedeemed $event): void;

    /**
     * Send the loyalty-tier-changed notification.
     */
    public function sendTierChanged(LoyaltyTierChanged $event): void;
}
