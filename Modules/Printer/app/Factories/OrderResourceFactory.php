<?php

namespace Modules\Printer\Factories;

use Illuminate\Support\Collection;
use Modules\Branch\Models\Branch;
use Modules\Currency\Currency;
use Modules\Invoice\Models\InvoiceDiscount;
use Modules\Invoice\Models\InvoiceLine;
use Modules\Invoice\Models\InvoiceParty;
use Modules\Invoice\Models\InvoiceTax;
use Modules\Option\Models\OptionValue;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderCharge;
use Modules\Order\Models\OrderDiscount;
use Modules\Order\Models\OrderProduct;
use Modules\Order\Models\OrderProductOption;
use Modules\Order\Models\OrderTax;
use Modules\Payment\Models\Payment;
use Modules\Payment\Models\PaymentAllocation;
use Modules\SeatingPlan\Models\Table;
use Modules\Support\Money;
use Modules\Tax\Support\TaxSummary;
use Modules\User\Models\User;

class OrderResourceFactory
{
    /**
     * Get order resource
     */
    public static function order(Order $order, bool $forKitchen = false): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'reference_no' => $order->reference_no,
            'type' => $order->type->trans(),
            'due_amount' => $order->due_amount->round()->amount(),
            'notes' => $order->notes,
            'car_plate' => $order->car_plate,
            'car_description' => $order->car_description,
            'scheduled_at' => dateTimeFormat($order->scheduled_at),
            'order_date' => dateTimeFormat($order->created_at),
            'currency_subunit' => Currency::subunit($order->currency),
            ...(! $forKitchen ? [
                'subtotal' => $order->subtotal->round()->amount(),
                'charge_total' => $order->charge_total->round()->amount(),
                'total' => $order->total->round()->amount(),
            ] : []),
        ];
    }

    /**
     * Get order branch resource
     */
    public static function branch(Branch $branch): array
    {
        return [
            'id' => $branch->id,
            'name' => $branch->name,
            'legal_name' => $branch->legal_name,
            'address_line1' => $branch->address_line1,
            'phone' => $branch->phone,
            'email' => $branch->email,
            'registration_number' => $branch->registration_number,
            'postal_code' => $branch->postal_code,
            'logo' => getLogoBase64(),
        ];
    }

    /**
     * Get order customer resource
     */
    public static function customer(Order $order): array
    {
        return [
            'id' => $order->customer?->id,
            'name' => $order->getCustomerName(),
            'phone' => $order->customer?->phone,
            'email' => $order->customer?->email,
        ];
    }

    /**
     * Get order table resource
     */
    public static function table(Table $table): array
    {
        return [
            'id' => $table->id,
            'name' => $table->name,
        ];
    }

    /**
     * Get order waiter resource
     */
    public static function waiter(User $waiter): array
    {
        return [
            'id' => $waiter->id,
            'name' => $waiter->name,
        ];
    }

    /**
     * Get order discount resource
     */
    public static function discount(OrderDiscount $discount): array
    {
        return [
            'id' => $discount->id,
            'name' => $discount->name,
            'amount' => $discount->amount->round()->amount(),
        ];
    }

    /**
     * Get order charge resource.
     */
    public static function charge(OrderCharge $charge): array
    {
        return [
            'id' => $charge->id,
            'code' => $charge->code,
            'name' => $charge->name,
            'quantity' => $charge->quantity,
            'unit_amount' => $charge->unit_amount->round()->amount(),
            'amount' => $charge->amount->round()->amount(),
            'meta' => $charge->meta,
        ];
    }

    /**
     * Get order payment resource
     */
    public static function payment(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'transaction_id' => $payment->transaction_id,
            'method' => $payment->method->trans(),
            'amount' => $payment->amount->round()->amount(),
            'type' => $payment->type->name,
        ];
    }

    /**
     * Get order product resource
     */
    public static function product(OrderProduct $product, bool $forKitchen = false): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'quantity' => $product->quantity,
            ...(! $forKitchen
                ? [
                    'unit_price' => $product->unit_price->round()->amount(),
                    'subtotal' => $product->subtotal->round()->amount(),
                    'tax_total' => $product->tax_total->round()->amount(),
                    'total' => $product->total->round()->amount(),
                    'taxes' => $product->taxes->map(fn (OrderTax $orderTax) => static::tax($orderTax)),
                ]
                : []
            ),
            'options' => $product->options->map(fn (OrderProductOption $productOption) => static::productOption($productOption, $forKitchen)),
        ];
    }

    /**
     * Get order tax resource
     */
    public static function tax(OrderTax $orderTax): array
    {
        return [
            'id' => $orderTax->id,
            'tax_id' => $orderTax->tax_id,
            'name' => $orderTax->name,
            'rate' => $orderTax->rate,
            'type' => $orderTax->type->value,
            'scope' => is_null($orderTax->order_product_id) ? 'global' : 'product',
            'amount' => $orderTax->amount->round()->amount(),
        ];
    }

    /**
     * Build a printable order tax summary from product and global tax records.
     */
    public static function orderTaxSummary(Collection $taxes): Collection
    {
        return static::printableTaxSummary(
            TaxSummary::summarize($taxes, 'order_product_id')
        );
    }

    /**
     * Get order product options resource
     */
    public static function productOption(OrderProductOption $productOption, bool $forKitchen = false): array
    {
        return [
            'id' => $productOption->id,
            'name' => $productOption->name,
            'values' => $productOption->values
                ->map(fn (OptionValue $productOptionValue) => [
                    'id' => $productOptionValue->id,
                    'label' => $productOption->values->count() === 1 && ! is_null($productOption->value)
                        ? $productOption->value
                        : $productOptionValue->label,
                    ...(! $forKitchen ? ['price' => $productOptionValue->pivot->price->round()->amount()] : []),
                ]),
        ];
    }

    /**
     * Get invoice party resource
     */
    public static function invoiceParty(InvoiceParty $party): array
    {
        return [
            'id' => $party->id,
            'type' => $party->type->toTrans(),
            'legal_name' => $party->legal_name,
            'vat_tin' => $party->vat_tin,
            'cr_number' => $party->cr_number,
            'address_line1' => $party->address_line1,
            'address_line2' => $party->address_line2,
            'city' => $party->city,
            'state' => $party->state,
            'country' => ! is_null($party->country_code)
                ? [
                    'code' => $party->country_code,
                    'name' => $party->country_name,
                ]
                : null,
            'postal_code' => $party->postal_code,
            'phone' => $party->phone,
            'email' => $party->email,
        ];
    }

    /**
     * Get invoice line resource
     */
    public static function invoiceLine(InvoiceLine $line): array
    {
        return [
            'id' => $line->id,
            'description' => $line->description,
            'sku' => $line->sku,
            'unit_price' => $line->unit_price->round()->amount(),
            'quantity' => $line->quantity,
            'tax_amount' => $line->tax_amount->round()->amount(),
            'taxes' => $line->taxes->map(fn (InvoiceTax $tax) => static::invoiceTax($tax)),
            'line_total_excl_tax' => $line->line_total_excl_tax->round()->amount(),
            'line_total_incl_tax' => $line->line_total_incl_tax->round()->amount(),
        ];
    }

    /**
     * Get payment allocation resource
     */
    public static function paymentAllocation(PaymentAllocation $allocation): array
    {
        return [
            'id' => $allocation->id,
            'amount' => $allocation->amount->round()->amount(),
            'payment' => [
                'id' => $allocation->payment->id,
                'method' => $allocation->payment->method->trans(),
                'type' => $allocation->payment->type->value,
                'transaction_id' => $allocation->payment->transaction_id,
            ],
        ];
    }

    /**
     * Get invoice tax resource
     */
    public static function invoiceTax(InvoiceTax $tax): array
    {
        return [
            'id' => $tax->id,
            'name' => $tax->name,
            'code' => $tax->code,
            'rate' => $tax->rate,
            'type' => $tax->type->value,
            'scope' => is_null($tax->invoice_line_id) ? 'global' : 'product',
            'amount' => $tax->amount->round()->amount(),
        ];
    }

    /**
     * Build a printable invoice tax summary from line and global tax records.
     */
    public static function invoiceTaxSummary(Collection $taxes): Collection
    {
        return static::printableTaxSummary(
            TaxSummary::summarize($taxes, 'invoice_line_id')
        );
    }

    /**
     * Format normalized tax amounts for thermal printing.
     */
    private static function printableTaxSummary(Collection $taxes): Collection
    {
        return $taxes->map(fn (array $tax) => [
            ...$tax,
            'amount' => (new Money($tax['amount'], $tax['currency']))->round()->amount(),
        ]);
    }

    /**
     * Get invoice discount resource
     */
    public static function invoiceDiscount(InvoiceDiscount $discount): array
    {
        return [
            'id' => $discount->id,
            'name' => $discount->name,
            'amount' => $discount->amount->round()->amount(),
        ];
    }
}
