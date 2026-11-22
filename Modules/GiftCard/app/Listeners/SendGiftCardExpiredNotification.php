<?php

namespace Modules\GiftCard\Listeners;

use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Modules\GiftCard\Events\GiftCardExpired;
use Modules\GiftCard\Services\GiftCardNotification\GiftCardNotificationServiceInterface;

class SendGiftCardExpiredNotification implements ShouldQueueAfterCommit
{
    use Queueable;

    /**
     * Handle the event.
     */
    public function handle(GiftCardExpired $event): void
    {
        app(GiftCardNotificationServiceInterface::class)->sendExpired($event);
    }
}
