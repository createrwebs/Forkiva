<?php

namespace Modules\Order\Services\SaveOrder;

use DB;
use Illuminate\Support\Collection;
use Modules\Branch\Models\Branch;
use Modules\Core\Contracts\DeliveryFeatureInterface;
use Modules\Currency\Currency;
use Modules\Currency\Models\CurrencyRate;
use Modules\Order\Enums\OrderPaymentStatus;
use Modules\Order\Enums\OrderProductAction;
use Modules\Order\Enums\OrderProductStatus;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\OrderType;
use Modules\Order\Events\OrderCreated;
use Modules\Order\Events\OrderUpdated;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderProduct;
use Modules\Order\Pricing\Pricing;
use Modules\Order\Pricing\PricingItem;
use Modules\Pos\Enums\PosSubmitAction;
use Modules\Product\Models\Product;
use Modules\SeatingPlan\Enums\TableMergeType;
use Modules\SeatingPlan\Enums\TableStatus;
use Modules\SeatingPlan\Models\Table;
use Modules\User\Enums\DefaultRole;
use Modules\User\Models\User;
use Throwable;

class SaveOrderService implements SaveOrderServiceInterface
{
    public function __construct(private Pricing $pricing) {}

    /** {@inheritDoc} */
    public function createFromSource(array $data): Order
    {
        return $this->createUsingActor($data);
    }

    /**
     * Store a new order with an optional actor.
     *
     * @throws Throwable
     */
    private function createUsingActor(array $data, ?User $user = null): Order
    {
        /** @var Branch $branch */
        $branch = Branch::withTrashed()->find($data['branch_id']);

        $data['currency_rate'] = CurrencyRate::for($branch->currency);
        if (! isset($data['type'])) {
            $data['type'] = $this->pricing->orderType()->value();
        }

        return DB::transaction(function () use ($data, $user, $branch) {
            $order = $this->createOrder($branch, $user, $data);
            $this->storeStatusLogs($order);
            $this->updateTableStatus($order);
            $this->updateOrCreateProducts($order);
            $order->updateOrCreateTaxes($this->pricing->taxes());
            $order->updateOrCreateCharges($this->pricing->charges());
            $order->UpdateOrCreateDiscount($this->pricing->discount());
            app(DeliveryFeatureInterface::class)->syncOrderFromPricing($order);

            event(new OrderCreated($order));

            return $order;
        });
    }

    /**
     * Create Order
     */
    private function createOrder(Branch $branch, ?User $user, array $data): Order
    {
        $isDineIn = $data['type'] === OrderType::DineIn->value;
        $tableMergeId = null;

        $tableId = $isDineIn ? ($data['table_id'] ?? null) : null;
        $waiterId = $user?->hasRole(DefaultRole::Waiter->value) ? $user->id : ($data['waiter_id'] ?? null);

        if (! is_null($tableId)) {
            $table = Table::query()->with(['currentMerge', 'activeOrder'])->findOrFail($tableId);

            abort_unless(is_null($table->activeOrder), 400, __('order::messages.table_already_have_active_order'));

            if (is_null($waiterId) && ! is_null($table->assigned_waiter_id)) {
                $waiterId = $table->assigned_waiter_id;
            } elseif (! is_null($waiterId) && is_null($table->assigned_waiter_id)) {
                $table->assigned_waiter_id = $waiterId;
                $table->save();
            }

            if (! is_null($table->currentMerge) && $table->currentMerge->type === TableMergeType::Billing) {
                $tableMergeId = $table->currentMerge->id;
            }
        }

        return Order::query()
            ->create([
                'branch_id' => $branch->id,
                'table_id' => $tableId,
                'customer_id' => $this->pricing->customer()?->id(),
                'pos_register_id' => $data['register_id'],
                'pos_session_id' => $data['session_id'],
                'waiter_id' => $waiterId,
                'status' => $data['submit_action'] === PosSubmitAction::SendToKitchen->value
                    ? OrderStatus::Confirmed
                    : OrderStatus::Pending,
                'source' => $data['source'] ?? null,
                'type' => $data['type'],
                'payment_status' => OrderPaymentStatus::Unpaid,
                'currency' => $branch->currency,
                'currency_rate' => $data['currency_rate'],
                'subtotal' => $this->pricing->subTotal()->amount(),
                'total' => $this->pricing->total()->amount(),
                'due_amount' => $this->pricing->total()->amount(),
                ...($data['type'] === OrderType::DriveThru->value
                    ? [
                        'car_plate' => $data['car_plate'],
                        'car_description' => $data['car_description'],
                    ]
                    : []),
                'scheduled_at' => in_array($data['type'], [OrderType::PreOrder->value, OrderType::Catering->value])
                    ? ($data['scheduled_at'] ?? null)
                    : null,
                'guest_count' => $data['guest_count'] ?? 1,
                'notes' => $data['notes'] ?? null,
                'order_date' => now(),
                'served_at' => $isDineIn ? now() : null,
                'table_merge_id' => $tableMergeId,
            ]);
    }

    /** {@inheritDoc} */
    public function create(array $data): Order
    {
        return $this->createUsingActor($data, auth()->user());
    }

    /**
     * Store Order Status Logs
     */
    private function storeStatusLogs(Order $order): void
    {
        $statuses = [OrderStatus::Pending];

        if ($order->status !== OrderStatus::Pending) {
            $statuses[] = OrderStatus::Confirmed;
        }

        foreach ($statuses as $status) {
            $order->storeStatusLog(status: $status);
        }
    }

    /**
     * Update table status
     */
    private function updateTableStatus(Order $order): void
    {
        if ($order->type === OrderType::DineIn && ! is_null($order->table_id)) {
            if ($order->table->status !== TableStatus::Occupied) {
                $order->table->updateStatusWithEvent(
                    status: TableStatus::Occupied,
                    note: "ORDER_CREATED :$order->reference_no"
                );
            }
        }
    }

    /**
     * Update Or Create Products
     *
     * @throws Throwable
     */
    private function updateOrCreateProducts(Order $order, bool $isUpdate = false): void
    {
        $items = $this->pricing->items();
        if ($isUpdate) {
            $items = $this->parseProductActions($items, $order->products);
        }

        // Option snapshots need catalog prices; load them once instead of once per saved line.
        $productIds = $items
            ->filter(fn (PricingItem $item) => $item->options->isNotEmpty())
            ->map(fn (PricingItem $item) => $item->product->id)
            ->unique();
        $products = Product::query()
            ->withTrashed()
            ->withOutGlobalBranchPermission()
            ->withoutGlobalActive()
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        foreach ($items as $item) {
            $order->updateOrCreateProduct($item, $products->get($item->product->id));
        }
    }

    /**
     * Parse Product actions
     */
    private function parseProductActions(Collection $items, Collection $orderProducts): Collection
    {
        $data = collect();
        $orderProducts = $orderProducts->keyBy('id');
        $itemsMap = $items
            ->filter(fn ($item) => ! is_null($item->orderProduct))
            ->keyBy(fn ($item) => $item->orderProduct->id());

        $deletedProductIds = [];
        /** @var OrderProduct $orderProduct */
        foreach ($orderProducts as $orderProduct) {
            if ($orderProduct->status === OrderProductStatus::Pending && ! isset($itemsMap[$orderProduct->id])) {
                $deletedProductIds[] = $orderProduct->id;
            }
        }

        OrderProduct::query()->whereIn('id', $deletedProductIds)->delete();

        /** @var PricingItem $item */
        foreach ($items as $item) {
            if (! is_null($item->orderProduct) && isset($orderProducts[$item->orderProduct->id()])) {
                $originalProduct = $orderProducts[$item->orderProduct->id()];
                $remaining = $item->qty;
                if (! $item->actions) {
                    $data->push($item);

                    continue;
                }
                if ($remaining > 0) {
                    $data->push($item);
                }
                foreach ($item->actions as $action) {
                    abort_if(
                        ($action['id'] === OrderProductAction::Cancel->value && $originalProduct->status !== OrderProductStatus::Preparing)
                        || ($action['id'] === OrderProductAction::Refund->value && ! in_array($originalProduct->status, [OrderProductStatus::Ready, OrderProductStatus::Served])),
                        409, __('order::checkout.order_changed')
                    );
                    $changed = clone $item;
                    $changed->orderProduct = clone $item->orderProduct;
                    $changed->qty = $action['quantity'];
                    if ($remaining > 0) {
                        $changed->orderProduct->setId();
                    }
                    $changed->orderProduct->setStatus(OrderProductAction::from($action['id'])->getProductStatus());
                    $data->push($changed);
                }
            } else {
                $data->push($item);
            }
        }

        return $data;
    }

    /** {@inheritDoc} */
    public function update(int $id, array $data): Order
    {
        return $this->updateUsingActor($id, $data, auth()->user());
    }

    /**
     * Update order with an optional actor.
     *
     * @throws Throwable
     */
    private function updateUsingActor(int $id, array $data, ?User $user = null): Order
    {
        /** @var Order $order */
        $order = Order::query()->with(['branch', 'products'])->findOrFail($id);

        $scale = Currency::subunit($order->currency);

        $data['currency_rate'] = CurrencyRate::for($order->branch->currency);
        $data['type'] = $this->pricing->orderType()->value();
        $totalRound = $this->pricing->total()->round()->amount();

        $amountPaid = round($order->total->amount() - $order->due_amount->amount(), $scale);
        $data['overpaid_amount'] = 0;

        if ($amountPaid > $totalRound) {
            $data['overpaid_amount'] = round($amountPaid - $totalRound, $scale);
        }

        abort_if(
            $data['overpaid_amount'] > 0 && ! isset($data['refund_payment_method']),
            400,
            __('validation.required', ['attribute' => __('order::attributes.orders.refund_payment_method')])
        );

        abort_if($totalRound <= 0, 400, __('order::messages.order_must_contain_at_least_one_active_product'));

        return DB::transaction(function () use ($data, $order, $user) {
            $order = $this->updateOrder($order, $user, $data);

            $this->updateOrCreateProducts($order, true);

            $order->updateOrCreateTaxes($this->pricing->taxes());
            $order->updateOrCreateCharges($this->pricing->charges());
            $order->updateOrCreateDiscount($this->pricing->discount());
            app(DeliveryFeatureInterface::class)->syncOrderFromPricing($order);
            $order->revertOrderStatusToPreparingIfModified();
            $order->handleOverpaymentAdjustment($data);

            event(new OrderUpdated($order));

            return $order;
        });
    }

    /**
     * Update Order
     */
    private function updateOrder(Order $order, ?User $user, array $data): Order
    {
        $isDineIn = $data['type'] === OrderType::DineIn->value;
        $total = $this->pricing->total()->amount();
        $tableId = $isDineIn ? ($data['table_id'] ?? null) : null;
        $tableMergeId = $order->table_merge_id;
        $waiterId = $data['waiter_id']
            ?? ($user?->hasRole(DefaultRole::Waiter->value) && $isDineIn ? $user->id : $order->waiter_id);

        if (! is_null($order->table_id) && is_null($tableId)) {
            $this->handleRemoveTableFromOrder($order);
        } elseif ($order->table_id && (int) $order->table_id !== (int) $tableId) {
            $tableData = $this->handleChangeTableOrder($order, $tableId, $waiterId);
            $waiterId = $tableData['waiter_id'];
            $tableMergeId = $tableData['table_merge_id'];
        }

        $order->update([
            'customer_id' => $this->pricing->customer()?->id(),
            'table_id' => $tableId,
            'pos_register_id' => $data['register_id'],
            'pos_session_id' => $data['session_id'],
            'waiter_id' => $waiterId,
            'source' => $data['source'] ?? $order->source,
            'type' => $data['type'],
            'currency' => $order->branch->currency,
            'currency_rate' => $data['currency_rate'],
            'subtotal' => $this->pricing->subTotal()->amount(),
            'total' => $total,
            'due_amount' => max($total - ($order->total->amount() - $order->due_amount->amount()), 0),
            ...($data['type'] === OrderType::DriveThru->value
                ? [
                    'car_plate' => $data['car_plate'],
                    'car_description' => $data['car_description'],
                ]
                : []),
            'scheduled_at' => in_array($data['type'], [OrderType::PreOrder->value, OrderType::Catering->value])
                ? ($data['scheduled_at'] ?? null)
                : null,
            'guest_count' => $data['guest_count'] ?? 1,
            'notes' => $data['notes'] ?? null,
            'modified_at' => now(),
            'modified_by' => $user?->id ?? auth()->id(),
            'table_merge_id' => $tableMergeId,
        ]);

        return $order->fresh();
    }

    /**
     * Handle remove table from order
     */
    private function handleRemoveTableFromOrder(Order $order): void
    {
        $table = Table::query()
            ->with(['currentMerge'])
            ->findOrFail($order->table_id);

        abort_if(
            ! is_null($table->currentMerge) || ! is_null($order->table_merge_id),
            400,
            __('order::messages.table_cannot_be_changed_because_merge_request')
        );

        $table->updateStatusWithEvent(
            status: TableStatus::Available,
            note: "ORDER_REMOVE_TABLE :$order->reference_no"
        );
    }

    /**
     * Handle remove table from order
     */
    private function handleChangeTableOrder(Order $order, int $newTableId, ?int $waiterId): array
    {
        $table = Table::query()
            ->with(['currentMerge'])
            ->findOrFail($order->table_id);

        abort_if(
            ! is_null($table->currentMerge) || ! is_null($order->table_merge_id),
            400,
            __('order::messages.table_cannot_be_changed_because_merge_request')
        );

        $newTable = Table::query()
            ->with(['currentMerge', 'activeOrder'])
            ->findOrFail($newTableId);

        abort_unless(is_null($newTable->activeOrder), 400, __('order::messages.table_already_have_active_order'));

        $tableMergeId = null;

        if (is_null($waiterId) && ! is_null($newTable->assigned_waiter_id)) {
            $waiterId = $newTable->assigned_waiter_id;
        } elseif (! is_null($waiterId) && is_null($newTable->assigned_waiter_id)) {
            $newTable->assigned_waiter_id = $waiterId;
            $newTable->save();
        }

        if (! is_null($table->currentMerge) && $table->currentMerge->type === TableMergeType::Billing) {
            $tableMergeId = $table->currentMerge->id;
        }

        if ($newTable->status !== TableStatus::Occupied) {
            $newTable->updateStatusWithEvent(
                status: TableStatus::Occupied,
                note: "ORDER_CHANGE_TABLE :$order->reference_no:$table->id"
            );
        }

        $table->updateStatusWithEvent(
            status: TableStatus::Available,
            note: "ORDER_CHANGE_TABLE :$order->reference_no:$newTable->id"
        );

        return [
            'table_merge_id' => $tableMergeId,
            'waiter_id' => $waiterId,
        ];
    }

    /** {@inheritDoc} */
    public function updateFromSource(int $id, array $data): Order
    {
        return $this->updateUsingActor($id, $data);
    }
}
