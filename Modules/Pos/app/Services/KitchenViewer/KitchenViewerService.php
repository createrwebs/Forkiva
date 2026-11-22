<?php

namespace Modules\Pos\Services\KitchenViewer;

use Arr;
use Carbon\Carbon;
use DB;
use Illuminate\Database\Eloquent\Builder;
use Modules\Branch\Models\Branch;
use Modules\Category\Models\Category;
use Modules\Core\Contracts\DeliveryFeatureInterface;
use Modules\Order\Enums\OrderProductStatus;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\OrderType;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderProduct;
use Modules\Order\Support\OrderWriteLock;
use Modules\User\Models\User;
use Throwable;

class KitchenViewerService implements KitchenViewerServiceInterface
{
    private const DEFAULT_KITCHEN_ORDERS_LIMIT = 150;

    private const MAX_KITCHEN_ORDERS_LIMIT = 200;

    /** {@inheritDoc} */
    public function getOrders(?int $branchId = null, ?string $lastUpdatedAt = null, array $filters = []): array
    {
        $user = auth()->user();
        $branchId ??= $user->effective_branch?->id;
        $deliveryFeature = app(DeliveryFeatureInterface::class);

        $categoryIds = $this->resolveKitchenCategoryIds($user);
        $filters = $this->normalizeKitchenFilters($filters);

        $baseQuery = Order::query()
            ->where('branch_id', $branchId)
            ->forKitchenCategories($categoryIds)
            ->visibleForKitchen()
            ->activeOrders()
            ->whereIn('status', $this->kitchenOrderStatusValues());

        $this->applyKitchenFilters($baseQuery, $filters);

        $currentLastUpdatedAt = $this->resolveKitchenLastUpdatedAt($baseQuery, $categoryIds);

        $normalizedLastUpdatedAt = $this->normalizeLastUpdatedAt($lastUpdatedAt);

        if (! is_null($normalizedLastUpdatedAt) && $normalizedLastUpdatedAt === $currentLastUpdatedAt) {
            return [
                'not_modified' => true,
                'last_updated_at' => $currentLastUpdatedAt,
            ];
        }

        $orders = (clone $baseQuery)
            ->select([
                'id',
                'branch_id',
                'source',
                'table_id',
                'customer_id',
                'notes',
                'reference_no',
                'order_number',
                'status',
                'type',
                'payment_status',
                'preparation_started_at',
                'prepared_at',
                'created_at',
                'updated_at',
            ])
            ->with($this->kitchenOrderRelations($categoryIds, $deliveryFeature->enabled()))
            ->orderByDesc('updated_at')
            ->limit($filters['limit'] + 1)
            ->get();

        $hasMore = $orders->count() > $filters['limit'];

        return [
            'not_modified' => false,
            'orders' => $orders->take($filters['limit'])->values(),
            'has_more' => $hasMore,
            'limit' => $filters['limit'],
            'last_updated_at' => $currentLastUpdatedAt,
        ];
    }

    /**
     * Resolve kitchen category ids
     */
    private function resolveKitchenCategoryIds(User $user): array
    {
        if (empty($user->category_slugs)) {
            return [];
        }

        return Category::query()
            ->whereIn('slug', $user->category_slugs)
            ->get()
            ->flatMap(fn ($category) => $category->descendants()->pluck('id'))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Normalize kitchen viewer order filters.
     *
     * @param  array<string, mixed>  $filters
     * @return array{search: string, types: array<int, string>, statuses: array<int, string>, limit: int}
     */
    private function normalizeKitchenFilters(array $filters): array
    {
        $search = $filters['search'] ?? '';
        $types = [
            ...Arr::wrap($filters['type'] ?? []),
            ...Arr::wrap($filters['types'] ?? []),
        ];
        $statuses = [
            ...Arr::wrap($filters['status'] ?? []),
            ...Arr::wrap($filters['statuses'] ?? []),
        ];

        return [
            'search' => mb_substr(trim(is_array($search) ? '' : (string) $search), 0, 120),
            'types' => $this->normalizeFilterValues($types, OrderType::values()),
            'statuses' => $this->normalizeFilterValues($statuses, $this->kitchenOrderStatusValues()),
            'limit' => $this->normalizeKitchenLimit($filters['limit'] ?? null),
        ];
    }

    /**
     * Normalize scalar or array filter values against an allowed enum value list.
     *
     * @param  array<int, mixed>  $values
     * @param  array<int, string>  $allowedValues
     * @return array<int, string>
     */
    private function normalizeFilterValues(array $values, array $allowedValues): array
    {
        return collect($values)
            ->filter(fn ($value) => is_string($value) || is_numeric($value))
            ->map(fn ($value) => (string) $value)
            ->filter(fn (string $value) => $value !== '' && in_array($value, $allowedValues, true))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Kitchen-visible order status values.
     *
     * @return array<int, string>
     */
    private function kitchenOrderStatusValues(): array
    {
        return [
            OrderStatus::Confirmed->value,
            OrderStatus::Preparing->value,
            OrderStatus::Ready->value,
            OrderStatus::Served->value,
        ];
    }

    /**
     * Normalize kitchen order response limit.
     */
    private function normalizeKitchenLimit(mixed $limit): int
    {
        if (is_array($limit) || ! filled($limit)) {
            return self::DEFAULT_KITCHEN_ORDERS_LIMIT;
        }

        return min(self::MAX_KITCHEN_ORDERS_LIMIT, max(1, (int) $limit));
    }

    /**
     * Apply backend kitchen viewer filters.
     *
     * @param  array{search: string, types: array<int, string>, statuses: array<int, string>, limit: int}  $filters
     */
    private function applyKitchenFilters(Builder $query, array $filters): void
    {
        $query
            ->when(
                filled($filters['search']),
                fn (Builder $query) => $query->search($filters['search'])
            )
            ->when(
                count($filters['types']) > 0,
                fn (Builder $query) => $query->whereIn('orders.type', $filters['types'])
            )
            ->when(
                count($filters['statuses']) > 0,
                fn (Builder $query) => $query->whereIn('orders.status', $filters['statuses'])
            );
    }

    /**
     * Resolve the latest visible kitchen order or item update timestamp.
     */
    private function resolveKitchenLastUpdatedAt(Builder $baseQuery, array $categoryIds): ?string
    {
        $orderUpdatedAt = (clone $baseQuery)->max('orders.updated_at');
        $productUpdatedAt = OrderProduct::query()
            ->whereIn('order_id', (clone $baseQuery)->select('orders.id'))
            ->when(
                count($categoryIds),
                fn ($q) => $q->whereHas(
                    'product.categories',
                    fn ($q) => $q->whereIn('id', $categoryIds)
                )
            )
            ->max('order_products.updated_at');

        $latest = collect([$orderUpdatedAt, $productUpdatedAt])
            ->filter()
            ->map(fn ($timestamp) => Carbon::parse($timestamp))
            ->sortDesc()
            ->first();

        return $latest?->toISOString();
    }

    /**
     * Normalize client timestamps before comparing with the current server value.
     */
    private function normalizeLastUpdatedAt(?string $lastUpdatedAt): ?string
    {
        if (blank($lastUpdatedAt)) {
            return null;
        }

        try {
            return Carbon::parse($lastUpdatedAt)->toISOString();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Relations required by the kitchen board payload.
     */
    private function kitchenOrderRelations(array $categoryIds, bool $deliveryEnabled): array
    {
        return [
            'table:id,name',
            'customer:id,name',
            'products' => fn ($query) => $query
                ->select([
                    'id',
                    'order_id',
                    'product_id',
                    'prep_time_minutes',
                    'preparation_started_at',
                    'prepared_at',
                    'quantity',
                    'status',
                    'updated_at',
                ])
                ->without('taxes')
                ->with([
                    'product' => fn ($query) => $query
                        ->select('id', 'name')
                        ->without(['branch']),
                    'options' => fn ($query) => $query
                        ->select([
                            'id',
                            'order_product_id',
                            'option_id',
                            'value',
                        ])
                        ->with([
                            'option:id,name',
                            'values' => fn ($query) => $query
                                ->select('option_values.id', 'option_values.label'),
                        ]),
                ])
                ->when(
                    count($categoryIds),
                    fn ($query) => $query->whereHas(
                        'product.categories',
                        fn ($query) => $query->whereIn('id', $categoryIds)
                    )
                )
                ->when(
                    $deliveryEnabled,
                    fn ($query) => $query->with([
                        'order' => fn ($query) => $query
                            ->select('id')
                            ->with('orderDelivery:id,order_id'),
                    ])
                ),
        ];
    }

    /** {@inheritDoc} */
    public function getConfiguration(?int $branchId = null): array
    {
        $user = auth()->user();
        $branch = null;

        $data = [
            'branches' => [],
            'order_types' => [],
            'order_statuses' => $this->kitchenOrderStatuses(),
            'branch_id' => $user->assignedToBranch() ? $user->branch_id : $branchId,
            'settings' => $this->kitchenViewerSettings(),
        ];

        if (is_null($data['branch_id'])) {
            if ($user->assignedToBranch()) {
                $data['branches'][] = [
                    'id' => $user->branch->id,
                    'name' => $user->branch->name,
                    'currency' => $user->branch->currency,
                ];
                $branch = $user->branch;
            } else {
                $data['branches'] = Branch::list();
            }
        }

        if (count($data['branches']) > 0 || ! is_null($data['branch_id'])) {
            if (is_null($data['branch_id'])) {
                $data['branch_id'] = $data['branches'][0]['id'];
            }

            $branch = $branch ?? Branch::select(
                'id',
                'name',
                'order_types',
                'currency')
                ->findOrFail($data['branch_id']);

            $data['order_types'] = array_values(array_filter(
                OrderType::toArrayTrans(),
                fn ($orderType) => in_array($orderType['id'], ($branch->order_types ?: []))
            ));
        }

        return $data;
    }

    /**
     * Kitchen-visible order statuses with translations.
     */
    private function kitchenOrderStatuses(): array
    {
        return OrderStatus::toArrayTrans(
            array_values(array_diff(OrderStatus::values(), $this->kitchenOrderStatusValues()))
        );
    }

    /**
     * Kitchen viewer runtime settings.
     */
    private function kitchenViewerSettings(): array
    {
        $autoRefreshEnabled = filter_var(setting('auto_refresh_enabled', false), FILTER_VALIDATE_BOOLEAN);
        $soundEnabled = filter_var(setting('kitchen_sound_enabled', true), FILTER_VALIDATE_BOOLEAN);

        return [
            'served_orders_visibility' => [
                'enabled' => filter_var(setting('kitchen_show_served_orders_enabled', true), FILTER_VALIDATE_BOOLEAN),
                'minutes' => min(20, max(1, (int) setting('kitchen_show_served_orders_minutes', 5))),
            ],
            'auto_refresh' => [
                'enabled' => $autoRefreshEnabled,
                ...($autoRefreshEnabled ? [
                    'mode' => setting('auto_refresh_mode'),
                    'interval' => setting('auto_refresh_interval'),
                    'pause_on_idle' => setting('auto_refresh_pause_on_idle'),
                    'idle_timeout' => setting('auto_refresh_idle_timeout'),
                ] : []),
            ],
            'sounds' => [
                'enabled' => $soundEnabled,
                ...($soundEnabled ? [
                    'new_order' => setting('kitchen_new_order_sound', 'chime'),
                    'order_updated' => setting('kitchen_order_updated_sound', 'double_beep'),
                    'volume' => min(100, max(0, (int) setting('kitchen_sound_volume', 70))),
                ] : []),
            ],
        ];
    }

    /** {@inheritDoc} */
    public function moveOrderProductToNextStatus(int|string $orderId, array|int $ids): Order
    {
        return OrderWriteLock::run($orderId, fn () => $this->moveOrderProductToNextStatusLocked($orderId, $ids));
    }

    private function moveOrderProductToNextStatusLocked(int|string $orderId, array|int $ids): Order
    {
        $user = auth()->user();
        $categoryIds = $this->resolveKitchenCategoryIds($user);
        $deliveryFeature = app(DeliveryFeatureInterface::class);

        $order = Order::query()
            ->whereIdOrReference($orderId)
            ->whereNot('status', OrderStatus::Served)
            ->activeOrders()
            ->firstOrFail();
        $orderProducts = $order->products()->whereIn('id', Arr::wrap($ids))->get();

        if (count($orderProducts)) {
            DB::transaction(function () use ($order, $orderProducts) {
                /** @var OrderProduct $orderProduct */
                foreach ($orderProducts as $orderProduct) {
                    $nextStatus = $orderProduct->status->nextStatus();

                    if ($nextStatus === null || $this->shouldBlockKitchenAdvance($order, $nextStatus)) {
                        continue;
                    }

                    $timing = match ($nextStatus) {
                        OrderProductStatus::Preparing => is_null($orderProduct->preparation_started_at)
                            ? ['preparation_started_at' => now()]
                            : [],
                        OrderProductStatus::Ready => is_null($orderProduct->prepared_at)
                            ? ['prepared_at' => now()]
                            : [],
                        default => [],
                    };

                    $orderProduct->update(['status' => $nextStatus, ...$timing]);
                }

                $order->recalculateOrderStatus();
            });
        }

        return $this->loadKitchenOrder($order->id, $categoryIds, $deliveryFeature->enabled());
    }

    /**
     * Determine whether kitchen progression should stop for a delivery order.
     */
    protected function shouldBlockKitchenAdvance(Order $order, OrderProductStatus $nextStatus): bool
    {
        return $nextStatus === OrderProductStatus::Served
            && app(DeliveryFeatureInterface::class)->enabled()
            && $order->orderDelivery()->exists();
    }

    /**
     * Load a single kitchen order using the board payload shape.
     */
    private function loadKitchenOrder(int $orderId, array $categoryIds, bool $deliveryEnabled): Order
    {
        return Order::query()
            ->select([
                'id',
                'source',
                'branch_id',
                'table_id',
                'customer_id',
                'notes',
                'reference_no',
                'order_number',
                'status',
                'type',
                'payment_status',
                'preparation_started_at',
                'prepared_at',
                'created_at',
                'updated_at',
            ])
            ->with($this->kitchenOrderRelations($categoryIds, $deliveryEnabled))
            ->findOrFail($orderId);
    }
}
