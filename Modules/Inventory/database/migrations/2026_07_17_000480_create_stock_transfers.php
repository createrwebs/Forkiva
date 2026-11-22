<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Branch\Models\Branch;
use Modules\Inventory\Models\Ingredient;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Models\Unit;
use Modules\User\Models\User;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->string('reference_number', 64)->nullable()->unique();
            $table->foreignIdFor(Branch::class, 'source_branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignIdFor(Branch::class, 'destination_branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('status', 32)->index();
            $table->string('currency', 3)->nullable();
            $table->dateTime('expected_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignIdFor(User::class, 'approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignIdFor(User::class, 'dispatched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dispatched_at')->nullable();
            $table->foreignIdFor(User::class, 'completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignIdFor(User::class, 'cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignIdFor(User::class, 'voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->foreignIdFor(User::class, 'created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['source_branch_id', 'status', 'created_at'], 'stock_transfers_source_status_idx');
            $table->index(['destination_branch_id', 'status', 'created_at'], 'stock_transfers_destination_status_idx');
        });

        Schema::create('stock_transfer_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained('stock_transfers')->cascadeOnDelete();
            $table->foreignIdFor(Ingredient::class, 'source_ingredient_id')->constrained('ingredients')->restrictOnDelete();
            $table->foreignIdFor(Ingredient::class, 'destination_ingredient_id')->constrained('ingredients')->restrictOnDelete();
            $table->foreignIdFor(Unit::class)->constrained('units')->restrictOnDelete();
            $table->decimal('requested_quantity', 18, 6)->unsigned();
            $table->decimal('dispatched_quantity', 18, 6)->unsigned()->default(0);
            $table->decimal('received_quantity', 18, 6)->unsigned()->default(0);
            $table->decimal('discrepancy_quantity', 18, 6)->unsigned()->default(0);
            $table->decimal('unit_cost', 18, 6)->nullable();
            $table->decimal('total_cost', 18, 6)->nullable();
            $table->text('discrepancy_reason')->nullable();
            $table->foreignIdFor(StockMovement::class, 'outbound_movement_id')->nullable()->constrained('stock_movements')->restrictOnDelete();
            $table->foreignIdFor(StockMovement::class, 'outbound_reversal_movement_id')->nullable()->constrained('stock_movements')->restrictOnDelete();
            $table->timestamps();

            $table->unique(
                ['stock_transfer_id', 'source_ingredient_id', 'destination_ingredient_id'],
                'stock_transfer_items_pair_unique',
            );
            $table->unique('outbound_movement_id', 'stock_transfer_items_outbound_unique');
            $table->unique('outbound_reversal_movement_id', 'stock_transfer_items_reversal_unique');
        });

        Schema::create('stock_transfer_receipts', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('stock_transfer_id')->constrained('stock_transfers')->restrictOnDelete();
            $table->string('reference_number', 64)->nullable()->unique();
            $table->uuid('idempotency_key')->unique();
            $table->text('notes')->nullable();
            $table->foreignIdFor(User::class, 'received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['stock_transfer_id', 'received_at'], 'stock_transfer_receipts_transfer_date_idx');
        });

        Schema::create('stock_transfer_receipt_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_transfer_receipt_id')->constrained('stock_transfer_receipts')->cascadeOnDelete();
            $table->foreignId('stock_transfer_item_id')->constrained('stock_transfer_items')->restrictOnDelete();
            $table->decimal('quantity', 18, 6)->unsigned();
            $table->decimal('unit_cost', 18, 6)->unsigned();
            $table->decimal('total_cost', 18, 6)->unsigned();
            $table->foreignIdFor(StockMovement::class, 'inbound_movement_id')->nullable()->constrained('stock_movements')->restrictOnDelete();
            $table->timestamps();

            $table->unique(
                ['stock_transfer_receipt_id', 'stock_transfer_item_id'],
                'stock_transfer_receipt_items_line_unique',
            );
            $table->unique('inbound_movement_id', 'stock_transfer_receipt_items_movement_unique');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE stock_transfers
                ADD CONSTRAINT stock_transfers_branch_check
                CHECK (source_branch_id <> destination_branch_id)',
            );
            DB::statement(
                'ALTER TABLE stock_transfer_items
                ADD CONSTRAINT stock_transfer_items_quantity_check
                CHECK (
                    requested_quantity > 0
                    AND dispatched_quantity >= 0
                    AND dispatched_quantity <= requested_quantity
                    AND received_quantity >= 0
                    AND discrepancy_quantity >= 0
                    AND received_quantity + discrepancy_quantity <= dispatched_quantity
                )',
            );
            DB::statement(
                'ALTER TABLE stock_transfer_receipt_items
                ADD CONSTRAINT stock_transfer_receipt_items_value_check
                CHECK (quantity > 0 AND unit_cost >= 0 AND total_cost >= 0)',
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_receipt_items');
        Schema::dropIfExists('stock_transfer_receipts');
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
    }
};
