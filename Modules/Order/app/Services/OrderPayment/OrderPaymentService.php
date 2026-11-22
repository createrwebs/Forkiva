<?php

namespace Modules\Order\Services\OrderPayment;

use DB;
use Illuminate\Support\Collection;
use Modules\Currency\Currency;
use Modules\GiftCard\Enums\GiftCardTransactionType;
use Modules\GiftCard\Services\GiftCard\GiftCardServiceInterface;
use Modules\GiftCard\Services\GiftCardTransaction\GiftCardTransactionServiceInterface;
use Modules\Order\Enums\OrderPaymentStatus;
use Modules\Order\Enums\OrderProductStatus;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\OrderType;
use Modules\Order\Events\OrderMergeBillingPaid;
use Modules\Order\Events\OrderUpdateStatus;
use Modules\Order\Models\Order;
use Modules\Order\Services\Order\OrderServiceInterface;
use Modules\Order\Support\OrderWriteLock;
use Modules\Payment\Enums\PaymentMethod;
use Modules\Payment\Enums\PaymentMode;
use Modules\Payment\Models\Payment;
use Modules\Payment\Support\PromptPayPayload;
use Modules\Pos\Models\PosSession;
use Modules\SeatingPlan\Enums\TableStatus;
use Modules\SeatingPlan\Models\Table;
use Modules\SeatingPlan\Models\TableMerge;
use Modules\Support\Money;
use Throwable;

class OrderPaymentService implements OrderPaymentServiceInterface
{
    /** {@inheritDoc} */
    public function storePayment(int|string $id, array $data): void
    {
        OrderWriteLock::run($id, fn () => $this->storePaymentLocked($id, $data));
    }

    private function storePaymentLocked(int|string $id, array $data): void
    {
        $order = app(OrderServiceInterface::class)->findOrFail($id);

        if (! is_null($order->table_merge_id)) {
            $orders = app(OrderServiceInterface::class)
                ->getModel()
                ->query()
                ->activeOrders()
                ->with('table')
                ->where('table_merge_id', $order->table_merge_id)
                ->get();

            $this->storePaymentMerge($orders, $data);

            return;
        }

        if ($order->table_id) {
            abort_if($order->next_status !== OrderStatus::Completed || ! $order->allowAddPayment(), 400, __('order::messages.order_payment_not_allowed'));
        } else {
            abort_unless($order->allowAddPayment(), 400, __('order::messages.order_payment_not_allowed'));
        }

        $user = auth()->user();

        $scale = Currency::subunit($order->currency);
        $factor = 10 ** $scale;

        $dueRounded = round($order->due_amount->amount(), $scale);
        $dueMinor = (int) round($dueRounded * $factor);

        $paymentMinorTotal = 0;
        foreach ($data['payments'] as $p) {
            $paymentMinorTotal += (int) round(($p['amount'] ?? 0) * $factor);
        }

        $dueAmount = $dueMinor / $factor;
        $amountToBePaid = $paymentMinorTotal / $factor;

        abort_if(
            $amountToBePaid > $dueAmount,
            400,
            __('order::messages.payment_overpaid', [
                'paid' => number_format($amountToBePaid, $scale),
                'total' => number_format($dueAmount, $scale),
            ])
        );

        abort_if(
            $data['payment_mode'] === PaymentMode::Full->value && $amountToBePaid < $dueAmount,
            400,
            __('order::messages.insufficient_payment', [
                'paid' => number_format($amountToBePaid, $scale),
                'total' => number_format($dueAmount, $scale),
            ])
        );

        $isOrderComplete = ($paymentMinorTotal === $dueMinor) && $order->next_status === OrderStatus::Completed;

        $session = PosSession::query()->findOrFail($data['session_id']);

        $payments = [];
        foreach ($data['payments'] as $payment) {
            $minor = (int) round($payment['amount'] * $factor);
            $meta = null;

            if ($payment['method'] === PaymentMethod::GiftCard->value) {
                $giftCard = app(GiftCardServiceInterface::class)->findOrFail($payment['gift_card_code']);
                $giftCardAmount = app(GiftCardServiceInterface::class)->convertOrderAmountToGiftCardAmount(
                    giftCard: $giftCard,
                    amount: (float) $payment['amount'],
                    orderCurrency: $order->currency,
                    orderCurrencyRate: $payment['currency_rate'] ?? $order->currency_rate,
                );
                $meta = [
                    'gift_card_code' => $giftCard->code,
                    'gift_card_id' => $giftCard->id,
                ];
            }

            $payments[] = [
                'cashier_id' => $user->id,
                'method' => $payment['method'],
                'amount' => $minor / $factor,
                'transaction_id' => $payment['transaction_id'] ?? null,
                'session' => $session,
                'meta' => $meta,
            ];
        }

        DB::transaction(function () use ($order, $payments, $isOrderComplete, $user, $session) {
            $storedPayments = $order->storePayments($payments);
            $this->storeGiftCardRedemptionsForPayments($order, $storedPayments);
            $updateData = [
                'closed_at' => $isOrderComplete && $order->type === OrderType::DineIn ? now() : null,
                'cashier_id' => $user->id,
                'pos_session_id' => $order->pos_session_id ?: $session->id,
            ];

            $newStatus = null;
            if ($isOrderComplete) {
                $newStatus = OrderStatus::Completed;
            } elseif ($order->status === OrderStatus::Pending && $order->isScheduledForToday()) {
                $newStatus = OrderStatus::Confirmed;
            }

            if (! is_null($newStatus)) {
                $updateData['status'] = $newStatus;
            }

            $order->update($updateData);

            if (! is_null($newStatus)) {
                event(
                    new OrderUpdateStatus(
                        order: $order,
                        status: $newStatus,
                        changedById: $user->id,
                    )
                );
            }
        });
    }

    /**
     * Store payment for merge orders
     *
     * @throws Throwable
     */
    protected function storePaymentMerge(Collection $orders, array $data): void
    {
        abort_if($orders->count() === 0, 400, __('order::messages.order_payment_not_allowed'));

        $user = auth()->user();

        $scale = Currency::subunit($orders[0]->currency);
        $factor = 10 ** $scale;

        $orderDueMinor = [];
        $totalDueMinor = 0;

        /** @var Order $order */
        foreach ($orders as $order) {
            abort_if($order->next_status !== OrderStatus::Completed || ! $order->allowAddPayment(), 400, __('order::messages.order_payment_not_allowed'));
            $dueRounded = $order->due_amount->round($scale)->amount();
            $minor = (int) round($dueRounded * $factor);
            $orderDueMinor[$order->id] = $minor;
            $totalDueMinor += $minor;
        }

        abort_if($totalDueMinor <= 0, 400, __('order::messages.order_payment_not_allowed'));

        $amountToBePaid = 0;
        foreach ($data['payments'] as $p) {
            $amountToBePaid += ($p['amount'] ?? 0);
        }
        $amountToBePaid = round($amountToBePaid, $scale);

        $dueAmount = $totalDueMinor / $factor;

        abort_if(
            $amountToBePaid > $dueAmount,
            400,
            __('order::messages.payment_overpaid', [
                'paid' => number_format($amountToBePaid, 2),
                'total' => number_format($dueAmount, 2),
            ])
        );

        abort_if(
            $data['payment_mode'] === PaymentMode::Full->value && $amountToBePaid < $dueAmount,
            400,
            __('order::messages.insufficient_payment', [
                'paid' => number_format($amountToBePaid, 2),
                'total' => number_format($dueAmount, 2),
            ])
        );

        $session = PosSession::query()->findOrFail($data['session_id']);

        $ordersIds = $orders->pluck('id')->values()->all();
        $lastOrderId = end($ordersIds);

        $perOrderPayments = [];
        $orderPaidMinor = [];

        foreach ($orders as $order) {
            $perOrderPayments[$order->id] = [];
            $orderPaidMinor[$order->id] = 0;
        }

        foreach ($data['payments'] as $payment) {
            $paymentMinor = (int) round($payment['amount'] * $factor);
            $distributedMinor = 0;

            foreach ($orders as $order) {
                if ($order->id === $lastOrderId) {
                    $share = $paymentMinor - $distributedMinor;
                } else {
                    $share = intdiv($paymentMinor * $orderDueMinor[$order->id], $totalDueMinor);
                    $distributedMinor += $share;
                }

                if ($share < 0) {
                    $share = 0;
                }

                $amount = $share / $factor;

                $perOrderPayments[$order->id][] = [
                    'cashier_id' => $user->id,
                    'method' => $payment['method'],
                    'amount' => $amount,
                    'session' => $session,
                    'transaction_id' => $payment['transaction_id'] ?? null,
                    'meta' => $payment['method'] === PaymentMethod::GiftCard->value
                        ? ['gift_card_code' => $payment['gift_card_code'] ?? null]
                        : null,
                ];

                $orderPaidMinor[$order->id] += $share;
            }
        }

        $merge = TableMerge::query()->findOrFail($orders->first()->table_merge_id);

        DB::transaction(function () use ($orders, $user, $merge, $session, $perOrderPayments, $orderPaidMinor, $orderDueMinor) {
            /** @var Order $order */
            foreach ($orders as $order) {
                $dueMinor = $orderDueMinor[$order->id];
                $paidMinor = $orderPaidMinor[$order->id];

                $isOrderComplete = ($dueMinor === $paidMinor) && $order->next_status === OrderStatus::Completed;

                $storedPayments = $order->storePayments($perOrderPayments[$order->id]);
                $this->storeGiftCardRedemptionsForPayments($order, $storedPayments);

                $order->update([
                    'status' => $isOrderComplete ? OrderStatus::Completed : $order->status,
                    'closed_at' => $isOrderComplete ? now() : null,
                    'cashier_id' => $user->id,
                    'pos_session_id' => $order->pos_session_id ?: $session->id,
                ]);

                if ($isOrderComplete) {
                    event(
                        new OrderUpdateStatus(
                            order: $order,
                            status: OrderStatus::Completed,
                            changedById: $user->id,
                        )
                    );
                }
            }

            if ($orders->filter(fn ($order) => $order->status !== OrderStatus::Completed)->isEmpty()) {
                $query = Table::query()->where('current_merge_id', $merge->id);
                $tables = $query->get();
                $query->update(['current_merge_id' => null]);
                $tables->each(fn (Table $table) => $table->updateStatusWithEvent(TableStatus::Cleaning));
                $merge->update(['closed_at' => now(), 'closed_by' => $user->id]);
                event(new OrderMergeBillingPaid($merge));
            }
        });
    }

    /**
     * @throws Throwable
     */
    protected function storeGiftCardRedemptionsForPayments(Order $order, array $payments): void
    {
        /** @var Payment $payment */
        foreach ($payments as $payment) {
            if ($payment->method !== PaymentMethod::GiftCard || empty($payment->meta['gift_card_code'])) {
                continue;
            }

            $giftCard = app(GiftCardServiceInterface::class)->findOrFail($payment->meta['gift_card_code']);
            $giftCardCurrencyRate = app(GiftCardServiceInterface::class)->currencyRateToDefault($giftCard->currency);
            $giftCardAmount = app(GiftCardServiceInterface::class)->convertOrderAmountToGiftCardAmount(
                giftCard: $giftCard,
                amount: (string) $payment->amount->amount(),
                orderCurrency: $order->currency,
                orderCurrencyRate: $payment->currency_rate ?: $order->currency_rate,
                giftCardCurrencyRate: $giftCardCurrencyRate,
            );

            app(GiftCardTransactionServiceInterface::class)
                ->record($giftCard, [
                    'type' => GiftCardTransactionType::Redeem,
                    'amount' => $giftCardAmount->amount(),
                    'amount_in_order_currency' => $payment->amount->amount(),
                    'order_currency' => $order->currency,
                    'exchange_rate' => $order->currency_rate,
                    'currency_rate' => $giftCardCurrencyRate,
                    'payment_id' => $payment->id,
                    'idempotency_key' => "gift_card:$giftCard->id:redeem:payment:$payment->id",
                    'economic_date' => $payment->received_at?->toDateString() ?? now()->toDateString(),
                    'order_id' => $order->id,
                    'branch_id' => $order->branch_id,
                    'notes' => __('order::messages.order_paid_successfully'),
                ]);
        }
    }

    /** {@inheritDoc} */
    public function getPaymentMeta(int|string $id): array
    {
        $query = app(OrderServiceInterface::class)->getModel()
            ->query()
            ->select('id', 'branch_id', 'table_merge_id', 'subtotal', 'total', 'currency', 'due_amount')
            ->withCount(['products' => fn ($q) => $q->whereNotIn('status', [OrderProductStatus::Cancelled, OrderProductStatus::Refunded])])
            ->whereNot('payment_status', OrderPaymentStatus::Paid)
            ->activeOrders()
            ->with(['branch', 'taxes', 'discount', 'charges']);

        /** @var Order $order */
        $order = (clone $query)->whereIdOrReference($id)
            ->firstOrFail();

        $orders = collect();
        if (! is_null($order->table_merge_id)) {
            $orders = (clone $query)
                ->where('table_merge_id', $order->table_merge_id)
                ->where('id', '!=', $order->id)
                ->get();
        }

        $orders->push($order);

        $productsCount = 0;
        $subtotal = new Money(0, $order->currency);
        $grandTotal = new Money(0, $order->currency);
        $totalTax = new Money(0, $order->currency);
        $totalDiscount = new Money(0, $order->currency);
        $chargeTotal = new Money(0, $order->currency);
        $dueAmount = new Money(0, $order->currency);
        $totalPaid = new Money(0, $order->currency);
        $charges = collect();

        /** @var Order $orderRow */
        foreach ($orders as $orderRow) {
            $productsCount += $orderRow->products_count;

            // A merged payment settles each order in currency minor units. Sum the
            // same rounded values here so the amount shown to the cashier is the
            // exact amount accepted by storePaymentMerge().
            $subtotal = $subtotal->add($orderRow->subtotal->round());
            $grandTotal = $grandTotal->add($orderRow->total->round());
            $totalTax = $totalTax->add($orderRow->totalTax());
            $chargeTotal = $chargeTotal->add($orderRow->charge_total);

            if (! is_null($orderRow->discount)) {
                $totalDiscount = $totalDiscount->add($orderRow->discount->amount);
            }

            $charges = $charges->merge($orderRow->charges->map(fn ($charge) => [
                'code' => $charge->code,
                'name' => $charge->name,
                'amount' => $charge->amount->round(),
            ]));
            $roundedDueAmount = $orderRow->due_amount->round();
            $dueAmount = $dueAmount->add($roundedDueAmount);

            $totalPaid = $totalPaid->add($orderRow->total->round()->subtract($roundedDueAmount));
        }

        return [
            'payment_methods' => array_filter(
                PaymentMethod::toArrayTrans(),
                fn ($orderType) => in_array($orderType['id'], $order->branch->payment_methods ?: [])
            ),
            'payment_modes' => PaymentMode::toArrayTrans(),
            'quick_pay_amounts' => $this->resolveQuickPayAmounts($order->branch->quick_pay_amounts),
            'order' => [
                'total_products' => $productsCount,
                'sub_total' => $subtotal->round(),
                'grand_total' => $grandTotal->round(),
                'total_tax' => $totalTax->round(),
                'discount' => $totalDiscount->round(),
                'charge_total' => $chargeTotal->round(),
                'charges' => $charges->values(),
                'due_amount' => $dueAmount->round(),
                'total_paid' => $totalPaid->round(),
                'currency' => $order->currency,
                'precision' => Currency::subunit($order->currency),
            ],
        ];
    }

    /** {@inheritDoc} */
    public function getPromptPayQr(int|string $id, ?string $amount = null): array
    {
        $meta = $this->getPaymentMeta($id);

        /** @var Order $order */
        $order = app(OrderServiceInterface::class)->getModel()
            ->query()
            ->select('id', 'branch_id', 'currency')
            ->with('branch:id,promptpay_id')
            ->whereIdOrReference($id)
            ->firstOrFail();

        abort_if($order->currency !== 'THB', 400, __('order::messages.promptpay_currency_unsupported'));
        abort_if(blank($order->branch?->promptpay_id), 400, __('order::messages.promptpay_not_configured'));

        // Compare in integer satang so the QR never exceeds the collectible due.
        $dueMinor = (int) round($meta['order']['due_amount']->amount() * 100);
        $requestedMinor = $amount === null ? $dueMinor : (int) round(((float) $amount) * 100);

        abort_if(
            ! is_numeric($amount ?? '0') || $requestedMinor <= 0 || $requestedMinor > $dueMinor,
            400,
            __('order::messages.promptpay_amount_invalid')
        );

        $qrAmount = number_format($requestedMinor / 100, 2, '.', '');

        return [
            'payload' => PromptPayPayload::generate($order->branch->promptpay_id, $qrAmount),
            'amount' => new Money((float) $qrAmount, 'THB'),
            'currency' => 'THB',
            'order_id' => $order->id,
        ];
    }

    /**
     * Resolve quick pay button values, with a fallback default and max 6 values.
     */
    private function resolveQuickPayAmounts(?array $values): array
    {
        $amounts = collect($values ?? [])
            ->map(fn ($value) => is_numeric($value) ? (float) $value : null)
            ->filter(fn ($value) => ! is_null($value) && $value > 0)
            ->unique()
            ->take(6)
            ->values()
            ->all();

        if (count($amounts) === 0) {
            return [10, 15, 20, 30, 50, 100];
        }

        return $amounts;
    }
}
