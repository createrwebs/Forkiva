<?php

namespace Modules\Pos\Listeners;

use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Pos\Events\ClosePosSession;
use Modules\Pos\Services\PosNotification\PosNotificationServiceInterface;

class SendPosSessionClosedNotification implements ShouldQueueAfterCommit
{
    use Queueable;

    /**
     * Handle the event.
     */
    public function handle(ClosePosSession $event): void
    {
        app(PosNotificationServiceInterface::class)->sendSessionClosed($event->session);
    }
}
