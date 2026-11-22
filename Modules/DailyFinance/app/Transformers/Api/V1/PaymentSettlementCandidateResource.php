<?php

namespace Modules\DailyFinance\Transformers\Api\V1;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Currency\Currency;
use Modules\DailyFinance\Services\PaymentSettlement\PaymentSettlementSource;
use Modules\Payment\Models\Payment;
use Modules\Support\Money;

/** @mixin Payment */
class PaymentSettlementCandidateResource extends JsonResource
{
    /**
     * Expose exact source decimals and a review hash; unrepresentable subunits remain unselectable.
     */
    public function toArray(Request $request): array
    {
        $raw = (string) $this->getRawOriginal('amount');
        $selectable = true;
        try {
            BigDecimal::of($raw)->toScale(Currency::subunit($this->currency), RoundingMode::UNNECESSARY);
        } catch (MathException) {
            $selectable = false;
        }
        $timezone = $this->relationLoaded('branch') ? $this->branch?->timezone : null;

        return [
            'id' => $this->id,
            'reference' => $this->order_reference_no,
            'transaction_id' => $this->transaction_id,
            'collected_at' => dateTimeFormat($this->received_at ?? $this->created_at, timezone: $timezone),
            'amount' => new Money(BigDecimal::of($raw)->toFloat(), $this->currency),
            'amount_decimal' => $raw,
            'selectable' => $selectable,
            'source_hash' => PaymentSettlementSource::hash($this->resource),
        ];
    }
}
