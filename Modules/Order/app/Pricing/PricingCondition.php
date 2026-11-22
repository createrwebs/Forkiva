<?php

namespace Modules\Order\Pricing;

use Illuminate\Support\Arr;

final class PricingCondition
{
    public function __construct(private array $data) {}

    /**
     * Return the stable key used to register this calculation condition.
     */
    public function getName(): string
    {
        return (string) $this->data['name'];
    }

    /**
     * Return the category used to group calculation conditions.
     */
    public function getType(): string
    {
        return (string) $this->data['type'];
    }

    /**
     * Return the server-resolved fixed value or percentage expression.
     */
    public function getValue(): mixed
    {
        return $this->data['value'];
    }

    /**
     * Return context metadata attached to this condition.
     */
    public function getAttributes(): array
    {
        return $this->data['attributes'] ?? [];
    }

    /**
     * Read a nested context attribute with an optional fallback.
     */
    public function getAttribute($key, $default = null): mixed
    {
        return Arr::get($this->getAttributes(), $key, $default);
    }

    /**
     * Evaluate a fixed or percentage adjustment as a positive amount.
     */
    public function getCalculatedValue($base): float
    {
        $value = (string) $this->getValue();

        return abs(str_ends_with($value, '%') ? $base * (float) rtrim($value, '%') / 100 : (float) $value);
    }
}
