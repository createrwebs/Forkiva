<?php

namespace Modules\Order\Pricing;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

class PricingDeliveryContext implements Arrayable, JsonSerializable
{
    public function __construct(protected PricingCondition $condition) {}

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function toArray(): array
    {
        return [
            'customerAddress' => $this->customerAddress(),
            'resolvedZone' => $this->resolvedZone(),
            'pricing' => $this->pricing(),
            'deliveryMethod' => $this->deliveryMethod(),
            'paymentCollectionChannel' => $this->paymentCollectionChannel(),
            'note' => $this->note(),
            'status' => $this->status(),
            'dispatchStatus' => $this->dispatchStatus(),
        ];
    }

    public function customerAddress(): ?array
    {
        return $this->condition->getAttribute('customer_address');
    }

    public function deliveryMethod(): ?string
    {
        return $this->condition->getAttribute('delivery_method');
    }

    public function resolvedZone(): ?array
    {
        return $this->condition->getAttribute('resolved_zone');
    }

    public function pricing(): ?array
    {
        return $this->condition->getAttribute('pricing');
    }

    public function pricingInputHash(): ?string
    {
        return $this->condition->getAttribute('pricing_input_hash');
    }

    public function paymentCollectionChannel(): ?string
    {
        return $this->condition->getAttribute('payment_collection_channel');
    }

    public function note(): ?string
    {
        return $this->condition->getAttribute('note');
    }

    public function status(): ?string
    {
        return $this->condition->getAttribute('status');
    }

    public function dispatchStatus(): ?string
    {
        return $this->condition->getAttribute('dispatch_status');
    }
}
