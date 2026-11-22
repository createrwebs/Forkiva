<?php

namespace Modules\GiftCard\Listeners;

use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Modules\GiftCard\Events\GiftCardRecharged;
use Modules\GiftCard\Services\GiftCardNotification\GiftCardNotificationServiceInterface;

class SendGiftCardRechargedNotification implements ShouldQueueAfterCommit
{
    use Queueable;

    /**
     * Handle the event.
     */
    public function handle(GiftCardRecharged $event): void
    {
        app(GiftCardNotificationServiceInterface::class)->sendRecharged($event->transaction);
    }
}
