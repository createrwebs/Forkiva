<?php

namespace Modules\User\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Sms\Services\SmsMessage\SmsMessageServiceInterface;
use Prgayman\Sms\Channels\SmsChannel;
use Prgayman\Sms\SmsNotification;

class PasswordRecoveryCodeNotification extends Notification
{
    /**
     * Create a recovery notification for the selected delivery channel.
     */
    public function __construct(protected string $code, protected string $channel) {}

    /**
     * Get the notification transport for the selected recovery channel.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $this->channel === 'email' ? ['mail'] : [SmsChannel::class];
    }

    /**
     * Build the translated recovery message using the configured email service.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('user::auth.recovery_subject'))
            ->line(__('user::email.recovery_instruction'))
            ->markdown('notifications::email', [
                'verificationCode' => $this->code,
                'expiresInMinutes' => 10,
                'securityNote' => __('user::auth.recovery_ignore'),
            ]);
    }

    /**
     * Build the translated recovery message through the shared SMS service.
     */
    public function toSms(object $notifiable): ?SmsNotification
    {
        return app(SmsMessageServiceInterface::class)
            ->make($notifiable, __('user::auth.recovery_message', ['code' => $this->code]));
    }
}
