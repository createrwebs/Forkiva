<?php

namespace Modules\Setting\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Setting\Models\Setting;
use Modules\Setting\Services\Setting\SettingService;
use Tests\TestCase;

class AppSettingsVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_versions_are_stable_until_settings_change(): void
    {
        app()->instance('env', 'local');
        $service = app(SettingService::class);
        $initial = $service->getAppSettingsVersion();

        $this->assertSame($initial, $service->getAppSettingsVersion());

        $setting = new Setting(['key' => 'performance_test', 'payload' => 'first']);
        $setting->saveQuietly();
        $created = $service->getAppSettingsVersion();
        $this->assertNotSame($initial, $created);

        $this->travel(2)->seconds();
        $setting->payload = 'second';
        $setting->saveQuietly();
        $updated = $service->getAppSettingsVersion();
        $this->assertNotSame($created, $updated);
        $this->assertSame($updated, $service->getAppSettingsVersion());
    }
}
