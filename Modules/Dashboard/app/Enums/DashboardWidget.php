<?php

namespace Modules\Dashboard\Enums;

use Modules\Support\Traits\EnumArrayable;

enum DashboardWidget: string
{
    use EnumArrayable;

    case TotalSales = 'total_sales';
    case TotalOrders = 'total_orders';
    case TotalActiveOrders = 'total_active_orders';
    case AverageOrderValue = 'average_order_value';
    case TotalUsers = 'total_users';
    case TotalMenus = 'total_menus';
    case TotalProducts = 'total_products';
    case TotalCategories = 'total_categories';
    case SalesAnalytics = 'sales_analytics';
    case HourlySalesTrend = 'hourly_sales_trend';
    case TopSellingProducts = 'top_selling_products';
    case BranchWiseSalesComparison = 'branch_wise_sales_comparison';
    case BestPerformingBranches = 'best_performing_branches';
    case OrderTypeDistribution = 'order_type_distribution';
    case OrderTotalByStatus = 'order_total_by_status';
    case LowStockAlerts = 'low_stock_alerts';
    case CashMovementsOverview = 'cash_movements_overview';
    case PaymentsOverview = 'payments_overview';
    case GrossProfitMargin = 'gross_profit_margin';
    case CustomerMix = 'customer_mix';
    case OrderSourceDistribution = 'order_source_distribution';
    case FulfillmentHealth = 'fulfillment_health';
    case OutstandingBalance = 'outstanding_balance';
    case TotalGuests = 'total_guests';
    case RevenuePerGuest = 'revenue_per_guest';
    case PaymentStatusDistribution = 'payment_status_distribution';
    case SalesByWeekday = 'sales_by_weekday';

    /**
     * Get the permission required to calculate this widget.
     */
    public function permission(): string
    {
        return match ($this) {
            self::HourlySalesTrend => 'admin.dashboards.sales_analytics',
            default => 'admin.dashboards.'.$this->value,
        };
    }

    /**
     * Get the widgets used by the default dashboard layout.
     *
     * @return array<int, string>
     */
    public static function defaultValues(): array
    {
        return [
            self::TotalSales->value,
            self::TotalOrders->value,
            self::TotalActiveOrders->value,
            self::AverageOrderValue->value,
            self::SalesAnalytics->value,
            self::OrderTotalByStatus->value,
            self::TopSellingProducts->value,
            self::LowStockAlerts->value,
        ];
    }
}
