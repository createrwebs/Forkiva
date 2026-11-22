<?php

namespace Modules\Order\Pricing;

use Illuminate\Contracts\Support\Arrayable;
use JsonException;
use JsonSerializable;
use Modules\Support\Money;
use Modules\Tax\Enums\TaxType;
use Modules\Tax\Models\Tax;

class PricingTax implements Arrayable, JsonSerializable
{
    /**
     * Create a new instance of PricingTax
     */
    public function __construct(protected PricingItem|Pricing $cart,
        protected Tax $tax,
        protected string $currency,
        protected ?float $preCalculatedAmount = null) {}

    /**
     * @throws JsonException
     */
    public function __toString(): string
    {
        return json_encode($this->jsonSerialize(), JSON_THROW_ON_ERROR);
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
            'rate' => $this->rate(),
            'type' => $this->type()->value,
            'scope' => $this->cart instanceof PricingItem ? 'product' : 'global',
            'amount' => $this->amount(),
        ];
    }

    public function id(): int
    {
        return $this->tax->id;
    }

    public function name(): string
    {
        return $this->tax->name;
    }

    public function amount(): Money
    {
        return new Money($this->calculate(), $this->currency);
    }

    /**
     * Calculate numeric tax amount respecting type & compound flags.
     */
    private function calculate(): float
    {
        return $this->preCalculatedAmount ?? $this->fallbackCalculateSingle();
    }

    /**
     * Fallback calculate single
     */
    private function fallbackCalculateSingle(): float
    {
        if ($this->tax->type === TaxType::Inclusive) {
            $rate = (float) ($this->tax->rate ?? 0);

            return $this->cart->subtotal()->amount() * ($rate / 100);
        }

        return $this->cart->subtotal()->amount() * (((float) ($this->tax->rate ?? 0)) / 100);
    }

    public function type(): TaxType
    {
        return $this->tax->type;
    }

    public function compound(): bool
    {
        return $this->tax->compound;
    }

    public function rate(): float
    {
        return $this->tax->rate;
    }

    public function translationsName(): array
    {
        return $this->tax->getTranslations('name');
    }

    public function currency(): string
    {
        return $this->currency;
    }
}
