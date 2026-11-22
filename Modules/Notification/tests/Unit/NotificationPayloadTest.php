<?php

namespace Modules\Notification\Tests\Unit;

use Modules\Notification\Support\NotificationPayload;
use Tests\TestCase;

class NotificationPayloadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app()->instance('setting', new class
        {
            public function get(string $key, mixed $default = null): mixed
            {
                return match ($key) {
                    'supported_locales' => ['en', 'ar'],
                    'default_locale' => 'en',
                    default => $default,
                };
            }

            public function set(array $data): array
            {
                return $data;
            }
        });
    }

    public function test_for_client_uses_locale_fallback_then_first_available_translation(): void
    {
        $payload = [
            'type' => 'profile_updated',
            'type_id' => null,
            'translations' => [
                'en' => [
                    'title' => 'Profile Updated',
                    'subject' => null,
                    'content' => 'Profile updated in English.',
                ],
                'ar' => [
                    'title' => null,
                    'subject' => null,
                    'content' => 'تم تحديث الملف الشخصي.',
                ],
            ],
        ];

        $clientPayload = NotificationPayload::forClient($payload, 'ar');

        $this->assertSame('Profile Updated', $clientPayload['title']);
        $this->assertSame('تم تحديث الملف الشخصي.', $clientPayload['content']);
        $this->assertNull($clientPayload['subject']);
        $this->assertArrayNotHasKey('translations', $clientPayload);
    }

    public function test_for_push_data_includes_notification_id_as_string(): void
    {
        $payload = [
            'type' => 'delivery_assigned',
            'type_id' => 15,
            'app_context' => 'driver',
            'translations' => [
                'en' => [
                    'title' => 'Delivery assigned',
                    'subject' => null,
                    'content' => '<p>Delivery assigned.</p>',
                ],
            ],
        ];

        $pushData = NotificationPayload::forPushData(
            payload: $payload,
            locale: 'en',
            notificationId: 'notification-123',
        );

        $this->assertSame('notification-123', $pushData['notification_id']);
        $this->assertSame('delivery_assigned', $pushData['type']);
        $this->assertSame('15', $pushData['type_id']);
        $this->assertSame('driver', $pushData['app_context']);
        $this->assertSame('Delivery assigned.', $pushData['content']);
    }
}
