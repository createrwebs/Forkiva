<?php

namespace Modules\Inventory\Services\InventoryAnalytics;

use Carbon\CarbonImmutable;
use DB;
use Illuminate\Support\Carbon;
use Modules\Branch\Models\Branch;
use Modules\Branch\Services\BranchAccess\BranchAccessServiceInterface;
use Modules\Currency\Models\CurrencyRate;
use Modules\Currency\Services\DefaultCurrencyConverter;
use Modules\Inventory\Enums\InventoryAnalyticsWidget;
use Modules\Inventory\Enums\StockMovementType;
use Modules\Inventory\Models\Ingredient;
use Modules\Inventory\Models\Purchase;
use Modules\Inventory\Models\PurchaseItem;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Models\Supplier;
use Modules\Support\DateRange;
use Modules\Support\Enums\DateTimeFormat;
use Modules\Support\GlobalStructureFilters;
use Modules\Support\Money;

class InventoryAnalyticsService implements InventoryAnalyticsServiceInterface
{
    /** @var array<string, mixed> */
    private array $requestMemo = [];

    /**
     * Create a new inventory analytics service instance.
     */
    public function __construct(protected BranchAccessServiceInterface $branchAccessService) {}

    /** {@inheritDoc} */
    public function analytics(array $filters = []): array
    {
        $this->requestMemo = [];
        $branchId = $this->branchAccessService->resolve(
            ! empty($filters['branch_id']) ? (int) $filters['branch_id'] : null
        );
        $from = (string) $filters['from'];
        $to = (string) $filters['to'];
        $branch = $branchId
            ? Branch::query()->withoutGlobalActive()->withTrashed()->findOrFail($branchId)
            : null;
        $widgets = collect($filters['widgets'] ?? InventoryAnalyticsWidget::defaultValues())
            ->map(fn (InventoryAnalyticsWidget|string $widget) => $widget instanceof InventoryAnalyticsWidget
                ? $widget
                : InventoryAnalyticsWidget::from($widget));

        return [
            'meta' => [
                'branch_id' => $branchId,
                'branch_locked' => $this->branchAccessService->assignedBranchId() !== null,
                'from' => $from,
                'to' => $to,
                'display_currency' => $branch?->currency ?? setting('default_currency', 'JOD'),
                'timezone' => (string) config('app.timezone'),
                'generated_at' => dateTimeFormat(now()),
            ],
            'widgets' => $widgets
                ->mapWithKeys(fn (InventoryAnalyticsWidget $widget) => [
                    $widget->value => $this->widget($widget, $from, $to, $branchId),
                ])
                ->all(),
        ];
    }

    /**
     * Resolve and securely calculate a requested inventory analytics widget.
     */
    protected function widget(
        InventoryAnalyticsWidget $widget,
        string $from,
        string $to,
        ?int $branchId,
    ): array {
        $authorized = auth()->user()?->can($widget->permission()) ?? false;

        return [
            'key' => $widget->value,
            'permission' => $widget->permission(),
            'authorized' => $authorized,
            'data' => $authorized ? $this->widgetData($widget, $from, $to, $branchId) : null,
        ];
    }

    /**
     * Calculate one requested widget, reusing the shared overview calculation.
     */
    protected function widgetData(
        InventoryAnalyticsWidget $widget,
        string $from,
        string $to,
        ?int $branchId,
    ): array {
        if ($widget->isOverviewMetric()) {
            $overview = $this->requestMemo['overview'] ??= $this->overviewMetrics($from, $to, $branchId);

            return $overview[$widget->value];
        }

        return match ($widget) {
            InventoryAnalyticsWidget::FastMovingIngredients => $this->fastMovingIngredients($from, $to, $branchId),
            InventoryAnalyticsWidget::StockMovement => $this->stockMovementSummary($from, $to, $branchId),
            InventoryAnalyticsWidget::TopSuppliers => $this->topSuppliers($from, $to, $branchId),
            InventoryAnalyticsWidget::LowStockIngredients => $this->lowStockIngredients($branchId),
            InventoryAnalyticsWidget::PurchaseStatus => $this->purchaseStatusSummary($from, $to, $branchId),
            InventoryAnalyticsWidget::MostWastedIngredients => $this->mostWastedIngredients($from, $to, $branchId),
            InventoryAnalyticsWidget::IngredientPurchases => $this->ingredientPurchases($from, $to, $branchId),
            InventoryAnalyticsWidget::WastageSpoilage => $this->wastageAndSpoilage($from, $to, $branchId),
            default => [],
        };
    }

    /**
     * Calculate the inventory health metric widgets in one shared query group.
     */
    protected function overviewMetrics(string $from, string $to, ?int $branchId): array
    {
        $toDate = CarbonImmutable::parse($to)->startOfDay();
        $fromDate = CarbonImmutable::parse($from)->startOfDay();
        if ($fromDate->greaterThan($toDate)) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        $periodDays = $fromDate->diffInDays($toDate) + 1;
        $previousTo = $fromDate->subDay();
        $previousFrom = $previousTo->subDays($periodDays - 1);
        $branch = $branchId
            ? Branch::query()->withoutGlobalActive()->withTrashed()->findOrFail($branchId)
            : null;
        $currency = $branch?->currency ?? setting('default_currency', 'JOD');

        $inventoryValue = $this->inventoryValue($branchId);
        $lowStockCount = Ingredient::query()
            ->whereNotNull('alert_quantity')
            ->whereColumn('current_stock', '<=', 'alert_quantity')
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->count();
        $purchaseSpend = $this->purchaseSpend($fromDate, $toDate, $branchId);
        $previousPurchaseSpend = $this->purchaseSpend($previousFrom, $previousTo, $branchId);
        $wasteRate = $this->wasteRate($fromDate, $toDate, $branchId);
        $previousWasteRate = $this->wasteRate($previousFrom, $previousTo, $branchId);

        return [
            'inventory_value' => [
                'value' => (new Money($inventoryValue, $currency))->round(),
            ],
            'low_stock_items' => [
                'value' => $lowStockCount,
            ],
            'purchase_spend' => [
                'value' => (new Money($purchaseSpend, $currency))->round(),
                'comparison' => $this->comparison($purchaseSpend, $previousPurchaseSpend),
            ],
            'waste_rate' => [
                'value' => round($wasteRate, 2),
                'suffix' => '%',
                'comparison' => $this->comparison($wasteRate, $previousWasteRate),
            ],
        ];
    }

    private function inventoryValue(?int $branchId): float
    {
        $values = Ingredient::query()
            ->without('unit')
            ->join('branches', 'branches.id', '=', 'ingredients.branch_id')
            ->select('branches.currency')
            ->selectRaw('SUM(CASE WHEN ingredients.current_stock > 0 THEN ingredients.current_stock * ingredients.cost_per_unit ELSE 0 END) as total')
            ->when($branchId, fn ($query) => $query->where('ingredients.branch_id', $branchId))
            ->groupBy('branches.currency')
            ->get();

        if ($branchId) {
            return (float) ($values->first()?->total ?? 0);
        }

        return $values->sum(function ($row): float {
            $rate = CurrencyRate::for($row->currency);

            return DefaultCurrencyConverter::convert(
                (string) $row->total,
                $row->currency,
                $rate,
            )->toFloat();
        });
    }

    private function purchaseSpend(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?int $branchId,
    ): float {
        $expression = $branchId
            ? 'COALESCE(total, 0)'
            : 'COALESCE(total, 0) / COALESCE(NULLIF(currency_rate, 0), 1)';

        return (float) Purchase::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->where('created_at', '>=', $from->startOfDay())
            ->where('created_at', '<', $to->addDay()->startOfDay())
            ->selectRaw("COALESCE(SUM($expression), 0) as aggregate")
            ->value('aggregate');
    }

    private function wasteRate(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?int $branchId,
    ): float {
        $costExpression = $branchId
            ? 'quantity * COALESCE(unit_cost, 0)'
            : 'quantity * COALESCE(unit_cost, 0) / COALESCE(NULLIF(currency_rate, 0), 1)';
        $totals = StockMovement::query()
            ->selectRaw("SUM(CASE WHEN type IN ('waste', 'spoil') THEN $costExpression ELSE 0 END) as waste_cost")
            ->selectRaw("SUM(CASE WHEN type IN ('out', 'waste', 'spoil') THEN $costExpression ELSE 0 END) as usage_cost")
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->where('created_at', '>=', $from->startOfDay())
            ->where('created_at', '<', $to->addDay()->startOfDay())
            ->first();

        $usageCost = (float) ($totals?->usage_cost ?? 0);
        if ($usageCost <= 0) {
            return 0;
        }

        return ((float) ($totals?->waste_cost ?? 0) / $usageCost) * 100;
    }

    /**
     * @return array{percent: float|null, direction: -1|0|1}
     */
    private function comparison(float $current, float $previous): array
    {
        return [
            'percent' => $previous === 0.0
                ? ($current === 0.0 ? 0 : null)
                : round((($current - $previous) / abs($previous)) * 100, 1),
            'direction' => $current <=> $previous,
        ];
    }

    /**
     * Get top suppliers based on total purchase amount.
     */
    protected function topSuppliers(string $from, string $to, ?int $branchId): array
    {
        $branch = $branchId
            ? Branch::query()->withoutGlobalActive()->withTrashed()->findOrFail($branchId)
            : null;

        $query = Supplier::query()
            ->select('suppliers.name')
            ->join('purchases', 'suppliers.id', '=', 'purchases.supplier_id')
            ->when($branchId, fn ($q) => $q->where('purchases.branch_id', $branchId))
            ->when($from, fn ($q) => $q->where('purchases.created_at', '>=', DateRange::start($from)))
            ->when($to, fn ($q) => $q->where('purchases.created_at', '<', DateRange::endExclusive($to)))
            ->groupBy('suppliers.name');

        if ($branchId) {
            $query->selectRaw('SUM(purchases.total) as total_amount');
        } else {
            $query->selectRaw('SUM(purchases.total * purchases.currency_rate) as total_amount');
        }

        $data = $query
            ->orderByDesc('total_amount')
            ->limit(10)
            ->get();

        $colors = [
            'rgba(45, 156, 219, 0.6)', 'rgba(39, 174, 96, 0.6)',
            'rgba(243, 156, 18, 0.6)', 'rgba(231, 76, 60, 0.6)',
            'rgba(142, 68, 173, 0.6)', 'rgba(26, 188, 156, 0.6)',
            'rgba(241, 196, 15, 0.6)', 'rgba(149, 165, 166, 0.6)',
            'rgba(230, 126, 34, 0.6)', 'rgba(52, 73, 94, 0.6)',
        ];

        return [
            'currency' => $branch?->currency ?? setting('default_currency'),
            'labels' => $data->pluck('name'),
            'datasets' => [
                [
                    'label' => __('inventory::inventories.analytics.top_suppliers'),
                    'data' => $data->pluck('total_amount'),
                    'backgroundColor' => $data->keys()->map(fn ($i) => $colors[$i % count($colors)]),
                ],
            ],
        ];
    }

    /**
     * Get total ingredient purchases grouped by ingredient.
     */
    protected function ingredientPurchases(string $from, string $to, ?int $branchId): array
    {
        $data = PurchaseItem::query()
            ->select('ingredient_id')
            ->selectRaw('SUM(purchase_items.quantity) as total_quantity')
            ->join('ingredients', 'ingredients.id', '=', 'purchase_items.ingredient_id')
            ->join('units', 'units.id', '=', 'ingredients.unit_id')
            ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->when($branchId, fn ($q) => $q->where('purchases.branch_id', $branchId))
            ->when($from, fn ($q) => $q->where('purchases.created_at', '>=', DateRange::start($from)))
            ->when($to, fn ($q) => $q->where('purchases.created_at', '<', DateRange::endExclusive($to)))
            ->groupBy('ingredient_id', 'ingredients.name', 'units.symbol')
            ->orderByDesc('total_quantity')
            ->get();

        return $data->map(fn ($item) => [
            'id' => $item->ingredient_id,
            'name' => $item->ingredient->name,
            'total_quantity' => (float) $item->total_quantity.' '.ucfirst($item->ingredient->unit->symbol),
        ])->toArray();
    }

    /**
     * Get stock movement totals grouped by type.
     */
    protected function stockMovementSummary(string $from, string $to, ?int $branchId): array
    {
        $data = StockMovement::query()
            ->select('type')
            ->selectRaw('SUM(quantity) as total')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($from, fn ($q) => $q->where('created_at', '>=', DateRange::start($from)))
            ->when($to, fn ($q) => $q->where('created_at', '<', DateRange::endExclusive($to)))
            ->groupBy('type')
            ->get();

        $colors = [
            'in' => 'rgba(46, 204, 113, 0.6)',
            'out' => 'rgba(255, 99, 132, 0.6)',
            'spoil' => 'rgba(241, 196, 15, 0.6)',
            'adjust_add' => 'rgba(52, 152, 219, 0.6)',
            'adjust_subtract' => 'rgba(155, 89, 182, 0.6)',
            'transfer_in' => 'rgba(26, 188, 156, 0.6)',
            'transfer_out' => 'rgba(52, 73, 94, 0.6)',
            'return_supplier' => 'rgba(230, 126, 34, 0.6)',
            'sample' => 'rgba(127, 140, 141, 0.6)',
            'waste' => 'rgba(255, 159, 64, 0.6)',
        ];

        return [
            'labels' => $data->map(fn ($item) => $item->type->trans()),
            'datasets' => [
                [
                    'label' => __('inventory::inventories.analytics.stock_movements'),
                    'data' => $data->pluck('total')->map(fn ($total): float => (float) $total),
                    'backgroundColor' => $data->pluck('type')->map(fn ($type) => $colors[$type->value] ?? 'rgba(189, 195, 199, 0.6)'),
                ],
            ],
        ];
    }

    /**
     * Get wastage and spoilage totals.
     */
    protected function wastageAndSpoilage(string $from, string $to, ?int $branchId): array
    {
        $data = StockMovement::query()
            ->select('type')
            ->selectRaw('SUM(quantity) as total')
            ->whereIn('type', ['waste', 'spoil'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($from, fn ($q) => $q->where('created_at', '>=', DateRange::start($from)))
            ->when($to, fn ($q) => $q->where('created_at', '<', DateRange::endExclusive($to)))
            ->groupBy('type')
            ->get();

        return [
            'labels' => $data->map(fn ($item) => $item->type->trans()),
            'datasets' => [
                [
                    'label' => __('inventory::inventories.analytics.wastage_and_spoilage'),
                    'data' => $data->pluck('total')->map(fn ($total): float => (float) $total),
                    'backgroundColor' => [
                        'rgba(241, 196, 15, 0.6)',
                        'rgba(255, 159, 64, 0.6)',
                    ],
                ],
            ],
        ];
    }

    /**
     * Get purchase counts grouped by status.
     */
    protected function purchaseStatusSummary(string $from, string $to, ?int $branchId): array
    {
        $data = Purchase::query()
            ->select('status')
            ->selectRaw('COUNT(*) as count')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($from, fn ($q) => $q->where('created_at', '>=', DateRange::start($from)))
            ->when($to, fn ($q) => $q->where('created_at', '<', DateRange::endExclusive($to)))
            ->groupBy('status')
            ->get();

        $colors = [
            'draft' => 'rgba(127, 140, 141, 0.6)',
            'pending' => 'rgba(52, 152, 219, 0.6)',
            'partially_received' => 'rgba(255, 159, 64, 0.6)',
            'received' => 'rgba(46, 204, 113, 0.6)',
            'cancelled' => 'rgba(231, 76, 60, 0.6)',
        ];

        return [
            'labels' => $data->map(fn ($item) => $item->status->trans()),
            'datasets' => [
                [
                    'label' => __('inventory::inventories.analytics.purchase_status'),
                    'data' => $data->pluck('count'),
                    'backgroundColor' => $data->pluck('status')->map(fn ($status) => $colors[$status->value] ?? 'rgba(189, 195, 199, 0.6)'),
                ],
            ],
        ];
    }

    /** {@inheritDoc} */
    public function filters(array $filters = []): array
    {
        $branchId = $this->branchAccessService->resolve(
            ! empty($filters['branch_id']) ? (int) $filters['branch_id'] : null
        );
        $branchFilter = GlobalStructureFilters::branch();

        return [
            'branches' => $branchFilter['options'] ?? [],
            'filters' => array_values(array_filter([
                $branchFilter,
                GlobalStructureFilters::from(__('inventory::inventories.analytics.from')),
                GlobalStructureFilters::to(__('inventory::inventories.analytics.to')),
            ])),
            'defaults' => [
                'branch_id' => $branchId,
                'from' => $filters['from'],
                'to' => $filters['to'],
            ],
            'meta' => [
                'branch_locked' => $this->branchAccessService->assignedBranchId() !== null,
                'timezone' => (string) config('app.timezone'),
            ],
        ];
    }

    /**
     * Get ingredients at or below their alert quantity.
     */
    protected function lowStockIngredients(?int $branchId): array
    {
        $ingredients = Ingredient::query()
            ->select('id', 'name', 'unit_id', 'current_stock', 'alert_quantity')
            ->with('unit')
            ->whereNotNull('alert_quantity')
            ->whereColumn('current_stock', '<=', 'alert_quantity')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->limit(10)->get();

        return $ingredients->map(fn ($ingredient) => [
            'id' => $ingredient->id,
            'name' => $ingredient->name,
            'current_stock' => $ingredient->current_stock.' '.$ingredient->unit->symbol,
            'alert_quantity' => $ingredient->alert_quantity.' '.$ingredient->unit->symbol,
        ])->toArray();
    }

    /**
     * Get the fastest-moving ingredients for the selected period.
     */
    protected function fastMovingIngredients(string $from, string $to, ?int $branchId): array
    {
        $topIngredients = StockMovement::query()
            ->select('ingredient_id', DB::raw('SUM(quantity) as total_quantity'))
            ->where('type', StockMovementType::Out->value)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('created_at', '>=', DateRange::start($from))
            ->where('created_at', '<', DateRange::endExclusive($to))
            ->groupBy('ingredient_id')
            ->orderByDesc('total_quantity')
            ->limit(10)
            ->pluck('ingredient_id');

        $data = StockMovement::query()
            ->select(
                'ingredient_id',
                DB::raw('DATE(stock_movements.created_at) as date'),
                'ingredients.name',
                DB::raw('SUM(quantity) as total')
            )
            ->join('ingredients', 'ingredients.id', '=', 'stock_movements.ingredient_id')
            ->where('type', 'out')
            ->whereIn('ingredient_id', $topIngredients)
            ->when($branchId, fn ($q) => $q->where('stock_movements.branch_id', $branchId))
            ->where('stock_movements.created_at', '>=', DateRange::start($from))
            ->where('stock_movements.created_at', '<', DateRange::endExclusive($to))
            ->groupBy('date', 'ingredient_id', 'ingredients.name')
            ->orderBy('date')
            ->get();

        $grouped = $data->groupBy('name');

        $labels = collect();
        $start = Carbon::parse($from);
        $end = Carbon::parse($to);
        while ($start <= $end) {
            $labels->push(dateTimeFormat($start, DateTimeFormat::Date));
            $start->addDay();
        }

        $colors = [
            'rgba(45, 156, 219, 0.6)',
            'rgba(39, 174, 96, 0.6)',
            'rgba(243, 156, 18, 0.6)',
            'rgba(231, 76, 60, 0.6)',
            'rgba(142, 68, 173, 0.6)',
            'rgba(26, 188, 156, 0.6)',
            'rgba(241, 196, 15, 0.6)',
            'rgba(149, 165, 166, 0.6)',
            'rgba(230, 126, 34, 0.6)',
            'rgba(52, 73, 94, 0.6)',
        ];

        $index = 0;

        $datasets = $grouped->map(function ($entries, $name) use ($labels, $colors, &$index) {
            $color = $colors[$index % count($colors)];
            $daily = $entries->keyBy('date');

            $dataset = [
                'label' => $name,
                'fill' => false,
                'data' => $labels->map(fn ($date) => (float) ($daily[$date]->total ?? 0)),
                'backgroundColor' => $color,
                'borderColor' => $color,
                'borderWidth' => 2,
                'tension' => 0.3,
            ];

            $index++;

            return $dataset;
        })->values();

        return [
            'labels' => $labels,
            'datasets' => $datasets,
        ];
    }

    /**
     * Get the ingredients with the greatest waste and spoilage quantity.
     */
    protected function mostWastedIngredients(string $from, string $to, ?int $branchId): array
    {
        $data = StockMovement::query()
            ->select('ingredients.id', 'units.symbol as symbol', 'ingredients.name', DB::raw('SUM(stock_movements.quantity) as total_wasted'))
            ->join('ingredients', 'ingredients.id', '=', 'stock_movements.ingredient_id')
            ->join('units', 'units.id', '=', 'ingredients.unit_id')
            ->whereIn('stock_movements.type', [
                StockMovementType::Waste->value,
                StockMovementType::Spoil->value,
            ])
            ->when($branchId, fn ($q) => $q->where('stock_movements.branch_id', $branchId))
            ->when($from, fn ($q) => $q->where('stock_movements.created_at', '>=', DateRange::start($from)))
            ->when($to, fn ($q) => $q->where('stock_movements.created_at', '<', DateRange::endExclusive($to)))
            ->groupBy('ingredients.id', 'ingredients.name', 'units.symbol')
            ->orderByDesc('total_wasted')
            ->limit(5)
            ->get();

        $colors = [
            'rgba(231, 76, 60, 0.6)',
            'rgba(243, 156, 18, 0.6)',
            'rgba(192, 57, 43, 0.6)',
            'rgba(211, 84, 0, 0.6)',
            'rgba(127, 140, 141, 0.6)',
        ];

        return [
            'labels' => $data->pluck('name'),
            'unitSymbols' => $data->pluck('symbol')->map(fn ($symbol) => ucfirst($symbol)),
            'datasets' => [
                [
                    'label' => __('inventory::inventories.analytics.most_wasted_ingredients'),
                    'data' => $data->pluck('total_wasted'),
                    'backgroundColor' => $data->keys()->map(fn ($i) => $colors[$i % count($colors)]),
                ],
            ],
        ];
    }
}
