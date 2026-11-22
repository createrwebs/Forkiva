<?php

namespace Modules\Notification\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Notification\Enums\NotificationContext;
use Modules\Notification\Enums\NotificationPushTokenClient;
use Modules\Notification\Services\NotificationChannel\NotificationChannelResolverService;
use Modules\Notification\Services\NotificationPush\NotificationPushProviderConfiguratorServiceInterface;
use Modules\Notification\Support\NotificationPayload;
use Modules\Service\Enums\PushNotificationProvider;
use Modules\Sms\Services\SmsMessage\SmsMessageServiceInterface;
use Modules\Sms\Services\SmsProvider\SmsProviderConfiguratorServiceInterface;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use NotificationChannels\Fcm\Resources\Notification as FcmNotification;
use Prgayman\Sms\Channels\SmsChannel;
use Prgayman\Sms\SmsNotification;

class RenderedTemplateNotification extends Notification
{
    use Queueable;

    /**
     * Cached canonical payloads by channel key.
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $payloads = [];

    /**
     * Create a new RenderedTemplateNotification instance.
     *
     * @param  array<string, array<string, mixed>>  $renderedChannels
     */
    public function __construct(protected array $renderedChannels, protected string $appName) {}

    /**
     * Get the notification delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = [];

        if ($this->hasChannel(NotificationChannelResolverService::EMAIL_CHANNEL)) {
            $channels[] = 'mail';
        }

        if ($this->hasChannel('in_app')) {
            $channels[] = 'database';
        }

        if ($this->hasChannel('push_notification')) {
            $channels = [...$channels, ...$this->pushVia()];
        }

        if ($this->hasChannel('sms') && app(SmsProviderConfiguratorServiceInterface::class)->activeProvider()) {
            $channels[] = SmsChannel::class;
        }

        return $channels;
    }

    /**
     * Determine whether the notification has a rendered payload for the channel.
     */
    protected function hasChannel(string $channelKey): bool
    {
        return isset($this->renderedChannels[$channelKey]) && is_array($this->renderedChannels[$channelKey]);
    }

    /**
     * Get the push delivery channel.
     *
     * @return array<int, string>
     */
    protected function pushVia(): array
    {
        return match ($this->pushProvider()) {
            PushNotificationProvider::Fcm => [FcmChannel::class],
            default => [],
        };
    }

    /**
     * Resolve the active push provider from Service settings.
     */
    protected function pushProvider(): ?PushNotificationProvider
    {
        return app(NotificationPushProviderConfiguratorServiceInterface::class)->activeProvider();
    }

    /**
     * Build the SMS notification.
     *
     * @throws BindingResolutionException
     */
    public function toSms(object $notifiable): ?SmsNotification
    {
        $payload = NotificationPayload::forClient($this->payload('sms'));

        return app(SmsMessageServiceInterface::class)->make(
            notifiable: $notifiable,
            message: NotificationPayload::plain($payload['content'] ?? ''),
        );
    }

    /**
     * Build the canonical payload for a channel.
     *
     * @return array<string, mixed>
     */
    protected function payload(string $channelKey): array
    {
        return $this->payloads[$channelKey] ??= NotificationPayload::fromRendered(
            $this->channelPayload($channelKey)
        );
    }

    /**
     * Get rendered payload for a channel.
     *
     * @return array<string, mixed>
     */
    protected function channelPayload(string $channelKey): array
    {
        return $this->renderedChannels[$channelKey] ?? [];
    }

    /**
     * Build the email notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $payload = NotificationPayload::forClient($this->payload(NotificationChannelResolverService::EMAIL_CHANNEL));

        return (new MailMessage)
            ->subject((string) ($payload['subject'] ?? $payload['title'] ?? $this->appName))
            ->view([
                'html' => 'notification::emails.rendered_template',
                'text' => 'notification::emails.rendered_template_text',
            ], [
                'content' => (string) ($payload['content'] ?? ''),
                'title' => (string) ($payload['subject'] ?? $payload['title'] ?? $this->appName),
            ]);
    }

    /**
     * Build the database notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->payload('in_app');
    }

    /**
     * Build the array notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->payload('in_app');
    }

    /**
     * Build the FCM notification.
     */
    public function toFcm(object $notifiable): FcmMessage
    {
        $payload = $this->payload('push_notification');
        $clientPayload = NotificationPayload::forClient($payload);

        return FcmMessage::create()
            ->notification(new FcmNotification(
                title: (string) ($clientPayload['title'] ?? $this->appName),
                body: NotificationPayload::plain($clientPayload['content'] ?? '')
            ))
            ->data(NotificationPayload::forPushData($payload, notificationId: $this->id));
    }

    /**
     * Resolve all push-token clients that should receive this notification.
     *
     * @return array<int, string>|null
     */
    public function pushTokenClients(): ?array
    {
        return match ($this->pushContext()) {
            NotificationContext::Driver->value => [
                NotificationPushTokenClient::Web->value,
                NotificationPushTokenClient::DriverApp->value,
            ],
            NotificationContext::Employee->value => [
                NotificationPushTokenClient::Web->value,
                NotificationPushTokenClient::EmployeeApp->value,
            ],
            NotificationContext::Other->value => [
                NotificationPushTokenClient::Web->value,
            ],
            default => null,
        };
    }

    /**
     * Resolve the notification context from the push payload.
     */
    public function pushContext(): ?string
    {
        $client = $this->payload('push_notification')['app_context'] ?? null;

        return blank($client) ? null : (string) $client;
    }
}
