<?php

namespace Modules\Pos\Services\PosNotification;

use Modules\Pos\Models\PosSession;

interface PosNotificationServiceInterface
{
    /**
     * Send the opened-session notification.
     */
    public function sendSessionOpened(PosSession $session): void;

    /**
     * Send the closed-session notification.
     */
    public function sendSessionClosed(PosSession $session): void;

    /**
     * Send the cash-variance notification when the session variance is non-zero.
     */
    public function sendCashVarianceFound(PosSession $session): void;
}
