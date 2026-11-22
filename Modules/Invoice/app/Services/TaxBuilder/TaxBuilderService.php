<?php

namespace Modules\Invoice\Services\TaxBuilder;

use Illuminate\Support\Collection;
use Modules\Invoice\Enums\InvoiceKind;
use Modules\Invoice\Models\Invoice;
use Modules\Invoice\Models\InvoiceLine;
use Modules\Invoice\Models\InvoiceTax;
use Modules\Invoice\Services\OrderLevelTaxAllocation\OrderLevelTaxAllocationServiceInterface;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderTax;

class TaxBuilderService implements TaxBuilderServiceInterface
{
    /**
     * Create a new tax builder service instance.
     */
    public function __construct(
        protected OrderLevelTaxAllocationServiceInterface $orderLevelTaxAllocator,
    ) {}

    /** {@inheritDoc} */
    public function createLineTaxes(Invoice $invoice, InvoiceLine $line, Collection $taxes): void
    {
        foreach ($taxes as $tax) {
            InvoiceTax::query()
                ->create([
                    'name' => $tax->getTranslations('name'),
                    'rate' => $tax->rate,
                    'currency' => $tax->currency,
                    'currency_rate' => $tax->currency_rate,
                    'amount' => $tax->amount->amount(),
                    'type' => $tax->type,
                    'compound' => $tax->compound,
                    'invoice_id' => $invoice->id,
                    'invoice_line_id' => $line->id,
                    'tax_id' => $tax->tax_id,
                    'code' => $tax->tax->code,
                ]);
        }
    }

    /** {@inheritDoc} */
    public function createInvoiceTaxes(Invoice $invoice, Collection $orders, ?Collection $orderProductIds = null, bool $fullOrderReversal = false): void
    {
        $grouped = collect();
        $allowedProductIds = $orderProductIds?->map(fn ($id) => (int) $id)->values();

        foreach ($orders as $order) {
            foreach ($order->taxes as $orderTax) {
                $amount = $this->resolveOrderLevelTaxAmount(
                    invoice: $invoice,
                    order: $order,
                    orderTax: $orderTax,
                    allowedProductIds: $allowedProductIds,
                    fullOrderReversal: $fullOrderReversal,
                );

                if ($amount <= 0) {
                    continue;
                }

                $key = $orderTax->tax_id.'-'.$orderTax->tax->code;

                $current = $grouped->get($key, [
                    'orderTax' => $orderTax,
                    'amount' => 0,
                ]);

                $current['amount'] += $amount;

                $grouped->put($key, $current);
            }
        }

        foreach ($grouped as $item) {
            $orderTax = $item['orderTax'];

            InvoiceTax::query()->create([
                'invoice_id' => $invoice->id,
                'invoice_line_id' => null,
                'tax_id' => $orderTax->tax_id,
                'code' => $orderTax->tax->code,
                'name' => $orderTax->getTranslations('name'),
                'rate' => $orderTax->rate,
                'currency' => $orderTax->currency,
                'currency_rate' => $orderTax->currency_rate,
                'amount' => $item['amount'],
                'type' => $orderTax->type,
                'compound' => $orderTax->compound,
            ]);
        }
    }

    /**
     * Resolve the refundable amount for an order-level tax row.
     *
     * @param  Collection<int,int>|null  $allowedProductIds
     */
    private function resolveOrderLevelTaxAmount(Invoice $invoice, Order $order, OrderTax $orderTax, ?Collection $allowedProductIds, bool $fullOrderReversal): float
    {
        if (is_null($allowedProductIds)) {
            return (float) $orderTax->amount->amount();
        }

        if ($fullOrderReversal) {
            return $this->resolveRemainingOrderLevelTaxAmount($invoice, $order, $orderTax);
        }

        return $this->orderLevelTaxAllocator->allocate(
            order: $order,
            reversedProductIds: $allowedProductIds,
            orderTax: $orderTax,
        );
    }

    /**
     * Resolve the remaining refundable amount for a full-order reversal after earlier partial credit notes.
     */
    private function resolveRemainingOrderLevelTaxAmount(Invoice $invoice, Order $order, OrderTax $orderTax): float
    {
        $alreadyCreditedAmount = 0.0;

        if (! is_null($invoice->reference_invoice_id)) {
            $alreadyCreditedAmount = (float) InvoiceTax::query()
                ->whereNull('invoice_line_id')
                ->where('tax_id', $orderTax->tax_id)
                ->where('code', $orderTax->tax->code)
                ->whereHas('invoice', function ($query) use ($invoice, $order) {
                    $query->where('invoice_kind', InvoiceKind::CreditNote)
                        ->where('reference_invoice_id', $invoice->reference_invoice_id)
                        ->where('order_id', $order->id)
                        ->whereKeyNot($invoice->id);
                })
                ->sum('amount');
        }

        return max(round($orderTax->amount->amount() - $alreadyCreditedAmount, 4), 0);
    }
}
