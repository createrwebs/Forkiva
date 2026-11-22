<?php

namespace Modules\GiftCard\Listeners;

use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Modules\GiftCard\Events\GiftCardRedeemed;
use Modules\GiftCard\Services\GiftCardNotification\GiftCardNotificationServiceInterface;

class SendGiftCardRedeemedNotification implements ShouldQueueAfterCommit
{
    use Queueable;

    /**
     * Handle the event.
     */
    public function handle(GiftCardRedeemed $event): void
    {
        app(GiftCardNotificationServiceInterface::class)->sendRedeemed($event->transaction);
    }
}
