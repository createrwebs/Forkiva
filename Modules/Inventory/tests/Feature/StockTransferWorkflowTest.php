<?php

namespace Modules\Inventory\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Modules\Branch\Models\Branch;
use Modules\Core\Contracts\FinancialPostingBridgeInterface;
use Modules\Core\Services\Accounting\NullFinancialPostingBridge;
use Modules\Inventory\Enums\StockMovementType;
use Modules\Inventory\Enums\StockTransferStatus;
use Modules\Inventory\Models\Ingredient;
use Modules\Inventory\Models\InventoryCostBalance;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Models\StockTransfer;
use Modules\Inventory\Models\StockTransferItem;
use Modules\Inventory\Models\StockTransferReceipt;
use Modules\Inventory\Models\StockTransferReceiptItem;
use Modules\Inventory\Models\Unit;
use Modules\Inventory\Services\StockMovement\StockMovementServiceInterface;
use Modules\Inventory\Services\StockTransfer\StockTransferServiceInterface;
use Modules\User\Models\Permission;
use Modules\User\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class StockTransferWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Branch $sourceBranch;

    private Branch $destinationBranch;

    private Unit $unit;

    public function test_full_dispatch_and_idempotent_partial_receipts_preserve_frozen_cost(): void
    {
        [$source, $destination] = $this->ingredientPair(20, 4.25);
        $service = app(StockTransferServiceInterface::class);
        $transfer = $service->store($this->payload($source, $destination, '10.000000'));

        $this->assertSame(StockTransferStatus::Draft, $transfer->status);
        $service->approve($transfer->id);
        $dispatched = $service->dispatch($transfer->id);
        $item = $dispatched->items->firstOrFail();

        $this->assertSame(StockTransferStatus::Dispatched, $dispatched->status);
        $this->assertEqualsWithDelta(4.25, (float) $item->getRawOriginal('unit_cost'), 0.000001);
        $this->assertEqualsWithDelta(10, (float) $item->getRawOriginal('dispatched_quantity'), 0.000001);
        $this->assertEqualsWithDelta(10, (float) $source->fresh()->getRawOriginal('current_stock'), 0.000001);
        $this->assertSame(1, StockMovement::query()->where('type', StockMovementType::TransferOut)->count());

        $firstKey = fake()->uuid();
        $firstReceipt = $service->receive($transfer->id, [
            'idempotency_key' => $firstKey,
            'items' => [[
                'stock_transfer_item_id' => $item->id,
                'quantity' => '4.000000',
            ]],
        ]);
        $retriedReceipt = $service->receive($transfer->id, [
            'idempotency_key' => $firstKey,
            'items' => [[
                'stock_transfer_item_id' => $item->id,
                'quantity' => '4.000000',
            ]],
        ]);

        $this->assertSame($firstReceipt->id, $retriedReceipt->id);
        $this->assertSame(StockTransferStatus::PartiallyReceived, $transfer->fresh()->status);
        $this->assertEqualsWithDelta(4, (float) $destination->fresh()->getRawOriginal('current_stock'), 0.000001);

        $service->receive($transfer->id, [
            'idempotency_key' => fake()->uuid(),
            'items' => [[
                'stock_transfer_item_id' => $item->id,
                'quantity' => '6.000000',
            ]],
        ]);

        $this->assertSame(StockTransferStatus::Received, $transfer->fresh()->status);
        $this->assertEqualsWithDelta(10, (float) $destination->fresh()->getRawOriginal('current_stock'), 0.000001);
        $this->assertSame(2, StockMovement::query()->where('type', StockMovementType::TransferIn)->count());
        $destinationBalance = InventoryCostBalance::query()
            ->where('ingredient_id', $destination->id)
            ->firstOrFail();
        $this->assertEqualsWithDelta(4.25, (float) $destinationBalance->getRawOriginal('average_unit_cost'), 0.000001);
        $this->assertEqualsWithDelta(42.5, (float) $destinationBalance->getRawOriginal('inventory_value'), 0.000001);
    }

    /**
     * @return array{Ingredient, Ingredient}
     */
    private function ingredientPair(float $sourceStock, float $sourceCost): array
    {
        $source = Ingredient::query()->create([
            'name' => ['en' => 'Source ingredient '.fake()->uuid(), 'ar' => 'مكون مصدر'],
            'branch_id' => $this->sourceBranch->id,
            'unit_id' => $this->unit->id,
            'cost_per_unit' => $sourceCost,
            'alert_quantity' => 0,
            'current_stock' => $sourceStock,
            'is_returnable' => true,
        ]);
        $destination = Ingredient::query()->create([
            'name' => ['en' => 'Destination ingredient '.fake()->uuid(), 'ar' => 'مكون وجهة'],
            'branch_id' => $this->destinationBranch->id,
            'unit_id' => $this->unit->id,
            'cost_per_unit' => 0,
            'alert_quantity' => 0,
            'current_stock' => 0,
            'is_returnable' => true,
        ]);
        InventoryCostBalance::query()->create([
            'branch_id' => $this->sourceBranch->id,
            'ingredient_id' => $source->id,
            'quantity_on_hand' => $sourceStock,
            'inventory_value' => $sourceStock * $sourceCost,
            'average_unit_cost' => $sourceCost,
            'currency' => $this->sourceBranch->currency,
        ]);

        return [$source, $destination];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Ingredient $source, Ingredient $destination, string $quantity): array
    {
        return [
            'source_branch_id' => $this->sourceBranch->id,
            'destination_branch_id' => $this->destinationBranch->id,
            'expected_at' => now()->addDay()->toDateTimeString(),
            'notes' => 'Production transfer test',
            'items' => [[
                'source_ingredient_id' => $source->id,
                'destination_ingredient_id' => $destination->id,
                'quantity' => $quantity,
            ]],
        ];
    }

    public function test_dispatch_is_atomic_and_never_allows_insufficient_stock(): void
    {
        [$firstSource, $firstDestination] = $this->ingredientPair(10, 2);
        [$secondSource, $secondDestination] = $this->ingredientPair(5, 3);
        $service = app(StockTransferServiceInterface::class);
        $transfer = $service->store([
            'source_branch_id' => $this->sourceBranch->id,
            'destination_branch_id' => $this->destinationBranch->id,
            'items' => [
                [
                    'source_ingredient_id' => $firstSource->id,
                    'destination_ingredient_id' => $firstDestination->id,
                    'quantity' => '3',
                ],
                [
                    'source_ingredient_id' => $secondSource->id,
                    'destination_ingredient_id' => $secondDestination->id,
                    'quantity' => '3',
                ],
            ],
        ]);
        $service->approve($transfer->id);
        $secondSource->update(['current_stock' => 2]);

        try {
            $service->dispatch($transfer->id);
            $this->fail('An insufficient multi-line transfer was dispatched.');
        } catch (ValidationException) {
            $this->assertSame(StockTransferStatus::Approved, $transfer->fresh()->status);
            $this->assertEqualsWithDelta(10, (float) $firstSource->fresh()->getRawOriginal('current_stock'), 0.000001);
            $this->assertEqualsWithDelta(2, (float) $secondSource->fresh()->getRawOriginal('current_stock'), 0.000001);
            $this->assertSame(0, StockMovement::query()->whereIn('type', [
                StockMovementType::TransferOut,
                StockMovementType::TransferIn,
            ])->count());
        }
    }

    public function test_create_update_and_approve_require_available_source_stock(): void
    {
        $service = app(StockTransferServiceInterface::class);

        [$createSource, $createDestination] = $this->ingredientPair(5, 2);
        try {
            $service->store($this->payload($createSource, $createDestination, '6'));
            $this->fail('An over-available stock transfer was created.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items', $exception->errors());
        }

        [$updateSource, $updateDestination] = $this->ingredientPair(5, 2);
        $transfer = $service->store($this->payload($updateSource, $updateDestination, '2'));
        try {
            $service->update($transfer->id, $this->payload($updateSource, $updateDestination, '6'));
            $this->fail('An over-available stock transfer was updated.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items', $exception->errors());
            $this->assertEqualsWithDelta(
                2,
                (float) $transfer->fresh()->items->firstOrFail()->getRawOriginal('requested_quantity'),
                0.000001,
            );
        }

        [$approveSource, $approveDestination] = $this->ingredientPair(10, 2);
        $transfer = $service->store($this->payload($approveSource, $approveDestination, '6'));
        $approveSource->update(['current_stock' => 5]);
        try {
            $service->approve($transfer->id);
            $this->fail('An over-available stock transfer was approved.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items', $exception->errors());
            $this->assertSame(StockTransferStatus::Draft, $transfer->fresh()->status);
        }
    }

    public function test_api_rejects_quantities_outside_decimal_contract(): void
    {
        [$source, $destination] = $this->ingredientPair(10, 2);
        $this->grantPermissions(['admin.stock_transfers.create']);

        foreach (['0.0000001', '1000000000000'] as $quantity) {
            $this->postJson(
                '/api/v1/stock-transfers',
                $this->payload($source, $destination, $quantity),
            )
                ->assertUnprocessable()
                ->assertJsonValidationErrors('items.0.quantity');
        }

        $this->assertDatabaseCount('stock_transfers', 0);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function grantPermissions(array $permissions): void
    {
        foreach ($permissions as $permission) {
            Permission::query()->firstOrCreate([
                'name' => $permission,
                'guard_name' => 'api',
            ]);
        }

        $this->user->givePermissionTo($permissions);
    }

    public function test_service_rejects_quantities_outside_decimal_contract(): void
    {
        [$source, $destination] = $this->ingredientPair(10, 2);
        $service = app(StockTransferServiceInterface::class);

        foreach (['0.0000001', '1000000000000'] as $quantity) {
            try {
                $service->store($this->payload($source, $destination, $quantity));
                $this->fail('A quantity outside DECIMAL(18,6) was accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('items', $exception->errors());
            }
        }

        $this->assertDatabaseCount('stock_transfers', 0);
    }

    public function test_draft_update_preserves_existing_transfer_item_rows(): void
    {
        [$source, $destination] = $this->ingredientPair(10, 2);
        $service = app(StockTransferServiceInterface::class);
        $transfer = $service->store($this->payload($source, $destination, '2'));
        $originalItem = $transfer->items->firstOrFail();

        $updated = $service->update($transfer->id, $this->payload($source, $destination, '3'));
        $updatedItem = $updated->fresh()->items->firstOrFail();

        $this->assertSame($originalItem->id, $updatedItem->id);
        $this->assertEqualsWithDelta(3, (float) $updatedItem->getRawOriginal('requested_quantity'), 0.000001);
    }

    public function test_void_before_first_receipt_creates_exact_reversal_and_blocks_direct_correction(): void
    {
        [$source, $destination] = $this->ingredientPair(12, 7.125);
        $service = app(StockTransferServiceInterface::class);
        $transfer = $service->store($this->payload($source, $destination, '5'));
        $service->approve($transfer->id);
        $dispatched = $service->dispatch($transfer->id);
        $outbound = $dispatched->items->firstOrFail()->outboundMovement;

        try {
            app(StockMovementServiceInterface::class)->correct($outbound->id, '5', 'Wrong line');
            $this->fail('A transfer-owned movement was directly corrected.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $voided = $service->void($transfer->id, 'Dispatch created in error');

        $this->assertSame(StockTransferStatus::Voided, $voided->status);
        $this->assertEqualsWithDelta(12, (float) $source->fresh()->getRawOriginal('current_stock'), 0.000001);
        $reversal = $voided->items->firstOrFail()->outboundReversalMovement;
        $this->assertSame(StockMovementType::TransferIn, $reversal->type);
        $this->assertSame($outbound->id, $reversal->reversal_of_id);
        $this->assertSame($outbound->getRawOriginal('unit_cost'), $reversal->getRawOriginal('unit_cost'));
    }

    public function test_close_assigns_all_remaining_quantity_to_discrepancy(): void
    {
        [$source, $destination] = $this->ingredientPair(8, 3.5);
        $service = app(StockTransferServiceInterface::class);
        $transfer = $service->store($this->payload($source, $destination, '8'));
        $service->approve($transfer->id);
        $dispatched = $service->dispatch($transfer->id);
        $item = $dispatched->items->firstOrFail();
        $service->receive($transfer->id, [
            'idempotency_key' => fake()->uuid(),
            'items' => [[
                'stock_transfer_item_id' => $item->id,
                'quantity' => '6',
            ]],
        ]);

        $closed = $service->close($transfer->id, 'Two kilograms damaged in transit');

        $this->assertSame(StockTransferStatus::ClosedWithDiscrepancy, $closed->status);
        $this->assertEqualsWithDelta(
            2,
            (float) $closed->items->firstOrFail()->getRawOriginal('discrepancy_quantity'),
            0.000001,
        );
        $this->assertSame('Two kilograms damaged in transit', $closed->items->firstOrFail()->discrepancy_reason);
    }

    public function test_receipt_cannot_exceed_remaining_and_first_receipt_permanently_blocks_void(): void
    {
        [$source, $destination] = $this->ingredientPair(8, 3.5);
        $service = app(StockTransferServiceInterface::class);
        $transfer = $service->store($this->payload($source, $destination, '5'));
        $service->approve($transfer->id);
        $item = $service->dispatch($transfer->id)->items->firstOrFail();

        try {
            $service->receive($transfer->id, [
                'idempotency_key' => fake()->uuid(),
                'items' => [[
                    'stock_transfer_item_id' => $item->id,
                    'quantity' => '5.000001',
                ]],
            ]);
            $this->fail('An over-receipt was accepted.');
        } catch (ValidationException) {
            $this->assertSame(0, $transfer->receipts()->count());
            $this->assertEqualsWithDelta(0, (float) $destination->fresh()->current_stock, 0.000001);
        }

        $service->receive($transfer->id, [
            'idempotency_key' => fake()->uuid(),
            'items' => [[
                'stock_transfer_item_id' => $item->id,
                'quantity' => '1',
            ]],
        ]);

        $this->expectException(HttpException::class);
        $service->void($transfer->id, 'Cannot void after receipt');
    }

    public function test_receipt_idempotency_key_rejects_different_retry_payload(): void
    {
        [$source, $destination] = $this->ingredientPair(8, 3.5);
        $service = app(StockTransferServiceInterface::class);
        $transfer = $service->store($this->payload($source, $destination, '5'));
        $service->approve($transfer->id);
        $item = $service->dispatch($transfer->id)->items->firstOrFail();
        $idempotencyKey = fake()->uuid();

        $service->receive($transfer->id, [
            'idempotency_key' => $idempotencyKey,
            'items' => [[
                'stock_transfer_item_id' => $item->id,
                'quantity' => '1',
            ]],
        ]);

        try {
            $service->receive($transfer->id, [
                'idempotency_key' => $idempotencyKey,
                'items' => [[
                    'stock_transfer_item_id' => $item->id,
                    'quantity' => '2',
                ]],
            ]);
            $this->fail('A different receipt payload reused the same idempotency key.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
            $this->assertSame(1, StockTransferReceipt::query()->count());
            $this->assertEqualsWithDelta(1, (float) $destination->fresh()->getRawOriginal('current_stock'), 0.000001);
        }
    }

    public function test_definition_rejects_same_branch_cross_currency_and_unit_mismatch(): void
    {
        [$source, $destination] = $this->ingredientPair(10, 2);
        $service = app(StockTransferServiceInterface::class);

        foreach ([
            [
                'source_branch_id' => $this->sourceBranch->id,
                'destination_branch_id' => $this->sourceBranch->id,
                'items' => [],
            ],
            [
                ...$this->payload($source, $destination, '1'),
                'destination_branch_id' => Branch::factory()->create(['currency' => 'USD'])->id,
            ],
        ] as $payload) {
            try {
                $service->store($payload);
                $this->fail('An invalid transfer definition was accepted.');
            } catch (HttpException $exception) {
                $this->assertSame(422, $exception->getStatusCode());
            }
        }

        $otherUnit = Unit::query()->create([
            'name' => ['en' => 'Piece', 'ar' => 'قطعة'],
            'symbol' => ['en' => 'pc', 'ar' => 'قطعة'],
            'type' => 'count',
        ]);
        $destination->update(['unit_id' => $otherUnit->id]);

        $this->expectException(HttpException::class);
        $service->store($this->payload($source, $destination, '1'));
    }

    public function test_assigned_user_can_see_either_side_but_source_and_destination_actions_are_separated(): void
    {
        [$source, $destination] = $this->ingredientPair(10, 2);
        $service = app(StockTransferServiceInterface::class);
        $transfer = $service->store($this->payload($source, $destination, '2'));

        $sourceUser = User::factory()->create([
            'branch_id' => $this->sourceBranch->id,
            'is_active' => true,
        ]);
        $destinationUser = User::factory()->create([
            'branch_id' => $this->destinationBranch->id,
            'is_active' => true,
        ]);
        $unrelatedUser = User::factory()->create([
            'branch_id' => Branch::factory()->create()->id,
            'is_active' => true,
        ]);

        $this->actingAs($destinationUser, 'api');
        $this->assertNotNull(StockTransfer::query()->find($transfer->id));
        try {
            $service->approve($transfer->id);
            $this->fail('A destination-only user approved a source action.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }

        $this->actingAs($sourceUser, 'api');
        $this->assertNotNull(StockTransfer::query()->find($transfer->id));
        $service->approve($transfer->id);

        $this->actingAs($unrelatedUser);
        $this->assertNull(StockTransfer::query()->find($transfer->id));
    }

    public function test_http_actions_require_their_individual_permissions(): void
    {
        [$source, $destination] = $this->ingredientPair(10, 2);
        $transfer = app(StockTransferServiceInterface::class)
            ->store($this->payload($source, $destination, '2'));

        $requests = [
            fn () => $this->getJson('/api/v1/stock-transfers'),
            fn () => $this->getJson("/api/v1/stock-transfers/$transfer->id"),
            fn () => $this->getJson('/api/v1/stock-transfers/form/meta'),
            fn () => $this->getJson('/api/v1/stock-transfers/form/ingredients'),
            fn () => $this->postJson('/api/v1/stock-transfers', []),
            fn () => $this->putJson("/api/v1/stock-transfers/$transfer->id", []),
            fn () => $this->postJson("/api/v1/stock-transfers/$transfer->id/approve"),
            fn () => $this->postJson("/api/v1/stock-transfers/$transfer->id/dispatch"),
            fn () => $this->postJson("/api/v1/stock-transfers/$transfer->id/receive", []),
            fn () => $this->postJson("/api/v1/stock-transfers/$transfer->id/close", []),
            fn () => $this->postJson("/api/v1/stock-transfers/$transfer->id/cancel", []),
            fn () => $this->postJson("/api/v1/stock-transfers/$transfer->id/void", []),
        ];

        foreach ($requests as $request) {
            $request()->assertForbidden();
        }
    }

    public function test_destination_branch_cannot_approve_over_http(): void
    {
        [$source, $destination] = $this->ingredientPair(10, 2);
        $transfer = app(StockTransferServiceInterface::class)
            ->store($this->payload($source, $destination, '2'));
        Permission::query()->firstOrCreate([
            'name' => 'admin.stock_transfers.approve',
            'guard_name' => 'api',
        ]);

        $destinationUser = User::factory()->create([
            'branch_id' => $this->destinationBranch->id,
            'is_active' => true,
        ]);
        $destinationUser->givePermissionTo('admin.stock_transfers.approve');
        $this->actingAs($destinationUser);
        $this->postJson("/api/v1/stock-transfers/$transfer->id/approve")->assertNotFound();
    }

    public function test_source_branch_cannot_receive_over_http(): void
    {
        [$source, $destination] = $this->ingredientPair(10, 2);
        $transfer = app(StockTransferServiceInterface::class)
            ->store($this->payload($source, $destination, '2'));
        Permission::query()->firstOrCreate([
            'name' => 'admin.stock_transfers.receive',
            'guard_name' => 'api',
        ]);

        $sourceUser = User::factory()->create([
            'branch_id' => $this->sourceBranch->id,
            'is_active' => true,
        ]);
        $sourceUser->givePermissionTo('admin.stock_transfers.receive');
        $this->actingAs($sourceUser);
        $this->postJson("/api/v1/stock-transfers/$transfer->id/receive", [
            'idempotency_key' => fake()->uuid(),
            'items' => [[
                'stock_transfer_item_id' => $transfer->items->firstOrFail()->id,
                'quantity' => '1',
            ]],
        ])->assertNotFound();
    }

    public function test_transfer_children_apply_the_same_two_branch_visibility_scope(): void
    {
        $this->assertArrayHasKey(
            'stock_transfer_branch_permission',
            (new StockTransferItem)->getGlobalScopes(),
        );
        $this->assertArrayHasKey(
            'stock_transfer_branch_permission',
            (new StockTransferReceipt)->getGlobalScopes(),
        );
        $this->assertArrayHasKey(
            'stock_transfer_branch_permission',
            (new StockTransferReceiptItem)->getGlobalScopes(),
        );

        [$source, $destination] = $this->ingredientPair(10, 2);
        $service = app(StockTransferServiceInterface::class);
        $transfer = $service->store($this->payload($source, $destination, '3'));
        $service->approve($transfer->id);
        $item = $service->dispatch($transfer->id)->items->firstOrFail();
        $receipt = $service->receive($transfer->id, [
            'idempotency_key' => fake()->uuid(),
            'items' => [[
                'stock_transfer_item_id' => $item->id,
                'quantity' => '1',
            ]],
        ]);
        $receiptItemId = $receipt->items->firstOrFail()->id;

        $sourceUser = User::factory()->create([
            'branch_id' => $this->sourceBranch->id,
            'is_active' => true,
        ]);
        $destinationUser = User::factory()->create([
            'branch_id' => $this->destinationBranch->id,
            'is_active' => true,
        ]);
        $unrelatedUser = User::factory()->create([
            'branch_id' => Branch::factory()->create()->id,
            'is_active' => true,
        ]);

        foreach ([$sourceUser, $destinationUser] as $branchUser) {
            $this->actingAs($branchUser);

            $this->assertNotNull(StockTransferItem::query()->find($item->id));
            $this->assertNotNull(StockTransferReceipt::query()->find($receipt->id));
            $this->assertNotNull(StockTransferReceiptItem::query()->find($receiptItemId));
        }

        $this->actingAs($unrelatedUser);

        $this->assertNull(StockTransfer::query()->find($transfer->id));
        $this->assertNull(StockTransferItem::query()->find($item->id));
        $this->assertNull(StockTransferReceipt::query()->find($receipt->id));
        $this->assertNull(StockTransferReceiptItem::query()->find($receiptItemId));
    }

    public function test_api_resources_expose_only_server_authorized_actions(): void
    {
        [$source, $destination] = $this->ingredientPair(10, 2);
        $this->grantPermissions([
            'admin.stock_transfers.index',
            'admin.stock_transfers.show',
            'admin.stock_transfers.create',
            'admin.stock_transfers.edit',
            'admin.stock_transfers.approve',
            'admin.stock_transfers.dispatch',
            'admin.stock_transfers.receive',
            'admin.stock_transfers.close',
            'admin.stock_transfers.cancel',
            'admin.stock_transfers.void',
        ]);

        $response = $this->postJson('/api/v1/stock-transfers', $this->payload($source, $destination, '3'))
            ->assertCreated();
        $id = (int) $response->json('body.id');
        $response->assertJsonPath('body.allow_edit', true)
            ->assertJsonPath('body.allow_approve', true)
            ->assertJsonPath('body.allow_dispatch', false)
            ->assertJsonPath('body.allow_receive', false)
            ->assertJsonCount(1, 'body.items')
            ->assertJsonPath('body.source_branch.id', $this->sourceBranch->id)
            ->assertJsonPath('body.destination_branch.id', $this->destinationBranch->id);

        $this->postJson("/api/v1/stock-transfers/$id/approve")
            ->assertOk()
            ->assertJsonPath('body.allow_edit', false)
            ->assertJsonPath('body.allow_approve', false)
            ->assertJsonPath('body.allow_dispatch', true)
            ->assertJsonCount(1, 'body.items');

        $this->getJson('/api/v1/stock-transfers')
            ->assertOk()
            ->assertJsonPath('body.data.0.id', $id);
    }

    public function test_receipt_action_returns_complete_resource_graph(): void
    {
        [$source, $destination] = $this->ingredientPair(10, 2);
        $this->grantPermissions([
            'admin.stock_transfers.create',
            'admin.stock_transfers.approve',
            'admin.stock_transfers.dispatch',
            'admin.stock_transfers.receive',
        ]);

        $transferId = (int) $this->postJson(
            '/api/v1/stock-transfers',
            $this->payload($source, $destination, '3'),
        )->assertCreated()->json('body.id');
        $this->postJson("/api/v1/stock-transfers/$transferId/approve")->assertOk();
        $dispatch = $this->postJson("/api/v1/stock-transfers/$transferId/dispatch")
            ->assertOk()
            ->assertJsonCount(1, 'body.items');
        $itemId = (int) $dispatch->json('body.items.0.id');

        $this->postJson("/api/v1/stock-transfers/$transferId/receive", [
            'idempotency_key' => fake()->uuid(),
            'items' => [[
                'stock_transfer_item_id' => $itemId,
                'quantity' => '1.000000',
            ]],
        ])
            ->assertCreated()
            ->assertJsonCount(1, 'body.items')
            ->assertJsonPath('body.items.0.stock_transfer_item_id', $itemId)
            ->assertJsonPath('body.received_by', $this->user->name);
    }

    public function test_ingredient_search_is_bounded_and_keeps_selected_options(): void
    {
        $this->grantPermissions(['admin.stock_transfers.create']);
        [$selected] = $this->ingredientPair(10, 2);

        for ($index = 0; $index < 30; $index++) {
            Ingredient::query()->create([
                'name' => ['en' => "Search source $index", 'ar' => "مصدر $index"],
                'branch_id' => $this->sourceBranch->id,
                'unit_id' => $this->unit->id,
                'cost_per_unit' => 1,
                'alert_quantity' => 0,
                'current_stock' => 1,
                'is_returnable' => true,
            ]);
        }

        $this->getJson('/api/v1/stock-transfers/form/ingredients?'.http_build_query([
            'branch_id' => $this->sourceBranch->id,
            'role' => 'source',
        ]))
            ->assertOk()
            ->assertJsonCount(25, 'body');

        $this->getJson('/api/v1/stock-transfers/form/meta?'.http_build_query([
            'source_branch_id' => $this->sourceBranch->id,
            'destination_branch_id' => $this->destinationBranch->id,
            'source_ingredient_ids' => [$selected->id],
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'body.source_ingredients')
            ->assertJsonPath('body.source_ingredients.0.id', $selected->id);
    }

    public function test_form_meta_and_ingredient_options_requests_validate_lookup_inputs(): void
    {
        $this->grantPermissions(['admin.stock_transfers.create']);

        $this->getJson('/api/v1/stock-transfers/form/meta?'.http_build_query([
            'source_branch_id' => 999999,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('source_branch_id');

        $this->getJson('/api/v1/stock-transfers/form/meta?'.http_build_query([
            'source_branch_id' => $this->sourceBranch->id,
            'destination_branch_id' => $this->sourceBranch->id,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('destination_branch_id');

        $this->getJson('/api/v1/stock-transfers/form/ingredients')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['branch_id', 'role']);

        $this->getJson('/api/v1/stock-transfers/form/ingredients?'.http_build_query([
            'branch_id' => $this->sourceBranch->id,
            'role' => 'invalid',
            'ids' => [999999],
            'unit_id' => 999999,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role', 'ids.0', 'unit_id']);
    }

    public function test_ingredient_list_is_cached_and_invalidated_by_ingredient_changes(): void
    {
        [$ingredient] = $this->ingredientPair(10, 2);
        Cache::tags(['ingredients', 'units'])->flush();
        DB::enableQueryLog();

        $first = Ingredient::list(
            branchId: $this->sourceBranch->id,
            limit: 25,
            ids: [$ingredient->id],
        );
        $queriesAfterFirstCall = count(DB::getQueryLog());

        $second = Ingredient::list(
            branchId: $this->sourceBranch->id,
            limit: 25,
            ids: [$ingredient->id],
        );

        $this->assertGreaterThan(0, $queriesAfterFirstCall);
        $this->assertSame($queriesAfterFirstCall, count(DB::getQueryLog()));
        $this->assertSame($first->all(), $second->all());

        $ingredient->update(['current_stock' => 7.5]);
        $queriesAfterUpdate = count(DB::getQueryLog());
        $refreshed = Ingredient::list(
            branchId: $this->sourceBranch->id,
            limit: 25,
            ids: [$ingredient->id],
        );

        $this->assertGreaterThan($queriesAfterUpdate, count(DB::getQueryLog()));
        $this->assertSame(7.5, $refreshed->first()['current_stock']);
    }

    public function test_approved_document_definition_is_immutable(): void
    {
        [$source, $destination] = $this->ingredientPair(10, 2);
        $service = app(StockTransferServiceInterface::class);
        $transfer = $service->store($this->payload($source, $destination, '3'));
        $service->approve($transfer->id);

        try {
            $service->update($transfer->id, [
                ...$this->payload($source, $destination, '4'),
                'notes' => 'Attempt to mutate approved transfer',
            ]);
            $this->fail('An approved transfer was edited.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->expectException(LogicException::class);
        $transfer->fresh()->update(['notes' => 'Direct mutation']);
    }

    public function test_only_draft_transfer_can_be_soft_deleted_and_audit_items_are_preserved(): void
    {
        [$source, $destination] = $this->ingredientPair(10, 2);
        $service = app(StockTransferServiceInterface::class);
        $draft = $service->store($this->payload($source, $destination, '3'));
        $draftItemId = $draft->items->firstOrFail()->id;

        $this->assertTrue($draft->delete());
        $this->assertNull(StockTransfer::query()->find($draft->id));
        $this->assertNotNull(StockTransfer::withTrashed()->find($draft->id)?->deleted_at);
        $this->assertDatabaseHas('stock_transfer_items', ['id' => $draftItemId]);
        $this->assertSame(
            $draft->id,
            StockTransferItem::query()->findOrFail($draftItemId)->transfer->id,
        );

        try {
            StockTransfer::withTrashed()->findOrFail($draft->id)->forceDelete();
            $this->fail('A stock transfer was permanently deleted.');
        } catch (LogicException) {
            $this->assertNotNull(StockTransfer::withTrashed()->find($draft->id));
        }

        $approved = $service->store($this->payload($source, $destination, '2'));
        $service->approve($approved->id);

        try {
            $approved->fresh()->delete();
            $this->fail('An approved stock transfer was deleted.');
        } catch (LogicException) {
            $this->assertNotNull(StockTransfer::query()->find($approved->id));
        }
    }

    public function test_receipt_soft_delete_preserves_audit_and_idempotent_stock_state(): void
    {
        [$source, $destination] = $this->ingredientPair(10, 2);
        $service = app(StockTransferServiceInterface::class);
        $transfer = $service->store($this->payload($source, $destination, '3'));
        $service->approve($transfer->id);
        $item = $service->dispatch($transfer->id)->items->firstOrFail();
        $idempotencyKey = fake()->uuid();
        $payload = [
            'idempotency_key' => $idempotencyKey,
            'items' => [[
                'stock_transfer_item_id' => $item->id,
                'quantity' => '1',
            ]],
        ];
        $receipt = $service->receive($transfer->id, $payload);
        $receiptItemId = $receipt->items->firstOrFail()->id;
        $movementCount = StockMovement::query()->count();
        $destinationStock = $destination->fresh()->current_stock;

        $this->assertTrue($receipt->delete());
        $this->assertNull(StockTransferReceipt::query()->find($receipt->id));
        $this->assertSoftDeleted('stock_transfer_receipts', ['id' => $receipt->id]);
        $this->assertDatabaseHas('stock_transfer_receipt_items', ['id' => $receiptItemId]);
        $this->assertSame(
            $receipt->id,
            StockTransferReceiptItem::query()->findOrFail($receiptItemId)->receipt->id,
        );

        $show = $service->show($transfer->id);
        $this->assertSame(1, $show->receipts_count);
        $this->assertTrue($show->receipts->contains('id', $receipt->id));

        $retried = $service->receive($transfer->id, $payload);
        $this->assertSame($receipt->id, $retried->id);
        $this->assertTrue($retried->trashed());
        $this->assertSame($movementCount, StockMovement::query()->count());
        $this->assertSame($destinationStock, $destination->fresh()->current_stock);

        try {
            StockTransferReceipt::withTrashed()->findOrFail($receipt->id)->forceDelete();
            $this->fail('A stock transfer receipt was permanently deleted.');
        } catch (LogicException) {
            $this->assertNotNull(StockTransferReceipt::withTrashed()->find($receipt->id));
        }
    }

    public function test_delete_endpoint_permission_and_resource_action_are_not_exposed(): void
    {
        [$source, $destination] = $this->ingredientPair(10, 2);
        $this->grantPermissions(['admin.stock_transfers.create']);

        $draftResponse = $this->postJson('/api/v1/stock-transfers', $this->payload($source, $destination, '3'))
            ->assertCreated();
        $draftId = (int) $draftResponse->json('body.id');
        $this->assertArrayNotHasKey('allow_destroy', $draftResponse->json('body'));

        $this->deleteJson("/api/v1/stock-transfers/$draftId")
            ->assertMethodNotAllowed();
        $this->assertNotSoftDeleted('stock_transfers', ['id' => $draftId]);
        $this->assertNotContains(
            'admin.stock_transfers.destroy',
            app(\Modules\User\Permission::class)->getPermissionNames(),
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(
            FinancialPostingBridgeInterface::class,
            new NullFinancialPostingBridge,
        );
        $this->sourceBranch = Branch::factory()->create(['currency' => 'JOD']);
        $this->destinationBranch = Branch::factory()->create(['currency' => 'JOD']);
        $this->user = User::factory()->create(['branch_id' => null, 'is_active' => true]);
        $this->actingAs($this->user);
        $this->unit = Unit::query()->create([
            'name' => ['en' => 'Kilogram', 'ar' => 'كيلوغرام'],
            'symbol' => ['en' => 'kg', 'ar' => 'كغ'],
            'type' => 'mass',
        ]);
    }
}
