<?php

namespace Modules\Translation\Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Translation\FileLoader;
use Modules\Setting\Repositories\SettingRepository;
use Modules\Translation\Models\Translation;
use Modules\Translation\Services\Translation\TranslationService;
use Modules\Translation\Support\TranslationSourceVersion;
use Tests\TestCase;

class AppTranslationsCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureLocales(['en', 'ar'], 'en');
        Cache::flush();
    }

    public function test_local_versions_are_stable_and_change_when_translations_change(): void
    {
        app()->instance('env', 'local');
        $service = app(TranslationService::class);
        $initial = $service->getAppTranslationsVersion();
        $this->assertSame($initial, $service->getAppTranslationsVersion());

        $translation = Translation::query()->create([
            'key' => 'product::performance_test.title',
            'value' => ['en' => 'First'],
        ]);
        $created = $service->getAppTranslationsVersion();
        $this->assertNotSame($initial, $created);

        $this->travel(2)->seconds();
        $translation->setTranslation('value', 'en', 'Second')->save();
        $this->assertNotSame($created, $service->getAppTranslationsVersion());
    }

    public function test_finished_bundles_are_reused_and_invalidated_by_model_changes(): void
    {
        $translation = Translation::query()->create([
            'key' => 'product::performance_test.title',
            'value' => ['en' => 'First', 'ar' => 'Arabic'],
        ]);
        $first = Translation::appRetrieve();
        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->assertSame($first, Translation::appRetrieve());
        $this->assertSame([], DB::getQueryLog());
        DB::disableQueryLog();

        $translation->setTranslation('value', 'en', 'Second')->save();
        $this->assertSame('Second', Translation::appRetrieve()['en']['product::performance_test.title']);
        $translation->delete();
        $this->assertArrayNotHasKey('product::performance_test.title', Translation::appRetrieve()['en']);
    }

    public function test_multilingual_bundles_fit_database_cache_packets_and_preserve_translations(): void
    {
        $this->configureLocales(['en', 'ar', 'af', 'af_NA'], 'en');
        $expected = Translation::appRetrieve();
        $this->assertGreaterThan(1048576, strlen(serialize($expected)));
        config([
            'cache.default' => 'database',
            'cache.stores.database.connection' => config('database.default'),
            'cache.stores.database.lock_connection' => config('database.default'),
        ]);
        Cache::forgetDriver('database');

        $this->assertSame($expected, Translation::appRetrieve());
        $this->assertSame($expected, Translation::appRetrieve());
        $values = DB::table('cache')->pluck('value');
        $this->assertNotEmpty($values);
        foreach ($values as $value) {
            $this->assertLessThan(1048576, strlen($value));
        }

        Translation::query()->create(['key' => 'product::packet_test.title', 'value' => ['en' => 'Updated']]);
        $this->assertSame('Updated', Translation::appRetrieve()['af_NA']['product::packet_test.title']);
    }

    public function test_locale_configuration_changes_invalidate_versions_and_cached_fallbacks(): void
    {
        Translation::query()->create([
            'key' => 'product::performance_test.title',
            'value' => ['en' => 'English', 'ar' => 'Arabic'],
        ]);
        $service = app(TranslationService::class);
        $initial = $service->getAppTranslationsVersion();
        Translation::appRetrieve();

        $this->configureLocales(['fr'], 'en');
        $englishFallbackVersion = $service->getAppTranslationsVersion();
        $this->assertNotSame($initial, $englishFallbackVersion);
        $this->assertSame(['fr'], array_keys(Translation::appRetrieve()));
        $this->assertSame('English', Translation::appRetrieve()['fr']['product::performance_test.title']);

        $this->configureLocales(['fr'], 'ar');
        $this->assertNotSame($englishFallbackVersion, $service->getAppTranslationsVersion());
        $this->assertSame('Arabic', Translation::appRetrieve()['fr']['product::performance_test.title']);
    }

    public function test_web_and_mobile_bundles_preserve_public_key_filtering(): void
    {
        foreach (['validation.performance_test', 'hr::employee_app.performance_test', 'delivery::driver_app.performance_test'] as $key) {
            Translation::query()->create(['key' => $key, 'value' => ['en' => 'Test']]);
        }
        $service = app(TranslationService::class);
        $this->assertArrayNotHasKey('validation.performance_test', $service->getAppTranslations()['en']);
        $this->assertArrayHasKey('hr::employee_app.performance_test', $service->getEmployeeAppTranslations()['en']);
        $this->assertArrayNotHasKey('delivery::driver_app.performance_test', $service->getEmployeeAppTranslations()['en']);
        $this->assertArrayHasKey('delivery::driver_app.performance_test', $service->getDriverAppTranslations()['en']);
        $this->assertArrayNotHasKey('hr::employee_app.performance_test', $service->getDriverAppTranslations()['en']);
    }

    public function test_local_file_edits_change_the_source_version_and_refresh_the_bundle(): void
    {
        app()->instance('env', 'local');
        $directory = sys_get_temp_dir().'/forkiva-translation-test-'.bin2hex(random_bytes(8));
        $filesystem = new Filesystem;
        $filesystem->makeDirectory($directory.'/en', 0755, true);
        app()->instance('translation.loader', new FileLoader($filesystem, [$directory]));

        try {
            $file = $directory.'/en/performance_test.php';
            file_put_contents($file, "<?php return ['title' => 'Old'];");
            $version = TranslationSourceVersion::get();
            $this->assertSame($version, TranslationSourceVersion::get());
            $this->assertSame('Old', Translation::appRetrieve()['en']['performance_test.title']);

            file_put_contents($file, "<?php return ['title' => 'New'];");
            $this->assertNotSame($version, TranslationSourceVersion::get());
            $this->assertSame('New', Translation::appRetrieve()['en']['performance_test.title']);
        } finally {
            $filesystem->deleteDirectory($directory);
        }
    }

    private function configureLocales(array $locales, string $fallback): void
    {
        $values = ['supported_locales' => $locales, 'default_locale' => $fallback];
        $settings = collect($values)->map(static fn (mixed $payload): array => [
            'payload' => serialize($payload),
            'is_encryptable' => false,
            'is_translatable' => false,
        ]);
        app()->instance('setting', new SettingRepository($settings));
    }
}
