<?php

namespace Modules\Service\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Setting\Models\Setting;
use Tests\TestCase;

class ServiceSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_services_index_returns_notification_services_from_module_config(): void
    {
        $response = $this->withoutMiddleware()->getJson('/api/v1/services');

        $response->assertOk();

        $categories = $response->json('body');
        $notifications = collect($categories)->firstWhere('key', 'notifications');

        $this->assertNotNull($notifications);
        $this->assertSame('Notifications', $notifications['label']);

        $services = collect($notifications['services']);
        $this->assertSame('Email', $services->firstWhere('key', 'email')['name'] ?? null);
        $this->assertSame('In-app', $services->firstWhere('key', 'in_app')['name'] ?? null);
        $this->assertSame('Push Notification', $services->firstWhere('key', 'push_notification')['name'] ?? null);
        $this->assertFalse($services->firstWhere('key', 'email')['values']['enabled'] ?? true);
        $this->assertFalse($services->firstWhere('key', 'in_app')['values']['enabled'] ?? true);
        $this->assertFalse($services->firstWhere('key', 'push_notification')['values']['enabled'] ?? true);
        $this->assertSame('#2563eb', $services->firstWhere('key', 'email')['color'] ?? null);
    }

    public function test_enum_backed_dropdown_options_are_serialized(): void
    {
        $response = $this->withoutMiddleware()
            ->getJson('/api/v1/services/notifications/push_notification');

        $response->assertOk();

        $provider = collect($response->json('body.settings'))->firstWhere('key', 'provider');

        $this->assertNotNull($provider);
        $this->assertContains('fcm', collect($provider['options'])->pluck('id'));
        $this->assertContains('FCM', collect($provider['options'])->pluck('name'));

        $credentials = collect($provider['conditional_fields']['fcm'] ?? [])
            ->firstWhere('key', 'credentials');

        $this->assertSame('Firebase Credentials', $credentials['label'] ?? null);
        $this->assertTrue($credentials['is_sensitive'] ?? false);
    }

    public function test_updating_fcm_push_notification_settings_persists_flat_keys_and_encrypts_credentials(): void
    {
        $credentials = '/secure/firebase/service-account.json';

        $response = $this->withoutMiddleware()->putJson(
            '/api/v1/services/notifications/push_notification/settings',
            [
                'enabled' => true,
                'provider' => 'fcm',
                'fcm' => [
                    'credentials' => $credentials,
                ],
            ]
        );

        $response->assertOk()
            ->assertJsonPath('body.values.provider', 'fcm')
            ->assertJsonPath('body.values.fcm.credentials', $credentials)
            ->assertJsonPath('body.settings.1.value_option.name', 'FCM');

        $this->assertTrue(setting('services.notifications.push_notification.enabled'));
        $this->assertSame('fcm', setting('services.notifications.push_notification.provider'));
        $this->assertSame($credentials, setting('services.notifications.push_notification.fcm.credentials'));

        $setting = Setting::query()
            ->where('key', 'services.notifications.push_notification.fcm.credentials')
            ->first();

        $this->assertNotNull($setting);
        $this->assertTrue($setting->is_encryptable);
        $this->assertNotSame(serialize($credentials), $setting->getRawOriginal('payload'));
    }

    public function test_active_provider_conditional_fields_are_required(): void
    {
        $response = $this->withoutMiddleware()->putJson(
            '/api/v1/services/notifications/push_notification/settings',
            [
                'enabled' => true,
                'provider' => 'fcm',
                'fcm' => [],
            ]
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['fcm.credentials']);
    }

    public function test_disabled_service_settings_only_require_enabled_field(): void
    {
        $response = $this->withoutMiddleware()->putJson(
            '/api/v1/services/notifications/push_notification/settings',
            [
                'enabled' => false,
            ]
        );

        $response->assertOk()
            ->assertJsonPath('body.values.enabled', false);

        $enabledSetting = Setting::query()
            ->where('key', 'services.notifications.push_notification.enabled')
            ->first();

        $this->assertNotNull($enabledSetting);
        $this->assertFalse(unserialize($enabledSetting->getRawOriginal('payload')));
        $this->assertNull(setting('services.notifications.push_notification.provider'));
        $this->assertNull(setting('services.notifications.push_notification.fcm.credentials'));
    }
}
