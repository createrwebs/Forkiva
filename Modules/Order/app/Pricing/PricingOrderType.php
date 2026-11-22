<?php

namespace Modules\Order\Pricing;

use Illuminate\Contracts\Support\Arrayable;
use JsonException;
use JsonSerializable;

class PricingOrderType implements Arrayable, JsonSerializable
{
    /**
     * Create a new instance of PricingOrderType
     */
    public function __construct(protected ?PricingCondition $condition) {}

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
            'id' => $this->value(),
            'name' => $this->name(),
        ];
    }

    public function value(): ?string
    {
        return $this->condition?->getValue();
    }

    public function name(): ?string
    {
        return $this->condition?->getName();
    }
}
