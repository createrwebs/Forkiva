<?php

namespace Modules\Sms\Services\SmsMessage;

use Prgayman\Sms\SmsNotification;

interface SmsMessageServiceInterface
{
    /**
     * Build a package SMS notification for a Laravel notifiable.
     */
    public function make(object $notifiable, string $message): ?SmsNotification;
}
