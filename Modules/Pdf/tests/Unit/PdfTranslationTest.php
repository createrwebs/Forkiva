<?php

namespace Modules\Pdf\Tests\Unit;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Modules\Pdf\Enums\PdfDriver;
use Modules\Pdf\Services\Driver\PdfDriverConfigurator;
use Modules\Pdf\Services\Driver\PdfDriverVerifier;
use Modules\Pdf\Services\Rendering\ArabicHtmlProcessor;
use Tests\TestCase;

class PdfTranslationTest extends TestCase
{
    public function test_english_and_arabic_pdf_translations_have_identical_keys(): void
    {
        $english = require module_path('Pdf', 'lang/en/settings.php');
        $arabic = require module_path('Pdf', 'lang/ar/settings.php');

        $this->assertSame(
            array_keys(Arr::dot($english)),
            array_keys(Arr::dot($arabic)),
        );
    }

    public function test_driver_dependency_messages_use_the_active_locale(): void
    {
        app()->setLocale('ar');

        $status = (new PdfDriverVerifier(new PdfDriverConfigurator, new ArabicHtmlProcessor))->dependencyStatus(
            PdfDriver::Cloudflare,
            [],
        );

        $this->assertFalse($status['available']);
        $this->assertSame('معرف حساب Cloudflare ورمز API مطلوبان.', $status['message']);
    }

    public function test_weasyprint_dependency_accepts_an_executable_command_name(): void
    {
        $status = (new PdfDriverVerifier(new PdfDriverConfigurator, new ArabicHtmlProcessor))->dependencyStatus(
            PdfDriver::Weasyprint,
            ['binary' => 'php'],
        );

        $this->assertTrue($status['available']);
        $this->assertNull($status['message']);
    }

    public function test_cloudflare_verification_surfaces_structured_api_errors(): void
    {
        Http::fake([
            'api.cloudflare.com/*' => Http::response([
                'success' => false,
                'errors' => [[
                    'code' => 10000,
                    'message' => 'Authentication error',
                ]],
            ], 403),
        ]);

        $result = (new PdfDriverVerifier(new PdfDriverConfigurator, new ArabicHtmlProcessor))->verify(
            PdfDriver::Cloudflare,
            ['account_id' => 'account-id', 'api_token' => 'secret-token'],
        );

        $this->assertFalse($result->ready);
        $this->assertSame(
            'Cloudflare could not render the verification PDF: Authentication error [10000]',
            $result->message,
        );
    }
}
