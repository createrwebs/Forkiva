<?php

namespace Modules\Order\Pricing;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Modules\Support\Money;

class PricingCharge implements Arrayable, JsonSerializable
{
    public function __construct(protected PricingCondition $condition) {}

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function toArray(): array
    {
        return [
            'code' => $this->code(),
            'name' => $this->name(),
            'amount' => $this->amount(),
            'translations' => $this->translations(),
            'meta' => $this->meta(),
        ];
    }

    public function code(): string
    {
        return (string) $this->condition->getAttribute('code');
    }

    public function name(): string
    {
        $translations = $this->translations();

        return (string) ($translations[app()->getLocale()]
            ?? $translations['en']
            ?? $this->condition->getAttribute('label')
            ?? $this->condition->getAttribute('code'));
    }

    public function translations(): array
    {
        return $this->condition->getAttribute('translations', []);
    }

    public function meta(): array
    {
        return $this->condition->getAttribute('meta', []);
    }

    public function amount(): Money
    {
        return new Money(
            (float) $this->condition->getAttribute('amount', 0),
            (string) $this->condition->getAttribute('currency', setting('default_currency'))
        );
    }
}
