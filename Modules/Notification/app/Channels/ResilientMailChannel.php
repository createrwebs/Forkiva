<?php

namespace Modules\Notification\Channels;

use Illuminate\Mail\SentMessage;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Notifications\Notification;
use Modules\Notification\Notifications\RenderedTemplateNotification;
use Throwable;

class ResilientMailChannel extends MailChannel
{
    /**
     * Send rendered workflow emails without failing the triggering operation.
     */
    public function send($notifiable, Notification $notification): ?SentMessage
    {
        if (! $notification instanceof RenderedTemplateNotification) {
            return parent::send($notifiable, $notification);
        }

        try {
            return parent::send($notifiable, $notification);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }
}
