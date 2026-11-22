<?php

namespace Modules\Report\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Branch\Models\Branch;
use Modules\Inventory\Enums\PurchaseStatus;
use Modules\Inventory\Models\Purchase;
use Modules\Inventory\Models\Supplier;
use Modules\Report\Reports\PurchaseVatReport;
use Modules\User\Models\User;
use Tests\TestCase;

class PurchaseVatReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_lists_purchases_with_supplier_tax_identity(): void
    {
        $branch = Branch::factory()->create(['currency' => 'THB']);
        $this->actingAs(User::factory()->create(['branch_id' => $branch->id]));

        $purchase = $this->createPurchase($branch, PurchaseStatus::Received, 1000, 70, 1070);

        $report = app(PurchaseVatReport::class);
        $rows = $report->query(new Request(['filters' => ['from' => '2020-01-01', 'to' => '2030-12-31']]))->get();

        $this->assertCount(1, $rows);
        $resource = $report->resource($rows->first());
        $this->assertSame($purchase->reference_number, $resource['reference_number']);
        $this->assertSame('บริษัท ผู้ขาย จำกัด', $resource['supplier_name']);
        $this->assertSame('1234567890123', $resource['supplier_vat_tin']);
        $this->assertSame('00000', $resource['supplier_branch_code']);
        $this->assertEqualsWithDelta(1000, $resource['sub_total']->amount(), 0.000001);
        $this->assertEqualsWithDelta(70, $resource['tax']->amount(), 0.000001);
        $this->assertEqualsWithDelta(1070, $resource['total']->amount(), 0.000001);
    }

    public function test_report_excludes_draft_and_cancelled_purchases(): void
    {
        $branch = Branch::factory()->create(['currency' => 'THB']);
        $this->actingAs(User::factory()->create(['branch_id' => $branch->id]));

        $this->createPurchase($branch, PurchaseStatus::Received, 1000, 70, 1070);
        $this->createPurchase($branch, PurchaseStatus::Draft, 500, 35, 535);
        $this->createPurchase($branch, PurchaseStatus::Cancelled, 800, 56, 856);

        $rows = app(PurchaseVatReport::class)
            ->query(new Request(['filters' => ['from' => '2020-01-01', 'to' => '2030-12-31']]))
            ->get();

        $this->assertCount(1, $rows);
        $this->assertEqualsWithDelta(1070, $rows->first()->total->amount(), 0.000001);
    }

    public function test_report_is_scoped_to_the_assigned_branch(): void
    {
        $branch = Branch::factory()->create(['currency' => 'THB']);
        $otherBranch = Branch::factory()->create(['currency' => 'THB']);
        $this->actingAs(User::factory()->create(['branch_id' => $branch->id]));

        $this->createPurchase($branch, PurchaseStatus::Received, 1000, 70, 1070);
        $this->createPurchase($otherBranch, PurchaseStatus::Received, 9000, 630, 9630);

        $rows = app(PurchaseVatReport::class)
            ->query(new Request(['filters' => ['from' => '2020-01-01', 'to' => '2030-12-31']]))
            ->get();

        $this->assertCount(1, $rows);
        $this->assertEqualsWithDelta(1070, $rows->first()->total->amount(), 0.000001);
    }

    private function createPurchase(Branch $branch, PurchaseStatus $status, float $subTotal, float $tax, float $total): Purchase
    {
        $supplier = Supplier::query()->create([
            'branch_id' => $branch->id,
            'name' => 'บริษัท ผู้ขาย จำกัด',
            'vat_tin' => '1234567890123',
            'tax_branch_code' => '00000',
        ]);

        // Bypass the branch-assignment creating event so purchases can be seeded
        // across branches to verify the report's branch scoping.
        return Purchase::withoutEvents(fn () => Purchase::query()->create([
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'reference_number' => 'PO-TEST-'.Str::random(6),
            'currency' => 'THB',
            'currency_rate' => 1,
            'discount' => 0,
            'tax' => $tax,
            'sub_total' => $subTotal,
            'total' => $total,
            'status' => $status,
        ]));
    }
}
