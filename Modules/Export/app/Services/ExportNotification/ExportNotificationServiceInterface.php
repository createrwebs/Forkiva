<?php

namespace Modules\Export\Services\ExportNotification;

use Modules\Export\Models\Export;

interface ExportNotificationServiceInterface
{
    /**
     * Send the export-completed notification.
     */
    public function sendCompleted(Export $export): void;

    /**
     * Send the export-failed notification.
     */
    public function sendFailed(Export $export): void;
}
