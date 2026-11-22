<?php

namespace Modules\Pdf\Tests\Unit;

use Illuminate\Http\Request as HttpRequest;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Validator;
use Modules\Setting\Enums\SettingSection;
use Modules\Setting\Http\Requests\Api\V1\SaveSettingRequest;
use Modules\Setting\Repositories\SettingRepository;
use Tests\TestCase;

class PdfSettingValidationTest extends TestCase
{
    public function test_valid_dompdf_settings_are_accepted(): void
    {
        $validator = $this->validator($this->payload());

        $this->assertTrue($validator->passes(), $validator->errors()->toJson());
        $this->assertSame(
            ['pdf_default_driver', 'pdf_dompdf_remote_enabled', 'pdf_dompdf_chroot'],
            array_keys($validator->validated()),
        );
    }

    public function test_inactive_driver_fields_are_not_validated(): void
    {
        $payload = $this->payload();
        $payload['pdf_chrome_startup_timeout'] = 0;
        $payload['pdf_chrome_timeout'] = 'invalid';
        $payload['pdf_gotenberg_url'] = 'not-a-url';
        $payload['pdf_weasyprint_timeout'] = 0;

        $validator = $this->validator($payload);

        $this->assertTrue($validator->passes(), $validator->errors()->toJson());
        $this->assertArrayNotHasKey('pdf_chrome_timeout', $validator->validated());
        $this->assertArrayNotHasKey('pdf_gotenberg_url', $validator->validated());
        $this->assertArrayNotHasKey('pdf_weasyprint_timeout', $validator->validated());
    }

    public function test_active_chrome_fields_are_validated(): void
    {
        $payload = $this->payload();
        $payload['pdf_default_driver'] = 'chrome';
        $payload['pdf_chrome_startup_timeout'] = 0;
        $payload['pdf_chrome_timeout'] = 999;
        $payload['pdf_chrome_operation_timeout'] = 300001;

        $validator = $this->validator($payload);

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('pdf_chrome_startup_timeout'));
        $this->assertTrue($validator->errors()->has('pdf_chrome_timeout'));
        $this->assertTrue($validator->errors()->has('pdf_chrome_operation_timeout'));
    }

    public function test_active_browsershot_fields_are_validated(): void
    {
        $payload = $this->payload();
        $payload['pdf_default_driver'] = 'browsershot';
        unset($payload['pdf_browsershot_no_sandbox']);

        $validator = $this->validator($payload);

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('pdf_browsershot_no_sandbox'));
        $this->assertFalse($validator->errors()->has('pdf_chrome_timeout'));
    }

    public function test_active_cloudflare_requires_its_account_and_prevents_clearing_its_token(): void
    {
        config(['pdf.drivers.cloudflare.api_token' => 'configured-token']);
        $payload = $this->payload();
        $payload['pdf_default_driver'] = 'cloudflare';
        $payload['pdf_cloudflare_account_id'] = null;
        $payload['pdf_clear_secrets']['cloudflare_api_token'] = true;

        $validator = $this->validator($payload);

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('pdf_cloudflare_account_id'));
        $this->assertTrue($validator->errors()->has('pdf_clear_secrets.cloudflare_api_token'));
        $this->assertSame(
            __('pdf::settings.messages.cannot_clear_active_cloudflare_token'),
            $validator->errors()->first('pdf_clear_secrets.cloudflare_api_token'),
        );
        $this->assertFalse($validator->errors()->has('pdf_gotenberg_url'));
    }

    public function test_active_cloudflare_requires_a_token_when_none_is_stored_or_configured(): void
    {
        config(['pdf.drivers.cloudflare.api_token' => null]);
        app()->instance('setting', new SettingRepository(collect()));
        $payload = $this->payload();
        $payload['pdf_default_driver'] = 'cloudflare';
        $payload['pdf_cloudflare_account_id'] = 'account-id';

        $validator = $this->validator($payload);

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('encryptable.pdf_cloudflare_api_token'));
        $this->assertStringContainsString(
            'Cloudflare API token',
            $validator->errors()->first('encryptable.pdf_cloudflare_api_token'),
        );
    }

    public function test_active_cloudflare_accepts_a_replacement_token_when_clear_was_selected(): void
    {
        config(['pdf.drivers.cloudflare.api_token' => null]);
        app()->instance('setting', new SettingRepository(collect()));
        $payload = $this->payload();
        $payload['pdf_default_driver'] = 'cloudflare';
        $payload['pdf_cloudflare_account_id'] = 'account-id';
        $payload['encryptable']['pdf_cloudflare_api_token'] = 'replacement-token';
        $payload['pdf_clear_secrets']['cloudflare_api_token'] = true;

        $validator = $this->validator($payload);

        $this->assertTrue($validator->passes(), $validator->errors()->toJson());
    }

    public function test_active_gotenberg_and_weasyprint_fields_are_validated(): void
    {
        $gotenberg = $this->payload();
        $gotenberg['pdf_default_driver'] = 'gotenberg';
        $gotenberg['pdf_gotenberg_url'] = 'not-a-url';

        $gotenbergValidator = $this->validator($gotenberg);

        $this->assertTrue($gotenbergValidator->fails());
        $this->assertTrue($gotenbergValidator->errors()->has('pdf_gotenberg_url'));

        $weasyprint = $this->payload();
        $weasyprint['pdf_default_driver'] = 'weasyprint';
        $weasyprint['pdf_weasyprint_binary'] = '';
        $weasyprint['pdf_weasyprint_timeout'] = 0;

        $weasyprintValidator = $this->validator($weasyprint);

        $this->assertTrue($weasyprintValidator->fails());
        $this->assertTrue($weasyprintValidator->errors()->has('pdf_weasyprint_binary'));
        $this->assertTrue($weasyprintValidator->errors()->has('pdf_weasyprint_timeout'));
    }

    public function test_unknown_driver_is_rejected(): void
    {
        $payload = $this->payload();
        $payload['pdf_default_driver'] = 'unknown';

        $validator = $this->validator($payload);

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('pdf_default_driver'));
    }

    public function test_pdf_uses_the_existing_authorized_system_settings_routes(): void
    {
        $routes = app('router')->getRoutes();
        $read = $routes->match(HttpRequest::create('/api/v1/settings/pdf', 'GET'));
        $update = $routes->match(HttpRequest::create('/api/v1/settings/pdf/update', 'PUT'));

        $this->assertSame('api/v1/settings/{section}', $read->uri());
        $this->assertSame('api/v1/settings/{section}/update', $update->uri());
        $this->assertContains('can:admin.settings.edit', $read->gatherMiddleware());
        $this->assertContains('can:admin.settings.edit', $update->gatherMiddleware());
    }

    private function validator(array $payload): \Illuminate\Validation\Validator
    {
        $request = SaveSettingRequest::create('/api/v1/settings/pdf/update', 'PUT', $payload);
        $route = new Route('PUT', 'api/v1/settings/{section}/update', fn () => null);
        $route->bind($request);
        $route->setParameter('section', SettingSection::Pdf);
        $request->setRouteResolver(fn (): Route => $route);

        return Validator::make(
            $request->all(),
            $request->rules(),
            $request->messages(),
            $request->attributes(),
        );
    }

    private function payload(): array
    {
        return [
            'pdf_default_driver' => 'dompdf',
            'pdf_dompdf_remote_enabled' => false,
            'pdf_dompdf_chroot' => null,
            'pdf_browsershot_node_binary' => null,
            'pdf_browsershot_npm_binary' => null,
            'pdf_browsershot_chrome_path' => null,
            'pdf_browsershot_node_modules_path' => null,
            'pdf_browsershot_no_sandbox' => false,
            'pdf_chrome_binary' => null,
            'pdf_chrome_no_sandbox' => false,
            'pdf_chrome_startup_timeout' => 30,
            'pdf_chrome_timeout' => 30000,
            'pdf_chrome_operation_timeout' => 5000,
            'pdf_chrome_user_data_dir' => null,
            'pdf_cloudflare_account_id' => null,
            'pdf_gotenberg_url' => 'http://localhost:3000',
            'pdf_gotenberg_username' => null,
            'pdf_weasyprint_binary' => 'weasyprint',
            'pdf_weasyprint_timeout' => 10,
            'encryptable' => [
                'pdf_cloudflare_api_token' => null,
                'pdf_gotenberg_password' => null,
            ],
            'pdf_clear_secrets' => [
                'cloudflare_api_token' => false,
                'gotenberg_password' => false,
            ],
        ];
    }
}
