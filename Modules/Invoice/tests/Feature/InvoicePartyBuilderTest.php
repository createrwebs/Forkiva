<?php

namespace Modules\Invoice\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branch\Models\Branch;
use Modules\Invoice\Services\InvoicePartyBuilder\InvoicePartyBuilderServiceInterface;
use Modules\User\Models\User;
use Tests\TestCase;

class InvoicePartyBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_snapshot_includes_address_and_tax_identity(): void
    {
        $customer = User::factory()->create([
            'name' => 'บริษัท ลูกค้า จำกัด',
            'phone_country_iso_code' => 'TH',
            'phone' => '0812345678',
            'vat_tin' => '1234567890123',
            'registration_number' => '0105558000001',
            'address_line1' => '123 ถนนทดสอบ',
            'address_line2' => 'อาคารเอ ชั้น 5',
            'city' => 'กรุงเทพ',
            'state' => 'กรุงเทพมหานคร',
            'postal_code' => '10110',
        ]);

        $party = app(InvoicePartyBuilderServiceInterface::class)->createBuyer($customer);

        $this->assertSame('บริษัท ลูกค้า จำกัด', $party->legal_name);
        $this->assertSame('1234567890123', $party->vat_tin);
        $this->assertSame('0105558000001', $party->cr_number);
        $this->assertSame('123 ถนนทดสอบ', $party->address_line1);
        $this->assertSame('อาคารเอ ชั้น 5', $party->address_line2);
        $this->assertSame('กรุงเทพ', $party->city);
        $this->assertSame('กรุงเทพมหานคร', $party->state);
        $this->assertSame('10110', $party->postal_code);
        $this->assertSame('TH', $party->country_code);
    }

    public function test_seller_snapshot_includes_the_tax_branch_code(): void
    {
        $branch = Branch::factory()->create([
            'country_code' => 'TH',
            'currency' => 'THB',
            'vat_tin' => '9876543210987',
            'tax_branch_code' => '00001',
        ]);

        $party = app(InvoicePartyBuilderServiceInterface::class)->createSeller($branch);

        $this->assertSame('9876543210987', $party->vat_tin);
        $this->assertSame('00001', $party->tax_branch_code);
    }

    public function test_buyer_is_null_without_a_customer(): void
    {
        $this->assertNull(app(InvoicePartyBuilderServiceInterface::class)->createBuyer(null));
    }
}
