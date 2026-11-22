<?php

namespace Modules\Order\Pricing;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

class PricingOrder implements Arrayable, JsonSerializable
{
    /**
     * Create a new instance of PricingOrder
     */
    public function __construct(protected PricingCondition $condition) {}

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
        ];
    }

    public function id(): string
    {
        return $this->condition->getValue();
    }

    public function name(): string
    {
        return $this->condition->getName();
    }
}
