<?php

namespace Modules\SeatingPlan\Listeners;

use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Modules\SeatingPlan\Events\TableAssignWaiter;
use Modules\SeatingPlan\Services\SeatingPlanNotification\SeatingPlanNotificationServiceInterface;

class SendTableAssignedToWaiterNotification implements ShouldQueueAfterCommit
{
    /**
     * Handle the event.
     */
    public function handle(TableAssignWaiter $event): void
    {
        app(SeatingPlanNotificationServiceInterface::class)->sendAssignedToWaiter($event);
    }
}
