<?php

namespace Modules\Loyalty\Listeners;

use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Loyalty\Events\LoyaltyPointsChanged;
use Modules\Loyalty\Services\LoyaltyNotification\LoyaltyNotificationServiceInterface;

class SendLoyaltyPointsChangedNotification implements ShouldQueueAfterCommit
{
    use Queueable;

    /**
     * Handle the event.
     */
    public function handle(LoyaltyPointsChanged $event): void
    {
        app(LoyaltyNotificationServiceInterface::class)->sendPointsChanged($event);
    }
}
