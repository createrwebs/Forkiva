<?php

namespace Modules\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Providers\CoreServiceProvider;
use Modules\Setting\Models\Setting;
use Tests\TestCase;

class AppNameBootTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_name_uses_the_saved_default_locale_during_boot(): void
    {
        $this->assertBootedAppName(['en' => 'English name', 'ar' => 'الاسم العربي']);
    }

    public function test_app_name_is_available_when_only_the_saved_locale_is_translated(): void
    {
        $this->assertBootedAppName(['ar' => 'الاسم العربي']);
    }

    private function assertBootedAppName(array $translations): void
    {
        config([
            'app.name' => 'Environment name',
            'app.locale' => 'en',
            'app.fallback_locale' => 'en',
        ]);
        foreach ([
            ['key' => 'default_locale', 'payload' => 'ar'],
            ['key' => 'app_name', 'is_translatable' => true, 'payload' => $translations],
            ['key' => 'services.notifications.email.enabled', 'payload' => true],
        ] as $attributes) {
            (new Setting($attributes))->saveQuietly();
        }
        Cache::tags('settings')->flush();

        (new CoreServiceProvider($this->app))->boot();

        $this->assertSame('ar', app()->getLocale());
        $this->assertSame('الاسم العربي', setting('app_name'));
        $this->assertSame('الاسم العربي', config('app.name'));
        $this->assertSame('الاسم العربي', config('mail.from.name'));
    }
}
