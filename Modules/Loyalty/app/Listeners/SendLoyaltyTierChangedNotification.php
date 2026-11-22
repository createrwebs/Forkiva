<?php

namespace Modules\Loyalty\Listeners;

use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Loyalty\Events\LoyaltyTierChanged;
use Modules\Loyalty\Services\LoyaltyNotification\LoyaltyNotificationServiceInterface;

class SendLoyaltyTierChangedNotification implements ShouldQueueAfterCommit
{
    use Queueable;

    /**
     * Handle the event.
     */
    public function handle(LoyaltyTierChanged $event): void
    {
        app(LoyaltyNotificationServiceInterface::class)->sendTierChanged($event);
    }
}
