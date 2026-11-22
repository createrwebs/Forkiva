<?php

namespace Modules\Export\Listeners;

use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Export\Events\ExportFailed;
use Modules\Export\Services\ExportNotification\ExportNotificationServiceInterface;

class SendExportFailedNotification implements ShouldQueueAfterCommit
{
    use Queueable;

    /**
     * Handle the event.
     */
    public function handle(ExportFailed $event): void
    {
        app(ExportNotificationServiceInterface::class)->sendFailed($event->export);
    }
}
