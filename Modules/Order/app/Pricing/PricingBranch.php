<?php

namespace Modules\Order\Pricing;

use Illuminate\Contracts\Support\Arrayable;
use JsonException;
use JsonSerializable;

class PricingBranch implements Arrayable, JsonSerializable
{
    /**
     * Create a new instance of PricingBranch
     */
    public function __construct(protected ?PricingCondition $condition = null) {}

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
            'currency' => $this->currency(),
        ];
    }

    public function id(): ?string
    {
        return $this->condition?->getValue();
    }

    public function name(): ?string
    {
        return $this->condition?->getName();
    }

    public function currency(): string
    {
        return $this->condition?->getAttribute('currency') ?: setting('default_currency');
    }
}
