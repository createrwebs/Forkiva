<?php

namespace Modules\GiftCard\Listeners;

use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Modules\GiftCard\Events\GiftCardAdjusted;
use Modules\GiftCard\Services\GiftCardNotification\GiftCardNotificationServiceInterface;

class SendGiftCardAdjustedNotification implements ShouldQueueAfterCommit
{
    use Queueable;

    /**
     * Handle the event.
     */
    public function handle(GiftCardAdjusted $event): void
    {
        app(GiftCardNotificationServiceInterface::class)->sendAdjusted($event->transaction);
    }
}
