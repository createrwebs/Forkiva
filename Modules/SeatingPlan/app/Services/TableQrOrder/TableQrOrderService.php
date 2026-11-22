<?php

namespace Modules\SeatingPlan\Services\TableQrOrder;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use JsonException;
use Modules\Branch\Models\Branch;
use Modules\Category\Models\Category;
use Modules\Discount\Services\DiscountApplyService\DiscountApplyServiceInterface;
use Modules\Menu\Models\Menu;
use Modules\Order\Enums\OrderPaymentStatus;
use Modules\Order\Enums\OrderProductStatus;
use Modules\Order\Enums\OrderSource;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\OrderType;
use Modules\Order\Facades\Pricing;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderProduct;
use Modules\Order\Services\Checkout\CheckoutConfirmation;
use Modules\Order\Services\Checkout\CheckoutPricing;
use Modules\Order\Services\SaveOrder\SaveOrderServiceInterface;
use Modules\Order\Support\PricingCatalog;
use Modules\Pos\Enums\PosSessionStatus;
use Modules\Pos\Enums\PosSubmitAction;
use Modules\Pos\Models\PosSession;
use Modules\Pos\Transformers\Api\V1\Pos\PosCategoryResource;
use Modules\Pos\Transformers\Api\V1\Pos\PosProductResource;
use Modules\Product\Models\Product;
use Modules\SeatingPlan\Enums\TableQrEventType;
use Modules\SeatingPlan\Enums\TableStatus;
use Modules\SeatingPlan\Exceptions\TableQrOrderException;
use Modules\SeatingPlan\Models\Table;
use Modules\SeatingPlan\Models\TableQrEvent;
use Modules\Voucher\Models\Voucher;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TableQrOrderService implements TableQrOrderServiceInterface
{
    private const CART_META_TYPE = 'table_qr_meta';

    public function __construct(
        protected SaveOrderServiceInterface $saveOrderService,
        protected DiscountApplyServiceInterface $discountApplyService
    ) {}

    /** {@inheritDoc} */
    public function context(string $uuid, ?string $token, Request $request): array
    {
        $context = $this->resolveContext($uuid, $token, true);
        Pricing::clear();
        Pricing::addBranch($context['branch']);
        Pricing::addOrderType(OrderType::DineIn);

        $this->logEvent(
            table: $context['table'],
            eventType: TableQrEventType::ContextOpened,
            request: $request,
            throttle: true
        );

        return [
            ...$this->baseContextPayload($context),
            ...PricingCatalog::branch($context['branch']->id, $context['branch']->currency),
            'cart' => Pricing::instance()->toArray(),
            'categories' => PosCategoryResource::collection($this->publicCategories($context['menu']))->resolve(),
            'products' => PosProductResource::collection($this->publicProducts($context['menu']))->resolve(),
        ];
    }

    /**
     * Resolve table context.
     *
     * @return array<string, mixed>
     *
     * @throws TableQrOrderException
     */
    private function resolveContext(string $uuid, ?string $token, bool $includeSessionStatus = false, bool $validateSaleState = true): array
    {
        if ($validateSaleState && ! setting('table_qr_ordering_enabled', false)) {
            throw $this->exception('qr_ordering_disabled', Response::HTTP_FORBIDDEN);
        }

        $table = Table::query()
            ->withOutGlobalBranchPermission()
            ->withoutGlobalActive()
            ->with(['floor', 'zone'])
            ->where('uuid', $uuid)
            ->first();

        if (is_null($table) || blank($token) || blank($table->table_qr_token) || ! hash_equals($table->table_qr_token, (string) $token)) {
            throw $this->exception('qr_token_invalid', Response::HTTP_NOT_FOUND);
        }

        $branch = Branch::query()
            ->withoutGlobalActive()
            ->find($table->branch_id);

        $menu = $branch ? Menu::getActiveMenu($branch->id) : null;

        $context = [
            'table' => $table,
            'branch' => $branch,
            'menu' => $menu,
            'session' => $includeSessionStatus && $branch ? $this->resolveOpenSession($branch) : null,
        ];

        if ($validateSaleState) {
            $this->assertTableCanBeUsed($context);
        }

        return $context;
    }

    /**
     * Build domain exception.
     */
    private function exception(string $reason, int $status): TableQrOrderException
    {
        return new TableQrOrderException(
            reason: $reason,
            message: __('seatingplan::messages.table_qr_customer_errors.'.$reason),
            statusCode: $status
        );
    }

    /**
     * Resolve open POS session for branch by configured strategy.
     */
    private function resolveOpenSession(Branch $branch): ?PosSession
    {
        $direction = setting('table_qr_register_session_strategy', 'latest_opened') === 'earliest_opened'
            ? 'asc'
            : 'desc';

        return PosSession::query()
            ->withOutGlobalBranchPermission()
            ->whereBranch($branch->id)
            ->where('status', PosSessionStatus::Open)
            ->whereHas('posRegister', fn (Builder $query) => $query->where('is_active', true))
            ->orderBy('opened_at', $direction)
            ->first();
    }

    /**
     * Validate table context.
     *
     * @param  array<string, mixed>  $context
     *
     * @throws TableQrOrderException
     */
    private function assertTableCanBeUsed(array $context): void
    {
        /** @var Table $table */
        $table = $context['table'];
        /** @var Branch|null $branch */
        $branch = $context['branch'];
        /** @var Menu|null $menu */
        $menu = $context['menu'];

        if (! $table->is_active || is_null($branch) || ! $branch->is_active || ! $table->floor?->is_active || ! $table->zone?->is_active) {
            throw $this->exception('table_unavailable', Response::HTTP_CONFLICT);
        }

        if (! is_null($table->current_merge_id) || $table->status === TableStatus::Merged) {
            throw $this->exception('table_merged', Response::HTTP_CONFLICT);
        }

        if (! in_array($table->status, [TableStatus::Available, TableStatus::Occupied])) {
            throw $this->exception('table_unavailable', Response::HTTP_CONFLICT);
        }

        if (! in_array(OrderType::DineIn->value, $branch->order_types ?: [])) {
            throw $this->exception('dine_in_not_supported', Response::HTTP_CONFLICT);
        }

        if (is_null($menu)) {
            throw $this->exception('no_active_menu', Response::HTTP_CONFLICT);
        }

        if ($table->status === TableStatus::Occupied) {
            $activeOrdersCount = $this->activeUnpaidDineInOrders($table)->count();

            if ($activeOrdersCount === 0) {
                throw $this->exception('occupied_table_without_active_order', Response::HTTP_CONFLICT);
            }

            if ($activeOrdersCount > 1) {
                throw $this->exception('multiple_active_orders', Response::HTTP_CONFLICT);
            }
        }
    }

    /**
     * Get active unpaid dine-in orders for a table.
     */
    private function activeUnpaidDineInOrders(Table $table): Builder
    {
        return Order::query()
            ->withOutGlobalBranchPermission()
            ->where('table_id', $table->id)
            ->where('type', OrderType::DineIn->value)
            ->whereNot('payment_status', OrderPaymentStatus::Paid->value)
            ->whereIn('status', [
                OrderStatus::Pending->value,
                OrderStatus::Confirmed->value,
                OrderStatus::Preparing->value,
                OrderStatus::Ready->value,
                OrderStatus::Served->value,
            ])
            ->whereNotIn('status', [
                OrderStatus::Cancelled->value,
                OrderStatus::Refunded->value,
                OrderStatus::Merged->value,
            ]);
    }

    /**
     * Persist QR event with optional context-open throttling.
     */
    private function logEvent(
        Table $table,
        TableQrEventType $eventType,
        Request $request,
        ?string $cartUuid = null,
        ?int $orderId = null,
        bool $throttle = false,
    ): void {
        if ($throttle) {
            $key = 'table_qr_context_opened:'.sha1(implode('|', [
                $request->ip(),
                $table->id,
                $cartUuid,
            ]));

            if (! Cache::add($key, true, now()->addMinutes(10))) {
                return;
            }
        }

        TableQrEvent::query()->create([
            'table_id' => $table->id,
            'event_type' => $eventType,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'cart_uuid' => $cartUuid,
            'order_id' => $orderId,
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Get base public context payload.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function baseContextPayload(array $context): array
    {
        return [
            'settings' => [
                'table_qr_auto_send_to_kitchen' => $this->autoSendToKitchen(),
                'table_qr_require_guest_count' => (bool) setting('table_qr_require_guest_count', false),
                'table_qr_allow_order_notes' => (bool) setting('table_qr_allow_order_notes', true),
                'table_qr_allow_discount_code' => (bool) setting('table_qr_allow_discount_code', false),
                'table_qr_cart_expires_minutes' => $this->cartExpiresMinutes(),
            ],
            'table' => [
                'uuid' => $context['table']->uuid,
                'name' => $context['table']->name,
                'capacity' => $context['table']->capacity,
                'status' => $context['table']->status->toTrans(),
            ],
            'branch' => [
                'id' => $context['branch']->id,
                'name' => $context['branch']->name,
                'currency' => $context['branch']->currency,
            ],
            'menu' => [
                'id' => $context['menu']->id,
                'name' => $context['menu']->name,
            ],
            'active_order' => $this->activeOrderTrackingPayload($context['table']),
            'session' => $this->sessionPayload($context['session']),
        ];
    }

    /**
     * Determine auto-send behavior.
     */
    private function autoSendToKitchen(): bool
    {
        return (bool) setting('table_qr_auto_send_to_kitchen', true);
    }

    /**
     * Get QR cart expiry minutes.
     */
    private function cartExpiresMinutes(): int
    {
        return max(5, min((int) setting('table_qr_cart_expires_minutes', 120), 1440));
    }

    /**
     * Get customer-safe tracking payload for the current active table order.
     *
     * @return array<string, mixed>|null
     */
    private function activeOrderTrackingPayload(Table $table): ?array
    {
        $order = $this->activeUnpaidDineInOrders($table)
            ->with([
                'products' => fn ($query) => $query
                    ->without('taxes')
                    ->with(['product', 'options.values']),
            ])
            ->latest('updated_at')
            ->first();

        if (is_null($order)) {
            return null;
        }

        $items = $order->products
            ->map(fn ($product) => [
                'id' => $product->id,
                'name' => $product->name,
                'quantity' => $product->quantity,
                'status' => $product->status->toTrans(),
                'total' => $product->total,
                'options' => $this->orderProductOptionsPayload($product),
            ])
            ->values();

        $progress = $this->activeOrderProgressPayload($items);

        return [
            'id' => $order->id,
            'reference_no' => $order->reference_no,
            'order_number' => $order->order_number,
            'status' => $order->status->toTrans(),
            'source' => $order->source?->toTrans(),
            'guest_count' => $order->guest_count,
            'total' => $order->total,
            'items_count' => $items->sum('quantity'),
            'items' => $items,
            'progress' => $progress,
            'progress_steps' => $this->activeOrderProgressStepsPayload($progress),
            'created_at' => $order->created_at?->toIso8601String(),
            'updated_at' => $order->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Get selected option labels for a tracked order product.
     *
     * @return array<int, array<string, mixed>>
     */
    private function orderProductOptionsPayload(OrderProduct $product): array
    {
        return $product->options
            ->map(fn ($option) => [
                'id' => $option->id,
                'name' => $option->name,
                'values' => $option->values
                    ->map(fn ($value) => [
                        'id' => $value->id,
                        'label' => $option->value ?: $value->label,
                    ])
                    ->filter(fn ($value) => filled($value['label']))
                    ->values()
                    ->all(),
            ])
            ->filter(fn ($option) => ! empty($option['values']))
            ->values()
            ->all();
    }

    /**
     * Get item counts grouped by order-product status.
     *
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array<string, int>
     */
    private function activeOrderProgressPayload(Collection $items): array
    {
        return [
            'pending' => $items->where('status.id', OrderProductStatus::Pending->value)->sum('quantity'),
            'preparing' => $items->where('status.id', OrderProductStatus::Preparing->value)->sum('quantity'),
            'ready' => $items->where('status.id', OrderProductStatus::Ready->value)->sum('quantity'),
            'served' => $items->where('status.id', OrderProductStatus::Served->value)->sum('quantity'),
        ];
    }

    /**
     * Get status-count rows with enum metadata for the customer tracker.
     *
     * @param  array<string, int>  $progress
     * @return array<int, array<string, mixed>>
     */
    private function activeOrderProgressStepsPayload(array $progress): array
    {
        return collect([
            OrderProductStatus::Pending,
            OrderProductStatus::Preparing,
            OrderProductStatus::Ready,
            OrderProductStatus::Served,
        ])
            ->map(fn (OrderProductStatus $status) => [
                'key' => $status->value,
                'status' => $status->toTrans(),
                'value' => $progress[$status->value] ?? 0,
            ])
            ->values()
            ->all();
    }

    /**
     * Get customer-safe POS session availability.
     *
     * @return array{available: bool, reason: string|null, message: string|null}
     */
    private function sessionPayload(?PosSession $session): array
    {
        return [
            'available' => ! is_null($session),
            'reason' => is_null($session) ? 'no_open_pos_session' : null,
            'message' => is_null($session)
                ? __('seatingplan::messages.table_qr_no_open_session_customer')
                : null,
        ];
    }

    /**
     * Get public QR categories for the selected table branch active menu.
     *
     * @return Collection<int, Category>
     */
    private function publicCategories(Menu $menu): Collection
    {
        return Category::query()
            ->select(['id', 'name', 'parent_id', 'order', 'menu_id'])
            ->with(['childrenRecursive', 'files'])
            ->whereNull('parent_id')
            ->whereMenu($menu->id)
            ->withOutGlobalBranchPermission()
            ->orderBy('order')
            ->get();
    }

    /**
     * Get public QR products for the selected table branch active menu.
     *
     * @return Collection<int, Product>
     */
    private function publicProducts(Menu $menu): Collection
    {
        $activeCategoryIds = Category::getFullyActiveCategoryIds($menu->id);

        if ($activeCategoryIds->isEmpty()) {
            return collect();
        }

        return Product::query()
            ->with([
                'taxes',
                'files',
                'categories:id,menu_id',
                'options' => fn ($query) => $query->with('values'),
            ])
            ->whereMenu($menu->id)
            ->withOutGlobalBranchPermission()
            ->whereHas(
                'categories',
                fn (Builder $query) => $query->whereIn('id', $activeCategoryIds)
            )
            ->latest()
            ->get();
    }

    /** {@inheritDoc} */
    public function tracking(string $uuid, ?string $token): array
    {
        $context = $this->resolveContext($uuid, $token, true);

        return [
            'table' => [
                'status' => $context['table']->status->toTrans(),
            ],
            'active_order' => $this->activeOrderTrackingPayload($context['table']),
            'session' => $this->sessionPayload($context['session']),
        ];
    }

    /**
     * Price the guest selections using the table and branch resolved from the QR context.
     *
     * @throws TableQrOrderException
     * @throws JsonException
     */
    public function quote(string $uuid, ?string $token, array $data): array
    {
        $context = $this->resolveContext($uuid, $token, true);
        $checkout = $this->prepareCheckout($context, $data);

        return [
            ...app(CheckoutConfirmation::class)->quote($data, $checkout['pricing'], $this->checkoutScope($uuid, $token)),
            'comparison_pricing' => $checkout['submitted_pricing'],
        ];
    }

    /**
     * Revalidate sale availability and price the complete order, including existing items.
     *
     * The submitted selection is retained separately so the browser can compare like-for-like
     * prices when this request appends to an active table order.
     *
     * @return array{pricing: array<string, mixed>, submitted_pricing: array<string, mixed>}
     *
     * @throws TableQrOrderException|JsonException
     */
    private function prepareCheckout(array $context, array $data, bool $locking = false): array
    {
        $this->validateSubmitData($context['table'], $data);
        if (! $context['session']) {
            throw $this->exception('no_open_pos_session', 409);
        }
        if ($locking) {
            $session = PosSession::withOutGlobalBranchPermission()->whereKey($context['session']->id)->lockForUpdate()->firstOrFail();
            if ($session->status !== PosSessionStatus::Open) {
                throw $this->exception('no_open_pos_session', 409);
            }
        }
        if (! empty($data['voucher_code'])) {
            $this->assertDiscountCodeAllowed();
        }
        $submittedPricing = app(CheckoutPricing::class)->calculate([
            ...$data,
            'branch_id' => $context['branch']->id,
            'menu_id' => $context['menu']->id,
            'type' => OrderType::DineIn->value,
        ], public: true)->toArray();
        if ($context['table']->status === TableStatus::Occupied) {
            $order = $this->activeUnpaidDineInOrders($context['table'])->when($locking, fn ($q) => $q->lockForUpdate())->firstOrFail();
            Pricing::initOrder($order->load(['branch', 'products.product', 'products.options.values', 'products.gift', 'customer', 'discount.discountable', 'discount.gift', 'charges', 'taxes']));
            // These selections were validated above; preserve their public line IDs and option inputs.
            foreach ($data['items'] as $item) {
                Pricing::store($item['product_id'], $item['qty'], $item['options'], lineKey: $item['line_id']);
            }
            if (! empty($data['voucher_code'])) {
                Pricing::removeDiscount();
                $this->discountApplyService->applyVoucher(Pricing::class, $data['voucher_code']);
            }
        }

        return [
            'pricing' => json_decode(json_encode(Pricing::instance()->toArray(), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR),
            'submitted_pricing' => $submittedPricing,
        ];
    }

    /**
     * Validate QR submit fields that depend on the table/settings context.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws TableQrOrderException
     */
    private function validateSubmitData(Table $table, array $data): void
    {
        if (setting('table_qr_require_guest_count', false) && blank($data['guest_count'] ?? null)) {
            throw $this->exception('guest_count_required', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (filled($data['guest_count'] ?? null) && (int) $data['guest_count'] > $this->guestCountLimit($table)) {
            throw $this->exception('guest_count_invalid', Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Get the maximum allowed QR guest count for a table.
     */
    private function guestCountLimit(Table $table): int
    {
        return max(1, min($table->capacity, 50));
    }

    /**
     * Ensure public QR discount-code entry is enabled.
     *
     * @throws TableQrOrderException
     */
    private function assertDiscountCodeAllowed(): void
    {
        if (! setting('table_qr_allow_discount_code', false)) {
            throw $this->exception('discount_code_disabled', Response::HTTP_FORBIDDEN);
        }
    }

    /**
     * Bind the submission identity to the table and its current QR access credential.
     */
    private function checkoutScope(string $uuid, ?string $token): string
    {
        return 'table_order:'.$uuid.':'.hash('sha256', (string) $token);
    }

    /**
     * Replay a committed attempt or atomically create/append to the locked table order.
     */
    public function submit(string $uuid, ?string $token, array $data, Request $request): array
    {
        $context = $this->resolveContext($uuid, $token, true, false);
        $confirmation = app(CheckoutConfirmation::class);

        return $confirmation->once($this->checkoutScope($uuid, $token), $data, function () use ($uuid, $token, $data, $context, $confirmation, $request) {
            Table::withOutGlobalBranchPermission()->whereKey($context['table']->id)->lockForUpdate()->firstOrFail();
            if (! empty($data['voucher_code'])) {
                Voucher::where('code', $data['voucher_code'])->lockForUpdate()->first();
            }
            $context = $this->resolveContext($uuid, $token, true);
            $this->logEvent($context['table'], TableQrEventType::SubmitAttempt, $request);
            $checkout = $this->prepareCheckout($context, $data, true);
            $confirmation->confirm($data, $checkout['pricing'], $this->checkoutScope($uuid, $token));
            $order = $this->saveCheckoutOrder($context, $data);

            $this->logEvent($context['table'], TableQrEventType::SubmitSuccess, $request, orderId: $order->id);

            return [
                'id' => $order->id,
                'order' => [
                    'id' => $order->id,
                    'reference_no' => $order->reference_no,
                    'order_number' => $order->order_number,
                    'status' => $order->status->toTrans(),
                    'source' => $order->source?->toTrans(),
                ],
            ];
        });
    }

    /**
     * Save the already priced selections to the table's new or active unpaid order.
     */
    private function saveCheckoutOrder(array $context, array $data): Order
    {
        if ($context['table']->status === TableStatus::Available) {
            return $this->createOrder($context, $context['session'], $data);
        }

        $order = $this->activeUnpaidDineInOrders($context['table'])->firstOrFail();

        return $this->saveOrderService->updateFromSource($order->id, [
            'table_id' => $context['table']->id,
            'register_id' => $context['session']->pos_register_id,
            'session_id' => $context['session']->id,
            'guest_count' => $this->guestCount($context['table'], $data),
            'notes' => $this->mergedNote($order->notes, $this->customerNote($data)),
            'source' => OrderSource::TableQr->value,
        ]);
    }

    /**
     * Create new QR order.
     *
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $data
     *
     * @throws Throwable
     */
    private function createOrder(array $context, PosSession $session, array $data): Order
    {
        if ($this->activeUnpaidDineInOrders($context['table'])->exists()) {
            throw $this->exception('table_unavailable', Response::HTTP_CONFLICT);
        }

        return $this->saveOrderService->createFromSource([
            'branch_id' => $context['branch']->id,
            'menu_id' => $context['menu']->id,
            'type' => OrderType::DineIn->value,
            'table_id' => $context['table']->id,
            'register_id' => $session->pos_register_id,
            'session_id' => $session->id,
            'submit_action' => $this->autoSendToKitchen()
                ? PosSubmitAction::SendToKitchen->value
                : PosSubmitAction::HoldOrder->value,
            'guest_count' => $this->guestCount($context['table'], $data),
            'notes' => $this->customerNote($data),
            'source' => OrderSource::TableQr->value,
        ]);
    }

    /**
     * Get validated guest count.
     *
     * @param  array<string, mixed>  $data
     */
    private function guestCount(Table $table, array $data, ?int $fallback = null): int
    {
        $guestCount = (int) ($data['guest_count'] ?? ($fallback ?: 1));

        return min(max($guestCount, 1), $this->guestCountLimit($table));
    }

    /**
     * Get customer note if enabled.
     *
     * @param  array<string, mixed>  $data
     */
    private function customerNote(array $data): ?string
    {
        if (! setting('table_qr_allow_order_notes', true)) {
            return null;
        }

        return filled($data['customer_note'] ?? null) ? (string) $data['customer_note'] : null;
    }

    /**
     * Merge existing staff/POS note with new QR customer note.
     */
    private function mergedNote(?string $existing, ?string $note): ?string
    {
        if (blank($note)) {
            return $existing;
        }

        return trim(($existing ? "$existing\n" : '').__('seatingplan::messages.table_qr_note_prefix').' '.$note);
    }
}
