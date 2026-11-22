<?php

namespace Modules\Setting\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Modules\Media\Enum\MediaType;
use Modules\Media\Models\Media;
use Modules\Setting\Enums\SettingSection;
use Modules\Setting\Http\Requests\Api\V1\SaveSettingRequest;
use Modules\Setting\Models\Setting;
use Modules\Setting\Repositories\SettingRepository;
use Modules\Setting\Services\Setting\SettingService;
use Tests\TestCase;

class EmailLogoSettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['filesystems.default_private' => 'email_logo_test']);
        Storage::fake('email_logo_test');
        $this->refreshSettings();
    }

    public function test_email_logo_is_optional_and_only_accepts_existing_email_compatible_images(): void
    {
        $png = $this->media('image/png');
        $svg = $this->media('image/svg+xml');
        $pdf = $this->media('application/pdf');
        $base = ['logo' => $png->id, 'favicon' => $png->id];

        foreach ([$base, $base + ['email_logo' => null], $base + ['email_logo' => $png->id]] as $data) {
            $this->assertFalse($this->validator($data)->fails());
        }
        foreach ([$svg->id, $pdf->id, 999999] as $id) {
            $this->assertTrue($this->validator($base + ['email_logo' => $id])->errors()->has('email_logo'));
        }
    }

    public function test_email_logo_is_saved_returned_and_cleared_without_changing_the_application_logo(): void
    {
        $logo = $this->media('image/png');
        $emailLogo = $this->media('image/gif');
        $service = app(SettingService::class);
        $data = ['logo' => $logo->id, 'favicon' => $logo->id, 'email_logo' => $emailLogo->id];

        $service->update(SettingSection::Logo, $this->validator($data)->validated());
        $this->refreshSettings();

        $this->assertSame($emailLogo->id, setting('email_logo'));
        $this->assertSame('image/gif', setting('email_logo_mime_type'));
        $this->assertSame(base64_encode('image/gif'), Storage::disk('email_logo_test')->get('email_logo.base64'));
        $this->assertSame($emailLogo->id, $service->getSettings(SettingSection::Logo)['email_logo']->resource->id);

        $service->update(SettingSection::Logo, ['logo' => $logo->id, 'favicon' => $logo->id, 'email_logo' => null]);
        $this->refreshSettings();

        $this->assertNull(setting('email_logo'));
        $this->assertNull(setting('email_logo_mime_type'));
        $this->assertNull($service->getSettings(SettingSection::Logo)['email_logo']);
        Storage::disk('email_logo_test')->assertMissing('email_logo.base64');
        $this->assertSame($logo->id, setting('logo'));
        Storage::disk('email_logo_test')->assertExists('logo.base64');
    }

    public function test_older_clients_omitting_email_logo_preserve_it(): void
    {
        $logo = $this->media('image/png');
        $emailLogo = $this->media('image/gif');
        $service = app(SettingService::class);
        $service->update(SettingSection::Logo, ['logo' => $logo->id, 'favicon' => $logo->id, 'email_logo' => $emailLogo->id]);
        $this->refreshSettings();

        $service->update(SettingSection::Logo, ['logo' => $logo->id, 'favicon' => $logo->id]);
        $this->refreshSettings();

        $this->assertSame($emailLogo->id, setting('email_logo'));
        Storage::disk('email_logo_test')->assertExists('email_logo.base64');
    }

    public function test_email_logo_can_be_saved_while_application_branding_uses_static_defaults(): void
    {
        $emailLogo = $this->media('image/png');
        $data = ['logo' => null, 'favicon' => null, 'email_logo' => $emailLogo->id];

        app(SettingService::class)->update(SettingSection::Logo, $this->validator($data)->validated());
        $this->refreshSettings();

        $this->assertSame($emailLogo->id, setting('email_logo'));
        $this->assertNull(setting('logo'));
        $this->assertNull(setting('favicon'));
        Storage::disk('email_logo_test')->assertExists('email_logo.base64');
    }

    private function validator(array $data): \Illuminate\Validation\Validator
    {
        $request = SaveSettingRequest::create('/api/v1/settings/logo/update', 'PUT', $data);
        $route = new Route('PUT', 'api/v1/settings/{section}/update', fn () => null);
        $route->bind($request);
        $route->setParameter('section', SettingSection::Logo);
        $request->setRouteResolver(fn () => $route);

        return Validator::make($request->all(), $request->rules());
    }

    private function media(string $mime): Media
    {
        $path = fake()->uuid();
        Storage::disk('email_logo_test')->put($path, $mime);

        return Media::create(['type' => MediaType::File, 'name' => 'Test logo', 'mime_type' => $mime, 'path' => $path, 'disk' => 'email_logo_test']);
    }

    private function refreshSettings(): void
    {
        app()->instance('setting', new SettingRepository(Setting::allCached()));
    }
}
