<?php

namespace Modules\Order\Pricing;

use Illuminate\Support\Collection;
use JsonSerializable;
use Modules\Option\Models\Option;
use Modules\Option\Models\OptionValue;
use Modules\Product\Models\Product;
use Modules\Support\Enums\PriceType;
use Modules\Support\Money;
use Modules\Tax\Enums\TaxType;
use Modules\Tax\Models\Tax;
use stdClass;

class PricingItem implements JsonSerializable
{
    /**
     * The ID of the calculation item.
     */
    public string $id;

    /**
     * Quantity of the calculation item.
     */
    public int $qty;

    /**
     * Underlying product of the calculation item.
     */
    public Product|PricingProductSnapshot $product;

    public Product|PricingProductSnapshot $item;

    /**
     * Options of the calculation item.
     */
    public Collection $options;

    /**
     * Taxes of the calculation item.
     */
    public Collection $taxes;

    /**
     * Actions of the calculation item.
     */
    public array $actions;

    /**
     * Order product
     */
    public ?PricingOrderProduct $orderProduct = null;

    /**
     * Loyalty gift
     */
    public ?PricingLoyaltyGift $loyaltyGift = null;

    /**
     * Create a new instance of PricingItem
     */
    public function __construct(protected Pricing $cart, protected \Illuminate\Support\Fluent $entry)
    {
        $attributes = $entry->get('attributes');
        $this->id = $entry->get('id');
        $this->qty = $entry->get('quantity');
        $this->product = $attributes['product'];
        $this->item = $attributes['item'];
        $this->options = $attributes['options'];
        $this->actions = $attributes['actions'] ?? [];
        if (isset($attributes['order_product'])) {
            $this->orderProduct = new PricingOrderProduct($attributes['order_product']);
        }

        if (isset($attributes['loyalty_gift'])) {
            $this->loyaltyGift = new PricingLoyaltyGift($attributes['loyalty_gift']);
        }

        $this->taxes = $this->product
            ->taxes
            ->filter(fn (Tax $tax) => $this->cart->isTaxApplicable($tax))
            ->values();
    }

    /**
     * @return false|string
     */
    public function __toString()
    {
        return json_encode($this->jsonSerialize());
    }

    public function jsonSerialize(): array
    {
        $data = [
            'line_id' => $this->entry->get('attributes')['line_id'] ?? $this->id,
            'option_input' => $this->entry->get('attributes')['option_input'] ?? [],
            'catalog_product' => $this->entry->get('attributes')['catalog_product'] ?? null,
            'id' => $this->id,
            'qty' => $this->qty,
            'item' => [
                'id' => $this->item->id,
                'name' => $this->item->name,
                'thumbnail' => $this->item->thumbnail?->preview_image_url,
            ],
            'actions' => array_values($this->actions),
            'options' => $this->options->isNotEmpty()
                ? $this
                    ->options
                    ->keyBy('order')
                    ->map(fn (Option $option) => [
                        'id' => $option->id,
                        'name' => $option->name,
                        'values' => $option
                            ->values
                            ->map(fn (OptionValue $value) => [
                                'id' => $value->id,
                                'label' => $value->label,
                                'price' => $value->price,
                            ]),
                    ])
                : new stdClass,
            'loyaltyGift' => $this->loyaltyGift,
            'unitPrice' => $this->unitPrice(),
            'subtotal' => $this->subtotal(),
            'taxTotal' => $this->taxTotal(),
            'taxes' => $this->taxes(),
            'total' => $this->total(),
        ];
        if (! is_null($this->orderProduct)) {
            $data['orderProduct'] = $this->orderProduct;
        }

        return $data;
    }

    /**
     * Calculate the unit price of the calculation item.
     */
    public function unitPrice(): Money
    {
        return $this->hasGifted()
            ? new Money(0, $this->item->selling_price->currency())
            : $this->item->selling_price->add($this->optionsPrice());
    }

    /**
     * Calculate the unit price before tax.
     */
    public function netUnitPrice(): Money
    {
        return $this->qty > 0
            ? $this->subtotal()->divide($this->qty)
            : new Money(0, $this->product->currency);
    }

    /**
     * Check if any this item is gifted
     */
    public function hasGifted(): bool
    {
        return ! is_null($this->loyaltyGift);
    }

    /**
     * Calculate the price of the options
     * of the calculation item.
     */
    public function optionsPrice(): Money
    {
        return new Money($this->calculateOptionsPrice(), $this->product->currency);
    }

    /**
     * Calculate the price of the options.
     */
    private function calculateOptionsPrice(): float
    {
        return (float) $this->options
            ->sum(fn ($option) => $this->sumOfThePricesOfTheValuesOf($option));
    }

    /**
     * Calculate the sum of the prices of the
     * values of the given option.
     */
    private function sumOfThePricesOfTheValuesOf($option): float
    {
        return (float) $option->values
            ->sum(function ($value) {
                return $value->price_type === PriceType::Fixed
                    ? $value->price->amount()
                    : take_percent(
                        $value->price,
                        $this->item->selling_price->amount()
                    );
            });
    }

    /**
     * Calculate the subtotal price of the calculation item.
     */
    public function subtotal(): Money
    {
        $gross = $this->unitPrice()->multiply($this->qty);
        $inclusiveRate = $this->inclusiveTaxes()->sum(fn (Tax $tax) => (float) ($tax->rate ?? 0));

        if ($inclusiveRate <= 0) {
            return $gross;
        }

        return $gross->divide(1 + ($inclusiveRate / 100));
    }

    /**
     * Calculate the total price of the calculation item.
     */
    public function taxTotal(): Money
    {
        return new Money(
            $this->taxes()->sum(fn (PricingTax $cartTax) => $cartTax->amount()->amount()),
            $this->product->currency
        );
    }

    /**
     * Retrieve all applied tax details as PricingTax objects.
     *
     * @return Collection<PricingTax>
     */
    public function taxes(): Collection
    {
        if (! $this->hasTax()) {
            return collect();
        }

        $ordered = $this->taxes->values()->sortBy(fn (Tax $t) => (int) ($t->compound));
        $base = $this->subtotal()->amount();
        $result = collect();

        foreach ($ordered as $tax) {
            if ($tax->type === TaxType::Inclusive) {
                $amount = $base * (((float) ($tax->rate ?? 0)) / 100);

                $result->push(
                    new PricingTax(
                        cart: $this,
                        tax: $tax,
                        currency: $this->product->currency,
                        preCalculatedAmount: $amount
                    )
                );

                continue;
            }

            $rate = (float) ($tax->rate ?? 0);
            $taxableBase = $tax->compound
                ? $base + $result->sum(fn (PricingTax $cartTax) => $cartTax->amount()->amount())
                : $base;
            $amount = $taxableBase * ($rate / 100);

            $result->push(
                new PricingTax(
                    cart: $this,
                    tax: $tax,
                    currency: $this->product->currency,
                    preCalculatedAmount: $amount
                )
            );
        }

        return $result->values();
    }

    /**
     * Get all inclusive taxes embedded in this item's displayed price.
     *
     * Product taxes are stored on the line while global taxes are stored on
     * the order, but both must be extracted from the same catalog price.
     *
     * @return Collection<Tax>
     */
    private function inclusiveTaxes(): Collection
    {
        return $this->taxes
            ->merge($this->cart->globalInclusiveTaxes())
            ->filter(fn (Tax $tax) => $tax->type === TaxType::Inclusive)
            ->unique('id')
            ->values();
    }

    /**
     * Check if any tax condition exists in the calculation.
     */
    public function hasTax(): bool
    {
        return $this->taxes->isNotEmpty();
    }

    /**
     * Calculate the total price of the calculation item.
     */
    public function total(): Money
    {
        return $this->subtotal()->add($this->taxTotal());
    }
}
