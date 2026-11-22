<?php

namespace Modules\Setting\Tests\Unit;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Validator;
use Modules\Setting\Enums\SettingSection;
use Modules\Setting\Http\Requests\Api\V1\SaveSettingRequest;
use Tests\TestCase;

class PasswordRecoverySettingValidationTest extends TestCase
{
    public function test_disabled_email_sms_and_both_configurations_are_accepted(): void
    {
        foreach ([[false, false, false], [true, true, false], [true, false, true], [true, true, true]] as [$enabled, $email, $sms]) {
            $this->assertFalse($this->validator($enabled, $email, $sms)->fails());
        }
    }

    public function test_enabled_recovery_requires_at_least_one_channel(): void
    {
        $validator = $this->validator(true, false, false);
        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('password_recovery_enabled'));
    }

    private function validator(bool $enabled, bool $email, bool $sms): \Illuminate\Validation\Validator
    {
        $request = SaveSettingRequest::create('/api/v1/settings/password_recovery/update', 'PUT', [
            'password_recovery_enabled' => $enabled,
            'password_recovery_email_enabled' => $email,
            'password_recovery_sms_enabled' => $sms,
        ]);
        $route = new Route('PUT', 'api/v1/settings/{section}/update', fn () => null);
        $route->bind($request);
        $route->setParameter('section', SettingSection::PasswordRecovery);
        $request->setRouteResolver(fn () => $route);
        $validator = Validator::make($request->all(), $request->rules());
        foreach ($request->after() as $callback) {
            $validator->after($callback);
        }

        return $validator;
    }
}
