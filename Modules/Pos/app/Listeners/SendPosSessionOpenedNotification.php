<?php

namespace Modules\Pos\Listeners;

use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Pos\Events\OpenPosSession;
use Modules\Pos\Services\PosNotification\PosNotificationServiceInterface;

class SendPosSessionOpenedNotification implements ShouldQueueAfterCommit
{
    use Queueable;

    /**
     * Handle the event.
     */
    public function handle(OpenPosSession $event): void
    {
        app(PosNotificationServiceInterface::class)->sendSessionOpened($event->session);
    }
}
