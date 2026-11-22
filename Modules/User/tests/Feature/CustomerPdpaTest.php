<?php

namespace Modules\User\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\User\Enums\DefaultRole;
use Modules\User\Enums\TwoFactorMethod;
use Modules\User\Models\Role;
use Modules\User\Models\User;
use Modules\User\Services\CustomerPdpa\CustomerPdpaServiceInterface;
use Tests\TestCase;

class CustomerPdpaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (DefaultRole::cases() as $role) {
            Role::query()->updateOrCreate(
                ['name' => $role->value, 'guard_name' => config('auth.defaults.guard', 'web')],
                [
                    'display_name' => ['en' => str($role->value)->replace('_', ' ')->title()->toString()],
                    'built_in' => true,
                ]
            );
        }
    }

    public function test_export_returns_the_personal_data_held_about_a_customer(): void
    {
        $this->actingAs(User::factory()->create(['branch_id' => null]));
        $customer = $this->createCustomer();

        $data = app(CustomerPdpaServiceInterface::class)->exportPersonalData($customer->id);

        $this->assertSame('สมชาย ใจดี', $data['name']);
        $this->assertSame('somchai@example.com', $data['email']);
        $this->assertSame('123 ถนนทดสอบ', $data['address_line1']);
        $this->assertSame('1234567890123', $data['vat_tin']);
        $this->assertNotNull($data['pdpa_consent_at']);
    }

    public function test_anonymize_erases_personal_data_but_preserves_the_record(): void
    {
        $this->actingAs(User::factory()->create(['branch_id' => null]));
        $customer = $this->createCustomer();
        $id = $customer->id;

        $result = app(CustomerPdpaServiceInterface::class)->anonymize($id);

        $this->assertSame(__('user::customers.pdpa.anonymized_name'), $result->name);
        $this->assertNull($result->email);
        $this->assertNull($result->getRawOriginal('phone'));
        $this->assertNull($result->address_line1);
        $this->assertNull($result->vat_tin);
        $this->assertFalse($result->is_active);
        $this->assertNotNull($result->pdpa_anonymized_at);
        $this->assertDatabaseHas('users', ['id' => $id]);
    }

    public function test_anonymize_releases_an_enrolled_factor_before_erasing_contact_details(): void
    {
        $this->actingAs(User::factory()->create(['branch_id' => null]));
        $customer = $this->createCustomer([
            'two_factor_method' => TwoFactorMethod::Email,
            'two_factor_confirmed_at' => now(),
        ]);

        $result = app(CustomerPdpaServiceInterface::class)->anonymize($customer->id);

        $this->assertNull($result->email);
        $this->assertNull($result->two_factor_confirmed_at);
        $this->assertNotNull($result->pdpa_anonymized_at);
    }

    private function createCustomer(array $attributes = []): User
    {
        $customer = User::factory()->create(array_merge([
            'name' => 'สมชาย ใจดี',
            'phone_country_iso_code' => 'TH',
            'phone' => '0812345678',
            'email' => 'somchai@example.com',
            'address_line1' => '123 ถนนทดสอบ',
            'city' => 'กรุงเทพ',
            'postal_code' => '10110',
            'vat_tin' => '1234567890123',
            'pdpa_consent_at' => now(),
        ], $attributes));

        $customer->assignRole(DefaultRole::Customer);

        return $customer;
    }
}
