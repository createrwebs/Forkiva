<?php

namespace Modules\User\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\Sanctum;
use Modules\Installer\Http\Requests\AdminRequest;
use Modules\User\Http\Requests\Api\V1\SaveCustomerRequest;
use Modules\User\Http\Requests\Api\V1\SaveUserRequest;
use Modules\User\Http\Requests\Api\V1\UpdateProfileRequest;
use Modules\User\Models\Role;
use Modules\User\Models\User;
use Modules\User\Rules\Username;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UsernameValidationTest extends TestCase
{
    use RefreshDatabase;

    public static function invalidUsernames(): array
    {
        return array_map(fn ($value) => [$value], [
            'person@example.com', 'person+tag@example.com', 'person@localhost',
            '0791234567', '+962791234567', '00962791234567',
            '+962 (79) 123-4567', '079.123.4567', ' 079 123 4567 ',
            '٠٧٩١٢٣٤٥٦٧', '12345',
        ]);
    }

    #[DataProvider('invalidUsernames')]
    public function test_rule_rejects_email_and_phone_styles(string $username): void
    {
        $validator = Validator::make(['username' => $username], ['username' => [new Username]]);

        $this->assertTrue($validator->fails());
        $this->assertSame(__('user::messages.username_not_email_or_phone'), $validator->errors()->first('username'));
    }

    public static function writeEndpoints(): array
    {
        return [
            ['POST', '/api/v1/users'],
            ['PUT', '/api/v1/users/1'],
            ['POST', '/api/v1/customers'],
            ['PUT', '/api/v1/customers/1'],
            ['PUT', '/api/v1/accounts/profile/update'],
        ];
    }

    #[DataProvider('writeEndpoints')]
    public function test_api_rejects_invalid_usernames_before_saving(string $method, string $url): void
    {
        $user = User::factory()->create(['username' => 'original_name', 'is_active' => true]);
        $user->assignRole(Role::query()->create([
            'name' => 'super_admin', 'display_name' => 'Admin', 'guard_name' => 'api',
        ]));
        Sanctum::actingAs($user);
        $count = User::count();

        foreach (['new@example.com', '+962 (79) 123-4567'] as $username) {
            $this->json($method, $url, ['username' => $username])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('username')
                ->assertJsonPath('errors.username.0', __('user::messages.username_not_email_or_phone'));
        }

        $this->assertSame($count, User::count());
        $this->assertSame('original_name', $user->fresh()->username);
    }

    public function test_all_requests_accept_normal_usernames_and_preserve_uniqueness_and_optional_customers(): void
    {
        $user = User::factory()->create(['username' => 'existing_user']);
        User::factory()->create(['username' => 'taken_user']);
        Sanctum::actingAs($user);

        foreach ([SaveUserRequest::class, SaveCustomerRequest::class, UpdateProfileRequest::class, AdminRequest::class] as $class) {
            $request = $class::create('/users/'.$user->id, 'PUT');
            $route = new Route('PUT', 'users/{id}', fn () => null);
            $route->bind($request);
            $request->setRouteResolver(fn () => $route);
            $rules = ['username' => $request->rules()['username']];

            foreach (['ayman', 'ayman_123', 'ayman.alaiwah', 'ayman-123', '123ayman', 'existing_user'] as $username) {
                $this->assertTrue(Validator::make(['username' => $username], $rules)->passes(), $class.': '.$username);
            }

            foreach (['person@example.com', '0791234567'] as $username) {
                $this->assertTrue(Validator::make(['username' => $username], $rules)->fails(), $class);
            }

            $this->assertSame($class === SaveCustomerRequest::class, Validator::make(['username' => null], $rules)->passes());

            if ($class !== AdminRequest::class) {
                $this->assertTrue(Validator::make(['username' => 'taken_user'], $rules)->fails());
            }
        }
    }
}
