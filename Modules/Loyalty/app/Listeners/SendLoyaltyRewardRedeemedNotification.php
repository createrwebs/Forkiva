<?php

namespace Modules\Loyalty\Listeners;

use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Loyalty\Events\LoyaltyRewardRedeemed;
use Modules\Loyalty\Services\LoyaltyNotification\LoyaltyNotificationServiceInterface;

class SendLoyaltyRewardRedeemedNotification implements ShouldQueueAfterCommit
{
    use Queueable;

    /**
     * Handle the event.
     */
    public function handle(LoyaltyRewardRedeemed $event): void
    {
        app(LoyaltyNotificationServiceInterface::class)->sendRewardRedeemed($event);
    }
}
