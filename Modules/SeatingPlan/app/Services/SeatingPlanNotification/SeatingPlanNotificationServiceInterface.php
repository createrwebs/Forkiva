<?php

namespace Modules\SeatingPlan\Services\SeatingPlanNotification;

use Modules\SeatingPlan\Events\TableAssignWaiter;
use Modules\SeatingPlan\Events\TableUpdateStatus;

interface SeatingPlanNotificationServiceInterface
{
    /**
     * Send table-assigned notification.
     */
    public function sendAssignedToWaiter(TableAssignWaiter $event): void;

    /**
     * Send table-status-changed notification.
     */
    public function sendStatusChanged(TableUpdateStatus $event): void;
}
