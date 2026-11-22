<?php

namespace Modules\Inventory\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branch\Models\Branch;
use Modules\Inventory\Models\StockLot;
use Modules\Inventory\Services\Retail\RetailStockService;
use Tests\TestCase;

class RetailStockFefoTest extends TestCase
{
    use RefreshDatabase;

    public function test_fefo_deducts_earliest_expiring_lot_first(): void
    {
        $branch = Branch::factory()->create();

        $laterLot = StockLot::query()->create([
            'branch_id' => $branch->id,
            'product_id' => 101,
            'lot_number' => 'LOT-2027',
            'expires_at' => now()->addYear(),
            'quantity_remaining' => '10.0000',
            'unit_cost' => '25.0000',
        ]);
        $soonerLot = StockLot::query()->create([
            'branch_id' => $branch->id,
            'product_id' => 101,
            'lot_number' => 'LOT-2026',
            'expires_at' => now()->addMonth(),
            'quantity_remaining' => '3.0000',
            'unit_cost' => '20.0000',
        ]);

        $allocations = app(RetailStockService::class)->deductFefoLots($branch->id, 101, '5.0000');

        $this->assertCount(2, $allocations);
        $this->assertSame('LOT-2026', $allocations[0]['lot_number']);
        $this->assertSame('3.0000', $allocations[0]['deducted']);
        $this->assertSame('LOT-2027', $allocations[1]['lot_number']);
        $this->assertSame('2.0000', $allocations[1]['deducted']);

        $this->assertSame('0.0000', (string) $soonerLot->fresh()->quantity_remaining);
        $this->assertSame('8.0000', (string) $laterLot->fresh()->quantity_remaining);
    }
}
