<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Enums\StockMovementType;

return new class extends Migration
{
    /**
     * Expand the production MySQL enum without rewriting portable test schemas.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $values = collect(StockMovementType::values())
            ->map(fn (string $value): string => DB::getPdo()->quote($value))
            ->implode(',');

        DB::statement("ALTER TABLE stock_movements MODIFY type ENUM($values) NOT NULL");
    }

    /**
     * Restore the previous enum only when no staff-meal audit history exists.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (DB::table('stock_movements')->where('type', StockMovementType::StaffMeal->value)->exists()) {
            throw new RuntimeException('Cannot remove staff_meal while stock movement history exists.');
        }

        $values = collect(StockMovementType::values())
            ->reject(fn (string $value): bool => $value === StockMovementType::StaffMeal->value)
            ->map(fn (string $value): string => DB::getPdo()->quote($value))
            ->implode(',');

        DB::statement("ALTER TABLE stock_movements MODIFY type ENUM($values) NOT NULL");
    }
};
