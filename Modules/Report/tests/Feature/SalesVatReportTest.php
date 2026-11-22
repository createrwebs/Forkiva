<?php

namespace Modules\Report\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Branch\Models\Branch;
use Modules\Invoice\Enums\InvoiceKind;
use Modules\Invoice\Enums\InvoicePartyType;
use Modules\Invoice\Enums\InvoicePurpose;
use Modules\Invoice\Enums\InvoiceStatus;
use Modules\Invoice\Enums\InvoiceType;
use Modules\Invoice\Models\Invoice;
use Modules\Invoice\Models\InvoiceParty;
use Modules\Report\Reports\SalesVatReport;
use Modules\User\Models\User;
use Tests\TestCase;

class SalesVatReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_lists_issued_invoices_with_buyer_tax_identity(): void
    {
        $branch = Branch::factory()->create(['currency' => 'THB']);
        $this->actingAs(User::factory()->create(['branch_id' => $branch->id]));

        $invoice = $this->createInvoice($branch, '2026-11-05', 1000, 70, 1070);

        $report = app(SalesVatReport::class);
        $rows = $report->query(new Request(['filters' => ['from' => '2026-11-01', 'to' => '2026-11-30']]))->get();

        $this->assertCount(1, $rows);
        $resource = $report->resource($rows->first());
        $this->assertSame($invoice->invoice_number, $resource['invoice_number']);
        $this->assertSame('บริษัท ทดสอบ จำกัด', $resource['buyer_name']);
        $this->assertSame('1234567890123', $resource['buyer_vat_tin']);
        $this->assertSame('00000', $resource['buyer_branch_code']);
        $this->assertEqualsWithDelta(1000, $resource['subtotal']->amount(), 0.000001);
        $this->assertEqualsWithDelta(70, $resource['tax_total']->amount(), 0.000001);
        $this->assertEqualsWithDelta(1070, $resource['total']->amount(), 0.000001);
    }

    public function test_report_is_scoped_to_the_assigned_branch(): void
    {
        $branch = Branch::factory()->create(['currency' => 'THB']);
        $otherBranch = Branch::factory()->create(['currency' => 'THB']);
        $this->actingAs(User::factory()->create(['branch_id' => $branch->id]));

        $this->createInvoice($branch, '2026-11-05', 1000, 70, 1070);
        $this->createInvoice($otherBranch, '2026-11-05', 9000, 630, 9630);

        $rows = app(SalesVatReport::class)
            ->query(new Request(['filters' => ['from' => '2026-11-01', 'to' => '2026-11-30']]))
            ->get();

        $this->assertCount(1, $rows);
        $this->assertEqualsWithDelta(1070, $rows->first()->total->amount(), 0.000001);
    }

    public function test_report_filters_by_the_issued_at_date_range(): void
    {
        $branch = Branch::factory()->create(['currency' => 'THB']);
        $this->actingAs(User::factory()->create(['branch_id' => $branch->id]));

        $this->createInvoice($branch, '2026-10-15', 500, 35, 535);
        $this->createInvoice($branch, '2026-11-05', 1000, 70, 1070);

        $rows = app(SalesVatReport::class)
            ->query(new Request(['filters' => ['from' => '2026-11-01', 'to' => '2026-11-30']]))
            ->get();

        $this->assertCount(1, $rows);
        $this->assertEqualsWithDelta(1070, $rows->first()->total->amount(), 0.000001);
    }

    private function createInvoice(Branch $branch, string $issuedAt, float $subtotal, float $tax, float $total): Invoice
    {
        $seller = InvoiceParty::query()->create([
            'type' => InvoicePartyType::Seller,
            'legal_name' => 'ร้านทดสอบ',
            'vat_tin' => '9876543210987',
            'tax_branch_code' => '00000',
            'country_code' => 'TH',
        ]);
        $buyer = InvoiceParty::query()->create([
            'type' => InvoicePartyType::Buyer,
            'legal_name' => 'บริษัท ทดสอบ จำกัด',
            'vat_tin' => '1234567890123',
            'tax_branch_code' => '00000',
            'country_code' => 'TH',
            'address_line1' => '123 ถนนทดสอบ',
            'city' => 'กรุงเทพ',
            'postal_code' => '10110',
        ]);

        return Invoice::withoutEvents(fn () => Invoice::query()->create([
            'branch_id' => $branch->id,
            'seller_party_id' => $seller->id,
            'buyer_party_id' => $buyer->id,
            'invoice_number' => 'INV-'.Str::random(8),
            'uuid' => (string) Str::uuid(),
            'type' => InvoiceType::Standard,
            'purpose' => InvoicePurpose::Original,
            'invoice_kind' => InvoiceKind::Standard,
            'currency' => 'THB',
            'currency_rate' => 1,
            'subtotal' => $subtotal,
            'tax_total' => $tax,
            'discount_total' => 0,
            'total' => $total,
            'rounding_adjustment' => 0,
            'paid_amount' => $total,
            'refunded_amount' => 0,
            'net_paid' => $total,
            'status' => InvoiceStatus::Issued,
            'issued_at' => $issuedAt,
        ]));
    }
}
