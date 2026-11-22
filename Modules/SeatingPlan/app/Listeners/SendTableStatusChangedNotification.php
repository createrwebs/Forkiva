<?php

namespace Modules\SeatingPlan\Listeners;

use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Modules\SeatingPlan\Events\TableUpdateStatus;
use Modules\SeatingPlan\Services\SeatingPlanNotification\SeatingPlanNotificationServiceInterface;

class SendTableStatusChangedNotification implements ShouldQueueAfterCommit
{
    /**
     * Handle the event.
     */
    public function handle(TableUpdateStatus $event): void
    {
        app(SeatingPlanNotificationServiceInterface::class)->sendStatusChanged($event);
    }
}
