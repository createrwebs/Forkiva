<?php

namespace Modules\Sms\Services\SmsMessage;

use Modules\Sms\Services\SmsProvider\SmsProviderConfiguratorServiceInterface;
use Prgayman\Sms\SmsNotification;

class SmsMessageService implements SmsMessageServiceInterface
{
    /**
     * Create a new SMS message service instance.
     */
    public function __construct(
        protected SmsProviderConfiguratorServiceInterface $providerConfigurator,
    ) {}

    /** {@inheritDoc} */
    public function make(object $notifiable, string $message): ?SmsNotification
    {
        $provider = $this->providerConfigurator->activeProvider();
        $recipient = $this->routeFor($notifiable);

        if (! $provider || blank($recipient) || blank($message)) {
            return null;
        }

        $sms = (new SmsNotification)
            ->driver($provider->value)
            ->to($recipient)
            ->message($message);

        if ($sender = $this->providerConfigurator->senderName()) {
            $sms->from($sender);
        }

        return $sms;
    }

    /**
     * Resolve the notifiable's SMS route.
     */
    protected function routeFor(object $notifiable): ?string
    {
        if (method_exists($notifiable, 'routeNotificationForSms')) {
            $route = $notifiable->routeNotificationForSms();

            return blank($route) ? null : (string) $route;
        }

        $route = data_get($notifiable, 'phone');

        return blank($route) ? null : (string) $route;
    }
}
