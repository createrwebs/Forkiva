<?php

namespace Modules\Inventory\tests\Feature;

use Illuminate\Support\Facades\Notification;
use Modules\Branch\Models\Branch;
use Modules\Inventory\Enums\PurchaseStatus;
use Modules\Inventory\Enums\StockTransferStatus;
use Modules\Inventory\Events\PurchaseMarkedAsReceived;
use Modules\Inventory\Events\StockTransferStatusChanged;
use Modules\Inventory\Models\Ingredient;
use Modules\Inventory\Models\Purchase;
use Modules\Inventory\Models\StockTransfer;
use Modules\Inventory\Models\Supplier;
use Modules\Inventory\Models\Unit;
use Modules\Inventory\Services\Purchase\PurchaseServiceInterface;
use Modules\Notification\Database\Seeders\NotificationDatabaseSeeder;
use Modules\Notification\Notifications\RenderedTemplateNotification;
use Tests\NotificationWorkflowTestCase;

class InventoryNotificationWorkflowTest extends NotificationWorkflowTestCase
{
    public function test_inventory_purchase_updated_event_sends_notification(): void
    {
        Notification::fake();
        $this->fakeServiceRegistry(['in_app']);
        $this->seed(NotificationDatabaseSeeder::class);
        $this->seedCustomRecipientRoles(['inventory']);

        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $inventoryUser = $this->createUserWithRole('inventory', [
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
        $supplier = Supplier::factory()->create([
            'branch_id' => $branch->id,
        ]);
        $unit = Unit::query()->create([
            'name' => ['en' => 'Kilogram', 'ar' => 'كيلوغرام'],
            'symbol' => ['en' => 'kg', 'ar' => 'كغ'],
            'type' => 'mass',
        ]);
        $ingredient = Ingredient::query()->create([
            'name' => ['en' => 'Flour', 'ar' => 'طحين'],
            'branch_id' => $branch->id,
            'unit_id' => $unit->id,
            'cost_per_unit' => 2,
            'alert_quantity' => 5,
            'current_stock' => 30,
            'is_returnable' => false,
        ]);

        $this->setWorkflowChannels('inventory_purchase_updated', [
            'in_app' => [
                'inventory' => true,
            ],
        ]);

        $purchase = Purchase::factory()->create([
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'currency' => $branch->currency,
            'currency_rate' => 1,
            'status' => PurchaseStatus::Pending,
        ]);
        $purchase->syncItems([
            [
                'ingredient_id' => $ingredient->id,
                'quantity' => 5,
                'unit_cost' => 3,
            ],
        ]);
        $purchase->calculateAndSyncTotals();

        $item = $purchase->items()->firstOrFail();

        app(PurchaseServiceInterface::class)->update($purchase->id, [
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'reference_number' => $purchase->reference_number,
            'notes' => 'Updated purchase',
            'expected_at' => now()->addDay()->toDateTimeString(),
            'items' => [
                [
                    'id' => $item->id,
                    'ingredient_id' => $ingredient->id,
                    'quantity' => 8,
                    'unit_cost' => 3,
                ],
            ],
        ]);

        Notification::assertSentTo($inventoryUser, RenderedTemplateNotification::class, function (RenderedTemplateNotification $notification, array $channels) use ($inventoryUser) {
            $payload = $notification->toDatabase($inventoryUser);

            return $channels === ['database']
                && $this->notificationTitleMatchesBase($payload, 'Inventory Purchase Updated');
        });
    }

    public function test_purchase_marked_as_received_sends_attention_notification_for_partial_receipt(): void
    {
        Notification::fake();
        $this->fakeServiceRegistry(['in_app']);
        $this->seed(NotificationDatabaseSeeder::class);
        $this->seedCustomRecipientRoles(['inventory']);

        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $inventoryUser = $this->createUserWithRole('inventory', [
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
        $supplier = Supplier::factory()->create([
            'branch_id' => $branch->id,
        ]);

        $this->setWorkflowChannels('stock_movement_needs_attention', [
            'in_app' => [
                'inventory' => true,
            ],
        ]);

        $purchase = Purchase::factory()->create([
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'currency' => $branch->currency,
            'status' => PurchaseStatus::PartiallyReceived,
        ]);

        event(new PurchaseMarkedAsReceived($purchase->fresh(['branch', 'supplier', 'items.receiptItems'])));

        Notification::assertSentTo($inventoryUser, RenderedTemplateNotification::class, function (RenderedTemplateNotification $notification, array $channels) use ($inventoryUser) {
            $payload = $notification->toDatabase($inventoryUser);

            return $channels === ['database']
                && $this->notificationTitleMatchesBase($payload, 'Stock Movement Needs Attention');
        });
    }

    public function test_stock_transfer_status_notifies_both_branch_teams_but_not_unrelated_branch(): void
    {
        Notification::fake();
        $this->fakeServiceRegistry(['in_app']);
        $this->seed(NotificationDatabaseSeeder::class);
        $this->seedCustomRecipientRoles(['inventory', 'procurement']);

        $source = Branch::factory()->create(['currency' => 'JOD']);
        $destination = Branch::factory()->create(['currency' => 'JOD']);
        $unrelated = Branch::factory()->create(['currency' => 'JOD']);
        $sourceUser = $this->createUserWithRole('inventory', [
            'branch_id' => $source->id,
            'is_active' => true,
        ]);
        $destinationUser = $this->createUserWithRole('procurement', [
            'branch_id' => $destination->id,
            'is_active' => true,
        ]);
        $unrelatedUser = $this->createUserWithRole('inventory', [
            'branch_id' => $unrelated->id,
            'is_active' => true,
        ]);
        $this->setWorkflowChannels('stock_transfer_status_changed', [
            'in_app' => [
                'inventory' => true,
                'procurement' => true,
            ],
        ]);
        $transfer = StockTransfer::query()->create([
            'reference_number' => 'ST-NOTIFICATION-1',
            'source_branch_id' => $source->id,
            'destination_branch_id' => $destination->id,
            'status' => StockTransferStatus::Dispatched,
            'currency' => 'JOD',
        ]);

        event(new StockTransferStatusChanged($transfer, StockTransferStatus::Dispatched));

        Notification::assertSentTo($sourceUser, RenderedTemplateNotification::class);
        Notification::assertSentTo($destinationUser, RenderedTemplateNotification::class);
        Notification::assertNotSentTo($unrelatedUser, RenderedTemplateNotification::class);
    }
}
