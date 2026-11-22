<?php

namespace Modules\Invoice\Services\DiscountBuilder;

use Illuminate\Support\Collection;
use Modules\Currency\Currency;
use Modules\Invoice\Enums\InvoiceKind;
use Modules\Invoice\Models\Invoice;
use Modules\Invoice\Models\InvoiceDiscount;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderDiscount;
use Modules\Voucher\Models\Voucher;

class DiscountBuilderService implements DiscountBuilderServiceInterface
{
    /** {@inheritDoc} */
    public function createDiscounts(
        Invoice $invoice,
        Collection $orders,
        ?Collection $orderProductIds = null,
        bool $fullOrderReversal = false,
    ): Collection {
        $grouped = collect();
        $discounts = collect();

        foreach ($orders as $order) {
            /** @var OrderDiscount $d */
            $d = $order->discount;
            if (is_null($d)) {
                continue;
            }

            $amount = $this->resolveAmount(
                $invoice,
                $order,
                $d,
                $orderProductIds,
                $fullOrderReversal,
            );

            if ($amount <= 0) {
                continue;
            }

            $key = $d->type->value.'-'.$d->discountable_type.'-'.$d->discountable_id;

            if (! $grouped->has($key)) {
                $grouped[$key] = (object) [
                    'discount' => $d,
                    'amount' => 0,
                ];
            }

            $grouped[$key]->amount += $amount;
        }

        foreach ($grouped as $item) {
            /** @var OrderDiscount $d */
            $d = $item->discount;

            $discounts->push(
                InvoiceDiscount::query()
                    ->create([
                        'invoice_id' => $invoice->id,
                        'discountable_type' => $d->discountable_type,
                        'discountable_id' => $d->discountable_id,
                        'source' => $d->type,
                        'name' => $d->discountable_type === Voucher::class
                            ? $d->discountable->code
                            : $d->discountable?->getTranslations('name'),
                        'currency' => $d->currency,
                        'currency_rate' => $d->currency_rate,
                        'amount' => $item->amount,
                    ])
            );
        }

        return $discounts;
    }

    /**
     * Resolve the discount amount represented by this invoice or credit note.
     *
     * @param  Collection<int,int>|null  $orderProductIds
     */
    private function resolveAmount(
        Invoice $invoice,
        Order $order,
        OrderDiscount $discount,
        ?Collection $orderProductIds,
        bool $fullOrderReversal,
    ): float {
        if (is_null($orderProductIds)) {
            return (float) $discount->amount->amount();
        }

        $remaining = max(
            round(
                $discount->amount->amount() - $this->alreadyCreditedAmount($invoice, $order, $discount),
                $discount->amount->precision(),
            ),
            0,
        );

        if ($fullOrderReversal) {
            return $remaining;
        }

        $reversedSubtotal = (float) $order->products()
            ->whereKey($orderProductIds->all())
            ->sum('subtotal');
        $orderSubtotal = (float) $order->products()->sum('subtotal');

        if ($reversedSubtotal <= 0 || $orderSubtotal <= 0) {
            return 0.0;
        }

        $allocated = round(
            ($reversedSubtotal / $orderSubtotal) * $discount->amount->amount(),
            Currency::subunit($discount->currency),
        );

        return min($allocated, $remaining);
    }

    /**
     * Sum prior discount credit for this order under the referenced invoice.
     */
    private function alreadyCreditedAmount(
        Invoice $invoice,
        Order $order,
        OrderDiscount $discount,
    ): float {
        if (is_null($invoice->reference_invoice_id)) {
            return 0.0;
        }

        return (float) InvoiceDiscount::query()
            ->where('source', $discount->type)
            ->where('discountable_type', $discount->discountable_type)
            ->where('discountable_id', $discount->discountable_id)
            ->whereHas('invoice', function ($query) use ($invoice, $order) {
                $query->where('invoice_kind', InvoiceKind::CreditNote)
                    ->where('reference_invoice_id', $invoice->reference_invoice_id)
                    ->where('order_id', $order->id)
                    ->whereKeyNot($invoice->id);
            })
            ->sum('amount');
    }
}
