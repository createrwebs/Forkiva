<?php

namespace Modules\Dashboard\Services\Dashboard;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use JsonException;
use Modules\Branch\Models\Branch;
use Modules\Branch\Services\BranchAccess\BranchAccessServiceInterface;
use Modules\Category\Models\Category;
use Modules\Currency\Currency;
use Modules\Dashboard\Enums\DashboardWidget;
use Modules\Inventory\Models\Ingredient;
use Modules\Menu\Models\Menu;
use Modules\Order\Enums\OrderPaymentStatus;
use Modules\Order\Enums\OrderSource;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\OrderType;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderProduct;
use Modules\Payment\Enums\PaymentMethod;
use Modules\Payment\Models\Payment;
use Modules\Pos\Models\PosCashMovement;
use Modules\Product\Models\Product;
use Modules\Support\Enums\Day;
use Modules\Support\GlobalStructureFilters;
use Modules\Support\Money;
use Modules\User\Models\User;

class DashboardService implements DashboardServiceInterface
{
    private const CACHE_TTL_SECONDS = 120;

    /** @var array<string, mixed> */
    private array $requestMemo = [];

    /**
     * Create a new dashboard service instance.
     */
    public function __construct(protected BranchAccessServiceInterface $branchAccessService) {}

    /** {@inheritDoc} */
    public function filters(array $filters = []): array
    {
        $normalized = $this->normalizeFilters($filters);
        $branchFilter = GlobalStructureFilters::branch();
        $today = CarbonImmutable::today();

        return [
            'filters' => [
                ...(is_null($branchFilter) ? [] : [$branchFilter]),
                GlobalStructureFilters::from(__('dashboard::dashboards.filters.from')),
                GlobalStructureFilters::to(__('dashboard::dashboards.filters.to')),
            ],
            'presets' => [
                [
                    'id' => 'today',
                    'name' => __('dashboard::dashboards.periods.today'),
                    'from' => $today->toDateString(),
                    'to' => $today->toDateString(),
                ],
                [
                    'id' => 'last_7_days',
                    'name' => __('dashboard::dashboards.periods.last_7_days'),
                    'from' => $today->subDays(6)->toDateString(),
                    'to' => $today->toDateString(),
                ],
                [
                    'id' => 'this_month',
                    'name' => __('dashboard::dashboards.periods.this_month'),
                    'from' => $today->startOfMonth()->toDateString(),
                    'to' => $today->toDateString(),
                ],
                [
                    'id' => 'this_year',
                    'name' => __('dashboard::dashboards.periods.this_year'),
                    'from' => $today->startOfYear()->toDateString(),
                    'to' => $today->toDateString(),
                ],
            ],
            'defaults' => [
                'branch_id' => $normalized['branch_id'],
                'from' => $normalized['from'],
                'to' => $normalized['to'],
            ],
            'meta' => [
                'branch_locked' => $this->branchAccessService->assignedBranchId() !== null,
                'max_range_days' => 367,
                'timezone' => (string) config('app.timezone'),
            ],
        ];
    }

    /**
     * Normalize user scope, dates, currency, and widget options.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    protected function normalizeFilters(array $filters): array
    {
        $branchId = $this->branchAccessService->resolve(
            ! empty($filters['branch_id']) ? (int) $filters['branch_id'] : null
        );
        $today = CarbonImmutable::today();
        $from = CarbonImmutable::parse(
            $filters['from'] ?? $today->startOfYear()->toDateString()
        )->startOfDay();
        $to = CarbonImmutable::parse($filters['to'] ?? $today->toDateString())->endOfDay();
        $rangeDays = $from->startOfDay()->diffInDays($to->startOfDay()) + 1;
        $comparisonTo = $from->subDay()->endOfDay();
        $comparisonFrom = $comparisonTo->subDays($rangeDays - 1)->startOfDay();
        $branch = $branchId
            ? Branch::query()->withoutGlobalActive()->withTrashed()->findOrFail($branchId)
            : null;

        return [
            'branch_id' => $branchId,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'from_at' => $from,
            'to_at' => $to,
            'comparison_from' => $comparisonFrom->toDateString(),
            'comparison_to' => $comparisonTo->toDateString(),
            'comparison_from_at' => $comparisonFrom,
            'comparison_to_at' => $comparisonTo,
            'range_days' => $rangeDays,
            'currency' => $branch?->currency ?? setting('default_currency', 'JOD'),
            'widget_options' => $this->normalizeWidgetOptions(
                (array) ($filters['widget_options'] ?? []),
            ),
            'refresh' => (bool) ($filters['refresh'] ?? false),
        ];
    }

    /**
     * Normalize nullable widget options for request and direct service callers.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, array<string, int|string|null>>
     */
    protected function normalizeWidgetOptions(array $options): array
    {
        $granularity = data_get($options, 'sales_analytics.granularity');

        if (! in_array($granularity, ['auto', 'hour', 'day', 'week', 'month'], true)) {
            $granularity = 'auto';
        }

        $hourlyDate = data_get($options, 'hourly_sales_trend.date');

        return [
            'sales_analytics' => [
                'granularity' => $granularity,
            ],
            'hourly_sales_trend' => [
                'date' => is_string($hourlyDate) && $hourlyDate !== '' ? $hourlyDate : null,
            ],
            'top_selling_products' => [
                'limit' => $this->normalizeLimit(
                    data_get($options, 'top_selling_products.limit'),
                    5,
                    [5, 10],
                ),
            ],
            'best_performing_branches' => [
                'limit' => $this->normalizeLimit(
                    data_get($options, 'best_performing_branches.limit'),
                    5,
                    [5, 10],
                ),
            ],
            'low_stock_alerts' => [
                'limit' => $this->normalizeLimit(
                    data_get($options, 'low_stock_alerts.limit'),
                    5,
                    [5, 10, 20],
                ),
            ],
        ];
    }

    /**
     * Resolve a bounded integer option without allowing null to become zero.
     *
     * @param  array<int, int>  $allowed
     */
    protected function normalizeLimit(mixed $value, int $default, array $allowed): int
    {
        $value = filter_var($value, FILTER_VALIDATE_INT);

        return is_int($value) && in_array($value, $allowed, true) ? $value : $default;
    }

    /** {@inheritDoc} */
    public function dashboard(array $filters = []): array
    {
        $this->requestMemo = [];
        $normalized = $this->normalizeFilters($filters);
        $widgets = collect($filters['widgets'] ?? DashboardWidget::defaultValues())
            ->map(fn (DashboardWidget|string $widget) => $widget instanceof DashboardWidget
                ? $widget
                : DashboardWidget::from($widget));

        return [
            'meta' => [
                'branch_id' => $normalized['branch_id'],
                'branch_locked' => $this->branchAccessService->assignedBranchId() !== null,
                'from' => $normalized['from'],
                'to' => $normalized['to'],
                'comparison' => [
                    'from' => $normalized['comparison_from'],
                    'to' => $normalized['comparison_to'],
                ],
                'display_currency' => $normalized['currency'],
                'granularity' => $this->resolveGranularity(
                    $normalized,
                    data_get($normalized, 'widget_options.sales_analytics.granularity'),
                ),
                'timezone' => (string) config('app.timezone'),
                'generated_at' => dateTimeFormat(now()),
            ],
            'widgets' => $widgets
                ->mapWithKeys(fn (DashboardWidget $widget) => [
                    $widget->value => $this->widget($widget, $normalized),
                ])
                ->all(),
        ];
    }

    /**
     * Resolve a safe chart granularity for the selected range.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function resolveGranularity(array $filters, ?string $requested = 'auto'): string
    {
        $days = (int) $filters['range_days'];
        $requested = in_array($requested, ['auto', 'hour', 'day', 'week', 'month'], true)
            ? $requested
            : 'auto';

        if ($requested !== 'auto') {
            if ($requested === 'hour' && $days > 2) {
                return 'day';
            }

            return $requested;
        }

        return match (true) {
            $days === 1 => 'hour',
            $days <= 45 => 'day',
            $days <= 180 => 'week',
            default => 'month',
        };
    }

    /**
     * Resolve and securely calculate a requested widget.
     *
     * @param  array<string, mixed>  $filters
     *
     * @throws JsonException
     */
    protected function widget(DashboardWidget $widget, array $filters): array
    {
        $authorized = auth()->user()?->can($widget->permission()) ?? false;

        return [
            'key' => $widget->value,
            'permission' => $widget->permission(),
            'authorized' => $authorized,
            'data' => $authorized ? $this->widgetData($widget, $filters) : null,
        ];
    }

    /**
     * Calculate one widget using shared request-level aggregates where practical.
     *
     * @param  array<string, mixed>  $filters
     *
     * @throws JsonException
     */
    protected function widgetData(DashboardWidget $widget, array $filters): static|array
    {
        return match ($widget) {
            DashboardWidget::TotalSales => $this->performanceMetric('sales', $filters),
            DashboardWidget::TotalOrders => $this->performanceMetric('orders', $filters),
            DashboardWidget::TotalActiveOrders => $this->activeOrders($filters),
            DashboardWidget::AverageOrderValue => $this->performanceMetric('average_order_value', $filters),
            DashboardWidget::TotalUsers => $this->catalogCount(User::class, $filters),
            DashboardWidget::TotalMenus => $this->catalogCount(Menu::class, $filters),
            DashboardWidget::TotalProducts => $this->catalogCount(Product::class, $filters),
            DashboardWidget::TotalCategories => $this->catalogCount(Category::class, $filters),
            DashboardWidget::SalesAnalytics => $this->salesAnalytics($filters),
            DashboardWidget::HourlySalesTrend => $this->hourlySalesTrend($filters),
            DashboardWidget::TopSellingProducts => $this->topSellingProducts($filters),
            DashboardWidget::BranchWiseSalesComparison => $this->branchPerformance($filters),
            DashboardWidget::BestPerformingBranches => $this->bestPerformingBranches($filters),
            DashboardWidget::OrderTypeDistribution => $this->orderDistribution('type', $filters),
            DashboardWidget::OrderTotalByStatus => $this->orderDistribution('status', $filters),
            DashboardWidget::LowStockAlerts => $this->lowStockAlerts($filters),
            DashboardWidget::CashMovementsOverview => $this->cashMovementsOverview($filters),
            DashboardWidget::PaymentsOverview => $this->paymentsOverview($filters),
            DashboardWidget::GrossProfitMargin => $this->grossProfitMargin($filters),
            DashboardWidget::CustomerMix => $this->customerMix($filters),
            DashboardWidget::OrderSourceDistribution => $this->orderDistribution('source', $filters),
            DashboardWidget::FulfillmentHealth => $this->fulfillmentHealth($filters),
            DashboardWidget::OutstandingBalance => $this->performanceMetric('outstanding_balance', $filters),
            DashboardWidget::TotalGuests => $this->performanceMetric('total_guests', $filters),
            DashboardWidget::RevenuePerGuest => $this->performanceMetric('revenue_per_guest', $filters),
            DashboardWidget::PaymentStatusDistribution => $this->orderDistribution('payment_status', $filters),
            DashboardWidget::SalesByWeekday => $this->salesByWeekday($filters),
        };
    }

    /**
     * Format a core performance metric with its previous-period comparison.
     *
     * @param  array<string, mixed>  $filters
     *
     * @throws JsonException
     */
    protected function performanceMetric(string $metric, array $filters): array
    {
        $summary = $this->performanceSummary($filters);
        $current = $summary[$metric];
        $previous = $summary['previous_'.$metric];
        $isMoney = in_array($metric, [
            'sales',
            'average_order_value',
            'outstanding_balance',
            'revenue_per_guest',
        ], true);

        return [
            'value' => $isMoney ? $this->money((float) $current, $filters) : (int) $current,
            'comparison' => $this->comparison((float) $current, (float) $previous),
        ];
    }

    /**
     * Get the shared current and previous-period order aggregates.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, float|int>
     *
     * @throws JsonException
     */
    protected function performanceSummary(array $filters): array
    {
        return $this->remember('performance_summary', $filters, function () use ($filters): array {
            $current = $this->aggregateOrders(
                $filters,
                $filters['from_at'],
                $filters['to_at'],
            );
            $previous = $this->aggregateOrders(
                $filters,
                $filters['comparison_from_at'],
                $filters['comparison_to_at'],
            );

            return [
                'sales' => (float) ($current->sales ?? 0),
                'orders' => (int) ($current->orders ?? 0),
                'average_order_value' => (float) ($current->average_order_value ?? 0),
                'gross_profit' => (float) ($current->dashboard_gross_profit ?? 0),
                'cost' => (float) ($current->dashboard_cost ?? 0),
                'outstanding_balance' => (float) ($current->outstanding_balance ?? 0),
                'total_guests' => (int) ($current->total_guests ?? 0),
                'revenue_per_guest' => (float) ($current->revenue_per_guest ?? 0),
                'previous_sales' => (float) ($previous->sales ?? 0),
                'previous_orders' => (int) ($previous->orders ?? 0),
                'previous_average_order_value' => (float) ($previous->average_order_value ?? 0),
                'previous_gross_profit' => (float) ($previous->dashboard_gross_profit ?? 0),
                'previous_outstanding_balance' => (float) ($previous->outstanding_balance ?? 0),
                'previous_total_guests' => (int) ($previous->total_guests ?? 0),
                'previous_revenue_per_guest' => (float) ($previous->revenue_per_guest ?? 0),
            ];
        });
    }

    /**
     * Cache a normalized dashboard segment and reuse it within the same request.
     *
     * @param  array<string, mixed>  $filters
     *
     * @throws JsonException
     */
    protected function remember(
        string $segment,
        array $filters,
        callable $callback,
        bool $includePeriod = true,
    ): mixed {
        $cacheFilters = [
            'branch_id' => $filters['branch_id'],
            'currency' => $filters['currency'],
            'locale' => app()->getLocale(),
            ...($segment === 'sales_by_weekday' ? [
                'start_of_week' => setting('start_of_week', Day::Sunday->value),
            ] : []),
            'hourly_date' => $filters['hourly_date'] ?? null,
            'top_products_limit' => $filters['top_products_limit'] ?? null,
            'low_stock_limit' => $filters['low_stock_limit'] ?? null,
        ];

        if ($includePeriod) {
            $cacheFilters = [
                ...$cacheFilters,
                'from' => $filters['from'],
                'to' => $filters['to'],
                'comparison_from' => $filters['comparison_from'],
                'comparison_to' => $filters['comparison_to'],
            ];
        }

        ksort($cacheFilters);
        $key = 'dashboard:'.$segment.':'.hash(
            'sha256',
            json_encode($cacheFilters, JSON_THROW_ON_ERROR),
        );

        if (array_key_exists($key, $this->requestMemo)) {
            return $this->requestMemo[$key];
        }

        if (! $filters['refresh'] && Cache::has($key)) {
            return $this->requestMemo[$key] = Cache::get($key);
        }

        try {
            $value = Cache::lock($key.':lock', 15)->block(5, function () use ($callback, $filters, $key): mixed {
                if (! $filters['refresh'] && Cache::has($key)) {
                    return Cache::get($key);
                }

                $value = $callback();
                Cache::put($key, $value, self::CACHE_TTL_SECONDS);

                return $value;
            });
        } catch (LockTimeoutException) {
            $value = Cache::has($key) ? Cache::get($key) : $callback();
        }

        return $this->requestMemo[$key] = $value;
    }

    /**
     * Aggregate core order values for a date window.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function aggregateOrders(array $filters, CarbonImmutable $from, CarbonImmutable $to): object
    {
        $salesExpression = $this->currencyExpression('orders.total', $filters);
        $grossProfitExpression = $this->currencyExpression('orders.gross_profit', $filters);
        $costExpression = $this->currencyExpression('orders.cost_price', $filters);
        $dueExpression = $this->currencyExpression('orders.due_amount', $filters);
        $guestExpression = 'COALESCE(orders.guest_count, 0)';

        return $this->ordersRangeQuery($filters, $from, $to)
            ->selectRaw('COUNT(orders.id) as orders')
            ->selectRaw("COALESCE(SUM($salesExpression), 0) as sales")
            ->selectRaw("COALESCE(AVG($salesExpression), 0) as average_order_value")
            ->selectRaw("COALESCE(SUM($grossProfitExpression), 0) as dashboard_gross_profit")
            ->selectRaw("COALESCE(SUM($costExpression), 0) as dashboard_cost")
            ->selectRaw("COALESCE(SUM($dueExpression), 0) as outstanding_balance")
            ->selectRaw("COALESCE(SUM($guestExpression), 0) as total_guests")
            ->selectRaw("COALESCE(SUM($salesExpression) / NULLIF(SUM($guestExpression), 0), 0) as revenue_per_guest")
            ->toBase()
            ->first();
    }

    /**
     * Convert a monetary database column only for multi-branch reporting.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function currencyExpression(
        string $column,
        array $filters,
        string $table = 'orders',
    ): string {
        return $filters['branch_id']
            ? "COALESCE($column, 0)"
            : "COALESCE($column, 0) * COALESCE($table.currency_rate, 1)";
    }

    /**
     * Build a date- and branch-scoped order query.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Order>
     */
    protected function ordersRangeQuery(
        array $filters,
        CarbonImmutable $from,
        CarbonImmutable $to,
        bool $excludeCanceled = true,
    ): Builder {
        $query = Order::query()
            ->when($filters['branch_id'], fn (Builder $branchQuery) => $branchQuery
                ->where('orders.branch_id', $filters['branch_id']))
            ->whereBetween('orders.created_at', [$from, $to]);

        return $excludeCanceled ? $query->withoutCanceledOrders() : $query;
    }

    /**
     * Create a display-currency money value.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function money(float $amount, array $filters): Money
    {
        return (new Money($amount, $filters['currency']))->round();
    }

    /**
     * Format a percentage comparison without hiding zero baselines.
     */
    protected function comparison(float $current, float $previous): array
    {
        $percent = $previous === 0.0
            ? ($current === 0.0 ? 0 : null)
            : round((($current - $previous) / abs($previous)) * 100, 1);

        return [
            'percent' => $percent,
            'direction' => $current <=> $previous,
        ];
    }

    /**
     * Get the live active-order count for the selected branch.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function activeOrders(array $filters): array
    {
        $memoKey = 'active_orders:'.($filters['branch_id'] ?? 'all');
        $count = $this->rememberForRequest($memoKey, fn () => Order::query()
            ->when($filters['branch_id'], fn (Builder $query) => $query->where('branch_id', $filters['branch_id']))
            ->activeOrders()
            ->count());

        return [
            'value' => (int) $count,
            'live' => true,
        ];
    }

    /**
     * Memoize data that must remain live across HTTP requests.
     */
    protected function rememberForRequest(string $key, callable $callback): mixed
    {
        if (! array_key_exists($key, $this->requestMemo)) {
            $this->requestMemo[$key] = $callback();
        }

        return $this->requestMemo[$key];
    }

    /**
     * Count a catalog or user resource under the selected branch scope.
     *
     * @param  class-string  $modelClass
     * @param  array<string, mixed>  $filters
     *
     * @throws JsonException
     */
    protected function catalogCount(string $modelClass, array $filters): array
    {
        $segment = 'count_'.strtolower(class_basename($modelClass));
        $count = $this->remember($segment, $filters, function () use ($modelClass, $filters): int {
            $query = $modelClass::query()->withoutGlobalActive();

            if (in_array($modelClass, [Product::class, Category::class], true)) {
                $query->whereHas('menu', fn (Builder $menuQuery) => $menuQuery
                    ->when($filters['branch_id'], fn (Builder $branchQuery) => $branchQuery
                        ->where('branch_id', $filters['branch_id'])));
            } else {
                $query->when($filters['branch_id'], fn (Builder $branchQuery) => $branchQuery
                    ->where('branch_id', $filters['branch_id']));
            }

            return $query->count();
        }, false);

        return ['value' => (int) $count];
    }

    /**
     * Get current and previous-period sales and order series.
     *
     * @param  array<string, mixed>  $filters
     *
     * @throws JsonException
     */
    protected function salesAnalytics(array $filters): array
    {
        $requestedGranularity = data_get($filters, 'widget_options.sales_analytics.granularity', 'auto');
        $granularity = $this->resolveGranularity($filters, $requestedGranularity);

        return $this->remember('sales_analytics_'.$granularity, $filters, function () use ($filters, $granularity): array {
            $current = $this->trend(
                $filters,
                $filters['from_at'],
                $filters['to_at'],
                $granularity,
            );
            $previous = $this->trend(
                $filters,
                $filters['comparison_from_at'],
                $filters['comparison_to_at'],
                $granularity,
            );

            return [
                'labels' => $current['labels'],
                'sales' => $current['sales'],
                'orders' => $current['orders'],
                'previous_sales' => $this->alignSeries(
                    $previous['sales'],
                    count($current['labels']),
                ),
                'previous_orders' => $this->alignSeries(
                    $previous['orders'],
                    count($current['labels']),
                ),
                'granularity' => $granularity,
                'currency' => $filters['currency'],
            ];
        });
    }

    /**
     * Aggregate an order series into stable, gap-filled time buckets.
     *
     * @param  array<string, mixed>  $filters
     * @return array{labels: array<int, string>, sales: array<int, float>, orders: array<int, int>}
     */
    protected function trend(
        array $filters,
        CarbonImmutable $from,
        CarbonImmutable $to,
        string $granularity,
    ): array {
        $bucketExpression = $this->dateBucketExpression('orders.created_at', $granularity);
        $salesExpression = $this->currencyExpression('orders.total', $filters);
        $rows = $this->ordersRangeQuery($filters, $from, $to)
            ->selectRaw("$bucketExpression as bucket")
            ->selectRaw("COALESCE(SUM($salesExpression), 0) as sales")
            ->selectRaw('COUNT(orders.id) as orders')
            ->groupBy(DB::raw($bucketExpression))
            ->orderBy('bucket')
            ->get()
            ->keyBy('bucket');
        $buckets = $this->buckets($from, $to, $granularity);

        return [
            'labels' => array_keys($buckets),
            'sales' => collect($buckets)
                ->map(fn ($unused, string $bucket) => round(
                    (float) ($rows->get($bucket)?->sales ?? 0),
                    Currency::subunit($filters['currency']),
                ))
                ->values()
                ->all(),
            'orders' => collect($buckets)
                ->map(fn ($unused, string $bucket) => (int) ($rows->get($bucket)?->orders ?? 0))
                ->values()
                ->all(),
        ];
    }

    /**
     * Get a database-portable time bucket expression.
     */
    protected function dateBucketExpression(string $column, string $granularity): string
    {
        if (DB::getDriverName() === 'pgsql') {
            return match ($granularity) {
                'hour' => "to_char(date_trunc('hour', $column), 'YYYY-MM-DD HH24:MI:SS')",
                'week' => "to_char(date_trunc('week', $column), 'YYYY-MM-DD')",
                'month' => "to_char(date_trunc('month', $column), 'YYYY-MM-DD')",
                default => "DATE($column)",
            };
        }

        if (DB::connection()->getDriverName() === 'sqlite') {
            return match ($granularity) {
                'hour' => "strftime('%Y-%m-%d %H:00:00', $column)",
                'week' => "date($column, '-' || ((strftime('%w', $column) + 6) % 7) || ' days')",
                'month' => "strftime('%Y-%m-01', $column)",
                default => "date($column)",
            };
        }

        return match ($granularity) {
            'hour' => "DATE_FORMAT($column, '%Y-%m-%d %H:00:00')",
            'week' => "DATE_FORMAT(DATE_SUB($column, INTERVAL WEEKDAY($column) DAY), '%Y-%m-%d')",
            'month' => "DATE_FORMAT($column, '%Y-%m-01')",
            default => "DATE($column)",
        };
    }

    /**
     * Build a gap-free list of time buckets.
     *
     * @return array<string, true>
     */
    protected function buckets(CarbonImmutable $from, CarbonImmutable $to, string $granularity): array
    {
        $cursor = match ($granularity) {
            'hour' => $from->startOfHour(),
            'week' => $from->startOfWeek(),
            'month' => $from->startOfMonth(),
            default => $from->startOfDay(),
        };
        $last = match ($granularity) {
            'hour' => $to->startOfHour(),
            'week' => $to->startOfWeek(),
            'month' => $to->startOfMonth(),
            default => $to->startOfDay(),
        };
        $buckets = [];

        while ($cursor->lte($last)) {
            $key = match ($granularity) {
                'hour' => $cursor->format('Y-m-d H:00:00'),
                'month' => $cursor->format('Y-m-01'),
                default => $cursor->toDateString(),
            };
            $buckets[$key] = true;
            $cursor = match ($granularity) {
                'hour' => $cursor->addHour(),
                'week' => $cursor->addWeek(),
                'month' => $cursor->addMonth(),
                default => $cursor->addDay(),
            };
        }

        return $buckets;
    }

    /**
     * Align a comparison series with the current chart labels.
     *
     * Calendar buckets can have a different count in adjacent equal-length periods.
     *
     * @param  array<int, float|int>  $series
     * @return array<int, float|int>
     */
    protected function alignSeries(array $series, int $length): array
    {
        return array_pad(array_slice(array_values($series), 0, $length), $length, 0);
    }

    /**
     * Get a single date's hourly sales series.
     *
     * @param  array<string, mixed>  $filters
     *
     * @throws JsonException
     */
    protected function hourlySalesTrend(array $filters): array
    {
        $requestedDate = data_get($filters, 'widget_options.hourly_sales_trend.date');
        $date = CarbonImmutable::parse($requestedDate ?: $filters['to']);
        $hourlyFilters = [
            ...$filters,
            'hourly_date' => $date->toDateString(),
        ];

        return $this->remember('hourly_sales_trend', $hourlyFilters, function () use ($filters, $date): array {
            $trend = $this->trend($filters, $date->startOfDay(), $date->endOfDay(), 'hour');

            return [
                ...$trend,
                'date' => $date->toDateString(),
                'currency' => $filters['currency'],
            ];
        });
    }

    /**
     * Get top products using a database aggregate rather than loading all order items.
     *
     * @param  array<string, mixed>  $filters
     *
     * @throws JsonException
     */
    protected function topSellingProducts(array $filters): array
    {
        $limit = (int) data_get($filters, 'widget_options.top_selling_products.limit', 5);
        $topFilters = [...$filters, 'top_products_limit' => $limit];

        return $this->remember('top_selling_products', $topFilters, function () use ($filters, $limit): array {
            $salesExpression = $this->currencyExpression('order_products.total', $filters, 'order_products');
            $rows = OrderProduct::query()
                ->without(['product', 'taxes', 'options'])
                ->join('orders', 'orders.id', '=', 'order_products.order_id')
                ->select('order_products.product_id')
                ->selectRaw('SUM(order_products.quantity) as total_quantity')
                ->selectRaw("COALESCE(SUM($salesExpression), 0) as total_sales")
                ->when($filters['branch_id'], fn (Builder $query) => $query
                    ->where('orders.branch_id', $filters['branch_id']))
                ->whereBetween('orders.created_at', [$filters['from_at'], $filters['to_at']])
                ->whereNotIn('orders.status', [
                    OrderStatus::Cancelled,
                    OrderStatus::Refunded,
                    OrderStatus::Merged,
                ])
                ->groupBy('order_products.product_id')
                ->orderByDesc('total_quantity')
                ->limit($limit)
                ->get();

            $rows->load('product.files');

            return $rows->map(function (OrderProduct $row) use ($filters): array {
                $product = $row->product;

                return [
                    'id' => $row->product_id,
                    'name' => $product?->name ?? __('dashboard::dashboards.labels.unknown_product'),
                    'thumbnail' => $product?->thumbnail?->preview_image_url,
                    'total_quantity' => (int) $row->total_quantity,
                    'total_sales' => $this->money((float) $row->total_sales, $filters),
                ];
            })->all();
        });
    }

    /**
     * Get sales and order totals by branch.
     *
     * @param  array<string, mixed>  $filters
     *
     * @throws JsonException
     */
    protected function branchPerformance(array $filters): array
    {
        return $this->remember('branch_performance', $filters, function () use ($filters): array {
            $salesExpression = $this->currencyExpression('orders.total', $filters);

            return $this->ordersRangeQuery($filters, $filters['from_at'], $filters['to_at'])
                ->select('orders.branch_id')
                ->selectRaw("COALESCE(SUM($salesExpression), 0) as total_sales")
                ->selectRaw('COUNT(orders.id) as total_orders')
                ->with('branch:id,name,currency')
                ->groupBy('orders.branch_id')
                ->orderByDesc('total_sales')
                ->get()
                ->map(fn (Order $order) => [
                    'id' => $order->branch_id,
                    'label' => $order->branch?->name ?? __('dashboard::dashboards.labels.unknown_branch'),
                    'orders' => (int) $order->total_orders,
                    'value' => $this->money((float) $order->total_sales, $filters),
                ])
                ->all();
        });
    }

    /**
     * Get the configured number of best-performing branches.
     *
     * @param  array<string, mixed>  $filters
     *
     * @throws JsonException
     */
    protected function bestPerformingBranches(array $filters): array
    {
        $limit = (int) data_get($filters, 'widget_options.best_performing_branches.limit', 5);

        return array_slice($this->branchPerformance($filters), 0, $limit);
    }

    /**
     * Get order counts grouped by status, type, source, or payment status.
     *
     * @throws JsonException
     */
    protected function orderDistribution(string $column, array $filters): array
    {
        return $this->remember('order_distribution_'.$column, $filters, function () use ($column, $filters): array {
            $groupExpression = $column === 'source'
                ? "CASE WHEN orders.source = 'table_qr' THEN orders.source ELSE 'pos' END"
                : "orders.$column";
            $query = $this->ordersRangeQuery(
                $filters,
                $filters['from_at'],
                $filters['to_at'],
                $column !== 'status',
            );
            $rows = $query
                ->selectRaw("$groupExpression as dashboard_group")
                ->selectRaw('COUNT(orders.id) as total')
                ->groupBy(DB::raw($groupExpression))
                ->orderByDesc('total')
                ->toBase()
                ->get();

            return $rows->map(function (object $row) use ($column): array {
                $value = $row->dashboard_group;

                if ($column === 'status') {
                    $status = OrderStatus::tryFrom((string) $value);

                    return [
                        'id' => (string) $value,
                        'label' => $status?->trans() ?? (string) $value,
                        'value' => (int) $row->total,
                        'color' => $status?->color() ?? '#94A3B8',
                    ];
                }

                if ($column === 'type') {
                    $type = OrderType::tryFrom((string) $value);

                    return [
                        'id' => (string) $value,
                        'label' => $type?->trans() ?? (string) $value,
                        'value' => (int) $row->total,
                        'color' => OrderType::getColor((string) $value),
                    ];
                }

                if ($column === 'payment_status') {
                    $paymentStatus = OrderPaymentStatus::tryFrom((string) $value);
                    $colors = [
                        OrderPaymentStatus::Paid->value => '#16A34A',
                        OrderPaymentStatus::PartiallyPaid->value => '#F59E0B',
                        OrderPaymentStatus::Unpaid->value => '#E11D48',
                    ];

                    return [
                        'id' => (string) $value,
                        'label' => $paymentStatus?->trans() ?? (string) $value,
                        'value' => (int) $row->total,
                        'color' => $colors[(string) $value] ?? '#94A3B8',
                    ];
                }

                $source = OrderSource::tryFrom((string) $value);

                return [
                    'id' => $source?->value ?? 'pos',
                    'label' => $source?->trans() ?? __('dashboard::dashboards.labels.pos'),
                    'value' => (int) $row->total,
                    'color' => $source ? '#03C3EC' : '#F57C00',
                ];
            })->values()->all();
        });
    }

    /**
     * Get sales and order volume by weekday for staffing and opening-hour decisions.
     *
     * @param  array<string, mixed>  $filters
     *
     * @throws JsonException
     */
    protected function salesByWeekday(array $filters): array
    {
        return $this->remember('sales_by_weekday', $filters, function () use ($filters): array {
            $weekdayExpression = match (DB::getDriverName()) {
                'sqlite' => "((CAST(strftime('%w', orders.created_at) AS INTEGER) + 6) % 7)",
                'pgsql' => '(EXTRACT(ISODOW FROM orders.created_at)::integer - 1)',
                default => 'WEEKDAY(orders.created_at)',
            };
            $salesExpression = $this->currencyExpression('orders.total', $filters);
            $rows = $this->ordersRangeQuery($filters, $filters['from_at'], $filters['to_at'])
                ->selectRaw("$weekdayExpression as weekday")
                ->selectRaw("COALESCE(SUM($salesExpression), 0) as sales")
                ->selectRaw('COUNT(orders.id) as orders')
                ->groupBy(DB::raw($weekdayExpression))
                ->toBase()
                ->get()
                ->keyBy(fn (object $row): int => (int) $row->weekday);
            $weekdays = collect(Day::cases());
            $startOfWeek = Day::tryFrom(
                (string) setting('start_of_week', Day::Sunday->value),
            ) ?? Day::Sunday;
            $startIndex = $weekdays->search($startOfWeek, true);
            $weekdays = $weekdays
                ->slice($startIndex)
                ->concat($weekdays->take($startIndex))
                ->values();

            return [
                'labels' => $weekdays
                    ->map(fn (Day $day): string => $day->trans())
                    ->all(),
                'sales' => $weekdays
                    ->map(fn (Day $day): float => round(
                        (float) ($rows->get(($day->carbonKey() + 6) % 7)?->sales ?? 0),
                        Currency::subunit($filters['currency']),
                    ))
                    ->all(),
                'orders' => $weekdays
                    ->map(fn (Day $day): int => (int) ($rows->get(
                        ($day->carbonKey() + 6) % 7,
                    )?->orders ?? 0))
                    ->all(),
                'currency' => $filters['currency'],
            ];
        });
    }

    /**
     * Get low-stock ingredients for the selected branch.
     *
     * @throws JsonException
     */
    protected function lowStockAlerts(array $filters): array
    {
        $limit = (int) data_get($filters, 'widget_options.low_stock_alerts.limit', 5);
        $stockFilters = [...$filters, 'low_stock_limit' => $limit];

        return $this->remember('low_stock_alerts', $stockFilters, fn () => Ingredient::query()
            ->when($filters['branch_id'], fn (Builder $query) => $query->where('branch_id', $filters['branch_id']))
            ->whereColumn('current_stock', '<=', 'alert_quantity')
            ->orderByRaw('CASE WHEN current_stock <= 0 THEN 0 ELSE 1 END')
            ->orderBy('current_stock')
            ->limit($limit)
            ->get()
            ->map(fn (Ingredient $ingredient) => [
                'id' => $ingredient->id,
                'name' => $ingredient->name,
                'current_stock' => $ingredient->current_stock,
                'alert_quantity' => $ingredient->alert_quantity,
                'symbol' => strtoupper((string) $ingredient->unit?->symbol),
                'out_of_stock' => $ingredient->current_stock <= 0,
            ])
            ->all(), false);
    }

    /**
     * Get point-of-sale cash movement totals by direction.
     *
     * @throws JsonException
     */
    protected function cashMovementsOverview(array $filters): array
    {
        return $this->remember('cash_movements_overview', $filters, function () use ($filters): array {
            $amountExpression = $this->currencyExpression('pos_cash_movements.amount', $filters, 'pos_cash_movements');

            return PosCashMovement::query()
                ->when($filters['branch_id'], fn (Builder $query) => $query
                    ->where('pos_cash_movements.branch_id', $filters['branch_id']))
                ->whereBetween('pos_cash_movements.occurred_at', [$filters['from_at'], $filters['to_at']])
                ->select('pos_cash_movements.direction')
                ->selectRaw("COALESCE(SUM($amountExpression), 0) as total")
                ->groupBy('pos_cash_movements.direction')
                ->get()
                ->map(fn (PosCashMovement $movement) => [
                    'id' => $movement->direction->value,
                    'label' => $movement->direction->trans(),
                    'value' => (float) $movement->total,
                    'formatted' => $this->money((float) $movement->total, $filters),
                    'color' => $movement->direction->color(),
                ])
                ->all();
        });
    }

    /**
     * Get non-voided payment totals by method.
     *
     * @throws JsonException
     */
    protected function paymentsOverview(array $filters): array
    {
        return $this->remember('payments_overview', $filters, function () use ($filters): array {
            $amountExpression = $this->currencyExpression('payments.amount', $filters, 'payments');

            return Payment::query()
                ->active()
                ->when($filters['branch_id'], fn (Builder $query) => $query->where('payments.branch_id', $filters['branch_id']))
                ->whereBetween('payments.created_at', [$filters['from_at'], $filters['to_at']])
                ->whereHas('order', fn (Builder $query) => $query->withoutCanceledOrders())
                ->select('payments.method')
                ->selectRaw("COALESCE(SUM($amountExpression), 0) as total")
                ->groupBy('payments.method')
                ->orderByDesc('total')
                ->get()
                ->map(function (Payment $payment) use ($filters): array {
                    $method = $payment->method;

                    return [
                        'id' => $method->value,
                        'label' => $method->trans(),
                        'value' => (float) $payment->total,
                        'formatted' => $this->money((float) $payment->total, $filters),
                        'color' => PaymentMethod::getColor($method->value),
                    ];
                })
                ->all();
        });
    }

    /**
     * Get gross profit, cost, and margin for the selected period.
     *
     * @param  array<string, mixed>  $filters
     *
     * @throws JsonException
     */
    protected function grossProfitMargin(array $filters): array
    {
        $summary = $this->performanceSummary($filters);
        $sales = (float) $summary['sales'];
        $grossProfit = (float) $summary['gross_profit'];

        return [
            'gross_profit' => $this->money($grossProfit, $filters),
            'cost' => $this->money((float) $summary['cost'], $filters),
            'margin_percent' => $sales > 0 ? round(($grossProfit / $sales) * 100, 1) : 0,
            'comparison' => $this->comparison(
                $grossProfit,
                (float) $summary['previous_gross_profit'],
            ),
        ];
    }

    /**
     * Get walk-in, identified, unique, and repeat customer activity.
     *
     * @param  array<string, mixed>  $filters
     *
     * @throws JsonException
     */
    protected function customerMix(array $filters): array
    {
        return $this->remember('customer_mix', $filters, function () use ($filters): array {
            $base = $this->ordersRangeQuery($filters, $filters['from_at'], $filters['to_at']);
            $summary = (clone $base)
                ->selectRaw('SUM(CASE WHEN customer_id IS NULL THEN 1 ELSE 0 END) as walk_in_orders')
                ->selectRaw('SUM(CASE WHEN customer_id IS NOT NULL THEN 1 ELSE 0 END) as identified_orders')
                ->selectRaw('COUNT(DISTINCT customer_id) as unique_customers')
                ->first();
            $repeatCustomers = DB::query()
                ->fromSub(
                    (clone $base)
                        ->whereNotNull('customer_id')
                        ->select('customer_id')
                        ->groupBy('customer_id')
                        ->havingRaw('COUNT(*) > 1'),
                    'repeat_customers',
                )
                ->count();
            $uniqueCustomers = (int) ($summary->unique_customers ?? 0);

            return [
                'items' => [
                    [
                        'id' => 'identified',
                        'label' => __('dashboard::dashboards.labels.identified_customers'),
                        'value' => (int) ($summary->identified_orders ?? 0),
                        'color' => '#043A63',
                    ],
                    [
                        'id' => 'walk_in',
                        'label' => __('dashboard::dashboards.labels.walk_in'),
                        'value' => (int) ($summary->walk_in_orders ?? 0),
                        'color' => '#F57C00',
                    ],
                ],
                'unique_customers' => $uniqueCustomers,
                'repeat_customers' => $repeatCustomers,
                'repeat_rate' => $uniqueCustomers > 0
                    ? round(($repeatCustomers / $uniqueCustomers) * 100, 1)
                    : 0,
            ];
        });
    }

    /**
     * Get operational service-time and cancellation metrics.
     *
     * @param  array<string, mixed>  $filters
     *
     * @throws JsonException
     */
    protected function fulfillmentHealth(array $filters): array
    {
        return $this->remember('fulfillment_health', $filters, function () use ($filters): array {
            $serviceMinutes = $this->minutesDifferenceExpression('orders.created_at', 'orders.served_at');
            $completionMinutes = $this->minutesDifferenceExpression('orders.created_at', 'orders.closed_at');
            $cancelled = OrderStatus::Cancelled->value;
            $refunded = OrderStatus::Refunded->value;
            $merged = OrderStatus::Merged->value;
            $completed = OrderStatus::Completed->value;
            $row = $this->ordersRangeQuery(
                $filters,
                $filters['from_at'],
                $filters['to_at'],
                false,
            )
                ->selectRaw('COUNT(orders.id) as total_orders')
                ->selectRaw("SUM(CASE WHEN orders.status = '$completed' THEN 1 ELSE 0 END) as completed_orders")
                ->selectRaw("SUM(CASE WHEN orders.status IN ('$cancelled', '$refunded') THEN 1 ELSE 0 END) as failed_orders")
                ->selectRaw("SUM(CASE WHEN orders.status = '$merged' THEN 1 ELSE 0 END) as merged_orders")
                ->selectRaw("AVG(CASE WHEN orders.served_at IS NOT NULL THEN $serviceMinutes END) as average_service_minutes")
                ->selectRaw("AVG(CASE WHEN orders.closed_at IS NOT NULL THEN $completionMinutes END) as average_completion_minutes")
                ->first();
            $totalOrders = max(0, (int) ($row->total_orders ?? 0) - (int) ($row->merged_orders ?? 0));
            $completedOrders = (int) ($row->completed_orders ?? 0);
            $failedOrders = (int) ($row->failed_orders ?? 0);

            return [
                'average_service_minutes' => round((float) ($row->average_service_minutes ?? 0), 1),
                'average_completion_minutes' => round((float) ($row->average_completion_minutes ?? 0), 1),
                'cancelled_or_refunded' => $failedOrders,
                'completion_rate' => $totalOrders > 0
                    ? round(($completedOrders / $totalOrders) * 100, 1)
                    : 0,
            ];
        });
    }

    /**
     * Get a database-portable minute difference expression.
     */
    protected function minutesDifferenceExpression(string $fromColumn, string $toColumn): string
    {
        if (DB::getDriverName() === 'pgsql') {
            return "TRUNC(EXTRACT(EPOCH FROM ($toColumn - $fromColumn)) / 60)";
        }

        if (DB::connection()->getDriverName() === 'sqlite') {
            return "(julianday($toColumn) - julianday($fromColumn)) * 1440";
        }

        return "TIMESTAMPDIFF(MINUTE, $fromColumn, $toColumn)";
    }
}
