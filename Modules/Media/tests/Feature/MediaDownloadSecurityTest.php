<?php

namespace Modules\Media\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Modules\Media\Enum\MediaMime;
use Modules\Media\Enum\MediaType;
use Modules\Media\Models\Media;
use Modules\User\Models\User;
use Tests\TestCase;

class MediaDownloadSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_download_rejects_unsigned_requests(): void
    {
        $this->get('/api/v1/media/1/download')->assertForbidden();
    }

    public function test_svg_preview_rejects_unsigned_requests(): void
    {
        $this->get('/api/v1/media/1/preview')->assertForbidden();
    }

    public function test_download_accepts_a_short_lived_signed_url(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('media/report.txt', 'private report');

        $media = Media::query()->create([
            'type' => MediaType::File,
            'name' => 'report',
            'mime_type' => 'text/plain',
            'extension' => 'txt',
            'size' => 14,
            'path' => 'media/report.txt',
            'disk' => 'local',
        ]);

        $this->get($media->download_url)
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_download_rejects_expired_signed_urls(): void
    {
        $url = URL::temporarySignedRoute(
            'admin.media.download',
            now()->subMinute(),
            ['id' => 1],
        );

        $this->get($url)->assertForbidden();
    }

    public function test_svg_preview_rejects_expired_signed_urls(): void
    {
        $url = URL::temporarySignedRoute(
            'admin.media.preview',
            now()->subMinute(),
            ['id' => 1],
        );

        $this->get($url)->assertForbidden();
    }

    public function test_svg_upload_is_allowed_without_exposing_its_raw_storage_url(): void
    {
        $this->assertContains('svg', MediaMime::values());
        $this->assertStringContainsString('svg', MediaMime::forValidation());

        Storage::fake(config('filesystems.default'));
        Sanctum::actingAs(User::factory()->create());

        $this->withoutMiddleware()
            ->postJson('/api/v1/media', [
                'file' => UploadedFile::fake()->createWithContent(
                    'unsafe.svg',
                    '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
                ),
            ])
            ->assertCreated()
            ->assertJsonPath('body.url', null)
            ->assertJson(fn ($json) => $json
                ->whereType('body.download_url', 'string')
                ->whereType('body.preview_image_url', 'string')
                ->etc());
    }

    public function test_svg_preview_is_signed_and_sandboxed(): void
    {
        Storage::fake('public');
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(document.cookie)</script><rect width="20" height="20"/></svg>';
        Storage::disk('public')->put('media/unsafe.svg', $svg);

        $media = Media::query()->create([
            'type' => MediaType::File,
            'name' => 'unsafe.svg',
            'mime_type' => 'image/svg+xml',
            'extension' => 'svg',
            'size' => strlen($svg),
            'path' => 'media/unsafe.svg',
            'disk' => 'public',
        ]);

        $this->assertNull($media->url);

        $this->get($media->preview_image_url)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml; charset=UTF-8')
            ->assertHeader('Content-Disposition', 'inline; filename="preview.svg"')
            ->assertHeader('Content-Security-Policy', "sandbox; default-src 'none'; style-src 'unsafe-inline'; img-src data:")
            ->assertHeader('Cross-Origin-Resource-Policy', 'cross-origin')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertSee($svg, false);
    }

    public function test_existing_svg_media_does_not_expose_an_inline_storage_url(): void
    {
        Storage::fake('public');

        foreach ([
            ['mime_type' => 'image/svg+xml', 'extension' => 'png'],
            ['mime_type' => 'image/png', 'extension' => 'svg'],
        ] as $index => $file) {
            $path = "media/unsafe-$index.svg";
            Storage::disk('public')->put($path, '<svg xmlns="http://www.w3.org/2000/svg"/>');

            $media = Media::query()->create([
                'type' => MediaType::File,
                'name' => "unsafe-$index.svg",
                'mime_type' => $file['mime_type'],
                'extension' => $file['extension'],
                'size' => 42,
                'path' => $path,
                'disk' => 'public',
            ]);

            $this->assertNull($media->url);
            $this->assertStringContainsString('/api/v1/media/', $media->preview_image_url);
            $this->assertStringContainsString('/preview?', $media->preview_image_url);
            $this->assertNotNull($media->download_url);
        }
    }
}
