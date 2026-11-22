<?php

namespace Modules\Invoice\Services\InvoiceLineBuilder;

use Illuminate\Support\Collection;
use Modules\Invoice\Models\Invoice;
use Modules\Invoice\Models\InvoiceLine;
use Modules\Invoice\Models\InvoiceTax;
use Modules\Invoice\Services\OrderChargeInvoicePolicy\OrderChargeInvoicePolicyResolverServiceInterface;
use Modules\Invoice\Services\TaxBuilder\TaxBuilderServiceInterface;
use Modules\Order\Models\OrderCharge;
use Modules\Order\Models\OrderProduct;
use Modules\Tax\Enums\TaxType;

class InvoiceLineBuilderService implements InvoiceLineBuilderServiceInterface
{
    /**
     * Create a new instance of InvoiceLineBuilderService
     */
    public function __construct(
        protected TaxBuilderServiceInterface $taxBuilder,
        protected OrderChargeInvoicePolicyResolverServiceInterface $chargePolicyResolver,
    ) {}

    /** {@inheritDoc} */
    public function createLines(Invoice $invoice, Collection $orders, bool $includeCharges = true): Collection
    {
        $lines = $this->createProductLines($invoice, $orders);

        if ($includeCharges) {
            $lines = $lines->merge($this->createChargeLines($invoice, $orders));
        }

        return $lines;
    }

    /** {@inheritDoc} */
    public function createProductLines(Invoice $invoice, Collection $orders, ?Collection $productIds = null): Collection
    {
        $lines = collect();
        $allowedProductIds = $productIds?->map(fn ($id) => (int) $id)->values();

        foreach ($orders as $order) {
            /** @var OrderProduct $op */
            foreach ($order->products as $op) {
                if (! is_null($allowedProductIds) && ! $allowedProductIds->contains((int) $op->id)) {
                    continue;
                }

                $line = InvoiceLine::query()
                    ->create([
                        'invoice_id' => $invoice->id,
                        'order_product_id' => $op->id,
                        'description' => $op->product->getTranslations('name'),
                        'sku' => $op->product->sku,
                        'currency' => $op->currency,
                        'currency_rate' => $op->currency_rate,
                        'unit_price' => $op->unit_price->amount(),
                        'quantity' => $op->quantity,
                        'tax_amount' => $op->tax_total->amount(),
                        'line_total_excl_tax' => $op->subtotal->amount(),
                        'line_total_incl_tax' => $op->total->amount(),
                    ]);

                $this->taxBuilder->createLineTaxes($invoice, $line, $op->taxes);

                $lines->push($line);
            }
        }

        return $lines;
    }

    /** {@inheritDoc} */
    public function createChargeLines(Invoice $invoice, Collection $orders, ?Collection $chargeIds = null): Collection
    {
        $lines = collect();
        $allowedChargeIds = $chargeIds?->map(fn ($id) => (int) $id)->values();

        foreach ($orders as $order) {
            /** @var OrderCharge $charge */
            foreach ($order->charges as $charge) {
                if (! is_null($allowedChargeIds) && ! $allowedChargeIds->contains((int) $charge->id)) {
                    continue;
                }

                $taxMetadata = $this->chargePolicyResolver->taxMetadata($charge);
                $taxAmount = collect($taxMetadata)->sum(fn (array $tax) => (float) ($tax['amount'] ?? 0));
                $lineTotalExclTax = $charge->amount->amount();
                $lineTotalInclTax = $lineTotalExclTax + $taxAmount;

                $line = InvoiceLine::query()->create([
                    'invoice_id' => $invoice->id,
                    'order_product_id' => null,
                    'order_charge_id' => $charge->id,
                    'description' => $charge->getTranslations('name'),
                    'sku' => null,
                    'currency' => $charge->currency,
                    'currency_rate' => $charge->currency_rate,
                    'unit_price' => $charge->unit_amount->amount(),
                    'quantity' => $charge->quantity,
                    'tax_amount' => $taxAmount,
                    'line_total_excl_tax' => $lineTotalExclTax,
                    'line_total_incl_tax' => $lineTotalInclTax,
                ]);

                $this->createChargeLineTaxes($invoice, $line, $charge, $taxMetadata);

                $lines->push($line);
            }
        }

        return $lines;
    }

    /**
     * Persist tax rows for a taxable charge line.
     *
     * @param  array<int,array<string,mixed>>  $taxMetadata
     */
    private function createChargeLineTaxes(Invoice $invoice, InvoiceLine $line, OrderCharge $charge, array $taxMetadata): void
    {
        if (! $this->chargePolicyResolver->isTaxable($charge)) {
            return;
        }

        foreach ($taxMetadata as $tax) {
            InvoiceTax::query()->create([
                'invoice_id' => $invoice->id,
                'invoice_line_id' => $line->id,
                'tax_id' => $tax['tax_id'] ?? null,
                'code' => $tax['code'] ?? 'charge_tax',
                'name' => $tax['name'] ?? ['en' => 'Charge Tax'],
                'rate' => (float) ($tax['rate'] ?? 0),
                'currency' => $tax['currency'] ?? $charge->currency,
                'currency_rate' => (float) ($tax['currency_rate'] ?? $charge->currency_rate),
                'amount' => (float) ($tax['amount'] ?? 0),
                'type' => isset($tax['type']) ? TaxType::from($tax['type']) : TaxType::Exclusive,
                'compound' => (bool) ($tax['compound'] ?? false),
            ]);
        }
    }
}
