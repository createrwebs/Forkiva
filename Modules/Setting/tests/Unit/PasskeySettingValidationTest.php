<?php

namespace Modules\Setting\Tests\Unit;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as LaravelValidator;
use Modules\Setting\Enums\SettingSection;
use Modules\Setting\Http\Requests\Api\V1\SaveSettingRequest;
use Tests\TestCase;

class PasskeySettingValidationTest extends TestCase
{
    public function test_secure_matching_passkey_configuration_is_accepted(): void
    {
        $validator = $this->validator([
            'passkeys_enabled' => true,
            'passkeys_relying_party_id' => 'example.com',
            'passkeys_allowed_origins' => [
                'https://example.com',
                'https://admin.example.com:8443',
            ],
            'passkeys_timeout' => 60000,
        ]);

        $this->assertTrue($validator->passes(), $validator->errors()->toJson());
    }

    public function test_insecure_production_origin_is_rejected(): void
    {
        $validator = $this->validator([
            'passkeys_enabled' => true,
            'passkeys_relying_party_id' => 'example.com',
            'passkeys_allowed_origins' => ['http://example.com'],
            'passkeys_timeout' => 60000,
        ]);

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('passkeys_allowed_origins.0'));
    }

    public function test_origin_outside_the_relying_party_domain_is_rejected(): void
    {
        $validator = $this->validator([
            'passkeys_enabled' => true,
            'passkeys_relying_party_id' => 'example.com',
            'passkeys_allowed_origins' => ['https://example.net'],
            'passkeys_timeout' => 60000,
        ]);

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('passkeys_allowed_origins.0'));
    }

    private function validator(array $payload): LaravelValidator
    {
        $request = SaveSettingRequest::create('/api/v1/settings/passkeys/update', 'PUT', $payload);
        $route = new Route('PUT', 'api/v1/settings/{section}/update', fn () => null);
        $route->bind($request);
        $route->setParameter('section', SettingSection::Passkeys);
        $request->setRouteResolver(fn (): Route => $route);

        $validator = Validator::make(
            $request->all(),
            $request->rules(),
            [],
            $request->attributes(),
        );
        foreach ($request->after() as $callback) {
            $validator->after($callback);
        }

        return $validator;
    }
}
