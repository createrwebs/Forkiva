<?php

namespace Modules\Inventory\Enums;

use Modules\Support\Traits\EnumArrayable;

enum InventoryAnalyticsWidget: string
{
    use EnumArrayable;

    case InventoryValue = 'inventory_value';
    case LowStockItems = 'low_stock_items';
    case PurchaseSpend = 'purchase_spend';
    case WasteRate = 'waste_rate';
    case FastMovingIngredients = 'fast_moving_ingredients';
    case StockMovement = 'stock_movement';
    case TopSuppliers = 'top_suppliers';
    case LowStockIngredients = 'low_stock_ingredients';
    case PurchaseStatus = 'purchase_status';
    case MostWastedIngredients = 'most_wasted_ingredients';
    case IngredientPurchases = 'ingredient_purchases';
    case WastageSpoilage = 'wastage_spoilage';

    /**
     * Get the permission required to calculate this widget.
     */
    public function permission(): string
    {
        return 'admin.inventories.analytics';
    }

    /**
     * Get the widgets used by the default analytics layout.
     *
     * @return array<int, string>
     */
    public static function defaultValues(): array
    {
        return [
            self::InventoryValue->value,
            self::LowStockItems->value,
            self::PurchaseSpend->value,
            self::WasteRate->value,
            self::FastMovingIngredients->value,
            self::StockMovement->value,
            self::TopSuppliers->value,
            self::LowStockIngredients->value,
        ];
    }

    /**
     * Determine whether this widget belongs to the shared overview calculation.
     */
    public function isOverviewMetric(): bool
    {
        return in_array($this, [
            self::InventoryValue,
            self::LowStockItems,
            self::PurchaseSpend,
            self::WasteRate,
        ], true);
    }
}
