<?php

namespace Modules\GiftCard\Listeners;

use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Modules\GiftCard\Events\GiftCardIssued;
use Modules\GiftCard\Services\GiftCardNotification\GiftCardNotificationServiceInterface;

class SendGiftCardIssuedNotification implements ShouldQueueAfterCommit
{
    use Queueable;

    /**
     * Handle the event.
     */
    public function handle(GiftCardIssued $event): void
    {
        app(GiftCardNotificationServiceInterface::class)->sendIssued($event->giftCard);
    }
}
