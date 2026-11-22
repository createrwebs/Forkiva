<?php

namespace Modules\Export\Listeners;

use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Export\Events\ExportCompleted;
use Modules\Export\Services\ExportNotification\ExportNotificationServiceInterface;

class SendExportCompletedNotification implements ShouldQueueAfterCommit
{
    use Queueable;

    /**
     * Handle the event.
     */
    public function handle(ExportCompleted $event): void
    {
        app(ExportNotificationServiceInterface::class)->sendCompleted($event->export);
    }
}
