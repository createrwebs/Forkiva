<?php

namespace Modules\Service\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\Rules\In;
use Modules\Service\Services\ServiceRegistry\ServiceRegistryServiceInterface;
use Modules\Setting\Models\Setting;
use Tests\TestCase;

class ServiceRegistryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_show_serializes_values_options_and_sensitive_conditional_fields(): void
    {
        $service = app(ServiceRegistryServiceInterface::class);

        $payload = $service->show('notifications', 'push_notification');

        $this->assertSame('push_notification', $payload['key']);
        $this->assertSame('notifications', $payload['category']);
        $this->assertFalse($payload['values']['enabled']);

        $provider = collect($payload['settings'])->firstWhere('key', 'provider');
        $this->assertNotNull($provider);
        $this->assertContains('fcm', collect($provider['options'])->pluck('id'));

        $credentials = collect($provider['conditional_fields']['fcm'] ?? [])->firstWhere('key', 'credentials');
        $this->assertSame('Firebase Credentials', $credentials['label'] ?? null);
        $this->assertTrue($credentials['is_sensitive'] ?? false);
    }

    public function test_rules_require_active_provider_conditional_fields_only_when_enabled(): void
    {
        $service = app(ServiceRegistryServiceInterface::class);

        $enabledRules = $service->rules('notifications', 'push_notification', [
            'enabled' => true,
            'provider' => 'fcm',
            'fcm' => [],
        ]);
        $disabledRules = $service->rules('notifications', 'push_notification', [
            'enabled' => false,
        ]);

        $this->assertArrayHasKey('enabled', $enabledRules);
        $this->assertArrayHasKey('provider', $enabledRules);
        $this->assertArrayHasKey('fcm', $enabledRules);
        $this->assertArrayHasKey('fcm.credentials', $enabledRules);
        $this->assertTrue(collect($enabledRules['provider'])->contains(fn ($rule) => $rule instanceof In));

        $this->assertArrayHasKey('enabled', $disabledRules);
        $this->assertArrayNotHasKey('provider', $disabledRules);
        $this->assertArrayNotHasKey('fcm.credentials', $disabledRules);
    }

    public function test_update_persists_flat_and_sensitive_values_through_service_contract(): void
    {
        $credentials = '/secure/firebase/service-account.json';

        $payload = app(ServiceRegistryServiceInterface::class)->update('notifications', 'push_notification', [
            'enabled' => true,
            'provider' => 'fcm',
            'fcm' => [
                'credentials' => $credentials,
            ],
        ]);

        $this->assertTrue($payload['values']['enabled']);
        $this->assertSame('fcm', $payload['values']['provider']);
        $this->assertSame($credentials, $payload['values']['fcm']['credentials']);
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
}
