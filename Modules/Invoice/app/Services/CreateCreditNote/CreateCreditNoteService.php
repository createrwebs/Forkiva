<?php

namespace Modules\Invoice\Services\CreateCreditNote;

use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Core\Contracts\FinancialPostingBridgeInterface;
use Modules\Core\Events\GratuityCollectionReversed;
use Modules\Currency\Currency;
use Modules\Invoice\Enums\InvoiceKind;
use Modules\Invoice\Enums\InvoicePurpose;
use Modules\Invoice\Models\Invoice;
use Modules\Invoice\Models\InvoiceDiscount;
use Modules\Invoice\Models\InvoiceLine;
use Modules\Invoice\Models\InvoiceTax;
use Modules\Invoice\Services\FullOrderReversal\FullOrderReversalResolverServiceInterface;
use Modules\Invoice\Services\InvoiceNumberGenerator\InvoiceNumberGeneratorServiceInterface;
use Modules\Invoice\Services\InvoicePartyBuilder\InvoicePartyBuilderServiceInterface;
use Modules\Invoice\Services\InvoiceTotalsCalculator\InvoiceTotalsCalculatorServiceInterface;
use Modules\Invoice\Services\OrderChargeInvoicePolicy\OrderChargeInvoicePolicyResolverServiceInterface;
use Modules\Order\Enums\OrderChargeCode;
use Modules\Order\Enums\OrderProductStatus;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderCharge;
use Throwable;

class CreateCreditNoteService implements CreateCreditNoteServiceInterface
{
    /**
     * Create a new credit note service instance.
     */
    public function __construct(
        protected InvoiceNumberGeneratorServiceInterface $numberGenerator,
        protected InvoicePartyBuilderServiceInterface $partyBuilder,
        protected InvoiceTotalsCalculatorServiceInterface $totalsCalculator,
        protected FullOrderReversalResolverServiceInterface $fullOrderReversalResolver,
        protected OrderChargeInvoicePolicyResolverServiceInterface $chargePolicyResolver,
        protected FinancialPostingBridgeInterface $bridge
    ) {}

    /** {@inheritDoc} */
    public function create(Order $order, InvoicePurpose $purpose = InvoicePurpose::Return): ?Invoice
    {
        return DB::transaction(function () use ($order, $purpose) {
            $original = $this->getOriginalInvoice($order);

            if (is_null($original)) {
                return null;
            }

            $order->load([
                'customer',
                'branch',
                'taxes.tax',
                'products' => fn ($query) => $query
                    ->whereIn('status', [OrderProductStatus::Cancelled, OrderProductStatus::Refunded])
                    ->with(['taxes.tax'])
                    ->without(['options']),
                'charges',
                'payments',
                'discount',
            ]);

            $reversedProductIds = $this->eligibleProductIdsForCreditNote($order, $original);
            $eligibleChargeIds = collect();
            $isFullOrderReversal = $this->fullOrderReversalResolver->isFullOrderReversal($order);

            if ($isFullOrderReversal) {
                $eligibleChargeIds = $this->eligibleChargeIdsForCreditNote($order, $original);
            }

            if ($reversedProductIds->isEmpty() && $eligibleChargeIds->isEmpty()) {
                return null;
            }

            $seller = $this->partyBuilder->createSeller($order->branch);
            $buyer = $this->partyBuilder->createBuyer($order->customer);
            $numberData = $this->numberGenerator->generate($order->branch, 'CN');

            $creditNote = Invoice::query()->create([
                'order_id' => $order->id,
                'table_merge_id' => $order->table_merge_id,
                'branch_id' => $order->branch_id,
                'seller_party_id' => $seller->id,
                'buyer_party_id' => $buyer?->id,
                'type' => $original->type,
                'purpose' => $purpose,
                'invoice_kind' => InvoiceKind::CreditNote,
                'reference_invoice_id' => $original->id,
                'currency' => $original->currency,
                'currency_rate' => $original->getRawOriginal('currency_rate'),
                'invoice_number' => $numberData['number'],
                'invoice_counter' => $numberData['counter'],
                'subtotal' => 0,
                'tax_total' => 0,
                'discount_total' => 0,
                'total' => 0,
                'issued_at' => now(),
            ]);

            $lines = $this->createSnapshotLines(
                $creditNote,
                $original,
                $reversedProductIds,
                $eligibleChargeIds,
            );
            $this->createSnapshotInvoiceTaxes($creditNote, $original, $reversedProductIds);
            $this->createSnapshotDiscounts($creditNote, $original, $reversedProductIds);
            $this->totalsCalculator->applyTotals($creditNote, $lines);
            $this->assertWithinOriginalBalances($original);
            $this->recordAccountingCreditNoteEvent($creditNote->fresh());
            $this->reverseGratuityCollections($creditNote, $original, $lines);

            return $creditNote;
        }, attempts: 3);
    }

    /** @param Collection<int,InvoiceLine> $creditLines */
    private function reverseGratuityCollections(Invoice $creditNote, Invoice $original, Collection $creditLines): void
    {
        foreach ($creditLines as $creditLine) {
            $creditLine->loadMissing('orderCharge:id,code');
            $code = OrderChargeCode::tryFrom((string) $creditLine->orderCharge?->code);
            if (! in_array($code, [OrderChargeCode::Tip, OrderChargeCode::ServiceCharge], true)) {
                continue;
            }

            $originalLine = $original->lines()
                ->where('order_charge_id', $creditLine->order_charge_id)
                ->first();
            if (! $originalLine) {
                continue;
            }

            event(new GratuityCollectionReversed([
                'branch_id' => $creditNote->branch_id,
                'original_source_type' => 'invoice_line',
                'original_source_id' => $originalLine->id,
                'source_type' => 'invoice_credit_line',
                'source_id' => $creditLine->id,
                'collection_type' => $code->value,
                'amount' => $creditLine->getRawOriginal('line_total_excl_tax'),
                'currency' => $originalLine->currency,
                'currency_rate' => $originalLine->getRawOriginal('currency_rate'),
                'economic_date' => $creditNote->issued_at?->toDateString(),
                'idempotency_key' => "invoice-gratuity-line-reversal-{$creditLine->id}",
                'source_snapshot' => [
                    'source_version' => 1,
                    'credit_note_id' => $creditNote->id,
                    'reference_invoice_id' => $original->id,
                    'credit_note_line_id' => $creditLine->id,
                    'original_invoice_line_id' => $originalLine->id,
                ],
            ]));
        }
    }

    /**
     * Get original invoice
     */
    private function getOriginalInvoice(Order $order): ?Invoice
    {
        if ($order->table_merge_id) {
            return Invoice::query()
                ->where('table_merge_id', $order->table_merge_id)
                ->where('invoice_kind', InvoiceKind::Standard)
                ->where('purpose', InvoicePurpose::Original)
                ->latest('id')
                ->lockForUpdate()
                ->first();
        }

        return Invoice::query()
            ->where('order_id', $order->id)
            ->where('invoice_kind', InvoiceKind::Standard)
            ->where('purpose', InvoicePurpose::Original)
            ->latest('id')
            ->lockForUpdate()
            ->first();
    }

    /**
     * Resolve refundable product ids for the credit note while preventing duplicates.
     *
     * @return Collection<int,int>
     */
    private function eligibleProductIdsForCreditNote(Order $order, Invoice $originalInvoice): Collection
    {
        $alreadyCreditedProductIds = InvoiceLine::query()
            ->whereHas('invoice', function ($query) use ($order, $originalInvoice) {
                $query->where('invoice_kind', InvoiceKind::CreditNote)
                    ->where('reference_invoice_id', $originalInvoice->id)
                    ->where(function ($nested) use ($order) {
                        $nested->where('order_id', $order->id);

                        if (! is_null($order->table_merge_id)) {
                            $nested->orWhere('table_merge_id', $order->table_merge_id);
                        }
                    });
            })
            ->whereNotNull('order_product_id')
            ->pluck('order_product_id');

        return $order->products
            ->reject(fn ($product) => $alreadyCreditedProductIds->contains($product->id))
            ->pluck('id')
            ->values();
    }

    /**
     * Resolve refundable charge ids for the credit note while preventing duplicates.
     *
     * @return Collection<int,int>
     */
    private function eligibleChargeIdsForCreditNote(Order $order, Invoice $originalInvoice): Collection
    {
        $alreadyCreditedChargeIds = InvoiceLine::query()
            ->whereHas('invoice', function ($query) use ($order, $originalInvoice) {
                $query->where('invoice_kind', InvoiceKind::CreditNote)
                    ->where('reference_invoice_id', $originalInvoice->id)
                    ->where(function ($nested) use ($order) {
                        $nested->where('order_id', $order->id);

                        if (! is_null($order->table_merge_id)) {
                            $nested->orWhere('table_merge_id', $order->table_merge_id);
                        }
                    });
            })
            ->whereNotNull('order_charge_id')
            ->pluck('order_charge_id');

        return $order->charges
            ->filter(fn (OrderCharge $charge) => $this->chargePolicyResolver->shouldRefundOnFullOrderReversal($charge))
            ->reject(fn (OrderCharge $charge) => $alreadyCreditedChargeIds->contains($charge->id))
            ->pluck('id')
            ->values();
    }

    /**
     * Clone eligible product and charge lines from the immutable referenced invoice.
     *
     * @param  Collection<int,int>  $productIds
     * @param  Collection<int,int>  $chargeIds
     * @return Collection<int,InvoiceLine>
     */
    private function createSnapshotLines(
        Invoice $creditNote,
        Invoice $original,
        Collection $productIds,
        Collection $chargeIds,
    ): Collection {
        $originalLines = $original->lines()
            ->where(function ($query) use ($productIds, $chargeIds) {
                $query->when(
                    $productIds->isNotEmpty(),
                    fn ($nested) => $nested->orWhereIn('order_product_id', $productIds),
                );
                $query->when(
                    $chargeIds->isNotEmpty(),
                    fn ($nested) => $nested->orWhereIn('order_charge_id', $chargeIds),
                );
            })
            ->orderBy('id')
            ->get();

        return $originalLines->map(function (InvoiceLine $originalLine) use ($creditNote) {
            $line = InvoiceLine::query()
                ->create([
                    'invoice_id' => $creditNote->id,
                    'order_product_id' => $originalLine->order_product_id,
                    'order_charge_id' => $originalLine->order_charge_id,
                    'description' => $originalLine->getTranslations('description'),
                    'sku' => $originalLine->sku,
                    'currency' => $originalLine->currency,
                    'currency_rate' => $originalLine->currency_rate,
                    'unit_price' => $originalLine->getRawOriginal('unit_price'),
                    'quantity' => $originalLine->quantity,
                    'tax_amount' => $originalLine->getRawOriginal('tax_amount'),
                    'line_total_excl_tax' => $originalLine->getRawOriginal('line_total_excl_tax'),
                    'line_total_incl_tax' => $originalLine->getRawOriginal('line_total_incl_tax'),
                ]);

            InvoiceTax::query()
                ->where('invoice_id', $originalLine->invoice_id)
                ->where('invoice_line_id', $originalLine->id)
                ->orderBy('id')
                ->get()
                ->each(fn (InvoiceTax $tax) => $this->cloneTax($tax, $creditNote, $line));

            return $line;
        });
    }

    /**
     * Clone one immutable invoice-tax snapshot.
     */
    private function cloneTax(
        InvoiceTax $tax,
        Invoice $creditNote,
        ?InvoiceLine $line,
        ?float $amount = null,
    ): InvoiceTax {
        return InvoiceTax::query()
            ->create([
                'invoice_id' => $creditNote->id,
                'invoice_line_id' => $line?->id,
                'tax_id' => $tax->tax_id,
                'name' => $tax->getTranslations('name'),
                'code' => $tax->code,
                'rate' => $tax->rate,
                'currency' => $tax->currency,
                'currency_rate' => $tax->currency_rate,
                'amount' => $amount ?? $tax->getRawOriginal('amount'),
                'type' => $tax->type,
                'compound' => $tax->compound,
            ]);
    }

    /**
     * Allocate invoice-level tax snapshots to the reversed original product lines.
     *
     * @param  Collection<int,int>  $productIds
     */
    private function createSnapshotInvoiceTaxes(
        Invoice $creditNote,
        Invoice $original,
        Collection $productIds,
    ): void {
        $allocation = $this->snapshotAllocation($original, $productIds);

        $original->taxes()
            ->whereNull('invoice_line_id')
            ->orderBy('id')
            ->get()
            ->each(function (InvoiceTax $tax) use ($creditNote, $original, $allocation) {
                $alreadyCredited = (float) InvoiceTax::query()
                    ->whereNull('invoice_line_id')
                    ->where('tax_id', $tax->tax_id)
                    ->where('code', $tax->code)
                    ->whereHas('invoice', fn ($query) => $query
                        ->where('invoice_kind', InvoiceKind::CreditNote)
                        ->where('reference_invoice_id', $original->id)
                        ->whereKeyNot($creditNote->id))
                    ->sum('amount');
                $amount = $this->allocatedSnapshotAmount(
                    (float) $tax->getRawOriginal('amount'),
                    $alreadyCredited,
                    $allocation,
                    $tax->currency,
                );

                if ($amount > 0) {
                    $this->cloneTax($tax, $creditNote, null, $amount);
                }
            });
    }

    /**
     * Determine the immutable invoice-line allocation and whether this is the final product reversal.
     *
     * @param  Collection<int,int>  $productIds
     * @return array{ratio:float,final:bool}
     */
    private function snapshotAllocation(Invoice $original, Collection $productIds): array
    {
        $originalProductLines = $original->lines()
            ->whereNotNull('order_product_id')
            ->get(['order_product_id', 'line_total_excl_tax']);
        $originalProductIds = $originalProductLines->pluck('order_product_id')->map(fn ($id) => (int) $id);
        $selectedSubtotal = $originalProductLines
            ->whereIn('order_product_id', $productIds)
            ->sum(fn (InvoiceLine $line) => (float) $line->getRawOriginal('line_total_excl_tax'));
        $totalSubtotal = $originalProductLines
            ->sum(fn (InvoiceLine $line) => (float) $line->getRawOriginal('line_total_excl_tax'));
        $creditedProductIds = InvoiceLine::query()
            ->whereNotNull('order_product_id')
            ->whereHas('invoice', fn ($query) => $query
                ->where('invoice_kind', InvoiceKind::CreditNote)
                ->where('reference_invoice_id', $original->id))
            ->pluck('order_product_id')
            ->map(fn ($id) => (int) $id)
            ->merge($productIds->map(fn ($id) => (int) $id))
            ->unique();

        return [
            'ratio' => $totalSubtotal > 0 ? $selectedSubtotal / $totalSubtotal : 0.0,
            'final' => $originalProductIds->diff($creditedProductIds)->isEmpty(),
        ];
    }

    /**
     * Resolve a proportional snapshot amount without exceeding its remaining balance.
     *
     * @param  array{ratio:float,final:bool}  $allocation
     */
    private function allocatedSnapshotAmount(
        float $originalAmount,
        float $alreadyCredited,
        array $allocation,
        string $currency,
    ): float {
        $remaining = max(round($originalAmount - $alreadyCredited, 4), 0);

        if ($allocation['final']) {
            return $remaining;
        }

        return min(
            round($originalAmount * $allocation['ratio'], Currency::subunit($currency)),
            $remaining,
        );
    }

    /**
     * Allocate discount snapshots to the reversed original product lines.
     *
     * @param  Collection<int,int>  $productIds
     */
    private function createSnapshotDiscounts(
        Invoice $creditNote,
        Invoice $original,
        Collection $productIds,
    ): void {
        $allocation = $this->snapshotAllocation($original, $productIds);

        $original->discounts()
            ->orderBy('id')
            ->get()
            ->each(function (InvoiceDiscount $discount) use ($creditNote, $original, $allocation) {
                $alreadyCredited = (float) InvoiceDiscount::query()
                    ->where('source', $discount->source)
                    ->where('discountable_type', $discount->discountable_type)
                    ->where('discountable_id', $discount->discountable_id)
                    ->whereHas('invoice', fn ($query) => $query
                        ->where('invoice_kind', InvoiceKind::CreditNote)
                        ->where('reference_invoice_id', $original->id)
                        ->whereKeyNot($creditNote->id))
                    ->sum('amount');
                $amount = $this->allocatedSnapshotAmount(
                    (float) $discount->getRawOriginal('amount'),
                    $alreadyCredited,
                    $allocation,
                    $discount->currency,
                );

                if ($amount <= 0) {
                    return;
                }

                InvoiceDiscount::query()
                    ->create([
                        'invoice_id' => $creditNote->id,
                        'discountable_type' => $discount->discountable_type,
                        'discountable_id' => $discount->discountable_id,
                        'source' => $discount->source,
                        'name' => $discount->getTranslations('name'),
                        'currency' => $discount->currency,
                        'currency_rate' => $discount->currency_rate,
                        'amount' => $amount,
                        'applied_before_tax' => $discount->applied_before_tax,
                    ]);
            });
    }

    /**
     * Enforce aggregate credit-note balances against the referenced invoice.
     */
    private function assertWithinOriginalBalances(Invoice $original): void
    {
        foreach (['subtotal', 'tax_total', 'discount_total', 'total'] as $column) {
            $credited = (float) Invoice::query()
                ->where('invoice_kind', InvoiceKind::CreditNote)
                ->where('reference_invoice_id', $original->id)
                ->sum($column);
            $limit = (float) $original->getRawOriginal($column);

            if ($credited > $limit + 0.0001) {
                throw new DomainException("Credit notes exceed the referenced invoice $column balance.");
            }
        }
    }

    /**
     * Record an issued credit note without coupling refunds to Accounting.
     */
    protected function recordAccountingCreditNoteEvent(Invoice $creditNote): void
    {
        $payload = [
            'branch_id' => $creditNote->branch_id,
            'invoice_number' => $creditNote->invoice_number,
            'invoice_kind' => $creditNote->invoice_kind?->value,
            'purpose' => $creditNote->purpose?->value,
            'reference_invoice_id' => $creditNote->reference_invoice_id,
            'order_id' => $creditNote->order_id,
            'currency' => $creditNote->currency,
            'currency_rate' => $creditNote->currency_rate,
            'subtotal' => $creditNote->subtotal->amount(),
            'tax_total' => $creditNote->tax_total->amount(),
            'discount_total' => $creditNote->discount_total->amount(),
            'rounding_adjustment' => $creditNote->rounding_adjustment->amount(),
            'total' => $creditNote->total->amount(),
            'issued_at' => $creditNote->issued_at?->toDateTimeString(),
            'idempotency_key' => "invoice:$creditNote->id:issued",
        ];

        try {
            $this->bridge->recordEvent(
                'invoice',
                $creditNote->id,
                'issued',
                $payload,
            );
        } catch (Throwable $exception) {
            try {
                $this->bridge->recordFailure(
                    'invoice',
                    $creditNote->id,
                    'issued',
                    class_basename($exception),
                    $exception->getMessage(),
                    $payload,
                );
            } catch (Throwable $diagnosticException) {
                Log::error('Unable to record credit note Accounting failure.', [
                    'credit_note_id' => $creditNote->id,
                    'idempotency_key' => $payload['idempotency_key'],
                    'posting_error' => $exception->getMessage(),
                    'diagnostic_error' => $diagnosticException->getMessage(),
                ]);
            }
        }
    }
}
