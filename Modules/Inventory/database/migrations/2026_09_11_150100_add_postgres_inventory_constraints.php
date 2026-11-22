<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Inventory\Enums\StockMovementType;
use Modules\Inventory\Enums\StockTransferStatus;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        Schema::table('stock_movements', fn (Blueprint $table) => $table->enum('type', StockMovementType::values())->change());
        $statuses = implode(', ', array_map(fn ($value) => DB::getPdo()->quote($value), StockTransferStatus::values()));
        DB::statement("ALTER TABLE stock_transfers ADD CONSTRAINT stock_transfers_status_check CHECK (status IN ({$statuses}))");
        DB::statement('ALTER TABLE stock_movements ADD CONSTRAINT stock_mov_cost_snapshot_check CHECK (legacy_cost_snapshot_missing OR (unit_cost IS NOT NULL AND currency IS NOT NULL))');
        DB::statement('ALTER TABLE stock_transfers ADD CONSTRAINT stock_transfers_branch_check CHECK (source_branch_id <> destination_branch_id)');
        DB::statement('ALTER TABLE stock_transfer_items ADD CONSTRAINT stock_transfer_items_quantity_check CHECK (requested_quantity > 0 AND dispatched_quantity >= 0 AND dispatched_quantity <= requested_quantity AND received_quantity >= 0 AND discrepancy_quantity >= 0 AND received_quantity + discrepancy_quantity <= dispatched_quantity)');
        DB::statement('ALTER TABLE stock_transfer_receipt_items ADD CONSTRAINT stock_transfer_receipt_items_value_check CHECK (quantity > 0 AND unit_cost >= 0 AND total_cost >= 0)');
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION stock_transfers_status_guard() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF NEW.status IS DISTINCT FROM OLD.status AND NOT (
                    (OLD.status = 'draft' AND NEW.status IN ('approved', 'cancelled'))
                    OR (OLD.status = 'approved' AND NEW.status IN ('dispatched', 'cancelled'))
                    OR (OLD.status = 'dispatched' AND NEW.status IN ('partially_received', 'received', 'closed_with_discrepancy', 'voided'))
                    OR (OLD.status = 'partially_received' AND NEW.status IN ('received', 'closed_with_discrepancy'))
                ) THEN
                    RAISE EXCEPTION 'Invalid stock transfer status transition';
                END IF;
                RETURN NEW;
            END $$;
            CREATE TRIGGER stock_transfers_terminal_status_guard BEFORE UPDATE ON stock_transfers
            FOR EACH ROW EXECUTE FUNCTION stock_transfers_status_guard();
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        DB::statement('DROP TRIGGER IF EXISTS stock_transfers_terminal_status_guard ON stock_transfers');
        DB::statement('DROP FUNCTION IF EXISTS stock_transfers_status_guard()');
        foreach ([
            'stock_movements' => ['stock_mov_cost_snapshot_check'],
            'stock_transfers' => ['stock_transfers_status_check', 'stock_transfers_branch_check'],
            'stock_transfer_items' => ['stock_transfer_items_quantity_check'],
            'stock_transfer_receipt_items' => ['stock_transfer_receipt_items_value_check'],
        ] as $table => $constraints) {
            foreach ($constraints as $constraint) {
                DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$constraint}");
            }
        }
        // Keep expanded movement types so rollback cannot invalidate financial history.
    }
};
