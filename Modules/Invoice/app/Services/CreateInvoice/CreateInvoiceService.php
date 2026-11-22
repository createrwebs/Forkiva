<?php

namespace Modules\Invoice\Services\CreateInvoice;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\DeliveryFeatureInterface;
use Modules\Core\Contracts\FinancialPostingBridgeInterface;
use Modules\Core\Events\GratuityCollected;
use Modules\Invoice\Enums\InvoiceKind;
use Modules\Invoice\Enums\InvoicePurpose;
use Modules\Invoice\Enums\InvoiceType;
use Modules\Invoice\Models\Invoice;
use Modules\Invoice\Models\InvoiceLine;
use Modules\Invoice\Services\DiscountBuilder\DiscountBuilderServiceInterface;
use Modules\Invoice\Services\InvoiceLineBuilder\InvoiceLineBuilderServiceInterface;
use Modules\Invoice\Services\InvoiceNumberGenerator\InvoiceNumberGeneratorServiceInterface;
use Modules\Invoice\Services\InvoicePartyBuilder\InvoicePartyBuilderServiceInterface;
use Modules\Invoice\Services\InvoiceTotalsCalculator\InvoiceTotalsCalculatorServiceInterface;
use Modules\Invoice\Services\PaymentAllocation\PaymentAllocationServiceInterface;
use Modules\Invoice\Services\TaxBuilder\TaxBuilderServiceInterface;
use Modules\Order\Enums\OrderChargeCode;
use Modules\Order\Enums\OrderProductStatus;
use Modules\Order\Models\Order;
use Throwable;

class CreateInvoiceService implements CreateInvoiceServiceInterface
{
    /**
     * Create a new CreateInvoiceService
     */
    public function __construct(
        protected InvoiceNumberGeneratorServiceInterface $numberGenerator,
        protected InvoicePartyBuilderServiceInterface $partyBuilder,
        protected InvoiceLineBuilderServiceInterface $lineBuilder,
        protected InvoiceTotalsCalculatorServiceInterface $totalsCalculator,
        protected TaxBuilderServiceInterface $taxBuilder,
        protected DiscountBuilderServiceInterface $discountBuilder,
        protected PaymentAllocationServiceInterface $paymentAllocator
    ) {}

    /** {@inheritDoc} */
    public function create(Order $order, InvoicePurpose $purpose = InvoicePurpose::Original): Invoice
    {
        return DB::transaction(function () use ($order, $purpose) {
            $orders = $this->getMergedOrders($order);
            $order = $orders->firstWhere('id', $order->id)
                ?? $order->load(['customer', 'branch']);

            $seller = $this->partyBuilder->createSeller($order->branch);
            $buyer = $this->partyBuilder->createBuyer($order->customer);

            $numberData = $this->numberGenerator->generate($order->branch);

            $invoice = Invoice::query()
                ->create([
                    'order_id' => $order->id,
                    'table_merge_id' => $order->table_merge_id,
                    'branch_id' => $order->branch_id,
                    'seller_party_id' => $seller->id,
                    'buyer_party_id' => $buyer?->id,
                    'type' => $order->customer->customer_type?->isCorporate()
                        ? InvoiceType::Standard
                        : InvoiceType::Simplified,
                    'purpose' => $purpose,
                    'invoice_kind' => InvoiceKind::Standard,
                    'currency' => $order->currency,
                    'currency_rate' => $order->currency_rate,
                    'invoice_number' => $numberData['number'],
                    'invoice_counter' => $numberData['counter'],
                    'subtotal' => 0,
                    'tax_total' => 0,
                    'discount_total' => 0,
                    'total' => 0,
                    'issued_at' => now(),
                ]);

            $lines = $this->lineBuilder->createLines($invoice, $orders);
            $this->taxBuilder->createInvoiceTaxes($invoice, $orders);
            $this->discountBuilder->createDiscounts($invoice, $orders);
            $this->totalsCalculator->applyTotals($invoice, $lines);
            $this->paymentAllocator->allocate($invoice, $orders);
            $this->recordAccountingInvoiceEvent($invoice->fresh());
            $this->recordGratuityCollections($invoice, $lines);

            return $invoice;
        });
    }

    /**
     * Get merged orders
     *
     * @return Collection<Order>
     */
    private function getMergedOrders(Order $order): Collection
    {
        $with = [
            'customer',
            'branch',
            'taxes.tax',
            'charges',
            'products' => fn ($query) => $query
                ->whereNotIn('status', [OrderProductStatus::Cancelled, OrderProductStatus::Refunded])
                ->with(['taxes.tax'])
                ->without(['options']),
            'payments',
            'discount',
        ];

        if (! $order->table_merge_id) {
            return collect([$order->load($with)]);
        }

        return Order::query()
            ->with($with)
            ->where('table_merge_id', $order->table_merge_id)
            ->get();
    }

    /**
     * Record the invoice source event for optional Accounting posting.
     */
    protected function recordAccountingInvoiceEvent(Invoice $invoice): void
    {
        try {
            app(FinancialPostingBridgeInterface::class)
                ->recordEvent('invoice',
                    $invoice->id,
                    'issued',
                    [
                        'branch_id' => $invoice->branch_id,
                        'invoice_number' => $invoice->invoice_number,
                        'order_id' => $invoice->order_id,
                        'currency' => $invoice->currency,
                        'currency_rate' => $invoice->currency_rate,
                        'subtotal' => $invoice->subtotal->amount(),
                        'tax_total' => $invoice->tax_total->amount(),
                        'discount_total' => $invoice->discount_total->amount(),
                        'rounding_adjustment' => $invoice->rounding_adjustment->amount(),
                        'total' => $invoice->total->amount(),
                        'issued_at' => $invoice->issued_at?->toDateTimeString(),
                    ]
                );
        } catch (Throwable) {
            // Accounting is optional and must not block invoice creation.
        }
    }

    /** @param Collection<int,InvoiceLine> $lines */
    protected function recordGratuityCollections(Invoice $invoice, Collection $lines): void
    {
        $deliveryFeature = app(DeliveryFeatureInterface::class);
        $deliveryEnabled = $deliveryFeature->enabled() && $deliveryFeature->orderDeliveryModelClass() !== null;
        $invoice->loadMissing([
            'order:id,waiter_id',
            ...($deliveryEnabled ? ['order.orderDelivery:id,order_id,driver_id'] : []),
        ]);

        foreach ($lines as $line) {
            $line->loadMissing('orderCharge:id,code');
            $code = OrderChargeCode::tryFrom((string) $line->orderCharge?->code);
            if (! in_array($code, [OrderChargeCode::Tip, OrderChargeCode::ServiceCharge], true)) {
                continue;
            }

            event(new GratuityCollected([
                'branch_id' => $invoice->branch_id,
                'source_type' => 'invoice_line',
                'source_id' => $line->id,
                'recipient_user_id' => $code === OrderChargeCode::Tip ? $invoice->order?->waiter_id : null,
                'delivery_driver_id' => $deliveryEnabled && $code === OrderChargeCode::Tip ? $invoice->order?->orderDelivery?->driver_id : null,
                'collection_type' => $code->value,
                'amount' => $line->getRawOriginal('line_total_excl_tax'),
                'currency' => $line->currency,
                'currency_rate' => $line->getRawOriginal('currency_rate'),
                'economic_date' => $invoice->issued_at?->toDateString(),
                'idempotency_key' => "invoice-gratuity-line-{$line->id}",
                'source_snapshot' => [
                    'source_version' => 1,
                    'invoice_id' => $invoice->id,
                    'order_id' => $invoice->order_id,
                    'order_charge_id' => $line->order_charge_id,
                    'invoice_line_id' => $line->id,
                    'recipient_user_id' => $code === OrderChargeCode::Tip ? $invoice->order?->waiter_id : null,
                    'delivery_driver_id' => $deliveryEnabled && $code === OrderChargeCode::Tip ? $invoice->order?->orderDelivery?->driver_id : null,
                ],
            ]));
        }
    }
}
