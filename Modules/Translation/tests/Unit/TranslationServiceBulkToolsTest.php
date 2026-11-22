<?php

namespace Modules\Translation\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Modules\Translation\Enums\TranslationExportMode;
use Modules\Translation\Enums\TranslationImportMode;
use Modules\Translation\Models\Translation;
use Modules\Translation\Services\Translation\Export\TranslationExportServiceInterface;
use Modules\Translation\Services\Translation\Import\TranslationImportServiceInterface;
use Modules\Translation\Services\Translation\TranslationServiceInterface;
use Tests\TestCase;
use ZipArchive;

class TranslationServiceBulkToolsTest extends TestCase
{
    use RefreshDatabase;

    private TranslationExportServiceInterface $exportService;

    private TranslationImportServiceInterface $importService;

    private TranslationServiceInterface $translationService;

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

        Cache::flush();
        $this->exportService = app(TranslationExportServiceInterface::class);
        $this->importService = app(TranslationImportServiceInterface::class);
        $this->translationService = app(TranslationServiceInterface::class);
    }

    public function test_driver_app_translations_include_driver_panel_and_driver_app_keys(): void
    {
        Translation::query()->create([
            'key' => 'delivery::driver_panel.app.name',
            'value' => ['en' => 'Forkiva Driver', 'ar' => 'سائق فوركيفا'],
        ]);
        Translation::query()->create([
            'key' => 'delivery::driver_app.home.title',
            'value' => ['en' => 'Home', 'ar' => 'الرئيسية'],
        ]);
        Translation::query()->create([
            'key' => 'hr::employee_app.app.name',
            'value' => ['en' => 'Forkiva Employee', 'ar' => 'موظفي فوركيفا'],
        ]);

        $translations = $this->translationService->getDriverAppTranslations();

        $this->assertSame('Forkiva Driver', $translations['en']['delivery::driver_panel.app.name']);
        $this->assertSame('Home', $translations['en']['delivery::driver_app.home.title']);

        if (\Modules\Core\Support\ModuleFeature::enabled('Delivery')) {
            $this->assertSame('Direct', $translations['en']['delivery::driver_app.profile.app_lock_timeout_direct']);
            $this->assertSame('مباشر', $translations['ar']['delivery::driver_app.profile.app_lock_timeout_direct']);
            $this->assertSame('Confirm logout', $translations['en']['delivery::driver_app.profile.logout_confirm_title']);
            $this->assertSame('تأكيد تسجيل الخروج', $translations['ar']['delivery::driver_app.profile.logout_confirm_title']);
        }

        $this->assertArrayNotHasKey('hr::employee_app.app.name', $translations['en']);
    }

    public function test_exports_selected_locale_values(): void
    {
        Translation::query()->create([
            'key' => 'auth.failed',
            'value' => ['en' => 'Login failed', 'ar' => 'فشل تسجيل الدخول'],
        ]);

        $export = $this->exportService->exportLocale('ar', TranslationExportMode::Values);

        $this->assertSame('فشل تسجيل الدخول', $export['auth.failed']);
    }

    public function test_exports_empty_missing_template(): void
    {
        Translation::query()->create([
            'key' => 'auth.failed',
            'value' => ['en' => 'Login failed'],
        ]);

        $export = $this->exportService->exportLocale('ar', TranslationExportMode::EmptyMissing);

        $this->assertArrayHasKey('auth.failed', $export);
        $this->assertSame('', $export['auth.failed']);
    }

    public function test_import_preview_reports_unknown_empty_fill_and_overwrite_counts(): void
    {
        Translation::query()->create([
            'key' => 'auth.failed',
            'value' => ['en' => 'Login failed', 'ar' => 'قديم'],
        ]);
        Translation::query()->create([
            'key' => 'auth.password',
            'value' => ['en' => 'Password failed'],
        ]);

        $preview = $this->importService->previewImport('ar', $this->jsonFile([
            'auth.failed' => 'جديد',
            'auth.password' => 'كلمة المرور غير صحيحة',
            'auth.unknown' => 'Unknown',
            'auth.empty' => '',
        ]));

        $this->assertSame(2, $preview['valid']);
        $this->assertSame(1, $preview['unknown']);
        $this->assertSame(1, $preview['empty_skipped']);
        $this->assertSame(1, $preview['would_fill_missing']);
        $this->assertSame(1, $preview['would_overwrite']);
    }

    public function test_exports_all_supported_locales_as_zip(): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('ZIP extension is not available.');
        }

        Translation::query()->create([
            'key' => 'auth.failed',
            'value' => ['en' => 'Login failed', 'ar' => 'فشل تسجيل الدخول'],
        ]);

        $path = $this->exportService->exportAllLocales(TranslationExportMode::Values);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        $this->assertNotFalse($zip->locateName('translations-en.json'));
        $this->assertNotFalse($zip->locateName('translations-ar.json'));
        $this->assertStringContainsString('Login failed', $zip->getFromName('translations-en.json'));
        $zip->close();
        @unlink($path);
    }

    public function test_fill_missing_import_does_not_overwrite_existing_values(): void
    {
        Translation::query()->create([
            'key' => 'auth.failed',
            'value' => ['en' => 'Login failed', 'ar' => 'قديم'],
        ]);
        Translation::query()->create([
            'key' => 'auth.password',
            'value' => ['en' => 'Password failed'],
        ]);

        $result = $this->importService->import('ar', $this->jsonFile([
            'auth.failed' => 'جديد',
            'auth.password' => 'كلمة المرور غير صحيحة',
        ]), TranslationImportMode::FillMissing);

        $this->assertSame(1, $result['updated']);
        $this->assertSame('قديم', Translation::query()->where('key', 'auth.failed')->first()->getTranslation('value', 'ar'));
        $this->assertSame('كلمة المرور غير صحيحة', Translation::query()->where('key', 'auth.password')->first()->getTranslation('value', 'ar'));
    }

    public function test_overwrite_import_updates_existing_values(): void
    {
        Translation::query()->create([
            'key' => 'auth.failed',
            'value' => ['en' => 'Login failed', 'ar' => 'قديم'],
        ]);

        $result = $this->importService->import('ar', $this->jsonFile([
            'auth.failed' => 'جديد',
        ]), TranslationImportMode::Overwrite);

        $this->assertSame(1, $result['updated']);
        $this->assertSame('جديد', Translation::query()->where('key', 'auth.failed')->first()->getTranslation('value', 'ar'));
    }

    public function test_zip_import_updates_multiple_supported_languages(): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('ZIP extension is not available.');
        }

        Translation::query()->create([
            'key' => 'auth.failed',
            'value' => ['en' => 'Old', 'ar' => 'قديم'],
        ]);

        $result = $this->importService->import(null, $this->zipFile([
            'translations-en.json' => ['auth.failed' => 'New'],
            'translations-ar.json' => ['auth.failed' => 'جديد'],
        ]), TranslationImportMode::Overwrite);

        $this->assertSame(2, $result['updated']);
        $translation = Translation::query()->where('key', 'auth.failed')->first();
        $this->assertSame('New', $translation->getTranslation('value', 'en'));
        $this->assertSame('جديد', $translation->getTranslation('value', 'ar'));
    }

    public function test_import_preview_handles_non_string_existing_translation_values(): void
    {
        Translation::query()->create([
            'key' => 'auth.failed',
            'value' => [
                'en' => ['message' => 'Login failed in :seconds seconds'],
                'ar' => 'قديم',
            ],
        ]);

        $preview = $this->importService->previewImport('en', $this->jsonFile([
            'auth.failed' => 'Login failed',
        ]));

        $this->assertSame(1, $preview['valid']);
        $this->assertSame(1, $preview['placeholder_warning_count']);
    }

    private function jsonFile(array $payload): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'translations.json',
            json_encode($payload, JSON_UNESCAPED_UNICODE),
        );
    }

    private function zipFile(array $files): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'translation-import-').'.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($files as $name => $payload) {
            $zip->addFromString($name, json_encode($payload, JSON_UNESCAPED_UNICODE));
        }
        $zip->close();

        return new UploadedFile($path, 'translations.zip', 'application/zip', null, true);
    }
}
