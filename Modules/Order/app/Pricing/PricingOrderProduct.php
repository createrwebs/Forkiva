<?php

namespace Modules\Order\Pricing;

use Illuminate\Contracts\Support\Arrayable;
use JsonException;
use JsonSerializable;
use Modules\Order\Enums\OrderProductStatus;

class PricingOrderProduct implements Arrayable, JsonSerializable
{
    /**
     * Create a new instance of CartOrderProductType
     */
    public function __construct(protected array $orderProduct) {}

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
            'status' => $this->status()->toTrans(),
        ];
    }

    public function id(): ?int
    {
        return $this->orderProduct['id'];
    }

    public function status(): OrderProductStatus
    {
        return $this->orderProduct['status'];
    }

    public function setStatus(OrderProductStatus $status): void
    {
        $this->orderProduct['status'] = $status;
    }

    public function setId(?int $id = null): void
    {
        $this->orderProduct['id'] = $id;
    }
}
