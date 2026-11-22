<?php

namespace Modules\Order\Pricing;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

class PricingCustomer implements Arrayable, JsonSerializable
{
    /**
     * Create a new instance of PricingCustomer
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

    /**
     * Get customer id
     */
    public function id(): int
    {
        return (int) data_get($this->condition->getAttribute('customer'), 'id');
    }

    /**
     * Get customer name
     */
    public function name(): string
    {
        $customer = $this->condition->getAttribute('customer');
        $name = data_get($customer, 'name');
        $phone = data_get($customer, 'phone');

        return $phone ? "$name ($phone)" : (string) $name;
    }
}
