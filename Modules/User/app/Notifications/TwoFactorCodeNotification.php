<?php

namespace Modules\User\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Sms\Services\SmsMessage\SmsMessageServiceInterface;
use Modules\User\Enums\TwoFactorMethod;
use Prgayman\Sms\Channels\SmsChannel;
use Prgayman\Sms\SmsNotification;

class TwoFactorCodeNotification extends Notification
{
    /**
     * Create a two-factor code notification.
     */
    public function __construct(
        protected string $code,
        protected TwoFactorMethod $method,
        protected int $expiresInMinutes,
    ) {}

    /**
     * Get the notification channels.
     */
    public function via(object $notifiable): array
    {
        return match ($this->method) {
            TwoFactorMethod::Email => ['mail'],
            TwoFactorMethod::Sms => [SmsChannel::class],
            TwoFactorMethod::Authenticator => [],
        };
    }

    /**
     * Build the email message.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('user::auth.two_factor_code_subject'))
            ->line(__('user::email.verification_instruction'))
            ->markdown('notifications::email', [
                'verificationCode' => $this->code,
                'expiresInMinutes' => $this->expiresInMinutes,
            ]);
    }

    /**
     * Build the SMS message.
     */
    public function toSms(object $notifiable): ?SmsNotification
    {
        return app(SmsMessageServiceInterface::class)->make(
            $notifiable,
            __('user::auth.two_factor_code_message', [
                'code' => $this->code,
                'minutes' => $this->expiresInMinutes,
            ]),
        );
    }
}
