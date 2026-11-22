<?php

namespace Modules\Sms\Tests\Unit;

use Modules\Notification\Notifications\RenderedTemplateNotification;
use Modules\Notification\Support\GuestNotifiable;
use Modules\Setting\Repositories\SettingRepository;
use Modules\Sms\Enums\SmsProvider;
use Modules\Sms\Services\SmsMessage\SmsMessageService;
use Modules\Sms\Services\SmsMessage\SmsMessageServiceInterface;
use Modules\Sms\Services\SmsProvider\SmsProviderConfiguratorService;
use Modules\Sms\Services\SmsProvider\SmsProviderConfiguratorServiceInterface;
use Prgayman\Sms\Channels\SmsChannel;
use Tests\TestCase;

class SmsModuleTest extends TestCase
{
    public function test_sms_service_bindings_are_registered(): void
    {
        $this->assertInstanceOf(
            SmsProviderConfiguratorService::class,
            app(SmsProviderConfiguratorServiceInterface::class)
        );
        $this->assertInstanceOf(
            SmsMessageService::class,
            app(SmsMessageServiceInterface::class)
        );
    }

    public function test_provider_is_inactive_when_sms_is_disabled_or_invalid(): void
    {
        $service = app(SmsProviderConfiguratorServiceInterface::class);

        $this->installSettings([
            'services.notifications.sms.enabled' => false,
            'services.notifications.sms.provider' => 'array',
        ]);
        $this->assertNull($service->activeProvider());

        $this->installSettings([
            'services.notifications.sms.enabled' => true,
            'services.notifications.sms.provider' => 'removed-provider',
        ]);
        $this->assertNull($service->activeProvider());
    }

    public function test_provider_settings_configure_the_package_driver(): void
    {
        $this->installSettings([
            'services.notifications.sms.enabled' => true,
            'services.notifications.sms.provider' => 'taqnyat',
            'services.notifications.sms.sender_name' => 'Klenivo',
            'services.notifications.sms.taqnyat.authorization' => 'secret-token',
        ]);

        $provider = app(SmsProviderConfiguratorServiceInterface::class)->activeProvider();

        $this->assertSame(SmsProvider::Taqnyat, $provider);
        $this->assertSame('taqnyat', config('sms.default'));
        $this->assertSame('secret-token', config('sms.drivers.taqnyat.authorization'));
        $this->assertSame('Klenivo', config('sms.drivers.taqnyat.sender'));
    }

    public function test_rendered_notification_delivers_sms_through_the_array_driver(): void
    {
        $this->installSettings([
            'services.notifications.sms.enabled' => true,
            'services.notifications.sms.provider' => 'array',
            'services.notifications.sms.sender_name' => 'Klenivo',
        ]);
        $notifiable = new GuestNotifiable(
            name: 'Guest',
            phone: '+962790000000',
        );
        $notification = new RenderedTemplateNotification([
            'sms' => [
                'type' => 'test_sms',
                'translations' => [
                    'en' => [
                        'title' => 'SMS test',
                        'subject' => null,
                        'content' => '<strong>Your code is 1234</strong>',
                    ],
                ],
            ],
        ], 'Klenivo');

        $this->assertContains(SmsChannel::class, $notification->via($notifiable));

        $response = app(SmsChannel::class)->send($notifiable, $notification);

        $this->assertTrue($response->successful());
        $this->assertSame([
            'type' => 'general',
            'from' => 'Klenivo',
            'to' => '+962790000000',
            'message' => 'Your code is 1234',
        ], $response->getRequest());
    }

    /**
     * Replace application settings for one test scenario.
     */
    private function installSettings(array $settings): void
    {
        app()->instance('setting', new SettingRepository(
            collect($settings)->map(fn (mixed $value): array => [
                'payload' => serialize($value),
                'is_encryptable' => false,
                'is_translatable' => false,
            ])
        ));
    }
}
