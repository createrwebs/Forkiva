<?php

namespace Modules\Order\Pricing;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use JsonSerializable;
use Modules\Discount\Models\Discount;
use Modules\Order\Enums\DiscountType;
use Modules\Support\Money;
use Modules\Voucher\Models\Voucher;

class PricingDiscount implements Arrayable, JsonSerializable
{
    /**
     * Create a new instance of PricingDiscount
     */
    public function __construct(
        protected ?Pricing $cart = null,
        protected Discount|Voucher|null $discount = null,
        protected ?PricingCondition $condition = null
    ) {}

    /**
     * Get discount model
     */
    public function model(): Discount|Voucher|null
    {
        return $this->discount;
    }

    public function __toString()
    {
        return json_encode($this->jsonSerialize());
    }

    /** {@inheritDoc} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /** {@inheritDoc} */
    public function toArray(): array
    {
        return [
            'id' => $this->id(),
            'name' => $this->name(),
            'type' => $this->type()?->toTrans(),
            'value' => $this->value(),
            'loyaltyGift' => $this->loyaltyGift(),
        ];
    }

    /**
     * Get discount id
     */
    public function id(): ?int
    {
        return $this->discount?->id;
    }

    /**
     * Get discount name
     */
    public function name(): ?string
    {
        return $this->type() === DiscountType::Voucher ? $this->discount?->code : $this->discount?->name;
    }

    /**
     * Get discount type
     */
    public function type(): ?DiscountType
    {
        return DiscountType::getTypeFromModel($this->discount?->getMorphClass());
    }

    /**
     * Get discount value
     */
    public function value(): Money
    {
        $currency = $this->cart?->branch()->currency();

        $amount = $this->calculate();
        $subTotal = $this->cart?->subTotal()->amount();

        if ($amount > $subTotal) {
            $amount = $subTotal;
        }

        if ((float) $amount === 0.0) {
            $this->cart?->removeDiscount();
        }

        if (
            ! is_null($this->discount)
            && ! is_null($this->discount->max_discount)
            && $amount > $this->discount->max_discount->amount()
        ) {
            $amount = $this->discount->max_discount->amount();
        }

        return ! is_null($currency) ? new Money($amount, $currency) : Money::inDefaultCurrency($amount);
    }

    /**
     * Discount calculate
     */
    private function calculate(): float
    {
        if (is_null($this->discount)
            || ! $this->applicableCustomer()
            || $this->didNotSpendTheRequiredAmount()
            || $this->spentMoreThanMaximumAmount()
            || $this->usageLimitReached()) {
            return 0;
        }

        $amount = $this->discountApplicableProductsTotalPrice();

        if ((float) $amount === 0.0) {
            return 0;
        }

        return $this->condition
            ->getCalculatedValue($amount);
    }

    /**
     * Applicable for selected customer
     */
    public function applicableCustomer(): bool
    {
        if (empty($this->discount->meta['customer_id'])) {
            return true;
        }

        return (int) $this->discount->meta['customer_id'] === (int) $this->cart->customer()?->id();
    }

    /**
     * Did not spend the required amount
     */
    public function didNotSpendTheRequiredAmount(): bool
    {
        return $this->fresh()->discount->didNotSpendTheRequiredAmount($this->cart);
    }

    /**
     * Free discount
     *
     * @return $this
     */
    public function fresh(): static
    {
        $this->discount = $this->discount->refresh();

        return $this;
    }

    /**
     * Spent more than a maximum amount
     */
    public function spentMoreThanMaximumAmount(): bool
    {
        return $this->fresh()->discount->spentMoreThanMaximumAmount($this->cart);
    }

    /**
     * Usage limit reached
     */
    public function usageLimitReached(): bool
    {
        return $this->fresh()
            ->discount
            ->usageLimitReached(
                $this->cart->customer()?->id(),
                $this->cart->currentOrder()?->id()
            );
    }

    /**
     * Discount applicable products total price
     */
    private function discountApplicableProductsTotalPrice(): float
    {
        return $this->discountApplicableProducts()
            ->sum(function ($cartItem) {
                return $cartItem->subtotal()->amount();
            });
    }

    /**
     * Discount applicable products
     */
    private function discountApplicableProducts(): Collection
    {
        return $this->cart->items()
            ->filter(function ($cartItem) {
                return $this->inApplicableProducts($cartItem);
            })->filter(function ($cartItem) {
                return $this->inApplicableCategories($cartItem);
            });
    }

    /**
     * In applicable products
     */
    private function inApplicableProducts(PricingItem $cartItem): bool
    {
        $products = collect($this->discount->conditions['products'] ?? []);

        return $products->isEmpty() || $products->contains($cartItem->product->sku);
    }

    /**
     * In applicable categories
     */
    private function inApplicableCategories(PricingItem $cartItem): bool
    {
        $categories = collect($this->discount->conditions['categories'] ?? []);

        return $categories->isEmpty() || $categories->intersect($cartItem->product->categories->pluck('slug'))
            ->isNotEmpty();
    }

    /**
     * Get loyalty gift
     */
    public function loyaltyGift(): ?PricingLoyaltyGift
    {
        $loyaltyGift = $this->condition?->getAttribute('loyalty_gift');

        if (! is_null($loyaltyGift)) {
            return new PricingLoyaltyGift($loyaltyGift);
        }

        return null;
    }

    public function __get($attribute)
    {
        return $this->discount?->{$attribute};
    }

    /**
     * Determine if a current discount is available or not
     */
    public function isAvailable(): bool
    {
        return $this->value()->amount() > 0;
    }
}
