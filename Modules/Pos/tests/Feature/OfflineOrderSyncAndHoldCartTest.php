<?php

namespace Modules\Pos\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branch\Models\Branch;
use Modules\Pos\Services\HoldCart\PosHoldCartService;
use Modules\Pos\Services\OfflineSync\OfflineOrderSyncService;
use Tests\TestCase;

class OfflineOrderSyncAndHoldCartTest extends TestCase
{
    use RefreshDatabase;

    public function test_offline_sync_is_idempotent_by_client_uuid_and_flags_negative_stock(): void
    {
        $branch = Branch::factory()->create(['currency' => 'THB']);
        $service = app(OfflineOrderSyncService::class);

        $batch = [
            [
                'client_uuid' => '550e8400-e29b-41d4-a716-446655440001',
                'offline_reference' => 'OFF-R1-20261122-001',
                'payment_method' => 'cash',
                'total' => '120.00',
                'items' => [['product_id' => 1, 'quantity' => 2, 'unit_price' => '60.00']],
                'stock_shortage' => true,
            ],
        ];

        $first = $service->syncBatch($branch, $batch);
        $second = $service->syncBatch($branch, $batch);

        $this->assertSame('synced', $first[0]['status']);
        $this->assertTrue($first[0]['needs_stock_reconciliation']);
        $this->assertSame('duplicate', $second[0]['status']);
        $this->assertSame($first[0]['receipt_id'], $second[0]['receipt_id']);
    }

    public function test_hold_and_resume_parked_retail_cart(): void
    {
        $branch = Branch::factory()->create();
        $service = app(PosHoldCartService::class);

        $held = $service->hold($branch->id, 'Customer in blue shirt', [
            ['product_id' => 10, 'qty' => 2, 'price' => '35.00'],
        ]);

        $this->assertCount(1, $service->listActive($branch->id));

        $resumed = $service->resume($held->id);
        $this->assertSame('Customer in blue shirt', $resumed['label']);
        $this->assertCount(1, $resumed['items']);
        $this->assertCount(0, $service->listActive($branch->id));
    }
}
