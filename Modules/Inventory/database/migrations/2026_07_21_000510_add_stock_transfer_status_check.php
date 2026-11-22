<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Enums\StockTransferStatus;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $statuses = StockTransferStatus::values();
        if (DB::table('stock_transfers')->whereNotIn('status', $statuses)->exists()) {
            throw new RuntimeException('Cannot add the stock-transfer status constraint while invalid statuses exist.');
        }

        $allowed = implode(', ', array_map(
            fn (string $status): string => DB::getPdo()->quote($status),
            $statuses,
        ));

        DB::statement(
            "ALTER TABLE stock_transfers
             ADD CONSTRAINT stock_transfers_status_check
             CHECK (status IN ($allowed))",
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE stock_transfers DROP CHECK stock_transfers_status_check');
        }
    }
};
